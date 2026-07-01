<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\ConnectedApp;
use App\Modules\User\Services\ConnectedApps\ConnectedAppManager;
use App\Modules\User\Services\ConnectedApps\CrmSyncService;
use App\Modules\User\Support\ConnectedApps\ConnectedAppRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Creator-facing "Connected Apps" area: connect/manage Salesforce, HubSpot,
 * Zoho (CRM, two-way) and Google Analytics 4 (event forwarding).
 *
 * OAuth uses a stateless, APP_KEY-encrypted `state` blob (carrying user id +
 * provider + return platform) rather than the session, so the very same
 * public callback serves both the web app and the mobile app (which returns
 * to a `sayzio://` deep link). Everything provider-specific is read from the
 * data-driven ConnectedAppRegistry — no per-provider branching here.
 */
class ConnectedAppController extends Controller
{
    public function __construct(
        protected ConnectedAppManager $manager,
        protected CrmSyncService $sync,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $connections = ConnectedApp::forUser($user->id)->get()->keyBy('provider');

        $providers = collect(ConnectedAppRegistry::all())->map(function ($meta, $key) use ($connections) {
            return [
                'meta'       => $meta,
                'available'  => ConnectedAppRegistry::isPlatformConfigured($key),
                'status'     => ConnectedAppRegistry::status($key),
                'connection' => optional($connections->get($key))->toPublicArray(),
            ];
        })->values();

        return view('user.connected-apps.index', [
            'providers' => $providers,
        ]);
    }

    /** Kick off the OAuth flow for a CRM provider. */
    public function connect(Request $request, string $provider)
    {
        $meta = ConnectedAppRegistry::provider($provider);
        if (!$meta || ($meta['connect_type'] ?? null) !== 'oauth') {
            return redirect()->route('user.connected-apps.index')->with('error', 'Unknown or non-OAuth provider.');
        }
        if (!ConnectedAppRegistry::isPlatformConfigured($provider)) {
            return redirect()->route('user.connected-apps.index')
                ->with('error', $meta['label'] . ' is not available yet — check back soon.');
        }

        $platform = $request->query('platform') === 'mobile' ? 'mobile' : 'web';
        $state = Crypt::encryptString(json_encode([
            'uid'      => (int) $request->user()->id,
            'provider' => $provider,
            'platform' => $platform,
            'nonce'    => bin2hex(random_bytes(8)),
            't'        => now()->timestamp,
        ]));

        try {
            $connector = $this->manager->connector($provider);
            $url = $connector->authorizationUrl($state, route('connected-apps.callback'));
        } catch (\Throwable $e) {
            return redirect()->route('user.connected-apps.index')->with('error', $e->getMessage());
        }

        return redirect()->away($url);
    }

    /**
     * Public OAuth callback (see routes/web.php). Decrypts the state, exchanges
     * the code, upserts the connection, then returns the creator to the web UI
     * or the mobile deep link.
     */
    public function callback(Request $request)
    {
        $webReturn = route('user.connected-apps.index');
        try {
            $state = json_decode(Crypt::decryptString((string) $request->query('state')), true);
        } catch (\Throwable $e) {
            return redirect($webReturn)->with('error', 'Connection request expired or invalid.');
        }
        if (!is_array($state) || empty($state['uid']) || empty($state['provider'])) {
            return redirect($webReturn)->with('error', 'Connection request expired or invalid.');
        }
        // Expire states older than 15 minutes.
        if (!empty($state['t']) && now()->timestamp - (int) $state['t'] > 900) {
            return redirect($webReturn)->with('error', 'Connection request expired — please try again.');
        }

        $provider = $state['provider'];
        $isMobile = ($state['platform'] ?? 'web') === 'mobile';
        $code     = $request->query('code');
        $err      = $request->query('error');

        if ($err || !$code) {
            return $this->finish($isMobile, $webReturn, false, 'Authorization was cancelled.');
        }
        if (!ConnectedAppRegistry::has($provider)) {
            return $this->finish($isMobile, $webReturn, false, 'Unknown provider.');
        }

        try {
            $conn = ConnectedApp::firstOrNew([
                'user_id'  => (int) $state['uid'],
                'provider' => $provider,
            ]);
            $meta = ConnectedAppRegistry::provider($provider);
            $conn->kind         = $meta['kind'];
            $conn->push_enabled = $conn->push_enabled ?? true;
            $conn->pull_enabled = $conn->pull_enabled ?? true;
            $conn->field_mappings = $conn->field_mappings ?: ($meta['default_field_mappings'] ?? []);
            $conn->save();

            $connector = $this->manager->connector($provider);
            $connector->exchangeCode($conn, $code, route('connected-apps.callback'));

            return $this->finish($isMobile, $webReturn, true, $meta['label'] . ' connected.');
        } catch (\Throwable $e) {
            Log::error('Connected app connect failed', ['provider' => $provider, 'err' => $e->getMessage()]);
            return $this->finish($isMobile, $webReturn, false, 'Could not connect: ' . $e->getMessage());
        }
    }

