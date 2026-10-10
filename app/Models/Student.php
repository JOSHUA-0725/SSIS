<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $guarded = [];

    protected $casts = ['birth_date' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function assessments()
    {
        return $this->hasMany(Assessment::class);
    }

    public function documentRequests()
    {
        return $this->hasMany(DocumentRequest::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->last_name}, {$this->first_name} {$this->middle_name}");
    }
}
