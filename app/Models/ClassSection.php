<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassSection extends Model
{
    protected $guarded = [];

    protected $casts = ['approved_at' => 'datetime'];

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
        return $this->belongsTo(User::class, 'faculty_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'section_id');
    }
}
