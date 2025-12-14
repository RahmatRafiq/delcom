<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class TokenEncryptionService
{
    /**
     * Encrypt a value.
     */
    public function encrypt(string $value): string
    {
        return Crypt::encryptString($value);
    }

    /**
     * Decrypt a value.
     */
    public function decrypt(string $value): ?string
    {
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            report($e);
            return null;
        }
    }
}
