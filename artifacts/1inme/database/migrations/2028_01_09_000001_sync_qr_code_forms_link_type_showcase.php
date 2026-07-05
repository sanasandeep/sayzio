<?php

use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Propagate the "QR Code" and "Forms" link types (added to the code
 * defaults) into already-seeded site_pages rows, mirroring
 * 2028_01_06_000001_sync_paid_page_link_type_showcase.php.
 *
 * The home "What you can create" cards live on the home row under
 * extra.link_types, and the /features "Link types" category lives on the
 * features row under sections[…].features — both are seeded only when
 * missing, so code-default changes never reach installs that already have a
 * row. This migration appends the "QR Code" / "Forms" entries only when they
 * are not already present. Admin-edited order, copy, colours and any entries
 * an admin removed are left untouched.
 */
return new class extends Migration
{
    private const NEW_NAMES = ['QR Code', 'Forms'];

    public function up(): void
    {
        $now = now();
        $this->syncHome($now);
        $this->syncFeatures($now);
        $this->syncFeaturesMetaDescription($now);
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
        // code defaults, which already include the new entries — nothing to do.
        if (!is_array($current) || $current === []) {
            return;
        }

        $defaults = SitePagesContent::homeLinkTypesDefault();
        $additions = [];
        foreach (self::NEW_NAMES as $name) {
            if ($this->hasName($current, $name)) {
                continue;
            }
            $addition = $this->findByName($defaults, $name);
            if ($addition !== null) {
                $additions[] = $addition;
            }
        }

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
            $additions = [];
            foreach (self::NEW_NAMES as $name) {
                if ($this->hasName($features, $name)) {
                    continue;
                }
                $addition = $this->findByName($defaultFeatures, $name);
                if ($addition !== null) {
                    $additions[] = $addition;
                }
            }
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
     * The features page `meta_description` bakes in the total link-type
     * count in prose ("all N link types ..."). Only overwrite it when it
     * still matches that seeded pattern (i.e. it is an unedited leftover
     * from an older code default) — an admin who has customised the copy
     * is left untouched.
     */
    private function syncFeaturesMetaDescription($now): void
    {
        $row = DB::table('site_pages')->where('slug', 'features')->first();
        if (!$row || !is_string($row->meta_description) || $row->meta_description === '') {
            return;
        }

        if (!preg_match('/^Everything you get with Sayzio — all \d+ link types/', $row->meta_description)) {
            return;
        }

        $fresh = SitePagesContent::richDefaults()['features']['meta_description'] ?? null;
        if (!$fresh || $fresh === $row->meta_description) {
            return;
        }

        DB::table('site_pages')->where('slug', 'features')->update([
            'meta_description' => $fresh,
            'updated_at' => $now,
        ]);
    }

    private function hasName(array $items, string $name): bool
    {
        $wanted = $this->key($name);
        foreach ($items as $item) {
            if (is_array($item) && isset($item['name']) && $this->key($item['name']) === $wanted) {
                return true;
            }
        }
        return false;
    }

    private function findByName(array $items, string $name): ?array
    {
        $wanted = $this->key($name);
        foreach ($items as $item) {
            if (is_array($item) && isset($item['name']) && $this->key($item['name']) === $wanted) {
                return $item;
            }
        }
        return null;
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
