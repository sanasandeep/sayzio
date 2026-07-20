<?php

namespace Database\Seeders;

use App\Modules\User\Models\VerificationTickType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds the default profile-verification tick types (Official / Government /
 * NGO / Company / Creator).
 *
 * The create-table migration inserts these rows inline, but only when it is
 * the one creating the table — a deploy where the table exists empty (e.g. a
 * schema-only provision, a partial restore, or an environment where the table
 * was created by other means) would otherwise leave the user request form and
 * the admin tick-type page blank.
 *
 * Idempotent: keyed on `slug` via firstOrCreate, so a re-run on a populated
 * database is a safe no-op and NEVER clobbers admin edits (name/color/icon/
 * is_active tweaks made in /user/profile-verification/admin/tick-types are
 * preserved).
 */
class VerificationTickTypeSeeder extends Seeder
{
    /** Default tick-type catalog. Keep in lockstep with the create migration. */
    public const DEFAULTS = [
        ['slug' => 'team',       'name' => 'Official',      'color' => '#1d9bf0', 'icon' => 'fa-check-circle', 'admin_assigned_only' => true,  'is_active' => true, 'sort_order' => 1],
        ['slug' => 'government', 'name' => 'Government',    'color' => '#8b9eb7', 'icon' => 'fa-landmark',     'admin_assigned_only' => false, 'is_active' => true, 'sort_order' => 2],
        ['slug' => 'ngo',        'name' => 'NGO / Charity', 'color' => '#00ba7c', 'icon' => 'fa-heart',        'admin_assigned_only' => false, 'is_active' => true, 'sort_order' => 3],
        ['slug' => 'company',    'name' => 'Company',       'color' => '#f4b400', 'icon' => 'fa-building',     'admin_assigned_only' => false, 'is_active' => true, 'sort_order' => 4],
        ['slug' => 'creator',    'name' => 'Creator',       'color' => '#9c6afe', 'icon' => 'fa-star',         'admin_assigned_only' => false, 'is_active' => true, 'sort_order' => 5],
    ];

    public function run(): void
    {
        if (!Schema::hasTable('verification_tick_types')) {
            return;
        }

        foreach (self::DEFAULTS as $row) {
            VerificationTickType::firstOrCreate(
                ['slug' => $row['slug']],
                $row
            );
        }
    }
}
