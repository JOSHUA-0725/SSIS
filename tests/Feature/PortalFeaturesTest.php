<?php

namespace Tests\Feature;

use App\Http\Controllers\AuthController;
use App\Models\Assessment;
use App\Models\ClearanceItem;
use App\Models\DocumentType;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\Office;
use App\Models\Payment;
use App\Models\QueueTicket;
use App\Models\SlotRequest;
use App\Models\Student;
use App\Services\ClearanceService;
use App\Services\DocumentRequestService;
use App\Services\EnrollmentQueueService;
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
        $this->get('/login')->assertOk()->assertSee('Choose your portal')->assertSee('Student Services Information System')->assertDontSee('Pamantasan ng Cabuyao');
        foreach (AuthController::PORTALS as $key => $portal) {
            $this->get('/login')->assertSee(route('login.portal', $key));
            $this->get("/login/{$key}")->assertOk()->assertSee($portal['module'])->assertSee($portal['field']);
        }
        $this->get('/login/nobody')->assertNotFound();
        $this->get('/forgot-password')->assertOk();
        $this->get('/documentation')->assertOk()->assertSee('SDLC plan');
    }

    // ----- Enrollment queue -----

    /** An irregular student, who enrolls through the queue. */
    private function makeIrregular(): Student
    {
        $student = $this->makeStudent();
        $student->update(['student_type' => 'Irregular']);

        return $student;
    }

    /** Slots already approved for the CCS department this term. */
    private function approveSlots(string $category, int $slots): void
    {
        SlotRequest::create([
            'term_id' => $this->term->id, 'department_id' => $this->ccs(), 'category' => $category, 'slots' => $slots,
            'status' => 'Approved', 'requested_by' => $this->makeUser('Faculty', ['department_id' => $this->ccs()])->id,
        ]);
    }

    public function test_students_line_up_and_the_registrar_calls_them_in_order(): void
    {
        $first = $this->makeIrregular();
        $second = $this->makeIrregular();
        $registrar = $this->makeUser('Registrar');
        $this->approveSlots('Irregular', 2);

        $this->actingAs($first->user)->get('/student/queue')->assertOk()->assertSee('Get a queue number');
        $this->actingAs($first->user)->post('/student/queue')->assertRedirect('/student/queue')->assertSessionHas('status');
        $this->actingAs($second->user)->post('/student/queue')->assertRedirect('/student/queue');

        // One number at a time.
        $this->actingAs($first->user)->post('/student/queue')->assertSessionHasErrors('queue');
        $this->assertSame(2, QueueTicket::count());
        $this->actingAs($second->user)->get('/student/queue')->assertOk()->assertSee('E-002')->assertSee('1 student is ahead of you');

        $this->actingAs($registrar)->get('/registrar/queue')->assertOk()->assertSee('E-001')->assertSee('E-002');
        $this->actingAs($registrar)->post('/registrar/queue/next')->assertSessionHasNoErrors();
        $this->assertSame('Serving', EnrollmentQueueService::openTicket($first)->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $first->user_id, 'title' => 'It is your turn']);
        $this->assertDatabaseHas('notifications', ['user_id' => $second->user_id, 'title' => 'You are next in line']);
        $this->actingAs($first->user)->get('/student/queue')->assertOk()->assertSee('It is your turn');

        // A number that is being served can no longer be cancelled by the student.
        $this->actingAs($first->user)->post('/student/queue/leave')->assertSessionHasErrors('queue');

        // Calling the next number closes the one at the window.
        $this->actingAs($registrar)->post('/registrar/queue/next')->assertSessionHasNoErrors();
        $this->assertNull(EnrollmentQueueService::openTicket($first));
        $this->assertSame('Done', QueueTicket::where('student_id', $first->id)->value('status'));

        $ticket = EnrollmentQueueService::openTicket($second);
        $this->actingAs($registrar)->put("/registrar/queue/{$ticket->id}", ['status' => 'Skipped'])->assertSessionHasNoErrors();
        $this->assertSame('Skipped', $ticket->fresh()->status);
        $this->actingAs($registrar)->post('/registrar/queue/next')->assertSessionHasErrors('queue');

        // A skipped student may line up again and gets the next number.
        $this->actingAs($second->user)->post('/student/queue')->assertRedirect('/student/queue');
        $this->assertSame(3, EnrollmentQueueService::openTicket($second)->number);
    }

    public function test_student_can_cancel_a_waiting_number_and_staff_pages_stay_closed(): void
    {
        $student = $this->makeIrregular();
        $this->approveSlots('Irregular', 1);

        $this->actingAs($student->user)->post('/student/queue');
        $this->actingAs($student->user)->post('/student/queue/leave')->assertRedirect('/student/queue');
        $this->assertSame('Cancelled', QueueTicket::first()->status);
        $this->actingAs($student->user)->post('/student/queue/leave')->assertNotFound();

        $this->actingAs($student->user)->get('/registrar/queue')->assertForbidden();
        $this->actingAs($student->user)->post('/registrar/queue/next')->assertForbidden();
        $this->actingAs($this->makeUser('Cashier'))->get('/student/queue')->assertForbidden();
    }

    public function test_faculty_asks_for_slots_and_the_registrar_approval_limits_the_queue(): void
    {
        $faculty = $this->makeUser('Faculty', ['department_id' => $this->ccs()]);
        $registrar = $this->makeUser('Registrar');
        $freshmen = [$this->makeStudent(), $this->makeStudent()];
        foreach ($freshmen as $student) {
            $student->update(['year_level' => 1]);
        }

        // Nothing is approved yet, so no first-year student can line up.
        $this->actingAs($freshmen[0]->user)->post('/student/queue')->assertSessionHasErrors('queue');

        $this->actingAs($faculty)->get('/faculty/enrollment')->assertOk()->assertSee('Ask the Registrar for slots');
        $this->actingAs($faculty)->post('/faculty/enrollment/slots', ['category' => 'First Year', 'slots' => 1])->assertSessionHasNoErrors();
        // One request per kind of student waits at a time.
        $this->actingAs($faculty)->post('/faculty/enrollment/slots', ['category' => 'First Year', 'slots' => 5])->assertSessionHasErrors('slots');
        $slotRequest = SlotRequest::sole();
        $this->assertDatabaseHas('notifications', ['user_id' => $registrar->id, 'title' => 'Enrollment slots requested']);

        // A pending request opens no slots.
        $this->actingAs($freshmen[0]->user)->post('/student/queue')->assertSessionHasErrors('queue');

        $this->actingAs($registrar)->get('/registrar/slot-requests')->assertOk()->assertSee('First Year');
        $this->actingAs($registrar)->put("/registrar/slot-requests/{$slotRequest->id}", ['status' => 'Approved'])->assertSessionHasNoErrors();
        $this->assertSame('Approved', $slotRequest->fresh()->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $faculty->id, 'title' => 'Slot request Approved']);
        $this->actingAs($registrar)->put("/registrar/slot-requests/{$slotRequest->id}", ['status' => 'Rejected'])->assertSessionHasErrors('status');

        // One slot: the first student gets it, the second is turned away.
        $this->actingAs($freshmen[0]->user)->post('/student/queue')->assertRedirect('/student/queue');
        $this->actingAs($freshmen[1]->user)->post('/student/queue')->assertSessionHasErrors('queue');
        $this->assertSame(1, QueueTicket::count());

        $this->actingAs($freshmen[0]->user)->get('/faculty/enrollment')->assertForbidden();
        $this->actingAs($faculty)->get('/registrar/slot-requests')->assertForbidden();
    }

    public function test_regular_students_enroll_by_block_once_the_faculty_opens_enrollment(): void
    {
        $faculty = $this->makeUser('Faculty', ['department_id' => $this->ccs()]);
        $student = $this->makeStudent();
        $student->update(['block_section' => 'BSIT-3A']);
        $this->makeSection($faculty);
        $this->makeSection($faculty);

        // Regular students do not use the queue.
        $this->actingAs($student->user)->post('/student/queue')->assertSessionHasErrors('queue');

        $this->actingAs($student->user)->get('/student/queue')->assertOk()->assertSee('has not opened enrollment yet');
        $this->actingAs($student->user)->post('/student/enroll')->assertSessionHasErrors('queue');
        $this->assertSame(0, Enrollment::count());

        $this->actingAs($faculty)->put('/faculty/enrollment/block', ['open' => 1])->assertSessionHasNoErrors();
        $this->actingAs($student->user)->get('/student/queue')->assertOk()->assertSee('Enroll in my block');
        $this->actingAs($student->user)->post('/student/enroll')->assertRedirect('/student/queue')->assertSessionHas('status');
        $this->assertSame(2, Enrollment::where('student_id', $student->id)->count());
        $this->actingAs($student->user)->get('/student/queue')->assertOk()->assertSee('You are enrolled in every subject of your block');

        // Closing enrollment stops the next regular student; an irregular one never enrolls by block.
        $this->actingAs($faculty)->put('/faculty/enrollment/block', ['open' => 0])->assertSessionHasNoErrors();
        $late = $this->makeStudent();
        $late->update(['block_section' => 'BSIT-3A']);
        $this->actingAs($late->user)->post('/student/enroll')->assertSessionHasErrors('queue');
        $this->actingAs($faculty)->put('/faculty/enrollment/block', ['open' => 1]);
        $this->actingAs($this->makeIrregular()->user)->post('/student/enroll')->assertSessionHasErrors('queue');
        $this->assertSame(2, Enrollment::count());
    }

    // ----- Payments already sent online -----

    /** A payment sent before online payment was removed, still waiting for the Cashier. */
    private function pendingOnlinePayment(Assessment ...$charges): string
    {
        foreach ($charges as $charge) {
            Payment::create([
                'assessment_id' => $charge->id, 'reference_no' => 'GC-10000001', 'amount' => $charge->amount,
                'method' => 'GCash', 'status' => 'For Verification', 'paid_at' => now(),
            ]);
        }

        return 'GC-10000001';
    }

    public function test_pending_online_payment_is_verified_by_the_cashier_then_clears_the_student(): void
    {
        $student = $this->makeStudent();
        $cashier = $this->makeUser('Cashier');
        $tuition = Assessment::create(['student_id' => $student->id, 'term_id' => $this->term->id, 'fee_type' => 'Tuition', 'description' => 'Tuition', 'amount' => 1000]);
        ClearanceService::ensureFor($student, $this->term);
        $docRequest = DocumentRequestService::file($student, DocumentType::where('name', 'Transcript of Records')->first(), 1, 'Employment');
        $reference = $this->pendingOnlinePayment($tuition, $docRequest->assessment);

        // Nothing is paid until the Cashier confirms.
        $this->actingAs($student->user)->get('/student/payments')->assertOk()->assertSee('1,150.00');
        $this->assertSame(1150.00, LedgerService::balance($student));
        $this->assertSame('Pending Payment', $docRequest->fresh()->status);

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

    public function test_rejected_online_payment_leaves_the_balance_unpaid(): void
    {
        $student = $this->makeStudent();
        $reference = $this->pendingOnlinePayment(Assessment::create(['student_id' => $student->id, 'term_id' => $this->term->id, 'fee_type' => 'Tuition', 'description' => 'Tuition', 'amount' => 500]));

        $this->actingAs($this->makeUser('Cashier'))->put("/cashier/online-payments/{$reference}", ['action' => 'reject'])->assertSessionHasNoErrors();
        $this->assertSame('Voided', Payment::first()->status);
        $this->assertSame(500.00, LedgerService::balance($student));
    }

    public function test_students_can_no_longer_pay_online(): void
    {
        $student = $this->makeStudent();
        Assessment::create(['student_id' => $student->id, 'term_id' => $this->term->id, 'fee_type' => 'Tuition', 'description' => 'Tuition', 'amount' => 500]);

        $this->actingAs($student->user)->get('/student/payments')->assertOk()->assertDontSee('GCash')->assertDontSee('Maya')->assertDontSee('Online banking');
        $this->actingAs($student->user)->post('/student/payments', ['method' => 'GCash'])->assertStatus(405);
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
            ->assertSee('Settle your balance to clear the Cashier.')->assertSee('2,450.00')->assertSee('See what to pay');

        $library = ClearanceItem::where('office_id', Office::where('code', 'LIB')->value('id'))->firstOrFail();
        ClearanceService::sign($library, 'On Hold', 'Unreturned book', $this->signatory('LIB'));

        $this->actingAs($student->user)->get('/dashboard')->assertOk()->assertSee('Settle your deficiency at the Library.');
    }

    // ----- Top bar, cash, live updates -----

    public function test_top_bar_shows_the_university_and_the_student_id(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student->user)->get('/dashboard')->assertOk()
            ->assertSee('Student Services Information System')->assertDontSee('Pamantasan ng Cabuyao')->assertSee('Student ID')->assertSee($student->student_no);
        $this->actingAs($this->makeUser('Cashier'))->get('/dashboard')->assertOk()->assertSee('User ID');
    }

    public function test_student_can_get_a_cash_payment_slip_but_cash_is_posted_only_by_the_cashier(): void
    {
        $student = $this->makeStudent();
        $charge = Assessment::create(['student_id' => $student->id, 'term_id' => $this->term->id, 'fee_type' => 'Tuition', 'description' => 'Tuition', 'amount' => 800]);

        $this->actingAs($student->user)->get('/student/payments')->assertOk()->assertSee('Pay in cash at the Cashier window on campus')->assertSee(route('student.payments.slip'));
        $this->actingAs($student->user)->get('/student/payments/slip')->assertOk()
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
        $this->get('/login/student')->assertOk()->assertSee('Forgot password?')->assertSee(route('password.request'));
    }
}
