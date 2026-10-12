<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\AuditLog;
use App\Models\ClassSection;
use App\Models\Grade;
use App\Models\Notification;
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

    /** Accounts that can teach in a department: its professors and its faculty. */
    private function instructors(int $departmentId)
    {
        return User::whereHas('role', fn ($q) => $q->whereIn('name', ['Professor', 'Faculty']))
            ->where('department_id', $departmentId)->where('status', 'Active')->orderBy('name')->get();
    }

    public function loads(Request $request)
    {
        $departmentId = $request->user()->department_id;
        abort_unless($departmentId, 403, 'Your account is not assigned to a department.');
        $term = AcademicTerm::active();

        return view('department.loads', [
            'term' => $term,
            'department' => $request->user()->department,
            'instructors' => $this->instructors($departmentId),
            'sections' => ClassSection::with(['subject', 'faculty'])->withCount('enrollments')
                ->where('term_id', $term?->id)
                ->whereHas('subject', fn ($q) => $q->where('department_id', $departmentId))
                ->get()->sortBy(fn ($section) => $section->subject->code.' '.$section->section_code)->values(),
        ]);
    }

    public function assignLoad(Request $request, ClassSection $section)
    {
        $departmentId = $request->user()->department_id;
        abort_unless($departmentId && $section->subject->department_id === $departmentId, 403);

        $data = $request->validate(['faculty_id' => ['required', Rule::in($this->instructors($departmentId)->pluck('id')->all())]]);

        // Once a grade sheet is with the Department Head, the name on it must not change.
        if (in_array($section->sheet_status, ['Submitted', 'Approved'], true)) {
            return back()->withErrors(['faculty_id' => "The grade sheet of {$section->subject->code} is already ".strtolower($section->sheet_status).', so its professor cannot be changed.']);
        }
        if ((int) $data['faculty_id'] === $section->faculty_id) {
            return back();
        }

        $old = $section->faculty_id;
        $section->update(['faculty_id' => (int) $data['faculty_id']]);
        AuditLog::record('UPDATE', 'class_sections', $section->id, ['faculty_id' => $old], ['faculty_id' => $section->faculty_id]);
        Notification::notify($section->faculty_id, 'Subject assigned to you', "{$section->subject->code} · {$section->subject->title} ({$section->section_code}) is now in My classes.");

        return back()->with('status', "{$section->subject->code} ({$section->section_code}) is now handled by {$section->faculty()->value('name')}.");
    }

    // ----- Faculty: enrollment of the department -----

    public function enrollment(Request $request)
    {
        $departmentId = $request->user()->department_id;
        abort_unless($departmentId, 403, 'Your account is not assigned to a department.');
        $term = AcademicTerm::active();

        $regulars = Student::where('year_level', '>', 1)->where('student_type', 'Regular')
            ->whereNotIn('status', ['Graduated', 'Dropped'])
            ->whereHas('program', fn ($q) => $q->where('department_id', $departmentId));

        return view('department.enrollment', [
            'term' => $term,
            'department' => $request->user()->department,
            'open' => $term && EnrollmentService::blockOpen($term->id, $departmentId),
            'regulars' => (clone $regulars)->count(),
            'regularsEnrolled' => $term ? (clone $regulars)->whereHas('enrollments.section', fn ($q) => $q->where('term_id', $term->id))->count() : 0,
            'categories' => collect(EnrollmentService::SLOT_CATEGORIES)->mapWithKeys(fn ($category) => [$category => [
                'approved' => $term ? EnrollmentService::approved($term->id, $departmentId, $category) : 0,
                'used' => $term ? EnrollmentService::used($term->id, $departmentId, $category) : 0,
            ]]),
            'requests' => SlotRequest::with(['requester', 'decider'])->where('department_id', $departmentId)
                ->where('term_id', $term?->id)->latest('id')->get(),
        ]);
    }

    public function setBlockEnrollment(Request $request)
    {
        $data = $request->validate(['open' => ['required', 'boolean']]);
        abort_unless($request->user()->department_id, 403);
        $term = AcademicTerm::active();
        if (! $term) {
            return back()->withErrors(['open' => 'No term is active.']);
        }

        EnrollmentService::setBlockOpen($request->user(), $term, (bool) $data['open']);

        return back()->with('status', $data['open'] ? 'Enrollment is open. Regular students can now enroll in their block sections.' : 'Enrollment is closed for regular students.');
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

    // ----- Faculty: grade encoding -----

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

    /** Faculty can open only the class sections assigned to them. */
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
            'grades.*.midterm' => ['nullable', 'numeric', 'between:1,5'],
            'grades.*.final' => ['nullable', 'numeric', 'between:1,5'],
        ], [
            'grades.*.midterm.between' => 'Grades must be from 1.00 to 5.00.',
            'grades.*.final.between' => 'Grades must be from 1.00 to 5.00.',
        ]);

        $enrollments = $section->enrollments->keyBy('id');

        if ($data['action'] === 'submit') {
            foreach ($enrollments as $id => $enrollment) {
                if (! isset($data['grades'][$id]['midterm'], $data['grades'][$id]['final'])) {
                    return back()->withErrors(['grades' => 'Every student needs a midterm and a final grade before the sheet can be submitted.'])->withInput();
                }
            }
        }

        DB::transaction(function () use ($data, $enrollments, $section, $request) {
            foreach ($data['grades'] as $enrollmentId => $row) {
                // Rows for students outside this section are ignored.
                if (! $enrollments->has($enrollmentId)) {
                    continue;
                }
                $midterm = isset($row['midterm']) ? (float) $row['midterm'] : null;
                $final = isset($row['final']) ? (float) $row['final'] : null;
                $rating = GradeService::finalRating($midterm, $final);

                $grade = Grade::firstOrNew(['enrollment_id' => $enrollmentId]);
                $old = $grade->only(['midterm_grade', 'final_grade']);
                $grade->fill([
                    'midterm_grade' => $midterm,
                    'final_grade' => $final,
                    'final_rating' => $rating,
                    'remarks' => GradeService::remarks($rating),
                    'encoded_by' => $request->user()->id,
                ]);

                if ($grade->isDirty(['midterm_grade', 'final_grade'])) {
                    $grade->save();
                    AuditLog::record($grade->wasRecentlyCreated ? 'CREATE' : 'UPDATE', 'grades', $grade->id, $old, ['midterm_grade' => $midterm, 'final_grade' => $final]);
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
