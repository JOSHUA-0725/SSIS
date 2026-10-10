<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Clearance extends Model
{
    protected $guarded = [];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function term()
    {
        return $this->belongsTo(AcademicTerm::class, 'term_id');
    }

    public function items()
    {
        return $this->hasMany(ClearanceItem::class);
    }
}
