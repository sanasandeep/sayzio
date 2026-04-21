<?php

namespace App\Modules\User\Services;

use Illuminate\Encryption\Encrypter;
use RuntimeException;

/**
 * Per-workspace symmetric encryption.
 *
 * The base secret is the application's encryption key (the same key used by
 * Laravel's Crypt facade). For each workspace we derive a distinct 32-byte
 * AES-256 key via HKDF-SHA256 with `workspace:<id>` as the info string, so
 * ciphertext written for one workspace cannot be decrypted with another
 * workspace's derived key even though they share an APP_KEY.
 *
 * Keys are cached per workspace id for the request lifetime.
 */
class WorkspaceEncryption
{
    /** @var array<int, Encrypter> */
    protected array $cache = [];

    public function encrypt(int $workspaceId, string $plaintext): string
    {
        return $this->encrypterFor($workspaceId)->encryptString($plaintext);
    }

    public function decrypt(int $workspaceId, string $ciphertext): string
    {
        return $this->encrypterFor($workspaceId)->decryptString($ciphertext);
    }

    protected function encrypterFor(int $workspaceId): Encrypter
    {
        if ($workspaceId <= 0) {
            throw new RuntimeException('WorkspaceEncryption requires a positive workspace id.');
        }
        if (isset($this->cache[$workspaceId])) {
            return $this->cache[$workspaceId];
        }
        // Workspaces in this codebase are keyed by an immutable bigint; HKDF
        // info just needs to be a unique, stable per-tenant byte string, so
        // 'workspace:<id>' serves the same isolation purpose as a UUID would.
        $key = hash_hkdf('sha256', $this->baseSecret(), 32, 'workspace:' . $workspaceId);
        return $this->cache[$workspaceId] = new Encrypter($key, 'aes-256-cbc');
    }

    protected function baseSecret(): string
    {
        $appKey = (string) config('app.key');
        if ($appKey === '') {
            throw new RuntimeException('APP_KEY is not configured.');
        }
        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);
            if ($decoded === false) {
                throw new RuntimeException('APP_KEY is not valid base64.');
            }
            return $decoded;
        }
        return $appKey;
    }
}
