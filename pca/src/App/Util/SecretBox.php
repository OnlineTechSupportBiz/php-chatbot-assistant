<?php

/**
 * Copyright (c) 2026 Online Tech Support, LLC
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

declare(strict_types=1);

namespace App\Util;

/**
 * SecretBox — reversible encryption for the one secret the app has to be able
 * to *read back*: the TOTP seed behind multi-factor authentication.
 *
 * Port of the JS port's lib/secret-box.ts. Hashing is right for every other
 * credential (session ids, reset links, recovery codes) because nothing reads
 * them back — only compares. A TOTP seed is different: the server recomputes
 * codes from it on every challenge, so it cannot be hashed, but it can be kept
 * out of reach of a database dump: AES-256-GCM with a key that lives in
 * APP_ENCRYPTION_KEY (never in the database).
 *
 * Format: `v1:` + base64(iv | authTag | ciphertext). Anything without that
 * prefix is returned as-is, so a seed stored before this existed keeps working
 * and is upgraded the next time it is written.
 */
final class SecretBox
{
    private const PREFIX = 'v1:';
    private const IV_BYTES = 12;
    private const TAG_BYTES = 16;

    /** Derive the 32-byte key. Any input length is accepted (openssl rand -hex 32). */
    private static function encryptionKey(): ?string
    {
        $raw = env('APP_ENCRYPTION_KEY', '');
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        return hash('sha256', $raw, true);
    }

    /** True when a key is configured, i.e. new secrets will be encrypted. */
    public static function isSecretEncryptionEnabled(): bool
    {
        return self::encryptionKey() !== null;
    }

    /**
     * Encrypt a secret for storage. Without a configured key the value is
     * returned unchanged — the development fallback.
     */
    public static function encryptSecret(string $plaintext): string
    {
        $key = self::encryptionKey();
        if ($key === null) {
            return $plaintext;
        }
        $iv = random_bytes(self::IV_BYTES);
        $tag = '';
        $cipherText = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_BYTES
        );
        if ($cipherText === false) {
            throw new \RuntimeException('Secret encryption failed');
        }
        return self::PREFIX . base64_encode($iv . $tag . $cipherText);
    }

    /**
     * Decrypt a stored secret. Values without the `v1:` prefix are legacy
     * plaintext (or were written while no key was configured) and pass through
     * unchanged, so existing MFA enrollments keep working across this change.
     */
    public static function decryptSecret(string $stored): string
    {
        if (!str_starts_with($stored, self::PREFIX)) {
            return $stored;
        }
        $key = self::encryptionKey();
        if ($key === null) {
            throw new \RuntimeException(
                'APP_ENCRYPTION_KEY is required to read an encrypted secret (set it to the key ' .
                'that was configured when the secret was stored)'
            );
        }
        $payload = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($payload === false || strlen($payload) < self::IV_BYTES + self::TAG_BYTES) {
            throw new \RuntimeException('Encrypted secret is truncated');
        }
        $iv = substr($payload, 0, self::IV_BYTES);
        $tag = substr($payload, self::IV_BYTES, self::TAG_BYTES);
        $cipherText = substr($payload, self::IV_BYTES + self::TAG_BYTES);
        $plain = openssl_decrypt(
            $cipherText,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        if ($plain === false) {
            // GCM auth tag failed — a tampered value fails loudly instead of
            // silently producing a wrong seed.
            throw new \RuntimeException('Encrypted secret failed authentication');
        }
        return $plain;
    }
}