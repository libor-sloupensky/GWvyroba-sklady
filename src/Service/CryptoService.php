<?php
declare(strict_types=1);

namespace App\Service;

final class CryptoService
{
    private const METHOD = 'aes-256-cbc';

    private static function key(): string
    {
        static $key;
        if ($key !== null) {
            return $key;
        }
        $cfg = include __DIR__ . '/../../config/config.php';
        $raw = (string)($cfg['encryption_key'] ?? '');
        if ($raw === '') {
            throw new \RuntimeException('encryption_key is not configured.');
        }
        // Derive a 32-byte key using SHA-256
        $key = hash('sha256', $raw, true);
        return $key;
    }

    public static function encrypt(string $plaintext): string
    {
        $ivLen = openssl_cipher_iv_length(self::METHOD);
        $iv = openssl_random_pseudo_bytes($ivLen);
        $cipher = openssl_encrypt($plaintext, self::METHOD, self::key(), OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new \RuntimeException('Encryption failed.');
        }
        return base64_encode($iv . $cipher);
    }

    public static function decrypt(string $encoded): string
    {
        $data = base64_decode($encoded, true);
        if ($data === false) {
            throw new \RuntimeException('Invalid encrypted data (base64).');
        }
        $ivLen = openssl_cipher_iv_length(self::METHOD);
        if (strlen($data) < $ivLen) {
            throw new \RuntimeException('Invalid encrypted data (too short).');
        }
        $iv = substr($data, 0, $ivLen);
        $cipher = substr($data, $ivLen);
        $plain = openssl_decrypt($cipher, self::METHOD, self::key(), OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new \RuntimeException('Decryption failed.');
        }
        return $plain;
    }
}
