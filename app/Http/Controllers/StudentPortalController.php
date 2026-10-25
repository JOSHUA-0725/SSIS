<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\AuditLog;
use App\Models\Clearance;
use App\Models\CurriculumSubject;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\QueueTicket;
use App\Models\Student;
use App\Models\User;
use App\Services\ClearanceService;
use App\Services\DocumentRequestService;
use App\Services\EnrollmentQueueService;
use App\Services\EnrollmentService;
use App\Services\GradeService;
use App\Services\LedgerService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StudentPortalController extends Controller
{
    public const PURPOSES = [
        'Employment', 'Scholarship application', 'Transfer to another school',
        'Board examination', 'Visa or travel', 'Personal copy',
    ];

    public const STEPS = ['Pending Payment' => 'Requested', 'Paid' => 'Paid', 'Processing' => 'Processing', 'Ready for Pickup' => 'Ready'];

    /** The student record always comes from the session, never from the URL. */
    private function student(Request $request): Student
    {
        $student = $request->user()->student;
        abort_unless($student, 403);

        return $student;
    }

    /** A request is visible only to the student who filed it. */
    private function ownRequest(Request $request, DocumentRequest $docRequest): DocumentRequest
    {
        abort_unless($docRequest->student_id === $this->student($request)->id, 404);

        return $docRequest;
    }

    private function clearanceFor(Student $student, ?AcademicTerm $term): ?Clearance
    {
        return $term && $student->status === 'Enrolled' ? ClearanceService::ensureFor($student, $term) : null;
    }

    /**
     * Clearance items in signing order. Each gets ->note (what the student reads under the
     * office) and ->display (the status shown: an unpaid balance reads as a deficiency).
     */
    private function clearanceItems(?Clearance $clearance): Collection
    {
        if (! $clearance) {
            return collect();
        }

        $owed = LedgerService::balance($clearance->student, $clearance->term_id, true);

        return $clearance->items()->with(['office', 'signer'])->get()->sortBy('office.sequence_no')->values()
            ->each(function ($item) use ($owed) {
                $unpaid = $item->office->code === ClearanceService::CASHIER_OFFICE && $item->status === 'Pending' && $owed > 0;
                $item->display = $unpaid ? 'On Hold' : $item->status;
                $item->note = $item->remarks ?: match (true) {
                    $unpaid => 'Outstanding balance of ₱'.number_format($owed, 2),
                    $item->status === 'Cleared' => 'No pending obligations',
                    $item->office->code === ClearanceService::FINAL_OFFICE => 'Final sign-off once all offices clear',
                    default => 'Waiting for sign-off',
                };
            });
    }

    /** Released grades of one term with the weighted average. */
    private function termGrades(Student $student, AcademicTerm $term): array
    {
        $rows = Enrollment::with(['section.subject', 'section.faculty', 'grade'])
            ->where('student_id', $student->id)
            ->whereHas('section', fn ($q) => $q->where('term_id', $term->id)->where('sheet_status', 'Approved'))
            ->get();

        return [
            'rows' => $rows,
            'gwa' => GradeService::gwa($rows->map(fn ($e) => [$e->grade?->final_rating, $e->section->subject->units])),
            'units' => $rows->filter(fn ($e) => $e->grade?->remarks === 'Passed')->sum('section.subject.units'),
            'allPassed' => $rows->isNotEmpty() && $rows->every(fn ($e) => $e->grade?->remarks === 'Passed'),
        ];
    }

    private function releasedTerms(Student $student): Collection
    {
        return AcademicTerm::where('grades_released', true)
            ->whereIn('id', Enrollment::where('student_id', $student->id)
                ->join('class_sections', 'class_sections.id', '=', 'enrollments.section_id')
                ->select('class_sections.term_id'))
            ->get()
            // Newest first: by school year, then Summer, 2nd, 1st.
            ->sortByDesc(fn ($term) => $term->school_year.array_search($term->semester, ['1st', '2nd', 'Summer'], true))->values();
    }

    public function overview(Request $request)
    {
        $student = $this->student($request);
        $term = AcademicTerm::active();
        $clearance = $this->clearanceFor($student, $term);
        $items = $this->clearanceItems($clearance);
        $balance = LedgerService::balance($student);
        $latest = $this->releasedTerms($student)->first();

        return view('student.dashboard', [
            'student' => $student,
            'term' => $term,
            'items' => $items,
            'cleared' => $items->where('status', 'Cleared')->count(),
            'blocker' => $this->blocker($clearance, $items, $student, $term, $balance),
            'gradeTerm' => $latest,
            'grades' => $latest ? $this->termGrades($student, $latest) : null,
            'requests' => $student->documentRequests()->with('type')
                ->whereNotIn('status', ['Released', 'Rejected', 'Cancelled'])->latest('id')->get(),
            'steps' => self::STEPS,
            'subjects' => $term ? Enrollment::with(['section.subject', 'section.faculty'])->where('student_id', $student->id)
                ->whereHas('section', fn ($q) => $q->where('term_id', $term->id))->get()->sortBy('section.subject.code')->values() : collect(),
            'notifications' => Notification::where('user_id', $student->user_id)->latest()->limit(4)->get(),
        ]);
    }

    /** The one thing the student should do next, shown in the highlighted card. */
    private function blocker(?Clearance $clearance, Collection $items, Student $student, ?AcademicTerm $term, float $balance): array
    {
        if (! $clearance) {
            return ['eyebrow' => 'Enrollment clearance', 'title' => 'No clearance is open for you this term.', 'amount' => null, 'cta' => 'View clearance', 'url' => route('student.clearance')];
        }
        if ($clearance->overall_status === 'Cleared') {
            return ['eyebrow' => 'You are cleared', 'title' => 'Every office has signed off. You can enroll.', 'amount' => null, 'cta' => 'Download clearance slip', 'url' => route('student.clearance.slip')];
        }

        $hold = $items->first(fn ($i) => $i->status === 'On Hold' && $i->office->code !== ClearanceService::CASHIER_OFFICE);
        if ($hold) {
            return ['eyebrow' => "What's blocking your enrollment", 'title' => "Settle your deficiency at the {$hold->office->name}.", 'detail' => $hold->remarks, 'amount' => null, 'cta' => 'View remarks', 'url' => route('student.clearance')];
        }

        $cashier = $items->first(fn ($i) => $i->office->code === ClearanceService::CASHIER_OFFICE);
        if ($cashier && $cashier->status !== 'Cleared' && $balance > 0) {
            return ['eyebrow' => "What's blocking your enrollment", 'title' => 'Settle your balance to clear the Cashier.', 'amount' => $balance, 'cta' => 'See what to pay', 'url' => route('student.payments')];
        }

        $waiting = $items->where('status', '!=', 'Cleared')->count();

        return ['eyebrow' => 'Almost there', 'title' => "Waiting for {$waiting} ".($waiting === 1 ? 'office' : 'offices').' to sign off.', 'amount' => null, 'cta' => 'View signatories', 'url' => route('student.clearance')];
    }

    public function grades(Request $request)
    {
        $student = $this->student($request);
        $request->validate(['term' => ['nullable', 'integer']]);

        $terms = $this->releasedTerms($student);

        // The current semester comes first: its subjects are listed as soon as the student is enrolled, without grades.
        $current = AcademicTerm::where('grades_released', false)->where('is_active', true)->first();
        $subjects = $current
            ? Enrollment::with(['section.subject', 'section.faculty'])->where('student_id', $student->id)
                ->whereHas('section', fn ($q) => $q->where('term_id', $current->id))->get()->sortBy('section.subject.code')->values()
            : collect();
        if ($subjects->isNotEmpty()) {
            $terms = $terms->prepend($current);
        }

        $term = $terms->firstWhere('id', (int) $request->query('term')) ?? $terms->first();
        $pending = $term && ! $term->grades_released;
        if ($pending) {
            // Grades stay hidden until the Registrar releases the term, even when a professor has already encoded them.
            $subjects->each->setRelation('grade', null);
            $grades = ['rows' => $subjects, 'gwa' => null, 'units' => $subjects->sum('section.subject.units'), 'allPassed' => false];
        } else {
            $grades = $term ? $this->termGrades($student, $term) : null;
        }

        return view('student.grades', [
            'terms' => $terms,
            'term' => $term,
            'pending' => $pending,
            'grades' => $grades,
            'unreleased' => $current,
            'certificate' => DocumentType::where('name', 'Certificate of Grades')->where('is_active', true)->first(),
        ]);
    }

    /** What happened with the student's account and records: sign-ins, failed attempts, requests, and enrollment. */
    public function activity(Request $request)
    {
        $student = $this->student($request);
        $user = $request->user();
        $data = $request->validate(['show' => ['nullable', Rule::in(['all', 'security', 'records'])]]);
        $filter = $data['show'] ?? 'all';
        $requestIds = DocumentRequest::where('student_id', $student->id)->pluck('id');

        // Entries the student made, and entries others made on the student's account, record, or requests.
        $mine = fn ($q) => $q->where('user_id', $user->id)
            ->orWhere(fn ($w) => $w->where('table_name', 'users')->where('record_id', $user->id))
            ->orWhere(fn ($w) => $w->where('table_name', 'students')->where('record_id', $student->id))
            ->orWhere(fn ($w) => $w->where('table_name', 'document_requests')->whereIn('record_id', $requestIds));
        $since = now()->subDays(30);

        return view('student.activity', [
            'user' => $user,
            'filter' => $filter,
            'lastLogin' => AuditLog::where('user_id', $user->id)->where('action', 'LOGIN')->latest('id')->first(),
            'failed' => AuditLog::where('user_id', $user->id)->where('action', 'LOGIN_FAILED')->where('created_at', '>=', $since)->count(),
            'locked' => AuditLog::where('user_id', $user->id)->where('action', 'ACCOUNT_LOCKED')->where('created_at', '>=', $since)->count(),
            'logs' => AuditLog::with('user')->where($mine)
                ->when($filter === 'security', fn ($q) => $q->whereIn('action', AuditLog::SECURITY))
                ->when($filter === 'records', fn ($q) => $q->whereNotIn('action', AuditLog::SECURITY))
                ->latest('id')->paginate(20)->withQueryString(),
        ]);
    }

    /** The whole curriculum of the student's program, by year level and semester, with what the student has taken. */
    public function curriculum(Request $request)
    {
        $student = $this->student($request)->load('program');
        $activeTermId = AcademicTerm::active()?->id;

        $rows = CurriculumSubject::with('subject')->where('program_id', $student->program_id)->get()
            ->sortBy(fn ($row) => sprintf('%d-%d-%s', $row->year_level, array_search($row->semester, ['1st', '2nd', 'Summer'], true), $row->subject->code));

        // One entry per subject. A released grade wins over a current enrollment, and Passed wins over another try.
        $progress = [];
        $taken = Enrollment::with(['section.term', 'section.subject', 'grade'])->where('student_id', $student->id)->get();
        foreach ($taken as $enrollment) {
            $section = $enrollment->section;
            $released = $section->term->grades_released && $section->sheet_status === 'Approved' && $enrollment->grade?->remarks;
            if ($released) {
                $entry = ['status' => $enrollment->grade->remarks, 'rating' => $enrollment->grade->final_rating];
            } elseif ($section->term_id === $activeTermId) {
                $entry = ['status' => 'Enrolled'];
            } else {
                continue;
            }
            $current = $progress[$section->subject_id]['status'] ?? null;
            if ($current !== 'Passed' && ($entry['status'] !== 'Enrolled' || $current === null)) {
                $progress[$section->subject_id] = $entry;
            }
        }

        $passed = $rows->filter(fn ($row) => ($progress[$row->subject_id]['status'] ?? null) === 'Passed');

        return view('student.curriculum', [
            'student' => $student,
            'years' => $rows->groupBy('year_level')->map(fn ($year) => $year->groupBy('semester')),
            'yearNames' => [1 => 'First Year', 2 => 'Second Year', 3 => 'Third Year', 4 => 'Fourth Year', 5 => 'Fifth Year'],
            'progress' => $progress,
            'summary' => [
                'subjects' => $rows->count(),
                'passed' => $passed->count(),
                'units' => $rows->sum('subject.units'),
                'unitsEarned' => $passed->sum('subject.units'),
                'enrolled' => $rows->filter(fn ($row) => ($progress[$row->subject_id]['status'] ?? null) === 'Enrolled')->count(),
            ],
        ]);
    }

    public function clearance(Request $request)
    {
        $student = $this->student($request);
        $term = AcademicTerm::active();
        $clearance = $this->clearanceFor($student, $term);
        $items = $this->clearanceItems($clearance);

        // Who signs for each office: the person who already signed, or the one assigned to it.
        $byRole = fn (string $role, array $where = []) => User::whereHas('role', fn ($q) => $q->where('name', $role))
            ->where('status', 'Active')->where($where)->value('name');
        foreach ($items as $item) {
            $item->signatory = $item->signer?->name ?? match ($item->office->code) {
                'DEPT' => $byRole('Department Head', ['department_id' => $student->program->department_id]),
                ClearanceService::CASHIER_OFFICE => $byRole('Cashier'),
                ClearanceService::FINAL_OFFICE => $byRole('Registrar'),
                default => $byRole('Signatory', ['office_id' => $item->office_id]),
            };
        }

        return view('student.clearance', [
            'term' => $term,
            'clearance' => $clearance,
            'items' => $items,
            'cleared' => $items->where('status', 'Cleared')->count(),
            'holds' => $items->where('status', 'On Hold'),
            'cashierPending' => $items->contains(fn ($i) => $i->office->code === ClearanceService::CASHIER_OFFICE && $i->status !== 'Cleared'),
            'schoolBalance' => $term ? LedgerService::balance($student, $term->id, true) : 0,
        ]);
    }

    public function clearanceSlip(Request $request)
    {
        $student = $this->student($request);
        $term = AcademicTerm::active();
        $clearance = $this->clearanceFor($student, $term);
        abort_unless($clearance && $clearance->overall_status === 'Cleared', 403, 'The clearance slip is available once every office has signed off.');

        return view('student.clearance-slip', [
            'student' => $student->load('program'),
            'term' => $term,
            'clearance' => $clearance,
            'items' => $this->clearanceItems($clearance),
        ]);
    }

    // ----- Enrollment queue -----

    public function queue(Request $request)
    {
        $student = $this->student($request)->load('program');
        $term = AcademicTerm::active();
        $category = EnrollmentService::category($student);
        $taken = $term ? Enrollment::where('student_id', $student->id)->whereHas('section', fn ($q) => $q->where('term_id', $term->id))->pluck('section_id') : collect();

        // Regular students do not line up: once the Registrar opens enrollment they take the subjects of their year level.
        if ($category === 'Regular') {
            $subjects = $term ? EnrollmentService::regularSubjects($student, $term) : collect();
            $offered = $subjects->pluck('section')->filter();

            return view('student.enrollment', [
                'term' => $term,
                'student' => $student,
                'subjects' => $subjects,
                'offered' => $offered->count(),
                'open' => (bool) $term?->enrollment_open,
                'enrolled' => $offered->isNotEmpty() && $offered->every(fn ($section) => $taken->contains($section->id)),
            ]);
        }

        $ticket = EnrollmentQueueService::openTicket($student);
        $advised = $term ? EnrollmentService::advisedSections($student, $term) : collect();

        return view('student.queue', [
            'term' => $term,
            'category' => $category,
            // Irregular students enroll only in the subjects their department opened for them.
            'advised' => $advised,
            'advisedEnrolled' => $advised->isNotEmpty() && $advised->every(fn ($section) => $taken->contains($section->id)),
            'ticket' => $ticket,
            'ahead' => $ticket ? EnrollmentQueueService::ahead($ticket) : 0,
            'serving' => QueueTicket::today()->where('status', 'Serving')->orderBy('number')->get(),
            'waiting' => QueueTicket::today()->where('status', 'Waiting')->count(),
            // The last number the student held today, to explain a skipped or finished turn.
            'last' => $ticket ? null : QueueTicket::today()->where('student_id', $student->id)->latest('id')->first(),
        ]);
    }

    /** Regular students enroll in the subjects of their year level; irregular students in the ones opened for them. */
    public function enroll(Request $request)
    {
        $student = $this->student($request);

        try {
            $added = EnrollmentService::category($student) === 'Regular'
                ? EnrollmentService::enrollRegular($student)
                : EnrollmentService::enrollAdvised($student);
        } catch (DomainException $e) {
            return back()->withErrors(['queue' => $e->getMessage()]);
        }

        return redirect()->route('student.queue')->with('status', $added
            ? "You are enrolled: {$added} ".($added === 1 ? 'subject' : 'subjects').' added.'
            : 'You were already enrolled in these subjects.');
    }

    public function joinQueue(Request $request)
    {
        try {
            $ticket = EnrollmentQueueService::join($this->student($request));
        } catch (DomainException $e) {
            return back()->withErrors(['queue' => $e->getMessage()]);
        }

        return redirect()->route('student.queue')->with('status', "You are in line. Your queue number is {$ticket->label}.");
    }

    public function leaveQueue(Request $request)
    {
        $ticket = EnrollmentQueueService::openTicket($this->student($request));
        abort_unless($ticket, 404);

        try {
            EnrollmentQueueService::leave($ticket);
        } catch (DomainException $e) {
            return back()->withErrors(['queue' => $e->getMessage()]);
        }

        return redirect()->route('student.queue')->with('status', "Queue number {$ticket->label} cancelled.");
    }

    // ----- Payments -----

    public function payments(Request $request)
    {
        $student = $this->student($request);
        $payables = LedgerService::payables($student);

        return view('student.payments', [
            'payables' => $payables,
            'balance' => LedgerService::balance($student),
            'pending' => $payables->sum('pending'),
            'payable' => $payables->sum('payable'),
            'history' => Payment::with('assessment')
                ->whereHas('assessment', fn ($q) => $q->where('student_id', $student->id))
                ->latest('paid_at')->latest('id')->get(),
        ]);
    }

    /** A slip listing what to pay in cash at the Cashier window. It records nothing. */
    public function cashSlip(Request $request)
    {
        $student = $this->student($request);
        $payables = LedgerService::payables($student)->where('payable', '>', 0);

        if ($payables->isEmpty()) {
            return redirect()->route('student.payments')->withErrors(['payment' => 'You have nothing left to pay.']);
        }

        return view('student.cash-slip', [
            'student' => $student->load('program'),
            'payables' => $payables,
            'total' => $payables->sum('payable'),
        ]);
    }

    // ----- Document requests -----

    public function requests(Request $request)
    {
        $student = $this->student($request);
        $types = DocumentType::where('is_active', true)->orderBy('id')->get();

        return view('student.requests', [
            'types' => $types,
            'selected' => (int) old('document_type_id', $types->firstWhere('id', (int) $request->query('type'))?->id ?? $types->first()?->id),
            'purposes' => self::PURPOSES,
            'requests' => $student->documentRequests()->with('type')->latest('id')->get(),
            'steps' => self::STEPS,
        ]);
    }

    public function storeRequest(Request $request)
    {
        $student = $this->student($request);
        $data = $request->validate([
            'document_type_id' => ['required', Rule::exists('document_types', 'id')->where('is_active', true)],
            'copies' => ['required', 'integer', 'min:1', 'max:'.DocumentRequestService::MAX_COPIES],
            // A fixed list: free text, and so any markup, can never be stored.
            'purpose' => ['required', Rule::in(self::PURPOSES)],
            'release_method' => ['nullable', Rule::in(['Pickup', 'E-copy'])],
        ], ['purpose.in' => 'Choose a purpose from the list.']);

        try {
            $docRequest = DocumentRequestService::file(
                $student, DocumentType::findOrFail($data['document_type_id']), (int) $data['copies'], $data['purpose'], $data['release_method'] ?? 'Pickup'
            );
        } catch (DomainException $e) {
            return back()->withErrors(['document_type_id' => $e->getMessage()])->withInput();
        }

        return redirect()->route('student.payments')
            ->with('status', "Request {$docRequest->reference_no} filed. Pay the ₱".number_format($docRequest->total_fee, 2).' fee below so the Registrar can start processing it.');
    }

    public function showRequest(Request $request, DocumentRequest $docRequest)
    {
        $docRequest = $this->ownRequest($request, $docRequest)->load(['type', 'history', 'assessment.payments']);

        return view('student.request-show', [
            'docRequest' => $docRequest,
            'pendingProof' => $docRequest->assessment?->payments->firstWhere('status', 'For Verification'),
            'steps' => self::STEPS,
        ]);
    }

    public function cancelRequest(Request $request, DocumentRequest $docRequest)
    {
        $docRequest = $this->ownRequest($request, $docRequest);

        try {
            DocumentRequestService::changeStatus($docRequest, 'Cancelled', $request->user(), 'Cancelled by the student');
            // The unpaid fee is removed from the ledger together with the request.
            $docRequest->assessment?->payments()->where('status', 'For Verification')->update(['status' => 'Voided']);
            $docRequest->assessment?->update(['amount' => 0]);
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('student.requests')->with('status', "Request {$docRequest->reference_no} cancelled.");
    }

    public function uploadProof(Request $request, DocumentRequest $docRequest)
    {
        $docRequest = $this->ownRequest($request, $docRequest)->load('assessment.payments');
        abort_unless($docRequest->status === 'Pending Payment' && $docRequest->assessment, 403);

        $data = $request->validate([
            // Type is checked from the file content, not the file name.
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            'method' => ['required', Rule::in(['Bank Deposit', 'E-wallet'])],
        ]);

        if ($docRequest->assessment->payments->contains('status', 'For Verification')) {
            return back()->withErrors(['proof' => 'A payment for this request is already waiting for verification.']);
        }

        // Stored on the private disk under a random name; never reachable by URL.
        $path = $data['proof']->store('proofs', 'local');

        Payment::create([
            'assessment_id' => $docRequest->assessment->id,
            'amount' => $docRequest->total_fee,
            'method' => $data['method'],
            'proof_file' => $path,
            'status' => 'For Verification',
            'paid_at' => now(),
        ]);

        return back()->with('status', 'Proof of payment uploaded. The Cashier will verify it.');
    }

    public function claimStub(Request $request, DocumentRequest $docRequest)
    {
        $docRequest = $this->ownRequest($request, $docRequest)->load(['type', 'student.program']);
        abort_unless(in_array($docRequest->status, ['Paid', 'Processing', 'Ready for Pickup', 'Released'], true), 403);

        return view('student.claim-stub', ['docRequest' => $docRequest]);
    }

    /** Download the certified e-copy. The first download marks the request as released. */
    public function ecopy(Request $request, DocumentRequest $docRequest)
    {
        $docRequest = $this->ownRequest($request, $docRequest);
        abort_unless(
            $docRequest->ecopy_file
            && in_array($docRequest->status, ['Ready for Pickup', 'Released'], true)
            && Storage::disk('local')->exists($docRequest->ecopy_file),
            404
        );

        if ($docRequest->status === 'Ready for Pickup') {
            DocumentRequestService::changeStatus($docRequest, 'Released', $request->user(), 'E-copy downloaded by the student', ['released_at' => now()]);
        }

        return Storage::disk('local')->download($docRequest->ecopy_file, "{$docRequest->reference_no}.pdf");
    }
}
