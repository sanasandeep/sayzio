<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('cities')) {
            Schema::create('cities', function (Blueprint $t) {
                $t->id();
                $t->string('country_code', 2);
                $t->string('city_normalized', 120);
                $t->string('city_name', 160);
                $t->decimal('latitude', 9, 5);
                $t->decimal('longitude', 9, 5);
                $t->unsignedBigInteger('population')->nullable();
                $t->unique(['country_code', 'city_normalized'], 'cities_cc_city_unique');
                $t->index('country_code');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
