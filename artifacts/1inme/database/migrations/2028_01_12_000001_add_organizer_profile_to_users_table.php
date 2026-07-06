<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3699 — reusable event organizer profile. Account-wide details
 * (logo, name, description, website, contact person, socials, address)
 * shown on every one of the creator's events, instead of a per-event
 * organizer override. Stored as one JSON blob, mirroring the `socials`
 * column pattern already on `users`.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'organizer_profile')) {
                // {logo, name, description, website, contact_name,
                //  contact_phone, contact_email, socials: {}, address}
                $table->json('organizer_profile')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'organizer_profile')) {
                $table->dropColumn('organizer_profile');
            }
        });
    }
};
