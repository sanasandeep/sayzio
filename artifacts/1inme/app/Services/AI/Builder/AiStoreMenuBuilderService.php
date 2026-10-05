<?php

namespace App\Services\AI\Builder;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\StoreCategory;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreProduct;
use App\Modules\User\Models\User;
use App\Services\AI\Editor\MenuEditPlan;
use App\Services\AI\Editor\MenuEditVocabulary;

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

    /**
     * The catalogue as it stands, for the model to change rather than
     * replace. Same shape and the same reasoning as the restaurant
     * builder's -- see that one.
     */
    public function existingContext(Link $link): string
    {
        $menu = StoreMenu::where('link_id', $link->id)->first();
        if (! $menu) {
            return '';
        }

        $cats = StoreCategory::where('menu_id', $menu->id)
            ->orderBy('sort_order')->orderBy('id')
            ->limit(self::MAX_CATEGORIES)->get();
        if ($cats->isEmpty()) {
            return '';
        }

        $lines = ['Current catalogue (currency '.$menu->currency.'):'];
        foreach ($cats as $cat) {
            $lines[] = '- '.$cat->name.($cat->is_active ? '' : ' [hidden]');
            $items = StoreProduct::where('menu_id', $menu->id)
                ->where('category_id', $cat->id)
                ->orderBy('sort_order')->orderBy('id')
                ->limit(self::MAX_PRODUCTS_PER_CATEGORY)->get();
            foreach ($items as $item) {
                $lines[] = '    - '.$item->name.' — '.$item->price
                    .($item->is_active ? '' : ' [hidden]');
            }
        }

        return implode("\n", $lines);
    }


    /**
     * A catalogue that already exists is EDITED, not rebuilt.
     *
     * Store/restaurant parity, which Sana named a must-have: the two page
     * types share one applier, so "the AI can change the price" cannot
     * become true on one of them and not the other.
     */
    public function supportsEditing(): bool
    {
        return true;
    }

    public function editAbilities(): array
    {
        return MenuEditVocabulary::abilities('product');
    }

    protected function editPrompt(User $user): string
    {
        return MenuEditVocabulary::prompt('store catalogue', 'product');
    }

    protected function applyPlan(User $user, Link $link, array $parsed): array
    {
        $menu = StoreMenu::where('link_id', $link->id)->firstOrFail();

        $operations = is_array($parsed['operations'] ?? null) ? $parsed['operations'] : [];

        return MenuEditPlan::apply($operations, $menu, $link, [
            'category'  => StoreCategory::class,
            'item'      => StoreProduct::class,
            'sold_out'  => 'is_out_of_stock',
            'item_noun' => 'product',
        ]);
    }

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
      ],
      "subcategories": [
        { "name": "sub-section name", "description": "optional", "products": [ ...same shape... ] }
      ]
    }
  ]
}

Rules:
- 2 to 12 categories, 2 to 25 products per category.
- Use "subcategories" only when the source genuinely groups products under a
  heading inside a section. One level only. A flat section is normal; do not
  invent groupings.
- Realistic prices consistent with the brief's products and market.
- Only reference image URLs the user explicitly supplied; keep them EXACTLY as given.
- Write real product copy from the brief — never lorem ipsum.
PROMPT;
    }

    public function supportsLinks(): bool
    {
        return false;
    }

    /** A store's catalogue arrives as a printed sheet just as often. */
    public function readsImages(): bool
    {
        return true;
    }

    protected function scanInstruction(): string
    {
        return 'The attached images are photographs or scans of a real price '
            .'list or catalogue sheet. Transcribe the sections, products and '
            .'prices exactly as printed, keeping the sheet\'s own order and its '
            .'own groupings -- if products sit under a heading inside a section, '
            .'use "subcategories" for that. Do NOT invent products, descriptions '
            .'or prices. If a price is unreadable, set it to 0 rather than '
            .'guessing; a zero is visible to the owner and a plausible wrong '
            .'number is not. If the images are not a product list, return an '
            .'empty categories array.';
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

            $productCount += $this->fillProducts($menu, $category, $catIn['products'] ?? null, $currency, $images);

            $subsIn = is_array($catIn['subcategories'] ?? null) ? $catIn['subcategories'] : [];
            foreach (array_slice(array_values(array_filter($subsIn, 'is_array')), 0, self::MAX_CATEGORIES) as $si => $subIn) {
                $subName = $this->str($subIn['name'] ?? null, 120);
                if ($subName === null) continue;

                $sub = StoreCategory::create([
                    'menu_id'     => $menu->id,
                    'parent_id'   => $category->id,
                    'name'        => $subName,
                    'description' => $this->str($subIn['description'] ?? null, 500),
                    'sort_order'  => $si,
                    'is_active'   => true,
                ]);
                $catCount++;
                $productCount += $this->fillProducts($menu, $sub, $subIn['products'] ?? null, $currency, $images);
            }
        }

        if ($productCount === 0) {
            throw new \RuntimeException('The AI response contained no usable products. Your coins were refunded — please try again.');
        }

        return ['categories' => $catCount, 'products' => $productCount];
    }

    /**
     * Write one section's products. Shared by sections and sub-sections --
     * a second copy is how the sub-sections end up missing a field.
     */
    private function fillProducts(StoreMenu $menu, StoreCategory $category, mixed $productsIn, string $currency, array $images): int
    {
        $productsIn = is_array($productsIn) ? $productsIn : [];
        $written = 0;

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
            $written++;
        }

        return $written;
    }
}
