<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-editable additions and overrides for the seven compiled-in
 * background catalogs.
 *
 * The 485 shipped looks STAY in their PHP constants. They are the
 * defaults, they ship with the code, and nothing is migrated out of them
 * -- a migration that moved 485 curated entries into rows would put the
 * library one bad `php artisan migrate:fresh` away from empty, and would
 * make every future shipped look a seeder concern.
 *
 * A row here does one of three things, decided by whether its
 * (kind, entry_key) matches a shipped one:
 *
 *   new key          -> a look that did not exist before
 *   shipped key      -> that look renders from this row instead
 *   is_active = false -> hidden from the picker
 *
 * Hiding never removes a look from the RENDERER. A page that saved
 * `pattern_dots_dark` two years ago keeps rendering it after an admin
 * hides it; hiding only stops it being offered to anyone new. Deleting
 * the row restores the shipped default -- which is the closest thing to
 * an undo the compiled catalogs can offer, and is why overriding beats
 * migrating.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bg_catalog_entries', function (Blueprint $table) {
            $table->id();
            // preset | gradient | mesh | pattern | tiles | torn | torn_style
            $table->string('kind', 20);
            // The key saved into link settings. For a shipped key this row
            // overrides that look; for a new one it adds a look.
            $table->string('entry_key', 100);
            $table->string('label', 120);
            // Shape depends on the kind -- see BgCatalogEntry::rulesFor().
            $table->json('payload');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['kind', 'entry_key']);
            $table->index(['kind', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bg_catalog_entries');
    }
};
