<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    protected $guarded = [];

    protected $casts = ['midterm_grade' => 'float', 'final_grade' => 'float', 'final_rating' => 'float'];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }
}
