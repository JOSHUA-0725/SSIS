<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicTerm extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'encoding_open' => 'boolean', 'grades_released' => 'boolean', 'enrollment_open' => 'boolean'];

    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
    }

    public function getLabelAttribute(): string
    {
        return "{$this->semester} Semester, {$this->school_year}";
    }
}
