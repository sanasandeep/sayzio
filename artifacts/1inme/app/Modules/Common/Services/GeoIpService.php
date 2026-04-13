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
                        'city' => $data['city'] ?? null,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::debug('GeoIP lookup failed: ' . $e->getMessage());
        }

        return [];
    }
}
