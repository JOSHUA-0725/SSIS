<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassSection extends Model
{
    protected $guarded = [];

    protected $casts = ['approved_at' => 'datetime'];

    /** Year levels a class can belong to. */
    public const YEARS = [1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year', 5 => '5th Year'];

    /** Section letters a program can have in one year level. */
    public const LETTERS = ['A', 'B', 'C', 'D', 'E', 'F'];

    /** Splits a section code such as BSIT-3A into its program, year level, and letter; null when it has another shape. */
    public function block(): ?array
    {
        if (! preg_match('/^([A-Za-z]+)-(\d)([A-Za-z])$/', (string) $this->section_code, $m)) {
            return null;
        }

        return ['program' => strtoupper($m[1]), 'year' => (int) $m[2], 'letter' => strtoupper($m[3])];
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function term()
    {
        return $this->belongsTo(AcademicTerm::class, 'term_id');
    }

    public function faculty()
    {
        // A class that Faculty has not given to a professor yet still shows a name.
        return $this->belongsTo(User::class, 'faculty_id')->withDefault(['name' => 'To be assigned']);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'section_id');
    }
}
