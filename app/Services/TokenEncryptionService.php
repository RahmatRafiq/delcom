<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class TokenEncryptionService
{
    /**
     * Encrypt a value.
     *
     * @param  string  $value  Plain text to encrypt
     * @return string Encrypted value
     *
     * @throws RuntimeException If encryption fails
     */
    public function encrypt(string $value): string
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('Cannot encrypt empty value');
        }

        try {
            return Crypt::encryptString($value);
        } catch (\Exception $e) {
            report($e);
            throw new RuntimeException('Failed to encrypt value: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Decrypt a value.
     *
     * @param  string  $value  Encrypted value
     * @return string Decrypted plain text
     *
     * @throws RuntimeException If decryption fails
     */
    public function decrypt(string $value): string
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('Cannot decrypt empty value');
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            report($e);
            throw new RuntimeException('Failed to decrypt value. Token may be corrupted or invalid.', 0, $e);
        }
    }

    /**
     * Safely decrypt a value without throwing exceptions.
     *
     * @param  string  $value  Encrypted value
     * @return string|null Decrypted value or null if failed
     */
    public function safeDecrypt(string $value): ?string
    {
        try {
            return $this->decrypt($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if a value can be decrypted.
     *
     * @param  string  $value  Encrypted value to test
     * @return bool True if can decrypt, false otherwise
     */
    public function canDecrypt(string $value): bool
    {
        return $this->safeDecrypt($value) !== null;
    }
}
