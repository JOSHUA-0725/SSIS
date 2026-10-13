<?php

namespace Database\Seeders;

use App\Models\CurriculumSubject;
use App\Models\Department;
use App\Models\Program;
use App\Models\Subject;
use Illuminate\Database\Seeder;

/** The four-year BSIT curriculum. Safe to run again: existing subjects are reused, not duplicated. */
class CurriculumSeeder extends Seeder
{
    /** year level => semester => [code, title, units] */
    private const BSIT = [
        1 => [
            '1st' => [
                ['IT 111', 'Introduction to Computing', 3],
                ['IT 112', 'Computer Programming 1', 3],
                ['GE 101', 'Understanding the Self', 3],
                ['GE 102', 'Purposive Communication', 3],
                ['GE 103', 'Mathematics in the Modern World', 3],
                ['PE 101', 'Physical Education 1', 2],
                ['NSTP 101', 'National Service Training Program 1', 3],
            ],
            '2nd' => [
                ['IT 121', 'Computer Programming 2', 3],
                ['IT 122', 'Discrete Mathematics', 3],
                ['IT 123', 'Introduction to Human Computer Interaction', 3],
                ['GE 104', 'Readings in Philippine History', 3],
                ['GE 106', 'Art Appreciation', 3],
                ['PE 102', 'Physical Education 2', 2],
                ['NSTP 102', 'National Service Training Program 2', 3],
            ],
        ],
        2 => [
            '1st' => [
                ['IT 211', 'Object-Oriented Programming', 3],
                ['IT 212', 'Platform Technologies', 3],
                ['IT 213', 'Information Management', 3],
                ['GE 107', 'Science, Technology, and Society', 3],
                ['GE 108', 'The Contemporary World', 3],
                ['PE 201', 'Physical Education 3', 2],
            ],
            '2nd' => [
                ['IT 221', 'Data Structures and Algorithms', 3],
                ['IT 222', 'Database Management Systems', 3],
                ['IT 223', 'Networking 1', 3],
                ['IT 224', 'Quantitative Methods', 3],
                ['GE 109', 'The Life and Works of Rizal', 3],
                ['PE 202', 'Physical Education 4', 2],
            ],
        ],
        3 => [
            '1st' => [
                ['IT 311', 'Systems Analysis and Design', 3],
                ['IT 312', 'Information Assurance and Security', 3],
                ['IT 313', 'Web Systems and Technologies', 3],
                ['IT 314', 'Networking 2', 3],
                ['IT 315', 'Integrative Programming and Technologies', 3],
                ['GE 105', 'Ethics', 3],
            ],
            '2nd' => [
                ['IT 321', 'Application Development and Emerging Technologies', 3],
                ['IT 322', 'Systems Integration and Architecture', 3],
                ['IT 323', 'Mobile Application Development', 3],
                ['IT 324', 'Social and Professional Issues', 3],
                ['IT 325', 'Capstone Project 1', 3],
            ],
        ],
        4 => [
            '1st' => [
                ['IT 411', 'Capstone Project 2', 3],
                ['IT 412', 'Systems Administration and Maintenance', 3],
                ['IT 413', 'Data Analytics', 3],
                ['IT 414', 'Technopreneurship', 3],
            ],
            '2nd' => [
                ['IT 421', 'Practicum (486 hours)', 6],
            ],
        ],
    ];

    public function run(): void
    {
        $program = Program::where('code', 'BSIT')->first();
        $departmentId = Department::where('code', 'CCS')->value('id');
        if (! $program || ! $departmentId) {
            return;
        }

        foreach (self::BSIT as $year => $semesters) {
            foreach ($semesters as $semester => $subjects) {
                foreach ($subjects as [$code, $title, $units]) {
                    $subject = Subject::firstOrCreate(['code' => $code], ['department_id' => $departmentId, 'title' => $title, 'units' => $units]);
                    CurriculumSubject::updateOrCreate(
                        ['program_id' => $program->id, 'subject_id' => $subject->id],
                        ['year_level' => $year, 'semester' => $semester],
                    );
                }
            }
        }
    }
}
