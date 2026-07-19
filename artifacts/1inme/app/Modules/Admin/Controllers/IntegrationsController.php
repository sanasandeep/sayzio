<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Integrations\GitHubTokenHealth;
use App\Services\Integrations\IntegrationCatalog;
use App\Services\Integrations\PlatformServiceSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Single admin "Integrations" hub. The landing page (index) groups every
 * third-party credential surface by category with a status badge and a
 * link to its editor — both the pre-existing dedicated editors (AI Engine,
 * WhatsApp & alerts, Email/SMTP, Payment Gateways, Social OAuth) and the
 * new env-only editors rendered here.
 *
 * The env-only editors (Google Places, Trustpilot, Google Contacts OAuth,
 * S3 storage) follow the established MailSettings pattern via
 * PlatformServiceSettings: values live in app_settings, secrets are
 * Crypt-encrypted at rest and masked in the UI, a blank field on save
 * leaves the stored value untouched, an explicit "remove" checkbox clears
 * it back to the env fallback, and applyRuntimeConfig() (called at boot)
 * pushes the effective values into the live config without a redeploy.
 *
 * Every route is gated behind the `settings.manage` permission.
 */
class IntegrationsController extends Controller
{
    public function index()
    {
        return view('admin.integrations.index', [
            'categories' => IntegrationCatalog::categories(),
            'summary'    => IntegrationCatalog::summary(),
        ]);
    }

    // ═════════════════════════════════════════════════════════════
    // Google Places (reviews)
    // ═════════════════════════════════════════════════════════════

    public function editGooglePlaces()
    {
        return view('admin.integrations.google-places', [
            'status'    => PlatformServiceSettings::googlePlacesStatus(),
            'hasValue'  => PlatformServiceSettings::googlePlacesApiKey() !== null,
            'masked'    => PlatformServiceSettings::maskedGooglePlacesApiKey(),
        ]);
    }

    public function updateGooglePlaces(Request $request)
    {
        $data = $request->validate([
            'api_key'       => 'nullable|string|max:255',
            'clear_api_key' => 'nullable|boolean',
        ]);

        if ($request->boolean('clear_api_key')) {
            PlatformServiceSettings::setGooglePlacesApiKey(null);
        } elseif (!empty($data['api_key'])) {
            PlatformServiceSettings::setGooglePlacesApiKey($data['api_key']);
        }

        return redirect()->route('admin.integrations.google-places.edit')
            ->with('success', 'Google Places settings saved.');
    }

    // ═════════════════════════════════════════════════════════════
    // Trustpilot (reviews)
    // ═════════════════════════════════════════════════════════════

    public function editTrustpilot()
    {
        return view('admin.integrations.trustpilot', [
            'status'    => PlatformServiceSettings::trustpilotStatus(),
            'hasValue'  => PlatformServiceSettings::trustpilotApiKey() !== null,
            'masked'    => PlatformServiceSettings::maskedTrustpilotApiKey(),
        ]);
    }

    public function updateTrustpilot(Request $request)
    {
        $data = $request->validate([
            'api_key'       => 'nullable|string|max:255',
            'clear_api_key' => 'nullable|boolean',
        ]);

        if ($request->boolean('clear_api_key')) {
            PlatformServiceSettings::setTrustpilotApiKey(null);
        } elseif (!empty($data['api_key'])) {
            PlatformServiceSettings::setTrustpilotApiKey($data['api_key']);
        }

        return redirect()->route('admin.integrations.trustpilot.edit')
            ->with('success', 'Trustpilot settings saved.');
    }

    // ═════════════════════════════════════════════════════════════
    // Google Contacts OAuth
    // ═════════════════════════════════════════════════════════════

    public function editGoogleContacts()
    {
        return view('admin.integrations.google-contacts', [
            'status'          => PlatformServiceSettings::googleContactsStatus(),
            'clientId'        => PlatformServiceSettings::googleContactsClientId(),
            'hasSecret'       => PlatformServiceSettings::googleContactsClientSecret() !== null,
            'maskedSecret'    => PlatformServiceSettings::maskedGoogleContactsClientSecret(),
        ]);
    }

    public function updateGoogleContacts(Request $request)
    {
        $data = $request->validate([
            'client_id'         => 'nullable|string|max:255',
            'client_secret'     => 'nullable|string|max:255',
            'clear_client_secret' => 'nullable|boolean',
        ]);

        PlatformServiceSettings::setGoogleContactsClientId($data['client_id'] ?? null);

        if ($request->boolean('clear_client_secret')) {
            PlatformServiceSettings::setGoogleContactsClientSecret(null);
        } elseif (!empty($data['client_secret'])) {
            PlatformServiceSettings::setGoogleContactsClientSecret($data['client_secret']);
        }

        return redirect()->route('admin.integrations.google-contacts.edit')
            ->with('success', 'Google Contacts OAuth settings saved.');
    }

    // ═════════════════════════════════════════════════════════════
    // GitHub personal access token
    // ═════════════════════════════════════════════════════════════

