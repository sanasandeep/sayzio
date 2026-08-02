<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Undo-able contact merges: every merge records an audit row carrying a
 * full snapshot of the deleted (source/loser) contact plus the ids of every
 * row that was repointed or created on the surviving primary. A time-limited
 * "Undo merge" recreates the source contact and repoints the recorded rows
 * back. No FK constraints — the primary contact may itself be deleted or
 * merged away later and the audit row must survive as a historical record.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contact_merge_audits')) {
            return;
        }
        Schema::create('contact_merge_audits', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->unsignedBigInteger('primary_contact_id')->index();
            $t->unsignedBigInteger('source_contact_id');
            // Full attribute snapshot of the deleted contact, incl. its
            // phones/emails, so undo can faithfully recreate it.
            $t->json('source_snapshot');
            // Map of table => [row ids] moved to (or created on) the primary
            // during the merge, so undo can repoint/remove exactly those.
            $t->json('moved');
            $t->unsignedBigInteger('restored_contact_id')->nullable();
            $t->timestamp('undone_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_merge_audits');
    }
};
