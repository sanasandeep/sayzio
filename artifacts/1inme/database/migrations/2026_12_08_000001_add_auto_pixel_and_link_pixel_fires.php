<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('links') && !Schema::hasColumn('links', 'auto_pixel')) {
            Schema::table('links', function (Blueprint $t) {
                // Default false at the column level — controllers compute the
                // "default true when workspace has pixels configured" flip.
                $t->boolean('auto_pixel')->default(false)->after('is_active');
                $t->index(['auto_pixel']);
            });
        }

        if (!Schema::hasTable('link_pixel_fires')) {
            Schema::create('link_pixel_fires', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('link_id');
                $t->unsignedBigInteger('workspace_id')->nullable();
                // Comma-separated providers attempted, e.g. "meta,tiktok".
                $t->string('providers', 120);
                // Lowercased SHA-256 of (visitor IP + user agent + day) so we
                // never store a raw fingerprint and roll over daily.
                $t->string('visitor_hash', 64)->nullable();
                $t->timestamp('fired_at')->useCurrent();

                $t->index(['link_id', 'fired_at']);
                $t->index(['workspace_id', 'fired_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('link_pixel_fires');
        if (Schema::hasTable('links') && Schema::hasColumn('links', 'auto_pixel')) {
            Schema::table('links', function (Blueprint $t) {
                try { $t->dropIndex(['auto_pixel']); } catch (\Throwable $e) {}
                $t->dropColumn('auto_pixel');
            });
        }
    }
};
