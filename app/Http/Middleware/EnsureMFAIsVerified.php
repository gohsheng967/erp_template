<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureMFAIsVerified
{
    public function handle(Request $request, Closure $next)
    {
        // If user is not logged in, let 'auth' middleware handle it
        if (!$request->user()) {
            return redirect()->route('login');
        }

        // If MFA is not passed in this session, redirect to MFA verify page
        if (!session('mfa_passed')) {
            return redirect()->route('mfa.verify');
        }

        return $next($request);
    }
}
