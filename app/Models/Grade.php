<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    protected $guarded = [];

    protected $casts = ['prelim_grade' => 'float', 'midterm_grade' => 'float', 'final_grade' => 'float', 'final_rating' => 'float'];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }
}
