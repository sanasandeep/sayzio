<?php

use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Propagate recent featuresCategoriesDefault() edits into already-seeded
 * site_pages `features` rows, mirroring
 * 2028_01_09_000001_sync_qr_code_forms_link_type_showcase.php:
 *
 *  - `links` category: rename "Projects" → "Folders" (description refreshed
 *    only when it still matches the old seeded copy), and insert the new
 *    "List & grid views" row right after it when missing.
 *  - `cross-platform` category: append the four new "Zio Browser …" rows
 *    when missing.
 *
 * The stored row is only seeded once, so code-default changes never reach
 * installs whose row already exists. Admin-edited order, copy, and any rows
 * an admin removed are left untouched: we only rename the unedited legacy
 * name and append/insert rows that are absent.
 */
return new class extends Migration
{
    private const OLD_PROJECTS_DESC = 'Group related links into project folders to keep large libraries tidy and easy to navigate.';

    private const CROSS_PLATFORM_NEW = [
        'Zio Browser desktop app',
        'Zio Browser ad blocker',
        'Zio Browser My Files & notes',
        'Zio Browser dialpad & viewers',
    ];

    public function up(): void
    {
        $row = DB::table('site_pages')->where('slug', 'features')->first();
        if (!$row) {
            return;
        }

        $sections = json_decode($row->sections ?? '[]', true);
        if (!is_array($sections) || $sections === []) {
            // Controller falls back to the (updated) code defaults.
            return;
        }

        $changed = false;

        foreach ($sections as $i => $cat) {
            if (!is_array($cat)) {
                continue;
            }
            $catId = $cat['id'] ?? null;
            if ($catId === 'links') {
                $features = is_array($cat['features'] ?? null) ? $cat['features'] : [];
                if ($this->syncLinks($features)) {
                    $sections[$i]['features'] = array_values($features);
                    $changed = true;
                }
            } elseif ($catId === 'cross-platform') {
                $features = is_array($cat['features'] ?? null) ? $cat['features'] : [];
                if ($this->syncCrossPlatform($features)) {
                    $sections[$i]['features'] = array_values($features);
                    $changed = true;
                }
            }
        }

        if (!$changed) {
            return;
        }

        DB::table('site_pages')->where('slug', 'features')->update([
            'sections'   => json_encode($sections),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Non-destructive: leave the propagated entries in place on rollback.
    }

    private function syncLinks(array &$features): bool
    {
        $changed  = false;
        $defaults = $this->defaultCategoryRows('links');

        $hasFolders = $this->hasName($features, 'Folders');
        $foldersIdx = null;

        foreach ($features as $idx => $f) {
            if (!is_array($f)) {
                continue;
            }
            $name = $this->key((string) ($f['name'] ?? ''));
            if ($name === 'folders') {
                $foldersIdx = $idx;
            } elseif ($name === 'projects' && !$hasFolders) {
                // Rename the legacy row in place; refresh the description
                // only when it is still the unedited seeded copy.
                $features[$idx]['name'] = 'Folders';
                $default = $this->findByName($defaults, 'Folders');
                if ($default !== null
                    && trim((string) ($f['description'] ?? '')) === self::OLD_PROJECTS_DESC) {
                    $features[$idx]['description'] = $default['description'] ?? $f['description'];
                }
                $foldersIdx = $idx;
                $hasFolders = true;
                $changed    = true;
            }
        }

        if (!$this->hasName($features, 'List & grid views')) {
            $addition = $this->findByName($defaults, 'List & grid views');
            if ($addition !== null) {
                if ($foldersIdx !== null) {
                    array_splice($features, $foldersIdx + 1, 0, [$addition]);
                } else {
                    $features[] = $addition;
                }
                $changed = true;
            }
        }

        return $changed;
    }

    private function syncCrossPlatform(array &$features): bool
    {
        $changed  = false;
        $defaults = $this->defaultCategoryRows('cross-platform');

        foreach (self::CROSS_PLATFORM_NEW as $name) {
            if ($this->hasName($features, $name)) {
                continue;
            }
            $addition = $this->findByName($defaults, $name);
            if ($addition !== null) {
                $features[] = $addition;
                $changed    = true;
            }
        }

        return $changed;
    }

    private function defaultCategoryRows(string $categoryId): array
    {
        foreach (SitePagesContent::featuresCategoriesDefault() as $cat) {
            if (($cat['id'] ?? null) === $categoryId) {
                return array_values(array_filter((array) ($cat['features'] ?? []), 'is_array'));
            }
        }
        return [];
    }

    private function hasName(array $items, string $name): bool
    {
        $wanted = $this->key($name);
        foreach ($items as $item) {
            if (is_array($item) && isset($item['name']) && $this->key((string) $item['name']) === $wanted) {
                return true;
            }
        }
        return false;
    }

    private function findByName(array $items, string $name): ?array
    {
        $wanted = $this->key($name);
        foreach ($items as $item) {
            if (is_array($item) && isset($item['name']) && $this->key((string) $item['name']) === $wanted) {
                return $item;
            }
        }
        return null;
    }

    private function key(string $name): string
    {
        return mb_strtolower(trim($name));
    }
};
