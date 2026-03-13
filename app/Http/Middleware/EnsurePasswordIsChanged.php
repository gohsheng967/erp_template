<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $routeName = $request->route()?->getName();
        $allowedWhileForced = [
            'force-password.change',
            'force-password.update',
            'logout',
        ];

        if ($user->must_change_password && !in_array($routeName, $allowedWhileForced, true)) {
            return redirect()->route('force-password.change');
        }

        return $next($request);
    }
}
