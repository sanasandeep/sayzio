<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Admin toggles for the "Perfect Pairings" cross-promo cards (Task: admin
 * checkboxes to disable individual pairing cards).
 *
 * The catalog stays code-defined in SitePagesContent::linkTypePairingsCatalog();
 * admins can only check/uncheck individual cards per page type. The disabled
 * set lives in app_settings under
 * SitePagesContent::LINK_TYPE_PAIRINGS_DISABLED_KEY and is enforced centrally
 * in linkTypePairingsFor(), so the web blade partial AND every API `pairings`
 * payload (consumed unchanged by the mobile app) respect the toggles.
 *
 * Covers: default all-on, filtered web render, filtered API payload, empty
 * list hides the section, restore defaults, and the admin screen itself.
 */
class LinkTypePairingsAdminTogglesTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );

        return Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    /** Default state: nothing stored means every catalog card is served. */
    public function test_default_is_everything_enabled(): void
    {
        foreach (SitePagesContent::linkTypePairingsCatalog() as $pageKey => $items) {
            $this->assertSame(
                $items,
                SitePagesContent::linkTypePairingsFor($pageKey),
                "with no setting stored, '{$pageKey}' must serve the full catalog",
            );
        }
    }

    /** Disabled cards are filtered out of linkTypePairingsFor(). */
    public function test_disabled_cards_are_filtered_out(): void
    {
        AppSetting::put(SitePagesContent::LINK_TYPE_PAIRINGS_DISABLED_KEY, [
            'biolink' => ['qr', 'vcf'],
        ]);

        $types = array_column(SitePagesContent::linkTypePairingsFor('biolink'), 'type');

        $this->assertSame(['reviews', 'ics'], $types);
        // Other page types are untouched.
        $this->assertCount(4, SitePagesContent::linkTypePairingsFor('reviews'));
    }

    /** Unchecking everything for a page yields an empty list (section hides). */
    public function test_disabling_all_cards_yields_an_empty_list_and_hidden_section(): void
    {
        $allTypes = array_column(SitePagesContent::linkTypePairingsCatalog()['resume'], 'type');
        AppSetting::put(SitePagesContent::LINK_TYPE_PAIRINGS_DISABLED_KEY, [
            'resume' => $allTypes,
        ]);

        $this->assertSame([], SitePagesContent::linkTypePairingsFor('resume'));

        $html = view('common.partials.link-type-pairings', [
            'pairingType' => 'resume',
            'theme'       => 'dark',
        ])->render();

        $this->assertStringNotContainsString('Perfect pairings', $html);
    }

    /** The web partial only renders enabled cards. */
    public function test_web_partial_omits_disabled_cards(): void
    {
        AppSetting::put(SitePagesContent::LINK_TYPE_PAIRINGS_DISABLED_KEY, [
            'reviews' => ['restaurant_menu'],
        ]);

        $html = view('common.partials.link-type-pairings', [
            'pairingType' => 'reviews',
            'theme'       => 'dark',
        ])->render();

        $this->assertStringContainsString('Perfect pairings', $html);
        $this->assertStringNotContainsString('Restaurant Menu', $html);
        $this->assertStringContainsString('Link in Bio', $html);
    }

    /** Stale entries for unknown pages/types are ignored, not fatal. */
    public function test_unknown_settings_entries_are_ignored(): void
    {
        AppSetting::put(SitePagesContent::LINK_TYPE_PAIRINGS_DISABLED_KEY, [
            'nonexistent_page' => ['qr'],
            'biolink'          => ['not_a_real_type'],
        ]);

        $this->assertCount(4, SitePagesContent::linkTypePairingsFor('biolink'));
        $this->assertSame([], SitePagesContent::linkTypePairingsDisabledMap());
    }

    /** The admin settings screen lists every page type + card with context. */
    public function test_admin_screen_lists_every_page_type_and_card(): void
    {
        $response = $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('admin.link-type-pairings.index'))
            ->assertOk()
            ->assertSee('Perfect Pairings');

        foreach (SitePagesContent::linkTypePairingsCatalog() as $pageKey => $items) {
            $response->assertSee($pageKey);
            foreach ($items as $item) {
                $response->assertSee($item['name']);
                $response->assertSee($item['benefit']);
            }
        }
    }

    public function test_admin_screen_requires_authentication(): void
    {
        $this->get(route('admin.link-type-pairings.index'))->assertRedirect();
    }

    /**
     * Saving the form stores exactly the unchecked cards as disabled: the
     * form submits `enabled[pageKey][]`, everything else becomes disabled.
     */
    public function test_update_stores_unchecked_cards_as_disabled(): void
    {
        $enabled = [];
        foreach (SitePagesContent::linkTypePairingsCatalog() as $pageKey => $items) {
            $enabled[$pageKey] = array_column($items, 'type');
        }
        // Uncheck two biolink cards and ALL resume cards.
        $enabled['biolink'] = ['reviews', 'ics'];
        unset($enabled['resume']);

        $this->actingAs($this->makeAdmin(), 'admin')
            ->put(route('admin.link-type-pairings.update'), ['enabled' => $enabled])
            ->assertRedirect();

        $stored = AppSetting::get(SitePagesContent::LINK_TYPE_PAIRINGS_DISABLED_KEY);
        $this->assertSame(['qr', 'vcf'], $stored['biolink']);
        $this->assertSame(
            array_column(SitePagesContent::linkTypePairingsCatalog()['resume'], 'type'),
            $stored['resume'],
        );
        $this->assertArrayNotHasKey('reviews', $stored);

        $this->assertSame(
            ['reviews', 'ics'],
            array_column(SitePagesContent::linkTypePairingsFor('biolink'), 'type'),
        );
        $this->assertSame([], SitePagesContent::linkTypePairingsFor('resume'));
    }

    /** Restore defaults re-enables every card everywhere. */
    public function test_restore_defaults_re_enables_everything(): void
    {
        AppSetting::put(SitePagesContent::LINK_TYPE_PAIRINGS_DISABLED_KEY, [
            'biolink' => ['qr'],
            'reviews' => ['ics'],
        ]);

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.link-type-pairings.restore-defaults'))
            ->assertRedirect();

        foreach (SitePagesContent::linkTypePairingsCatalog() as $pageKey => $items) {
            $this->assertSame($items, SitePagesContent::linkTypePairingsFor($pageKey));
        }
    }

    /** Copy overrides flow through linkTypePairingsFor() for web + API alike. */
    public function test_copy_overrides_are_applied_to_pairings(): void
    {
        AppSetting::put(SitePagesContent::LINK_TYPE_PAIRINGS_COPY_KEY, [
            'biolink' => [
                'qr' => ['name' => 'Custom QR', 'benefit' => 'Custom benefit line.'],
            ],
        ]);

        $items = SitePagesContent::linkTypePairingsFor('biolink');
        $qr = collect($items)->firstWhere('type', 'qr');

        $this->assertSame('Custom QR', $qr['name']);
        $this->assertSame('Custom benefit line.', $qr['benefit']);
        // Other cards keep default copy.
        $vcf = collect($items)->firstWhere('type', 'vcf');
        $this->assertSame('Contact Card', $vcf['name']);
    }

    /** Blank/stale/unknown copy entries are ignored and fall back to defaults. */
    public function test_blank_or_stale_copy_overrides_fall_back_to_defaults(): void
    {
        AppSetting::put(SitePagesContent::LINK_TYPE_PAIRINGS_COPY_KEY, [
            'biolink'          => [
                'qr'              => ['name' => '   ', 'benefit' => ''],
                'not_a_real_type' => ['name' => 'X'],
            ],
            'nonexistent_page' => ['qr' => ['name' => 'X']],
        ]);

        $this->assertSame([], SitePagesContent::linkTypePairingsCopyMap());
        $this->assertSame(
            SitePagesContent::linkTypePairingsCatalog()['biolink'],
            SitePagesContent::linkTypePairingsFor('biolink'),
        );
    }

    /**
     * Saving the form stores only non-blank copy values that differ from the
     * shipped defaults — submitting the defaults (or blanks) stores nothing,
     * which is what the per-card "Reset" button relies on.
     */
    public function test_update_stores_only_changed_copy_and_reset_drops_overrides(): void
    {
        $enabled = [];
        $copy = [];
        foreach (SitePagesContent::linkTypePairingsCatalog() as $pageKey => $items) {
            $enabled[$pageKey] = array_column($items, 'type');
            foreach ($items as $item) {
                // Submit defaults for everything (what the form does untouched).
                $copy[$pageKey][$item['type']] = [
                    'name'    => $item['name'],
                    'benefit' => $item['benefit'],
                ];
            }
        }
        // Override one card's copy.
        $copy['biolink']['qr'] = ['name' => 'Scan Me', 'benefit' => 'A better pitch.'];

        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin')
            ->put(route('admin.link-type-pairings.update'), ['enabled' => $enabled, 'copy' => $copy])
            ->assertRedirect();

        $this->assertSame(
            ['biolink' => ['qr' => ['name' => 'Scan Me', 'benefit' => 'A better pitch.']]],
            AppSetting::get(SitePagesContent::LINK_TYPE_PAIRINGS_COPY_KEY),
        );
        $qr = collect(SitePagesContent::linkTypePairingsFor('biolink'))->firstWhere('type', 'qr');
        $this->assertSame('Scan Me', $qr['name']);

        // "Reset": re-submit the defaults for that card — override is dropped.
        $copy['biolink']['qr'] = [
            'name'    => 'QR Code',
            'benefit' => 'Turn your page into a scannable code for print and signage.',
        ];
        $this->actingAs($admin, 'admin')
            ->put(route('admin.link-type-pairings.update'), ['enabled' => $enabled, 'copy' => $copy])
            ->assertRedirect();

        $this->assertSame([], AppSetting::get(SitePagesContent::LINK_TYPE_PAIRINGS_COPY_KEY));
        $qr = collect(SitePagesContent::linkTypePairingsFor('biolink'))->firstWhere('type', 'qr');
        $this->assertSame('QR Code', $qr['name']);
    }

    /** Restore-defaults also clears all copy overrides. */
    public function test_restore_defaults_resets_copy_overrides(): void
    {
        AppSetting::put(SitePagesContent::LINK_TYPE_PAIRINGS_COPY_KEY, [
            'biolink' => ['qr' => ['name' => 'Custom']],
        ]);

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.link-type-pairings.restore-defaults'))
            ->assertRedirect();

        $this->assertSame([], AppSetting::get(SitePagesContent::LINK_TYPE_PAIRINGS_COPY_KEY));
    }

    /** The web partial renders overridden copy. */
    public function test_web_partial_renders_overridden_copy(): void
    {
        AppSetting::put(SitePagesContent::LINK_TYPE_PAIRINGS_COPY_KEY, [
            'reviews' => ['biolink' => ['name' => 'One Link Hub', 'benefit' => 'All your stuff, one link.']],
        ]);

        $html = view('common.partials.link-type-pairings', [
            'pairingType' => 'reviews',
            'theme'       => 'dark',
        ])->render();

        $this->assertStringContainsString('One Link Hub', $html);
        $this->assertStringContainsString('All your stuff, one link.', $html);
        $this->assertStringNotContainsString('Link in Bio', $html);
    }

    /** API payload picks up overridden copy (mobile parity, no mobile change). */
    public function test_api_pairings_payload_includes_overridden_copy(): void
    {
        $user = \App\Modules\User\Models\User::create([
            'name'     => 'Rev Owner',
            'email'    => 'revc' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'status'   => 'active',
        ]);
        $link = \App\Modules\User\Models\Link::create([
            'user_id' => $user->id,
            'type'    => 'reviews',
            'alias'   => 'revcopy' . uniqid(),
            'url'     => null,
            'status'  => 'active',
        ]);

        AppSetting::put(SitePagesContent::LINK_TYPE_PAIRINGS_COPY_KEY, [
            'reviews' => ['qr' => ['benefit' => 'Scan to review us in seconds.']],
        ]);

        $pairings = $this->getJson('/api/v1/reviews/' . $link->alias)
            ->assertOk()
            ->json('data.pairings');

        $qr = collect($pairings)->firstWhere('type', 'qr');
        $this->assertSame('Scan to review us in seconds.', $qr['benefit']);
        $this->assertSame('QR Code', $qr['name']);
    }

    /**
     * API parity: the public API `pairings` payload — the exact list the
     * mobile app renders — is filtered by the same setting, with no mobile
     * change. Uses the public reviews endpoint as the representative surface
     * (all six controllers call the same linkTypePairingsFor()).
     */
    public function test_api_pairings_payload_respects_the_toggles(): void
    {
        $user = \App\Modules\User\Models\User::create([
            'name'     => 'Rev Owner',
            'email'    => 'rev' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'status'   => 'active',
        ]);
        $link = \App\Modules\User\Models\Link::create([
            'user_id' => $user->id,
            'type'    => 'reviews',
            'alias'   => 'revpair' . uniqid(),
            'url'     => null,
            'status'  => 'active',
        ]);

        // Default: full catalog in the payload.
        $this->getJson('/api/v1/reviews/' . $link->alias)
            ->assertOk()
            ->assertJsonCount(4, 'data.pairings');

        AppSetting::put(SitePagesContent::LINK_TYPE_PAIRINGS_DISABLED_KEY, [
            'reviews' => ['restaurant_menu', 'qr'],
        ]);

        $types = collect(
            $this->getJson('/api/v1/reviews/' . $link->alias)
                ->assertOk()
                ->json('data.pairings')
        )->pluck('type')->all();

        $this->assertSame(['biolink', 'ics'], $types);
    }
}
