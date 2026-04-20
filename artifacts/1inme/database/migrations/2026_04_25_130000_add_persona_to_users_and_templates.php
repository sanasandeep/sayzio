<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('persona', 40)->nullable()->after('language');
            $table->timestamp('onboarded_at')->nullable()->after('persona');
        });

        // Existing accounts predate the wizard — mark them as onboarded so
        // they don't get yanked into it on their next login. They'll still
        // see a soft, dismissible banner if they haven't picked a persona.
        DB::table('users')->whereNull('onboarded_at')->update(['onboarded_at' => now()]);

        Schema::table('page_templates', function (Blueprint $table) {
            $table->json('recommended_personas')->nullable()->after('plan_tier');
        });
    }

    public function down(): void
    {
        Schema::table('page_templates', function (Blueprint $table) {
            $table->dropColumn('recommended_personas');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['persona', 'onboarded_at']);
        });
    }
};
