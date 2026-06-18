<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Owner-entered Identity Profile additions for the Dialer hub. Kept in
     * a dedicated column so they stay distinct from auto-pulled biolink data
     * and from the Google-sync `socials` column. Shape:
     * { "channels": [{type,label,value}],
     *   "socials":  [{platform,label,url}],
     *   "location": {label,address,lat,lng} | null }
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->json('manual_profile')->nullable()->after('socials');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('manual_profile');
        });
    }
};
