<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\ClassSection;
use App\Models\ClearanceItem;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Office;
use App\Models\Program;
use App\Models\Role;
use App\Models\SlotRequest;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\ClearanceService;
use App\Services\DocumentRequestService;
use App\Services\GradeService;
use App\Services\LedgerService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Every demo account uses this password. Change it before real use. */
    public const DEMO_PASSWORD = 'password123';

    public function run(): void
    {
        $this->call(ReferenceSeeder::class);

        $role = Role::pluck('id', 'name');
        $office = Office::pluck('id', 'code');
        $ccs = Department::where('code', 'CCS')->first();
        $bsit = Program::where('code', 'BSIT')->first();

        $staff = fn (string $username, string $name, string $roleName, array $extra = []) => User::create([
            'username' => $username, 'name' => $name, 'role_id' => $role[$roleName],
            'email' => "{$username}@college.test", 'password' => self::DEMO_PASSWORD,
        ] + $extra);

        $staff('admin', 'System Administrator', 'Administrator');
        $registrar = $staff('registrar', 'Elena Ramos', 'Registrar', ['office_id' => $office['REG']]);
        $cashier = $staff('cashier', 'Roberto Aquino', 'Cashier', ['office_id' => $office['CASH']]);
        $faculty = $staff('faculty', 'Prof. Antonio Garcia', 'Faculty', ['department_id' => $ccs->id]);
        // Professors handle the subjects: each sees their own class sections in My classes.
        $santos = $staff('prof', 'Prof. Liza Santos', 'Professor', ['department_id' => $ccs->id]);
        $reyes = $staff('prof2', 'Prof. Miguel Reyes', 'Professor', ['department_id' => $ccs->id]);
        $head = $staff('head', 'Dr. Lourdes Mendoza', 'Department Head', ['department_id' => $ccs->id]);
        $library = $staff('library', 'Carmen Villanueva', 'Signatory', ['office_id' => $office['LIB']]);
        $laboratory = $staff('laboratory', 'Ramon Castillo', 'Signatory', ['office_id' => $office['LAB']]);
        $sao = $staff('sao', 'Teresa Navarro', 'Signatory', ['office_id' => $office['SAO']]);

        $students = collect([
            ['2026-00123', '2026-00123', 'Dela Cruz', 'Juan', 'Santos', 'M'],
            ['2026-00124', '2026-00124', 'Santos', 'Maria', 'Lopez', 'F'],
            ['2026-00125', '2026-00125', 'Reyes', 'Jose', 'Bautista', 'M'],
        ])->map(function ($row) use ($role, $bsit) {
            [$username, $no, $last, $first, $middle, $sex] = $row;
            $user = User::create([
                'username' => $username, 'name' => "{$first} {$last}", 'role_id' => $role['Student'],
                'email' => "{$no}@student.college.test", 'password' => self::DEMO_PASSWORD,
            ]);

            return Student::create([
                'user_id' => $user->id, 'student_no' => $no, 'last_name' => $last, 'first_name' => $first,
                'middle_name' => $middle, 'sex' => $sex, 'program_id' => $bsit->id, 'year_level' => 3,
            ]);
        });
        [$juan, $maria, $jose] = $students->all();

        $past = AcademicTerm::create(['school_year' => '2025-2026', 'semester' => '2nd', 'grades_released' => true]);
        $current = AcademicTerm::create(['school_year' => '2026-2027', 'semester' => '1st', 'is_active' => true, 'encoding_open' => true, 'enrollment_open' => true]);

        // The curriculum already holds these subjects; the demo classes below are offerings of them.
        $this->call(CurriculumSeeder::class);
        $subject = fn (string $code, string $title) => Subject::firstOrCreate(['code' => $code], ['department_id' => $ccs->id, 'title' => $title, 'units' => 3]);
        $section = fn (Subject $s, AcademicTerm $t, string $status = 'Draft', ?User $teacher = null) => ClassSection::create([
            'subject_id' => $s->id, 'term_id' => $t->id, 'section_code' => 'BSIT-3A', 'faculty_id' => ($teacher ?? $santos)->id,
            'sheet_status' => $status,
            'approved_by' => $status === 'Approved' ? $head->id : null,
            'approved_at' => $status === 'Approved' ? now()->subMonths(4) : null,
        ]);
        $enroll = function (ClassSection $sec, array $grades = []) use ($students) {
            foreach ($students as $i => $student) {
                $enrollment = Enrollment::create(['student_id' => $student->id, 'section_id' => $sec->id]);
                if (isset($grades[$i])) {
                    [$prelim, $midterm, $final] = $grades[$i];
                    $rating = GradeService::finalRating($prelim, $midterm, $final);
                    Grade::create([
                        'enrollment_id' => $enrollment->id, 'prelim_grade' => $prelim, 'midterm_grade' => $midterm, 'final_grade' => $final,
                        'final_rating' => $rating, 'remarks' => GradeService::remarks($rating), 'encoded_by' => $sec->faculty_id,
                    ]);
                }
            }
        };

        // Last term: approved and released, so students already have grades to view.
        $enroll($section($subject('IT 221', 'Data Structures and Algorithms'), $past, 'Approved'), [[2.00, 1.75, 1.50], [1.25, 1.25, 1.25], [2.75, 2.50, 3.00]]);
        $enroll($section($subject('IT 222', 'Database Management Systems'), $past, 'Approved'), [[2.00, 2.00, 1.75], [1.50, 1.50, 1.25], [3.00, 3.00, 2.75]]);

        // This term: one sheet waiting for the Department Head, one draft with prelim and midterm only, two empty.
        $enroll($section($subject('IT 311', 'Systems Analysis and Design'), $current, 'Submitted', $santos), [[1.75, 2.00, 1.50], [1.25, 1.25, 1.50], [2.50, 2.75, 2.50]]);
        $enroll($section($subject('IT 312', 'Information Assurance and Security'), $current, 'Draft', $santos), [[1.75, 1.75, null], [1.50, 1.50, null]]);
        $enroll($section($subject('IT 313', 'Web Systems and Technologies'), $current, 'Draft', $reyes));
        $enroll($section($subject('GE 105', 'Ethics'), $current, 'Draft', $reyes));

        // Ten more professors and fifty more BSIT students, then released grades for every earlier semester.
        $this->call(BulkDemoSeeder::class);
        $this->call(GradeHistorySeeder::class);

        // Enrollment is open in the demo, so every subject of this semester is already offered for each block section.
        \App\Services\EnrollmentService::offerCurriculum($current);

        // Juan and Maria are regular students of block BSIT-3A; Jose is irregular: Faculty opens his subjects one by one, within the approved slots.
        $juan->update(['block_section' => 'BSIT-3A']);
        $maria->update(['block_section' => 'BSIT-3A']);
        $jose->update(['student_type' => 'Irregular']);
        SlotRequest::create([
            'term_id' => $current->id, 'department_id' => $ccs->id, 'category' => 'Irregular', 'slots' => 20,
            'status' => 'Approved', 'requested_by' => $faculty->id, 'decided_by' => $registrar->id, 'decided_at' => now()->subDay(),
        ]);
        SlotRequest::create(['term_id' => $current->id, 'department_id' => $ccs->id, 'category' => 'Irregular', 'slots' => 10, 'requested_by' => $faculty->id]);

        $charges = [];
        foreach ($students as $student) {
            $charges[$student->id] = [
                Assessment::create(['student_id' => $student->id, 'term_id' => $current->id, 'fee_type' => 'Tuition', 'description' => 'Tuition fee, 21 units', 'amount' => 15000]),
                Assessment::create(['student_id' => $student->id, 'term_id' => $current->id, 'fee_type' => 'Miscellaneous', 'description' => 'Miscellaneous fees', 'amount' => 3500]),
            ];
            ClearanceService::ensureFor($student, $current);
        }

        // Juan: balance of 5,500, so the Cashier has not cleared him. Other offices have.
        LedgerService::postPayment($charges[$juan->id][0], 13000, 'OR-0045801', 'Cash', $cashier);
        // Maria: fully paid, every office cleared, waiting only for the Registrar's final clearance.
        LedgerService::postPayment($charges[$maria->id][0], 15000, 'OR-0045802', 'Cash', $cashier);
        LedgerService::postPayment($charges[$maria->id][1], 3500, 'OR-0045803', 'Bank Deposit', $cashier);

        $sign = function (Student $student, string $code, User $by, string $status = 'Cleared', ?string $remarks = null) use ($current, $office) {
            $item = ClearanceItem::where('office_id', $office[$code])
                ->whereHas('clearance', fn ($q) => $q->where(['student_id' => $student->id, 'term_id' => $current->id]))->first();
            ClearanceService::sign($item, $status, $remarks, $by);
        };
        foreach ([$juan, $maria] as $student) {
            $sign($student, 'LIB', $library);
            $sign($student, 'LAB', $laboratory);
            $sign($student, 'SAO', $sao);
            $sign($student, 'DEPT', $head);
        }
        // Jose: on hold at the Library.
        $sign($jose, 'LIB', $library, 'On Hold', 'Unreturned book: Database System Concepts');

        // Document requests at different stages.
        $tor = DocumentType::where('name', 'Transcript of Records')->first();
        $coe = DocumentType::where('name', 'Certificate of Enrollment')->first();
        DocumentRequestService::file($juan, $tor, 2, 'Employment');
        $paid = DocumentRequestService::file($maria, $coe, 1, 'Scholarship application');
        LedgerService::postPayment($paid->assessment, $paid->total_fee, 'OR-0045804', 'Cash', $cashier);
    }
}
