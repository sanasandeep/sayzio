<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\BiolinkBlock;

/**
 * Source-of-truth registry for biolink block types (Task #1090).
 *
 * Goals:
 *   1. Provide a single PHP-side catalog of canonical block types, with
 *      backward-compat aliases that map legacy / duplicate type slugs
 *      onto a canonical key plus pre-filled mode/size/layout settings.
 *   2. Keep saved blocks rendering without any data migration: legacy
 *      type strings persist in the database, and the renderer / editor
 *      keep dispatching on `$block->type` directly. Aliases are read at
 *      load time via {@see resolveAlias()} when callers want the
 *      canonical view (e.g. for the public block picker, or to bundle
 *      design variants from the canonical key).
 *   3. Mirror to TypeScript at `artifacts/1inme-mobile/lib/blockTypeRegistry.ts`
 *      so the mobile editor uses the same canonical labels and bundles.
 *
 * Wiring (consumers):
 *   - `BiolinkBlockController::store` accepts both canonical TYPES keys
 *     AND alias keys via `aliases()`, so legacy clients can still POST
 *     `type=link_big` etc.
 *   - `BiolinkBlock::pickerTypes()` filters out alias entries
 *     (`_alias=true`), and `PlanFormCatalogue::blockTypesByCategory()`
 *     consumes that — so the plan-gating UI and "Add block" panel show
 *     the consolidated catalog only.
 *   - `BlockVariantCatalog` and the mobile `blockVariants.ts` mirror
 *     bundle assignments via `bundlesForCanonical()`.
 *
 * The registry layers on top of `BiolinkBlock::TYPES`:
 *   - declares which legacy types are aliases of a canonical type, and
 *     what mode/size/layout a fresh block of that alias should default to
 *   - exposes a `meta()` map for any extra editor metadata (mode keys,
 *     size keys, layout keys) that the per-type editor partials can read
 *
 * Web3 blocks (`nft_gallery`, `crypto_address`) are out of scope and
 * left untouched.
 */
final class BlockTypeRegistry
{
    /**
     * Schema version. Bumped when the alias map or mode/size/layout
     * defaults change in a way that callers might want to detect.
     */
    public const VERSION = 1;

    /**
     * Map of LEGACY type → canonical descriptor. The renderer still
     * dispatches on the legacy key (so saved blocks render unchanged),
     * but the public block picker, bundle lookup, and mobile mirror
     * collapse them under the canonical entry.
     *
     * Each descriptor:
     *   - canonical: canonical type slug (must exist in BiolinkBlock::TYPES)
     *   - mode/size/layout: pre-fill values for a brand-new block of
     *     this alias (e.g. picking "Featured Link" → canonical `link`
     *     with `_mode=featured`, etc.). The renderer reads these from
     *     `settings._registry` when present.
     */
    private const ALIASES = [
        // Link family — all collapse onto canonical `link` with a mode.
        'link_big'        => ['canonical' => 'link', 'mode' => 'featured', 'size' => 'lg'],
        'cta_button'      => ['canonical' => 'link', 'mode' => 'cta',      'size' => 'lg'],
        'featured_pin'    => ['canonical' => 'link', 'mode' => 'pinned',   'size' => 'lg'],
        'external_item'   => ['canonical' => 'link', 'mode' => 'external_card'],

        // Heading family.
        'heading_logo'    => ['canonical' => 'heading', 'mode' => 'with_logo'],
        'verified_heading'=> ['canonical' => 'heading', 'mode' => 'verified'],

        // Avatar family.
        'verified_avatar' => ['canonical' => 'avatar', 'mode' => 'verified'],

        // Paragraph family — `paragraph_rich` is canonical; the older
        // `paragraph` and `markdown` slugs collapse under it in the picker.
        'paragraph'       => ['canonical' => 'paragraph_rich', 'mode' => 'plain'],
        'markdown'        => ['canonical' => 'paragraph_rich', 'mode' => 'markdown'],

        // List family — numbered/pricing become layouts on `list`.
        'list_numbered'   => ['canonical' => 'list', 'layout' => 'numbered'],
        'list_pricing'    => ['canonical' => 'list', 'layout' => 'pricing'],

        // FAQ family.
        'faq_v2'          => ['canonical' => 'faq', 'layout' => 'accordion'],

        // Socials family — multi/custom collapse on socials.
        'socials_multi'   => ['canonical' => 'socials', 'mode' => 'grouped'],
        'socials_custom'  => ['canonical' => 'socials', 'mode' => 'custom'],

        // Image slider.
        'image_slider_v2' => ['canonical' => 'image_slider', 'layout' => 'carousel'],

        // YouTube / Instagram "latest" feeds — separate types but
        // collapsed in picker as a feed mode.
        'latest_youtube'  => ['canonical' => 'youtube',   'mode' => 'latest'],
        'youtube_feed'    => ['canonical' => 'youtube',   'mode' => 'feed'],
        'instagram_media' => ['canonical' => 'instagram', 'mode' => 'post'],
        'latest_instagram'=> ['canonical' => 'instagram', 'mode' => 'latest'],

        // Calendly inline embed — collapses on calendly with a layout.
        'calendly_embed'  => ['canonical' => 'calendly', 'layout' => 'inline'],

        // Profile cards — v2/v3/v4 are layouts of canonical profile_card.
        'profile_card_v1' => ['canonical' => 'profile_card', 'layout' => 'classic'],
        'profile_card_v2' => ['canonical' => 'profile_card', 'layout' => 'cover'],
        'profile_card_v3' => ['canonical' => 'profile_card', 'layout' => 'stats'],
        'profile_card_v4' => ['canonical' => 'profile_card', 'layout' => 'badges'],

        // Timeline.
        'timeline_staged' => ['canonical' => 'timeline', 'layout' => 'staged'],
    ];

