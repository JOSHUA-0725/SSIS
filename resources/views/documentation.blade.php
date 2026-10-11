<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head', ['title' => 'System documentation'])
</head>
<body>
<main class="guest">
    <div class="guest-box wide">
        <p><a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="link-arrow">@include('partials.icon', ['name' => 'arrow-left']) Back</a></p>
        <div class="d-flex align-items-center gap-3 mb-3">
            <img class="brand-logo" src="{{ asset('images/pnc-logo.png') }}" alt="Pamantasan ng Cabuyao seal" width="40" height="40">
            <span class="muted">Pamantasan ng Cabuyao · Student Services Information System</span>
        </div>
        <h1>System documentation</h1>
        <p class="muted mb-4">The Student Services Information System (SSIS) replaces the physical queues for enrollment clearance, grade viewing, and document requests. This page summarises how it was planned, designed, and tested.</p>

        <section class="doc-section">
            <h2>Modules</h2>
            <div class="card"><div class="table-responsive"><table class="table align-middle">
                <thead><tr><th scope="col">Module</th><th scope="col">Used by</th><th scope="col">What it does</th></tr></thead>
                <tbody>
                    <tr><td>Student Portal</td><td>Students</td><td>Clearance tracker, grades with GWA, payments, notifications</td></tr>
                    <tr><td>Document Request System</td><td>Students, Cashier, Registrar</td><td>Online filing, payment, status tracking, claim stub or certified e-copy</td></tr>
                    <tr><td>Registrar Module</td><td>Registrar</td><td>Student records, enrollment, terms and grade release, request processing, final clearance</td></tr>
                    <tr><td>Cashier Module</td><td>Cashier</td><td>Charges, payment posting, online payment verification, ledger, collection report</td></tr>
                    <tr><td>Department Module</td><td>Faculty, Department Heads, clearance offices</td><td>Grade encoding and approval, office clearance</td></tr>
                    <tr><td>Admin Module</td><td>Administrator</td><td>Users and roles, document types and fees, audit log</td></tr>
                </tbody>
            </table></div></div>
        </section>

        <section class="doc-section">
            <h2>SDLC plan</h2>
            <p class="muted">Iterative and incremental: planning, analysis, and design are done once, then development and testing repeat in three two-week increments, each ending in a demo to the offices.</p>
            <div class="card"><div class="table-responsive"><table class="table align-middle">
                <thead><tr><th scope="col">Phase</th><th scope="col">Weeks</th><th scope="col">Deliverables</th></tr></thead>
                <tbody>
                    <tr><td>Planning</td><td>1–2</td><td>Project charter, feasibility report, schedule</td></tr>
                    <tr><td>Requirements analysis</td><td>3–4</td><td>Interview notes, requirements specification, traceability matrix</td></tr>
                    <tr><td>System design</td><td>5–7</td><td>System models, ERD and data dictionary, wireframes, test plan</td></tr>
                    <tr><td>Development</td><td>8–13</td><td>Working modules in three increments, unit test results</td></tr>
                    <tr><td>Testing</td><td>14–16</td><td>Test results, defect log, security test report, acceptance sign-off</td></tr>
                    <tr><td>Deployment and training</td><td>17–18</td><td>Installed system, user manuals, training, go-live</td></tr>
                    <tr><td>Maintenance</td><td>After week 18</td><td>Maintenance log, patch releases, term-end review</td></tr>
                </tbody>
            </table></div></div>
        </section>

        <section class="doc-section">
            <h2>System modeling</h2>
            <div class="panel">
                <p><strong>Enrollment clearance.</strong> When a term opens, the system creates one clearance per enrolled student with an item for each office. The Library, Laboratory, Student Affairs, Department, and Cashier sign in parallel. The Cashier item clears by itself when school fees reach zero. The Registrar gives the final clearance only after every other office has cleared, and a withdrawn sign-off withdraws the final clearance too.</p>
                <p><strong>Document request.</strong> A student files a request and the system computes the fee. The request moves through Requested, Paid, Processing, and Ready, then Released. The Registrar cannot start processing until the payment is verified.</p>
                <p class="mb-0"><strong>Grades.</strong> Faculty encode midterm and final grades while the encoding period is open and submit the sheet. The Department Head approves or returns it. Approved sheets are locked. Students see grades only after the Registrar releases the term.</p>
            </div>
        </section>

        <section class="doc-section">
            <h2>Storyboard</h2>
            <div class="card"><div class="table-responsive"><table class="table align-middle">
                <thead><tr><th scope="col">Scene</th><th scope="col">Screen</th><th scope="col">What happens</th></tr></thead>
                <tbody>
                    <tr><td>1</td><td>Sign in</td><td>The student chooses Student, enters the student number and password</td></tr>
                    <tr><td>2</td><td>Overview</td><td>Clearance progress, what is blocking enrollment, GWA, and requests in progress</td></tr>
                    <tr><td>3</td><td>Document requests</td><td>The student picks a document, copies, purpose, and how to receive it</td></tr>
                    <tr><td>4</td><td>Payments</td><td>The student pays with GCash, Maya, or online banking and gets a reference number</td></tr>
                    <tr><td>5</td><td>Cashier: Verify payments</td><td>The Cashier confirms the payment; the request becomes Paid</td></tr>
                    <tr><td>6</td><td>Registrar: Document requests</td><td>The Registrar processes the request and sets a claim date or uploads the e-copy</td></tr>
                    <tr><td>7</td><td>Request tracker</td><td>The student sees Ready, opens the claim stub or downloads the e-copy</td></tr>
                </tbody>
            </table></div></div>
        </section>

        <section class="doc-section">
            <h2>Database</h2>
            <p class="muted">20 tables in third normal form on MySQL (InnoDB), with foreign keys on every link.</p>
            <div class="card"><div class="table-responsive"><table class="table align-middle">
                <thead><tr><th scope="col">Group</th><th scope="col">Tables</th></tr></thead>
                <tbody>
                    <tr><td>Accounts</td><td>roles, users, audit_logs, notifications</td></tr>
                    <tr><td>Academic structure</td><td>departments, programs, subjects, academic_terms, class_sections</td></tr>
                    <tr><td>Students and grades</td><td>students, enrollments, grades</td></tr>
                    <tr><td>Finance</td><td>assessments, payments</td></tr>
                    <tr><td>Clearance</td><td>offices, clearances, clearance_items</td></tr>
                    <tr><td>Document requests</td><td>document_types, document_requests, request_status_history</td></tr>
                </tbody>
            </table></div></div>
        </section>

        <section class="doc-section">
            <h2>Test plan</h2>
            <div class="card"><div class="table-responsive"><table class="table align-middle">
                <thead><tr><th scope="col">Level</th><th scope="col">What is checked</th><th scope="col">Tool</th></tr></thead>
                <tbody>
                    <tr><td>Unit</td><td>Grade computation, GWA, fee computation, request status rules</td><td>PHPUnit</td></tr>
                    <tr><td>Integration</td><td>Data written by one module is read correctly by another</td><td>PHPUnit feature tests</td></tr>
                    <tr><td>System</td><td>Complete processes end to end: request to release, encoding to grade view, full clearance</td><td>PHPUnit feature tests</td></tr>
                    <tr><td>Acceptance</td><td>Real students and staff complete their tasks without help</td><td>UAT script and survey</td></tr>
                    <tr><td>Performance</td><td>Response time and errors under many simultaneous users</td><td>ApacheBench, JMeter</td></tr>
                    <tr><td>Security</td><td>SQL injection, stored cross-site scripting, broken access control</td><td>PHPUnit security tests, OWASP ZAP</td></tr>
                </tbody>
            </table></div></div>
        </section>

        <section class="doc-section">
            <h2>Security: three vulnerabilities and their solutions</h2>
            <div class="card"><div class="table-responsive"><table class="table align-middle">
                <thead><tr><th scope="col">Vulnerability</th><th scope="col">Risk</th><th scope="col">Solution in the system</th></tr></thead>
                <tbody>
                    <tr><td>SQL injection</td><td>Logging in without a password, or reading every record through a search box</td><td>Every query uses bound parameters; input is never joined into SQL</td></tr>
                    <tr><td>Stored cross-site scripting</td><td>A script saved by one user runs in a staff member's session</td><td>All output is HTML-encoded, inputs are validated against fixed lists and patterns, and a Content Security Policy blocks inline scripts</td></tr>
                    <tr><td>Broken access control</td><td>A student opens another student's records or a staff page by changing the address</td><td>Every page checks the role on the server, student pages take the student from the session, and each record is checked for ownership</td></tr>
                </tbody>
            </table></div></div>
        </section>
    </div>
</main>
</body>
</html>
