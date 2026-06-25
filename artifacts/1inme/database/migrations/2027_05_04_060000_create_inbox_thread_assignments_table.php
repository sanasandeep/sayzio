<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Audit trail of every assignee change on an inbox thread:
        // who handed it off, who picked it up, and any note attached
        // to the handoff. Also records the closing assignee when a
        // thread is archived/resolved so we can answer "who handled
        // this conversation?" after the fact.
        if (!Schema::hasTable('inbox_thread_assignments')) {
            Schema::create('inbox_thread_assignments', function (Blueprint $t) {
                $t->id();
                $t->foreignId('thread_id')->constrained('inbox_threads')->cascadeOnDelete();
                $t->unsignedBigInteger('from_user_id')->nullable();
                $t->unsignedBigInteger('to_user_id')->nullable();
                $t->unsignedBigInteger('actor_user_id')->nullable();
                $t->string('action', 16); // assign|unassign|reassign|resolved
                $t->text('note')->nullable();
                $t->timestamp('created_at')->nullable();

                $t->index(['thread_id', 'created_at']);
                $t->index(['to_user_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_thread_assignments');
    }
};
