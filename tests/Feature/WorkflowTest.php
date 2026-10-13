<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\ClearanceItem;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\Grade;
use App\Models\Office;
use App\Models\Payment;
use App\Models\Student;
use App\Services\ClearanceService;
use App\Services\DocumentRequestService;
use App\Services\LedgerService;

/** Integration and system tests: data written by one module is read correctly by another. */
class WorkflowTest extends SsisTestCase
{
    // ----- Document Request System, Cashier, Registrar -----

    public function test_document_request_from_filing_to_release(): void
    {
        $student = $this->makeStudent();
        $cashier = $this->makeUser('Cashier');
        $registrar = $this->makeUser('Registrar');
        $tor = DocumentType::where('name', 'Transcript of Records')->first();

        // Student files: the fee is computed by the system.
        $this->actingAs($student->user)
            ->post('/student/requests', ['document_type_id' => $tor->id, 'copies' => 2, 'purpose' => 'Employment'])
            ->assertSessionHasNoErrors();
        $docRequest = DocumentRequest::firstOrFail();
        $this->assertSame('Pending Payment', $docRequest->status);
        $this->assertSame(300.00, $docRequest->total_fee);

        // The same amount appears as a payable for the Cashier.
        $assessment = Assessment::where('document_request_id', $docRequest->id)->firstOrFail();
        $this->assertSame(300.00, $assessment->amount);
        $this->actingAs($cashier)->get('/cashier/payments')->assertOk()->assertSee($docRequest->reference_no);

        // Cashier posts the payment: the request becomes Paid and the student is notified.
        $this->actingAs($cashier)->post("/cashier/students/{$student->id}/payments", [
            'assessment_id' => $assessment->id, 'amount' => 300, 'or_number' => 'OR-1001', 'method' => 'Cash',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Paid', $docRequest->fresh()->status);
        $this->assertDatabaseHas('request_status_history', ['document_request_id' => $docRequest->id, 'status' => 'Paid']);
        $this->assertDatabaseHas('notifications', ['user_id' => $student->user_id, 'title' => 'Document request Paid']);

        // Registrar processes, schedules and releases.
        $url = "/registrar/requests/{$docRequest->reference_no}";
        $this->actingAs($registrar)->get('/registrar/requests?status=Paid')->assertSee($docRequest->reference_no);
        $this->actingAs($registrar)->put($url, ['action' => 'process'])->assertSessionHasNoErrors();
        $this->actingAs($registrar)->put($url, ['action' => 'ready', 'claim_date' => today()->addDays(3)->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame('Ready for Pickup', $docRequest->fresh()->status);

        $this->actingAs($student->user)->get("/student/requests/{$docRequest->reference_no}/stub")->assertOk()->assertSee($docRequest->reference_no);

        $this->actingAs($registrar)->put($url, ['action' => 'release'])->assertSessionHasNoErrors();
        $docRequest->refresh();
        $this->assertSame('Released', $docRequest->status);
        $this->assertSame($registrar->id, $docRequest->released_by);
        $this->assertSame(
            ['Pending Payment', 'Paid', 'Processing', 'Ready for Pickup', 'Released'],
            $docRequest->history->pluck('status')->all()
        );
    }

    public function test_registrar_cannot_process_an_unpaid_request(): void
    {
        $docRequest = DocumentRequestService::file($this->makeStudent(), DocumentType::first(), 1, 'Employment');

        $this->actingAs($this->makeUser('Registrar'))
            ->put("/registrar/requests/{$docRequest->reference_no}", ['action' => 'process'])
            ->assertSessionHasErrors('action');

        $this->assertSame('Pending Payment', $docRequest->fresh()->status);
    }

    public function test_rejected_request_shows_the_reason_to_the_student(): void
    {
        $student = $this->makeStudent();
        $docRequest = DocumentRequestService::file($student, DocumentType::first(), 1, 'Employment');
        LedgerService::postPayment($docRequest->assessment, $docRequest->total_fee, 'OR-1002', 'Cash', $this->makeUser('Cashier'));

        $this->actingAs($this->makeUser('Registrar'))
            ->put("/registrar/requests/{$docRequest->reference_no}", ['action' => 'reject', 'reason' => 'Incomplete academic records'])
            ->assertSessionHasNoErrors();

        $this->actingAs($student->user)->get("/student/requests/{$docRequest->reference_no}")
            ->assertSee('rejected')->assertSee('Incomplete academic records');
    }

    public function test_or_number_cannot_be_used_twice_and_overpayment_is_refused(): void
    {
        $student = $this->makeStudent();
        $cashier = $this->makeUser('Cashier');
        $charge = Assessment::create(['student_id' => $student->id, 'term_id' => $this->term->id, 'fee_type' => 'Tuition', 'description' => 'Tuition', 'amount' => 1000]);
        $url = "/cashier/students/{$student->id}/payments";

        $this->actingAs($cashier)->post($url, ['assessment_id' => $charge->id, 'amount' => 400, 'or_number' => 'OR-2001', 'method' => 'Cash'])->assertSessionHasNoErrors();
        $this->actingAs($cashier)->post($url, ['assessment_id' => $charge->id, 'amount' => 100, 'or_number' => 'OR-2001', 'method' => 'Cash'])->assertSessionHasErrors('or_number');
        $this->actingAs($cashier)->post($url, ['assessment_id' => $charge->id, 'amount' => 700, 'or_number' => 'OR-2002', 'method' => 'Cash'])->assertSessionHasErrors('amount');

        $this->assertSame(1, Payment::count());
        $this->assertSame(600.00, LedgerService::balance($student));
    }

    public function test_voided_and_unverified_payments_do_not_reduce_the_balance(): void
    {
        $student = $this->makeStudent();
        $charge = Assessment::create(['student_id' => $student->id, 'term_id' => $this->term->id, 'fee_type' => 'Tuition', 'description' => 'Tuition', 'amount' => 15000]);
        LedgerService::postPayment($charge, 9500, 'OR-3001', 'Cash', $this->makeUser('Cashier'));
        Payment::create(['assessment_id' => $charge->id, 'amount' => 1000, 'method' => 'Cash', 'status' => 'Voided', 'paid_at' => now()]);
        Payment::create(['assessment_id' => $charge->id, 'amount' => 1000, 'method' => 'E-wallet', 'status' => 'For Verification', 'paid_at' => now()]);

        $this->assertSame(5500.00, LedgerService::balance($student));
    }

    // ----- Department, Registrar, Student Portal: grades -----

    public function test_grades_from_encoding_to_student_view(): void
    {
        $student = $this->makeStudent();
        $faculty = $this->makeUser('Professor', ['department_id' => $this->ccs()]);
        $head = $this->makeUser('Department Head', ['department_id' => $this->ccs()]);
        $registrar = $this->makeUser('Registrar');
        $section = $this->makeSection($faculty, $student);
        $enrollmentId = $section->enrollments->first()->id;
        $url = "/faculty/classes/{$section->id}";

        // A grade outside 1.00 to 5.00 is rejected.
        $this->actingAs($faculty)->put($url, ['action' => 'save', 'grades' => [$enrollmentId => ['prelim' => 1.75, 'midterm' => 6.00, 'final' => 1.50]]])
            ->assertSessionHasErrors("grades.{$enrollmentId}.midterm");
        $this->assertSame(0, Grade::count());

        // A sheet with a missing grade cannot be submitted.
        $this->actingAs($faculty)->put($url, ['action' => 'submit', 'grades' => [$enrollmentId => ['prelim' => 1.75, 'midterm' => 2.00, 'final' => null]]])
            ->assertSessionHasErrors('grades');

        $this->actingAs($faculty)->put($url, ['action' => 'submit', 'grades' => [$enrollmentId => ['prelim' => 1.75, 'midterm' => 2.00, 'final' => 1.50]]])
            ->assertSessionHasNoErrors();
        $this->assertSame('Submitted', $section->fresh()->sheet_status);
        $this->assertSame(1.75, Grade::first()->final_rating);
        $this->assertSame('Passed', Grade::first()->remarks);

        // Department Head approves: the sheet is locked for the faculty member.
        $this->actingAs($head)->put("/department/sheets/{$section->id}", ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('Approved', $section->fresh()->sheet_status);
        $this->actingAs($faculty)->put($url, ['action' => 'save', 'grades' => [$enrollmentId => ['prelim' => 1.00, 'midterm' => 1.00, 'final' => 1.00]]])
            ->assertSessionHasErrors('grades');
        $this->assertSame(1.75, Grade::first()->final_rating);

        // The student sees nothing until the Registrar releases the term.
        $this->actingAs($student->user)->get('/student/grades')->assertOk()->assertSee('have not been released')->assertDontSee('1.75');

        $this->actingAs($registrar)->put("/registrar/terms/{$this->term->id}", ['action' => 'release'])->assertSessionHasNoErrors();
        $this->actingAs($student->user)->get('/student/grades')->assertOk()->assertSee($section->subject->code)->assertSee('General weighted average')->assertSee('1.75');

        // The grade change is in the audit log with the acting user.
        $this->assertDatabaseHas('audit_logs', ['action' => 'CREATE', 'table_name' => 'grades', 'user_id' => $faculty->id]);
    }

    public function test_faculty_assigns_a_subject_to_a_professor_who_then_encodes_its_grades(): void
    {
        $faculty = $this->makeUser('Faculty', ['department_id' => $this->ccs()]);
        $professor = $this->makeUser('Professor', ['department_id' => $this->ccs()]);
        $outsider = $this->makeUser('Professor', ['department_id' => \App\Models\Department::where('code', 'CBA')->value('id')]);
        $student = $this->makeStudent();
        $section = $this->makeSection($faculty, $student);

        $this->actingAs($faculty)->get('/faculty/loads')->assertOk()->assertSee($section->subject->code)->assertSee($professor->name);
        // Only an instructor of the same department can be chosen.
        $this->actingAs($faculty)->put("/faculty/loads/{$section->id}", ['faculty_id' => $outsider->id])->assertSessionHasErrors('faculty_id');
        $this->actingAs($faculty)->put("/faculty/loads/{$section->id}", ['faculty_id' => $professor->id])->assertSessionHasNoErrors();
        $this->assertSame($professor->id, $section->fresh()->faculty_id);
        $this->assertDatabaseHas('notifications', ['user_id' => $professor->id, 'title' => 'Subject assigned to you']);

        // Faculty also chooses the year level and the section of the class; the grade sheet status is not shown here.
        $this->actingAs($faculty)->get('/faculty/loads')->assertSee('2nd Year')->assertSee('BSIT B')->assertDontSee('Grade sheet');
        $this->actingAs($faculty)->put("/faculty/loads/{$section->id}", ['faculty_id' => $professor->id, 'year_level' => 2, 'section' => 'XX-Z'])->assertSessionHasErrors('section');
        $this->actingAs($faculty)->put("/faculty/loads/{$section->id}", ['faculty_id' => $professor->id, 'year_level' => 2, 'section' => 'BSIT-B'])->assertSessionHasNoErrors();
        $this->assertSame('BSIT-2B', $section->fresh()->section_code);

        // The subject moves to the professor, who encodes and submits the grades.
        $this->actingAs($professor)->get('/dashboard')->assertOk()->assertSee('My classes this term');
        $this->actingAs($professor)->get('/faculty/classes')->assertOk()->assertSee($section->subject->code);
        $this->actingAs($professor)->get("/faculty/classes/{$section->id}")->assertOk();
        $this->actingAs($faculty)->get("/faculty/classes/{$section->id}")->assertForbidden();
        $this->actingAs($outsider)->get("/faculty/classes/{$section->id}")->assertForbidden();

        // Faculty does not teach: it has no class list and cannot be chosen as the professor of a subject.
        $this->actingAs($faculty)->get('/faculty/classes')->assertForbidden();
        $this->actingAs($faculty)->put("/faculty/loads/{$section->id}", ['faculty_id' => $faculty->id])->assertSessionHasErrors('faculty_id');

        // Professors teach; they do not run the department's enrollment or loads.
        $this->actingAs($professor)->get('/faculty/loads')->assertForbidden();
        $this->actingAs($professor)->get('/faculty/enrollment')->assertForbidden();
        $this->actingAs($professor)->put("/faculty/loads/{$section->id}", ['faculty_id' => $professor->id])->assertForbidden();

        // A submitted grade sheet keeps its professor.
        $section->update(['sheet_status' => 'Submitted']);
        $this->actingAs($faculty)->put("/faculty/loads/{$section->id}", ['faculty_id' => $faculty->id])->assertSessionHasErrors('faculty_id');
        $this->assertSame($professor->id, $section->fresh()->faculty_id);

        $this->post('/logout');
        $this->post('/login', ['portal' => 'department', 'username' => $professor->username, 'password' => self::PASSWORD])->assertRedirect('/dashboard');
    }

    public function test_faculty_cannot_encode_when_the_period_is_closed(): void
    {
        $faculty = $this->makeUser('Professor', ['department_id' => $this->ccs()]);
        $section = $this->makeSection($faculty, $this->makeStudent());
        $this->term->update(['encoding_open' => false]);

        $this->actingAs($faculty)->put("/faculty/classes/{$section->id}", [
            'action' => 'save', 'grades' => [$section->enrollments->first()->id => ['prelim' => 2.00, 'midterm' => 2.00, 'final' => 2.00]],
        ])->assertSessionHasErrors('grades');

        $this->assertSame(0, Grade::count());
    }

    public function test_returned_sheet_can_be_corrected_and_resubmitted(): void
    {
        $faculty = $this->makeUser('Professor', ['department_id' => $this->ccs()]);
        $head = $this->makeUser('Department Head', ['department_id' => $this->ccs()]);
        $section = $this->makeSection($faculty, $this->makeStudent());
        $grades = [$section->enrollments->first()->id => ['prelim' => 2.00, 'midterm' => 2.00, 'final' => 2.00]];

        $this->actingAs($faculty)->put("/faculty/classes/{$section->id}", ['action' => 'submit', 'grades' => $grades]);
        $this->actingAs($head)->put("/department/sheets/{$section->id}", ['action' => 'return'])->assertSessionHasErrors('remarks');
        $this->actingAs($head)->put("/department/sheets/{$section->id}", ['action' => 'return', 'remarks' => 'Check the final grade'])->assertSessionHasNoErrors();
        $this->assertSame('Returned', $section->fresh()->sheet_status);

        $this->actingAs($faculty)->put("/faculty/classes/{$section->id}", ['action' => 'submit', 'grades' => $grades])->assertSessionHasNoErrors();
        $this->assertSame('Submitted', $section->fresh()->sheet_status);
    }

    // ----- Clearance -----

    private function item(Student $student, string $officeCode): ClearanceItem
    {
        return ClearanceItem::where('office_id', Office::where('code', $officeCode)->value('id'))
            ->whereHas('clearance', fn ($q) => $q->where('student_id', $student->id))->firstOrFail();
    }

    public function test_full_clearance_ends_with_the_registrar(): void
    {
        $student = $this->makeStudent();
        $registrar = $this->makeUser('Registrar');
        $clearance = ClearanceService::ensureFor($student, $this->term);

        // With no school fees charged, the Cashier item clears itself.
        $this->assertSame('Cleared', $this->item($student, 'CASH')->status);

        // The Registrar is blocked while any office is pending.
        $this->actingAs($registrar)->put('/clearance/'.$this->item($student, 'REG')->id, ['status' => 'Cleared'])->assertSessionHasErrors('status');
        $this->assertFalse(ClearanceService::isComplete($clearance));

        foreach (['LIB', 'LAB', 'SAO'] as $code) {
            $this->actingAs($this->signatory($code))->put('/clearance/'.$this->item($student, $code)->id, ['status' => 'Cleared'])->assertSessionHasNoErrors();
        }
        $this->actingAs($this->makeUser('Department Head', ['department_id' => $this->ccs()]))
            ->put('/clearance/'.$this->item($student, 'DEPT')->id, ['status' => 'Cleared'])->assertSessionHasNoErrors();

        $this->actingAs($registrar)->put('/clearance/'.$this->item($student, 'REG')->id, ['status' => 'Cleared'])->assertSessionHasNoErrors();
        $this->assertSame('Cleared', $clearance->fresh()->overall_status);

        $this->actingAs($student->user)->get('/student/clearance')->assertOk()->assertSee('6 of 6 cleared');
    }

    public function test_hold_needs_a_reason_and_is_shown_to_the_student(): void
    {
        $student = $this->makeStudent();
        ClearanceService::ensureFor($student, $this->term);
        $library = $this->signatory('LIB');
        $url = '/clearance/'.$this->item($student, 'LIB')->id;

        $this->actingAs($library)->put($url, ['status' => 'On Hold'])->assertSessionHasErrors('remarks');
        $this->actingAs($library)->put($url, ['status' => 'On Hold', 'remarks' => 'Unreturned book'])->assertSessionHasNoErrors();

        $this->actingAs($student->user)->get('/student/clearance')->assertSee('Deficiency')->assertSee('Unreturned book');
    }

    public function test_signatory_cannot_sign_for_another_office(): void
    {
        $student = $this->makeStudent();
        ClearanceService::ensureFor($student, $this->term);

        $this->actingAs($this->signatory('LIB'))->put('/clearance/'.$this->item($student, 'LAB')->id, ['status' => 'Cleared'])->assertForbidden();
        $this->assertSame('Pending', $this->item($student, 'LAB')->status);
    }

    public function test_department_head_cannot_sign_for_students_of_another_department(): void
    {
        $student = $this->makeStudent('BSBA');
        ClearanceService::ensureFor($student, $this->term);

        $this->actingAs($this->makeUser('Department Head', ['department_id' => $this->ccs()]))
            ->put('/clearance/'.$this->item($student, 'DEPT')->id, ['status' => 'Cleared'])->assertForbidden();
    }

    public function test_cashier_clearance_is_automatic_when_school_fees_are_paid(): void
    {
        $student = $this->makeStudent();
        $charge = Assessment::create(['student_id' => $student->id, 'term_id' => $this->term->id, 'fee_type' => 'Tuition', 'description' => 'Tuition', 'amount' => 1000]);
        ClearanceService::ensureFor($student, $this->term);
        $this->assertSame('Pending', $this->item($student, 'CASH')->status);

        $cashier = $this->makeUser('Cashier');
        LedgerService::postPayment($charge, 400, 'OR-4001', 'Cash', $cashier);
        $this->assertSame('Pending', $this->item($student, 'CASH')->status);

        LedgerService::postPayment($charge, 600, 'OR-4002', 'Cash', $cashier);
        $this->assertSame('Cleared', $this->item($student, 'CASH')->status);
    }

    // ----- Admin -----

    public function test_admin_creates_a_user_and_the_change_is_audited(): void
    {
        $admin = $this->makeUser('Administrator');

        $this->actingAs($admin)->post('/admin/users', [
            'username' => 'EMP-0142', 'name' => 'New Cashier', 'email' => 'emp0142@college.test',
            'role_id' => \App\Models\Role::where('name', 'Cashier')->value('id'),
        ])->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertDatabaseHas('users', ['username' => 'EMP-0142', 'status' => 'Active']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'CREATE', 'table_name' => 'users', 'user_id' => $admin->id]);
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = $this->makeUser('Administrator');

        $this->actingAs($admin)->put("/admin/users/{$admin->id}", [
            'name' => $admin->name, 'email' => $admin->email, 'role_id' => $admin->role_id, 'status' => 'Inactive',
        ])->assertSessionHasErrors('status');

        $this->assertSame('Active', $admin->fresh()->status);
    }

    public function test_admin_menu_lists_each_kind_of_account_on_its_own_page(): void
    {
        $admin = $this->makeUser('Administrator');
        $cashier = $this->makeUser('Cashier');
        $faculty = $this->makeUser('Faculty', ['department_id' => $this->ccs()]);
        $student = $this->makeStudent();

        $this->actingAs($admin)->get('/admin/users?group=students')->assertOk()
            ->assertSee($student->user->username)->assertDontSee($cashier->username)->assertDontSee($faculty->username);
        $this->get('/admin/users?group=cashier')->assertOk()
            ->assertSee($cashier->username)->assertDontSee($faculty->username)->assertDontSee($student->user->username);
        $this->get('/admin/users?group=faculty')->assertOk()
            ->assertSee($faculty->username)->assertDontSee($cashier->username);
        foreach (['students', 'cashier', 'registrar', 'faculty'] as $group) {
            $this->get('/admin/users')->assertSee(route('admin.users', ['group' => $group]));
        }
    }

    public function test_every_role_can_open_its_own_pages(): void
    {
        $student = $this->makeStudent();
        $faculty = $this->makeUser('Professor', ['department_id' => $this->ccs()]);
        $section = $this->makeSection($faculty, $student);

        $pages = [
            [$student->user, ['/dashboard', '/student/grades', '/student/curriculum', '/student/clearance', '/student/payments', '/student/requests', '/profile', '/notifications']],
            [$this->makeUser('Registrar'), ['/dashboard', '/registrar/students', '/registrar/students/new', "/registrar/students/{$student->id}", '/registrar/terms', '/registrar/requests', '/clearance']],
            [$this->makeUser('Cashier'), ['/dashboard', '/cashier/payments', '/cashier/payments?q=Last', "/cashier/students/{$student->id}", '/cashier/proofs', '/cashier/report', '/cashier/report?export=csv', '/clearance']],
            [$faculty, ['/dashboard', '/faculty/classes', "/faculty/classes/{$section->id}"]],
            [$this->makeUser('Faculty', ['department_id' => $this->ccs()]), ['/dashboard', '/faculty/loads', '/faculty/enrollment']],
            [$this->makeUser('Department Head', ['department_id' => $this->ccs()]), ['/dashboard', '/department/sheets', "/department/sheets/{$section->id}", '/clearance']],
            [$this->signatory('LIB'), ['/dashboard', '/clearance', '/clearance?status=Cleared&q=Last']],
            [$this->makeUser('Administrator'), ['/dashboard', '/admin/users', '/admin/users/new', "/admin/users/{$faculty->id}/edit", "/admin/users/{$student->user_id}/edit", '/admin/document-types', '/admin/audit-log']],
        ];

        foreach ($pages as [$user, $urls]) {
            foreach ($urls as $url) {
                $this->actingAs($user)->get($url)->assertOk();
            }
        }
    }

    public function test_faculty_opens_sections_of_curriculum_subjects_for_available_professors(): void
    {
        $this->seed(\Database\Seeders\CurriculumSeeder::class);
        $faculty = $this->makeUser('Faculty', ['department_id' => $this->ccs()]);
        $professor = $this->makeUser('Professor', ['department_id' => $this->ccs()]);
        $busy = $this->makeUser('Professor', ['department_id' => $this->ccs()]);
        $networking = \App\Models\Subject::where('code', 'IT 314')->firstOrFail();   // third year, first semester
        $capstone = \App\Models\Subject::where('code', 'IT 325')->firstOrFail();     // second semester: not offered now
        $existing = \App\Models\ClassSection::create(['subject_id' => $networking->id, 'term_id' => $this->term->id, 'section_code' => 'BSIT-3A', 'faculty_id' => $busy->id]);

        // The year level comes from the curriculum, each professor shows how many classes they hold, and sections read in full.
        $this->actingAs($faculty)->get('/faculty/loads')->assertOk()
            ->assertSee('3rd Year')->assertSee('BSIT 3A')->assertSee($busy->name.' · 1 class')->assertSee($professor->name.' · 0 classes')
            ->assertSee('Open a section')->assertSee('Networking 2')->assertDontSee('Capstone Project 1');

        // Another section of the same subject for the professor who is free.
        $this->actingAs($faculty)->post('/faculty/loads', ['subject_id' => $networking->id, 'letter' => 'B', 'faculty_id' => $professor->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('class_sections', ['subject_id' => $networking->id, 'term_id' => $this->term->id, 'section_code' => 'BSIT-3B', 'faculty_id' => $professor->id, 'sheet_status' => 'Draft']);
        $this->assertDatabaseHas('notifications', ['user_id' => $professor->id, 'title' => 'Subject assigned to you']);
        $this->actingAs($professor)->get('/faculty/classes')->assertOk()->assertSee('IT 314');

        // The same section twice, a subject of the other semester, and a professor adding one are all refused.
        $this->actingAs($faculty)->post('/faculty/loads', ['subject_id' => $networking->id, 'letter' => 'B', 'faculty_id' => $busy->id])->assertSessionHasErrors('letter');
        $this->actingAs($faculty)->post('/faculty/loads', ['subject_id' => $capstone->id, 'letter' => 'A', 'faculty_id' => $professor->id])->assertSessionHasErrors('subject_id');
        $this->actingAs($professor)->post('/faculty/loads', ['subject_id' => $networking->id, 'letter' => 'C', 'faculty_id' => $professor->id])->assertForbidden();

        // Moving a class to another section keeps the curriculum year, whatever year is sent.
        $this->actingAs($faculty)->put("/faculty/loads/{$existing->id}", ['faculty_id' => $busy->id, 'section' => 'BSIT-C', 'year_level' => 1])->assertSessionHasNoErrors();
        $this->assertSame('BSIT-3C', $existing->fresh()->section_code);
    }
}
