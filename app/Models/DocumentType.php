<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'fee_per_copy' => 'float'];
}
