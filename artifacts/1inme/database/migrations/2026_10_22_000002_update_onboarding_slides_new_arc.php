<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the old 10-slide "link-in-bio persona" intro arc with the new
 * 5-slide AI-business-companion intro arc.
 *
 * Idempotent / additive-safe on the shared RDS:
 *   - Uses INSERT … ON CONFLICT DO UPDATE (upsert) for the 5 new slides so
 *     re-running is safe regardless of whether the seeder already ran.
 *   - Deactivates any slide whose slug is NOT one of the 5 new ones so the
 *     OnboardingSlideController (which filters by active()) stops serving them.
 *   - Never truncates or drops rows — old data stays for audit.
 */
return new class extends Migration {
    /** Slugs that should remain active after this migration. */
    private const ACTIVE_SLUGS = [
        'welcome',
        'every-business',
        'everything-you-need',
        'work-smarter',
        'start-free',
    ];

    private const NEW_SLIDES = [
        [
            'slug'       => 'welcome',
            'sort_order' => 10,
            'category'   => 'Welcome',
            'title'      => 'Meet Sayzio',
            'body'       => 'Your AI-powered business companion for smarter customer engagement.',
        ],
        [
            'slug'       => 'every-business',
            'sort_order' => 20,
            'category'   => 'Built for Every Business',
            'title'      => 'One Platform. Every Business.',
            'body'       => "Whether you're a startup, coach, restaurant, retailer, clinic, freelancer, or nonprofit, Sayzio helps you connect and grow.",
        ],
        [
            'slug'       => 'everything-you-need',
            'sort_order' => 30,
            'category'   => 'Everything You Need',
            'title'      => 'All Your Business Tools in One Place',
            'body'       => null,
        ],
        [
            'slug'       => 'work-smarter',
            'sort_order' => 40,
            'category'   => 'Work Smarter',
            'title'      => 'Automate. Engage. Grow.',
            'body'       => 'Let AI answer questions, capture leads, schedule appointments, and support customers 24/7.',
        ],
        [
            'slug'       => 'start-free',
            'sort_order' => 50,
            'category'   => 'Start Free',
            'title'      => 'Ready to Grow?',
            'body'       => "Create your free workspace in minutes and upgrade whenever you're ready.",
        ],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('onboarding_slides')) {
            return;
        }

        $now = now();

        // 1. Upsert the 5 new slides — insert or update on slug conflict.
        foreach (self::NEW_SLIDES as $row) {
            DB::table('onboarding_slides')->upsert(
                [
                    'slug'       => $row['slug'],
                    'category'   => $row['category'],
                    'title'      => $row['title'],
                    'body'       => $row['body'],
                    'status'     => 'active',
                    'sort_order' => $row['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['slug'],
                ['category', 'title', 'body', 'status', 'sort_order', 'updated_at'],
            );
        }

        // 2. Deactivate every slide whose slug is not in the new active set.
        DB::table('onboarding_slides')
            ->whereNotIn('slug', self::ACTIVE_SLUGS)
            ->update(['status' => 'inactive', 'updated_at' => $now]);
    }

    public function down(): void
    {
        // Reactivate the old slides and deactivate the new ones.
        if (!Schema::hasTable('onboarding_slides')) {
            return;
        }

        $now = now();

        DB::table('onboarding_slides')
            ->whereIn('slug', self::ACTIVE_SLUGS)
            ->update(['status' => 'inactive', 'updated_at' => $now]);

        DB::table('onboarding_slides')
            ->whereNotIn('slug', self::ACTIVE_SLUGS)
            ->update(['status' => 'active', 'updated_at' => $now]);
    }
};
