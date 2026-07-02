<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets each additional alias (link_aliases row) point at its own custom
 * domain, independently of the link's primary domain_id. Null means the
 * alias renders on the default/platform host (mirrors links.domain_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('link_aliases', function (Blueprint $table) {
            if (!Schema::hasColumn('link_aliases', 'domain_id')) {
                $table->foreignId('domain_id')->nullable()->after('alias')
                    ->constrained('domains')->nullOnDelete();
                $table->index(['domain_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('link_aliases', function (Blueprint $table) {
            if (Schema::hasColumn('link_aliases', 'domain_id')) {
                $table->dropConstrainedForeignId('domain_id');
            }
        });
    }
};
