<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurriculumSubject extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
