<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\User\Models\Domain;

/**
 * Host-aware branding resolver.
 *
 * When a visitor is on a NON-primary global domain (an admin-managed row in
 * the `domains` table that is not flagged primary), that domain's own logos
 * replace the platform logos everywhere the brand-logo partial is rendered.
 * Any logo slot the domain hasn't customised falls back to the platform
 * AppSetting branding so nothing renders broken. On the primary domain (or
 * any host with no matching non-primary global row) the platform branding is
 * returned unchanged.
 *
 * Both lookups are cached per-request: the brand-logo partial is included on
 * almost every page multiple times (header + footer), so we only ever issue
 * a single domain query per request.
 */
class DomainBranding
{
    /** @var array<string,array{logo_light:string,logo_dark:string,icon:string}> */
    private static array $logoCache = [];

    private static bool $domainResolved = false;

    private static ?Domain $domain = null;

    /**
     * The non-primary global domain matching the current request host, if
     * any. Returns null on the primary domain, on configured platform hosts
     * with no matching row, on CLI, or when the host isn't a global domain.
     */
    public static function currentGlobalDomain(): ?Domain
    {
        if (self::$domainResolved) {
            return self::$domain;
        }
        self::$domainResolved = true;

        $host = PlatformHosts::normalize(self::requestHost());
        if ($host === null) {
            return self::$domain = null;
        }

        try {
            self::$domain = Domain::query()
                ->whereNull('user_id')
                ->where('is_primary', false)
                ->where('domain', $host)
                ->first();
        } catch (\Throwable) {
            // domains table / columns not migrated yet — behave as platform.
            self::$domain = null;
        }

        return self::$domain;
    }

    /**
     * The active logo set for the current host.
     *
     * @return array{logo_light:string,logo_dark:string,icon:string}
     */
    public static function logos(): array
    {
        $host = PlatformHosts::normalize(self::requestHost()) ?? '__cli__';
        if (array_key_exists($host, self::$logoCache)) {
            return self::$logoCache[$host];
        }

        $logos = self::platformDefaults();

        $domain = self::currentGlobalDomain();
        if ($domain) {
            $logos = [
                'logo_light' => $domain->brand_logo_light_url ?: $logos['logo_light'],
                'logo_dark'  => $domain->brand_logo_dark_url ?: $logos['logo_dark'],
                'icon'       => $domain->brand_icon_url ?: $logos['icon'],
            ];
        }

        return self::$logoCache[$host] = $logos;
    }

    /**
     * Relationship payload for the non-primary domain's landing page, or
     * null when the current host is the primary domain / not a global
     * domain. Uses the admin-edited blurb when set, otherwise a sensible
     * default referencing the primary domain.
     *
     * @return array{domain:string,primary_domain:string,primary_url:string,blurb:string}|null
     */
    public static function relationship(): ?array
    {
        $domain = self::currentGlobalDomain();
        if (!$domain) {
            return null;
        }

        $primary = null;
        try {
            $primary = Domain::primary();
        } catch (\Throwable) {
            $primary = null;
        }
        $primaryName = $primary?->domain ?: PlatformHosts::primary();

        $blurb = trim((string) ($domain->relationship_blurb ?? ''));
        if ($blurb === '') {
            $blurb = "{$domain->domain} is part of {$primaryName} — the same product, same account, same team.";
        }

        return [
            'domain'         => $domain->domain,
            'primary_domain' => $primaryName,
            'primary_url'    => 'https://' . $primaryName,
            'blurb'          => $blurb,
        ];
    }

    /**
     * @return array{logo_light:string,logo_dark:string,icon:string}
     */
    private static function platformDefaults(): array
    {
        return [
            'logo_light' => AppSetting::get('brand_logo_light_url', '/branding/logo-light.png'),
            'logo_dark'  => AppSetting::get('brand_logo_dark_url', '/branding/logo-dark.png'),
            'icon'       => AppSetting::get('brand_icon_url', '/branding/icon.jpg'),
        ];
    }

    private static function requestHost(): ?string
    {
        try {
            return request()->getHost();
        } catch (\Throwable) {
            return null;
        }
    }
}
