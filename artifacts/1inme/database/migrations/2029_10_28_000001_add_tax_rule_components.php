<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { if (!Schema::hasColumn('tax_rules', 'components')) Schema::table('tax_rules', fn (Blueprint $table) => $table->json('components')->nullable()); }
    public function down(): void { if (Schema::hasColumn('tax_rules', 'components')) Schema::table('tax_rules', fn (Blueprint $table) => $table->dropColumn('components')); }
};
