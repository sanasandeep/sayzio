<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('verification_requests')) {
            Schema::create('verification_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('link_id')->constrained()->cascadeOnDelete();
                $table->string('category'); // artist_creator, business_product
                $table->string('business_name');
                $table->string('display_name');
                $table->text('purpose');
                $table->string('logo_path')->nullable();
                $table->json('proof_files')->nullable();
                $table->string('status')->default('pending'); // pending, approved, rejected
                $table->text('admin_notes')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        Schema::table('links', function (Blueprint $table) {
            if (!Schema::hasColumn('links', 'is_verified')) {
                $table->boolean('is_verified')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('links', 'verified_name')) {
                $table->string('verified_name')->nullable()->after('is_verified');
            }
            if (!Schema::hasColumn('links', 'verified_logo')) {
                $table->string('verified_logo')->nullable()->after('verified_name');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_requests');
        Schema::table('links', function (Blueprint $table) {
            $table->dropColumn(['is_verified', 'verified_name', 'verified_logo']);
        });
    }
};
