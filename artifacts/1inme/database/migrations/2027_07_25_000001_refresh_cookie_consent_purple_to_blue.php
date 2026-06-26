<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refresh the persisted cookie-consent config from the old brand purple
 * (#7c3aed) to the new brand blue (#3d6bff).
 *
 * The source defaults in CookieConsentConfig were swapped to blue, but that
 * only affects fresh / unconfigured installs. A tenant that already saved the
 * consent config keeps the old purple default baked into its
 * `app_settings.cookie_consent_config` blob (accent, primary "Accept all"
 * button bg, and the tertiary "Customize" link bg/text), so the live banner
 * would still render purple.
 *
 * This is an additive, idempotent data migration (no schema change). It only
 * rewrites cells that still hold the OLD default colour via a JSON text
 * replace, so an admin who deliberately chose a different colour is never
 * touched. Re-running is a no-op once converted.
 */
return new class extends Migration {
    private const OLD = '#7c3aed';
    private const NEW = '#3d6bff';
    private const KEY = 'cookie_consent_config';

    public function up(): void
    {
        if (!Schema::hasTable('app_settings')) {
            return;
        }

        DB::table('app_settings')
            ->where('key', self::KEY)
            ->whereRaw('position(? in value::text) > 0', [self::OLD])
            ->update([
                'value' => DB::raw(
                    "replace(value::text, " . DB::getPdo()->quote(self::OLD)
                    . ", " . DB::getPdo()->quote(self::NEW) . ")::jsonb"
                ),
            ]);

        // The setting is read through a 5-minute cache; drop the entry so the
        // refreshed colours take effect immediately rather than after expiry.
        Cache::forget('app_setting:' . self::KEY);
    }

    public function down(): void
    {
        // No-op: the old purple brand colour is intentionally not restored.
    }
};
