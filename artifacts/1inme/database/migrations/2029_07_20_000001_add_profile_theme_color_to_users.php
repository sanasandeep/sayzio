<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable hex theme/accent color that creators can choose for
 * their public /@handle profile page.  Stored as a 7-character hex
 * string (e.g. "#e11d48").  Null means "use the platform default".
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'profile_theme_color')) {
                $table->string('profile_theme_color', 7)->nullable()->after('profile_section_visibility');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'profile_theme_color')) {
                $table->dropColumn('profile_theme_color');
            }
        });
    }
};
