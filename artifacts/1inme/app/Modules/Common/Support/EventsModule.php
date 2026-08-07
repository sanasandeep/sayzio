<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;

/**
 * Platform-wide Events module switch (Task #6726).
 *
 * Single source of truth consulted by every events surface: public
 * /events directory, event pages + RSVP/ticket/connect-QR routes,
 * creator @handle/events pages, user-side event creation/management
 * routes, the mobile API, navigation entries, and the marketing hero
 * band. Default ON so existing installs are unaffected. Turning it off
 * never touches event data — everything comes back when re-enabled.
 */
class EventsModule
{
    /** AppSetting key backing the admin toggle. */
    public const KEY = 'events_module_enabled';

    public static function enabled(): bool
    {
        try {
            return (bool) AppSetting::get(self::KEY, true);
        } catch (\Throwable $e) {
            // Settings table unavailable (fresh install mid-migrate) —
            // fail open, matching the default.
            return true;
        }
    }
}
