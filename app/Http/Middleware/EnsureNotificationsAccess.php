<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotificationsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->canAny(['correos.notifications.manage', 'workshops.enrollments.email'])) {
            abort(403);
        }

        return $next($request);
    }
}
