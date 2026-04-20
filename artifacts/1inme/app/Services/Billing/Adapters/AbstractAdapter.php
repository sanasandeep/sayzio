<?php

namespace App\Services\Billing\Adapters;

use App\Modules\Admin\Models\GatewaySetting;
use App\Services\Billing\Contracts\GatewayAdapter;

abstract class AbstractAdapter implements GatewayAdapter
{
    protected ?GatewaySetting $settings = null;

    public function setSettings(?GatewaySetting $settings): void
    {
        $this->settings = $settings;
    }

    protected function cred(string $key, $default = null)
    {
        return $this->settings?->credential($key, $default) ?? $default;
    }
}
