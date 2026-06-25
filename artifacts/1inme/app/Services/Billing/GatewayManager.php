<?php

namespace App\Services\Billing;

use App\Modules\Admin\Models\GatewaySetting;
use App\Services\Billing\Adapters\CashfreeAdapter;
use App\Services\Billing\Adapters\OfflineAdapter;
use App\Services\Billing\Adapters\PaypalAdapter;
use App\Services\Billing\Adapters\PayuAdapter;
use App\Services\Billing\Adapters\RazorpayAdapter;
use App\Services\Billing\Adapters\StripeAdapter;
use App\Services\Billing\Contracts\GatewayAdapter;

/**
 * Resolves gateway adapters by slug and hydrates them with the admin's
 * stored credentials. Usage:
 *   $adapter = app(GatewayManager::class)->for('razorpay');
 *
 * enabledAdapters() returns the five adapters that are currently flagged
 * `is_enabled=true` in gateway_settings, sorted for UI display.
 */
class GatewayManager
{
    /** @var array<string,class-string<GatewayAdapter>> */
    public const MAP = [
        'razorpay'  => RazorpayAdapter::class,
        'stripe'    => StripeAdapter::class,
        'paypal'    => PaypalAdapter::class,
        'cashfree'  => CashfreeAdapter::class,
        'payumoney' => PayuAdapter::class,
        'offline'   => OfflineAdapter::class,
    ];

    public function for(string $slug): GatewayAdapter
    {
        if (!isset(self::MAP[$slug])) {
            abort(404, "Unknown payment gateway: {$slug}");
        }
        /** @var GatewayAdapter $adapter */
        $adapter = app(self::MAP[$slug]);
        $adapter->setSettings(GatewaySetting::where('gateway_slug', $slug)->first());
        return $adapter;
    }

    /** @return array<int,GatewayAdapter> */
    public function enabledAdapters(): array
    {
        $rows = GatewaySetting::where('is_enabled', true)
            ->orderBy('sort_order')->orderBy('id')->get();
        $out = [];
        foreach ($rows as $row) {
            if (!isset(self::MAP[$row->gateway_slug])) continue;
            $adapter = app(self::MAP[$row->gateway_slug]);
            $adapter->setSettings($row);
            $out[] = $adapter;
        }
        return $out;
    }

    /** @return array<int,array{slug:string,display_name:string,settings:GatewaySetting}> */
    public function allWithSettings(): array
    {
        $out = [];
        foreach (self::MAP as $slug => $class) {
            $row = GatewaySetting::firstOrCreate(
                ['gateway_slug' => $slug],
                [
                    'display_name' => app($class)->displayName(),
                    'mode'         => 'test',
                    'is_enabled'   => false,
                    'sort_order'   => array_search($slug, array_keys(self::MAP), true),
                ],
            );
            $out[] = ['slug' => $slug, 'display_name' => $row->display_name, 'settings' => $row];
        }
        return $out;
    }
}
