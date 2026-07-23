<?php

namespace App\Services\AI\Builder;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\User;

/**
 * AI builder for the Restaurant Menu link type: categories → items with
 * prices, replacing any previously generated catalogue.
 */
class AiRestaurantMenuBuilderService extends AbstractAiTypeBuilderService
{
    public const FEATURE = 'restaurant_menu_builder';

    public const MAX_CATEGORIES = 12;
    public const MAX_ITEMS_PER_CATEGORY = 25;

    public function feature(): string  { return self::FEATURE; }
    public function linkType(): string { return Link::TYPE_RESTAURANT_MENU; }
    public function label(): string    { return 'AI Restaurant Menu builder'; }

    protected function systemPrompt(User $user): string
    {
        return <<<'PROMPT'
You are a restaurant menu writer. Answer with ONE JSON object only — no prose, no markdown fences.

Schema:
{
  "currency": "3-letter ISO code, e.g. USD",
  "categories": [
    {
      "name": "category name (max 120 chars)",
      "description": "optional short description",
      "items": [
        {
          "name": "dish name (max 160 chars)",
          "description": "optional appetizing one-liner",
          "price": 12.5,
          "photo_url": "ONLY a supplied image URL, else omit"
        }
      ]
    }
  ]
}

Rules:
- 2 to 12 categories, 2 to 25 items per category.
- Realistic prices consistent with the brief's cuisine and market.
- Only reference image URLs the user explicitly supplied; keep them EXACTLY as given.
- Write real menu copy from the brief — never lorem ipsum.
PROMPT;
    }

    public function supportsLinks(): bool
    {
        return false;
    }

    protected function materialize(User $user, Link $link, array $parsed, array $links, array $images): array
    {
        $menu = RestaurantMenu::firstOrCreate(
            ['link_id' => $link->id],
            ['user_id' => $link->user_id, 'mode' => RestaurantMenu::MODE_DISPLAY, 'currency' => 'USD'],
        );

        $currency = strtoupper((string) ($parsed['currency'] ?? ''));
        if (preg_match('/^[A-Z]{3}$/', $currency) && $currency !== $menu->currency) {
            $menu->update(['currency' => $currency]);
        }
        $currency = $menu->fresh()->currency;

        // Replace the previous catalogue wholesale.
        RestaurantMenuItem::where('menu_id', $menu->id)->delete();
        RestaurantMenuCategory::where('menu_id', $menu->id)->delete();

        $categoriesIn = is_array($parsed['categories'] ?? null) ? $parsed['categories'] : [];
        $categoriesIn = array_slice(array_values(array_filter($categoriesIn, 'is_array')), 0, self::MAX_CATEGORIES);

        $catCount = 0;
        $itemCount = 0;

        foreach ($categoriesIn as $ci => $catIn) {
            $name = $this->str($catIn['name'] ?? null, 120);
            if ($name === null) continue;

            $category = RestaurantMenuCategory::create([
                'menu_id'     => $menu->id,
                'name'        => $name,
                'description' => $this->str($catIn['description'] ?? null, 500),
                'sort_order'  => $ci,
                'is_active'   => true,
            ]);
            $catCount++;

            $itemsIn = is_array($catIn['items'] ?? null) ? $catIn['items'] : [];
            foreach (array_slice(array_values(array_filter($itemsIn, 'is_array')), 0, self::MAX_ITEMS_PER_CATEGORY) as $ii => $itemIn) {
                $itemName = $this->str($itemIn['name'] ?? null, 160);
                if ($itemName === null) continue;

                RestaurantMenuItem::create([
                    'menu_id'     => $menu->id,
                    'category_id' => $category->id,
                    'name'        => $itemName,
                    'description' => $this->str($itemIn['description'] ?? null, 500),
                    'price'       => $this->price($itemIn['price'] ?? 0),
                    'currency'    => $currency,
                    'photo_url'   => $this->suppliedImage($itemIn['photo_url'] ?? null, $images),
                    'sort_order'  => $ii,
                    'is_sold_out' => false,
                    'is_active'   => true,
                ]);
                $itemCount++;
            }
        }

        if ($itemCount === 0) {
            throw new \RuntimeException('The AI response contained no usable menu items. Your coins were refunded — please try again.');
        }

        return ['categories' => $catCount, 'items' => $itemCount];
    }
}
