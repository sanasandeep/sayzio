<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-managed list of names that users cannot claim as a profile handle
 * or link alias (e.g. "admin", "support", "login"). Matching is
 * case-insensitive — a unique functional index on LOWER(name) enforces
 * that at the database level so two admins can't accidentally insert the
 * same entry with different casing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banned_names', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('note', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX banned_names_name_lower_unique ON banned_names (LOWER(name))');
    }

    public function down(): void
    {
        Schema::dropIfExists('banned_names');
    }
};
