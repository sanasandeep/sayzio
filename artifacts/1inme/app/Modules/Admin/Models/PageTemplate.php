<?php

namespace App\Modules\Admin\Models;

use Database\Seeders\ExpandedPageTemplateLibrarySeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PageTemplate extends Model
{
    protected $fillable = [
        'name', 'slug', 'category', 'description', 'thumbnail_url',
        'plan_tier', 'recommended_personas', 'is_active', 'sort_order', 'snapshot',
        'design_locked',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'design_locked' => 'boolean',
        'sort_order' => 'integer',
        'snapshot' => 'array',
        'recommended_personas' => 'array',
    ];

    /**
     * Seeders store root-relative thumbnail paths (e.g.
     * `/template-thumbs/<slug>.svg?v=N`) so rows stay portable across
     * hosts (dev vs production domain). Absolutize on read so every
     * consumer — Blade cards, the REST API and the mobile app — gets a
     * fully-qualified URL for the current host. Absolute URLs (admin
     * uploads, legacy rows) pass through untouched.
     */
    public function getThumbnailUrlAttribute(?string $value): ?string
    {
        if ($value !== null && str_starts_with($value, '/')) {
            return url($value);
        }
        return $value;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Templates available to a user with the given plan slug.
     * Empty plan_tier = open to all. Otherwise the template's required tier
     * sort_order must be <= the user's plan sort_order (higher tier users
     * see lower-tier templates).
     */
    public function scopeAvailableForPlan($query, ?string $userPlanSlug)
    {
        $ranks = Plan::pluck('sort_order', 'slug');
        $userRank = $userPlanSlug ? ($ranks[$userPlanSlug] ?? -1) : -1;
        $allowedTiers = $ranks->filter(fn($rank) => $rank <= $userRank)->keys()->all();
        return $query->where(function ($q) use ($allowedTiers) {
            $q->whereNull('plan_tier')->orWhere('plan_tier', '');
            if (!empty($allowedTiers)) {
                $q->orWhereIn('plan_tier', $allowedTiers);
            }
        });
    }

    /**
     * True when updated_at has drifted past created_at by more than the
     * shared tolerance — i.e. an admin has saved this row at least once
     * since it was created/seeded. Same signal that
     * `templates:refresh-persona-seed` uses to detect admin edits, so
     * the admin "Customized" badge and that command stay in agreement.
     */
    public function wasCustomized(): bool
    {
        if (!$this->updated_at || !$this->created_at) {
            return false;
        }
        return $this->updated_at->getTimestamp() - $this->created_at->getTimestamp()
            > \App\Console\Commands\RefreshPersonaSeed::EDIT_DRIFT_TOLERANCE;
    }

    /**
     * Stored blueprint version stamped into this row's snapshot when it
     * was originally seeded. Returns 0 for legacy rows that predate the
     * `snapshot.meta.seed_version` field — they're treated as the
     * earliest possible blueprint version.
     */
    public function seedVersion(): int
    {
        $snap = (array) ($this->snapshot ?? []);
        return (int) ($snap['meta']['seed_version'] ?? 0);
    }

    /**
     * If this row's slug lives in the `persona-<slug>-<key>` namespace
     * owned by ExpandedPageTemplateLibrarySeeder, return the persona
     * slug. Otherwise null. Used to scope the "outdated blueprint"
     * indicator to seed-managed rows only — admin-created templates
     * never carry a seed version and shouldn't be flagged.
     */
    public function personaSeedSlug(): ?string
    {
        if (!$this->slug || !Str::startsWith($this->slug, 'persona-')) {
            return null;
        }
        // slug format: persona-<persona-slug>-<blueprint-key>. Walk
        // backwards through possible split points so multi-word persona
        // slugs (e.g. "small-business") resolve correctly.
        $remainder = Str::after($this->slug, 'persona-');
        $parts = explode('-', $remainder);
        for ($i = count($parts) - 1; $i >= 1; $i--) {
            $candidate = implode('-', array_slice($parts, 0, $i));
            if (\App\Modules\User\Services\PersonaCatalog::isValid($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Look up the current blueprint that owns this row's slug, if any.
     * Returns null for rows whose slug doesn't map to a current
     * blueprint (admin-added rows in the persona namespace, or rows
     * whose blueprint key was retired).
     *
     * @return array{key:string,name:string,description:string,thumb:string,snapshot:array}|null
     */
    public function currentBlueprint(): ?array
    {
        $personaSlug = $this->personaSeedSlug();
        if (!$personaSlug) {
            return null;
        }
        $persona = collect(\App\Modules\User\Services\PersonaCatalog::all())
            ->firstWhere('slug', $personaSlug);
        if (!$persona) {
            return null;
        }
        $seeder = new ExpandedPageTemplateLibrarySeeder();
        foreach ($seeder->blueprintsFor($persona) as $bp) {
            $bpSlug = 'persona-' . $personaSlug . '-' . Str::slug($bp['key']);
            if ($bpSlug === $this->slug) {
                return $bp;
            }
        }
        return null;
    }

    /**
     * True when this row was originally seeded by
     * ExpandedPageTemplateLibrarySeeder (slug is in the persona
     * namespace AND maps to a current blueprint) and its stored
     * `snapshot.meta.seed_version` is older than the seeder's current
     * SEED_VERSION. Untouched rows in this state get auto-refreshed on
     * the next deploy; admin-edited rows are intentionally preserved
     * and surface this flag so admins can decide per-row.
     */
    public function isOutdatedBlueprint(): bool
    {
        if (!$this->currentBlueprint()) {
            return false;
        }
        return $this->seedVersion() < ExpandedPageTemplateLibrarySeeder::SEED_VERSION;
    }

    /**
     * Concrete design problems with this row's stored snapshot — unknown
     * block types and stale design-variant keys that would silently
     * degrade on the public page. Empty array = clean. Drives the
     * "Design issues" badge and one-click fix flow on the admin index.
     *
     * @return array<int,string>
     */
    public function designIssues(): array
    {
        return \App\Modules\User\Support\TemplateSnapshotValidator::issues(
            (array) ($this->snapshot ?? []),
            'page'
        );
    }

    public static function categories(): array
    {
        // Legacy "shape" categories kept for backwards-compatibility with
        // existing seed data. Personas are appended below so admins can
        // pick a persona-as-category for new templates and the same
        // dropdown stays in sync with the onboarding picker.
        $base = [
            'general' => 'General',
            'event' => 'Event',
            'product' => 'Product',
            'portfolio' => 'Portfolio',
        ];
        // Personas (slug => label). Where a persona slug overlaps a
        // legacy category key, the persona label wins so admins see a
        // single, consistent name.
        $personas = \App\Modules\User\Services\PersonaCatalog::slugLabelMap();
        return array_merge($base, $personas);
    }
}
