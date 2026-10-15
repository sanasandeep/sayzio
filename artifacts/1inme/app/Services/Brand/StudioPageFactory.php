<?php

namespace App\Services\Brand;

use App\Modules\User\Models\BrandStudioKit;
use App\Modules\User\Models\Calendar;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\User;
use App\Support\PlatformTimezone;

/** Native page adapters. Catalog facts are reviewed before these drafts are created. */
class StudioPageFactory
{
    public function sanitize(string $kind, array $asset): array
    {
        $text = fn ($value, $max) => mb_substr(trim(is_scalar($value) ? (string) $value : ''), 0, $max);
        $color = $text($asset['theme_color'] ?? '', 7);
        $currency = $text($asset['currency'] ?? '', 10);
        $categories = [];
        foreach (array_slice((array) ($asset['categories'] ?? []), 0, 8) as $category) {
            if (! is_array($category) || ! $name = $text($category['name'] ?? '', 120)) {
                continue;
            }
            $items = [];
            foreach (array_slice((array) ($category['items'] ?? []), 0, 12) as $item) {
                if (! is_array($item) || ! $itemName = $text($item['name'] ?? '', 160)) {
                    continue;
                }
                // Never turn missing prices into a free item.
                if (! is_numeric($item['price'] ?? null) || $item['price'] < 0 || $item['price'] > 99999999) {
                    continue;
                }
                $items[] = ['name' => $itemName, 'description' => $text($item['description'] ?? '', 1000), 'price' => round((float) $item['price'], 2)];
            }
            if ($items) {
                $categories[] = ['name' => $name, 'items' => $items];
            }
        }

        return ['kind' => $kind, 'title' => $text($asset['title'] ?? '', 160) ?: AiBrandStudioService::KIND_LABELS[$kind], 'description' => $text($asset['description'] ?? '', 1000), 'theme_color' => preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#3d6bff', 'currency' => preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'USD', 'categories' => $kind === 'calendar' ? [] : $categories];
    }

    public function create(User $owner, BrandStudioKit $kit, array $asset, array &$counts): array
    {
        $kind = $asset['kind'];
        $cap = ['calendar' => 'max_calendars', 'restaurant_menu' => 'max_restaurant_menu', 'store_menu' => 'max_store_menu'][$kind];
        if (! $owner->getPlanFeature('module_'.$kind, true)) {
            throw new PlanCapReachedException(AiBrandStudioService::KIND_LABELS[$kind].' are not included in your plan.');
        }
        if (! $owner->planUnderLimit('max_links', $counts['links'], 0) || ! $owner->planUnderLimit($cap, Link::withoutGlobalScope('workspace')->where('user_id', $owner->id)->where('type', $kind)->count(), -1)) {
            throw new PlanCapReachedException('Your plan limit for '.AiBrandStudioService::KIND_LABELS[$kind].' was reached.');
        }
        if (in_array($kind, Link::BIOLINK_FAMILY, true) && ! $owner->planUnderLimit('max_biolinks', $counts['biolinks'], 0)) {
            throw new PlanCapReachedException('Your plan’s Link in Bio family limit was reached.');
        }
        $link = Link::create(['user_id' => $owner->id, 'type' => $kind, 'title' => $asset['title'], 'alias' => Link::generateAlias(), 'is_active' => false, 'settings' => ['brand_studio_kit_id' => $kit->id, 'biolink' => ['theme_color' => $asset['theme_color']]]]);
        if ($kind === 'calendar') {
            $calendar = Calendar::create(['link_id' => $link->id, 'user_id' => $owner->id, 'workspace_id' => $link->workspace_id, 'title' => $link->title, 'slug' => $link->alias, 'description' => $asset['description'], 'accent_color' => $asset['theme_color'], 'timezone' => PlatformTimezone::forUser($owner), 'is_public' => false]);
            $link->calendar_id = $calendar->id;
            $link->save();
        } else {
            $class = $kind === 'restaurant_menu' ? RestaurantMenu::class : StoreMenu::class;
            $menu = $class::create(['link_id' => $link->id, 'user_id' => $owner->id, 'mode' => 'display', 'currency' => $asset['currency'], 'accent_color' => $asset['theme_color'], 'settings' => ['description' => $asset['description']]]);
            foreach ($asset['categories'] as $order => $category) {
                $row = $menu->categories()->create(['name' => $category['name'], 'sort_order' => $order]);
                $relation = $kind === 'restaurant_menu' ? $menu->items() : $menu->products();
                foreach ($category['items'] as $index => $item) {
                    $relation->create($item + ['category_id' => $row->id, 'currency' => $asset['currency'], 'sort_order' => $index, 'is_active' => true]);
                }
            }
        }
        $counts['links']++;
        if (in_array($kind, Link::BIOLINK_FAMILY, true)) {
            $counts['biolinks']++;
        }

        return ['kind' => $kind, 'id' => $link->id, 'title' => $link->title, 'alias' => $link->alias];
    }
}
