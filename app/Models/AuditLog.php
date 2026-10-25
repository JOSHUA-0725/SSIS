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

    /** Sign-ins and account security, as shown to students. */
    public const SECURITY = ['LOGIN', 'LOGOUT', 'LOGIN_FAILED', 'LOGIN_LOCKED', 'ACCOUNT_LOCKED', 'PASSWORD_CHANGE', 'PASSWORD_RESET', 'PASSWORD_RESET_REQUEST', 'ACCESS_DENIED'];

    /** One plain sentence for the entry, for people who do not read action codes. */
    public function describe(): string
    {
        $new = json_decode((string) $this->new_value, true) ?: [];
        $old = json_decode((string) $this->old_value, true) ?: [];
        $subjects = isset($new['subjects']) && is_array($new['subjects']) && $new['subjects'] ? ': '.implode(', ', $new['subjects']) : '';

        return match ([$this->action, $this->table_name]) {
            ['LOGIN', 'users'] => 'Signed in',
            ['LOGOUT', 'users'] => 'Signed out',
            ['LOGIN_FAILED', 'users'] => 'Wrong password entered',
            ['LOGIN_LOCKED', 'users'] => 'Sign-in refused while the account was locked',
            ['ACCOUNT_LOCKED', 'users'] => 'Account locked after repeated wrong passwords'.(isset($new['minutes']) ? " ({$new['minutes']} minutes)" : ''),
            ['PASSWORD_CHANGE', 'users'] => 'Changed the password',
            ['PASSWORD_RESET', 'users'] => 'Password reset by the Administrator',
            ['PASSWORD_RESET_REQUEST', 'users'] => 'Asked for a password reset',
            ['ACCESS_DENIED', null] => 'Tried to open a page that is not allowed'.(isset($new['url']) ? " (/{$new['url']})" : ''),
            ['QUEUE_JOIN', 'queue_tickets'] => 'Took a queue number'.(isset($new['number']) ? " ({$new['number']})" : ''),
            ['QUEUE_LEAVE', 'queue_tickets'] => 'Cancelled a queue number',
            ['ENROLL_REGULAR', 'students'], ['ENROLL_ADVISED', 'students'], ['ENROLL_BLOCK', 'students'] => 'Enrolled in subjects'.$subjects,
            ['ADVISE_SUBJECTS', 'students'] => 'Department opened subjects for you'.$subjects,
            ['CREATE', 'students'] => 'Student record created',
            ['UPDATE', 'students'] => 'Student record updated by the Registrar',
            ['CREATE', 'document_requests'] => 'Filed a document request'.(isset($new['reference_no']) ? " ({$new['reference_no']})" : ''),
            ['UPDATE', 'document_requests'] => 'Document request '.(isset($new['status']) ? 'changed to '.$new['status'] : 'updated').(isset($old['status']) ? " from {$old['status']}" : ''),
            default => ucfirst(strtolower(str_replace('_', ' ', $this->action))).($this->table_name ? ' · '.str_replace('_', ' ', $this->table_name) : ''),
        };
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
