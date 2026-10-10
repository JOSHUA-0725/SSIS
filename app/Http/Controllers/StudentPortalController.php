<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\Clearance;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\ClearanceService;
use App\Services\DocumentRequestService;
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
            ->orderByDesc('school_year')->orderByDesc('id')->get();
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
            return ['eyebrow' => "What's blocking your enrollment", 'title' => 'Settle your balance to clear the Cashier.', 'amount' => $balance, 'cta' => 'Pay online now', 'url' => route('student.payments')];
        }

        $waiting = $items->where('status', '!=', 'Cleared')->count();

        return ['eyebrow' => 'Almost there', 'title' => "Waiting for {$waiting} ".($waiting === 1 ? 'office' : 'offices').' to sign off.', 'amount' => null, 'cta' => 'View signatories', 'url' => route('student.clearance')];
    }

    public function grades(Request $request)
    {
        $student = $this->student($request);
        $request->validate(['term' => ['nullable', 'integer']]);

        $terms = $this->releasedTerms($student);
        $term = $terms->firstWhere('id', (int) $request->query('term')) ?? $terms->first();

        return view('student.grades', [
            'terms' => $terms,
            'term' => $term,
            'grades' => $term ? $this->termGrades($student, $term) : null,
            'unreleased' => AcademicTerm::where('grades_released', false)->where('is_active', true)->first(),
            'certificate' => DocumentType::where('name', 'Certificate of Grades')->where('is_active', true)->first(),
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
            'methods' => array_keys(LedgerService::ONLINE_METHODS),
            'history' => Payment::with('assessment')
                ->whereHas('assessment', fn ($q) => $q->where('student_id', $student->id))
                ->latest('paid_at')->latest('id')->get(),
        ]);
    }

    public function checkout(Request $request)
    {
        $student = $this->student($request);
        $data = $request->validate(['method' => ['required', Rule::in(array_keys(LedgerService::ONLINE_METHODS))]]);
        $payables = LedgerService::payables($student)->where('payable', '>', 0);

        if ($payables->isEmpty()) {
            return redirect()->route('student.payments')->withErrors(['method' => 'You have nothing left to pay.']);
        }

        return view('student.checkout', [
            'method' => $data['method'],
            'payables' => $payables,
            'total' => $payables->sum('payable'),
        ]);
    }

    public function pay(Request $request)
    {
        $student = $this->student($request);
        $data = $request->validate(['method' => ['required', Rule::in(array_keys(LedgerService::ONLINE_METHODS))]]);

        try {
            $payment = LedgerService::startOnlinePayment($student, $data['method']);
        } catch (DomainException $e) {
            return redirect()->route('student.payments')->withErrors(['method' => $e->getMessage()]);
        }

        return redirect()->route('student.payments')->with('status',
            '₱'.number_format($payment['amount'], 2)." sent through {$data['method']}. Reference {$payment['reference']}. It will show as paid once the Cashier verifies it.");
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
