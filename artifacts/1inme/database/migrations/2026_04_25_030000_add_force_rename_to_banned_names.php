<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the "force rename on next login" toggle to banned-name entries.
 * When enabled, any user whose handle matches a banned entry is bounced
 * to their profile-edit page on their next sign-in and asked to pick a
 * new handle. Per-user acknowledgements live in their own table so
 * admins can also dismiss specific conflicts without flipping the flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banned_names', function (Blueprint $table) {
            $table->boolean('force_rename_on_login')->default(false)->after('note');
        });

        if (!Schema::hasTable('banned_name_acknowledgements')) {
            Schema::create('banned_name_acknowledgements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('banned_name_id');
                $table->string('conflict_type', 16); // user | link | extra
                $table->unsignedBigInteger('conflict_id');
                $table->unsignedBigInteger('acknowledged_by')->nullable();
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamps();

                $table->unique(['banned_name_id', 'conflict_type', 'conflict_id'], 'banned_name_ack_unique');
                $table->index('banned_name_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('banned_name_acknowledgements');
        Schema::table('banned_names', function (Blueprint $table) {
            $table->dropColumn('force_rename_on_login');
        });
    }
};
