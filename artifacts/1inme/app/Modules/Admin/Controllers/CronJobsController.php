<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Support\CronJobsInspector;

/**
 * Read-only admin reference for the server crontab. Lists every scheduled
 * Laravel command — derived live from routes/console.php via
 * CronJobsInspector — so an operator can confidently set up the single master
 * cron entry and understand what each job does. The page never triggers or
 * edits schedules; it is informational only.
 */
class CronJobsController extends Controller
{
    public function index(CronJobsInspector $inspector)
    {
        $jobs = $inspector->jobs();

        return view('admin.cron-jobs.index', [
            'masterCronLine' => $inspector->masterCronLine(),
            'jobs'           => $jobs,
            'status'         => $inspector->schedulerStatus($jobs),
            'appPath'        => base_path(),
        ]);
    }
}
