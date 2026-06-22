<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Support\DomainBranding;

/**
 * Public, CORS-open JSON feed of the admin-configured brand logos.
 *
 * Consumed by the standalone marketing site (1inme.com) and the mobile app so
 * they render the same admin-set logo as the product app, switching the
 * light/dark variant with the client's own theme. Resolution is delegated to
 * DomainBranding (host-aware: a non-primary global domain serves its own
 * logos, otherwise the platform AppSetting branding), so there is no duplicate
 * resolution logic here.
 *
 * Relative stored paths (e.g. "/branding/logo-light.png") are made absolute
 * because the consumers are different origins and would otherwise resolve them
 * against their own domain and 404.
 */
class BrandingFeedController extends Controller
{
    public function feed()
    {
        $logos = DomainBranding::logos();

        $data = [
            'logoLight' => $this->absoluteUrl($logos['logo_light']),
            'logoDark'  => $this->absoluteUrl($logos['logo_dark']),
            'icon'      => $this->absoluteUrl($logos['icon']),
        ];

        return response()->json(['data' => $data], 200, $this->corsHeaders());
    }

    public function feedPreflight()
    {
        return response('', 204, $this->corsHeaders());
    }

    private function absoluteUrl(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return url($path);
    }

    private function corsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With',
            'Access-Control-Max-Age'       => '600',
            'Cache-Control'                => 'public, max-age=300',
        ];
    }
}
