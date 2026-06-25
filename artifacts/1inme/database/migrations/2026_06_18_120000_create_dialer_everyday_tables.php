<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Speed-dial / favorites — per-user, ordered, syncable web+mobile. A
        // favorite is either an attached contact (contact_id) or a raw number
        // (number_e164); we keep label as a cached display name so the strip
        // renders without a join even for raw numbers.
        if (!Schema::hasTable('dialer_favorites')) {
            Schema::create('dialer_favorites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
                $table->string('number_e164', 32)->nullable();
                $table->string('label', 191)->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->index(['user_id', 'sort_order']);
                $table->index(['user_id', 'number_e164']);
            });
        }

        // Per-user spam flag + block list. One row per (user, number); a number
        // can be flagged as spam (badge only) and/or blocked (hidden from
        // recents/frequent and warned before dialing). Purely per-user — no
        // crowd-sourced DB (out of scope).
        if (!Schema::hasTable('dialer_number_flags')) {
            Schema::create('dialer_number_flags', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('number_e164', 32);
                $table->boolean('is_spam')->default(false);
                $table->boolean('is_blocked')->default(false);
                $table->timestamps();
                $table->unique(['user_id', 'number_e164']);
            });
        }

        // Enrich the existing call log instead of replacing it. Each logged
        // call/lookup row can carry an outcome, a short note + tag (mini-CRM),
        // and an optional callback reminder time (delivered once, then stamped).
        Schema::table('dialer_lookups', function (Blueprint $table) {
            if (!Schema::hasColumn('dialer_lookups', 'outcome')) {
                $table->string('outcome', 20)->nullable()->after('contact_id');
                $table->index(['user_id', 'number_e164']);
            }
            if (!Schema::hasColumn('dialer_lookups', 'note')) {
                $table->text('note')->nullable()->after('outcome');
            }
            if (!Schema::hasColumn('dialer_lookups', 'tag')) {
                $table->string('tag', 50)->nullable()->after('note');
            }
            if (!Schema::hasColumn('dialer_lookups', 'callback_at')) {
                $table->timestampTz('callback_at')->nullable()->after('tag');
            }
            if (!Schema::hasColumn('dialer_lookups', 'callback_notified_at')) {
                $table->timestampTz('callback_notified_at')->nullable()->after('callback_at');
                $table->index(['callback_at', 'callback_notified_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('dialer_lookups', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'number_e164']);
            $table->dropIndex(['callback_at', 'callback_notified_at']);
            $table->dropColumn(['outcome', 'note', 'tag', 'callback_at', 'callback_notified_at']);
        });
        Schema::dropIfExists('dialer_number_flags');
        Schema::dropIfExists('dialer_favorites');
    }
};
