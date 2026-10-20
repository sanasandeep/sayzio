<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Plan copy should not pitch one country.
 *
 * /pricing was telling everyone, whatever their currency or location:
 *
 *   "A focused, affordable Link in Bio plan — built for creators in India
 *    getting started with their first bio page."
 *
 * Prices are already localised -- PricingResolver picks INR or USD per
 * visitor -- so a visitor paying in dollars was reading copy addressed to
 * somebody else. Sana's call: drop the geography and talk about growing,
 * for everyone.
 *
 * Matched on the description text rather than the plan slug, because the
 * slug is an assumption and the text is what was measured on the live page.
 * Mirrors 2029_10_16_000002_correct_bio_plan_descriptions: replace only
 * known copy, so an administrator's own wording is never overwritten.
 */
return new class extends Migration
{
    /**
     * Exact strings seen in production, mapped to their replacement. No new
     * feature claims: the old copy's promises are kept, only the geography
     * goes, because a description is not the place to invent what a plan
     * includes.
     */
    private const REPLACEMENTS = [
        'A focused, affordable Link in Bio plan — built for creators in India getting started with their first bio page.'
            => 'A focused, affordable Link in Bio plan — start with your first bio page and grow from there.',

        // Carried from the earlier correction, whose `old` list this exact
        // string was in: harmless where it already ran, useful where the
        // stored copy never matched and the India framing is still live.
        'A power-user Link in Bio plan with custom domains, team collaboration, and expanded AI tools — for creators in India ready to grow beyond the basics.'
            => 'A power-user Link in Bio plan with custom domains, team collaboration and expanded AI tools, for creators ready to grow beyond the basics.',
    ];

    public function up(): void
    {
        foreach (self::REPLACEMENTS as $old => $new) {
            DB::table('plans')
                ->where('description', $old)
                ->update(['description' => $new, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Deliberately not reversed: restoring it would put country-specific
        // copy back in front of every visitor.
    }
};