    public function editGitHub()
    {
        return view('admin.integrations.github', [
            'status'    => PlatformServiceSettings::githubStatus(),
            'hasValue'  => PlatformServiceSettings::githubToken() !== null,
            'masked'    => PlatformServiceSettings::maskedGithubToken(),
            'repo'      => (string) config('services.github.repo', ''),
            'lastProbe' => \App\Services\Integrations\GitHubTokenHealth::lastProbe(),
        ]);
    }

    public function testGitHub()
    {
        $probe = \App\Services\Integrations\GitHubTokenHealth::verify();

        // The admin layout renders session('success') / session('error') only.
        $flashKey = $probe['status'] === 'ok' ? 'success' : 'error';

        return redirect()->route('admin.integrations.github.edit')
            ->with($flashKey, ($probe['status'] === 'inconclusive' ? 'Inconclusive — ' : '') . $probe['detail']);
    }

    public function updateGitHub(Request $request)
    {
        $data = $request->validate([
            'token'       => 'nullable|string|max:255',
            'clear_token' => 'nullable|boolean',
        ]);

        if ($request->boolean('clear_token')) {
            PlatformServiceSettings::setGithubToken(null);
        } elseif (!empty($data['token'])) {
            PlatformServiceSettings::setGithubToken($data['token']);
        }

        return redirect()->route('admin.integrations.github.edit')
            ->with('success', 'GitHub token settings saved.');
    }

    /**
     * "Verify token" button: live probe of the stored GitHub token.
     *
     * Each click makes a real GitHub API call, so the endpoint is throttled
     * per admin (6/min) — a stuck or spammed button must not burn the
     * authenticated rate limit (or the anonymous 60/hr limit when no token
     * is configured).
     */
    public function testGitHub(Request $request)
    {
        // Resolve the admin identity explicitly — admin routes use the custom
        // AdminAuth middleware (no Auth::shouldUse('admin')), so a plain
        // $request->user() reads the default web guard and can be null,
        // silently collapsing the throttle to per-IP.
        $actor = Auth::guard('admin')->user() ?: $request->user();
        $key   = 'github-token-test:' . ($actor?->id ?? $request->ip());

        if (RateLimiter::tooManyAttempts($key, 6)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->with('error', 'Please wait ' . max(1, $seconds) . ' seconds before checking the GitHub token again.');
        }
        RateLimiter::hit($key, 60);

        $probe = GitHubTokenHealth::probe();

        if ($probe['status'] === 'ok') {
            return back()->with('success', $probe['detail']);
        }

        return back()->with('error', 'GitHub token check: ' . $probe['detail']);
    }

    // ═════════════════════════════════════════════════════════════
    // Connected Apps: CRM OAuth clients (Salesforce / HubSpot / Zoho)
    // ═════════════════════════════════════════════════════════════

    public function editConnectedApp(string $provider)
    {
        $meta = \App\Modules\User\Support\ConnectedApps\ConnectedAppRegistry::provider($provider);
        abort_if(!$meta || ($meta['connect_type'] ?? null) !== 'oauth', 404);

        return view('admin.integrations.connected-app', [
            'provider'     => $provider,
            'meta'         => $meta,
            'status'       => PlatformServiceSettings::connectedAppStatus($provider),
            'clientId'     => PlatformServiceSettings::connectedAppClientId($provider),
            'hasSecret'    => PlatformServiceSettings::connectedAppClientSecret($provider) !== null,
            'maskedSecret' => PlatformServiceSettings::maskedConnectedAppClientSecret($provider),
        ]);
    }

    public function updateConnectedApp(Request $request, string $provider)
    {
        $meta = \App\Modules\User\Support\ConnectedApps\ConnectedAppRegistry::provider($provider);
        abort_if(!$meta || ($meta['connect_type'] ?? null) !== 'oauth', 404);

        $data = $request->validate([
            'client_id'           => 'nullable|string|max:255',
            'client_secret'       => 'nullable|string|max:255',
            'clear_client_secret' => 'nullable|boolean',
        ]);

        PlatformServiceSettings::setConnectedAppClientId($provider, $data['client_id'] ?? null);

        if ($request->boolean('clear_client_secret')) {
            PlatformServiceSettings::setConnectedAppClientSecret($provider, null);
        } elseif (!empty($data['client_secret'])) {
            PlatformServiceSettings::setConnectedAppClientSecret($provider, $data['client_secret']);
        }

        return redirect()->route('admin.integrations.connected-app.edit', $provider)
            ->with('success', $meta['label'] . ' OAuth settings saved.');
    }

    // ═════════════════════════════════════════════════════════════
    // Connected Apps: Google Analytics 4 forwarding (enable switch)
    // ═════════════════════════════════════════════════════════════

    public function editGoogleAnalytics()
    {
        return view('admin.integrations.google-analytics', [
            'status'  => PlatformServiceSettings::googleAnalyticsStatus(),
            'enabled' => PlatformServiceSettings::googleAnalyticsEnabled(),
        ]);
    }

