<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /** Allow the request only when the logged-in user has one of the given roles. */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole(...$roles)) {
            AuditLog::record('ACCESS_DENIED', null, null, null, ['url' => $request->path()]);
            abort(403, 'You do not have permission to view this page.');
        }

        return $next($request);
    }
}
