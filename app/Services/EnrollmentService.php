<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\AdvisedSubject;
use App\Models\AuditLog;
use App\Models\ClassSection;
use App\Models\CurriculumSubject;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\SlotRequest;
use App\Models\Student;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Two ways to enroll.
 * Regular students enroll by themselves once the Registrar opens enrollment: they get the subjects
 * the curriculum gives their year level this semester.
 * Irregular students do not enroll with them. Their department's Faculty asks the Registrar for slots,
 * then opens chosen subjects for each student, and the student enrolls in exactly those.
 */
class EnrollmentService
{
    /** Students of these kinds are handled one by one by Faculty, within the slots the Registrar approved. */
    public const SLOT_CATEGORIES = ['Irregular'];

    /** Irregular or Regular. */
    public static function category(Student $student): string
    {
        return $student->student_type === 'Irregular' ? 'Irregular' : 'Regular';
    }

    private static function blocked(Student $student): void
    {
        if (in_array($student->status, ['Graduated', 'Dropped'], true)) {
            throw new DomainException("Your record is marked {$student->status}, so you cannot enroll. Visit the Registrar.");
        }
    }

    private static function finish(Student $student, AcademicTerm $term, Collection $sections, string $action): int
    {
        return DB::transaction(function () use ($student, $term, $sections, $action) {
            $added = 0;
            foreach ($sections as $section) {
                $added += (int) Enrollment::firstOrCreate(['student_id' => $student->id, 'section_id' => $section->id])->wasRecentlyCreated;
            }

            $student->update(['status' => 'Enrolled']);
            ClearanceService::ensureFor($student, $term);
            AuditLog::record($action, 'students', $student->id, null, ['subjects' => $sections->pluck('subject.code')->all(), 'added' => $added], $student->user_id);

            return $added;
        });
    }

    // ----- Slots for irregular students -----

    public static function approved(int $termId, int $departmentId, string $category): int
    {
        return (int) SlotRequest::where(['term_id' => $termId, 'department_id' => $departmentId, 'category' => $category, 'status' => 'Approved'])->sum('slots');
    }

    /** Irregular students of the department who already hold a slot this term: Faculty opened subjects for them. */
    public static function used(int $termId, int $departmentId, string $category = 'Irregular'): int
    {
        return Student::whereHas('program', fn ($q) => $q->where('department_id', $departmentId))
            ->whereIn('id', AdvisedSubject::whereHas('section', fn ($q) => $q->where('term_id', $termId))->select('student_id'))
            ->count();
    }

    public static function remaining(int $termId, int $departmentId, string $category = 'Irregular'): int
    {
        return max(0, self::approved($termId, $departmentId, $category) - self::used($termId, $departmentId, $category));
    }

    public static function holdsSlot(Student $student, int $termId): bool
    {
        return AdvisedSubject::where('student_id', $student->id)->whereHas('section', fn ($q) => $q->where('term_id', $termId))->exists();
    }

    public static function requestSlots(User $faculty, AcademicTerm $term, string $category, int $slots): SlotRequest
    {
        $pending = SlotRequest::where(['term_id' => $term->id, 'department_id' => $faculty->department_id, 'category' => $category, 'status' => 'Pending'])->exists();
        if ($pending) {
            throw new DomainException("A request for {$category} slots is still waiting for the Registrar.");
        }

        $slotRequest = SlotRequest::create([
            'term_id' => $term->id, 'department_id' => $faculty->department_id,
            'category' => $category, 'slots' => $slots, 'requested_by' => $faculty->id,
        ]);
        AuditLog::record('CREATE', 'slot_requests', $slotRequest->id, null, ['category' => $category, 'slots' => $slots], $faculty->id);

        $registrars = User::whereHas('role', fn ($q) => $q->where('name', 'Registrar'))->where('status', 'Active')->pluck('id');
        foreach ($registrars as $registrarId) {
            Notification::notify($registrarId, 'Enrollment slots requested', "{$faculty->name} asked for {$slots} {$category} slots. Open Slot requests to decide.");
        }

        return $slotRequest;
    }