    public function updateGoogleAnalytics(Request $request)
    {
        $data = $request->validate(['enabled' => 'nullable|boolean']);
        PlatformServiceSettings::setGoogleAnalyticsEnabled($request->boolean('enabled'));

        return redirect()->route('admin.integrations.google-analytics.edit')
            ->with('success', 'Google Analytics forwarding ' . ($request->boolean('enabled') ? 'enabled' : 'disabled') . '.');
    }

    // ═════════════════════════════════════════════════════════════
    // S3 / CloudFront storage
    // ═════════════════════════════════════════════════════════════

    public function editStorage()
    {
        return view('admin.integrations.storage', [
            'status'        => PlatformServiceSettings::s3Status(),
            'hasKey'        => PlatformServiceSettings::s3Key() !== null,
            'maskedKey'     => PlatformServiceSettings::maskedS3Key(),
            'hasSecret'     => PlatformServiceSettings::s3Secret() !== null,
            'maskedSecret'  => PlatformServiceSettings::maskedS3Secret(),
            'region'        => PlatformServiceSettings::s3Region(),
            'bucket'        => PlatformServiceSettings::s3Bucket(),
            'url'           => PlatformServiceSettings::s3Url(),
            'endpoint'      => PlatformServiceSettings::s3Endpoint(),
            'usePathStyle'  => PlatformServiceSettings::s3UsePathStyle(),
            'configured'    => PlatformServiceSettings::s3Configured(),
            'missing'       => PlatformServiceSettings::s3MissingPieces(),
        ]);
    }

    public function updateStorage(Request $request)
    {
        $data = $request->validate([
            's3_key'            => 'nullable|string|max:255',
            'clear_s3_key'      => 'nullable|boolean',
            's3_secret'         => 'nullable|string|max:255',
            'clear_s3_secret'   => 'nullable|boolean',
            's3_region'         => 'nullable|string|max:128',
            's3_bucket'         => 'nullable|string|max:255',
            's3_url'            => 'nullable|string|max:255',
            's3_endpoint'       => 'nullable|string|max:255',
            's3_use_path_style' => 'nullable|boolean',
        ]);

        // User content is always S3-backed — there is no "disable S3" option
        // anymore. We still refuse to save a state that would leave S3
        // *incomplete* (missing key/secret/bucket/region), since that would
        // make every upload fail with no way to fall back.
        $effKey    = $request->boolean('clear_s3_key')
            ? null
            : (!empty($data['s3_key']) ? $data['s3_key'] : PlatformServiceSettings::s3Key());
        $effSecret = $request->boolean('clear_s3_secret')
            ? null
            : (!empty($data['s3_secret']) ? $data['s3_secret'] : PlatformServiceSettings::s3Secret());
        $effRegion = $data['s3_region'] ?? PlatformServiceSettings::s3Region();
        $effBucket = $data['s3_bucket'] ?? PlatformServiceSettings::s3Bucket();

        if (!$effKey || !$effSecret || !$effBucket || !$effRegion) {
            return back()->withErrors([
                's3_bucket' => 'User content storage is S3-only and cannot be disabled — provide an access key, secret, bucket and region.',
            ])->withInput();
        }

        if ($request->boolean('clear_s3_key')) {
            PlatformServiceSettings::setS3Key(null);
        } elseif (!empty($data['s3_key'])) {
            PlatformServiceSettings::setS3Key($data['s3_key']);
        }

        if ($request->boolean('clear_s3_secret')) {
            PlatformServiceSettings::setS3Secret(null);
        } elseif (!empty($data['s3_secret'])) {
            PlatformServiceSettings::setS3Secret($data['s3_secret']);
        }

        PlatformServiceSettings::setS3Region($data['s3_region'] ?? null);
        PlatformServiceSettings::setS3Bucket($data['s3_bucket'] ?? null);
        PlatformServiceSettings::setS3Url($data['s3_url'] ?? null);
        PlatformServiceSettings::setS3Endpoint($data['s3_endpoint'] ?? null);
        PlatformServiceSettings::setS3UsePathStyle($request->boolean('s3_use_path_style'));

        // If this save closed an open "storage misconfigured" alert episode,
        // send the all-clear immediately instead of waiting for the hourly
        // storage:check-s3-config sweep. Best-effort — never block the save.
        try {
            \App\Services\Integrations\StorageHealthAlerts::check();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('storage-health post-save check failed: ' . $e->getMessage());
        }

        return redirect()->route('admin.integrations.storage.edit')
            ->with('success', 'Storage settings saved.');
    }

    public function testStorage()
    {
        $result = PlatformServiceSettings::verifyS3();

        if ($result['ok']) {
            return back()->with('success', 'S3 connectivity check passed — wrote, read and deleted a probe object.');
        }

        return back()->with('error', 'S3 connectivity check failed: ' . ($result['error'] ?? 'unknown error'));
    }
}
