<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Run-history rows for scheduled (cron) jobs — one row per execution,
 * written by ScheduledJobRunRecorder (scheduler lifecycle events) and by
 * the manual `scheduled-jobs:run` command. Powers the duration / exit-code
 * / failure columns and the per-job history drawer on the admin
 * "Scheduled Jobs" panel (+ its /api/v1/admin/scheduled-jobs parity).
 *
 * Rows are pruned opportunistically (lottery, keep the newest ~100 per
 * job) so the table stays small on the shared RDS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scheduled_job_runs')) {
            return;
        }

        Schema::create('scheduled_job_runs', function (Blueprint $table) {
            $table->id();
            $table->string('job_key', 191);
            // 'schedule' (fired by schedule:run) or 'manual' (run-now).
            $table->string('source', 20)->default('schedule');
            // 'running' | 'success' | 'failed'.
            $table->string('status', 20)->default('running');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            // Wall-clock seconds, as reported by the scheduler / measured by the runner.
            $table->decimal('runtime', 10, 3)->nullable();
            $table->integer('exit_code')->nullable();
            $table->text('error')->nullable();

            // Latest-N-per-job lookups (history drawer + last-run join).
            $table->index(['job_key', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_job_runs');
    }
};
