<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $guarded = [];

    protected $casts = ['amount' => 'float', 'paid_at' => 'datetime'];

    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
