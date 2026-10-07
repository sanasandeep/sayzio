<?php

use Database\Seeders\CardTemplateSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new CardTemplateSeeder)->run();
    }

    public function down(): void
    {
        // Do not overwrite templates customized after the refresh.
    }
};
