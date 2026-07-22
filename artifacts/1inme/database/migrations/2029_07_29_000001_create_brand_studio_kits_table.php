<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Brand Studio (Task #5551) — a "kit" groups one studio run: the brand
 * context + request that produced an AI proposal, the reviewed proposal
 * itself, and (after confirm) the created asset references.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('brand_studio_kits')) {
            return;
        }

        Schema::create('brand_studio_kits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('name', 190);
            $table->string('mode', 20)->default('kit');      // kit | bulk
            $table->string('status', 20)->default('proposal'); // proposal | created
            $table->text('request')->nullable();               // the plain-language brief
            $table->json('brand')->nullable();                 // brand context snapshot (kit id/name or inline details)
            $table->json('proposal')->nullable();              // AI-proposed assets (reviewed/edited client-side)
            $table->json('results')->nullable();               // created asset references
            $table->unsignedInteger('credits_spent')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_studio_kits');
    }
};
