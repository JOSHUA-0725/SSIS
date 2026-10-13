<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\AdvisedSubject;
use App\Models\AuditLog;
use App\Models\ClassSection;
use App\Models\CurriculumSubject;
use App\Models\Grade;
use App\Models\Notification;
use App\Models\Program;
use App\Models\SlotRequest;
use App\Models\Student;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\GradeService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    // ----- Faculty: which professor handles each subject -----

    /** Accounts that can teach in a department: its professors. */
    private function instructors(int $departmentId)
    {
        return User::whereHas('role', fn ($q) => $q->where('name', 'Professor'))
            ->where('department_id', $departmentId)->where('status', 'Active')->orderBy('name')->get();
    }

    /** The sections a department can offer: each of its programs with a section letter, such as BSIT-A. */
    private function blocks(int $departmentId): array
    {
        return Program::where('department_id', $departmentId)->orderBy('code')->pluck('code')
            ->flatMap(fn ($code) => array_map(fn ($letter) => "{$code}-{$letter}", ClassSection::LETTERS))->all();
    }

    /** Where the department's curricula place each subject: subject id => its curriculum row (year level, semester, program). */
    private function curriculum(int $departmentId)
    {
        return CurriculumSubject::with(['program', 'subject'])
            ->whereHas('program', fn ($q) => $q->where('department_id', $departmentId))
            ->get()->keyBy('subject_id');
    }

    public function loads(Request $request)
    {
        $departmentId = $request->user()->department_id;
        abort_unless($departmentId, 403, 'Your account is not assigned to a department.');
        $term = AcademicTerm::active();
        $curriculum = $this->curriculum($departmentId);

        $sections = ClassSection::with(['subject', 'faculty'])->withCount('enrollments')
            ->where('term_id', $term?->id)
            ->whereHas('subject', fn ($q) => $q->where('department_id', $departmentId))
            ->get()->sortBy(fn ($section) => $section->subject->code.' '.$section->section_code)->values();

        return view('department.loads', [
            'term' => $term,
            'department' => $request->user()->department,
            'instructors' => $this->instructors($departmentId),
            // How many classes each professor already handles this term, so Faculty can see who is available.
            'loadCounts' => $sections->countBy('faculty_id'),
            'years' => ClassSection::YEARS,
            'letters' => ClassSection::LETTERS,
            'blocks' => $this->blocks($departmentId),
            'curriculum' => $curriculum,
            // Subjects the curriculum places in this semester: the ones a new section can be opened for.
            'offerable' => $curriculum->where('semester', $term?->semester)
                ->sortBy(fn ($row) => $row->year_level.' '.$row->subject->code)->values(),
            'sections' => $sections,
        ]);
    }

    /** Opens another section of a subject for this term and gives it a professor. */
    public function storeLoad(Request $request)
    {
        $departmentId = $request->user()->department_id;
        abort_unless($departmentId, 403);
        $term = AcademicTerm::active();
        abort_unless($term, 404);
        $offerable = $this->curriculum($departmentId)->where('semester', $term->semester);

        $data = $request->validate([
            'subject_id' => ['required', Rule::in($offerable->keys()->all())],
            'letter' => ['required', Rule::in(ClassSection::LETTERS)],
            'faculty_id' => ['required', Rule::in($this->instructors($departmentId)->pluck('id')->all())],
        ], ['subject_id.in' => 'Choose a subject from the list.', 'letter.in' => 'Choose a section from the list.', 'faculty_id.in' => 'Choose a professor from the list.']);

        $row = $offerable[(int) $data['subject_id']];
        $code = "{$row->program->code}-{$row->year_level}{$data['letter']}";
        if (ClassSection::where('subject_id', $row->subject_id)->where('term_id', $term->id)->where('section_code', $code)->exists()) {
            return back()->withErrors(['letter' => "{$row->subject->code} already has a class for {$code}."])->withInput();
        }

        $section = ClassSection::create(['subject_id' => $row->subject_id, 'term_id' => $term->id, 'section_code' => $code, 'faculty_id' => (int) $data['faculty_id']]);
        AuditLog::record('CREATE', 'class_sections', $section->id, null, $section->only(['subject_id', 'section_code', 'faculty_id']));
        Notification::notify($section->faculty_id, 'Subject assigned to you', "{$row->subject->code} · {$row->subject->title} ({$code}) is now in My classes.");

        return back()->with('status', "{$row->subject->code} ({$code}) was added and is handled by {$section->faculty()->value('name')}.");
    }

    public function assignLoad(Request $request, ClassSection $section)
    {
        $departmentId = $request->user()->department_id;
        abort_unless($departmentId && $section->subject->department_id === $departmentId, 403);

        // The curriculum decides the year level of a subject; Faculty chooses it only for a subject outside the curriculum.
        $placed = $this->curriculum($departmentId)->get($section->subject_id);

        $data = $request->validate([
            'faculty_id' => ['required', Rule::in($this->instructors($departmentId)->pluck('id')->all())],
            'year_level' => ['nullable', $placed ? 'exclude' : 'required_with:section', 'integer', Rule::in(array_keys(ClassSection::YEARS))],
            'section' => ['nullable', $placed ? 'bail' : 'required_with:year_level', Rule::in($this->blocks($departmentId))],
        ], ['section.in' => 'Choose a section from the list.', 'year_level.in' => 'Choose a year level from the list.']);

        // Once the grades are with the Department Head, the names on the sheet must not change.
        if (in_array($section->sheet_status, ['Submitted', 'Approved'], true)) {
            return back()->withErrors(['faculty_id' => "The grade sheet of {$section->subject->code} is already ".strtolower($section->sheet_status).', so its professor and section cannot be changed.']);
        }

        // BSIT-A in third year is stored as BSIT-3A, the same name students have as their block section.
        $code = $section->section_code;
        if (! empty($data['section'])) {
            [$program, $letter] = explode('-', $data['section']);
            $year = $placed?->year_level ?? $data['year_level'];
            $code = "{$program}-{$year}{$letter}";
        }
        $taken = ClassSection::where('subject_id', $section->subject_id)->where('term_id', $section->term_id)
            ->where('section_code', $code)->where('id', '!=', $section->id)->exists();
        if ($taken) {
            return back()->withErrors(['section' => "{$section->subject->code} already has a class for {$code}."]);
        }

        $old = $section->only(['faculty_id', 'section_code']);
        $section->update(['faculty_id' => (int) $data['faculty_id'], 'section_code' => $code]);
        if (! $section->wasChanged()) {
            return back();
        }

        AuditLog::record('UPDATE', 'class_sections', $section->id, $old, $section->only(['faculty_id', 'section_code']));
        if ($section->wasChanged('faculty_id')) {
            Notification::notify($section->faculty_id, 'Subject assigned to you', "{$section->subject->code} · {$section->subject->title} ({$section->section_code}) is now in My classes.");
        }

        return back()->with('status', "{$section->subject->code} ({$section->section_code}) is now handled by {$section->faculty()->value('name')}.");
    }

    // ----- Faculty: enrollment of the department -----

    public function enrollment(Request $request)
    {
        $departmentId = $request->user()->department_id;
        abort_unless($departmentId, 403, 'Your account is not assigned to a department.');
        $data = $request->validate(['student_no' => ['nullable', 'string', 'max:15']]);
        $term = AcademicTerm::active();

        $inDepartment = fn ($q) => $q->where('department_id', $departmentId);
        $regulars = Student::where('student_type', 'Regular')->whereNotIn('status', ['Graduated', 'Dropped'])->whereHas('program', $inDepartment);

        // Faculty finds one irregular student by student number, then opens subjects for that student.
        $search = trim((string) ($data['student_no'] ?? ''));
        $found = $search === '' ? null : Student::with('program')->where('student_no', $search)->whereHas('program', $inDepartment)->first();
        $sections = $term && $found
            ? ClassSection::with(['subject', 'faculty'])->where('term_id', $term->id)
                ->whereHas('subject', fn ($q) => $q->where('department_id', $departmentId))
                ->get()->sortBy(fn ($section) => $section->subject->code.' '.$section->section_code)->values()
            : collect();

        return view('department.enrollment', [
            'term' => $term,
            'department' => $request->user()->department,
            'open' => (bool) $term?->enrollment_open,
            'regulars' => (clone $regulars)->count(),
            'regularsEnrolled' => $term ? (clone $regulars)->whereHas('enrollments.section', fn ($q) => $q->where('term_id', $term->id))->count() : 0,
            'slots' => [
                'approved' => $term ? EnrollmentService::approved($term->id, $departmentId, 'Irregular') : 0,
                'used' => $term ? EnrollmentService::used($term->id, $departmentId) : 0,
            ],
            'search' => $search,
            'found' => $found,
            'foundIrregular' => $found && EnrollmentService::category($found) === 'Irregular',
            'sections' => $sections,
            'openedIds' => $found ? AdvisedSubject::where('student_id', $found->id)->pluck('section_id')->all() : [],
            'enrolledIds' => $found ? $found->enrollments()->pluck('section_id')->all() : [],
            // Irregular students who already have subjects opened this term.
            'advised' => $term
                ? AdvisedSubject::with(['student', 'section.subject'])->whereHas('section', fn ($q) => $q->where('term_id', $term->id))
                    ->whereHas('student.program', $inDepartment)->get()->groupBy('student_id')
                : collect(),
            'requests' => SlotRequest::with(['requester', 'decider'])->where('department_id', $departmentId)
                ->where('term_id', $term?->id)->latest('id')->get(),
        ]);
    }

    /** Opens the chosen subjects for one irregular student, who can then enroll in them. */
    public function adviseStudent(Request $request, Student $student)
    {
        abort_unless($request->user()->department_id, 403);
        $data = $request->validate(['sections' => ['nullable', 'array'], 'sections.*' => ['integer']]);
        $term = AcademicTerm::active();
        if (! $term) {
            return back()->withErrors(['sections' => 'No term is active.']);
        }

        try {
            $sections = EnrollmentService::advise($request->user(), $student->load('program'), $term, $data['sections'] ?? []);
        } catch (DomainException $e) {
            return back()->withErrors(['sections' => $e->getMessage()]);
        }

        return redirect()->route('faculty.enrollment', ['student_no' => $student->student_no])->with('status', $sections->isEmpty()
            ? "No subject is open for {$student->full_name} now."
            : $sections->count().' '.($sections->count() === 1 ? 'subject is' : 'subjects are')." now open for {$student->full_name}: ".$sections->pluck('subject.code')->join(', ').'.');
    }

    public function requestSlots(Request $request)
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(EnrollmentService::SLOT_CATEGORIES)],
            'slots' => ['required', 'integer', 'between:1,2000'],
        ]);
        abort_unless($request->user()->department_id, 403);
        $term = AcademicTerm::active();
        if (! $term) {
            return back()->withErrors(['slots' => 'No term is active.']);
        }

        try {
            EnrollmentService::requestSlots($request->user(), $term, $data['category'], (int) $data['slots']);
        } catch (DomainException $e) {
            return back()->withErrors(['slots' => $e->getMessage()])->withInput();
        }

        return back()->with('status', "Request for {$data['slots']} {$data['category']} slots sent to the Registrar.");
    }

    // ----- Professor: grade encoding -----

    public function classes(Request $request)
    {
        $term = AcademicTerm::active();

        return view('department.classes', [
            'term' => $term,
            'sections' => ClassSection::with('subject')->withCount('enrollments')
                ->where('faculty_id', $request->user()->id)
                ->where('term_id', $term?->id)->get(),
        ]);
    }

    /** A professor can open only the class sections assigned to them. */
    private function ownSection(Request $request, ClassSection $section): ClassSection
    {
        abort_unless($section->faculty_id === $request->user()->id, 403);

        return $section->load(['subject', 'term', 'enrollments.student', 'enrollments.grade']);
    }

    private function editable(ClassSection $section): bool
    {
        return $section->term->encoding_open && in_array($section->sheet_status, ['Draft', 'Returned'], true);
    }

    public function encode(Request $request, ClassSection $section)
    {
        $section = $this->ownSection($request, $section);

        return view('department.encode', [
            'section' => $section,
            'enrollments' => $section->enrollments->sortBy('student.last_name'),
            'editable' => $this->editable($section),
        ]);
    }

    public function saveGrades(Request $request, ClassSection $section)
    {
        $section = $this->ownSection($request, $section);

        if (! $this->editable($section)) {
            return back()->withErrors(['grades' => $section->term->encoding_open
                ? "This sheet is {$section->sheet_status} and can no longer be edited."
                : 'The encoding period is closed.']);
        }

        $data = $request->validate([
            'action' => ['required', Rule::in(['save', 'submit'])],
            'grades' => ['required', 'array'],
            'grades.*.prelim' => ['nullable', 'numeric', 'between:1,5'],
            'grades.*.midterm' => ['nullable', 'numeric', 'between:1,5'],
            'grades.*.final' => ['nullable', 'numeric', 'between:1,5'],
        ], [
            'grades.*.prelim.between' => 'Grades must be from 1.00 to 5.00.',
            'grades.*.midterm.between' => 'Grades must be from 1.00 to 5.00.',
            'grades.*.final.between' => 'Grades must be from 1.00 to 5.00.',
        ]);

        $enrollments = $section->enrollments->keyBy('id');

        if ($data['action'] === 'submit') {
            foreach ($enrollments as $id => $enrollment) {
                if (! isset($data['grades'][$id]['prelim'], $data['grades'][$id]['midterm'], $data['grades'][$id]['final'])) {
                    return back()->withErrors(['grades' => 'Every student needs a prelim, a midterm, and a final grade before the sheet can be submitted.'])->withInput();
                }
            }
        }

        DB::transaction(function () use ($data, $enrollments, $section, $request) {
            foreach ($data['grades'] as $enrollmentId => $row) {
                // Rows for students outside this section are ignored.
                if (! $enrollments->has($enrollmentId)) {
                    continue;
                }
                $prelim = isset($row['prelim']) ? (float) $row['prelim'] : null;
                $midterm = isset($row['midterm']) ? (float) $row['midterm'] : null;
                $final = isset($row['final']) ? (float) $row['final'] : null;
                $rating = GradeService::finalRating($prelim, $midterm, $final);

                $grade = Grade::firstOrNew(['enrollment_id' => $enrollmentId]);
                $old = $grade->only(['prelim_grade', 'midterm_grade', 'final_grade']);
                $grade->fill([
                    'prelim_grade' => $prelim,
                    'midterm_grade' => $midterm,
                    'final_grade' => $final,
                    'final_rating' => $rating,
                    'remarks' => GradeService::remarks($rating),
                    'encoded_by' => $request->user()->id,
                ]);

                if ($grade->isDirty(['prelim_grade', 'midterm_grade', 'final_grade'])) {
                    $grade->save();
                    AuditLog::record($grade->wasRecentlyCreated ? 'CREATE' : 'UPDATE', 'grades', $grade->id, $old, ['prelim_grade' => $prelim, 'midterm_grade' => $midterm, 'final_grade' => $final]);
                }
            }

            if ($data['action'] === 'submit') {
                $section->update(['sheet_status' => 'Submitted', 'return_remarks' => null]);
                AuditLog::record('UPDATE', 'class_sections', $section->id, null, ['sheet_status' => 'Submitted']);
            }
        });

        return redirect()->route('faculty.encode', $section)
            ->with('status', $data['action'] === 'submit' ? 'Grade sheet submitted to the Department Head.' : 'Draft saved.');
    }

    // ----- Department Head: grade sheet approval -----

    public function sheets(Request $request)
    {
        $statuses = ['Submitted', 'Approved', 'Returned', 'Draft'];
        $status = in_array($request->query('status'), $statuses, true) ? $request->query('status') : 'Submitted';

        return view('department.sheets', [
            'status' => $status,
            'statuses' => $statuses,
            'sections' => ClassSection::with(['subject', 'faculty', 'term'])->withCount('enrollments')
                ->where('sheet_status', $status)
                ->whereHas('subject', fn ($q) => $q->where('department_id', $request->user()->department_id))
                ->latest('updated_at')->get(),
        ]);
    }

    /** A head can open only the sheets of their own department. */
    private function departmentSection(Request $request, ClassSection $section): ClassSection
    {
        $section->load(['subject', 'term', 'faculty', 'enrollments.student', 'enrollments.grade']);
        abort_unless($section->subject->department_id === $request->user()->department_id, 403);

        return $section;
    }

    public function sheet(Request $request, ClassSection $section)
    {
        $section = $this->departmentSection($request, $section);

        return view('department.sheet', [
            'section' => $section,
            'enrollments' => $section->enrollments->sortBy('student.last_name'),
        ]);
    }

    public function decideSheet(Request $request, ClassSection $section)
    {
        $section = $this->departmentSection($request, $section);

        if ($section->sheet_status !== 'Submitted') {
            return back()->withErrors(['action' => "Only submitted sheets can be decided. This sheet is {$section->sheet_status}."]);
        }

        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'return'])],
            'remarks' => ['required_if:action,return', 'nullable', 'string', 'max:255'],
        ]);

        if ($data['action'] === 'approve') {
            $section->update(['sheet_status' => 'Approved', 'approved_by' => $request->user()->id, 'approved_at' => now(), 'return_remarks' => null]);
        } else {
            $section->update(['sheet_status' => 'Returned', 'return_remarks' => $data['remarks']]);
        }

        AuditLog::record('UPDATE', 'class_sections', $section->id, ['sheet_status' => 'Submitted'], ['sheet_status' => $section->sheet_status]);
        Notification::notify($section->faculty_id, "Grade sheet {$section->sheet_status}",
            "{$section->subject->code} {$section->section_code} was {$section->sheet_status}.".($data['action'] === 'return' ? " Remarks: {$data['remarks']}" : ''));

        return redirect()->route('head.sheets')->with('status', "Grade sheet {$section->sheet_status}.");
    }
}
