<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biolink_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 32)->index();
            $table->text('comment')->nullable();
            $table->string('reporter_ip', 64)->nullable()->index();
            $table->string('user_agent', 500)->nullable();
            // pending | dismissed | warned | hidden | escalated | coalesced
            $table->string('status', 16)->default('pending')->index();
            $table->unsignedInteger('coalesced_count')->default(1);
            $table->timestamp('actioned_at')->nullable();
            $table->string('admin_note', 1000)->nullable();
            $table->timestamps();

            $table->index(['link_id', 'status']);
            $table->index(['link_id', 'reporter_ip', 'created_at']);
        });

        Schema::table('links', function (Blueprint $table) {
            // null | warned | hidden | escalated
            $table->string('moderation_state', 16)->nullable()->index();
            $table->string('moderation_reason', 64)->nullable();
            $table->text('moderation_note')->nullable();
            $table->timestamp('moderation_at')->nullable();
            $table->timestamp('moderation_appealed_at')->nullable();
            $table->text('moderation_appeal_message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropColumn([
                'moderation_state',
                'moderation_reason',
                'moderation_note',
                'moderation_at',
                'moderation_appealed_at',
                'moderation_appeal_message',
            ]);
        });
        Schema::dropIfExists('biolink_reports');
    }
};
