<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\CloudConnection;
use App\Modules\User\Models\CloudProviderApp;
use App\Modules\User\Services\CloudFiles\CloudProviderRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CloudOAuthController extends Controller
{
    public function __construct(protected CloudProviderRegistry $registry) {}

    public function start(Request $request, string $provider)
    {
        abort_unless(CloudProviderApp::isKnownProvider($provider), 404);

        $app = CloudProviderApp::where('provider', $provider)->first();
        if (!$app || !$app->isConfigured()) {
            return redirect()->route('user.cloud-files.connections')
                ->with('error', CloudProviderApp::PROVIDER_LABELS[$provider]
                    . ' is not configured for this workspace yet. Ask the workspace owner to add OAuth credentials in Settings.');
        }

        $state = Str::random(64);
        $ws = app('current_workspace');
        session([
            'cloud_oauth_state_' . $provider => $state,
            'cloud_oauth_ws_'    . $provider => $ws->id,
        ]);

        $url = $this->registry->get($provider)->authorizeUrl($app, $state, $this->redirectUriFor($app, $provider));
        return redirect()->away($url);
    }

    public function callback(Request $request, string $provider)
    {
        abort_unless(CloudProviderApp::isKnownProvider($provider), 404);

        $expected = session()->pull('cloud_oauth_state_' . $provider);
        $wsId     = session()->pull('cloud_oauth_ws_' . $provider);
        $state    = (string) $request->query('state', '');
        $code     = (string) $request->query('code', '');

        if (!$expected || !hash_equals($expected, $state)) {
            return redirect()->route('user.cloud-files.connections')
                ->with('error', 'OAuth state mismatch. Please try again.');
        }
        if ($request->has('error') || $code === '') {
            return redirect()->route('user.cloud-files.connections')
                ->with('error', 'Authorization was cancelled or failed.');
        }
        if (!Auth::check()) {
            return redirect()->route('user.login')
                ->with('error', 'Please sign in again to finish connecting.');
        }

        $ws = app('current_workspace');
        if ($wsId && (int) $wsId !== (int) $ws->id) {
            return redirect()->route('user.cloud-files.connections')
                ->with('error', 'Workspace changed during the OAuth flow. Please reconnect.');
        }

        $app = CloudProviderApp::where('provider', $provider)->first();
        if (!$app || !$app->isConfigured()) {
            return redirect()->route('user.cloud-files.connections')
                ->with('error', 'OAuth app is no longer configured.');
        }

        try {
            [$access, $refresh, $expires, $email, $label, $scopes] =
                $this->registry->get($provider)->exchangeCode($app, $code, $this->redirectUriFor($app, $provider));
        } catch (\RuntimeException $e) {
            return redirect()->route('user.cloud-files.connections')
                ->with('error', 'Connect failed: ' . $e->getMessage());
        }

        $conn = CloudConnection::query()
            ->where('user_id', Auth::id())
            ->where('provider', $provider)
            ->first();

        $payload = [
            'user_id'                 => Auth::id(),
            'provider'                => $provider,
            'account_label'           => $label,
            'account_email'           => $email,
            'access_token_encrypted'  => $access,
            'expires_at'              => $expires,
            'scopes'                  => $scopes,
            'last_error'              => null,
            'last_synced_at'          => now(),
        ];
        if ($refresh) $payload['refresh_token_encrypted'] = $refresh;

        if ($conn) {
            $conn->update($payload);
        } else {
            CloudConnection::create($payload);
        }

        return redirect()->route('user.cloud-files.connections')
            ->with('success', CloudProviderApp::PROVIDER_LABELS[$provider] . ' connected.');
    }

    private function redirectUriFor(CloudProviderApp $app, string $provider): string
    {
        return $app->redirect_uri ?: url('/user/cloud-oauth/' . $provider . '/callback');
    }
}
