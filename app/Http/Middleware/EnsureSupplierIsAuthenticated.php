<?php

namespace App\Http\Middleware;

use App\Models\Supplier;
use Closure;
use Illuminate\Http\Request;

class EnsureSupplierIsAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->session()->has('supplier_auth_id')) {
            return redirect()->route('supplier.login');
        }

        $supplierId = (int) $request->session()->get('supplier_auth_id');
        $supplier = Supplier::query()->find($supplierId);

        if (!$supplier || (int) $supplier->login_status !== 1) {
            $request->session()->forget('supplier_auth_id');
            return redirect()->route('supplier.login');
        }

        $routeName = $request->route()?->getName();
        $allowedWhileForced = [
            'supplier.password.change',
            'supplier.password.update',
            'supplier.logout',
        ];

        if ($supplier->login_must_change_password && !in_array($routeName, $allowedWhileForced, true)) {
            return redirect()->route('supplier.password.change');
        }

        return $next($request);
    }
}

