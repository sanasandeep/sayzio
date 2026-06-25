<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #1209 — Subscriptions, per-post paywall (blurred preview), and
 * tipping. Adds the fan-facing monetization surfaces on the Creator
 * Profile (/@handle), all routed to the creator's default connected
 * payout provider (Task #1208) with 0% platform fee.
 *
 * Schema:
 *   - subscription_tiers           : creator-defined paid (and free) tiers
 *   - subscription_promo_codes     : creator's bundles / promo codes
 *   - creator_subscriptions        : fan ↔ creator subscription rows
 *   - post_unlocks                 : per-post pay-per-view unlocks
 *   - creator_tips                 : one-off tips (profile or post level)
 *   - creator_payment_events       : unified ledger / activity log used
 *                                    by the dashboard tabs and reconcile
 *                                    flows (refunds revoke matching access)
 *
 * Plus columns on `creator_posts` to flag visibility (free / tier-gated /
 * pay-per-view) and the blurred-preview render settings.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('subscription_tiers')) {
            Schema::create('subscription_tiers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('name', 80);
                // url-safe slug used to deep-link from the profile to a tier.
                $table->string('slug', 80);
                // Always exactly one row per creator with is_free=true. Free
                // tiers price stays at 0 and yearly is hidden.
                $table->boolean('is_free')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                // Monthly price in the smallest currency unit (cents).
                $table->unsignedInteger('price_monthly_cents')->default(0);
                // Optional yearly price; when null the UI hides the toggle.
                $table->unsignedInteger('price_yearly_cents')->nullable();
                $table->string('currency', 3)->default('USD');
                // Display copy (perks list, lines of bullet text). Stored
                // as an array of strings so the UI can render bullet rows.
                $table->json('perks')->nullable();
                // Tailwind-friendly colour token for the badge / accent.
                $table->string('color', 16)->default('violet');
                // 0–N badge emoji / icon fragment surfaced next to the tier
                // name in lists and feeds (e.g. "💎", "fas fa-crown").
                $table->string('badge', 32)->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'slug']);
                $table->index(['user_id', 'sort_order']);
                $table->index(['user_id', 'is_active']);
            });
        }

        if (!Schema::hasTable('subscription_promo_codes')) {
            Schema::create('subscription_promo_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                // Case-insensitive, unique per creator (we lowercase on save).
                $table->string('code', 40);
                $table->string('label', 120)->nullable();
                // percent | amount | months_free | founder | lifetime
                //   percent       — % off recurring, value=0–100
                //   amount        — flat cents off recurring, value=cents
                //   months_free   — N months free, value=count
                //   founder       — flat cents off, never expires for that fan
                //   lifetime      — one-off lifetime grant (no recurring)
                $table->string('kind', 20);
                $table->unsignedInteger('value')->default(0);
                // Restrict promo to a subset of tiers (null = applies to all).
                $table->json('applies_to_tier_ids')->nullable();
                // Accept only N redemptions globally; null = unlimited.
                $table->unsignedInteger('max_redemptions')->nullable();
                $table->unsignedInteger('redemptions_count')->default(0);
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['user_id', 'code']);
                $table->index(['user_id', 'is_active']);
            });
        }

        if (!Schema::hasTable('creator_subscriptions')) {
            Schema::create('creator_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fan_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('creator_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('tier_id')->constrained('subscription_tiers')->cascadeOnDelete();
                // monthly | yearly. Free tiers always store 'monthly' for
                // bookkeeping; price_cents stays at 0.
                $table->string('billing_cycle', 16)->default('monthly');
                // active | trialing | past_due | canceled | paused
                $table->string('status', 20)->default('active');
                $table->unsignedInteger('price_cents')->default(0);
                $table->string('currency', 3)->default('USD');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('current_period_start')->nullable();
                $table->timestamp('current_period_end')->nullable();
                $table->timestamp('canceled_at')->nullable();
                // When set, status stays 'active' until current_period_end
                // is reached (downgrades, fan-initiated cancel-at-period-end).
                $table->boolean('cancel_at_period_end')->default(false);
                $table->timestamp('last_payment_at')->nullable();
                // Provider routing — copied from the creator's default
                // CreatorPaymentConnection at checkout time so the row is
                // self-describing even if the creator switches providers.
                $table->string('gateway', 32)->nullable();
                $table->string('gateway_subscription_id', 191)->nullable();
                $table->foreignId('promo_code_id')->nullable()->constrained('subscription_promo_codes')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['creator_user_id', 'status']);
                $table->index(['fan_user_id', 'status']);
                $table->unique(['fan_user_id', 'creator_user_id'], 'creator_sub_fan_creator_unique');
            });
        }

        if (!Schema::hasTable('post_unlocks')) {
            Schema::create('post_unlocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('post_id')->constrained('creator_posts')->cascadeOnDelete();
                $table->foreignId('fan_user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedInteger('price_cents')->default(0);
                $table->string('currency', 3)->default('USD');
                $table->string('gateway', 32)->nullable();
                $table->string('gateway_charge_id', 191)->nullable();
                $table->timestamp('unlocked_at')->useCurrent();
                // Set when a refund webhook reverses the unlock — keeping the
                // row preserves history / dashboards but revokes access.
                $table->timestamp('refunded_at')->nullable();
                $table->timestamps();

                $table->unique(['post_id', 'fan_user_id']);
                $table->index(['fan_user_id', 'unlocked_at']);
            });
        }

        if (!Schema::hasTable('creator_tips')) {
            Schema::create('creator_tips', function (Blueprint $table) {
                $table->id();
                $table->foreignId('creator_user_id')->constrained('users')->cascadeOnDelete();
                // Nullable so a guest could in theory tip without an account
                // (we still require viewer auth in this task — kept nullable
                // for the next iteration that allows guest tipping).
                $table->foreignId('fan_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('post_id')->nullable()->constrained('creator_posts')->nullOnDelete();
                $table->unsignedInteger('amount_cents');
                $table->string('currency', 3)->default('USD');
                $table->string('note', 280)->nullable();
                $table->boolean('anonymous')->default(false);
                // succeeded | refunded | failed
                $table->string('status', 16)->default('succeeded');
                $table->string('gateway', 32)->nullable();
                $table->string('gateway_charge_id', 191)->nullable();
                $table->timestamp('refunded_at')->nullable();
                $table->timestamps();

                $table->index(['creator_user_id', 'created_at']);
                $table->index(['post_id']);
            });
        }

        // Unified event log so the Earnings / Payments dashboards can
        // render a single chronological feed without unioning four
        // tables on every page load. Each money-moving action also
        // writes a row here.
        if (!Schema::hasTable('creator_payment_events')) {
            Schema::create('creator_payment_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('creator_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('fan_user_id')->nullable()->constrained('users')->nullOnDelete();
                // sub | ppv | tip
                $table->string('source', 8);
                // sub.created | sub.renewed | sub.canceled | sub.refunded
                // ppv.unlocked | ppv.refunded
                // tip.received | tip.refunded
                $table->string('type', 32);
                // Optional polymorphic pointer back to the originating row.
                $table->string('reference_type', 64)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                // Signed cents — refunds are negative.
                $table->integer('amount_cents')->default(0);
                $table->string('currency', 3)->default('USD');
                $table->string('gateway', 32)->nullable();
                $table->string('gateway_event_id', 191)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at')->useCurrent();
                $table->timestamps();

                $table->index(['creator_user_id', 'occurred_at']);
                $table->index(['creator_user_id', 'source']);
                $table->index(['gateway', 'gateway_event_id']);
            });
        }

        Schema::table('creator_posts', function (Blueprint $table) {
            // free | tier | ppv  — drives the renderer + access policy.
            if (!Schema::hasColumn('creator_posts', 'visibility')) {
                $table->string('visibility', 8)->default('free')->after('post_type');
            }
            // tier ids that unlock this post (any subscriber to a tier
            // with sort_order >= the lowest in this list also unlocks it,
            // so an "All paid" gate is just the lowest paid tier here).
            if (!Schema::hasColumn('creator_posts', 'visible_tier_ids')) {
                $table->json('visible_tier_ids')->nullable()->after('visibility');
            }
            if (!Schema::hasColumn('creator_posts', 'ppv_price_cents')) {
                $table->unsignedInteger('ppv_price_cents')->nullable()->after('visible_tier_ids');
            }
            if (!Schema::hasColumn('creator_posts', 'ppv_currency')) {
                $table->string('ppv_currency', 3)->nullable()->after('ppv_price_cents');
            }
            // teaser_caption + blur + preview-counts.
            // Stored as JSON to keep the migration narrow and to leave
            // room for additional knobs (e.g. per-post pixelation style).
            if (!Schema::hasColumn('creator_posts', 'paywall_settings')) {
                $table->json('paywall_settings')->nullable()->after('ppv_currency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('creator_posts', function (Blueprint $table) {
            foreach ([
                'visibility', 'visible_tier_ids',
                'ppv_price_cents', 'ppv_currency', 'paywall_settings',
            ] as $col) {
                if (Schema::hasColumn('creator_posts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::dropIfExists('creator_payment_events');
        Schema::dropIfExists('creator_tips');
        Schema::dropIfExists('post_unlocks');
        Schema::dropIfExists('creator_subscriptions');
        Schema::dropIfExists('subscription_promo_codes');
        Schema::dropIfExists('subscription_tiers');
    }
};
