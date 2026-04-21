<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\VaultAttachment;
use App\Modules\User\Models\VaultAudit;
use App\Modules\User\Models\VaultClient;
use App\Modules\User\Models\VaultClientAddress;
use App\Modules\User\Models\VaultClientEmail;
use App\Modules\User\Models\VaultClientPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VaultClientController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $query = VaultClient::query()->orderBy('name');

        if ($q !== '') {
            $needle = '%' . str_replace('%', '\%', $q) . '%';
            $query->where(function ($w) use ($needle) {
                $w->where('name', 'like', $needle)
                  ->orWhere('company', 'like', $needle)
                  ->orWhere('primary_email', 'like', $needle)
                  ->orWhere('primary_phone', 'like', $needle);
            });
            if (preg_match('/^[A-Za-z0-9_-]{1,40}$/', $q)) {
                $query->orWhereJsonContains('tags', $q);
            }
        }

        $items = $query->get()->filter(fn ($c) => $c->visibleTo($request->user(), app('current_workspace')))->values();

        return view('user.vault.clients.index', compact('items', 'q'));
    }

    public function create()
    {
        $this->authorizeAction('vault.create');
        return view('user.vault.clients.create');
    }

    public function store(Request $request)
    {
        $this->authorizeAction('vault.create');
        $data = $this->validateClient($request);

        $client = DB::transaction(function () use ($data, $request) {
            $client = new VaultClient();
            $client->fill($this->basicAttrs($data));
            $client->setEncrypted('notes', $data['notes'] ?? null);
            $client->setEncrypted('fields', $this->parseKv($data['fields'] ?? null));
            $client->save();
            $this->syncMultiValues($client, $request);
            $this->refreshPrimaries($client);
            return $client;
        });

        VaultAudit::record('create', 'client', $client->id, $client->name);

        return redirect()->route('user.vault.clients.show', $client)->with('status', 'Client saved.');
    }

    public function show(Request $request, VaultClient $client)
    {
        $this->ensureVisible($request, $client);
        $client->load(['emails', 'phones', 'addresses', 'attachments']);
        return view('user.vault.clients.show', ['item' => $client]);
    }

    public function edit(Request $request, VaultClient $client)
    {
        $this->authorizeAction('vault.edit');
        $this->ensureVisible($request, $client);
        $client->load(['emails', 'phones', 'addresses']);
        return view('user.vault.clients.edit', ['item' => $client]);
    }

    public function update(Request $request, VaultClient $client)
    {
        $this->authorizeAction('vault.edit');
        $this->ensureVisible($request, $client);
        $data = $this->validateClient($request);

        DB::transaction(function () use ($client, $data, $request) {
            $client->fill($this->basicAttrs($data));
            $client->setEncrypted('notes', $data['notes'] ?? null);
            $client->setEncrypted('fields', $this->parseKv($data['fields'] ?? null));
            $client->save();
            // Replace child rows so the form is the source of truth.
            $client->emails()->delete();
            $client->phones()->delete();
            $client->addresses()->delete();
            $this->syncMultiValues($client, $request);
            $this->refreshPrimaries($client);
        });

        VaultAudit::record('update', 'client', $client->id, $client->name);

        return redirect()->route('user.vault.clients.show', $client)->with('status', 'Client updated.');
    }

    public function destroy(Request $request, VaultClient $client)
    {
        $this->authorizeAction('vault.delete');
        $this->ensureVisible($request, $client);

        $label = $client->name;
        $id = $client->id;

        DB::transaction(function () use ($client) {
            // Cascade attachment files so the disk doesn't accumulate orphans.
            foreach ($client->attachments()->get() as $att) {
                Storage::disk($att->disk)->delete($att->path);
                $att->delete();
            }
            $client->emails()->delete();
            $client->phones()->delete();
            $client->addresses()->delete();
            $client->delete();
        });

        VaultAudit::record('delete', 'client', $id, $label);

        return redirect()->route('user.vault.clients.index')->with('status', 'Client deleted.');
    }

    /** Reveal the encrypted notes blob — audited like credential reveals. */
    public function revealNotes(Request $request, VaultClient $client)
    {
        $this->ensureVisible($request, $client);
        $notes = $client->getEncrypted('notes');
        VaultAudit::record('reveal', 'client', $client->id, $client->name);
        if ($notes === null) {
            return response()->json(['error' => 'could_not_decrypt'], 422);
        }
        return response()->json(['notes' => $notes]);
    }

    protected function basicAttrs(array $data): array
    {
        return [
            'name'       => $data['name'],
            'company'    => $data['company'] ?? null,
            'website'    => $data['website'] ?? null,
            'visibility' => $data['visibility'] ?? 'shared',
            'tags'       => $this->parseTags($data['tags'] ?? null),
        ];
    }

    protected function validateClient(Request $request): array
    {
        return $request->validate([
            'name'       => ['required', 'string', 'max:200'],
            'company'    => ['nullable', 'string', 'max:200'],
            'website'    => ['nullable', 'string', 'max:500'],
            'notes'      => ['nullable', 'string', 'max:20000'],
            'visibility' => ['nullable', 'in:shared,private'],
            'tags'       => ['nullable', 'string', 'max:500'],
            'emails'     => ['nullable', 'array'],
            'emails.*.email' => ['nullable', 'string', 'max:255'],
            'emails.*.label' => ['nullable', 'string', 'max:32'],
            'phones'     => ['nullable', 'array'],
            'phones.*.phone' => ['nullable', 'string', 'max:64'],
            'phones.*.label' => ['nullable', 'string', 'max:32'],
            'addresses'  => ['nullable', 'array'],
            'fields'     => ['nullable', 'array'],
        ]);
    }

    protected function syncMultiValues(VaultClient $client, Request $request): void
    {
        foreach ((array) $request->input('emails', []) as $i => $row) {
            $email = trim((string) ($row['email'] ?? ''));
            if ($email === '') continue;
            VaultClientEmail::create([
                'client_id' => $client->id,
                'email'     => $email,
                'label'     => $row['label'] ?? null,
                'is_primary'=> $i === 0,
            ]);
        }
        foreach ((array) $request->input('phones', []) as $i => $row) {
            $phone = trim((string) ($row['phone'] ?? ''));
            if ($phone === '') continue;
            VaultClientPhone::create([
                'client_id' => $client->id,
                'phone'     => $phone,
                'label'     => $row['label'] ?? null,
                'is_primary'=> $i === 0,
            ]);
        }
        foreach ((array) $request->input('addresses', []) as $row) {
            $line1 = trim((string) ($row['line1'] ?? ''));
            $city  = trim((string) ($row['city'] ?? ''));
            if ($line1 === '' && $city === '') continue;
            VaultClientAddress::create([
                'client_id'  => $client->id,
                'label'      => $row['label'] ?? null,
                'line1'      => $row['line1'] ?? null,
                'line2'      => $row['line2'] ?? null,
                'city'       => $row['city'] ?? null,
                'region'     => $row['region'] ?? null,
                'postal_code'=> $row['postal_code'] ?? null,
                'country'    => $row['country'] ?? null,
            ]);
        }
    }

    protected function refreshPrimaries(VaultClient $client): void
    {
        $client->primary_email = optional($client->emails()->where('is_primary', true)->first())->email;
        $client->primary_phone = optional($client->phones()->where('is_primary', true)->first())->phone;
        $client->saveQuietly();
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

    protected function ensureVisible(Request $request, VaultClient $client): void
    {
        if (!$client->visibleTo($request->user(), app('current_workspace'))) {
            abort(404);
        }
    }
}
