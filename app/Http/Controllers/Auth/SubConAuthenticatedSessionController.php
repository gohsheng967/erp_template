<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SubCon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class SubConAuthenticatedSessionController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->session()->has('sub_con_auth_id')) {
            return redirect()->route('sub-con.password.change');
        }

        return Inertia::render('Auth/PartnerLogin', [
            'defaultRole' => 'sub_con',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identity_no' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $subCon = SubCon::query()
            ->where('login_identity_no', $validated['identity_no'])
            ->first();

        $valid = $subCon
            && $subCon->login_password
            && (int) $subCon->login_status === 1
            && Hash::check($validated['password'], $subCon->login_password);

        if (!$valid) {
            return back()->withErrors([
                'identity_no' => 'Invalid login credentials.',
            ])->onlyInput('identity_no');
        }

        $request->session()->regenerate();
        $request->session()->put('sub_con_auth_id', $subCon->id);

        if ($subCon->login_must_change_password) {
            return redirect()->route('sub-con.password.change');
        }

        return redirect()->route('sub-con.portal');
    }

    public function showChangePassword(Request $request): Response|RedirectResponse
    {
        $subCon = $this->authenticatedSubCon($request);

        if (!$subCon->login_must_change_password) {
            return redirect()->route('sub-con.portal');
        }

        return Inertia::render('Auth/SubConForcePasswordChange', [
            'subCon' => [
                'name' => $subCon->name,
                'identity_no' => $subCon->login_identity_no,
            ],
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $subCon = $this->authenticatedSubCon($request);

        if (!$subCon->login_must_change_password) {
            return redirect()->route('sub-con.portal');
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed', 'different:current_password'],
        ]);

        if (!$subCon->login_password || !Hash::check($validated['current_password'], $subCon->login_password)) {
            return back()->withErrors([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        $subCon->login_password = Hash::make($validated['password']);
        $subCon->login_must_change_password = false;
        $subCon->save();

        return redirect()->route('sub-con.portal')->with('success', 'Password changed successfully.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('sub_con_auth_id');
        $request->session()->regenerateToken();

        return redirect()->route('sub-con.login');
    }

    private function authenticatedSubCon(Request $request): SubCon
    {
        $subConId = (int) $request->session()->get('sub_con_auth_id');

        return SubCon::query()->findOrFail($subConId);
    }
}