    /**
     * Legacy / friendly slugs that appear only in plan `block_types_allowed`
     * allowlists (seeded or hand-typed) and never as a real `links` block
     * `type`. Unlike {@see ALIASES}, these are display-only synonyms — they
     * are NOT valid POST types, so a single friendly slug may fan out to
     * SEVERAL canonical block types (e.g. "tiktok" → both TikTok blocks).
     *
     * The pricing page used to humanize these gracefully for display, but
     * the editor gate compared the canonical POST type (`link`, `socials`,
     * `tiktok_video`) against the raw allowlist (`link_button`,
     * `social_icons`, `tiktok`), so what we advertised didn't match what we
     * enforced. {@see canonicalizeAllowlist()} resolves these so gating and
     * display agree.
     */
    private const ALLOWLIST_ALIASES = [
        'link_button'  => ['link'],
        'social_icons' => ['socials'],
        'tiktok'       => ['tiktok_video', 'tiktok_profile'],
        'twitter'      => ['twitter_profile', 'twitter_tweet', 'twitter_video'],
    ];

    /**
     * New canonical types added by Task #1090. Each entry is the same
     * shape as a `BiolinkBlock::TYPES` row, with optional `meta` for
     * editor-side mode/size/layout metadata.
     */
    private const NEW_TYPES = [
        'file_list' => [
            'label'    => 'File List',
            'icon'     => 'fa-folder-open',
            'category' => 'media',
            'meta' => [
                'layouts' => ['compact', 'cards', 'grid', 'pdf_strip'],
                'default' => ['layout' => 'compact'],
            ],
        ],
        'audio_list' => [
            'label'    => 'Audio Playlist',
            'icon'     => 'fa-headphones',
            'category' => 'music',
            'meta' => [
                'layouts' => ['compact', 'cards', 'wave'],
                'default' => ['layout' => 'compact'],
            ],
        ],
        'link_tree_group' => [
            'label'    => 'Link Group',
            'icon'     => 'fa-list-tree',
            'category' => 'basic',
            'meta'     => ['layouts' => ['list', 'grid'], 'default' => ['layout' => 'list']],
        ],
        'tabs' => [
            'label'    => 'Tabs',
            'icon'     => 'fa-folder',
            'category' => 'layout',
            'meta'     => ['layouts' => ['tabs', 'pills', 'underline'], 'default' => ['layout' => 'tabs']],
        ],
        'accordion' => [
            'label'    => 'Accordion',
            'icon'     => 'fa-bars-staggered',
            'category' => 'interactive',
            'meta'     => ['layouts' => ['plain', 'cards'], 'default' => ['layout' => 'plain']],
        ],
        'event_list' => [
            'label'    => 'Event List',
            'icon'     => 'fa-calendar-days',
            'category' => 'utility',
            'meta'     => ['layouts' => ['compact', 'cards', 'agenda'], 'default' => ['layout' => 'compact']],
        ],
        'menu' => [
            'label'    => 'Menu',
            'icon'     => 'fa-utensils',
            'category' => 'business',
            'meta'     => ['layouts' => ['classic', 'cards', 'sections'], 'default' => ['layout' => 'classic']],
        ],
        'menu_section' => [
            'label'    => 'Menu Section',
            'icon'     => 'fa-list-ul',
            'category' => 'business',
            'meta'     => ['layouts' => ['plain', 'card'], 'default' => ['layout' => 'plain']],
        ],
        'testimonial_carousel' => [
            'label'    => 'Testimonial Carousel',
            'icon'     => 'fa-comments',
            'category' => 'interactive',
            'meta'     => ['layouts' => ['carousel', 'stack'], 'default' => ['layout' => 'carousel']],
        ],
        'stats' => [
            'label'    => 'Stats',
            'icon'     => 'fa-chart-simple',
            'category' => 'utility',
            'meta'     => ['layouts' => ['row', 'grid'], 'default' => ['layout' => 'row']],
        ],
        'affiliate_links' => [
            'label'    => 'Affiliate Links',
            'icon'     => 'fa-tags',
            'category' => 'business',
            'meta'     => ['layouts' => ['compact', 'cards', 'grid'], 'default' => ['layout' => 'compact']],
        ],
        'booking_slots' => [
            'label'    => 'Booking Slots',
            'icon'     => 'fa-calendar-check',
            'category' => 'integrations',
            'meta'     => ['layouts' => ['list', 'grid'], 'default' => ['layout' => 'list']],
        ],
    ];

