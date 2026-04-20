<?php

namespace App\Console\Commands;

use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Once-a-day job that emails each follower a single digest covering every
 * creator they follow that has had pending activity since their last
 * digest. Followers in 'instant' mode are skipped (their emails go out
 * synchronously from CreatorPostController). Followers in 'off' mode never
 * have rows queued, so they're naturally excluded.
 *
 * Idempotent within a day: digest emails are gated on
 * `follower_digest_last_sent_at`, so re-running the command on the same UTC
 * day does nothing for users that have already received their digest.
 */
class SendFollowerDigest extends Command
{
    protected $signature = 'followers:send-digest
        {--user= : Optional follower user id to digest (default: all eligible)}
        {--force : Send even if a digest was already sent today}';

    protected $description = 'Email each opted-in follower one digest of new creator activity.';

    public function handle(): int
    {
        $today = now()->startOfDay();

        $query = User::query()->where('follower_updates_mode', 'digest');
        if ($this->option('user')) {
            $query->where('id', $this->option('user'));
        }

        $sent = 0;
        $skipped = 0;
        $force = (bool) $this->option('force');

        $query->chunkById(200, function ($users) use (&$sent, &$skipped, $today, $force) {
            foreach ($users as $user) {
                if (!$force && $user->follower_digest_last_sent_at
                    && $user->follower_digest_last_sent_at->greaterThanOrEqualTo($today)) {
                    $skipped++;
                    continue;
                }

                $pending = UserNotification::where('user_id', $user->id)
                    ->where('type', 'follower_update')
                    ->whereNull('emailed_at')
                    ->orderBy('created_at')
                    ->get();

                if ($pending->isEmpty()) {
                    $skipped++;
                    continue;
                }

                if ($this->emailDigest($user, $pending)) {
                    $now = now();
                    UserNotification::whereIn('id', $pending->pluck('id'))
                        ->update(['emailed_at' => $now]);
                    $user->forceFill(['follower_digest_last_sent_at' => $now])->save();
                    $sent++;
                } else {
                    $skipped++;
                }
            }
        });

        $this->info("Digest run complete. Sent: {$sent}, skipped: {$skipped}.");
        return self::SUCCESS;
    }

    /**
     * Group pending notifications by creator and email a single summary.
     * Returns true on success so the caller can mark the rows emailed.
     */
    private function emailDigest(User $user, $pending): bool
    {
        $byCreator = [];
        foreach ($pending as $row) {
            $data = $row->data ?? [];
            $cid = (int) ($data['creator_id'] ?? 0);
            if (!isset($byCreator[$cid])) {
                $byCreator[$cid] = [
                    'name'     => $data['creator_name'] ?? 'A creator you follow',
                    'messages' => [],
                ];
            }
            $msg = trim((string) ($data['message'] ?? ''));
            if ($msg !== '') $byCreator[$cid]['messages'][] = $msg;
        }

        $lines = [];
        $lines[] = "Hi " . ($user->name ?: 'there') . ",";
        $lines[] = "";
        $lines[] = "Here's what creators you follow have been up to since your last digest:";
        $lines[] = "";
        foreach ($byCreator as $entry) {
            $lines[] = "• {$entry['name']}";
            foreach (array_slice($entry['messages'], 0, 5) as $m) {
                $lines[] = "    - {$m}";
            }
            $extra = count($entry['messages']) - 5;
            if ($extra > 0) {
                $lines[] = "    - …and {$extra} more update" . ($extra === 1 ? '' : 's');
            }
        }
        $lines[] = "";
        $lines[] = "You're receiving the daily digest. To switch to instant emails or turn this off, visit your profile notification settings.";

        $body = implode("\n", $lines);
        $count = $pending->count();
        $creatorCount = count($byCreator);
        $subject = "Your daily digest: {$count} update" . ($count === 1 ? '' : 's')
            . " from {$creatorCount} creator" . ($creatorCount === 1 ? '' : 's');

        try {
            Mail::raw($body, function ($m) use ($user, $subject) {
                $m->to($user->email)->subject($subject);
            });
            return true;
        } catch (\Throwable $e) {
            \Log::warning('follower digest send failed for user ' . $user->id . ': ' . $e->getMessage());
            return false;
        }
    }
}
