<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\AuditLog;
use App\Models\Clearance;
use App\Models\ClearanceItem;
use App\Models\Notification;
use App\Models\Office;
use App\Models\Student;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class ClearanceService
{
    public const FINAL_OFFICE = 'REG';

    public const CASHIER_OFFICE = 'CASH';

    /** Create the clearance of a student for a term, with one item per active office. */
    public static function ensureFor(Student $student, AcademicTerm $term): Clearance
    {
        $clearance = Clearance::firstOrCreate(['student_id' => $student->id, 'term_id' => $term->id]);

        $existing = $clearance->items()->pluck('office_id')->all();
        foreach (Office::where('is_active', true)->whereNotIn('id', $existing)->get() as $office) {
            ClearanceItem::create(['clearance_id' => $clearance->id, 'office_id' => $office->id]);
        }

        if ($clearance->wasRecentlyCreated) {
            self::syncCashier($student, $term->id);
        }

        return $clearance;
    }

    /** True when every office item is Cleared. */
    public static function isComplete(Clearance $clearance): bool
    {
        return ! $clearance->items()->where('status', '!=', 'Cleared')->exists();
    }

    public static function refreshOverall(Clearance $clearance): void
    {
        $clearance->update(['overall_status' => self::isComplete($clearance) ? 'Cleared' : 'Pending']);
    }

    /** Financial clearance is given automatically once school fees for the term are fully paid. */
    public static function syncCashier(Student $student, int $termId): void
    {
        $clearance = Clearance::where(['student_id' => $student->id, 'term_id' => $termId])->first();
        $office = Office::where('code', self::CASHIER_OFFICE)->first();
        if (! $clearance || ! $office) {
            return;
        }

        $item = $clearance->items()->where('office_id', $office->id)->first();
        if ($item && $item->status !== 'Cleared' && LedgerService::balance($student, $termId, true) <= 0) {
            $item->update(['status' => 'Cleared', 'remarks' => 'Auto-cleared: balance settled', 'signed_at' => now()]);
            Notification::notify($student->user_id, 'Cashier clearance', 'Your financial clearance is now Cleared.');
            self::refreshOverall($clearance);
        }
    }

    /** Record an office decision on one clearance item. */
    public static function sign(ClearanceItem $item, string $status, ?string $remarks, User $by): void
    {
        $clearance = $item->clearance;
        $isFinal = $item->office->code === self::FINAL_OFFICE;

        if ($isFinal && $status === 'Cleared') {
            $holding = $clearance->items()->where('id', '!=', $item->id)->where('status', '!=', 'Cleared')->with('office')->get();
            if ($holding->isNotEmpty()) {
                throw new DomainException('Final clearance is blocked. Not yet cleared: '.$holding->pluck('office.name')->join(', ').'.');
            }
        }

        DB::transaction(function () use ($item, $status, $remarks, $by, $clearance, $isFinal) {
            $old = $item->status;
            $item->update([
                'status' => $status,
                'remarks' => $status === 'Cleared' ? null : $remarks,
                'signed_by' => $by->id,
                'signed_at' => now(),
            ]);

            // An office that withdraws its sign-off also withdraws the Registrar's final clearance.
            if (! $isFinal && $status !== 'Cleared') {
                $clearance->items()
                    ->whereHas('office', fn ($q) => $q->where('code', self::FINAL_OFFICE))
                    ->where('status', 'Cleared')
                    ->update(['status' => 'Pending', 'signed_by' => null, 'signed_at' => null]);
            }

            self::refreshOverall($clearance);

            AuditLog::record('UPDATE', 'clearance_items', $item->id, ['status' => $old], ['status' => $status, 'remarks' => $remarks], $by->id);

            Notification::notify(
                $clearance->student->user_id,
                "Clearance: {$item->office->name}",
                "{$item->office->name} set your clearance to {$status}.".($status === 'On Hold' && $remarks ? " Remarks: {$remarks}" : '')
            );
        });
    }
}
