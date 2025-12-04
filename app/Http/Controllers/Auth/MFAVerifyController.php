<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MFAVerifyController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Auth/MFA/Verify');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = $request->user();
        $google2fa = new Google2FA();

        // 1 — Google Authenticator TOTP
        if ($google2fa->verifyKey($user->google2fa_secret, $request->code)) {
            session(['mfa_passed' => true]);
            return redirect()->route('dashboard');
        }

        // 2 — Backup Code
        if ($user->mfa_backup_code && Hash::check($request->code, $user->mfa_backup_code)) {

            $user->mfa_backup_code = null;

            $newPlain = strtoupper(Str::random(10));
            $user->mfa_backup_code = Hash::make($newPlain);

            $user->save();

            session(['mfa_passed' => true]);
            return redirect()->route('dashboard');
        }

        return back()->withErrors([
            'code' => 'Invalid verification code.',
        ]);
    }
}
