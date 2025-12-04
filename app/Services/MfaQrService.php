<?php

namespace App\Services;

class MfaQrService
{
    public static function generate(string $secret, string $identityNo)
    {
        $issuer = "InfiniteERP";

        $otpauth = "otpauth://totp/{$issuer}:{$identityNo}"
                 . "?secret={$secret}"
                 . "&issuer={$issuer}";

        // Encode the otpauth URI
        $encoded = urlencode($otpauth);

        // Generate QR via QRServer API
        return "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={$encoded}";
    }
}
