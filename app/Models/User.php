<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = [
        'username', 'password', 'role_id', 'name', 'email', 'office_id', 'department_id', 'status',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'locked_until' => 'datetime',
            'last_login' => 'datetime',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function student()
    {
        return $this->hasOne(Student::class);
    }

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function roleName(): string
    {
        return $this->role->name;
    }

    public function hasRole(string ...$names): bool
    {
        return in_array($this->roleName(), $names, true);
    }
}
