<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlotRequest extends Model
{
    protected $guarded = [];

    protected $casts = ['decided_at' => 'datetime'];

    public function term()
    {
        return $this->belongsTo(AcademicTerm::class, 'term_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
