<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Student;
use App\Services\LedgerService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CashierController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $students = $q === '' ? collect() : Student::with('program')
            ->where(fn ($w) => $w
                ->where('student_no', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('first_name', 'like', "%{$q}%"))
            ->orderBy('last_name')->limit(25)->get();

        return view('cashier.index', [
            'q' => $q,
            'students' => $students,
            'pending' => Assessment::with(['student', 'documentRequest'])
                ->whereHas('documentRequest', fn ($w) => $w->where('status', 'Pending Payment'))
                ->latest('id')->limit(20)->get(),
        ]);
    }

    public function student(Student $student)
    {
        $assessments = Assessment::with(['term', 'payments'])->where('student_id', $student->id)->latest('id')->get()
            ->each(fn ($a) => $a->balance = round($a->amount - $a->payments->where('status', 'Posted')->sum('amount'), 2));

        return view('cashier.student', [
            'student' => $student->load('program'),
            'assessments' => $assessments,
            'unpaid' => $assessments->where('balance', '>', 0),
            'payments' => Payment::with(['assessment', 'cashier'])
                ->whereIn('assessment_id', $assessments->pluck('id'))->latest('paid_at')->get(),
            'balance' => LedgerService::balance($student),
            'term' => AcademicTerm::active(),
        ]);
    }

    public function storePayment(Request $request, Student $student)
    {
        $data = $request->validate([
            'assessment_id' => ['required', Rule::exists('assessments', 'id')->where('student_id', $student->id)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'or_number' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/', 'unique:payments,or_number'],
            'method' => ['required', Rule::in(['Cash', 'Bank Deposit', 'E-wallet', 'GCash', 'Maya', 'Online Banking'])],
        ], ['or_number.unique' => 'That OR number has already been used.']);

        try {
            LedgerService::postPayment(Assessment::findOrFail($data['assessment_id']), (float) $data['amount'], $data['or_number'], $data['method'], $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        return back()->with('status', "Payment posted under OR {$data['or_number']}.");
    }

    public function storeAssessment(Request $request, Student $student)
    {
        $term = AcademicTerm::active();
        abort_unless($term, 404);

        $data = $request->validate([
            'fee_type' => ['required', Rule::in(['Tuition', 'Miscellaneous', 'Laboratory', 'Other'])],
            'description' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
        ]);

        $assessment = Assessment::create($data + ['student_id' => $student->id, 'term_id' => $term->id]);
        AuditLog::record('CREATE', 'assessments', $assessment->id, null, $data);

        return back()->with('status', 'Charge added to the student ledger.');
    }

    public function proofs()
    {
        $waiting = Payment::with(['assessment.student', 'assessment.documentRequest'])
            ->where('status', 'For Verification')->oldest('id')->get();

        return view('cashier.proofs', [
            'online' => $waiting->whereNotNull('reference_no')->groupBy('reference_no'),
            'proofs' => $waiting->whereNull('reference_no'),
        ]);
    }

    public function decideOnline(Request $request, string $reference)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['confirm', 'reject'])]]);

        try {
            $amount = LedgerService::decideOnlinePayment($reference, $data['action'] === 'confirm', $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return back()->with('status', $data['action'] === 'confirm'
            ? "Payment {$reference} of ₱".number_format($amount, 2).' confirmed and posted.'
            : "Payment {$reference} rejected. The student was notified.");
    }

    public function proofFile(Payment $payment)
    {
        abort_unless($payment->proof_file && Storage::disk('local')->exists($payment->proof_file), 404);

        return Storage::disk('local')->response($payment->proof_file);
    }

    public function decideProof(Request $request, Payment $payment)
    {
        abort_unless($payment->status === 'For Verification', 404);

        $data = $request->validate([
            'action' => ['required', Rule::in(['verify', 'void'])],
            'or_number' => ['required_if:action,verify', 'nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/', 'unique:payments,or_number'],
        ], ['or_number.unique' => 'That OR number has already been used.']);

        if ($data['action'] === 'void') {
            $payment->update(['status' => 'Voided', 'posted_by' => $request->user()->id]);
            AuditLog::record('UPDATE', 'payments', $payment->id, ['status' => 'For Verification'], ['status' => 'Voided']);
            Notification::notify($payment->assessment->student->user_id, 'Proof of payment not accepted',
                'Your uploaded proof of payment could not be verified. Please upload a clear copy or pay at the Cashier.');

            return back()->with('status', 'Proof voided. The student was notified.');
        }

        try {
            LedgerService::postPayment($payment->assessment, $payment->amount, $data['or_number'], $payment->method, $request->user(), $payment);
        } catch (DomainException $e) {
            return back()->withErrors(['or_number' => $e->getMessage()]);
        }

        return back()->with('status', "Proof verified and posted under OR {$data['or_number']}.");
    }

    public function report(Request $request)
    {
        $request->validate(['date' => ['nullable', 'date']]);
        $date = $request->query('date', today()->toDateString());

        $payments = Payment::with(['assessment.student', 'cashier'])
            ->where('status', 'Posted')->whereDate('paid_at', $date)->orderBy('paid_at')->get();

        if ($request->query('export') === 'csv') {
            AuditLog::record('EXPORT', 'payments', null, null, ['date' => $date]);

            return response()->streamDownload(function () use ($payments) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['OR or Reference Number', 'Time', 'Student No', 'Student', 'Description', 'Method', 'Amount', 'Posted By']);
                foreach ($payments as $p) {
                    fputcsv($out, [
                        $p->or_number ?? $p->reference_no, $p->paid_at->format('H:i'), $p->assessment->student->student_no,
                        $p->assessment->student->full_name, $p->assessment->description, $p->method,
                        number_format($p->amount, 2, '.', ''), $p->cashier?->name,
                    ]);
                }
                fclose($out);
            }, "collection-{$date}.csv", ['Content-Type' => 'text/csv']);
        }

        return view('cashier.report', ['date' => $date, 'payments' => $payments, 'total' => $payments->sum('amount')]);
    }
}
