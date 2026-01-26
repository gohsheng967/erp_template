<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\MfaQrService;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Facades\Hash;

class MFASetupController extends Controller
{
    public function showSetup(Request $request)
    {
        $user = $request->user();
        $google2fa = new Google2FA();

        // Generate secret if missing
        if (!$user->google2fa_secret) {

            // Generate base32 secret (correct format)
            $secret = $google2fa->generateSecretKey();

            $user->google2fa_secret = $secret;

            // One-time backup code (hash)
            $backupPlain = strtoupper(Str::random(10));
            $user->mfa_backup_code = Hash::make($backupPlain);

            // keep original for showing
            $user->backup_plain = $backupPlain;

            $user->save();
        } else {
            // Mask hashed backup code when re-loading
            $user->backup_plain = '**********';
        }

        // Generate QR using GD
        $qr = MfaQrService::generate(
            $user->google2fa_secret,
            'infinite'
        );

        return Inertia::render('Auth/MFA/Setup', [
            'qrImage' => $qr,
            'secret'  => $user->google2fa_secret,
            'backupCode' => $user->backup_plain
        ]);

    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string'
        ]);

        $user = $request->user();
        $google2fa = new Google2FA();
        $rawCode = trim($request->code);
        $totpCode = preg_replace('/\D/', '', $rawCode);
        $backupCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawCode));

        // 1. Google Authenticator TOTP
        if ($totpCode !== '' && $google2fa->verifyKey($user->google2fa_secret, $totpCode)) {

            $user->mfa_enabled = true;
            $user->save();

            session(['mfa_passed' => true]);

            return redirect()->route('dashboard');
        }


        // 2. Backup Code
        if ($backupCode !== '' && $user->mfa_backup_code && Hash::check($backupCode, $user->mfa_backup_code)) {

            // one time
            $user->mfa_backup_code = null;
            $user->save();

            session(['mfa_passed' => true]);
            return redirect()->route('dashboard');
        }

        return back()->withErrors(['code' => 'Invalid MFA code']);
    }
}
