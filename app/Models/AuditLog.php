<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Write one audit entry for the current request. */
    public static function record(string $action, ?string $table = null, ?int $recordId = null, mixed $old = null, mixed $new = null, ?int $userId = null): void
    {
        static::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'table_name' => $table,
            'record_id' => $recordId,
            'old_value' => $old === null ? null : json_encode($old),
            'new_value' => $new === null ? null : json_encode($new),
            'ip_address' => request()->ip(),
        ]);
    }
}
