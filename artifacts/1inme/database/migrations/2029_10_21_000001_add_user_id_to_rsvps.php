<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tie an RSVP to the account that made it, when there was one.
 *
 * An RSVP has never carried a user_id. Anyone can RSVP to a public event
 * without an account, which is the point, so the row only ever held a name
 * and an email typed into a form. The cost is that a Sayzio user who RSVPs
 * while logged in leaves no trace the product can follow: their own
 * dashboard cannot show them the event they are going to, and nothing can
 * put two attendees of the same event in touch.
 *
 * Nullable on purpose and left null for guests. This only records a link
 * that was already true at the moment of submission; it never claims an
 * existing RSVP for an account after the fact.
 *
 * Additive and idempotent, per the shared-RDS convention in this folder.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('rsvps', 'user_id')) {
            Schema::table('rsvps', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('link_id');
                $table->index(['user_id', 'link_id'], 'rsvps_user_link_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('rsvps', function (Blueprint $table) {
            if (Schema::hasColumn('rsvps', 'user_id')) {
                $table->dropIndex('rsvps_user_link_index');
                $table->dropColumn('user_id');
            }
        });
    }
};
