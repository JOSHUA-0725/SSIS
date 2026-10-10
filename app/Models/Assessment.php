<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
{
    protected $guarded = [];

    protected $casts = ['amount' => 'float'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function term()
    {
        return $this->belongsTo(AcademicTerm::class, 'term_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function documentRequest()
    {
        return $this->belongsTo(DocumentRequest::class);
    }
}
