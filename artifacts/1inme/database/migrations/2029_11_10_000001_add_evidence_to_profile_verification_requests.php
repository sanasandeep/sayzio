<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('profile_verification_requests', function (Blueprint $table) {
            $table->json('supporting_urls')->nullable();
            $table->text('additional_info')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('profile_verification_requests', function (Blueprint $table) {
            $table->dropColumn(['supporting_urls', 'additional_info']);
        });
    }
};
