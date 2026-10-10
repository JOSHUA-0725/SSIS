<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    protected $guarded = [];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function section()
    {
        return $this->belongsTo(ClassSection::class, 'section_id');
    }

    public function grade()
    {
        return $this->hasOne(Grade::class);
    }
}
