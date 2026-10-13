<?php

namespace App\Providers;

use App\Models\AuditLog;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** Sign-in attempts allowed from one IP address per minute, across all usernames. */
    public const LOGIN_ATTEMPTS_PER_MINUTE = 10;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // The account lock stops guessing on one username; this stops one device from trying many usernames.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(self::LOGIN_ATTEMPTS_PER_MINUTE)
            ->by($request->ip())
            ->response(function (Request $request, array $headers) {
                AuditLog::record('LOGIN_THROTTLED', null, null, null, ['username' => (string) $request->input('username')]);

                return back()->withErrors(['username' => 'Too many sign-in attempts from this device. Wait one minute, then try again.'])->with('retry_seconds', max(1, (int) ($headers['Retry-After'] ?? 60)))->onlyInput('username', 'portal');
            }));
    }
}
