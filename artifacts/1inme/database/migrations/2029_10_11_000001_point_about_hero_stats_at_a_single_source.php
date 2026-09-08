<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The About page's three hero figures were typed by hand into the page's
 * stored content and then never touched again, so the live site read
 * "14K+ Creators served", "0 Years young" and "1 Teammates" while the home
 * page claimed 375,000 users. A visitor who sees two different numbers does
 * not assume one is stale; they assume both are invented.
 *
 * This points all three at a single source by storing the literal value
 * "auto" (see App\Modules\Common\Support\AboutFigures): creators comes from
 * the same Site Stats row the home page reads, years is counted from the
 * founding date, and the team size is one app setting. Labels, order and
 * visibility are left exactly as the admin arranged them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_settings')) {
            foreach ([
                'company_founded_on' => '2023-05-05',
                'company_team_size'  => 10,
            ] as $key => $value) {
                $exists = DB::table('app_settings')->where('key', $key)->exists();
                if (! $exists) {
                    DB::table('app_settings')->insert([
                        'key'        => $key,
                        'value'      => json_encode($value),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if (! Schema::hasTable('site_pages')) {
            return;
        }

        $page = DB::table('site_pages')->where('slug', 'about')->first();
        if (! $page || ! isset($page->extra)) {
            return;
        }

        $extra = json_decode((string) $page->extra, true);
        if (! is_array($extra) || ! isset($extra['hero']['stats']) || ! is_array($extra['hero']['stats'])) {
            return;
        }

        $changed = false;
        foreach ($extra['hero']['stats'] as $i => $stat) {
            if (! is_array($stat)) {
                continue;
            }
            $label = strtolower((string) ($stat['label'] ?? ''));
            $isKnown = str_contains($label, 'year')
                || str_contains($label, 'team') || str_contains($label, 'mate')
                || str_contains($label, 'creator') || str_contains($label, 'user');

            if (! $isKnown) {
                continue;
            }

            $extra['hero']['stats'][$i]['value'] = 'auto';
            $extra['hero']['stats'][$i]['suffix'] = '';
            $changed = true;
        }

        if ($changed) {
            DB::table('site_pages')->where('id', $page->id)->update([
                'extra'      => json_encode($extra),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Deliberately not reversed: restoring "0 Years young" and
        // "1 Teammates" would put the wrong numbers back on a public page.
    }
};
