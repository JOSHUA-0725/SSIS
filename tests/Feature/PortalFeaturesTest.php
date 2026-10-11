<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\ClearanceItem;
use App\Models\DocumentType;
use App\Models\Notification;
use App\Models\Office;
use App\Models\Payment;
use App\Services\ClearanceService;
use App\Services\DocumentRequestService;
use App\Services\LedgerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** The buttons added with the portal design: sign-in choices, online payment, e-copy, clearance slip. */
class PortalFeaturesTest extends SsisTestCase
{
    // ----- Sign in -----

    public function test_sign_in_choice_must_match_the_account(): void
    {
        $registrar = $this->makeUser('Registrar');
        $credentials = ['username' => $registrar->username, 'password' => self::PASSWORD];

        $this->post('/login', $credentials + ['portal' => 'student'])->assertSessionHasErrors('username');
        $this->assertGuest();

        $this->post('/login', $credentials + ['portal' => 'registrar'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($registrar);
    }

    public function test_department_choice_accepts_faculty_heads_and_clearance_offices(): void
    {
        foreach ([$this->makeUser('Faculty', ['department_id' => $this->ccs()]), $this->makeUser('Department Head', ['department_id' => $this->ccs()]), $this->signatory('LIB')] as $user) {
            $this->post('/login', ['portal' => 'department', 'username' => $user->username, 'password' => self::PASSWORD])->assertRedirect('/dashboard');
            $this->post('/logout');
        }
    }

    public function test_forgot_password_notifies_the_administrators_without_revealing_accounts(): void
    {
        $admin = $this->makeUser('Administrator');
        $student = $this->makeStudent();

        $known = $this->post('/forgot-password', ['username' => $student->student_no, 'email' => $student->user->email]);
        $unknown = $this->post('/forgot-password', ['username' => 'nobody', 'email' => 'nobody@college.test']);

        // Same answer whether or not the account exists.
        $this->assertSame($known->getSession()->get('status'), $unknown->getSession()->get('status'));
        $known->assertSessionHas('status');
        $unknown->assertSessionHas('status');
        $this->assertSame(1, Notification::where('user_id', $admin->id)->where('title', 'Password reset requested')->count());
    }

    public function test_public_pages_open_without_signing_in(): void
    {
        $this->get('/login')->assertOk()->assertSee('I am signing in as')->assertSee('Pamantasan ng Cabuyao')->assertSee('System documentation');
        $this->get('/forgot-password')->assertOk();
        $this->get('/documentation')->assertOk()->assertSee('SDLC plan');
    }

    // ----- Online payment -----

    public function test_online_payment_is_verified_by_the_cashier_then_clears_the_student(): void
    {
        $student = $this->makeStudent();
        $cashier = $this->makeUser('Cashier');
        Assessment::create(['student_id' => $student->id, 'term_id' => $this->term->id, 'fee_type' => 'Tuition', 'description' => 'Tuition', 'amount' => 1000]);
        ClearanceService::ensureFor($student, $this->term);
        $docRequest = DocumentRequestService::file($student, DocumentType::where('name', 'Transcript of Records')->first(), 1, 'Employment');

        $this->actingAs($student->user)->get('/student/payments')->assertOk()->assertSee('1,150.00');
        $this->actingAs($student->user)->get('/student/payments/checkout?method=GCash')->assertOk()->assertSee('Confirm payment of');
        $this->actingAs($student->user)->post('/student/payments', ['method' => 'GCash'])->assertRedirect('/student/payments')->assertSessionHas('status');

        // One reference covers both charges; nothing is paid until the Cashier confirms.
        $payments = Payment::all();
        $this->assertCount(2, $payments);
        $this->assertCount(1, $payments->pluck('reference_no')->unique());
        $reference = $payments->first()->reference_no;
        $this->assertStringStartsWith('GC-', $reference);
        $this->assertSame(1150.00, LedgerService::balance($student));
        $this->assertSame('Pending Payment', $docRequest->fresh()->status);

        // The student cannot pay the same charges twice while one payment is being verified.
        $this->actingAs($student->user)->post('/student/payments', ['method' => 'Maya'])->assertSessionHasErrors('method');
        $this->assertSame(2, Payment::count());

        $this->actingAs($cashier)->get('/cashier/proofs')->assertOk()->assertSee($reference);
        $this->actingAs($cashier)->put("/cashier/online-payments/{$reference}", ['action' => 'confirm'])->assertSessionHasNoErrors();

        $this->assertSame(0.00, LedgerService::balance($student));
        $this->assertSame('Paid', $docRequest->fresh()->status);
        $cashierItem = ClearanceItem::where('office_id', Office::where('code', 'CASH')->value('id'))->first();
        $this->assertSame('Cleared', $cashierItem->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $student->user_id, 'title' => 'Payment verified']);

        // A decided payment cannot be decided again.
        $this->actingAs($cashier)->put("/cashier/online-payments/{$reference}", ['action' => 'reject'])->assertSessionHasErrors('action');
    }

    public function test_rejected_online_payment_can_be_paid_again(): void
    {
        $student = $this->makeStudent();
        Assessment::create(['student_id' => $student->id, 'term_id' => $this->term->id, 'fee_type' => 'Tuition', 'description' => 'Tuition', 'amount' => 500]);

        $this->actingAs($student->user)->post('/student/payments', ['method' => 'Online Banking']);
        $reference = Payment::first()->reference_no;
        $this->assertStringStartsWith('OB-', $reference);

        $this->actingAs($this->makeUser('Cashier'))->put("/cashier/online-payments/{$reference}", ['action' => 'reject'])->assertSessionHasNoErrors();
        $this->assertSame('Voided', Payment::first()->status);
        $this->assertSame(500.00, LedgerService::balance($student));

        $this->actingAs($student->user)->post('/student/payments', ['method' => 'Maya'])->assertSessionHasNoErrors();
        $this->assertSame(1, Payment::where('status', 'For Verification')->where('method', 'Maya')->count());
    }

    public function test_only_offered_payment_methods_are_accepted(): void
    {
        $student = $this->makeStudent();
        Assessment::create(['student_id' => $student->id, 'term_id' => $this->term->id, 'fee_type' => 'Tuition', 'description' => 'Tuition', 'amount' => 500]);

        $this->actingAs($student->user)->post('/student/payments', ['method' => 'Cash'])->assertSessionHasErrors('method');
        $this->assertSame(0, Payment::count());
    }

    // ----- Certified e-copy -----

    public function test_certified_e_copy_is_uploaded_by_the_registrar_and_downloaded_by_the_owner(): void
    {
        Storage::fake('local');
        $student = $this->makeStudent();
        $registrar = $this->makeUser('Registrar');

        $this->actingAs($student->user)->post('/student/requests', [
            'document_type_id' => DocumentType::first()->id, 'copies' => 1, 'purpose' => 'Employment', 'release_method' => 'E-copy',
        ])->assertRedirect('/student/payments');
        $docRequest = $student->documentRequests()->firstOrFail();
        $this->assertSame('E-copy', $docRequest->release_method);

        LedgerService::postPayment($docRequest->assessment, $docRequest->total_fee, 'OR-9001', 'Cash', $this->makeUser('Cashier'));
        $url = "/registrar/requests/{$docRequest->reference_no}";
        $this->actingAs($registrar)->put($url, ['action' => 'process'])->assertSessionHasNoErrors();

        // Nothing to download yet, and the Registrar must attach a real PDF.
        $this->actingAs($student->user)->get("/student/requests/{$docRequest->reference_no}/ecopy")->assertNotFound();
        $this->actingAs($registrar)->put($url, ['action' => 'ready'])->assertSessionHasErrors('ecopy');

        $path = tempnam(sys_get_temp_dir(), 'ssis');
        file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF");
        $this->actingAs($registrar)->put($url, ['action' => 'ready', 'ecopy' => new UploadedFile($path, 'tor.pdf', 'application/pdf', null, true)])->assertSessionHasNoErrors();
        $this->assertSame('Ready for Pickup', $docRequest->fresh()->status);

        // Only the owner can download; the first download releases the request.
        $this->actingAs($this->makeStudent()->user)->get("/student/requests/{$docRequest->reference_no}/ecopy")->assertNotFound();
        $this->actingAs($student->user)->get("/student/requests/{$docRequest->reference_no}/ecopy")->assertOk()->assertDownload("{$docRequest->reference_no}.pdf");
        $this->assertSame('Released', $docRequest->fresh()->status);
    }

    // ----- Clearance slip -----

    public function test_clearance_slip_opens_only_when_every_office_has_cleared(): void
    {
        $student = $this->makeStudent();
        $clearance = ClearanceService::ensureFor($student, $this->term);

        $this->actingAs($student->user)->get('/student/clearance/slip')->assertForbidden();

        $registrar = $this->makeUser('Registrar');
        foreach ($clearance->items()->with('office')->get()->sortBy('office.sequence_no') as $item) {
            ClearanceService::sign($item, 'Cleared', null, $registrar);
        }

        $this->actingAs($student->user)->get('/student/clearance/slip')->assertOk()->assertSee('Cleared for enrollment.');
        $this->actingAs($student->user)->get('/dashboard')->assertOk()->assertSee('Download clearance slip');
    }

    public function test_overview_points_the_student_to_what_blocks_enrollment(): void
    {
        $student = $this->makeStudent();
        Assessment::create(['student_id' => $student->id, 'term_id' => $this->term->id, 'fee_type' => 'Tuition', 'description' => 'Tuition', 'amount' => 2450]);

        $this->actingAs($student->user)->get('/dashboard')->assertOk()
            ->assertSee('Settle your balance to clear the Cashier.')->assertSee('2,450.00')->assertSee('Pay online now');

        $library = ClearanceItem::where('office_id', Office::where('code', 'LIB')->value('id'))->firstOrFail();
        ClearanceService::sign($library, 'On Hold', 'Unreturned book', $this->signatory('LIB'));

        $this->actingAs($student->user)->get('/dashboard')->assertOk()->assertSee('Settle your deficiency at the Library.');
    }

    // ----- Top bar, cash, live updates -----

    public function test_top_bar_shows_the_university_and_the_student_id(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student->user)->get('/dashboard')->assertOk()
            ->assertSee('Pamantasan ng Cabuyao')->assertSee('Student ID')->assertSee($student->student_no);
        $this->actingAs($this->makeUser('Cashier'))->get('/dashboard')->assertOk()->assertSee('User ID');
    }

