<?php

namespace App\Modules\Admin\Support;

use App\Modules\Admin\Models\Plan;

/**
 * The admin-only names for plan categories.
 *
 * A category is a bucket of near-identical plans competing for ONE slot on
 * /pricing; a visitor sees one member and never learns the siblings exist.
 * The name is internal — it labels the experiment, not a thing anyone buys —
 * so it is written for whoever is editing plans at 2am, not for customers.
 *
 * These are SUGGESTIONS, not a fixed list. `plans.plan_category` is free text:
 * rename one, invent a new one, or stop using one entirely, all without a
 * deploy. This class exists so the admin form can offer what already exists
 * instead of making someone retype a name exactly and silently splitting a
 * category in two when they typo it.
 *
 * Naming convention worth keeping: `<band>-<rung>`. It sorts the categories
 * into their pricing band, and it makes the one hard rule visible at a glance
 * — a category lives in exactly one band, never two
 * (PlanCategoryIntegrityTest enforces that).
 */
class PlanCategories
{
    /**
     * One category per slot in today's lineup, so the buckets are ready to
     * drop variants into. Each maps to the plan that starts out in it.
     *
     * The internal "unlimited" comp plan is deliberately absent: it never
     * appears on a public surface, so it has no slot to compete for.
     *
     * @return array<string, array{slug: string, note: string}>
     */
    public static function suggested(): array
    {
        return [
            'free-entry'     => ['slug' => 'free',           'note' => 'The free rung. Variants here test what the free tier includes, not price.'],
            'bio-entry'      => ['slug' => 'bio-lite',       'note' => 'Cheapest paid link-in-bio. The most price-sensitive bucket you have.'],
            'bio-advanced'   => ['slug' => 'bio-plus',       'note' => 'Link-in-bio with a domain and the AI tools.'],
            'platform-solo'  => ['slug' => 'creator',        'note' => 'One person, whole platform. Currently the weakest-priced rung.'],
            'platform-pro'   => ['slug' => 'professional',   'note' => 'The money plan: payments, API, support.'],
            'team-small'     => ['slug' => 'business',       'note' => 'A team sharing one set of pages.'],
            'team-agency'    => ['slug' => 'agency',         'note' => 'Client work, white-label.'],
            'dev-standard'   => ['slug' => 'developer',      'note' => 'API without the dashboard.'],
            'dev-enterprise' => ['slug' => 'enterprise-api', 'note' => 'Volume API, SLA, contract.'],
        ];
    }

    /**
     * Every category name the admin form should offer: the suggestions above
     * plus anything already in use, so a category invented last week is one
     * keystroke away rather than something to retype from memory.
     *
     * @return list<string>
     */
    public static function options(): array
    {
        $inUse = Plan::query()
            ->whereNotNull('plan_category')
            ->where('plan_category', '!=', '')
            ->distinct()
            ->pluck('plan_category')
            ->all();

        return collect(array_keys(self::suggested()))
            ->merge($inUse)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
