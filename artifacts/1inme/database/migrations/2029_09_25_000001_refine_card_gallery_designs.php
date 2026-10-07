<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $seeder = new \Database\Seeders\CardTemplateSeeder;
        $seeder->recoverLibraryBaselines();
        $seeder->run();
    }

    public function down(): void
    {
        // Preserve templates and admin work on rollback.
    }
};
