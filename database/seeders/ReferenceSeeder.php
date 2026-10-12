<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\DocumentType;
use App\Models\Office;
use App\Models\Program;
use App\Models\Role;
use Illuminate\Database\Seeder;

/** Lookup data the system needs before any account exists. Also used by the tests. */
class ReferenceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Student', 'Registrar', 'Cashier', 'Faculty', 'Professor', 'Department Head', 'Signatory', 'Administrator'] as $name) {
            Role::firstOrCreate(['name' => $name]);
        }

        $ccs = Department::create(['code' => 'CCS', 'name' => 'College of Computer Studies']);
        $cba = Department::create(['code' => 'CBA', 'name' => 'College of Business Administration']);

        Program::create(['department_id' => $ccs->id, 'code' => 'BSIT', 'name' => 'Bachelor of Science in Information Technology']);
        Program::create(['department_id' => $ccs->id, 'code' => 'BSCS', 'name' => 'Bachelor of Science in Computer Science']);
        Program::create(['department_id' => $cba->id, 'code' => 'BSBA', 'name' => 'Bachelor of Science in Business Administration']);

        // Signing order. The Registrar is last and gives the final clearance.
        foreach ([
            ['LIB', 'Library'], ['LAB', 'Laboratory'], ['SAO', 'Student Affairs'],
            ['DEPT', 'Department'], ['CASH', 'Cashier'], ['REG', 'Registrar'],
        ] as $i => [$code, $name]) {
            Office::create(['code' => $code, 'name' => $name, 'sequence_no' => $i + 1]);
        }

        foreach ([
            ['Transcript of Records', 150, 5, 'Official academic record, per copy'],
            ['Certificate of Enrollment', 50, 1, 'Proof of current enrollment'],
            ['Certificate of Grades', 50, 2, 'Grades for a specific term'],
            ['Certificate of Good Moral Character', 50, 2, 'Signed by Student Affairs'],
            ['Honorable Dismissal', 200, 5, 'Required for transfer'],
            ['Certified True Copy of Diploma', 100, 3, 'Certified copy for graduates'],
        ] as [$name, $fee, $days, $description]) {
            DocumentType::create(['name' => $name, 'fee_per_copy' => $fee, 'processing_days' => $days, 'description' => $description]);
        }
    }
}
