<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\ConnectedApp;
use App\Modules\User\Services\ConnectedApps\ConnectedAppManager;
use App\Modules\User\Services\ConnectedApps\CrmSyncService;
use App\Modules\User\Support\ConnectedApps\ConnectedAppRegistry;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;

/**
 * Mobile (/api/v1) parity for the creator "Connected Apps" area: connect and
 * manage Salesforce, HubSpot, Zoho (two-way CRM sync) and Google Analytics 4
 * (event forwarding).
 *
 * OAuth is delegated to the very same stateless, APP_KEY-encrypted callback
 * the web app uses (routes/web.php `connected-apps.callback`) — this endpoint
 * just returns the authorization URL for the mobile app to open in a browser;
 * on completion the callback redirects to the `sayzio://oauth-callback` deep
 * link, which the app catches to refresh this screen. Everything
 * provider-specific is read from the data-driven ConnectedAppRegistry.
 */
class ConnectedAppController extends Controller
{
    use ApiResponses;

    public function __construct(
        protected ConnectedAppManager $manager,
        protected CrmSyncService $sync,
    ) {}

    /** List every provider with platform availability + this user's connection. */
    public function index(Request $request)
    {
        $user = $request->user();
        $connections = ConnectedApp::forUser($user->id)->get()->keyBy('provider');

        $providers = [];
        foreach (ConnectedAppRegistry::all() as $key => $meta) {
            $providers[] = [
                'key'          => $key,
                'label'        => $meta['label'],
                'kind'         => $meta['kind'],
                'icon'         => $meta['icon'],
                'color'        => $meta['color'],
                'blurb'        => $meta['blurb'],
                'connect_type' => $meta['connect_type'],
                'capabilities' => $meta['capabilities'] ?? [],
                'config_fields' => $meta['config_fields'] ?? [],
                'available'    => ConnectedAppRegistry::isPlatformConfigured($key),
                'status'       => ConnectedAppRegistry::status($key),
                'connection'   => optional($connections->get($key))->toPublicArray(),
            ];
        }

        return $this->ok([
            'providers'       => $providers,
            'connected_apps'  => (bool) $user->getPlanFeature('connected_apps', false),
        ]);
    }

    /** Return an OAuth authorization URL for a CRM provider (opened in-browser). */
    public function connectUrl(Request $request, string $provider)
    {
        if (!$request->user()->getPlanFeature('connected_apps', false)) {
            return $this->planGate('Connected Apps are not available on your plan.', 'connected_apps', $request->user());
        }
        $meta = ConnectedAppRegistry::provider($provider);
        if (!$meta || ($meta['connect_type'] ?? null) !== 'oauth') {
            return $this->fail('Unknown or non-OAuth provider.', 422, 'invalid_provider');
        }
        if (!ConnectedAppRegistry::isPlatformConfigured($provider)) {
            return $this->fail($meta['label'] . ' is not available yet.', 422, 'provider_unavailable');
        }

        $state = Crypt::encryptString(json_encode([
            'uid'      => (int) $request->user()->id,
            'provider' => $provider,
            'platform' => 'mobile',
            'nonce'    => bin2hex(random_bytes(8)),
            't'        => now()->timestamp,
        ]));

        try {
            $url = $this->manager->connector($provider)->authorizationUrl($state, route('connected-apps.callback'));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 502, 'oauth_url_failed');
        }

        return $this->ok(['authorization_url' => $url]);
    }

    /** Connect / update a Google Analytics 4 property (config, not OAuth). */
    public function saveGa(Request $request)
    {
        if (!$request->user()->getPlanFeature('connected_apps', false)) {
            return $this->planGate('Connected Apps are not available on your plan.', 'connected_apps', $request->user());
        }
        if (!ConnectedAppRegistry::isPlatformConfigured('google_analytics')) {
            return $this->fail('Google Analytics is not available yet.', 422, 'provider_unavailable');
        }
        $data = $request->validate([
            'measurement_id' => ['required', 'string', 'max:64'],
            'api_secret'     => ['required', 'string', 'max:255'],
        ]);

        $conn = ConnectedApp::firstOrNew([
            'user_id'  => (int) $request->user()->id,
            'provider' => 'google_analytics',
        ]);
        $conn->kind          = 'analytics';
        $conn->status        = ConnectedApp::STATUS_CONNECTED;
        $conn->connected_at  = $conn->connected_at ?: now();
        $conn->settings      = array_merge($conn->settings ?? [], ['measurement_id' => trim($data['measurement_id'])]);
        $conn->access_token  = trim($data['api_secret']);
        $conn->account_label = trim($data['measurement_id']);
        $conn->save();

        return $this->ok(['connection' => $conn->fresh()->toPublicArray()]);
    }

    /** Toggle push/pull, pause/resume, or update field mappings. */
    public function update(Request $request, int $id)
    {
        if (!$request->user()->getPlanFeature('connected_apps', false)) {
            return $this->planGate('Connected Apps are not available on your plan.', 'connected_apps', $request->user());
        }
        $conn = ConnectedApp::forUser($request->user()->id)->find($id);
        if (!$conn) return $this->fail('Connection not found.', 404, 'not_found');

        $data = $request->validate([
            'push_enabled'   => ['sometimes', 'boolean'],
            'pull_enabled'   => ['sometimes', 'boolean'],
            'paused'         => ['sometimes', 'boolean'],
            'field_mappings' => ['sometimes', 'array'],
        ]);

        if (array_key_exists('push_enabled', $data)) $conn->push_enabled = (bool) $data['push_enabled'];
        if (array_key_exists('pull_enabled', $data)) $conn->pull_enabled = (bool) $data['pull_enabled'];
        if (array_key_exists('paused', $data)) {
            $conn->paused_at = $data['paused'] ? now() : null;
            $conn->status = $data['paused'] ? ConnectedApp::STATUS_PAUSED : ConnectedApp::STATUS_CONNECTED;
        }
        if (array_key_exists('field_mappings', $data)) {
            $conn->field_mappings = array_filter($data['field_mappings'], fn ($v) => is_string($v) && $v !== '');
        }
        $conn->save();

        return $this->ok(['connection' => $conn->fresh()->toPublicArray()]);
    }

    /** Trigger an immediate inbound pull for one CRM connection. */
    public function syncNow(Request $request, int $id)
    {
        if (!$request->user()->getPlanFeature('connected_apps', false)) {
            return $this->planGate('Connected Apps are not available on your plan.', 'connected_apps', $request->user());
        }
        $conn = ConnectedApp::forUser($request->user()->id)->find($id);
        if (!$conn) return $this->fail('Connection not found.', 404, 'not_found');
        if (!$conn->isCrm()) return $this->fail('Only CRM connections can be pulled.', 422, 'not_crm');

        try {
            $count = $this->sync->pull($conn);
        } catch (\Throwable $e) {
            return $this->fail('Sync failed: ' . $e->getMessage(), 502, 'sync_failed');
        }

        return $this->ok([
            'imported'   => $count,
            'connection' => $conn->fresh()->toPublicArray(),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $conn = ConnectedApp::forUser($request->user()->id)->find($id);
        if (!$conn) return $this->fail('Connection not found.', 404, 'not_found');
        $conn->delete();
        return $this->ok(['disconnected' => true]);
    }
}
