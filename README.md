# SSIS — Student Services Information System

A web system that replaces the physical queues for enrollment clearance, grade viewing, and document requests. Built with Laravel 12 (PHP 8.2), MySQL/MariaDB, and Bootstrap 5.

## Modules

| Module | Who uses it | What it does |
| --- | --- | --- |
| Student Portal | Students | Grades per term with GWA, clearance tracker, account balance, notifications |
| Document Request System | Students, Cashier, Registrar | File a request, pay or upload proof, track status, claim stub |
| Registrar Module | Registrar | Student records, enrollment, terms and grade release, request processing, final clearance |
| Cashier Module | Cashier | Charges, payment posting with OR number, proof verification, ledger, daily collection report |
| Department Module | Professor, Faculty, Department Head | Professors encode the grades of their classes; Faculty assigns subjects and runs the department's enrollment; the Head approves grade sheets and signs clearance |
| Admin Module | Administrator | Users and roles, document types and fees, audit log |

## Run it on XAMPP

1. Open the XAMPP Control Panel and start **Apache** and **MySQL**.
2. Open a terminal in this folder (`C:\xampp\htdocs\SSIS`).
3. First time only, create the database and the demo data:

   ```
   C:\xampp\mysql\bin\mysql.exe -uroot -e "CREATE DATABASE IF NOT EXISTS ssis_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   C:\xampp\php\php.exe artisan migrate --seed
   ```

4. Start the system:

   ```
   C:\xampp\php\php.exe artisan serve
   ```

5. Open <http://127.0.0.1:8000> in a browser.

It also runs through Apache at <http://localhost/SSIS/public>. When you use Apache with several users at once, run `php artisan config:cache` first (see Troubleshooting).

On a new computer, run `composer install`, copy `.env.example` to `.env`, and run `php artisan key:generate` before step 3.

After pulling new code, run `C:\xampp\php\php.exe artisan migrate` once so the database gets any new columns.

## Demo accounts

Every demo account uses the password `password123`. A student's username is the Student ID, for example `2026-00123`. On the sign-in page, choose the tile that matches the account: Student, Registrar, Cashier, Department (professors, faculty, department heads, and clearance offices), or Admin.

To see the whole flow: sign in as student `2026-00123`, file a document request, pay it on the Payments page, sign in as `cashier` and confirm it under Verify payments, then sign in as `registrar` and process it under Document requests.

Online payments (GCash, Maya, online banking) run on a demo channel: no merchant account is connected, so no real money moves. A payment is recorded with a reference number and waits for the Cashier to confirm it.

| Username | Role | What to try |
| --- | --- | --- |
| `2026-00123` | Student, Juan Dela Cruz | Balance of ₱5,500, Cashier clearance pending, one unpaid Transcript request |
| `2026-00124` | Student, Maria Santos | Fully paid, waiting only for the Registrar's final clearance |
| `2026-00125` | Student, Jose Reyes | On hold at the Library |
| `2026-00201` to `2026-00250` | 50 more BSIT students | First to fourth year, not yet enrolled this term; every tenth one (…210, …220, …230, …240, …250) is irregular |
| `registrar` | Registrar | Open enrollment for regular students, Process the paid request, release grades, give final clearance |
| `cashier` | Cashier | Post a payment, verify proofs, print the collection report |
| `prof` to `prof12` | Professor | Open My classes, encode and submit a grade sheet |
| `faculty` | Faculty | Assign a professor and section to each subject, ask the Registrar for irregular slots, search an irregular student by ID and open subjects for them |
| `head` | Department Head | Approve or return the submitted IT 311 sheet, sign clearance |
| `library`, `laboratory`, `sao` | Clearance Signatory | Clear a student or place a hold |
| `admin` | Administrator | Add users, change fees, read the audit log |

To reset everything back to the demo data: `C:\xampp\php\php.exe artisan migrate:fresh --seed`. This deletes all records in `ssis_db`.

## Tests

```
C:\xampp\php\php.exe artisan config:clear
C:\xampp\php\php.exe artisan test
```

Tests run on a separate in-memory database, so they do not touch `ssis_db`. **Always run `config:clear` first.** If the configuration is cached, the tests use the real database and wipe it.

| File | Level | Covers |
| --- | --- | --- |
| `tests/Unit` | Unit | Grade computation, GWA, fee computation, request status rules |
| `tests/Feature/AuthTest.php` | Integration | Login, lockout after 5 failed attempts, deactivated accounts, session renewal |
| `tests/Feature/WorkflowTest.php` | Integration and system | Document request end to end, payments, grades from encoding to release, clearance, admin |
| `tests/Feature/SecurityTest.php` | Security | SQL injection, stored XSS, broken access control, brute force on sign-in, file upload |
| `tests/Feature/PortalFeaturesTest.php` | Integration and system | Sign-in choices, forgot password, online payment and verification, certified e-copy, clearance slip |

## Where things are

| Path | Contents |
| --- | --- |
| `routes/web.php` | Every URL, grouped by module and role |
| `app/Http/Controllers` | One controller per module |
| `app/Services` | Business rules: grades, ledger, clearance, document requests |
| `app/Http/Middleware` | Role check and security headers |
| `app/Models` | The 20 database tables as Eloquent models |
| `database/migrations` | Database schema |
| `database/seeders` | Lookup data and demo data |
| `resources/views` | Screens (Blade templates) |

## Troubleshooting

- **"Server Error" on some requests when many users load pages at once through Apache.** PHP on Windows Apache reads `.env` in a way that is not thread-safe. Run `C:\xampp\php\php.exe artisan config:cache`. Run it again after any change to `.env`, and run `config:clear` before running the tests.
- **"SQLSTATE[HY000] [2002]" or "Connection refused".** MySQL is not running. Start it in the XAMPP Control Panel.
- **"Unknown database 'ssis_db'".** Run step 3 above.
- **Pages have no styling or the fonts look plain.** Bootstrap and the fonts load from the internet (cdn.jsdelivr.net and Google Fonts). Connect to the internet.
- **"Unknown column" errors after an update.** Run `C:\xampp\php\php.exe artisan migrate`.
- **Locked out after 5 wrong passwords.** Wait for the time shown (15 minutes the first time, then 30, 60, and so on up to one day), or log in as `admin` and use Reset password on the Users page.
- **"Too many sign-in attempts from this device."** One device may try to sign in 10 times per minute. Wait one minute.
