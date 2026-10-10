<?php

namespace Tests\Feature;

use App\Models\AuditLog;

class AuthTest extends SsisTestCase
{
    public function test_guest_is_sent_to_the_login_page(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_user_logs_in_and_lands_on_the_dashboard(): void
    {
        $user = $this->makeUser('Registrar');

        $this->post('/login', ['username' => $user->username, 'password' => self::PASSWORD])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', ['action' => 'LOGIN', 'user_id' => $user->id]);
    }

    public function test_wrong_password_is_refused_and_logged(): void
    {
        $user = $this->makeUser('Registrar');

        $this->post('/login', ['username' => $user->username, 'password' => 'wrong'])->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'LOGIN_FAILED', 'user_id' => $user->id]);
    }

    public function test_account_locks_after_five_failed_attempts(): void
    {
        $user = $this->makeUser('Cashier');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => $user->username, 'password' => 'wrong']);
        }

        $this->assertTrue($user->fresh()->locked_until->isFuture());

        // The correct password is refused while the lock is active.
        $this->post('/login', ['username' => $user->username, 'password' => self::PASSWORD])->assertSessionHasErrors('username');
        $this->assertGuest();

        // After 15 minutes the account works again.
        $this->travel(16)->minutes();
        $this->post('/login', ['username' => $user->username, 'password' => self::PASSWORD])->assertRedirect('/dashboard');
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $user = $this->makeUser('Faculty', ['status' => 'Inactive']);

        $this->post('/login', ['username' => $user->username, 'password' => self::PASSWORD])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_user_deactivated_while_logged_in_is_logged_out(): void
    {
        $user = $this->makeUser('Faculty');
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->update(['status' => 'Inactive']);

        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_session_id_changes_at_login(): void
    {
        $user = $this->makeUser('Registrar');
        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', ['username' => $user->username, 'password' => self::PASSWORD]);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_failed_login_of_unknown_user_is_logged_without_a_user(): void
    {
        $this->post('/login', ['username' => 'nobody', 'password' => 'x']);

        $this->assertSame(1, AuditLog::where('action', 'LOGIN_FAILED')->whereNull('user_id')->count());
    }
}
