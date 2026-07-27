<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Changelog entries backing the admin "Versions & Releases" hub. One row per
 * (surface, version): web app, marketing site, mobile app, Zio Dialer,
 * Zio Browser, browser extension, api-server, docs. Admin-managed CRUD, with
 * Zio Browser rows auto-inserted from the cached GitHub release feed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('releases')) {
            return;
        }

        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->string('surface', 50)->index();       // web|marketing|mobile|dialer|zio_browser|extension|api_server|docs
            $table->string('version', 100);
            $table->date('released_at')->nullable();
            $table->text('notes')->nullable();            // markdown changelog notes
            $table->string('source', 20)->default('manual'); // manual|github|seed
            $table->timestamps();

            $table->unique(['surface', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('releases');
    }
};
