<?php

namespace App\Modules\User\Support\ConnectedApps;

use App\Modules\User\Services\ConnectedApps\GoogleAnalyticsForwarder;
use App\Modules\User\Services\ConnectedApps\HubspotConnector;
use App\Modules\User\Services\ConnectedApps\SalesforceConnector;
use App\Modules\User\Services\ConnectedApps\ZohoConnector;
use App\Services\Integrations\PlatformServiceSettings;

/**
 * Single, data-driven source of truth for every app a creator can connect
 * from the "Connected Apps" area. Adding a new provider is a matter of
 * declaring one entry here (+ its adapter class) — the web UI, admin hub,
 * API and mobile all read from this registry, so nothing is hard-coded per
 * provider elsewhere.
 *
 * Each entry declares:
 *   - kind: 'crm' | 'analytics'
 *   - connect_type: 'oauth' (CRMs) | 'config' (GA measurement id + secret)
 *   - adapter: the service class implementing ConnectorContract
 *   - oauth: auth/token endpoints + scopes (oauth connect_type only)
 *   - default_field_mappings: sayzio_field => provider_field (crm only)
 *   - capabilities: which directions the provider supports
 *
 * Admin-provided credentials + "configured" checks are resolved lazily via
 * PlatformServiceSettings so absent credentials degrade to preview /
 * "coming soon" rather than breaking.
 */
class ConnectedAppRegistry
{
    /**
     * @return array<string,array<string,mixed>>
     */
    public static function all(): array
    {
        return [
            'salesforce' => [
                'key'          => 'salesforce',
                'label'        => 'Salesforce',
                'kind'         => 'crm',
                'icon'         => 'fab fa-salesforce',
                'color'        => '#00a1e0',
                'blurb'        => 'Push new leads, contacts, subscribers and form submissions into Salesforce, and pull Salesforce contacts back into Sayzio.',
                'connect_type' => 'oauth',
                'adapter'      => SalesforceConnector::class,
                'capabilities' => ['push' => true, 'pull' => true],
                'oauth'        => [
                    'auth_url'  => 'https://login.salesforce.com/services/oauth2/authorize',
                    'token_url' => 'https://login.salesforce.com/services/oauth2/token',
                    'scopes'    => ['api', 'refresh_token', 'offline_access'],
                ],
                'default_field_mappings' => [
                    'email'        => 'Email',
                    'first_name'   => 'FirstName',
                    'last_name'    => 'LastName',
                    'phone'        => 'Phone',
                    'company'      => 'Company',
                    'display_name' => 'LastName',
                ],
            ],
            'hubspot' => [
                'key'          => 'hubspot',
                'label'        => 'HubSpot',
                'kind'         => 'crm',
                'icon'         => 'fab fa-hubspot',
                'color'        => '#ff7a59',
                'blurb'        => 'Sync leads and contacts to HubSpot CRM in both directions, keeping your Sayzio address book and HubSpot in step.',
                'connect_type' => 'oauth',
                'adapter'      => HubspotConnector::class,
                'capabilities' => ['push' => true, 'pull' => true],
                'oauth'        => [
                    'auth_url'  => 'https://app.hubspot.com/oauth/authorize',
                    'token_url' => 'https://api.hubapi.com/oauth/v1/token',
                    'scopes'    => ['crm.objects.contacts.read', 'crm.objects.contacts.write', 'oauth'],
                ],
                'default_field_mappings' => [
                    'email'        => 'email',
                    'first_name'   => 'firstname',
                    'last_name'    => 'lastname',
                    'phone'        => 'phone',
                    'company'      => 'company',
                    'display_name' => 'lastname',
                ],
            ],
            'zoho' => [
                'key'          => 'zoho',
                'label'        => 'Zoho CRM',
                'kind'         => 'crm',
                'icon'         => 'fas fa-z',
                'color'        => '#e42527',
                'blurb'        => 'Connect Zoho CRM to push captured leads and contacts and pull Zoho records into Sayzio contacts.',
                'connect_type' => 'oauth',
                'adapter'      => ZohoConnector::class,
                'capabilities' => ['push' => true, 'pull' => true],
                'oauth'        => [
                    'auth_url'  => 'https://accounts.zoho.com/oauth/v2/auth',
                    'token_url' => 'https://accounts.zoho.com/oauth/v2/token',
                    'scopes'    => ['ZohoCRM.modules.ALL', 'ZohoCRM.users.READ', 'AaaServer.profile.READ'],
                ],
                'default_field_mappings' => [
                    'email'        => 'Email',
                    'first_name'   => 'First_Name',
                    'last_name'    => 'Last_Name',
                    'phone'        => 'Phone',
                    'company'      => 'Company',
                    'display_name' => 'Last_Name',
                ],
            ],
            'google_analytics' => [
                'key'          => 'google_analytics',
                'label'        => 'Google Analytics 4',
                'kind'         => 'analytics',
                'icon'         => 'fas fa-chart-line',
                'color'        => '#e8710a',
                'blurb'        => 'Forward link click and visitor events to your GA4 property server-side via the Measurement Protocol.',
                'connect_type' => 'config',
                'adapter'      => GoogleAnalyticsForwarder::class,
                'capabilities' => ['forward' => true],
                // Creator supplies these two on connect.
                'config_fields' => [
                    ['key' => 'measurement_id', 'label' => 'Measurement ID', 'placeholder' => 'G-XXXXXXXXXX', 'secret' => false],
                    ['key' => 'api_secret',     'label' => 'API Secret',     'placeholder' => 'Measurement Protocol API secret', 'secret' => true],
                ],
            ],
        ];
    }

    public static function provider(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function has(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    /** @return array<string,array<string,mixed>> */
    public static function crmProviders(): array
    {
        return array_filter(self::all(), fn ($p) => ($p['kind'] ?? null) === 'crm');
    }

    /**
     * Whether the platform has the admin-side prerequisites for this provider
     * to be *connectable* by creators. CRMs need admin OAuth client
     * credentials; GA only needs the feature switched on (the creator brings
     * their own property credentials). Absent ⇒ preview / coming-soon.
     */
    public static function isPlatformConfigured(string $key): bool
    {
        $meta = self::provider($key);
        if (!$meta) {
            return false;
        }
        if (($meta['connect_type'] ?? null) === 'oauth') {
            return PlatformServiceSettings::connectedAppConfigured($key);
        }
        if ($key === 'google_analytics') {
            return PlatformServiceSettings::googleAnalyticsEnabled();
        }
        return false;
    }

    /** {key,label,tone} status descriptor for admin/creator surfaces. */
    public static function status(string $key): array
    {
        return self::isPlatformConfigured($key)
            ? ['key' => 'configured', 'label' => 'Available', 'tone' => 'green']
            : ['key' => 'preview', 'label' => 'Coming soon', 'tone' => 'slate'];
    }
}
