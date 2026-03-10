<?php

namespace App\Http\Middleware;

use App\Models\SubCon;
use Closure;
use Illuminate\Http\Request;

class EnsureSubConIsAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->session()->has('sub_con_auth_id')) {
            return redirect()->route('sub-con.login');
        }

        $subConId = (int) $request->session()->get('sub_con_auth_id');
        $subCon = SubCon::query()->find($subConId);

        if (!$subCon || (int) $subCon->login_status !== 1) {
            $request->session()->forget('sub_con_auth_id');
            return redirect()->route('sub-con.login');
        }

        $routeName = $request->route()?->getName();
        $allowedWhileForced = [
            'sub-con.password.change',
            'sub-con.password.update',
            'sub-con.logout',
        ];

        if ($subCon->login_must_change_password && !in_array($routeName, $allowedWhileForced, true)) {
            return redirect()->route('sub-con.password.change');
        }

        return $next($request);
    }
}
