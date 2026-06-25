<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Admin\Support\CronJobsInspector;
use App\Modules\Api\Controllers\Concerns\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;

/**
 * Bearer-token parity for the web admin "Cron Jobs" reference page so a
 * super admin can review which scheduled jobs the server must run — and the
 * single master crontab line that drives them — from the Sayzio Mobile app.
 *
 * This is the mobile counterpart of
 * {@see \App\Modules\Admin\Controllers\CronJobsController}. Both surfaces share
 * the SAME engine ({@see CronJobsInspector}), which derives the list live from
 * Laravel's registered schedule (routes/console.php), so the two views never
 * drift apart:
 *
 *   GET /api/v1/admin/cron-jobs   (read-only reference list)
 *
 * The endpoint is read-only and informational — it never triggers or edits a
 * schedule — and is gated behind the same `settings.manage` permission the web
 * route uses, so only platform admins reach it; a regular sanctum token is
 * rejected with 403.
 */
class CronJobsController extends Controller
{
    use ApiResponses;

    /**
     * Read-only reference: the single master crontab line an operator must add
     * to the server, plus the derived list of every scheduled command (command,
     * plain-English frequency, raw cron expression, purpose and next run).
     */
    public function index(Request $request, CronJobsInspector $inspector)
    {
        if (! $request->user()->hasPermission('settings.manage')) {
            return $this->forbidden('You are not allowed to view cron jobs.');
        }

        return $this->ok([
            'master_cron_line' => $inspector->masterCronLine(),
            'app_path'         => base_path(),
            'jobs'             => array_map(
                fn (array $job) => $this->jobPayload($job),
                $inspector->jobs(),
            ),
        ]);
    }

    /**
     * Shape a single {@see CronJobsInspector::jobs()} entry for the API,
     * serialising the Carbon `next_run` as an ISO-8601 string.
     *
     * @param array<string, mixed> $job
     * @return array<string, mixed>
     */
    private function jobPayload(array $job): array
    {
        $nextRun = $job['next_run'] ?? null;

        return [
            'is_callback'         => (bool) ($job['is_callback'] ?? false),
            'command'             => $job['command'] ?? '',
            'manual_command'      => $job['manual_command'] ?? null,
            'expression'          => $job['expression'] ?? '',
            'frequency'           => $job['frequency'] ?? '',
            'purpose'             => $job['purpose'] ?? '—',
            'next_run'            => $nextRun instanceof Carbon ? $nextRun->toIso8601String() : null,
            'without_overlapping' => (bool) ($job['without_overlapping'] ?? false),
            'on_one_server'       => (bool) ($job['on_one_server'] ?? false),
            'running_now'         => (bool) ($job['running_now'] ?? false),
        ];
    }
}
