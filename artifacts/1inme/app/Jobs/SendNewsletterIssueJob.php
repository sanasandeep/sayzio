<?php

namespace App\Jobs;

use App\Modules\Common\Models\NewsletterIssue;
use App\Modules\Common\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Deliver a queued newsletter issue to every active (non-unsubscribed)
 * subscriber. Runs in the background so the admin's "Send" request returns
 * immediately even with thousands of subscribers.
 */
class SendNewsletterIssueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 minutes ceiling per issue
    public int $tries = 1;

    public function __construct(public int $issueId) {}

    public function handle(): void
    {
        // Atomically claim the issue so a duplicate dispatch (or a retried
        // worker) can't double-send. Only a row currently in 'queued' is
        // flipped to 'sending'; anything already sending/sent/failed is left
        // alone and the job exits.
        $claimed = DB::table('newsletter_issues')
            ->where('id', $this->issueId)
            ->where('status', 'queued')
            ->update([
                'status'     => 'sending',
                'sent_at'    => now(),
                'updated_at' => now(),
            ]);
        if ($claimed === 0) return;

        /** @var NewsletterIssue|null $issue */
        $issue = NewsletterIssue::find($this->issueId);
        if (!$issue) return;

        $fromAddress = config('mail.from.address', 'noreply@1inme.com');
        $fromName    = config('mail.from.name', config('app.name'));

        $sent = 0;
        $failed = 0;

        try {
            NewsletterSubscriber::query()
                ->whereNull('unsubscribed_at')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($issue, $fromAddress, $fromName, &$sent, &$failed) {
                    foreach ($rows as $sub) {
                        if (empty($sub->email)) {
                            $failed++;
                            continue;
                        }
                        try {
                            Mail::html($issue->body_html, function ($m) use ($sub, $issue, $fromAddress, $fromName) {
                                $m->to($sub->email)
                                  ->subject($issue->subject)
                                  ->from($fromAddress, $fromName);
                            });
                            $sent++;
                        } catch (\Throwable $e) {
                            $failed++;
                            Log::warning('Newsletter send failed', [
                                'issue_id' => $issue->id,
                                'email'    => $sub->email,
                                'error'    => $e->getMessage(),
                            ]);
                        }
                    }
                    // Persist progress so admins can watch the counts move.
                    $issue->forceFill([
                        'sent_count'   => $sent,
                        'failed_count' => $failed,
                    ])->save();
                });

            $issue->forceFill([
                'status'       => 'sent',
                'sent_count'   => $sent,
                'failed_count' => $failed,
                'finished_at'  => now(),
            ])->save();
        } catch (\Throwable $e) {
            $issue->forceFill([
                'status'       => 'failed',
                'sent_count'   => $sent,
                'failed_count' => $failed,
                'finished_at'  => now(),
            ])->save();
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $issue = NewsletterIssue::find($this->issueId);
        if ($issue && !in_array($issue->status, ['sent', 'failed'], true)) {
            $issue->forceFill([
                'status'      => 'failed',
                'finished_at' => now(),
            ])->save();
        }
    }
}
