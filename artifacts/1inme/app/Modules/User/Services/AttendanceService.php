<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\AttendanceChallenge;
use App\Modules\User\Models\AttendanceParticipant;
use App\Modules\User\Models\AttendancePunch;
use App\Modules\User\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public const ACTIONS = ['in', 'break', 'resume', 'out'];

    public const POSES = ['Show two fingers beside your face', 'Show an open palm beside your face', 'Touch your chin with one finger', 'Raise one hand beside your face'];

    public function policy(Workspace $workspace): array
    {
        $stored = DB::table('attendance_policies')->where('workspace_id', $workspace->id)->value('settings');

        return array_replace(['timezone' => 'UTC', 'require_photo' => (bool) config('attendance.evidence_bucket'), 'require_location' => false,
            'latitude' => null, 'longitude' => null, 'radius_m' => null, 'retention_days' => 90],
            $stored ? json_decode($stored, true) : []);
    }

    public function participant(Workspace $workspace, int $userId): AttendanceParticipant
    {
        return AttendanceParticipant::firstOrCreate(['workspace_id' => $workspace->id, 'user_id' => $userId]);
    }

    public function nextState(string $state, string $action): string
    {
        $next = ['out' => ['in' => 'working'], 'working' => ['break' => 'break', 'out' => 'out'], 'break' => ['resume' => 'working', 'out' => 'out']];
        if (! isset($next[$state][$action])) {
            throw ValidationException::withMessages(['action' => 'Attendance changed or this action is unavailable. Refresh and try again.']);
        }

        return $next[$state][$action];
    }

    public function challenge(AttendanceParticipant $participant, string $action, ?string $ip = null, ?string $qr = null): AttendanceChallenge
    {
        return DB::transaction(function () use ($participant, $action, $ip, $qr) {
            $participant = AttendanceParticipant::whereKey($participant->id)->lockForUpdate()->firstOrFail();
            app(AttendanceOperations::class)->checkPunchAccess($participant, $ip);
            if ($qr) {
                abort_unless($action === 'in', 422, 'QR sessions are for clock-in only.');
                $this->qrSession($qr, $participant);
            }
            $this->nextState($participant->state, $action);
            AttendanceChallenge::where('participant_id', $participant->id)->whereNull('consumed_at')->delete();

            return AttendanceChallenge::create(['id' => (string) Str::uuid(), 'participant_id' => $participant->id,
                'qr_session_id' => $qr, 'action' => $action, 'pose' => self::POSES[random_int(0, count(self::POSES) - 1)], 'expires_at' => now()->addMinutes(2)]);
        });
    }

    public function punch(AttendanceParticipant $participant, Workspace $workspace, array $data, $photo): AttendancePunch
    {
        $path = null;
        try {
            return DB::transaction(function () use ($participant, $workspace, $data, $photo, &$path) {
                $participant = AttendanceParticipant::whereKey($participant->id)->lockForUpdate()->firstOrFail();
                $challenge = AttendanceChallenge::whereKey($data['challenge_id'])->where('participant_id', $participant->id)->lockForUpdate()->firstOrFail();
                app(AttendanceOperations::class)->checkPunchAccess($participant, $data['ip_address'] ?? null);
                // A retried upload returns its original receipt, never a second punch.
                if ($challenge->consumed_at) {
                    return AttendancePunch::where('challenge_id', $challenge->id)->firstOrFail();
                }
                if ($challenge->expires_at->isPast()) {
                    throw ValidationException::withMessages(['challenge_id' => 'Challenge expired. Start a new capture.']);
                }
                if ($challenge->qr_session_id) {
                    $this->qrSession($challenge->qr_session_id, $participant);
                }
                abort_if($participant->auto_punch_out_at && $participant->auto_punch_out_at->isPast(), 409, 'Automatic punch-out is due. Refresh shortly before recording another action.');
                $next = $this->nextState($participant->state, $challenge->action);
                $policy = $this->policy($workspace);
                $needsPhoto = $policy['require_photo'] && in_array($challenge->action, ['in', 'out']);
                if ($needsPhoto && ! $photo) {
                    throw ValidationException::withMessages(['photo' => 'Capture a selfie showing the requested pose.']);
                }
                if ($policy['require_location'] && ! isset($data['latitude'], $data['longitude'], $data['accuracy'])) {
                    throw ValidationException::withMessages(['latitude' => 'A fresh location reading is required.']);
                }
                if ($policy['radius_m']) {
                    if (! isset($data['latitude'], $data['longitude'], $data['accuracy']) || $data['accuracy'] > min(100, $policy['radius_m'])
                        || $this->distance($policy['latitude'], $policy['longitude'], $data['latitude'], $data['longitude']) + $data['accuracy'] > $policy['radius_m']) {
                        throw ValidationException::withMessages(['latitude' => 'Location is outside the permitted area or not accurate enough.']);
                    }
                }
                $capturedAt = isset($data['captured_at']) ? CarbonImmutable::parse($data['captured_at'])->utc() : null;
                if ($photo && (! $capturedAt || $capturedAt->lt($challenge->created_at) || $capturedAt->gt(now()->addSeconds(10)))) {
                    throw ValidationException::withMessages(['captured_at' => 'Capture time is outside this challenge. Please retry.']);
                }
                if ($photo) {
                    abort_unless(config('attendance.evidence_bucket'), 503, 'S3 evidence storage is not configured. Contact the workspace owner.');
                    $path = 'attendance-encrypted/'.$workspace->id.'/'.Str::uuid().'.enc';
                    $saved = Storage::disk('attendance_evidence')->put($path, Crypt::encryptString($photo->getContent()), ['ContentType' => 'application/octet-stream']);
                    if (! $saved) {
                        throw new \RuntimeException('Evidence upload failed. Attendance was not recorded.');
                    }
                }
                $punch = AttendancePunch::create(['participant_id' => $participant->id, 'challenge_id' => $challenge->id,
                    'action' => $challenge->action, 'occurred_at' => now(), 'photo_path' => $path, 'pose' => $photo ? $challenge->pose : null,
                    'captured_at' => $capturedAt, 'latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null,
                    'ip_address' => $data['ip_address'] ?? null, 'source' => $challenge->qr_session_id ? 'qr' : 'web',
                    'accuracy' => $data['accuracy'] ?? null, 'review_status' => $photo ? 'pending' : 'not_required']);
                $challenge->update(['consumed_at' => now()]);
                $participant->update(['state' => $next, 'auto_punch_out_at' => $challenge->action === 'in' ? app(AttendanceOperations::class)->autoOutDeadline($participant, $workspace) : ($next === 'out' ? null : $participant->auto_punch_out_at)]);
                if ($challenge->qr_session_id) {
                    DB::table('attendance_qr_redemptions')->insert(['qr_session_id' => $challenge->qr_session_id, 'participant_id' => $participant->id, 'punch_id' => $punch->id, 'created_at' => now(), 'updated_at' => now()]);
                }

                return $punch;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('attendance_evidence')->delete($path);
            }
            throw $e;
        }
    }

    public function qrSession(string $token, AttendanceParticipant $person)
    {
        $qr = DB::table('attendance_qr_sessions')->where('id', $token)->where('workspace_id', $person->workspace_id)->first();
        abort_unless($qr, 404);
        abort_if(CarbonImmutable::parse($qr->expires_at)->isPast(), 422, 'Workplace QR expired. Scan the current code.');
        abort_if(DB::table('attendance_qr_redemptions')->where('qr_session_id', $token)->where('participant_id', $person->id)->exists(), 409, 'You already used this workplace QR.');

        return $qr;
    }

    public function distance(float $a, float $b, float $c, float $d): float
    {
        $v = sin(deg2rad($c - $a) / 2) ** 2 + cos(deg2rad($a)) * cos(deg2rad($c)) * sin(deg2rad($d - $b) / 2) ** 2;

        return 6371000 * 2 * asin(sqrt(min(1, $v)));
    }

    /** Compute worked seconds from UTC intervals; overnight sessions and breaks cross dates safely. */
    public function workedSeconds(iterable $punches, $from, $to): int
    {
        $start = null;
        $seconds = 0;
        foreach ($punches as $punch) {
            $time = $punch->occurred_at->getTimestamp();
            if ($start !== null) {
                $seconds += max(0, min($time, $to->getTimestamp()) - max($start, $from->getTimestamp()));
            }
            $start = in_array($punch->action, ['in', 'resume']) ? $time : null;
        }
        if ($start !== null) {
            $seconds += max(0, min(now()->getTimestamp(), $to->getTimestamp()) - max($start, $from->getTimestamp()));
        }

        return $seconds;
    }
}
