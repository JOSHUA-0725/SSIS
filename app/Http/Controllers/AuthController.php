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

    /** Each new lock doubles the wait, up to one day. */
    public const MAX_LOCK_MINUTES = 1440;

    /** The sign-in pages, one per portal, and the roles each one accepts. */
    public const PORTALS = [
        'student' => ['label' => 'Student', 'module' => 'Student Portal', 'about' => 'Grades, payments, clearance, and document requests', 'field' => 'Student ID', 'hint' => '2026-00123', 'icon' => 'graduation', 'roles' => ['Student']],
        'registrar' => ['label' => 'Registrar', 'module' => 'Registrar Module', 'about' => 'Student records, terms, and document requests', 'field' => 'Registrar username', 'hint' => 'registrar', 'icon' => 'file', 'roles' => ['Registrar']],
        'cashier' => ['label' => 'Cashier', 'module' => 'Cashier Module', 'about' => 'Assessments, payments, and collection reports', 'field' => 'Cashier username', 'hint' => 'cashier', 'icon' => 'wallet', 'roles' => ['Cashier']],
        'department' => ['label' => 'Department', 'module' => 'Department Module', 'about' => 'Faculty, professors, department heads, and clearance offices', 'field' => 'Faculty, professor, or office username', 'hint' => 'faculty', 'icon' => 'building', 'roles' => ['Faculty', 'Professor', 'Department Head', 'Signatory']],
        'admin' => ['label' => 'Admin', 'module' => 'Admin Module', 'about' => 'User accounts, document types, and the audit log', 'field' => 'Admin username', 'hint' => 'admin', 'icon' => 'shield', 'roles' => ['Administrator']],
    ];

    /** The first screen: choose a portal. */
    public function showLogin()
    {
        return view('auth.portals', ['portals' => self::PORTALS]);
    }

    /** The sign-in form of one portal. */
    public function showPortal(string $portal)
    {
        return view('auth.login', ['key' => $portal, 'portal' => self::PORTALS[$portal]]);
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

            return $this->lockedResponse($user, $keep);
        }

        if (! $user || $user->status !== 'Active' || ! Hash::check($data['password'], $user->password)) {
            $lockedFor = null;
            if ($user) {
                $user->failed_attempts++;
                if ($user->failed_attempts >= self::MAX_ATTEMPTS) {
                    $user->lockout_count = min($user->lockout_count + 1, 20);
                    $lockedFor = min(self::LOCK_MINUTES * 2 ** ($user->lockout_count - 1), self::MAX_LOCK_MINUTES);
                    $user->locked_until = now()->addMinutes($lockedFor);
                    $user->failed_attempts = 0;
                }
                $user->save();
                if ($lockedFor) {
                    $this->alertLockout($user, $lockedFor, (string) $request->ip());
                }
            }
            AuditLog::record('LOGIN_FAILED', 'users', $user?->id, null, ['username' => $data['username']], $user?->id);

            if ($lockedFor) {
                return $this->lockedResponse($user, $keep);
            }

            return back()->withErrors(['username' => 'Invalid username or password.'])->onlyInput(...$keep);
        }

        // Checked only after the password is correct, so it reveals nothing to a guesser.
        $portal = $data['portal'] ?? null;
        if ($portal && ! $user->hasRole(...self::PORTALS[$portal]['roles'])) {
            return back()->withErrors(['username' => 'That account does not belong to the '.self::PORTALS[$portal]['module'].'. Go back, choose your own portal, then sign in again.'])->onlyInput(...$keep);
        }

        $user->forceFill(['failed_attempts' => 0, 'lockout_count' => 0, 'locked_until' => null, 'last_login' => now()])->save();

        Auth::login($user);
        $request->session()->regenerate();
        AuditLog::record('LOGIN', 'users', $user->id);

        return redirect()->intended(route('dashboard'));
    }

    /** The refusal shown while an account is locked, with the seconds left for the countdown on the page. */
    private function lockedResponse(User $user, array $keep)
    {
        $seconds = max(1, (int) ceil(now()->diffInSeconds($user->locked_until)));

        return back()->withErrors(['username' => 'Too many failed attempts. This account is locked.'])->with('retry_seconds', $seconds)->onlyInput(...$keep);
    }

    /** Tells the account owner and the administrators that an account was locked, and from where. */
    private function alertLockout(User $user, int $minutes, string $ip): void
    {
        AuditLog::record('ACCOUNT_LOCKED', 'users', $user->id, null, ['minutes' => $minutes, 'lockout_count' => $user->lockout_count], $user->id);

        Notification::notify($user->id, 'Your account was locked', "Someone entered a wrong password ".self::MAX_ATTEMPTS." times from {$ip}, so your account was locked for {$minutes} minutes. If this was not you, change your password.");

        $admins = User::whereHas('role', fn ($q) => $q->where('name', 'Administrator'))->where('status', 'Active')->where('id', '!=', $user->id)->pluck('id');
        foreach ($admins as $adminId) {
            Notification::notify($adminId, 'Account locked', "{$user->username} ({$user->name}) was locked for {$minutes} minutes after repeated wrong passwords from {$ip}.");
        }
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
