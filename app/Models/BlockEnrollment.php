<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockEnrollment extends Model
{
    protected $guarded = [];

    protected $casts = ['is_open' => 'boolean'];
}
