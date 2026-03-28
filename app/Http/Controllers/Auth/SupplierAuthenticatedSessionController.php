<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class SupplierAuthenticatedSessionController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->session()->has('supplier_auth_id')) {
            return redirect()->route('supplier.password.change');
        }

        return Inertia::render('Auth/PartnerLogin', [
            'defaultRole' => 'supplier',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identity_no' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $supplier = Supplier::query()
            ->where('login_identity_no', $validated['identity_no'])
            ->first();

        $valid = $supplier
            && $supplier->login_password
            && (int) $supplier->login_status === 1
            && Hash::check($validated['password'], $supplier->login_password);

        if (!$valid) {
            return back()->withErrors([
                'identity_no' => 'Invalid login credentials.',
            ])->onlyInput('identity_no');
        }

        $request->session()->regenerate();
        $request->session()->put('supplier_auth_id', $supplier->id);

        if ($supplier->login_must_change_password) {
            return redirect()->route('supplier.password.change');
        }

        return redirect()->route('supplier.portal');
    }

    public function showChangePassword(Request $request): Response|RedirectResponse
    {
        $supplier = $this->authenticatedSupplier($request);

        if (!$supplier->login_must_change_password) {
            return redirect()->route('supplier.portal');
        }

        return Inertia::render('Auth/SupplierForcePasswordChange', [
            'supplier' => [
                'name' => $supplier->company_name,
                'identity_no' => $supplier->login_identity_no,
            ],
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $supplier = $this->authenticatedSupplier($request);

        if (!$supplier->login_must_change_password) {
            return redirect()->route('supplier.portal');
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed', 'different:current_password'],
        ]);

        if (!$supplier->login_password || !Hash::check($validated['current_password'], $supplier->login_password)) {
            return back()->withErrors([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        $supplier->login_password = Hash::make($validated['password']);
        $supplier->login_must_change_password = false;
        $supplier->save();

        return redirect()->route('supplier.portal')->with('success', 'Password changed successfully.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('supplier_auth_id');
        $request->session()->regenerateToken();

        return redirect()->route('supplier.login');
    }

    private function authenticatedSupplier(Request $request): Supplier
    {
        $supplierId = (int) $request->session()->get('supplier_auth_id');

        return Supplier::query()->findOrFail($supplierId);
    }
}
