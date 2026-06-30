<?php

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Seed placeholder URLs for the four headline social networks (X/Twitter,
 * Instagram, Facebook, LinkedIn) so the marketing footer's social icon row
 * (common/partials/social-row) actually renders. That row only shows a network
 * whose `social_link_*` AppSetting holds a non-empty URL, so without these the
 * icons stay hidden.
 *
 * Idempotent + non-destructive: each key is only filled when it is currently
 * missing or blank, so re-running the migration is safe and an admin's real,
 * configured URL is never overwritten by a placeholder.
 */
return new class extends Migration {
    private const KEYS = [
        'social_link_twitter',
        'social_link_instagram',
        'social_link_facebook',
        'social_link_linkedin',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('app_settings')) {
            return;
        }

        $networks = SitePagesContent::socialNetworks();

        foreach (self::KEYS as $key) {
            $existing = trim((string) AppSetting::get($key, ''));
            if ($existing !== '') {
                continue; // keep any already-configured (possibly real) URL
            }

            $placeholder = $networks[$key]['placeholder'] ?? null;
            if (is_string($placeholder) && $placeholder !== '') {
                AppSetting::put($key, $placeholder);
            }
        }
    }

    public function down(): void
    {
        // No-op: these are harmless default placeholders, and a rollback should
        // not risk wiping URLs an admin may have set in the meantime.
    }
};
