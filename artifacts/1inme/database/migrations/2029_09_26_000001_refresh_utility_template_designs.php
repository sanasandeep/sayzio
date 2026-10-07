<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        (new \Database\Seeders\CardTemplateSeeder)->run();
    }
    public function down(): void
    {
        // Retain designs and admin edits.
    }
};
