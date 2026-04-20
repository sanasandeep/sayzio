<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (!Schema::hasColumn('plans', 'is_archived')) {
                $table->boolean('is_archived')->default(false)->after('status');
            }
            if (!Schema::hasColumn('plans', 'metadata')) {
                $table->jsonb('metadata')->nullable()->after('features');
            }
        });

        if (!Schema::hasTable('addons')) {
            Schema::create('addons', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('type')->default('recurring');
                $table->decimal('monthly_price', 10, 2)->default(0);
                $table->decimal('annual_price', 10, 2)->default(0);
                $table->jsonb('features')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->string('status')->default('active');
                $table->boolean('is_archived')->default(false);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('addon_plan')) {
            Schema::create('addon_plan', function (Blueprint $table) {
                $table->id();
                $table->foreignId('addon_id')->constrained('addons')->cascadeOnDelete();
                $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['addon_id', 'plan_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_plan');
        Schema::dropIfExists('addons');
        Schema::table('plans', function (Blueprint $table) {
            if (Schema::hasColumn('plans', 'metadata')) {
                $table->dropColumn('metadata');
            }
            if (Schema::hasColumn('plans', 'is_archived')) {
                $table->dropColumn('is_archived');
            }
        });
    }
};
