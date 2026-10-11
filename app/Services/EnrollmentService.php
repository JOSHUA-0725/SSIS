<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\AuditLog;
use App\Models\BlockEnrollment;
use App\Models\ClassSection;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\QueueTicket;
use App\Models\SlotRequest;
use App\Models\Student;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EnrollmentService
{
    /** Students of these kinds are enrolled one by one, within the slots the Registrar approved. */
    public const SLOT_CATEGORIES = ['Irregular', 'First Year'];

    /** A queue ticket in one of these states uses up a slot. */
    private const SLOT_HELD = ['Waiting', 'Serving', 'Done'];

    /** First Year, Irregular, or Regular. A first-year student is always First Year. */
    public static function category(Student $student): string
    {
        return match (true) {
            (int) $student->year_level === 1 => 'First Year',
            $student->student_type === 'Irregular' => 'Irregular',
            default => 'Regular',
        };
    }

    // ----- Slots for irregular and first-year students -----

    public static function approved(int $termId, int $departmentId, string $category): int
    {
        return (int) SlotRequest::where(['term_id' => $termId, 'department_id' => $departmentId, 'category' => $category, 'status' => 'Approved'])->sum('slots');
    }

    /** Students of the category who already hold a slot this term: they lined up and were not skipped. */
    public static function used(int $termId, int $departmentId, string $category): int
    {
        $students = $category === 'First Year'
            ? Student::where('year_level', 1)
            : Student::where('year_level', '>', 1)->where('student_type', 'Irregular');

        return $students
            ->whereHas('program', fn ($q) => $q->where('department_id', $departmentId))
            ->whereIn('id', QueueTicket::where('term_id', $termId)->whereIn('status', self::SLOT_HELD)->select('student_id'))
            ->count();
    }

    public static function remaining(int $termId, int $departmentId, string $category): int
    {
        return max(0, self::approved($termId, $departmentId, $category) - self::used($termId, $departmentId, $category));
    }

    public static function holdsSlot(Student $student, int $termId): bool
    {
        return QueueTicket::where(['term_id' => $termId, 'student_id' => $student->id])->whereIn('status', self::SLOT_HELD)->exists();
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

    // ----- Block enrollment for regular students -----

    public static function blockOpen(int $termId, int $departmentId): bool
    {
        return BlockEnrollment::where(['term_id' => $termId, 'department_id' => $departmentId, 'is_open' => true])->exists();
    }

    public static function setBlockOpen(User $faculty, AcademicTerm $term, bool $open): void
    {
        BlockEnrollment::updateOrCreate(
            ['term_id' => $term->id, 'department_id' => $faculty->department_id],
            ['is_open' => $open, 'updated_by' => $faculty->id]
        );
        AuditLog::record($open ? 'ENROLLMENT_OPEN' : 'ENROLLMENT_CLOSE', 'block_enrollments', null, null, ['department_id' => $faculty->department_id, 'term_id' => $term->id], $faculty->id);
    }

    /** The class sections of the student's block this term. */
    public static function blockSections(Student $student, AcademicTerm $term): Collection
    {
        if (! $student->block_section) {
            return collect();
        }

        return ClassSection::with(['subject', 'faculty'])->where('term_id', $term->id)
            ->where('section_code', $student->block_section)->get()->sortBy('subject.code')->values();
    }

    /** Enroll a regular student in every subject of their block. Returns how many subjects were added. */
    public static function enrollBlock(Student $student): int
    {
        $term = AcademicTerm::active();
        if (! $term) {
            throw new DomainException('Enrollment is not open: no term is active.');
        }
        if (self::category($student) !== 'Regular') {
            throw new DomainException('Block enrollment is for regular students. Get a queue number instead.');
        }
        if (in_array($student->status, ['Graduated', 'Dropped'], true)) {
            throw new DomainException("Your record is marked {$student->status}, so you cannot enroll. Visit the Registrar.");
        }
        if (! self::blockOpen($term->id, $student->program->department_id)) {
            throw new DomainException('Your department has not opened enrollment yet.');
        }

        $sections = self::blockSections($student, $term);
        if ($sections->isEmpty()) {
            throw new DomainException('No block section is set for you this term. Visit the Registrar.');
        }

        return DB::transaction(function () use ($student, $term, $sections) {
            $added = 0;
            foreach ($sections as $section) {
                $added += (int) Enrollment::firstOrCreate(['student_id' => $student->id, 'section_id' => $section->id])->wasRecentlyCreated;
            }

            $student->update(['status' => 'Enrolled']);
            ClearanceService::ensureFor($student, $term);
            AuditLog::record('ENROLL_BLOCK', 'students', $student->id, null, ['block_section' => $student->block_section, 'subjects' => $added], $student->user_id);

            return $added;
        });
    }
}