    public static function decide(SlotRequest $slotRequest, string $status, ?string $remarks, User $registrar): void
    {
        if ($slotRequest->status !== 'Pending') {
            throw new DomainException('This request was already decided.');
        }

        $slotRequest->update(['status' => $status, 'remarks' => $remarks, 'decided_by' => $registrar->id, 'decided_at' => now()]);
        AuditLog::record('UPDATE', 'slot_requests', $slotRequest->id, ['status' => 'Pending'], ['status' => $status, 'remarks' => $remarks], $registrar->id);
        Notification::notify(
            $slotRequest->requested_by,
            "Slot request {$status}",
            "Your request for {$slotRequest->slots} {$slotRequest->category} slots was ".strtolower($status).'.'.($remarks ? " Remarks: {$remarks}" : '')
        );
    }

    // ----- Regular students: the subjects of their year level, once the Registrar opens enrollment -----

    /**
     * What the curriculum gives the student this semester. Each row holds the subject and the class
     * the student would join: the one of their block section when there is one, otherwise the first
     * section offered. A subject nobody has opened yet has no class.
     *
     * @return Collection<int, array{subject: \App\Models\Subject, section: ?ClassSection}>
     */
    public static function regularSubjects(Student $student, AcademicTerm $term): Collection
    {
        $rows = CurriculumSubject::with('subject')
            ->where(['program_id' => $student->program_id, 'year_level' => $student->year_level, 'semester' => $term->semester])
            ->get()->sortBy('subject.code')->values();

        $classes = ClassSection::with(['subject', 'faculty'])->where('term_id', $term->id)
            ->whereIn('subject_id', $rows->pluck('subject_id'))->orderBy('section_code')->get()->groupBy('subject_id');

        return $rows->map(function ($row) use ($classes, $student) {
            $offered = $classes->get($row->subject_id, collect());

            return [
                'subject' => $row->subject,
                'section' => $offered->firstWhere('section_code', $student->block_section) ?? $offered->first(),
            ];
        });
    }

    /**
     * Offers every subject the curricula place in this semester, so regular students have something to enroll in
     * the moment the Registrar opens enrollment. Each year level gets one class per block section its regular
     * students belong to (section A when none is set). The classes start without a professor; Faculty assigns one.
     * Returns how many classes were added.
     */
    public static function offerCurriculum(AcademicTerm $term): int
    {
        $added = 0;
        $rows = CurriculumSubject::with('program')->where('semester', $term->semester)->get()->groupBy(fn ($row) => $row->program_id.'-'.$row->year_level);

        foreach ($rows as $group) {
            $program = $group->first()->program;
            $year = (int) $group->first()->year_level;

            $blocks = Student::where(['program_id' => $program->id, 'year_level' => $year, 'student_type' => 'Regular'])
                ->whereNotIn('status', ['Graduated', 'Dropped'])->whereNotNull('block_section')
                ->distinct()->pluck('block_section')
                ->filter(fn ($code) => preg_match('/^'.preg_quote($program->code, '/').'-'.$year.'[A-Za-z]$/', $code))
                ->map(fn ($code) => strtoupper($code))->unique()->sort()->values();
            if ($blocks->isEmpty()) {
                $blocks = collect(["{$program->code}-{$year}A"]);
            }

            foreach ($group as $row) {
                foreach ($blocks as $code) {
                    $added += (int) ClassSection::firstOrCreate(['subject_id' => $row->subject_id, 'term_id' => $term->id, 'section_code' => $code])->wasRecentlyCreated;
                }
            }
        }

        if ($added > 0) {
            AuditLog::record('OFFER_SUBJECTS', 'class_sections', null, null, ['term_id' => $term->id, 'classes' => $added]);
        }

        return $added;
    }

