<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    /** Amount still unpaid on one charge. Only Posted payments count. */
    public static function assessmentBalance(Assessment $assessment): float
    {
        $paid = (float) $assessment->payments()->where('status', 'Posted')->sum('amount');

        return round($assessment->amount - $paid, 2);
    }

    /**
     * Outstanding balance of a student.
     *
     * @param  bool  $schoolFeesOnly  exclude document fees (used for financial clearance)
     */
    public static function balance(Student $student, ?int $termId = null, bool $schoolFeesOnly = false): float
    {
        $assessments = Assessment::where('student_id', $student->id)
            ->when($termId, fn ($q) => $q->where('term_id', $termId))
            ->when($schoolFeesOnly, fn ($q) => $q->where('fee_type', '!=', 'Document'));

        $charged = (float) (clone $assessments)->sum('amount');
        $paid = (float) Payment::where('status', 'Posted')
            ->whereIn('assessment_id', (clone $assessments)->select('id'))
            ->sum('amount');

        return round($charged - $paid, 2);
    }

    /**
     * Post a payment. The payment row, the linked document request status, and the
     * cashier clearance are saved in one transaction so none can succeed alone.
     */
    public static function postPayment(Assessment $assessment, float $amount, ?string $orNumber, string $method, User $cashier, ?Payment $pending = null): Payment
    {
        return DB::transaction(function () use ($assessment, $amount, $orNumber, $method, $cashier, $pending) {
            $balance = self::assessmentBalance($assessment);
            if ($amount <= 0 || $amount > $balance) {
                throw new DomainException('Amount must be greater than zero and not more than the balance of '.number_format($balance, 2).'.');
            }

            $attributes = [
                'or_number' => $orNumber,
                'amount' => $amount,
                'method' => $method,
                'status' => 'Posted',
                'posted_by' => $cashier->id,
            ];

            if ($pending) {
                $pending->forceFill($attributes)->save();
                $payment = $pending;
            } else {
                $payment = Payment::create($attributes + ['assessment_id' => $assessment->id, 'paid_at' => now()]);
            }

            AuditLog::record('CREATE', 'payments', $payment->id, null, ['or_number' => $orNumber, 'amount' => $amount], $cashier->id);

            $request = $assessment->documentRequest;
            if ($request && $request->status === 'Pending Payment' && self::assessmentBalance($assessment) <= 0) {
                DocumentRequestService::changeStatus($request, 'Paid', $cashier, $orNumber ? "OR {$orNumber}" : "Ref. {$payment->reference_no}");
            }

            ClearanceService::syncCashier($assessment->student, $assessment->term_id);

            return $payment;
        });
    }

    // ----- Online payments (GCash, Maya, online banking) -----

    public const ONLINE_METHODS = ['GCash' => 'GC', 'Maya' => 'MY', 'Online Banking' => 'OB'];

    /**
     * Charges the student can still pay online. Each gets ->balance (unpaid), ->pending
     * (sent but not yet verified) and ->payable (what a new payment would cover).
     */
    public static function payables(Student $student): Collection
    {
        return Assessment::with(['payments', 'term'])->where('student_id', $student->id)->orderBy('id')->get()
            ->each(function (Assessment $assessment) {
                $assessment->balance = round($assessment->amount - $assessment->payments->where('status', 'Posted')->sum('amount'), 2);
                $assessment->pending = round((float) $assessment->payments->where('status', 'For Verification')->sum('amount'), 2);
                $assessment->payable = max(round($assessment->balance - $assessment->pending, 2), 0);
            })
            ->filter(fn (Assessment $assessment) => $assessment->balance > 0)
            ->values();
    }

    /**
     * Record an online payment for everything the student owes. It stays "For Verification"
     * until the Cashier confirms the money was received.
     *
     * @return array{reference: string, amount: float}
     */
    public static function startOnlinePayment(Student $student, string $method): array
    {
        $payables = self::payables($student)->where('payable', '>', 0);
        if ($payables->isEmpty()) {
            throw new DomainException('You have nothing left to pay.');
        }

        $reference = self::ONLINE_METHODS[$method].'-'.random_int(10000000, 99999999);

        DB::transaction(function () use ($payables, $method, $reference, $student) {
            foreach ($payables as $assessment) {
                Payment::create([
                    'assessment_id' => $assessment->id,
                    'reference_no' => $reference,
                    'amount' => $assessment->payable,
                    'method' => $method,
                    'status' => 'For Verification',
                    'paid_at' => now(),
                ]);
            }

            AuditLog::record('CREATE', 'payments', null, null, ['reference_no' => $reference, 'method' => $method, 'amount' => $payables->sum('payable')], $student->user_id);

            foreach (User::whereHas('role', fn ($q) => $q->where('name', 'Cashier'))->where('status', 'Active')->pluck('id') as $cashierId) {
                Notification::notify($cashierId, 'Online payment to verify', "{$student->student_no} paid ₱".number_format($payables->sum('payable'), 2)." via {$method}. Ref. {$reference}.");
            }
        });

        return ['reference' => $reference, 'amount' => round($payables->sum('payable'), 2)];
    }

    /** Cashier confirms or rejects every charge covered by one online reference. */
    public static function decideOnlinePayment(string $reference, bool $confirm, User $cashier): float
    {
        $payments = Payment::with('assessment.student')->where('reference_no', $reference)->where('status', 'For Verification')->get();
        if ($payments->isEmpty()) {
            throw new DomainException('That payment has already been decided.');
        }

        return DB::transaction(function () use ($payments, $confirm, $cashier, $reference) {
            foreach ($payments as $payment) {
                if ($confirm) {
                    self::postPayment($payment->assessment, $payment->amount, null, $payment->method, $cashier, $payment);
                } else {
                    $payment->update(['status' => 'Voided', 'posted_by' => $cashier->id]);
                    AuditLog::record('UPDATE', 'payments', $payment->id, ['status' => 'For Verification'], ['status' => 'Voided'], $cashier->id);
                }
            }

            $student = $payments->first()->assessment->student;
            Notification::notify($student->user_id, $confirm ? 'Payment verified' : 'Payment not verified', $confirm
                ? "Your payment {$reference} of ₱".number_format($payments->sum('amount'), 2).' was verified by the Cashier.'
                : "Your payment {$reference} could not be verified. Please pay again or go to the Cashier.");

            return round($payments->sum('amount'), 2);
        });
    }
}
