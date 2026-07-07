<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One execution of a scheduled job (see ScheduledJobRegistry for the job
 * catalog). Written by ScheduledJobRunRecorder for scheduler-fired runs and
 * by the `scheduled-jobs:run` command for manual run-now executions.
 *
 * No created_at/updated_at — started_at/finished_at are the record.
 */
class ScheduledJobRun extends Model
{
    public const SOURCE_SCHEDULE = 'schedule';
    public const SOURCE_MANUAL   = 'manual';

    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED  = 'failed';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
        'runtime'     => 'float',
        'exit_code'   => 'integer',
    ];

    /** API/JSON shape shared by the web history drawer and the mobile API. */
    public function toDisplayArray(): array
    {
        return [
            'id'          => $this->id,
            'job_key'     => $this->job_key,
            'source'      => $this->source,
            'status'      => $this->status,
            'started_at'  => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'runtime'     => $this->runtime,
            'exit_code'   => $this->exit_code,
            'error'       => $this->error,
        ];
    }
}
