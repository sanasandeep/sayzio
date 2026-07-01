<?php

namespace App\Modules\User\Services\ConnectedApps;

use App\Modules\User\Support\ConnectedApps\ConnectedAppRegistry;

/**
 * Resolves the right adapter for a connection from the data-driven registry
 * and exposes the analytics forwarder. Keeps provider→class wiring in one
 * place so controllers/jobs never hard-code adapter classes.
 */
class ConnectedAppManager
{
    /** Resolve the CRM connector for a provider key. */
    public function connector(string $provider): ConnectorContract
    {
        $meta = ConnectedAppRegistry::provider($provider);
        if (!$meta || ($meta['kind'] ?? null) !== 'crm') {
            throw new ConnectedAppException("No CRM connector for provider [{$provider}].");
        }
        $adapter = app($meta['adapter']);
        if (!$adapter instanceof ConnectorContract) {
            throw new ConnectedAppException("Adapter for [{$provider}] is not a CRM connector.");
        }
        return $adapter;
    }

    public function forwarder(): GoogleAnalyticsForwarder
    {
        return app(GoogleAnalyticsForwarder::class);
    }

    /** True when the provider exists and is a CRM. */
    public function isCrm(string $provider): bool
    {
        return (ConnectedAppRegistry::provider($provider)['kind'] ?? null) === 'crm';
    }
}
