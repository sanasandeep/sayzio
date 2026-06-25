<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\GatewaySetting;
use App\Services\Billing\GatewayManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin CRUD for payment-gateway settings. Credentials are stored
 * encrypted (via the model's `encrypted:array` cast) and NEVER echoed
 * back to the edit form — only a "•••• configured" placeholder is
 * shown. Leaving a credential field blank on update preserves the
 * existing stored value.
 */
class GatewaySettingsController extends Controller
{
    protected function fieldsFor(string $slug): array
    {
        return match ($slug) {
            'razorpay' => ['key_id', 'key_secret', 'webhook_secret'],
            'stripe'   => ['publishable_key', 'secret_key', 'webhook_secret'],
            'paypal'   => ['client_id', 'client_secret', 'webhook_id'],
            'cashfree' => ['app_id', 'secret_key', 'webhook_secret'],
            'payumoney'=> ['merchant_key', 'salt'],
            'offline'  => ['payee_name', 'bank_details', 'upi_id', 'instructions'],
            default    => [],
        };
    }

    public function index(GatewayManager $gm)
    {
        $rows = $gm->allWithSettings();
        return view('admin.payment-gateways.index', ['rows' => $rows]);
    }

    public function edit(string $slug, GatewayManager $gm)
    {
        if (!isset(GatewayManager::MAP[$slug])) abort(404);
        $gm->allWithSettings(); // ensures the row exists
        $row = GatewaySetting::where('gateway_slug', $slug)->firstOrFail();
        return view('admin.payment-gateways.edit', [
            'row'    => $row,
            'fields' => $this->fieldsFor($slug),
        ]);
    }

    public function update(Request $request, string $slug)
    {
        if (!isset(GatewayManager::MAP[$slug])) abort(404);
        $row = GatewaySetting::where('gateway_slug', $slug)->firstOrFail();

        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'mode'         => ['required', Rule::in(['test', 'live'])],
            'is_enabled'   => ['nullable', 'boolean'],
            'sort_order'   => ['nullable', 'integer', 'min:0'],
            'credentials'  => ['array'],
        ]);

        $existing = $row->credentials();
        $incoming = (array) ($request->input('credentials') ?? []);
        $merged   = $existing;
        foreach ($this->fieldsFor($slug) as $key) {
            $val = $incoming[$key] ?? null;
            // Blank input preserves the stored value (so we don't overwrite
            // a real secret with an empty placeholder on every save).
            if ($val !== null && $val !== '') {
                $merged[$key] = (string) $val;
            }
        }

        $row->update([
            'display_name'          => $data['display_name'],
            'mode'                  => $data['mode'],
            'is_enabled'            => $request->boolean('is_enabled'),
            'sort_order'            => (int) ($data['sort_order'] ?? $row->sort_order),
            'credentials_encrypted' => $merged,
        ]);

        return redirect()->route('admin.payment-gateways.index')
            ->with('success', "Saved settings for {$row->display_name}.");
    }

    public function toggle(Request $request, string $slug)
    {
        $row = GatewaySetting::where('gateway_slug', $slug)->firstOrFail();
        $row->update(['is_enabled' => !$row->is_enabled]);
        return back()->with('success', ($row->is_enabled ? 'Enabled ' : 'Disabled ') . $row->display_name . '.');
    }
}
