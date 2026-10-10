<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public const MAX_ATTEMPTS = 5;

    public const LOCK_MINUTES = 15;

    /** The sign-in choices on the login page and the roles each one accepts. */
    public const PORTALS = [
        'student' => ['label' => 'Student', 'module' => 'Student Portal', 'field' => 'Student number', 'hint' => '2026-00123', 'icon' => 'graduation', 'roles' => ['Student']],
        'registrar' => ['label' => 'Registrar', 'module' => 'Registrar Module', 'field' => 'Registrar username', 'hint' => 'registrar', 'icon' => 'file', 'roles' => ['Registrar']],
        'cashier' => ['label' => 'Cashier', 'module' => 'Cashier Module', 'field' => 'Cashier username', 'hint' => 'cashier', 'icon' => 'wallet', 'roles' => ['Cashier']],
        'department' => ['label' => 'Department', 'module' => 'Department Module', 'field' => 'Faculty or office username', 'hint' => 'faculty', 'icon' => 'building', 'roles' => ['Faculty', 'Department Head', 'Signatory']],
        'admin' => ['label' => 'Admin', 'module' => 'Admin Module', 'field' => 'Admin username', 'hint' => 'admin', 'icon' => 'shield', 'roles' => ['Administrator']],
    ];

    public function showLogin()
    {
        return view('auth.login', ['portals' => self::PORTALS]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'portal' => ['nullable', Rule::in(array_keys(self::PORTALS))],
            'username' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $user = User::where('username', $data['username'])->first();
        $keep = ['username', 'portal'];

        if ($user && $user->locked_until?->isFuture()) {
            AuditLog::record('LOGIN_LOCKED', 'users', $user->id, null, null, $user->id);

            return back()->withErrors(['username' => 'Too many failed attempts. This account is locked for '.self::LOCK_MINUTES.' minutes.'])->onlyInput(...$keep);
        }

        if (! $user || $user->status !== 'Active' || ! Hash::check($data['password'], $user->password)) {
            if ($user) {
                $user->failed_attempts++;
                if ($user->failed_attempts >= self::MAX_ATTEMPTS) {
                    $user->locked_until = now()->addMinutes(self::LOCK_MINUTES);
                    $user->failed_attempts = 0;
                }
                $user->save();
            }
            AuditLog::record('LOGIN_FAILED', 'users', $user?->id, null, ['username' => $data['username']], $user?->id);

            return back()->withErrors(['username' => 'Invalid username or password.'])->onlyInput(...$keep);
        }

        // Checked only after the password is correct, so it reveals nothing to a guesser.
        $portal = $data['portal'] ?? null;
        if ($portal && ! $user->hasRole(...self::PORTALS[$portal]['roles'])) {
            return back()->withErrors(['username' => 'That account does not belong to the '.self::PORTALS[$portal]['module'].'. Choose how you use SSIS above, then sign in again.'])->onlyInput(...$keep);
        }

        $user->forceFill(['failed_attempts' => 0, 'locked_until' => null, 'last_login' => now()])->save();

        Auth::login($user);
        $request->session()->regenerate();
        AuditLog::record('LOGIN', 'users', $user->id);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        AuditLog::record('LOGOUT', 'users', Auth::id());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showForgot()
    {
        return view('auth.forgot');
    }

    /** Sends a reset request to the administrators, who issue a temporary password. */
    public function forgot(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:120'],
        ]);

        $user = User::where('username', $data['username'])->where('email', $data['email'])->where('status', 'Active')->first();

        if ($user) {
            foreach (User::whereHas('role', fn ($q) => $q->where('name', 'Administrator'))->where('status', 'Active')->pluck('id') as $adminId) {
                Notification::notify($adminId, 'Password reset requested', "{$user->username} ({$user->name}) asked for a password reset. Open Users and choose Reset password.");
            }
        }
        AuditLog::record('PASSWORD_RESET_REQUEST', 'users', $user?->id, null, ['username' => $data['username']], $user?->id);

        // Same answer whether or not the account exists.
        return back()->with('status', 'If that username and email match an account, the MIS office has been notified and will give you a temporary password.');
    }

    public function documentation()
    {
        return view('documentation');
    }
}
