<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class MessageEncryptionService
{
    private string $key;
    private string $cipher = 'AES-256-CBC';

    public function __construct()
    {
        $key = config('app.cipher_key') ?? config('app.key');

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        $this->key = $key;
    }

    /**
     * Verschlüsselt einen Text
     */
    public function encrypt(string $plaintext): array
    {
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = random_bytes($ivLength);

        $encrypted = openssl_encrypt(
            $plaintext,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            Log::error('Encryption failed');
            throw new \RuntimeException('Encryption failed');
        }

        return [
            'encrypted' => base64_encode($encrypted),
            'iv' => base64_encode($iv),
        ];
    }

    /**
     * Entschlüsselt einen Text
     */
    public function decrypt(string $encryptedBase64, string $ivBase64): string
    {
        $encrypted = base64_decode($encryptedBase64);
        $iv = base64_decode($ivBase64);

        $decrypted = openssl_decrypt(
            $encrypted,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            Log::error('Decryption failed');
            throw new \RuntimeException('Decryption failed');
        }

        return $decrypted;
    }

    /**
     * Verschlüsselt für DB-Speicherung und gibt formatierte Daten zurück
     */
    public function encryptForStorage(string $content): array
    {
        $result = $this->encrypt($content);

        return [
            'content' => $result['encrypted'],
            'content_iv' => $result['iv'],
        ];
    }

    /**
     * Entschlüsselt aus DB und gibt Klartext zurück
     */
    public function decryptFromStorage(string $content, ?string $contentIv): string
    {
        if (empty($contentIv)) {
            // Unverschlüsselte Nachricht (Legacy oder Fehler)
            return $content;
        }

        return $this->decrypt($content, $contentIv);
    }
}
