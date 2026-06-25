<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\GatewaySetting;
use App\Services\Billing\GatewayManager;
use Illuminate\Database\Seeder;

class GatewaySettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'razorpay' => 'Razorpay',
            'stripe'   => 'Stripe',
            'paypal'   => 'PayPal',
            'cashfree' => 'Cashfree',
            'payumoney'=> 'PayUMoney',
            'offline'  => 'Pay manually (bank transfer / UPI)',
        ];
        $i = 0;
        foreach (array_keys(GatewayManager::MAP) as $slug) {
            GatewaySetting::firstOrCreate(
                ['gateway_slug' => $slug],
                [
                    'display_name' => $defaults[$slug] ?? ucfirst($slug),
                    'mode'         => 'test',
                    'is_enabled'   => $slug === 'offline', // offline is the
                                                          // only working
                                                          // adapter shipped
                                                          // in task-193.
                    'sort_order'   => $i++,
                    'credentials_encrypted' => $slug === 'offline' ? [
                        'payee_name'  => config('billing.merchant.name'),
                        'instructions'=> "Please transfer the amount shown on your invoice to the bank/UPI details below, then email the transaction reference to " . config('billing.merchant.support_email') . ".\n\nYour plan will be activated within one business day.",
                    ] : [],
                ]
            );
        }
    }
}