    /**
     * Per-type meta the editor reads to know what modes / sizes / layouts
     * are exposed on the canonical type. Keyed by canonical type slug.
     * Existing canonical types (`link`, `heading`, etc.) get meta here so
     * the registry surfaces consolidation knobs from the alias map.
     */
    private const META = [
        'link' => [
            'modes'   => ['standard', 'featured', 'cta', 'pinned', 'external_card'],
            'sizes'   => ['sm', 'md', 'lg'],
            'layouts' => ['button', 'plain_text', 'image_cover'],
            'default' => ['mode' => 'standard', 'size' => 'md', 'layout' => 'button'],
        ],
        'heading' => [
            'modes'   => ['standard', 'with_logo', 'verified'],
            'sizes'   => ['h1', 'h2', 'h3'],
            'default' => ['mode' => 'standard', 'size' => 'h2'],
        ],
        'paragraph_rich' => [
            'modes'   => ['plain', 'rich', 'markdown'],
            'default' => ['mode' => 'rich'],
        ],
        'list' => [
            'layouts' => ['bulleted', 'numbered', 'pricing'],
            'default' => ['layout' => 'bulleted'],
        ],
        'faq' => [
            'layouts' => ['simple', 'accordion'],
            'default' => ['layout' => 'simple'],
        ],
        'socials' => [
            'modes'   => ['standard', 'grouped', 'custom'],
            'sizes'   => ['sm', 'md', 'lg'],
            'default' => ['mode' => 'standard', 'size' => 'md'],
        ],
        'image_slider' => [
            'layouts' => ['slider', 'carousel'],
            'default' => ['layout' => 'slider'],
        ],
        'avatar' => [
            'modes'   => ['standard', 'verified'],
            'default' => ['mode' => 'standard'],
        ],
        'youtube' => [
            'modes'   => ['single', 'feed', 'latest'],
            'default' => ['mode' => 'single'],
        ],
        'instagram' => [
            'modes'   => ['post', 'latest'],
            'default' => ['mode' => 'post'],
        ],
        'calendly' => [
            'layouts' => ['button', 'inline'],
            'default' => ['layout' => 'button'],
        ],
        'profile_card' => [
            'layouts' => ['classic', 'cover', 'stats', 'badges'],
            'default' => ['layout' => 'classic'],
        ],
        'timeline' => [
            'layouts' => ['linear', 'staged'],
            'default' => ['layout' => 'linear'],
        ],
    ];

    /**
     * Returns the alias descriptor for a legacy type, or null when the
     * type is already canonical. The renderer keeps dispatching on the
     * raw `$block->type`; this is for editor / picker / mobile mirror.
     */
    public static function resolveAlias(string $type): ?array
    {
        return self::ALIASES[$type] ?? null;
    }

    /**
     * Canonical type for a (possibly-legacy) type slug. Returns the input
     * unchanged when the slug is already canonical or unknown.
     */
    public static function canonical(string $type): string
    {
        return self::ALIASES[$type]['canonical'] ?? $type;
    }

