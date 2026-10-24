<?php

namespace App\Console\Commands;

use App\Modules\User\Models\AttendanceChallenge;
use App\Modules\User\Models\AttendancePunch;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\AttendanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneAttendanceEvidence extends Command
{
    protected $signature = 'attendance:prune-evidence';

    protected $description = 'Remove expired attendance photos and coordinates under each team retention policy';

    public function handle(AttendanceService $service): int
    {
        $count = 0;
        AttendancePunch::with('participant')->where('occurred_at', '<', now()->subDays(7))->where(function ($q) {
            $q->whereNotNull('photo_path')->orWhereNotNull('latitude');
        })->chunkById(200, function ($rows) use ($service, &$count) {
            foreach ($rows as $row) {
                $ws = new Workspace;
                $ws->id = $row->participant->workspace_id;
                $days = $service->policy($ws)['retention_days'];
                if ($row->occurred_at->gt(now()->subDays($days))) {
                    continue;
                }
                if ($row->photo_path && ! Storage::disk('attendance_evidence')->delete($row->photo_path)) {
                    throw new \RuntimeException('Evidence deletion failed for punch '.$row->id);
                }
                $row->update(['photo_path' => null, 'latitude' => null, 'longitude' => null, 'accuracy' => null]);
                $count++;
            }
        });
        AttendanceChallenge::where('expires_at', '<', now()->subDays(7))->delete();
        $this->info($count.' attendance evidence records expired.');

        return self::SUCCESS;
    }
}
