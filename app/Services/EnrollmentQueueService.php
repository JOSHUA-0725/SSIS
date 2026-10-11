<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\QueueTicket;
use App\Models\Student;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class EnrollmentQueueService
{
    /** A student holding one of these is still in line. */
    public const OPEN = ['Waiting', 'Serving'];

    /** The ticket that keeps the student in today's line, if any. */
    public static function openTicket(Student $student): ?QueueTicket
    {
        return QueueTicket::today()->where('student_id', $student->id)->whereIn('status', self::OPEN)->first();
    }

    /** How many students are called before this ticket. */
    public static function ahead(QueueTicket $ticket): int
    {
        return QueueTicket::today()->where('status', 'Waiting')->where('number', '<', $ticket->number)->count();
    }

    /** Give the student the next number of today's line. */
    public static function join(Student $student): QueueTicket
    {
        $term = AcademicTerm::active();
        if (! $term) {
            throw new DomainException('Enrollment is not open: no term is active.');
        }
        if (in_array($student->status, ['Graduated', 'Dropped'], true)) {
            throw new DomainException("Your record is marked {$student->status}, so you cannot line up for enrollment. Visit the Registrar.");
        }
        if (self::openTicket($student)) {
            throw new DomainException('You already have a queue number today.');
        }

        $category = EnrollmentService::category($student);
        if ($category === 'Regular') {
            throw new DomainException('Regular students enroll with their block section and do not need a queue number.');
        }
        // A student who already holds a slot this term may line up again without taking another one.
        $departmentId = $student->program->department_id;
        if (! EnrollmentService::holdsSlot($student, $term->id) && EnrollmentService::remaining($term->id, $departmentId, $category) === 0) {
            throw new DomainException(EnrollmentService::approved($term->id, $departmentId, $category) === 0
                ? "No enrollment slots have been approved yet for {$category} students of your department."
                : "All approved enrollment slots for {$category} students of your department are taken.");
        }

        return DB::transaction(function () use ($student, $term) {
            $ticket = QueueTicket::create([
                'student_id' => $student->id,
                'term_id' => $term->id,
                'queue_date' => today(),
                'number' => (int) QueueTicket::today()->lockForUpdate()->max('number') + 1,
            ]);
            AuditLog::record('QUEUE_JOIN', 'queue_tickets', $ticket->id, null, ['number' => $ticket->number], $student->user_id);

            return $ticket;
        });
    }

    /** The student gives up a number that has not been called yet. */
    public static function leave(QueueTicket $ticket): void
    {
        if ($ticket->status !== 'Waiting') {
            throw new DomainException('Only a number that is still waiting can be cancelled.');
        }

        $ticket->update(['status' => 'Cancelled', 'finished_at' => now()]);
        AuditLog::record('QUEUE_LEAVE', 'queue_tickets', $ticket->id, ['status' => 'Waiting'], ['status' => 'Cancelled']);
    }

    /** Call the lowest waiting number to this Registrar's window. The one being served is marked done first. */
    public static function callNext(User $by): QueueTicket
    {
        return DB::transaction(function () use ($by) {
            $next = QueueTicket::today()->where('status', 'Waiting')->orderBy('number')->lockForUpdate()->first();
            if (! $next) {
                throw new DomainException('No one is waiting in line.');
            }

            foreach (QueueTicket::today()->where('status', 'Serving')->where('served_by', $by->id)->get() as $current) {
                self::finish($current, 'Done', $by);
            }

            $next->update(['status' => 'Serving', 'served_by' => $by->id, 'called_at' => now()]);
            AuditLog::record('QUEUE_CALL', 'queue_tickets', $next->id, ['status' => 'Waiting'], ['status' => 'Serving'], $by->id);
            Notification::notify($next->student->user_id, 'It is your turn', "Queue number {$next->label} is now being served. Please go to the Registrar window.");

            // A heads-up for whoever is called after this one.
            $upcoming = QueueTicket::today()->where('status', 'Waiting')->orderBy('number')->first();
            if ($upcoming) {
                Notification::notify($upcoming->student->user_id, 'You are next in line', "Queue number {$upcoming->label} will be called next. Please stay near the Registrar window.");
            }

            return $next;
        });
    }

    /** Close the ticket being served: Done when the student was attended to, Skipped when they did not show up. */
    public static function finish(QueueTicket $ticket, string $status, User $by): void
    {
        if ($ticket->status !== 'Serving') {
            throw new DomainException('Only the number being served can be closed.');
        }

        $ticket->update(['status' => $status, 'finished_at' => now()]);
        AuditLog::record('QUEUE_'.strtoupper($status), 'queue_tickets', $ticket->id, ['status' => 'Serving'], ['status' => $status], $by->id);

        if ($status === 'Skipped') {
            Notification::notify($ticket->student->user_id, 'Queue number skipped', "Queue number {$ticket->label} was called but no one came. Get a new number to line up again.");
        }
    }
}
