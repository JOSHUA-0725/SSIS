<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\AuditLog;
use App\Models\ClassSection;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\Program;
use App\Models\QueueTicket;
use App\Models\Role;
use App\Models\SlotRequest;
use App\Models\Student;
use App\Models\User;
use App\Services\ClearanceService;
use App\Services\DocumentRequestService;
use App\Services\EnrollmentQueueService;
use App\Services\EnrollmentService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RegistrarController extends Controller
{
    public function students(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $students = Student::with('program')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('student_no', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('first_name', 'like', "%{$q}%")))
            ->orderBy('last_name')->paginate(15)->withQueryString();

        return view('registrar.students', compact('students', 'q'));
    }

    public function createStudent()
    {
        return view('registrar.student-create', ['programs' => Program::orderBy('code')->get()]);
    }

    public function storeStudent(Request $request)
    {
        $data = $request->validate([
            'student_no' => ['required', 'regex:/^\d{4}-\d{5}$/', 'unique:students,student_no', 'unique:users,username'],
            'last_name' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:50'],
            'middle_name' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'sex' => ['nullable', Rule::in(['M', 'F'])],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'contact_no' => ['nullable', 'string', 'max:15'],
            'address' => ['nullable', 'string', 'max:255'],
            'program_id' => ['required', 'exists:programs,id'],
            'year_level' => ['required', 'integer', 'between:1,5'],
        ], ['student_no.regex' => 'Student number must look like 2026-00123.']);

        $password = Str::password(10, symbols: false);

        $student = DB::transaction(function () use ($data, $password) {
            $user = User::create([
                'username' => $data['student_no'],
                'password' => $password,
                'role_id' => Role::where('name', 'Student')->value('id'),
                'name' => "{$data['first_name']} {$data['last_name']}",
                'email' => $data['email'],
            ]);

            $student = Student::create(collect($data)->except('email')->all() + ['user_id' => $user->id]);
            AuditLog::record('CREATE', 'students', $student->id, null, ['student_no' => $student->student_no]);

            return $student;
        });

        return redirect()->route('registrar.students.show', $student)
            ->with('status', "Student created. Temporary password: {$password} (shown once; the student should change it after logging in).");
    }

    public function showStudent(Student $student)
    {
        $term = AcademicTerm::active();

        return view('registrar.student-show', [
            'student' => $student->load('program.department', 'user'),
            'term' => $term,
            'enrollments' => Enrollment::with(['section.subject', 'section.term', 'grade'])
                ->where('student_id', $student->id)->get()->sortByDesc('section.term_id'),
            'sections' => $term ? ClassSection::with('subject')->where('term_id', $term->id)
                ->whereNotIn('id', Enrollment::where('student_id', $student->id)->select('section_id'))->get() : collect(),
        ]);
    }

    public function updateStudent(Request $request, Student $student)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['Enrolled', 'Not Enrolled', 'LOA', 'Graduated', 'Dropped'])],
            'year_level' => ['required', 'integer', 'between:1,5'],
            'student_type' => ['sometimes', 'required', Rule::in(['Regular', 'Irregular'])],
            'block_section' => ['sometimes', 'nullable', 'string', 'max:15', 'regex:/^[A-Za-z0-9 \-]+$/'],
            'contact_no' => ['nullable', 'string', 'max:15'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $old = $student->only(array_keys($data));
        $student->update($data);
        AuditLog::record('UPDATE', 'students', $student->id, $old, $data);

        return back()->with('status', 'Student record updated.');
    }

    public function enrollStudent(Request $request, Student $student)
    {
        $term = AcademicTerm::active();
        abort_unless($term, 404);

        $data = $request->validate([
            'section_id' => ['required', Rule::exists('class_sections', 'id')->where('term_id', $term->id)],
        ]);

        $enrollment = Enrollment::firstOrCreate(['student_id' => $student->id, 'section_id' => $data['section_id']]);
        AuditLog::record('CREATE', 'enrollments', $enrollment->id, null, $data);
        ClearanceService::ensureFor($student, $term);

        return back()->with('status', 'Student enrolled in the class section.');
    }

    public function terms()
    {
        return view('registrar.terms', [
            'terms' => AcademicTerm::orderByDesc('school_year')->orderByDesc('id')->get(),
        ]);
    }

    public function storeTerm(Request $request)
    {
        $data = $request->validate([
            'school_year' => ['required', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['required', Rule::in(['1st', '2nd', 'Summer']),
                Rule::unique('academic_terms')->where('school_year', $request->input('school_year'))],
        ], ['school_year.regex' => 'School year must look like 2026-2027.', 'semester.unique' => 'That term already exists.']);

        $term = AcademicTerm::create($data);
        AuditLog::record('CREATE', 'academic_terms', $term->id, null, $data);

        return back()->with('status', 'Term added.');
    }

    public function updateTerm(Request $request, AcademicTerm $term)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['activate', 'open_enrollment', 'close_enrollment', 'open_encoding', 'close_encoding', 'release', 'unrelease'])],
        ]);

        $old = $term->only(['is_active', 'enrollment_open', 'encoding_open', 'grades_released']);

        DB::transaction(function () use ($term, $data) {
            match ($data['action']) {
                'activate' => [AcademicTerm::where('id', '!=', $term->id)->update(['is_active' => false]), $term->update(['is_active' => true])],
                'open_enrollment' => $term->update(['enrollment_open' => true]),
                'close_enrollment' => $term->update(['enrollment_open' => false]),
                'open_encoding' => $term->update(['encoding_open' => true]),
                'close_encoding' => $term->update(['encoding_open' => false]),
                'release' => $term->update(['grades_released' => true, 'encoding_open' => false]),
                'unrelease' => $term->update(['grades_released' => false]),
            };
        });

        $offered = 0;
        if ($data['action'] === 'open_enrollment') {
            // Every subject of this semester is offered right away; Faculty assigns the professors afterwards.
            $offered = EnrollmentService::offerCurriculum($term);
            // Regular students are told they can enroll now; irregular students wait for their department.
            $userIds = Student::where('student_type', 'Regular')->whereNotIn('status', ['Graduated', 'Dropped'])->pluck('user_id');
            foreach ($userIds as $userId) {
                Notification::notify($userId, 'Enrollment is open', "Enrollment for {$term->label} is open. Open Enrollment to take the subjects of your year level.");
            }
        }

        if ($data['action'] === 'release') {
            $userIds = Student::whereIn('id', Enrollment::whereHas('section', fn ($q) => $q->where('term_id', $term->id))->select('student_id'))->pluck('user_id');
            foreach ($userIds as $userId) {
                Notification::notify($userId, 'Grades released', "Your grades for {$term->label} are now available.");
            }
        }

        AuditLog::record('UPDATE', 'academic_terms', $term->id, $old, $term->only(array_keys($old)));

        return back()->with('status', $data['action'] === 'open_enrollment'
            ? 'Enrollment is open.'.($offered ? " {$offered} ".($offered === 1 ? 'class was' : 'classes were').' offered from the curriculum; Faculty can now assign their professors.' : '')
            : 'Term updated.');
    }

    // ----- Enrollment slots asked for by the departments -----

    public function slotRequests()
    {
        return view('registrar.slot-requests', [
            // Pending requests first, then the newest decisions.
            'requests' => SlotRequest::with(['department', 'term', 'requester', 'decider'])
                ->orderByRaw("status = 'Pending' desc")->latest('id')->paginate(15),
        ]);
    }

    public function decideSlotRequest(Request $request, SlotRequest $slotRequest)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['Approved', 'Rejected'])],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            EnrollmentService::decide($slotRequest, $data['status'], $data['remarks'] ?? null, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', "{$slotRequest->slots} {$slotRequest->category} slots for {$slotRequest->department->code} ".strtolower($data['status']).'.');
    }

    // ----- Enrollment queue -----

    public function queue(Request $request)
    {
        $today = QueueTicket::today()->with(['student.program', 'server'])->orderBy('number')->get();

        return view('registrar.queue', [
            'mine' => $today->first(fn ($t) => $t->status === 'Serving' && $t->served_by === $request->user()->id),
            'others' => $today->filter(fn ($t) => $t->status === 'Serving' && $t->served_by !== $request->user()->id),
            'waiting' => $today->where('status', 'Waiting'),
            'closed' => $today->whereIn('status', ['Done', 'Skipped', 'Cancelled'])->sortByDesc('finished_at'),
        ]);
    }

    public function callNext(Request $request)
    {
        try {
            $ticket = EnrollmentQueueService::callNext($request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['queue' => $e->getMessage()]);
        }

        return back()->with('status', "Now serving {$ticket->label}: {$ticket->student->full_name}.");
    }

    public function closeTicket(Request $request, QueueTicket $ticket)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['Done', 'Skipped'])]]);
        abort_unless($ticket->served_by === $request->user()->id, 403);

        try {
            EnrollmentQueueService::finish($ticket, $data['status'], $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['queue' => $e->getMessage()]);
        }

        return back()->with('status', "{$ticket->label} marked ".strtolower($data['status']).'.');
    }

    public function requests(Request $request)
    {
        $statuses = ['Paid', 'Processing', 'Ready for Pickup', 'Released', 'Pending Payment', 'Rejected'];
        $status = in_array($request->query('status'), $statuses, true) ? $request->query('status') : 'Paid';

        return view('registrar.requests', [
            'status' => $status,
            'counts' => DocumentRequest::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'statuses' => $statuses,
            'requests' => DocumentRequest::with(['student', 'type'])->where('status', $status)->oldest('id')->paginate(15)->withQueryString(),
        ]);
    }

    public function showRequest(DocumentRequest $docRequest)
    {
        return view('registrar.request-show', [
            'docRequest' => $docRequest->load(['student.program', 'type', 'history.user', 'releaser']),
        ]);
    }

    public function updateRequest(Request $request, DocumentRequest $docRequest)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['process', 'ready', 'release', 'reject'])],
            'claim_date' => [Rule::requiredIf($request->input('action') === 'ready' && $docRequest->release_method === 'Pickup'), 'nullable', 'date', 'after_or_equal:today'],
            'ecopy' => [Rule::requiredIf($request->input('action') === 'ready' && $docRequest->release_method === 'E-copy'), 'nullable', 'file', 'mimes:pdf', 'max:5120'],
            'reason' => ['required_if:action,reject', 'nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        try {
            match ($data['action']) {
                'process' => DocumentRequestService::changeStatus($docRequest, 'Processing', $user),
                'ready' => $docRequest->release_method === 'E-copy'
                    ? DocumentRequestService::changeStatus($docRequest, 'Ready for Pickup', $user,
                        'Certified e-copy is ready to download.',
                        ['ecopy_file' => $data['ecopy']->store('ecopies', 'local')])
                    : DocumentRequestService::changeStatus($docRequest, 'Ready for Pickup', $user,
                        'Claim on or after '.date('F j, Y', strtotime($data['claim_date'])).'. Bring your claim stub and school ID.',
                        ['claim_date' => $data['claim_date']]),
                'release' => DocumentRequestService::changeStatus($docRequest, 'Released', $user, null,
                    ['released_by' => $user->id, 'released_at' => now()]),
                'reject' => DocumentRequestService::changeStatus($docRequest, 'Rejected', $user, "Reason: {$data['reason']}",
                    ['reject_reason' => $data['reason']]),
            };
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return back()->with('status', "Request is now {$docRequest->status}.");
    }

    public function ecopy(DocumentRequest $docRequest)
    {
        abort_unless($docRequest->ecopy_file && Storage::disk('local')->exists($docRequest->ecopy_file), 404);

        return Storage::disk('local')->download($docRequest->ecopy_file, "{$docRequest->reference_no}.pdf");
    }
}
