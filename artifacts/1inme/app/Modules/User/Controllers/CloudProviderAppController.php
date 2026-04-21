<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\CloudProviderApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CloudProviderAppController extends Controller
{
    public function index()
    {
        $existing = CloudProviderApp::query()->get()->keyBy('provider');
        $rows = [];
        foreach (CloudProviderApp::PROVIDERS as $p) {
            $rows[$p] = $existing->get($p);
        }
        $callback = url('/user/cloud-oauth/__provider__/callback');
        return view('user.cloud-files.settings', compact('rows', 'callback'));
    }

    public function update(Request $request, string $provider)
    {
        abort_unless(CloudProviderApp::isKnownProvider($provider), 404);

        $data = $request->validate([
            'client_id'     => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:1000'],
            'redirect_uri'  => ['nullable', 'url', 'max:1024'],
            'enabled'       => ['nullable', 'boolean'],
        ]);

        $row = CloudProviderApp::query()->firstOrNew(['provider' => $provider]);

        if (array_key_exists('client_id', $data) && $data['client_id'] !== null) {
            $row->client_id = $data['client_id'];
        }
        // Blank secret on update preserves existing — same convention as IntegrationConfigController.
        if (!empty($data['client_secret'])) {
            $row->client_secret_encrypted = $data['client_secret'];
        }
        $row->redirect_uri = $data['redirect_uri'] ?? null;
        $row->enabled = (bool) ($data['enabled'] ?? false);
        $row->save();

        return redirect()->route('user.cloud-files.settings.index')
            ->with('success', $row->label() . ' OAuth credentials saved.');
    }

    public function destroy(string $provider)
    {
        abort_unless(CloudProviderApp::isKnownProvider($provider), 404);
        CloudProviderApp::where('provider', $provider)->delete();
        return back()->with('success', 'Removed.');
    }

    /**
     * Lightweight credential sanity check. Posts a deliberately invalid auth
     * code to the provider's token endpoint and inspects the OAuth error
     * response. The provider rejects "invalid_client" before it ever looks at
     * the code, so we can tell apart "bad client_id/secret" (fail) vs.
     * "credentials accepted, code rejected" (pass) without a real OAuth round
     * trip.
     */
    public function test(string $provider)
    {
        abort_unless(CloudProviderApp::isKnownProvider($provider), 404);
        $row = CloudProviderApp::where('provider', $provider)->first();
        if (!$row || !$row->isConfigured()) {
            return response()->json(['ok' => false, 'message' => 'Not configured.'], 422);
        }

        $endpoints = [
            'google_drive' => 'https://oauth2.googleapis.com/token',
            'dropbox'      => 'https://api.dropboxapi.com/oauth2/token',
            'onedrive'     => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
        ];
        $redirect = $row->redirect_uri ?: url('/user/cloud-oauth/' . $provider . '/callback');

        $r = Http::asForm()->post($endpoints[$provider], [
            'grant_type'    => 'authorization_code',
            'code'          => '__1inme_credential_probe__',
            'client_id'     => $row->client_id,
            'client_secret' => (string) $row->client_secret_encrypted,
            'redirect_uri'  => $redirect,
        ]);

        $err = $r->json('error');
        // Anything other than invalid_client/unauthorized_client means the
        // provider got past credential validation and choked on the dummy
        // code — exactly what we want to confirm.
        $clientCredsBad = in_array($err, ['invalid_client', 'unauthorized_client'], true);
        if ($clientCredsBad) {
            return response()->json([
                'ok'      => false,
                'message' => 'Provider rejected the client ID/secret (' . $err . ').',
            ]);
        }

        return response()->json([
            'ok'      => true,
            'message' => 'Credentials accepted by ' . $row->label() . '. Ready to connect.',
        ]);
    }
}
