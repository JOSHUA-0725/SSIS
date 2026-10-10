<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClearanceItem extends Model
{
    protected $guarded = [];

    protected $casts = ['signed_at' => 'datetime'];

    public function clearance()
    {
        return $this->belongsTo(Clearance::class);
    }

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function signer()
    {
        return $this->belongsTo(User::class, 'signed_by');
    }
}
