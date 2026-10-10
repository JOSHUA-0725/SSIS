<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentRequest extends Model
{
    protected $guarded = [];

    protected $casts = ['claim_date' => 'date', 'released_at' => 'datetime', 'total_fee' => 'float'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function type()
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function history()
    {
        return $this->hasMany(RequestStatusHistory::class)->orderBy('id');
    }

    public function assessment()
    {
        return $this->hasOne(Assessment::class);
    }

    public function releaser()
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