    /** Connect / update a Google Analytics 4 property (config, not OAuth). */
    public function saveGa(Request $request)
    {
        if (!ConnectedAppRegistry::isPlatformConfigured('google_analytics')) {
            return redirect()->route('user.connected-apps.index')
                ->with('error', 'Google Analytics is not available yet.');
        }
        $data = $request->validate([
            'measurement_id' => ['required', 'string', 'max:64'],
            'api_secret'     => ['required', 'string', 'max:255'],
        ]);

        $conn = ConnectedApp::firstOrNew([
            'user_id'  => (int) $request->user()->id,
            'provider' => 'google_analytics',
        ]);
        $conn->kind         = 'analytics';
        $conn->status       = ConnectedApp::STATUS_CONNECTED;
        $conn->connected_at = $conn->connected_at ?: now();
        $conn->settings     = array_merge($conn->settings ?? [], ['measurement_id' => trim($data['measurement_id'])]);
        $conn->access_token = trim($data['api_secret']); // encrypted cast
        $conn->account_label = trim($data['measurement_id']);
        $conn->save();

        return redirect()->route('user.connected-apps.index')->with('success', 'Google Analytics connected.');
    }

    /** Toggle push/pull, pause/resume, or update field mappings. */
    public function update(Request $request, ConnectedApp $connectedApp)
    {
        abort_if($connectedApp->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'push_enabled'   => ['sometimes', 'boolean'],
            'pull_enabled'   => ['sometimes', 'boolean'],
            'paused'         => ['sometimes', 'boolean'],
            'field_mappings' => ['sometimes', 'array'],
        ]);

        if (array_key_exists('push_enabled', $data)) {
            $connectedApp->push_enabled = (bool) $data['push_enabled'];
        }
        if (array_key_exists('pull_enabled', $data)) {
            $connectedApp->pull_enabled = (bool) $data['pull_enabled'];
        }
        if (array_key_exists('paused', $data)) {
            $connectedApp->paused_at = $data['paused'] ? now() : null;
            $connectedApp->status = $data['paused'] ? ConnectedApp::STATUS_PAUSED : ConnectedApp::STATUS_CONNECTED;
        }
        if (array_key_exists('field_mappings', $data)) {
            $connectedApp->field_mappings = array_filter(
                $data['field_mappings'],
                fn ($v) => is_string($v) && $v !== ''
            );
        }
        $connectedApp->save();

        return back()->with('success', 'Connection updated.');
    }

    /** Trigger an immediate inbound pull for one CRM connection. */
    public function syncNow(Request $request, ConnectedApp $connectedApp)
    {
        abort_if($connectedApp->user_id !== $request->user()->id, 403);
        if (!$connectedApp->isCrm()) {
            return back()->with('error', 'Only CRM connections can be pulled.');
        }
        $count = $this->sync->pull($connectedApp);
        return back()->with('success', "Sync complete — {$count} contact(s) imported.");
    }

    public function destroy(Request $request, ConnectedApp $connectedApp)
    {
        abort_if($connectedApp->user_id !== $request->user()->id, 403);
        $label = $connectedApp->providerLabel();
        $connectedApp->delete();
        return redirect()->route('user.connected-apps.index')
            ->with('success', $label . ' disconnected.');
    }

    private function finish(bool $isMobile, string $webReturn, bool $ok, string $message)
    {
        if ($isMobile) {
            $params = http_build_query([
                'status'   => $ok ? 'ok' : 'error',
                'message'  => $message,
                'feature'  => 'connected-apps',
            ]);
            return redirect()->away('sayzio://oauth-callback?' . $params);
        }
        return redirect($webReturn)->with($ok ? 'success' : 'error', $message);
    }
}