    public function test_student_can_get_a_cash_payment_slip_but_cash_is_posted_only_by_the_cashier(): void
    {
        $student = $this->makeStudent();
        $charge = Assessment::create(['student_id' => $student->id, 'term_id' => $this->term->id, 'fee_type' => 'Tuition', 'description' => 'Tuition', 'amount' => 800]);

        $this->actingAs($student->user)->get('/student/payments')->assertOk()->assertSee('Pay at the Cashier window on campus');
        $this->actingAs($student->user)->get('/student/payments/checkout?method=Cash')->assertOk()
            ->assertSee('Cash Payment Slip')->assertSee($student->student_no)->assertSee('800.00');

        // The slip records nothing; the balance changes when the Cashier posts the cash.
        $this->assertSame(0, Payment::count());
        $this->actingAs($this->makeUser('Cashier'))->post("/cashier/students/{$student->id}/payments", [
            'assessment_id' => $charge->id, 'amount' => 800, 'or_number' => 'OR-7001', 'method' => 'Cash',
        ])->assertSessionHasNoErrors();
        $this->assertSame(0.00, LedgerService::balance($student));
    }

    public function test_live_signature_changes_when_a_payment_or_a_subject_is_added(): void
    {
        $student = $this->makeStudent();
        $faculty = $this->makeUser('Faculty', ['department_id' => $this->ccs()]);
        $section = $this->makeSection($faculty);
        $charge = Assessment::create(['student_id' => $student->id, 'term_id' => $this->term->id, 'fee_type' => 'Tuition', 'description' => 'Tuition', 'amount' => 800]);
        $signature = fn () => $this->actingAs($student->user)->getJson('/live')->assertOk()->json('signature');

        $start = $signature();
        $this->assertSame($start, $signature(), 'Nothing changed, so the signature is stable.');

        // Registrar adds a subject: the student sees it on the overview straight away.
        $this->actingAs($this->makeUser('Registrar'))->post("/registrar/students/{$student->id}/enroll", ['section_id' => $section->id])->assertSessionHasNoErrors();
        $afterSubject = $signature();
        $this->assertNotSame($start, $afterSubject);
        $this->actingAs($student->user)->get('/dashboard')->assertSee('Subjects this term')->assertSee($section->subject->title);

        // Cashier posts a payment.
        LedgerService::postPayment($charge, 300, 'OR-7002', 'Cash', $this->makeUser('Cashier'));
        $this->assertNotSame($afterSubject, $signature());
    }

    public function test_live_signature_needs_a_signed_in_user(): void
    {
        $this->getJson('/live')->assertUnauthorized();
    }

    public function test_forgot_password_link_is_on_the_sign_in_page(): void
    {
        $this->get('/login')->assertOk()->assertSee('Forgot password?')->assertSee(route('password.request'));
    }
}
