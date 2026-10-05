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

    /**
     * The menu as it stands, for the model to change rather than replace.
     *
     * Compact on purpose: names and prices are what a brief refers to
     * ("make the dosas cheaper", "drop the Chinese section"), and sending
     * every description would double the token bill of a modify for
     * something the model is being told to leave alone anyway.
     *
     * Capped at the same limits a build may produce, so a menu that is
     * already at the ceiling does not generate a prompt the model cannot
     * answer within its output budget.
     */
    public function existingContext(Link $link): string
    {
        $menu = RestaurantMenu::where('link_id', $link->id)->first();
        if (! $menu) {
            return '';
        }

        $cats = RestaurantMenuCategory::where('menu_id', $menu->id)
            ->orderBy('sort_order')->orderBy('id')
            ->limit(self::MAX_CATEGORIES)->get();
        if ($cats->isEmpty()) {
            return '';
        }

        $lines = ['Current menu (currency '.$menu->currency.'):'];
        foreach ($cats as $cat) {
            $lines[] = '- '.$cat->name.($cat->is_active ? '' : ' [hidden]');
            $items = RestaurantMenuItem::where('menu_id', $menu->id)
                ->where('category_id', $cat->id)
                ->orderBy('sort_order')->orderBy('id')
                ->limit(self::MAX_ITEMS_PER_CATEGORY)->get();
            foreach ($items as $item) {
                $lines[] = '    - '.$item->name.' — '.$item->price
                    .($item->is_sold_out ? ' [sold out]' : '')
                    .($item->is_active ? '' : ' [hidden]');
            }
        }

        return implode("\n", $lines);
    }


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
      ],
      "subcategories": [
        { "name": "sub-section name", "description": "optional", "items": [ ...same shape... ] }
      ]
    }
  ]
}

Rules:
- 2 to 12 categories, 2 to 25 items per category.
- Use "subcategories" only when the source genuinely groups dishes under a
  heading inside a section ("Tiffins" holding "Steamed" and "Fried"). One
  level only. A flat section is normal; do not invent groupings.
- Realistic prices consistent with the brief's cuisine and market.
- Only reference image URLs the user explicitly supplied; keep them EXACTLY as given.
- Write real menu copy from the brief — never lorem ipsum.
PROMPT;
    }

    public function supportsLinks(): bool
    {
        return false;
    }

    /**
     * Sana, 2026-09-23: "here it should have modified version of extracting
     * content with pdf or multiple scanned images of menu card.... thats
     * usuall used by restro".
     *
     * He is right about what restaurants actually have: a laminated card, a
     * PDF the designer sent, photos taken on a phone. Typing it in again is
     * the reason a menu never gets onto the platform.
     */
    public function readsImages(): bool
    {
        return true;
    }

    /**
     * The scan instruction matters more here than for most things, because
     * a menu is a list of PRICES. A model that fills a smudged line with a
     * plausible number produces a live page that charges the wrong amount,
     * and nothing on the screen says which lines were read and which were
     * invented.
     */
    protected function scanInstruction(): string
    {
        return 'The attached images are photographs or scans of a real menu card. '
            .'Transcribe the sections, dishes and prices exactly as printed. '
            .'Keep the card\'s own order and its own groupings -- if dishes sit '
            .'under a heading inside a section, use "subcategories" for that. '
            .'Do NOT invent dishes, descriptions or prices. If a price is '
            .'unreadable, set it to 0 rather than guessing; a zero is visible '
            .'to the owner and a plausible wrong number is not. If the images '
            .'are not a menu, return an empty categories array.';
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

            $itemCount += $this->fillItems($menu, $category, $catIn['items'] ?? null, $currency, $images);

            // Sub-sections, one level deep, in the card's own order. A
            // printed card that groups "Idli" and "Dosa" under "Tiffins"
            // now survives the trip.
            $subsIn = is_array($catIn['subcategories'] ?? null) ? $catIn['subcategories'] : [];
            foreach (array_slice(array_values(array_filter($subsIn, 'is_array')), 0, self::MAX_CATEGORIES) as $si => $subIn) {
                $subName = $this->str($subIn['name'] ?? null, 120);
                if ($subName === null) continue;

                $sub = RestaurantMenuCategory::create([
                    'menu_id'     => $menu->id,
                    'parent_id'   => $category->id,
                    'name'        => $subName,
                    'description' => $this->str($subIn['description'] ?? null, 500),
                    'sort_order'  => $si,
                    'is_active'   => true,
                ]);
                $catCount++;
                $itemCount += $this->fillItems($menu, $sub, $subIn['items'] ?? null, $currency, $images);
            }
        }

        if ($itemCount === 0) {
            throw new \RuntimeException('The AI response contained no usable menu items. Your coins were refunded — please try again.');
        }

        return ['categories' => $catCount, 'items' => $itemCount];
    }

    /**
     * Write one section's items. Shared by sections and their
     * sub-sections, because the two differ only in which row they hang off
     * and a second copy is how the sub-sections end up missing a field.
     */
    private function fillItems(RestaurantMenu $menu, RestaurantMenuCategory $category, mixed $itemsIn, string $currency, array $images): int
    {
        $itemsIn = is_array($itemsIn) ? $itemsIn : [];
        $written = 0;

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
            $written++;
        }

        return $written;
    }
}
