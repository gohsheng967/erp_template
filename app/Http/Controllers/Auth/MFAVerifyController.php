<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

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
        $rawCode = trim($request->code);
        $totpCode = preg_replace('/\D/', '', $rawCode);
        $backupCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawCode));

        if ($totpCode !== '' && $google2fa->verifyKey($user->google2fa_secret, $totpCode)) {
            session(['mfa_passed' => true]);
            return redirect()->route('dashboard');
        }

        if ($backupCode !== '' && $user->mfa_backup_code && Hash::check($backupCode, $user->mfa_backup_code)) {
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

