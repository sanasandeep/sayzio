<?php

use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Propagate the four link types that were added to the marketing showcase
 * (Store Menu, Resume / Portfolio, Calendar, Bizs Profile) into already-seeded
 * site_pages rows.
 *
 * The home "What you can create" cards live on the home row under
 * extra.link_types, and the /features "Link types" category lives on the
 * features row under sections[…].features — both are seeded only when missing,
 * so code-default changes never reach installs that already have a row. This
 * migration appends ONLY the four new entries, matched by name
 * (case-insensitive), and only when they are not already present. Admin-edited
 * order, copy, colours and any entries an admin removed are left untouched.
 */
return new class extends Migration
{
    /**
     * The four newly added showcase types, by canonical name. Kept here so the
     * append is precise — we never re-add an older default an admin deleted.
     */
    private const NEW_NAMES = ['Store Menu', 'Resume / Portfolio', 'Calendar', 'Bizs Profile'];

    public function up(): void
    {
        $now = now();
        $this->syncHome($now);
        $this->syncFeatures($now);
    }

    public function down(): void
    {
        // Non-destructive: leave the propagated entries in place on rollback.
    }

    private function syncHome($now): void
    {
        $row = DB::table('site_pages')->where('slug', 'home')->first();
        if (!$row) {
            return;
        }

        $extra = json_decode($row->extra ?? '[]', true);
        $extra = is_array($extra) ? $extra : [];

        $current = $extra['link_types'] ?? null;
        // Empty/missing list ⇒ the controller falls back to the (now updated)
        // code defaults, which already include all four — nothing to do.
        if (!is_array($current) || $current === []) {
            return;
        }

        $additions = $this->missingEntries(
            $current,
            SitePagesContent::homeLinkTypesDefault()
        );
        if ($additions === []) {
            return;
        }

        $extra['link_types'] = array_merge($current, $additions);

        DB::table('site_pages')->where('slug', 'home')->update([
            'extra' => json_encode($extra),
            'updated_at' => $now,
        ]);
    }

    private function syncFeatures($now): void
    {
        $row = DB::table('site_pages')->where('slug', 'features')->first();
        if (!$row) {
            return;
        }

        $sections = json_decode($row->sections ?? '[]', true);
        if (!is_array($sections) || $sections === []) {
            return;
        }

        $defaultFeatures = $this->defaultFeatureRows();
        $changed = false;

        foreach ($sections as $i => $cat) {
            if (!is_array($cat) || ($cat['id'] ?? null) !== 'link-types') {
                continue;
            }
            $features = is_array($cat['features'] ?? null) ? $cat['features'] : [];
            $additions = $this->missingEntries($features, $defaultFeatures);
            if ($additions !== []) {
                $sections[$i]['features'] = array_merge($features, $additions);
                $changed = true;
            }
            break;
        }

        if (!$changed) {
            return;
        }

        DB::table('site_pages')->where('slug', 'features')->update([
            'sections' => json_encode($sections),
            'updated_at' => $now,
        ]);
    }

    /**
     * From $defaults, return the entries whose name is one of the four new
     * names AND not already present (case-insensitive) in $current.
     */
    private function missingEntries(array $current, array $defaults): array
    {
        $have = [];
        foreach ($current as $item) {
            if (is_array($item) && isset($item['name'])) {
                $have[$this->key($item['name'])] = true;
            }
        }

        $wanted = array_map([$this, 'key'], self::NEW_NAMES);

        $out = [];
        foreach ($defaults as $entry) {
            if (!is_array($entry) || !isset($entry['name'])) {
                continue;
            }
            $k = $this->key($entry['name']);
            if (in_array($k, $wanted, true) && !isset($have[$k])) {
                $out[] = $entry;
            }
        }
        return $out;
    }

    /**
     * The features "Link types" category rows, normalised to the stored
     * {name, icon, description} shape.
     */
    private function defaultFeatureRows(): array
    {
        foreach (SitePagesContent::featuresCategoriesDefault() as $cat) {
            if (($cat['id'] ?? null) === 'link-types') {
                return array_values(array_filter(
                    (array) ($cat['features'] ?? []),
                    'is_array'
                ));
            }
        }
        return [];
    }

    private function key(string $name): string
    {
        return mb_strtolower(trim($name));
    }
};
