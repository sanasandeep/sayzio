<?php

namespace App\Modules\Common\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeoIpService
{
    protected array $privateRanges = ['127.0.0.1', '::1', '0.0.0.0'];

    public function lookup(string $ip): array
    {
        if ($this->isPrivateIp($ip)) {
            return [];
        }

        return Cache::remember("geoip:{$ip}", 3600, function () use ($ip) {
            return $this->fetchFromApi($ip);
        });
    }

    public function detectCountry(string $ip): ?string
    {
        $data = $this->lookup($ip);
        return $data['country_code'] ?? null;
    }

    public function detectCity(string $ip): ?string
    {
        $data = $this->lookup($ip);
        return $data['city'] ?? null;
    }

    /**
     * Returns ['latitude' => float, 'longitude' => float] or null.
     * Falls back to the offline CityLookupService if the IP API
     * doesn't return coordinates (or wasn't reachable).
     */
    public function detectCoordinates(string $ip): ?array
    {
        $data = $this->lookup($ip);
        if (!empty($data['latitude']) && !empty($data['longitude'])) {
            return [
                'latitude'  => (float) $data['latitude'],
                'longitude' => (float) $data['longitude'],
            ];
        }
        // Fallback: city + country → coords from the offline DB
        $city = $data['city'] ?? null;
        $cc   = $data['country_code'] ?? null;
        if ($city && $cc) {
            return app(CityLookupService::class)->lookup($city, $cc);
        }
        return null;
    }

    /**
     * One-shot helper that returns the full enriched geo bundle
     * for a click/session row: country_code, city, latitude, longitude.
     */
    public function detectGeo(string $ip): array
    {
        $data = $this->lookup($ip);
        $cc   = $data['country_code'] ?? null;
        $city = $data['city'] ?? null;
        $lat  = isset($data['latitude'])  ? (float) $data['latitude']  : null;
        $lng  = isset($data['longitude']) ? (float) $data['longitude'] : null;

        if (($lat === null || $lng === null) && $city && $cc) {
            $coords = app(CityLookupService::class)->lookup($city, $cc);
            if ($coords) {
                $lat = $lat ?? $coords['latitude'];
                $lng = $lng ?? $coords['longitude'];
            }
        }

        return [
            'country_code' => $cc,
            'city'         => $city,
            'latitude'     => $lat,
            'longitude'    => $lng,
        ];
    }

    protected function isPrivateIp(string $ip): bool
    {
        if (in_array($ip, $this->privateRanges)) {
            return true;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    protected function fetchFromApi(string $ip): array
    {
        try {
            $response = Http::timeout(2)
                ->connectTimeout(2)
                ->get("https://ipapi.co/{$ip}/json/");

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['country_code'])) {
                    return [
                        'country_code' => $data['country_code'],
                        'city'         => $data['city'] ?? null,
                        'latitude'     => isset($data['latitude'])  ? (float) $data['latitude']  : null,
                        'longitude'    => isset($data['longitude']) ? (float) $data['longitude'] : null,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::debug('GeoIP lookup failed: ' . $e->getMessage());
        }

        return [];
    }
}
