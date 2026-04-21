<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\VaultAudit;
use App\Modules\User\Models\VaultAttachment;
use App\Modules\User\Models\VaultClient;
use App\Modules\User\Models\VaultCredential;
use App\Modules\User\Services\WorkspaceEncryption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Owner-only export of the entire vault as a passphrase-encrypted JSON file.
 *
 * The exported envelope is { v, kdf, salt, iv, ct, tag } — AES-256-GCM
 * ciphertext over the plaintext JSON, with the key derived from the
 * passphrase via PBKDF2-SHA256 (200 000 iterations). The passphrase is
 * never stored on the server.
 */
class VaultExportController extends Controller
{
    public function show()
    {
        return view('user.vault.export.show');
    }

    public function download(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'passphrase' => ['required', 'string', 'min:8', 'max:200'],
        ]);

        $ws = app('current_workspace');
        $payload = $this->collectPayload();
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $envelope = $this->encryptWithPassphrase($json, $data['passphrase']);

        VaultAudit::record('export', 'vault', $ws->id, 'workspace export');

        $filename = 'vault-' . $ws->id . '-' . now()->format('Ymd-His') . '.enc.json';
        $body = json_encode($envelope, JSON_UNESCAPED_SLASHES);

        return response()->streamDownload(function () use ($body) {
            echo $body;
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    protected function collectPayload(): array
    {
        $credentials = VaultCredential::query()->get()->map(function ($c) {
            return [
                'id'            => $c->id,
                'label'         => $c->label,
                'url'           => $c->url,
                'username'      => $c->username,
                'password'      => $c->getEncrypted('password'),
                'notes'         => $c->getEncrypted('notes'),
                'custom_fields' => $c->getEncrypted('custom_fields', true),
                'tags'          => $c->tags,
                'visibility'    => $c->visibility,
                'created_at'    => optional($c->created_at)->toIso8601String(),
                'updated_at'    => optional($c->updated_at)->toIso8601String(),
            ];
        });

        $clients = VaultClient::with(['emails', 'phones', 'addresses'])->get()->map(function ($c) {
            return [
                'id'             => $c->id,
                'name'           => $c->name,
                'company'        => $c->company,
                'website'        => $c->website,
                'emails'         => $c->emails->map(fn ($e) => ['email' => $e->email, 'label' => $e->label, 'is_primary' => $e->is_primary]),
                'phones'         => $c->phones->map(fn ($p) => ['phone' => $p->phone, 'label' => $p->label, 'is_primary' => $p->is_primary]),
                'addresses'      => $c->addresses->map(fn ($a) => $a->only(['label','line1','line2','city','region','postal_code','country'])),
                'fields'         => $c->getEncrypted('fields', true),
                'social_handles' => $c->getEncrypted('social_handles', true),
                'notes'          => $c->getEncrypted('notes'),
                'tags'           => $c->tags,
                'visibility'     => $c->visibility,
            ];
        });

        return [
            'workspace_id' => app('current_workspace')->id,
            'exported_at'  => now()->toIso8601String(),
            'credentials'  => $credentials,
            'clients'      => $clients,
            'attachments'  => $this->collectAttachments(),
        ];
    }

    /**
     * Decrypt every attachment under the active workspace and emit it inline
     * as base64 plaintext. The whole payload is then re-encrypted under the
     * passphrase envelope, so the export is "complete" but never written to
     * disk in plaintext.
     */
    protected function collectAttachments(): array
    {
        $svc = app(WorkspaceEncryption::class);
        $out = [];
        foreach (VaultAttachment::query()->get() as $att) {
            $bytes = null;
            if (Storage::disk($att->disk)->exists($att->path)) {
                $raw = Storage::disk($att->disk)->get($att->path);
                if ($att->encrypted) {
                    try { $raw = $svc->decrypt((int) $att->workspace_id, $raw); }
                    catch (\Throwable $e) { $raw = null; }
                }
                $bytes = $raw === null ? null : base64_encode($raw);
            }
            $out[] = [
                'id'          => $att->id,
                'parent_type' => $att->parent_type,
                'parent_id'   => $att->parent_id,
                'filename'    => $att->filename,
                'mime'        => $att->mime,
                'size'        => $att->size,
                'content_b64' => $bytes,
            ];
        }
        return $out;
    }

    protected function encryptWithPassphrase(string $plaintext, string $passphrase): array
    {
        $salt = random_bytes(16);
        $iv = random_bytes(12);
        $iters = 200000;
        $key = hash_pbkdf2('sha256', $passphrase, $salt, $iters, 32, true);
        $tag = '';
        $ct = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) {
            abort(500, 'Vault export encryption failed.');
        }
        return [
            'v'    => 1,
            'kdf'  => ['algo' => 'pbkdf2-sha256', 'iters' => $iters, 'salt' => base64_encode($salt)],
            'iv'   => base64_encode($iv),
            'ct'   => base64_encode($ct),
            'tag'  => base64_encode($tag),
        ];
    }
}
