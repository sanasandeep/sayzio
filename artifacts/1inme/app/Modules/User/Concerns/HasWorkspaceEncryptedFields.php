<?php

namespace App\Modules\User\Concerns;

use App\Modules\User\Services\WorkspaceEncryption;

/**
 * Adds per-workspace encrypted "virtual" fields to a model.
 *
 * Implementing models declare a `$encryptedFields` map of `virtualName =>
 * dbColumn`. Plaintext writes are buffered until `saving` so the workspace
 * key is guaranteed to be available; reads decrypt on demand and quietly
 * return null if the ciphertext is unreadable (rotated APP_KEY, restored
 * backup with mismatched env, etc.) so the UI never leaks a stack trace.
 *
 * JSON values round-trip transparently: arrays in / arrays out.
 */
trait HasWorkspaceEncryptedFields
{
    /** Plaintext values waiting to be encrypted on the next save. */
    protected array $pendingEncrypted = [];

    public static function bootHasWorkspaceEncryptedFields(): void
    {
        static::saving(function ($model) {
            // Workspace context must exist before the encryption key can be
            // derived. Fall back to the bound active workspace if the
            // attribute hasn't been hydrated yet (matches BelongsToWorkspace).
            if (empty($model->workspace_id) && app()->bound('current_workspace')) {
                $ws = app('current_workspace');
                if ($ws) $model->workspace_id = $ws->id;
            }
            if (empty($model->workspace_id)) {
                throw new \RuntimeException(static::class . ' requires workspace_id before save.');
            }
            $svc = app(WorkspaceEncryption::class);
            $map = property_exists($model, 'encryptedFields') ? $model->encryptedFields : [];
            foreach ($model->pendingEncrypted as $field => $value) {
                $col = $map[$field] ?? null;
                if (!$col) continue;
                if ($value === null || $value === '') {
                    $model->attributes[$col] = null;
                    continue;
                }
                $payload = is_array($value) ? json_encode($value) : (string) $value;
                $model->attributes[$col] = $svc->encrypt((int) $model->workspace_id, $payload);
            }
            $model->pendingEncrypted = [];
        });
    }

    public function setEncrypted(string $field, $value): void
    {
        $this->pendingEncrypted[$field] = $value;
    }

    /**
     * Decrypt and return the named field. Returns null on any failure to
     * avoid leaking key/cipher details into views.
     */
    public function getEncrypted(string $field, bool $asJson = false)
    {
        if (array_key_exists($field, $this->pendingEncrypted)) {
            return $this->pendingEncrypted[$field];
        }
        $col = $this->encryptedFields[$field] ?? null;
        if (!$col) return null;
        $cipher = $this->attributes[$col] ?? null;
        if ($cipher === null || $cipher === '') return $asJson ? [] : null;
        try {
            $plain = app(WorkspaceEncryption::class)->decrypt((int) $this->workspace_id, $cipher);
        } catch (\Throwable $e) {
            return $asJson ? [] : null;
        }
        if ($asJson) {
            $decoded = json_decode($plain, true);
            return is_array($decoded) ? $decoded : [];
        }
        return $plain;
    }
}
