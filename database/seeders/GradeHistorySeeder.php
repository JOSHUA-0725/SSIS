<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\ClassSection;
use App\Models\CurriculumSubject;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use App\Services\GradeService;
use Illuminate\Database\Seeder;

/**
 * Demo data: released grades of the demo students for every semester before the current one,
 * following their curriculum. Safe to run again: existing classes and grades are kept.
 */
class GradeHistorySeeder extends Seeder
{
    private const SCALE = [1.00, 1.25, 1.50, 1.75, 2.00, 2.25, 2.50, 2.75, 3.00];

    /** Where each named demo student usually lands on the scale. */
    private const USUAL = ['2026-00123' => 3, '2026-00124' => 1, '2026-00125' => 6];

    public function run(): void
    {
        $active = AcademicTerm::active();
        $professors = User::whereIn('username', ['prof', 'prof2'])->orderBy('username')->get();
        $head = User::where('username', 'head')->first();
        // The three named demo students, and the fifty added by BulkDemoSeeder (their username is their student number).
        $bulk = array_map(fn ($i) => sprintf('2026-%05d', BulkDemoSeeder::FIRST_NUMBER + $i), range(0, BulkDemoSeeder::STUDENTS - 1));
        $students = Student::with(['user', 'program'])->whereHas('user', fn ($q) => $q->whereIn('username', array_merge(array_keys(self::USUAL), $bulk)))->get();
        if (! $active || $professors->isEmpty() || $students->isEmpty()) {
            return;
        }

        $startYear = (int) substr($active->school_year, 0, 4);

        foreach ($students as $student) {
            $rows = CurriculumSubject::with('subject')->where('program_id', $student->program_id)
                ->where('year_level', '<', $student->year_level)->get();

            foreach ($rows as $i => $row) {
                // A third-year student took the first-year subjects two school years ago.
                $from = $startYear - ($student->year_level - $row->year_level);
                $term = AcademicTerm::firstOrCreate(
                    ['school_year' => $from.'-'.($from + 1), 'semester' => $row->semester],
                    ['grades_released' => true],
                );
                if (! $term->grades_released) {
                    continue;
                }

                $section = ClassSection::firstOrCreate(
                    ['subject_id' => $row->subject_id, 'term_id' => $term->id],
                    [
                        'section_code' => "{$student->program->code}-{$row->year_level}A",
                        'faculty_id' => $professors[$i % $professors->count()]->id,
                        'sheet_status' => 'Approved', 'approved_by' => $head?->id, 'approved_at' => now()->subMonths(4),
                    ],
                );
                $enrollment = Enrollment::firstOrCreate(['student_id' => $student->id, 'section_id' => $section->id]);

                $prelim = $this->grade($student->user->username, $row->subject->code.'P');
                $midterm = $this->grade($student->user->username, $row->subject->code.'M');
                $final = $this->grade($student->user->username, $row->subject->code.'F');
                $rating = GradeService::finalRating($prelim, $midterm, $final);
                Grade::firstOrCreate(['enrollment_id' => $enrollment->id], [
                    'prelim_grade' => $prelim, 'midterm_grade' => $midterm, 'final_grade' => $final, 'final_rating' => $rating,
                    'remarks' => GradeService::remarks($rating), 'encoded_by' => $section->faculty_id,
                ]);
            }

            // Demo grades encoded before the prelim period existed get one, and their rating is computed again.
            $old = Grade::with('enrollment.section.subject')->whereNull('prelim_grade')->whereNotNull('midterm_grade')
                ->whereHas('enrollment', fn ($q) => $q->where('student_id', $student->id))->get();
            foreach ($old as $grade) {
                $prelim = $this->grade($student->user->username, $grade->enrollment->section->subject->code.'P');
                $rating = GradeService::finalRating($prelim, $grade->midterm_grade, $grade->final_grade);
                $grade->update(['prelim_grade' => $prelim, 'final_rating' => $rating, 'remarks' => GradeService::remarks($rating)]);
            }
        }
    }

    /** The same student and subject always get the same grade, one step around the student's usual mark. */
    private function grade(string $username, string $salt): float
    {
        $step = (crc32($username.$salt) % 3) - 1;
        // A student without a set mark gets one from their username, anywhere from 1.25 to 2.50.
        $usual = self::USUAL[$username] ?? 1 + crc32($username) % 6;
        $index = max(0, min(count(self::SCALE) - 1, $usual + $step));

        return self::SCALE[$index];
    }
}
