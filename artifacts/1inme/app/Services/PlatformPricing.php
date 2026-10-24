<?php

namespace App\Services;

use Illuminate\Http\Request;

class PlatformPricing
{
    public static function platform(Request $request): string
    {
        return $request->validate(['platform' => 'sometimes|in:web,ios,android'])['platform'] ?? 'web';
    }

    public static function defaultMinor(int $webMinor): int
    {
        // Both supported currencies use 100 minor units per major unit.
        return (int) round($webMinor / 70, 0, PHP_ROUND_HALF_UP) * 100;
    }

    public static function price($model, string $currency, string $cycle, string $platform = 'web'): array
    {
        $price = PricingResolver::priceForCurrency($model, $currency, $cycle);
        if (in_array($platform, ['ios', 'android'], true)) {
            $row = $model->prices->first(fn ($row) => $row->currency === $currency && $row->billing_cycle === $cycle && $row->is_active);
            $minor = $row?->{$platform.'_amount_minor'};
            $price['amount_minor'] = $minor === null ? self::defaultMinor($price['amount_minor']) : (int) $minor;
            $price['formatted'] = PricingResolver::money($price['amount_minor'], $currency);
        }

        return $price + ['platform' => $platform];
    }

    public static function rules(): array
    {
        return [
            'platform_prices' => 'sometimes|array:USD,INR',
            'platform_prices.*' => 'array:monthly,annual',
            'platform_prices.*.*' => 'array:ios,android',
            'platform_prices.*.*.*' => 'nullable|integer|min:0|max:100000000000',
        ];
    }

    public static function save($model, array $input): void
    {
        foreach ($input as $currency => $cycles) {
            foreach ($cycles as $cycle => $platforms) {
                $row = $model->prices()->where('currency', $currency)->where('billing_cycle', $cycle)->first();
                if (! $row) {
                    continue;
                }
                foreach ($platforms as $platform => $minor) {
                    $row->{$platform.'_amount_minor'} = $minor;
                }
                $row->save();
            }
        }
        $model->unsetRelation('prices');
    }
}
