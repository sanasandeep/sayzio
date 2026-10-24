<?php

namespace App\Console\Commands;

use App\Modules\User\Models\AttendanceParticipant;
use App\Modules\User\Models\AttendanceReview;
use App\Modules\User\Services\AttendanceOperations;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoPunchOutAttendance extends Command
{
    protected $signature = 'attendance:auto-punch-out';

    protected $description = 'Close attendance sessions at each employee configured cutoff without inventing photo or GPS evidence';

    public function handle(AttendanceOperations $operations): int
    {
        $count = 0;
        AttendanceParticipant::where('state', '!=', 'out')->whereNotNull('auto_punch_out_at')->where('auto_punch_out_at', '<=', now())->chunkById(200, function ($people) use ($operations, &$count) {
            foreach ($people as $person) {
                DB::transaction(function () use ($person, $operations, &$count) {
                    $person = AttendanceParticipant::whereKey($person->id)->lockForUpdate()->firstOrFail();
                    if ($person->state === 'out' || ! $person->auto_punch_out_at || $person->auto_punch_out_at->isFuture()) {
                        return;
                    }
                    $time = $person->auto_punch_out_at;
                    $last = $person->punches()->latest('occurred_at')->latest('id')->first();
                    if ($last && $last->occurred_at->gt($time)) {
                        $time = $last->occurred_at;
                    }
                    $punch = $operations->closeSession($person, 'auto_punch_out', $time);
                    AttendanceReview::create(['workspace_id' => $person->workspace_id, 'actor_user_id' => 0, 'actor_guard' => 'system', 'subject_type' => 'punch', 'subject_id' => $punch->id, 'decision' => 'auto_punch_out', 'note' => 'Employee scheduled automatic punch-out. No photo or location was generated.', 'created_at' => now()]);
                    $count++;
                });
            }
        });
        $this->info($count.' sessions automatically closed.');

        return self::SUCCESS;
    }
}
