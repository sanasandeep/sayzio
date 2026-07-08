<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Http\Request;

/**
 * Admin toggles for the "Perfect Pairings" cross-promo cards shown on public
 * link-type pages (biolink, resume, reviews, restaurant menu, store, event).
 *
 * The catalog itself stays code-defined in
 * SitePagesContent::linkTypePairingsCatalog(); this screen lets admins
 * check/uncheck individual cards per page type AND override each card's
 * name/benefit copy. The disabled set is stored in app_settings
 * (SitePagesContent::LINK_TYPE_PAIRINGS_DISABLED_KEY) and copy overrides in
 * SitePagesContent::LINK_TYPE_PAIRINGS_COPY_KEY; both are applied centrally
 * in SitePagesContent::linkTypePairingsFor(), so web public pages and every
 * mobile-consumed API `pairings` payload respect the settings with no other
 * changes. Blank or default-matching copy fields are not stored, so clearing
 * a field (or the per-card reset) falls back to the shipped default.
 */
class LinkTypePairingsController extends Controller
{
    /** Human-readable label per pairing page key, for the settings UI. */
    private const PAGE_LABELS = [
        'ics'             => 'Event pages',
        'restaurant_menu' => 'Restaurant menu pages',
        'store_menu'      => 'Store pages',
        'resume'          => 'Resume pages',
        'reviews'         => 'Reviews pages',
        'biolink'         => 'Biolink pages',
    ];

    public function index()
    {
        $catalog = SitePagesContent::linkTypePairingsCatalog();
        $disabled = SitePagesContent::linkTypePairingsDisabledMap();
        $copy = SitePagesContent::linkTypePairingsCopyMap();

        $sections = [];
        foreach ($catalog as $pageKey => $items) {
            $items = array_map(function ($item) use ($copy, $pageKey) {
                $override = $copy[$pageKey][$item['type'] ?? ''] ?? [];
                $item['default_name'] = $item['name'];
                $item['default_benefit'] = $item['benefit'];
                $item['name'] = $override['name'] ?? $item['name'];
                $item['benefit'] = $override['benefit'] ?? $item['benefit'];

                return $item;
            }, $items);

            $sections[] = [
                'key'      => $pageKey,
                'label'    => self::PAGE_LABELS[$pageKey] ?? ucfirst(str_replace('_', ' ', $pageKey)),
                'items'    => $items,
                'disabled' => $disabled[$pageKey] ?? [],
            ];
        }

        return view('admin.link-type-pairings.index', ['sections' => $sections]);
    }

    /**
     * Save the checkbox state and copy overrides. The form submits
     * `enabled[pageKey][]` arrays (one entry per checked card); anything in
     * the catalog that was NOT submitted as enabled is stored as disabled.
     * A page with every card unchecked simply submits no entries for that
     * key, which disables all of its cards (and hides the whole section on
     * that page type). Copy comes in as `copy[pageKey][type][name|benefit]`;
     * only values that are non-blank AND differ from the shipped default are
     * stored, so blanking a field or the per-card reset restores defaults.
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled'       => ['nullable', 'array'],
            'enabled.*'     => ['nullable', 'array'],
            'enabled.*.*'   => ['string', 'max:60'],
            'copy'                 => ['nullable', 'array'],
            'copy.*'               => ['nullable', 'array'],
            'copy.*.*'             => ['nullable', 'array'],
            'copy.*.*.name'        => ['nullable', 'string', 'max:80'],
            'copy.*.*.benefit'     => ['nullable', 'string', 'max:220'],
        ]);

        $enabled = (array) ($data['enabled'] ?? []);
        $copyInput = (array) ($data['copy'] ?? []);

        $disabledMap = [];
        foreach (SitePagesContent::linkTypePairingsCatalog() as $pageKey => $items) {
            $checked = array_map('strval', (array) ($enabled[$pageKey] ?? []));
            $disabled = [];
            foreach ($items as $item) {
                $type = (string) ($item['type'] ?? '');
                if ($type !== '' && !in_array($type, $checked, true)) {
                    $disabled[] = $type;
                }
            }
            if ($disabled) {
                $disabledMap[$pageKey] = $disabled;
            }
        }

        AppSetting::put(SitePagesContent::LINK_TYPE_PAIRINGS_DISABLED_KEY, $disabledMap);

        $copyMap = [];
        foreach (SitePagesContent::linkTypePairingsCatalog() as $pageKey => $items) {
            foreach ($items as $item) {
                $type = (string) ($item['type'] ?? '');
                if ($type === '') {
                    continue;
                }
                $fields = (array) ($copyInput[$pageKey][$type] ?? []);
                $entry = [];
                foreach (['name', 'benefit'] as $field) {
                    $value = trim((string) ($fields[$field] ?? ''));
                    if ($value !== '' && $value !== (string) ($item[$field] ?? '')) {
                        $entry[$field] = $value;
                    }
                }
                if ($entry) {
                    $copyMap[$pageKey][$type] = $entry;
                }
            }
        }

        AppSetting::put(SitePagesContent::LINK_TYPE_PAIRINGS_COPY_KEY, $copyMap);

        return back()->with('success', 'Perfect Pairings settings saved.');
    }

    /** Restore defaults: re-enable every card everywhere and reset all copy. */
    public function restoreDefaults()
    {
        AppSetting::put(SitePagesContent::LINK_TYPE_PAIRINGS_DISABLED_KEY, []);
        AppSetting::put(SitePagesContent::LINK_TYPE_PAIRINGS_COPY_KEY, []);

        return back()->with('success', 'All Perfect Pairings cards re-enabled with default copy.');
    }
}
