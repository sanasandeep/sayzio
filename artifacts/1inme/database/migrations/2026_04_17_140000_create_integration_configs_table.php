<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('integration_configs')) {
            Schema::create('integration_configs', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->string('kind', 16);                 // payment | sms | email
                $t->string('provider', 32);             // stripe | twilio | smtp | ...
                $t->string('name', 120);                // user-given label
                $t->boolean('is_active')->default(true);
                $t->boolean('is_default')->default(false);
                $t->text('credentials')->nullable();    // encrypted JSON
                $t->jsonb('meta')->nullable();          // non-secret extras (from address, sender id, etc.)
                $t->timestamps();

                $t->index(['user_id', 'kind']);
                $t->index(['user_id', 'kind', 'provider']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_configs');
    }
};
