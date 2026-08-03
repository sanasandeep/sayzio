<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\IntegrationConfig;
use App\Modules\User\Support\IntegrationConfigRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IntegrationConfigController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        // Account-level, not workspace-level: show all of the user's configs
        // regardless of which workspace is currently active.
        $configs = IntegrationConfig::withoutGlobalScope('workspace')->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderBy('kind')
            ->orderBy('provider')
            ->orderByDesc('id')
            ->get()
            ->groupBy('kind');

        $kinds     = IntegrationConfigRegistry::kinds();
        $activeTab = in_array($request->get('tab'), ['payment', 'sms', 'email'], true) ? $request->get('tab') : 'payment';

        return view('user.integrations.index', compact('configs', 'kinds', 'activeTab'));
    }

    public function create(Request $request, string $kind)
    {
        $this->ensureKind($kind);
        $providers = IntegrationConfigRegistry::providers($kind);
        $kindMeta  = IntegrationConfigRegistry::kinds()[$kind];

        $provider = $request->get('provider');
        $providerSchema = $provider ? IntegrationConfigRegistry::provider($kind, $provider) : null;

        return view('user.integrations.create', compact('kind', 'kindMeta', 'providers', 'provider', 'providerSchema'));
    }

    public function store(Request $request, string $kind)
    {
        $this->ensureKind($kind);
        $userId = $request->user()->id;

        $provider = $request->input('provider');
        $schema   = IntegrationConfigRegistry::provider($kind, $provider);
        abort_if($schema === null, 422, 'Unknown provider.');

        [$data, $credentials, $meta] = $this->validateAndSplit($request, $kind, $provider, $schema);

        $config = DB::transaction(function () use ($userId, $kind, $provider, $data, $credentials, $meta) {
            $config = IntegrationConfig::create([
                'user_id'     => $userId,
                'kind'        => $kind,
                'provider'    => $provider,
                'name'        => $data['name'],
                'is_active'   => (bool) ($data['is_active'] ?? true),
                'is_default'  => (bool) ($data['is_default'] ?? false),
                'credentials' => $credentials,
                'meta'        => $meta,
            ]);

            // Auto-default if it's the user's first config of this kind.
            $count = IntegrationConfig::withoutGlobalScope('workspace')->where('user_id', $userId)->kind($kind)->count();
            if ($count === 1) {
                $config->is_default = true;
                $config->save();
            }

            if ($config->is_default) {
                $this->clearOtherDefaults($userId, $kind, $config->id);
            }

            return $config;
        });

        return redirect()
            ->to($this->afterUrl($request, $kind))
            ->with('success', "{$schema['label']} configuration saved.");
    }

    public function edit(Request $request, IntegrationConfig $integrationConfig)
    {
        $this->authorizeOwnership($request, $integrationConfig);
        $schema   = IntegrationConfigRegistry::provider($integrationConfig->kind, $integrationConfig->provider);
        $kindMeta = IntegrationConfigRegistry::kinds()[$integrationConfig->kind];

        return view('user.integrations.edit', [
            'config'         => $integrationConfig,
            'kind'           => $integrationConfig->kind,
            'kindMeta'       => $kindMeta,
            'providerSchema' => $schema,
            'masked'         => $integrationConfig->maskedCredentials(),
        ]);
    }

    public function update(Request $request, IntegrationConfig $integrationConfig)
    {
        $this->authorizeOwnership($request, $integrationConfig);
        $schema = IntegrationConfigRegistry::provider($integrationConfig->kind, $integrationConfig->provider);

        // Provider is immutable on update — pin the request value to the saved
        // record's provider so a tampered POST cannot validate against (or
        // accidentally rewrite credentials for) a different provider's schema.
        $request->merge(['provider' => $integrationConfig->provider]);

        [$data, $credentials, $meta] = $this->validateAndSplit($request, $integrationConfig->kind, $integrationConfig->provider, $schema, $integrationConfig);

        DB::transaction(function () use ($integrationConfig, $data, $credentials, $meta) {
            // Preserve existing credential values when the user leaves the field blank
            // (so they don't have to re-enter secrets just to change a label).
            $existing = (array) $integrationConfig->credentials;
            foreach ($credentials as $k => $v) {
                if ($v === null || $v === '') {
                    $credentials[$k] = $existing[$k] ?? null;
                }
            }

            $integrationConfig->update([
                'name'        => $data['name'],
                'is_active'   => (bool) ($data['is_active'] ?? true),
                'is_default'  => (bool) ($data['is_default'] ?? false),
                'credentials' => $credentials,
                'meta'        => $meta,
            ]);

            if ($integrationConfig->is_default) {
                $this->clearOtherDefaults($integrationConfig->user_id, $integrationConfig->kind, $integrationConfig->id);
            }
        });

        return redirect()
            ->to($this->afterUrl($request, $integrationConfig->kind))
            ->with('success', 'Configuration updated.');
    }

    public function destroy(Request $request, IntegrationConfig $integrationConfig)
    {
        $this->authorizeOwnership($request, $integrationConfig);
        $kind = $integrationConfig->kind;
        $integrationConfig->delete();

        return redirect()
            ->to($this->afterUrl($request, $kind))
            ->with('success', 'Configuration deleted.');
    }

    public function setDefault(Request $request, IntegrationConfig $integrationConfig)
    {
        $this->authorizeOwnership($request, $integrationConfig);
        DB::transaction(function () use ($integrationConfig) {
            $this->clearOtherDefaults($integrationConfig->user_id, $integrationConfig->kind, $integrationConfig->id);
            $integrationConfig->update(['is_default' => true]);
        });

        return back()->with('success', 'Default updated.');
    }

    public function toggleActive(Request $request, IntegrationConfig $integrationConfig)
    {
        $this->authorizeOwnership($request, $integrationConfig);
        $integrationConfig->update(['is_active' => ! $integrationConfig->is_active]);
        return back()->with('success', $integrationConfig->is_active ? 'Enabled.' : 'Disabled.');
    }

    // ---------- helpers ----------

    /**
     * Where to land after a mutation: the dedicated SMTP Connections page when
     * the flow started there (`return_to=connections`, email kind only),
     * otherwise the classic Settings → Integrations tab.
     */
    private function afterUrl(Request $request, string $kind): string
    {
        if ($kind === 'email' && $request->input('return_to') === 'connections') {
            return route('user.email-connections.index');
        }
        return route('user.integrations.index', ['tab' => $kind]);
    }
    private function ensureKind(string $kind): void
    {
        abort_unless(in_array($kind, ['payment', 'sms', 'email'], true), 404);
    }

    private function authorizeOwnership(Request $request, IntegrationConfig $c): void
    {
        abort_unless($c->user_id === $request->user()->id, 403);
    }

    private function clearOtherDefaults(int $userId, string $kind, int $keepId): void
    {
        IntegrationConfig::withoutGlobalScope('workspace')->where('user_id', $userId)
            ->kind($kind)
            ->where('id', '!=', $keepId)
            ->update(['is_default' => false]);
    }

    /**
     * Validate the request against the chosen provider's field schema, then
     * split the values into (top-level form data, encrypted credentials, plain meta).
     *
     * @return array{0: array, 1: array, 2: array}
     */
    private function validateAndSplit(Request $request, string $kind, string $provider, array $schema, ?IntegrationConfig $editing = null): array
    {
        $rules = [
            'name'       => 'required|string|max:120',
            'provider'   => ['required', 'string', Rule::in(array_keys(IntegrationConfigRegistry::providers($kind)))],
            'is_active'  => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ];

        // Build per-field rules from the registry schema.
        foreach ($schema['fields'] as $f) {
            $key  = "fields.{$f['key']}";
            $rule = [];

            // On UPDATE, password fields can be left blank to keep the existing secret.
            $isPwd = ($f['type'] ?? 'text') === 'password';
            $req   = (bool) ($f['required'] ?? false);

            if ($req && ! ($editing && $isPwd)) {
                $rule[] = 'required';
            } else {
                $rule[] = 'nullable';
            }

            $rule[] = match ($f['type']) {
                'email'    => 'email:rfc',
                'url'      => 'url',
                'textarea' => 'string',
                'select'   => Rule::in(array_keys($f['options'] ?? [])),
                default    => 'string',
            };
            $rule[] = 'max:1000';

            $rules[$key] = $rule;
        }

        $validated = $request->validate($rules);

        $credentials = [];
        $meta = [];
        foreach ($schema['fields'] as $f) {
            $val = $validated['fields'][$f['key']] ?? null;
            if (($f['group'] ?? 'meta') === 'credentials') {
                $credentials[$f['key']] = $val;
            } else {
                $meta[$f['key']] = $val;
            }
        }

        return [$validated, $credentials, $meta];
    }
}
