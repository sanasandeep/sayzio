<?php

use Database\Seeders\CardTemplateSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // The seeder preserves admin-customized templates and does not touch
        // pages already created from a template.
        (new CardTemplateSeeder)->run();
    }

    public function down(): void
    {
        // Preserve template edits made after this content refresh.
    }
};
