<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChangeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // A staff member who was given a temporary password (new teacher login, reset by an admin)
        // must choose their own before using anything else. Pupils have their own version of this
        // gate (FirstLoginPasswordChange on the pupil area).
        if ($user && $user->role === 'admin' && $user->first_login && !$request->expectsJson()
            && !$request->is('password/change', 'password/update', 'logout')) {
            return redirect()->route('password.change')->with('warning', 'Please choose your own password before you continue.');
        }

        return $next($request);
    }
}
