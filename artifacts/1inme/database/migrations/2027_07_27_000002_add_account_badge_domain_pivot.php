<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Badge-gating for admin-global domains. Mirrors the existing `domain_plan`
 * pivot: an admin can tag a global domain with one or more account badges so
 * that, alongside plan tags, the domain is offered to accounts that hold a
 * matching badge.
 *
 * Gating combines across tag types with OR: an untagged global domain (no
 * plans AND no badges) stays open to everyone; a tagged one is offered when
 * the account matches ANY plan tag OR ANY badge tag.
 *
 * Additive + idempotent (shared-RDS rules): guarded by hasTable so a partial
 * re-run is safe; never drops domain rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('account_badge_domain')) {
            Schema::create('account_badge_domain', function (Blueprint $table) {
                $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
                $table->foreignId('account_badge_id')->constrained('account_badges')->cascadeOnDelete();
                $table->primary(['domain_id', 'account_badge_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_badge_domain');
    }
};
