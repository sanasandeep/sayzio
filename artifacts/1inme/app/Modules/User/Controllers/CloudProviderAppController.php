<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\CloudProviderApp;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
}
