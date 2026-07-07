<?php

use App\Modules\Admin\Support\ScheduledJobRegistry;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled jobs — registry-driven
|--------------------------------------------------------------------------
| Every scheduled job is declared ONCE in routes/schedules/<group>.php and
| loaded through ScheduledJobRegistry (key, group, description, cadence,
| overlap lock, protected flag). This loop registers the live Laravel
| schedule from those definitions, so the registry, the admin "Scheduled
| Jobs" panel and the actual schedule can never drift apart (guarded by a
| lockstep feature test).
|
| Behaviour preserved from the previous per-job registration:
|   - cadences are applied verbatim from each definition's cadence spec;
|   - every job runs withoutOverlapping() + onOneServer() (contacts:sync
|     keeps its 10-minute lock expiry);
|   - the creator-digest closure keeps its ->name() (a CallbackEvent's
|     mutex is keyed by its name, so renaming it would orphan the lock).
|
| New: every event gets a ->skip() filter honouring the persisted pause
| switch (AppSetting `scheduled_jobs.paused`) driven by the admin panel /
| API. Protected jobs can never be paused (the registry refuses), so the
| filter is inert for them.
*/
foreach (ScheduledJobRegistry::all() as $key => $job) {
    if (isset($job['callback'])) {
        [$class, $method] = explode('@', $job['callback'], 2);

        // name() BEFORE withoutOverlapping(): a CallbackEvent's mutex name is
        // derived from its description (the name). Never call ->description()
        // on a callback event afterwards — it would overwrite the name.
        $event = Schedule::call(fn () => app($class)->{$method}())->name($key);
    } else {
        // For command events the description is display-only (the mutex is
        // keyed by expression+command), so it's safe to attach the registry's
        // operator-facing purpose line here.
        $event = Schedule::command($job['command'])->description($job['description']);
    }

    $cadence = $job['cadence'];
    $cadenceMethod = array_shift($cadence);
    $event->{$cadenceMethod}(...$cadence);

    $event->withoutOverlapping($job['without_overlapping'] ?? 1440)
        ->onOneServer()
        ->skip(fn (): bool => ScheduledJobRegistry::isPaused($key));
}
