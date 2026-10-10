<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'request_status_history';

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
