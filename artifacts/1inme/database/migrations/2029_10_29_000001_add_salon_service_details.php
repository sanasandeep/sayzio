<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_booking_services', function (Blueprint $table) {
            $table->boolean('price_from')->default(false);
            $table->text('preparation_notes')->nullable();
            $table->text('aftercare_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('service_booking_services', function (Blueprint $table) {
            $table->dropColumn(['price_from', 'preparation_notes', 'aftercare_notes']);
        });
    }
};
