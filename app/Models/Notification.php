<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $guarded = [];

    protected $casts = ['is_read' => 'boolean'];

    public static function notify(int $userId, string $title, string $message): void
    {
        static::create(['user_id' => $userId, 'title' => $title, 'message' => $message]);
    }
}
