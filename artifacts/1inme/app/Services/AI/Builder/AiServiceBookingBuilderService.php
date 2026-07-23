<?php

namespace App\Services\AI\Builder;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\ServiceBooking;
use App\Modules\User\Models\ServiceBookingCategory;
use App\Modules\User\Models\ServiceBookingService;
use App\Modules\User\Models\User;

/**
 * AI builder for the Service Booking link type: categories → services with
 * prices and durations, replacing any previously generated catalogue.
 */
class AiServiceBookingBuilderService extends AbstractAiTypeBuilderService
{
    public const FEATURE = 'service_booking_builder';

    public const MAX_CATEGORIES = 12;
    public const MAX_SERVICES_PER_CATEGORY = 25;

    public function feature(): string  { return self::FEATURE; }
    public function linkType(): string { return Link::TYPE_SERVICE_BOOKING; }
    public function label(): string    { return 'AI Service Booking builder'; }

    protected function systemPrompt(User $user): string
    {
        return <<<'PROMPT'
You are a services-menu writer for an appointment-booking page. Answer with ONE JSON object only — no prose, no markdown fences.

Schema:
{
  "currency": "3-letter ISO code, e.g. USD",
  "categories": [
    {
      "name": "category name (max 120 chars)",
      "description": "optional short description",
      "services": [
        {
          "name": "service name (max 160 chars)",
          "description": "optional one-liner about what's included",
          "price": 45,
          "duration_minutes": 60,
          "photo_url": "ONLY a supplied image URL, else omit"
        }
      ]
    }
  ]
}

Rules:
- 1 to 12 categories, 1 to 25 services per category.
- duration_minutes must be between 5 and 1440 and realistic for the service.
- Realistic prices consistent with the brief's trade and market.
- Only reference image URLs the user explicitly supplied; keep them EXACTLY as given.
- Write real service copy from the brief — never lorem ipsum.
PROMPT;
    }

    public function supportsLinks(): bool
    {
        return false;
    }

    protected function materialize(User $user, Link $link, array $parsed, array $links, array $images): array
    {
        $booking = ServiceBooking::firstOrCreate(
            ['link_id' => $link->id],
            [
                'user_id'             => $link->user_id,
                'currency'            => 'USD',
                'slot_length_minutes' => 30,
                'lead_time_minutes'   => 60,
                'max_days_ahead'      => 30,
                'timezone'            => 'UTC',
            ],
        );

        $currency = strtoupper((string) ($parsed['currency'] ?? ''));
        if (preg_match('/^[A-Z]{3}$/', $currency) && $currency !== $booking->currency) {
            $booking->update(['currency' => $currency]);
        }
        $currency = $booking->fresh()->currency;

        // Replace the previous catalogue wholesale.
        ServiceBookingService::where('service_booking_id', $booking->id)->delete();
        ServiceBookingCategory::where('service_booking_id', $booking->id)->delete();

        $categoriesIn = is_array($parsed['categories'] ?? null) ? $parsed['categories'] : [];
        $categoriesIn = array_slice(array_values(array_filter($categoriesIn, 'is_array')), 0, self::MAX_CATEGORIES);

        $catCount = 0;
        $serviceCount = 0;

        foreach ($categoriesIn as $ci => $catIn) {
            $name = $this->str($catIn['name'] ?? null, 120);
            if ($name === null) continue;

            $category = ServiceBookingCategory::create([
                'service_booking_id' => $booking->id,
                'name'               => $name,
                'description'        => $this->str($catIn['description'] ?? null, 500),
                'sort_order'         => $ci,
                'is_active'          => true,
            ]);
            $catCount++;

            $servicesIn = is_array($catIn['services'] ?? null) ? $catIn['services'] : [];
            foreach (array_slice(array_values(array_filter($servicesIn, 'is_array')), 0, self::MAX_SERVICES_PER_CATEGORY) as $si => $serviceIn) {
                $serviceName = $this->str($serviceIn['name'] ?? null, 160);
                if ($serviceName === null) continue;

                $duration = is_numeric($serviceIn['duration_minutes'] ?? null) ? (int) $serviceIn['duration_minutes'] : 60;

                ServiceBookingService::create([
                    'service_booking_id' => $booking->id,
                    'category_id'        => $category->id,
                    'name'               => $serviceName,
                    'description'        => $this->str($serviceIn['description'] ?? null, 500),
                    'price'              => $this->price($serviceIn['price'] ?? 0),
                    'currency'           => $currency,
                    'duration_minutes'   => max(5, min(1440, $duration)),
                    'photo_url'          => $this->suppliedImage($serviceIn['photo_url'] ?? null, $images),
                    'sort_order'         => $si,
                    'is_unavailable'     => false,
                    'is_active'          => true,
                ]);
                $serviceCount++;
            }
        }

        if ($serviceCount === 0) {
            throw new \RuntimeException('The AI response contained no usable services. Your coins were refunded — please try again.');
        }

        return ['categories' => $catCount, 'services' => $serviceCount];
    }
}
