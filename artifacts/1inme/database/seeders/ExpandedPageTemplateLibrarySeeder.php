<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\PageTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * NEUTRALIZED (Task: permanently delete the old bulk persona library).
 *
 * This seeder used to generate ~10 `persona-<slug>-<key>` page templates
 * per persona from a large in-code blueprint bank. That bulk library was
 * permanently retired in favour of the small designer-made `starter-*`
 * set seeded by {@see StarterPageTemplatesSeeder}; the old rows are
 * deleted by the `purge_persona_page_template_library` data migration.
 *
 * The class is intentionally kept as an empty shell because several
 * surfaces still reference it:
 *  - `DatabaseSeeder` calls it (now a no-op).
 *  - `templates:refresh-persona-seed` (RefreshPersonaSeed) instantiates
 *    it and reads `blueprintsFor()` — an empty blueprint set means it
 *    can never recreate the old library.
 *  - `PageTemplate::currentBlueprint()` / `isOutdatedBlueprint()` read
 *    `blueprintsFor()` and `SEED_VERSION` — with no blueprints, no row
 *    is ever classified as an outdated seed row.
 *  - `templates:check-designs` (CheckTemplateDesigns) and the seed
 *    design-validity test reflect into `variantKits()`.
 *  - DesignLockedTemplateTest exercises `createBlueprintRow()` directly.
 *
 * DO NOT re-add blueprints here. New templates belong in
 * StarterPageTemplatesSeeder (or admin-curated rows), not this retired
 * bulk generator.
 */
class ExpandedPageTemplateLibrarySeeder extends Seeder
{
    /**
     * Kept for the admin templates screen and PageTemplate::isStale()
     * comparisons. With `blueprintsFor()` empty, no row can map to a
     * current blueprint, so this value no longer drives any refresh.
     */
    public const SEED_VERSION = 7;

    /**
     * Personas whose "starter" blueprint used to ship design-locked.
     * Referenced by `createBlueprintRow()` (still exercised by tests).
     *
     * @var array<int,string>
     */
    private const DESIGN_LOCKED_STARTER_PERSONAS = [
        'creator', 'artist', 'musician', 'influencer', 'coach', 'business',
        'developer', 'photographer', 'podcaster', 'fitness', 'restaurant',
        'realestate',
    ];

    /**
     * No-op. The bulk persona library is retired; this seeder never
     * creates, refreshes, or deletes any page_templates rows anymore.
     */
    public function run(): void
    {
        // Intentionally empty — the persona blueprint bank was retired.
    }

    /**
     * The blueprint bank is permanently empty. Callers (RefreshPersonaSeed,
     * PageTemplate::currentBlueprint(), CheckTemplateDesigns) treat an
     * empty list as "nothing seed-managed exists for this persona".
     *
     * @return array<int, array{key:string,name:string,description:string,thumb:string,snapshot:array}>
     */
    public function blueprintsFor(array $persona): array
    {
        return [];
    }

    /**
     * Retired with the blueprint bank. Kept (reflected into by
     * CheckTemplateDesigns and PageTemplateSeedDesignValidityTest).
     *
     * @return array<int, array{ptype:string,pvar:string,link:string}>
     */
    private function variantKits(): array
    {
        return [];
    }

    /**
     * Insert one persona blueprint row. No production caller remains
     * (run() is a no-op and the blueprint bank is empty); kept because
     * DesignLockedTemplateTest asserts the design-lock stamping rules.
     *
     * @param  array{key:string,name:string,description:string,thumb:string,snapshot:array}  $bp
     */
    private function createBlueprintRow(string $personaSlug, array $bp, int $index): void
    {
        PageTemplate::create([
            'slug'                 => 'persona-' . $personaSlug . '-' . Str::slug($bp['key']),
            'name'                 => $bp['name'],
            'category'             => $personaSlug,
            'description'          => $bp['description'],
            'thumbnail_url'        => $bp['thumb'],
            'plan_tier'            => null,
            'is_active'            => true,
            'sort_order'           => 100 + $index,
            'recommended_personas' => [$personaSlug],
            'snapshot'             => $bp['snapshot'],
            // Curated designer templates were seeded design-locked so the
            // pages they create keep the designed look (detachable).
            'design_locked'        => $bp['key'] === 'starter'
                && in_array($personaSlug, self::DESIGN_LOCKED_STARTER_PERSONAS, true),
        ]);
    }
}
