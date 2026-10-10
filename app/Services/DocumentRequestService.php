<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\Notification;
use App\Models\RequestStatusHistory;
use App\Models\Student;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DocumentRequestService
{
    public const MAX_COPIES = 10;

    /** Allowed status changes. Payment can never be skipped. */
    public const TRANSITIONS = [
        'Pending Payment' => ['Paid', 'Cancelled'],
        'Paid' => ['Processing', 'Rejected'],
        'Processing' => ['Ready for Pickup', 'Rejected'],
        'Ready for Pickup' => ['Released'],
        'Released' => [],
        'Rejected' => [],
        'Cancelled' => [],
    ];

    public static function computeFee(float $feePerCopy, int $copies): float
    {
        if ($copies < 1 || $copies > self::MAX_COPIES) {
            throw new InvalidArgumentException('Copies must be between 1 and '.self::MAX_COPIES.'.');
        }

        return round($feePerCopy * $copies, 2);
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** File a request and create its payable in the student ledger. */
    public static function file(Student $student, DocumentType $type, int $copies, string $purpose, string $releaseMethod = 'Pickup'): DocumentRequest
    {
        $term = AcademicTerm::active();
        if (! $term) {
            throw new DomainException('No active academic term is set.');
        }
        $fee = self::computeFee($type->fee_per_copy, $copies);

        return DB::transaction(function () use ($student, $type, $copies, $purpose, $fee, $term, $releaseMethod) {
            $request = DocumentRequest::create([
                'reference_no' => 'DR-'.date('Y').'-'.strtoupper(Str::random(8)),
                'student_id' => $student->id,
                'document_type_id' => $type->id,
                'copies' => $copies,
                'purpose' => $purpose,
                'total_fee' => $fee,
                'release_method' => $releaseMethod,
                'status' => 'Pending Payment',
            ]);

            Assessment::create([
                'student_id' => $student->id,
                'term_id' => $term->id,
                'fee_type' => 'Document',
                'description' => "{$type->name} x {$copies} ({$request->reference_no})",
                'amount' => $fee,
                'document_request_id' => $request->id,
            ]);

            RequestStatusHistory::create([
                'document_request_id' => $request->id,
                'status' => 'Pending Payment',
                'remarks' => 'Request filed online',
                'changed_by' => $student->user_id,
            ]);

            AuditLog::record('CREATE', 'document_requests', $request->id, null, ['reference_no' => $request->reference_no]);

            return $request;
        });
    }

    /** Move a request to a new status, record the history, and notify the student. */
    public static function changeStatus(DocumentRequest $request, string $to, ?User $by, ?string $remarks = null, array $extra = []): void
    {
        if (! self::canTransition($request->status, $to)) {
            throw new DomainException("A request that is {$request->status} cannot be set to {$to}.");
        }

        DB::transaction(function () use ($request, $to, $by, $remarks, $extra) {
            $old = $request->status;
            $request->forceFill(['status' => $to] + $extra)->save();

            RequestStatusHistory::create([
                'document_request_id' => $request->id,
                'status' => $to,
                'remarks' => $remarks,
                'changed_by' => $by?->id,
            ]);

            AuditLog::record('UPDATE', 'document_requests', $request->id, ['status' => $old], ['status' => $to], $by?->id);

            Notification::notify(
                $request->student->user_id,
                "Document request {$to}",
                "Your request {$request->reference_no} is now {$to}.".($remarks ? " {$remarks}" : '')
            );
        });
    }
}