    /**
     * Expand a single plan-allowlist slug to the real block type(s) it
     * permits.
     *
     * IMPORTANT: this resolves friendly allowlist-only synonyms
     * ({@see ALLOWLIST_ALIASES}) ONLY — those are display strings that are
     * not themselves valid `links` block types (e.g. `tiktok` fans out to
     * both real TikTok blocks). Real block types — including those the
     * editor-side {@see ALIASES} map treats as "modes" of another type
     * (`cta_button`, `link_big`, `paragraph`, `markdown`, …) — are returned
     * UNCHANGED. We must not collapse them onto a canonical here: gating and
     * the AI block catalog match on the raw type string, so a plan that
     * allows `link` but not `cta_button` must keep enforcing that
     * distinction.
     */
    public static function expandAllowlistSlug(string $slug): array
    {
        if (isset(self::ALLOWLIST_ALIASES[$slug])) {
            return self::ALLOWLIST_ALIASES[$slug];
        }
        return [$slug];
    }

    /**
     * Normalize a whole `block_types_allowed` list to real block-type slugs,
     * expanding friendly synonyms ({@see ALLOWLIST_ALIASES}) and
     * de-duplicating while preserving every real type unchanged. The `'*'`
     * sentinel is handled by callers, not here. Used by gating
     * ({@see \App\Modules\User\Models\User::userCanUseBlockType}), the
     * pricing matrix, and the data migration so "what we show" == "what we
     * enforce".
     */
    public static function canonicalizeAllowlist(array $slugs): array
    {
        $out = [];
        foreach ($slugs as $slug) {
            if (!is_string($slug) || $slug === '') {
                continue;
            }
            foreach (self::expandAllowlistSlug($slug) as $canonical) {
                $out[$canonical] = true;
            }
        }
        return array_keys($out);
    }

    /**
     * Full alias map (legacy → descriptor). Useful for the mobile mirror.
     */
    public static function aliases(): array
    {
        return self::ALIASES;
    }

    /**
     * Editor-side meta for a canonical type, or `null` when the type
     * has no consolidation knobs.
     */
    public static function meta(string $canonicalType): ?array
    {
        if (isset(self::META[$canonicalType])) {
            return self::META[$canonicalType];
        }
        return self::NEW_TYPES[$canonicalType]['meta'] ?? null;
    }

    /**
     * New types this registry adds on top of `BiolinkBlock::TYPES`. The
     * model merges these in via {@see canonicalTypes()} so the rest of
     * the codebase (validation, picker, plan gating) sees them.
     *
     * Returned in the same shape as a `BiolinkBlock::TYPES` row
     * (label/icon/category, plus optional `meta`).
     */
    public static function newTypes(): array
    {
        return self::NEW_TYPES;
    }

    /**
     * Convenience: list of every canonical type slug (legacy keys
     * collapsed under their canonical). Used by the mobile mirror to
     * pick which types appear in the public block picker.
     */
    public static function canonicalTypeSlugs(): array
    {
        $aliasKeys = array_keys(self::ALIASES);
        $allTypes  = array_keys(BiolinkBlock::TYPES) + array_keys(self::NEW_TYPES);
        // Keep order: existing TYPES first, new types appended.
        $ordered = array_merge(array_keys(BiolinkBlock::TYPES), array_keys(self::NEW_TYPES));
        return array_values(array_diff($ordered, $aliasKeys));
    }

    /**
     * Returns design-variant bundle ids that apply to a canonical type.
     * Surfaces here so {@see BlockVariantCatalog::forType()} and the
     * mobile `blockVariants.ts` mirror agree on bundle assignment for
     * the new block types.
     */
    public static function bundlesForCanonical(string $canonicalType): array
    {
        return match ($canonicalType) {
            'file_list'             => ['link_actions', 'link_shapes'],
            'audio_list'            => ['music'],
            'link_tree_group'       => ['link_actions', 'link_shapes'],
            'tabs'                  => ['headings'],
            'accordion'             => ['body_text'],
            'event_list'            => ['calendar'],
            'menu'                  => ['commerce', 'body_text'],
            'testimonial_carousel'  => ['body_text'],
            'stats'                 => ['headings', 'body_text'],
            'affiliate_links'       => ['link_actions', 'link_shapes', 'commerce'],
            'booking_slots'         => ['calendar'],
            default                 => [],
        };
    }
}
