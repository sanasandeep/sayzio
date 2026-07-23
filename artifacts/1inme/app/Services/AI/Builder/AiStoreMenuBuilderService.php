<?php

namespace App\Services\AI\Builder;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\StoreCategory;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreProduct;
use App\Modules\User\Models\User;

/**
 * AI builder for the Store link type: categories → products with prices,
 * replacing any previously generated catalogue.
 */
class AiStoreMenuBuilderService extends AbstractAiTypeBuilderService
{
    public const FEATURE = 'store_menu_builder';

    public const MAX_CATEGORIES = 12;
    public const MAX_PRODUCTS_PER_CATEGORY = 25;

    public function feature(): string  { return self::FEATURE; }
    public function linkType(): string { return Link::TYPE_STORE_MENU; }
    public function label(): string    { return 'AI Store builder'; }

    protected function systemPrompt(User $user): string
    {
        return <<<'PROMPT'
You are a product catalogue writer for a small store. Answer with ONE JSON object only — no prose, no markdown fences.

Schema:
{
  "currency": "3-letter ISO code, e.g. USD",
  "categories": [
    {
      "name": "category name (max 120 chars)",
      "description": "optional short description",
      "products": [
        {
          "name": "product name (max 160 chars)",
          "description": "optional persuasive one-liner",
          "price": 19.99,
          "photo_url": "ONLY a supplied image URL, else omit"
        }
      ]
    }
  ]
}

Rules:
- 2 to 12 categories, 2 to 25 products per category.
- Realistic prices consistent with the brief's products and market.
- Only reference image URLs the user explicitly supplied; keep them EXACTLY as given.
- Write real product copy from the brief — never lorem ipsum.
PROMPT;
    }

    public function supportsLinks(): bool
    {
        return false;
    }

    protected function materialize(User $user, Link $link, array $parsed, array $links, array $images): array
    {
        $menu = StoreMenu::firstOrCreate(
            ['link_id' => $link->id],
            ['user_id' => $link->user_id, 'currency' => 'USD'],
        );

        $currency = strtoupper((string) ($parsed['currency'] ?? ''));
        if (preg_match('/^[A-Z]{3}$/', $currency) && $currency !== $menu->currency) {
            $menu->update(['currency' => $currency]);
        }
        $currency = $menu->fresh()->currency;

        // Replace the previous catalogue wholesale.
        StoreProduct::where('menu_id', $menu->id)->delete();
        StoreCategory::where('menu_id', $menu->id)->delete();

        $categoriesIn = is_array($parsed['categories'] ?? null) ? $parsed['categories'] : [];
        $categoriesIn = array_slice(array_values(array_filter($categoriesIn, 'is_array')), 0, self::MAX_CATEGORIES);

        $catCount = 0;
        $productCount = 0;

        foreach ($categoriesIn as $ci => $catIn) {
            $name = $this->str($catIn['name'] ?? null, 120);
            if ($name === null) continue;

            $category = StoreCategory::create([
                'menu_id'     => $menu->id,
                'name'        => $name,
                'description' => $this->str($catIn['description'] ?? null, 500),
                'sort_order'  => $ci,
                'is_active'   => true,
            ]);
            $catCount++;

            $productsIn = is_array($catIn['products'] ?? null) ? $catIn['products'] : [];
            foreach (array_slice(array_values(array_filter($productsIn, 'is_array')), 0, self::MAX_PRODUCTS_PER_CATEGORY) as $pi => $productIn) {
                $productName = $this->str($productIn['name'] ?? null, 160);
                if ($productName === null) continue;

                StoreProduct::create([
                    'menu_id'         => $menu->id,
                    'category_id'     => $category->id,
                    'name'            => $productName,
                    'description'     => $this->str($productIn['description'] ?? null, 500),
                    'price'           => $this->price($productIn['price'] ?? 0),
                    'currency'        => $currency,
                    'photo_url'       => $this->suppliedImage($productIn['photo_url'] ?? null, $images),
                    'sort_order'      => $pi,
                    'is_out_of_stock' => false,
                    'is_active'       => true,
                ]);
                $productCount++;
            }
        }

        if ($productCount === 0) {
            throw new \RuntimeException('The AI response contained no usable products. Your coins were refunded — please try again.');
        }

        return ['categories' => $catCount, 'products' => $productCount];
    }
}
