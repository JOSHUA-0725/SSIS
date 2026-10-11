<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueTicket extends Model
{
    protected $guarded = [];

    protected $casts = ['queue_date' => 'date', 'called_at' => 'datetime', 'finished_at' => 'datetime'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function server()
    {
        return $this->belongsTo(User::class, 'served_by');
    }

    /** Tickets of today's line only. */
    public function scopeToday($query)
    {
        return $query->whereDate('queue_date', today());
    }

    /** The number as it is called at the window, for example E-007. */
    public function getLabelAttribute(): string
    {
        return 'E-'.str_pad((string) $this->number, 3, '0', STR_PAD_LEFT);
    }
}
