<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Bearer-token parity for the admin "Perfect Pairings" toggles page, so a
 * platform admin can enable/disable individual cross-promo cards from the
 * Sayzio Mobile app.
 *
 * Mirrors the web Admin\LinkTypePairingsController exactly: the catalog stays
 * code-defined in SitePagesContent::linkTypePairingsCatalog(); this surface
 * only checks/unchecks individual cards per page type. The disabled set is
 * stored in app_settings (SitePagesContent::LINK_TYPE_PAIRINGS_DISABLED_KEY)
 * and enforced centrally in linkTypePairingsFor(), so every public web page
 * and API `pairings` payload respects the toggles automatically. Gated behind
 * the same `settings.manage` permission as the other /admin/* API surfaces.
 */
class LinkTypePairingsController extends Controller
{
    use ApiResponses;

    /** Human-readable label per pairing page key (mirrors the web screen). */
    private const PAGE_LABELS = [
        'ics'             => 'Event pages',
        'restaurant_menu' => 'Restaurant menu pages',
        'store_menu'      => 'Store pages',
        'resume'          => 'Resume pages',
        'reviews'         => 'Reviews pages',
        'biolink'         => 'Biolink pages',
    ];

    /** Current toggle state: every page type with its cards + enabled flags. */
    public function status(Request $request)
    {
        if (!$request->user()->hasPermission('settings.manage')) {
            return $this->forbidden('You are not allowed to view Perfect Pairings settings.');
        }

        return $this->ok($this->statusPayload());
    }

    /**
     * Save the checkbox state, mirroring the web update(): the client submits
     * `enabled` as a map of pageKey => list of checked card types; anything
     * in the catalog NOT submitted as enabled is stored as disabled. A page
     * with every card unchecked simply submits no entries for its key, which
     * disables all of its cards (and hides the section on that page type).
     */
    public function update(Request $request)
    {
        if (!$request->user()->hasPermission('settings.manage')) {
            return $this->forbidden('You are not allowed to edit Perfect Pairings settings.');
        }

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

        return $this->ok($this->statusPayload());
    }

    /** Restore defaults: re-enable every card everywhere. */
    public function restoreDefaults(Request $request)
    {
        if (!$request->user()->hasPermission('settings.manage')) {
            return $this->forbidden('You are not allowed to edit Perfect Pairings settings.');
        }

        AppSetting::put(SitePagesContent::LINK_TYPE_PAIRINGS_DISABLED_KEY, []);

        return $this->ok($this->statusPayload());
    }

    /** Sections payload shared by every action (mirrors the web screen data). */
    private function statusPayload(): array
    {
        $catalog = SitePagesContent::linkTypePairingsCatalog();
        $disabledMap = SitePagesContent::linkTypePairingsDisabledMap();

        $sections = [];
        foreach ($catalog as $pageKey => $items) {
            $disabled = $disabledMap[$pageKey] ?? [];
            $sections[] = [
                'key'   => $pageKey,
                'label' => self::PAGE_LABELS[$pageKey] ?? ucfirst(str_replace('_', ' ', $pageKey)),
                'items' => array_map(fn ($item) => [
                    'name'    => $item['name'],
                    'type'    => $item['type'],
                    'icon'    => $item['icon'],
                    'benefit' => $item['benefit'],
                    'enabled' => !in_array($item['type'], $disabled, true),
                ], $items),
            ];
        }

        return ['sections' => $sections];
    }
}
