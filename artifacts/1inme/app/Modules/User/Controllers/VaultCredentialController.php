<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\VaultAttachment;
use App\Modules\User\Models\VaultAudit;
use App\Modules\User\Models\VaultCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VaultCredentialController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $query = VaultCredential::query()->orderByDesc('updated_at');

        if ($q !== '') {
            // Encrypted columns are intentionally excluded from search. The
            // tag predicate must stay inside the same grouped `where` so the
            // workspace global scope's WHERE remains conjunctive — without
            // the grouping a top-level orWhereJsonContains would leak rows
            // from other workspaces.
            $needle = '%' . str_replace('%', '\%', $q) . '%';
            $tagSafe = preg_match('/^[A-Za-z0-9_-]{1,40}$/', $q) ? $q : null;
            $query->where(function ($w) use ($needle, $tagSafe) {
                $w->where('label', 'like', $needle)
                  ->orWhere('username', 'like', $needle)
                  ->orWhere('url', 'like', $needle);
                if ($tagSafe !== null) {
                    $w->orWhereJsonContains('tags', $tagSafe);
                }
            });
        }

        $items = $query->get()->filter(fn ($c) => $c->visibleTo($request->user(), app('current_workspace')))->values();
        $audits = $this->recentAudits($request);

        return view('user.vault.credentials.index', compact('items', 'q', 'audits'));
    }

    public function create()
    {
        $this->authorizeAction('vault.create');
        return view('user.vault.credentials.create');
    }

    public function store(Request $request)
    {
        $this->authorizeAction('vault.create');
        $data = $this->validateCredential($request);

        $cred = new VaultCredential();
        $cred->fill([
            'label'      => $data['label'],
            'url'        => $data['url'] ?? null,
            'username'   => $data['username'] ?? null,
            'visibility' => $data['visibility'] ?? 'private',
            'tags'       => $this->parseTags($data['tags'] ?? null),
        ]);
        $cred->setEncrypted('password', $data['password'] ?? null);
        $cred->setEncrypted('authenticator',$this->authenticatorUri($data['authenticator']??null,$cred));
        $cred->setEncrypted('notes', $data['notes'] ?? null);
        $cred->setEncrypted('custom_fields', $this->parseKv($data['custom_fields'] ?? null));
        $cred->save();

        VaultAudit::record('create', 'credential', $cred->id, $cred->label);

        return redirect()->route('user.vault.credentials.show', $cred)->with('status', 'Credential saved.');
    }

    public function show(Request $request, VaultCredential $credential)
    {
        $this->ensureVisible($request, $credential);
        VaultAudit::record('open','credential',$credential->id,$credential->label);
        return response()->view('user.vault.credentials.show', ['item' => $credential])->header('Cache-Control','no-store');
    }

    public function edit(Request $request, VaultCredential $credential)
    {
        $this->authorizeAction('vault.edit');
        $this->ensureVisible($request, $credential);
        return view('user.vault.credentials.edit', ['item' => $credential]);
    }

    public function update(Request $request, VaultCredential $credential)
    {
        $this->authorizeAction('vault.edit');
        $this->ensureVisible($request, $credential);
        $data = $this->validateCredential($request);

        $credential->fill([
            'label'      => $data['label'],
            'url'        => $data['url'] ?? null,
            'username'   => $data['username'] ?? null,
            'visibility' => $data['visibility'] ?? $credential->visibility,
            'tags'       => $this->parseTags($data['tags'] ?? null),
        ]);
        // Empty string in the password field means "leave existing".
        if (array_key_exists('password', $data) && $data['password'] !== null && $data['password'] !== '') {
            $credential->setEncrypted('password', $data['password']);
        }
        if($request->boolean('remove_authenticator')) $credential->setEncrypted('authenticator',null);
        elseif(!empty($data['authenticator'])) $credential->setEncrypted('authenticator',$this->authenticatorUri($data['authenticator'],$credential));
        $credential->setEncrypted('notes', $data['notes'] ?? null);
        $credential->setEncrypted('custom_fields', $this->parseKv($data['custom_fields'] ?? null));
        $credential->save();

        VaultAudit::record('update', 'credential', $credential->id, $credential->label);

        return redirect()->route('user.vault.credentials.show', $credential)->with('status', 'Credential updated.');
    }

    public function destroy(Request $request, VaultCredential $credential)
    {
        $this->authorizeAction('vault.delete');
        $this->ensureVisible($request, $credential);

        $label = $credential->label;
        $id = $credential->id;

        DB::transaction(function () use ($credential, $id) {
            // Cascade attachment files so deleted secrets don't leave readable blobs on disk.
            $attachments = VaultAttachment::where('parent_type', 'credential')->where('parent_id', $id)->get();
            foreach ($attachments as $att) {
                Storage::disk($att->disk)->delete($att->path);
                $att->delete();
            }
            $credential->delete();
        });

        VaultAudit::record('delete', 'credential', $id, $label);

        return redirect()->route('user.vault.credentials.index')->with('status', 'Credential deleted.');
    }

    /** Reveal all encrypted fields (password, notes, custom_fields) — writes a single audit row. */
    public function reveal(Request $request, VaultCredential $credential)
    {
        $this->ensureVisible($request, $credential);

        $authenticator=$credential->getEncrypted('authenticator');
        $password = $credential->getEncrypted('password');
        $notes = $credential->getEncrypted('notes');
        $custom = $credential->getEncrypted('custom_fields', true) ?? [];
        VaultAudit::record('reveal', 'credential', $credential->id, $credential->label);

        if ($password === null && $notes === null && empty($custom) && !$authenticator) {
            return response()->json(['error' => 'could_not_decrypt'], 422);
        }
        return response()->json([
            'authenticator_qr'=>$authenticator ? 'data:image/svg+xml;base64,'.base64_encode(app(\App\Modules\User\Services\TotpService::class)->qrSvg($authenticator)) : null,
            'authenticator_uri'=>$authenticator,
            'password'      => $password,
            'notes'         => $notes,
            'custom_fields' => $custom,
        ])->header('Cache-Control','no-store');
    }

    private function authenticatorUri(?string $input,VaultCredential $credential): ?string
    {
        if(!$input)return null;
        $input=trim($input);
        if(preg_match('/^[A-Z2-7]{16,128}$/i',$input)) return app(\App\Modules\User\Services\TotpService::class)->provisioningUri(strtoupper($input),$credential->username?:$credential->label,$credential->label);
        $parts = parse_url($input);
        parse_str($parts['query'] ?? '', $parameters);
        $valid = is_array($parts) && ($parts['scheme'] ?? '') === 'otpauth'
            && ($parts['host'] ?? '') === 'totp' && !empty($parts['path'])
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['fragment']) && !isset($parts['port'])
            && count(array_filter($parameters, 'is_array')) === 0
            && preg_match('/^[A-Z2-7]{16,128}$/i', $parameters['secret'] ?? '')
            && in_array(strtoupper($parameters['algorithm'] ?? 'SHA1'), ['SHA1', 'SHA256', 'SHA512'], true)
            && in_array((string) ($parameters['digits'] ?? '6'), ['6', '8'], true)
            && preg_match('/^[0-9]+$/', (string) ($parameters['period'] ?? 30))
            && (int) ($parameters['period'] ?? 30) >= 15 && (int) ($parameters['period'] ?? 30) <= 120;
        if (!$valid) throw \Illuminate\Validation\ValidationException::withMessages(['authenticator' => 'Enter a valid TOTP setup URI or Base32 key.']);
        return 'otpauth://totp'.$parts['path'].'?'.http_build_query(array_intersect_key($parameters, array_flip(['secret', 'issuer', 'algorithm', 'digits', 'period'])), '', '&', PHP_QUERY_RFC3986);
    }

    protected function validateCredential(Request $request): array
    {
        return $request->validate([
            'label'         => ['required', 'string', 'max:160'],
            'url'           => ['nullable', 'string', 'max:500'],
            'username'      => ['nullable', 'string', 'max:240'],
            'password'      => ['nullable', 'string', 'max:2000'],
            'authenticator'=>['nullable','string','max:2000'],
            'remove_authenticator'=>['nullable','boolean'],
            'notes'         => ['nullable', 'string', 'max:20000'],
            'visibility'    => ['nullable', 'in:shared,private'],
            'tags'          => ['nullable', 'string', 'max:500'],
            'custom_fields' => ['nullable', 'array'],
        ]);
    }

    protected function parseTags($raw): array
    {
        if (is_array($raw)) return array_values(array_filter(array_map('trim', $raw)));
        if (!is_string($raw) || $raw === '') return [];
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    protected function parseKv($raw): array
    {
        if (!is_array($raw)) return [];
        $out = [];
        foreach ($raw as $row) {
            $k = trim((string) ($row['key'] ?? ''));
            $v = (string) ($row['value'] ?? '');
            if ($k !== '') $out[] = ['key' => $k, 'value' => $v];
        }
        return $out;
    }

    protected function authorizeAction(string $perm): void
    {
        $user = auth()->user();
        $ws = app('current_workspace');
        if (!$user || !$ws || !$user->canInWorkspace($ws, $perm)) {
            abort(403);
        }
    }

    protected function ensureVisible(Request $request, VaultCredential $credential): void
    {
        if (!$credential->visibleTo($request->user(), app('current_workspace'))) {
            abort(404);
        }
    }

    protected function recentAudits(Request $request)
    {
        $user = $request->user();
        $ws = app('current_workspace');
        $isAdmin = $user->hasPermission('user.vault.access_any')
            || (int) $ws->owner_user_id === (int) $user->id
            || $user->canInWorkspace($ws, 'vault.delete');
        $q = VaultAudit::query()->orderByDesc('occurred_at')->limit(50);
        if (!$isAdmin) {
            $q->where('actor_user_id', $user->id);
        }
        return $q->get();
    }
}