    /** Enroll a regular student in every offered subject of their year level. Returns how many subjects were added. */
    public static function enrollRegular(Student $student): int
    {
        $term = AcademicTerm::active();
        if (! $term) {
            throw new DomainException('Enrollment is not open: no term is active.');
        }
        if (self::category($student) !== 'Regular') {
            throw new DomainException('Irregular students enroll in the subjects their department opens for them.');
        }
        self::blocked($student);
        if (! $term->enrollment_open) {
            throw new DomainException('The Registrar has not opened enrollment yet.');
        }

        $sections = self::regularSubjects($student, $term)->pluck('section')->filter()->values();
        if ($sections->isEmpty()) {
            throw new DomainException('No subject of your year level is offered yet this term. Ask your department.');
        }

        return self::finish($student, $term, $sections, 'ENROLL_REGULAR');
    }

    // ----- Irregular students: the subjects Faculty opened for them -----

    /** The classes Faculty opened for the student this term. */
    public static function advisedSections(Student $student, AcademicTerm $term): Collection
    {
        return ClassSection::with(['subject', 'faculty'])->where('term_id', $term->id)
            ->whereIn('id', AdvisedSubject::where('student_id', $student->id)->select('section_id'))
            ->get()->sortBy('subject.code')->values();
    }

    /**
     * Faculty sets which classes an irregular student may enroll in this term.
     * A class the student already enrolled in stays open even when it is left out.
     *
     * @param  int[]  $sectionIds
     */
    public static function advise(User $faculty, Student $student, AcademicTerm $term, array $sectionIds): Collection
    {
        if ($student->program->department_id !== $faculty->department_id) {
            throw new DomainException('That student belongs to another department.');
        }
        if (self::category($student) !== 'Irregular') {
            throw new DomainException('Regular students enroll by themselves once the Registrar opens enrollment.');
        }

        $allowed = ClassSection::where('term_id', $term->id)->whereIn('id', $sectionIds)
            ->whereHas('subject', fn ($q) => $q->where('department_id', $faculty->department_id))->pluck('id');
        $enrolled = Enrollment::where('student_id', $student->id)->whereHas('section', fn ($q) => $q->where('term_id', $term->id))->pluck('section_id');
        $keep = $allowed->merge($enrolled)->unique()->values();

        // The first subject opened for a student takes one of the approved slots.
        if ($keep->isNotEmpty() && ! self::holdsSlot($student, $term->id) && self::remaining($term->id, $faculty->department_id) === 0) {
            throw new DomainException(self::approved($term->id, $faculty->department_id, 'Irregular') === 0
                ? 'No slots for irregular students have been approved yet. Ask the Registrar for slots first.'
                : 'All approved slots for irregular students are taken. Ask the Registrar for more.');
        }

        DB::transaction(function () use ($faculty, $student, $term, $keep) {
            AdvisedSubject::where('student_id', $student->id)
                ->whereHas('section', fn ($q) => $q->where('term_id', $term->id))
                ->whereNotIn('section_id', $keep)->delete();
            foreach ($keep as $sectionId) {
                AdvisedSubject::firstOrCreate(['student_id' => $student->id, 'section_id' => $sectionId], ['advised_by' => $faculty->id]);
            }
        });

        $sections = self::advisedSections($student, $term);
        AuditLog::record('ADVISE_SUBJECTS', 'students', $student->id, null, ['subjects' => $sections->pluck('subject.code')->all()], $faculty->id);
        if ($sections->isNotEmpty()) {
            Notification::notify($student->user_id, 'Subjects opened for you', 'Your department opened '.$sections->count().' '.($sections->count() === 1 ? 'subject' : 'subjects').' for you: '.$sections->pluck('subject.code')->join(', ').'. Open Enrollment to enroll.');
        }

        return $sections;
    }

    /** Enroll an irregular student in every class Faculty opened for them. Returns how many subjects were added. */
    public static function enrollAdvised(Student $student): int
    {
        $term = AcademicTerm::active();
        if (! $term) {
            throw new DomainException('Enrollment is not open: no term is active.');
        }
        self::blocked($student);

        $sections = self::advisedSections($student, $term);
        if ($sections->isEmpty()) {
            throw new DomainException('Your department has not opened any subject for you yet.');
        }

        return self::finish($student, $term, $sections, 'ENROLL_ADVISED');
    }
}
