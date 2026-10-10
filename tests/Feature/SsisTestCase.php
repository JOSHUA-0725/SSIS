<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\ClassSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Office;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Shared setup for integration, system and security tests. */
abstract class SsisTestCase extends TestCase
{
    use RefreshDatabase;

    protected const PASSWORD = 'Password123';

    protected AcademicTerm $term;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceSeeder::class);
        $this->term = AcademicTerm::create(['school_year' => '2026-2027', 'semester' => '1st', 'is_active' => true, 'encoding_open' => true]);
    }

    protected function makeUser(string $role, array $extra = []): User
    {
        static $n = 0;
        $n++;

        // Values in $extra replace the defaults.
        return User::create($extra + [
            'username' => "user{$n}", 'name' => "{$role} {$n}", 'email' => "user{$n}@college.test",
            'password' => self::PASSWORD, 'role_id' => Role::where('name', $role)->value('id'),
        ])->refresh();
    }

    protected function makeStudent(string $program = 'BSIT'): Student
    {
        static $n = 0;
        $n++;
        $no = sprintf('2026-%05d', $n);
        $user = $this->makeUser('Student', ['username' => $no]);

        return Student::create([
            'user_id' => $user->id, 'student_no' => $no, 'last_name' => "Last{$n}", 'first_name' => "First{$n}",
            'program_id' => Program::where('code', $program)->value('id'), 'year_level' => 3,
        ])->refresh();
    }

    protected function ccs(): int
    {
        return Department::where('code', 'CCS')->value('id');
    }

    protected function signatory(string $officeCode): User
    {
        return $this->makeUser('Signatory', ['office_id' => Office::where('code', $officeCode)->value('id')]);
    }

    /** A class section in the active term with the given students enrolled. */
    protected function makeSection(User $faculty, Student ...$students): ClassSection
    {
        static $n = 0;
        $n++;
        $subject = Subject::create(['department_id' => $this->ccs(), 'code' => "IT {$n}", 'title' => "Subject {$n}", 'units' => 3]);
        $section = ClassSection::create(['subject_id' => $subject->id, 'term_id' => $this->term->id, 'section_code' => 'BSIT-3A', 'faculty_id' => $faculty->id]);
        foreach ($students as $student) {
            Enrollment::create(['student_id' => $student->id, 'section_id' => $section->id]);
        }

        return $section->refresh();
    }
}
