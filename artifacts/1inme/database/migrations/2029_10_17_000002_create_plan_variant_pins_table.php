<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which plan variant a given visitor has been shown, per category.
 *
 * Without this, switching a category's default would silently re-price every
 * visitor mid-decision: someone who saw a plan at one price on Monday would
 * find a different price on Thursday with no explanation. A pin is written
 * the first time a visitor is shown a category and is never rewritten, so the
 * price a person was quoted is the price they keep.
 *
 * Two kinds of owner, exactly one set per row:
 *  - `user_id` for a signed-in account, which follows them across devices.
 *  - `visitor_token` for a logged-out browser, from a long-lived cookie. On
 *    signup the token's rows are claimed by the new user, so the price
 *    someone saw before registering survives registering.
 *
 * `plan_id` is what they saw. It is deliberately NOT a foreign key cascade to
 * delete: a pin outliving an archived plan tells us a visitor is still owed
 * that price, which is a thing support needs to see rather than lose.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plan_variant_pins')) {
            return;
        }

        Schema::create('plan_variant_pins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('visitor_token', 64)->nullable();
            $table->string('plan_category', 64);
            $table->unsignedBigInteger('plan_id');
            $table->timestamps();

            $table->unique(['user_id', 'plan_category'], 'plan_variant_pins_user_category');
            $table->unique(['visitor_token', 'plan_category'], 'plan_variant_pins_visitor_category');
            $table->index('plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_variant_pins');
    }
};
