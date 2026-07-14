<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('testimonials')) {
            return;
        }

        if (!Schema::hasColumn('testimonials', 'status')) {
            Schema::table('testimonials', function (Blueprint $table) {
                $table->string('status', 20)->default('approved')->after('sort_order');
            });
            DB::table('testimonials')->whereNull('status')->orWhere('status', '')->update(['status' => 'approved']);
        }

        if (!Schema::hasColumn('testimonials', 'source')) {
            Schema::table('testimonials', function (Blueprint $table) {
                $table->string('source', 20)->default('admin')->after('status');
            });
            DB::table('testimonials')->whereNull('source')->orWhere('source', '')->update(['source' => 'admin']);
        }

        if (!Schema::hasColumn('testimonials', 'submitter_email')) {
            Schema::table('testimonials', function (Blueprint $table) {
                $table->string('submitter_email', 200)->nullable()->after('source');
            });
        }

        if (!Schema::hasColumn('testimonials', 'submitted_at')) {
            Schema::table('testimonials', function (Blueprint $table) {
                $table->timestamp('submitted_at')->nullable()->after('submitter_email');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('testimonials')) {
            return;
        }
        Schema::table('testimonials', function (Blueprint $table) {
            foreach (['status', 'source', 'submitter_email', 'submitted_at'] as $col) {
                if (Schema::hasColumn('testimonials', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
