<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo data: 10 more professors of the CCS department and 50 more BSIT students, first to fourth year.
 * Safe to run again: accounts that already exist are left alone.
 */
class BulkDemoSeeder extends Seeder
{
    /** Student numbers 2026-00201 to 2026-00250. */
    public const FIRST_NUMBER = 201;

    public const STUDENTS = 50;

    private const PROFESSORS = [
        'Andres Bautista', 'Beatriz Cruz', 'Carlos Domingo', 'Diana Espiritu', 'Eduardo Flores',
        'Felicia Gonzales', 'Gabriel Hernandez', 'Helen Ignacio', 'Ismael Javier', 'Josefina Lim',
    ];

    private const FIRST_NAMES = [
        ['Adrian', 'M'], ['Bianca', 'F'], ['Carlo', 'M'], ['Danica', 'F'], ['Emmanuel', 'M'],
        ['Faith', 'F'], ['Gerald', 'M'], ['Hannah', 'F'], ['Ivan', 'M'], ['Jasmine', 'F'],
        ['Kevin', 'M'], ['Lovely', 'F'], ['Marco', 'M'], ['Nicole', 'F'], ['Oliver', 'M'],
        ['Patricia', 'F'], ['Quentin', 'M'], ['Rachelle', 'F'], ['Samuel', 'M'], ['Trisha', 'F'],
        ['Ulysses', 'M'], ['Vanessa', 'F'], ['Warren', 'M'], ['Ximena', 'F'], ['Yuri', 'M'],
    ];

    private const LAST_NAMES = [
        'Abad', 'Belen', 'Castro', 'De Guzman', 'Estrada', 'Fernandez', 'Garcia', 'Herrera', 'Ilagan', 'Jimenez',
        'Katigbak', 'Lopez', 'Mendoza', 'Navarro', 'Ocampo', 'Pascual', 'Quiambao', 'Ramos', 'Salazar', 'Torres',
        'Umali', 'Valdez', 'Villanueva', 'Yap', 'Zamora',
    ];

    private const MIDDLE_NAMES = ['Aquino', 'Reyes', 'Santos', 'Cruz', 'Bautista'];

    public function run(): void
    {
        $departmentId = Department::where('code', 'CCS')->value('id');
        $program = Program::where('code', 'BSIT')->first();
        $roles = Role::pluck('id', 'name');
        if (! $departmentId || ! $program) {
            return;
        }

        // prof and prof2 already exist, so these are prof3 to prof12.
        foreach (self::PROFESSORS as $i => $name) {
            $username = 'prof'.($i + 3);
            User::firstOrCreate(['username' => $username], [
                'name' => "Prof. {$name}", 'role_id' => $roles['Professor'], 'department_id' => $departmentId,
                'email' => "{$username}@college.test", 'password' => DatabaseSeeder::DEMO_PASSWORD,
            ]);
        }

        for ($i = 0; $i < self::STUDENTS; $i++) {
            $no = sprintf('2026-%05d', self::FIRST_NUMBER + $i);
            [$first, $sex] = self::FIRST_NAMES[$i % count(self::FIRST_NAMES)];
            $last = self::LAST_NAMES[($i * 7 + intdiv($i, 25)) % count(self::LAST_NAMES)];
            $year = $i % 4 + 1;
            // One student in ten is irregular: no block section, and Faculty opens their subjects.
            $irregular = $i % 10 === 9;

            $user = User::firstOrCreate(['username' => $no], [
                'name' => "{$first} {$last}", 'role_id' => $roles['Student'],
                'email' => "{$no}@student.college.test", 'password' => DatabaseSeeder::DEMO_PASSWORD,
            ]);
            Student::firstOrCreate(['user_id' => $user->id], [
                'student_no' => $no, 'last_name' => $last, 'first_name' => $first,
                'middle_name' => self::MIDDLE_NAMES[$i % count(self::MIDDLE_NAMES)], 'sex' => $sex,
                'program_id' => $program->id, 'year_level' => $year,
                // Not enrolled yet this term, so they can go through enrollment.
                'status' => 'Not Enrolled',
                'student_type' => $irregular ? 'Irregular' : 'Regular',
                'block_section' => $irregular ? null : "{$program->code}-{$year}".(intdiv($i, 4) % 2 === 0 ? 'A' : 'B'),
            ]);
        }
    }
}
