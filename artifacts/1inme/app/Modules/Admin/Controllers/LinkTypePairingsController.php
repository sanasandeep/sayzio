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
 * SitePagesContent::linkTypePairingsCatalog(); this screen only lets admins
 * check/uncheck individual cards per page type. The disabled set is stored in
 * app_settings (SitePagesContent::LINK_TYPE_PAIRINGS_DISABLED_KEY) and
 * enforced centrally in SitePagesContent::linkTypePairingsFor(), so web
 * public pages and every mobile-consumed API `pairings` payload respect the
 * toggles with no other changes.
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

        $sections = [];
        foreach ($catalog as $pageKey => $items) {
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
     * Save the checkbox state. The form submits `enabled[pageKey][]` arrays
     * (one entry per checked card); anything in the catalog that was NOT
     * submitted as enabled is stored as disabled. A page with every card
     * unchecked simply submits no entries for that key, which disables all
     * of its cards (and hides the whole section on that page type).
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled'       => ['nullable', 'array'],
            'enabled.*'     => ['nullable', 'array'],
            'enabled.*.*'   => ['string', 'max:60'],
        ]);

        $enabled = (array) ($data['enabled'] ?? []);

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

        return back()->with('success', 'Perfect Pairings settings saved.');
    }

    /** Restore defaults: re-enable every card everywhere. */
    public function restoreDefaults()
    {
        AppSetting::put(SitePagesContent::LINK_TYPE_PAIRINGS_DISABLED_KEY, []);

        return back()->with('success', 'All Perfect Pairings cards re-enabled.');
    }
}
