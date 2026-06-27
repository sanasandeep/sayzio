<?php

namespace App\Modules\User\Services\Inbox;

use App\Modules\User\Models\InboxThread;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiPlanAccess;
use Illuminate\Support\Facades\Log;

/**
 * The autonomous half of the Inbox Agent. Runs off the existing inbox sync
 * (no visitor present): for newly-arrived inbound threads in the eligible
 * categories it drafts a reply and, when confident enough and safe, sends
 * it autonomously ("Sent by AI"). Borderline or sensitive threads are
 * staged with a draft and parked in the manual review queue instead.
 *
 * Guardrails (a thread is only auto-SENT when ALL hold):
 *  - workspace autopilot enabled + category in the allow-list
 *  - AI Engine on + owner's plan unlocks the Inbox Agent
 *  - thread is open, inbound, never replied to, not already agent-handled
 *  - category is not spam and the body is not "sensitive"
 *  - triage confidence >= the workspace threshold
 *  - a reply channel exists (email address or biolink DM)
 */
class InboxAutopilot
{
    public const FEATURE = 'inbox_agent';

    /** Hard cap on threads processed per sync pass to bound cost/latency. */
    protected const BATCH = 8;

    /**
     * Topics we never auto-send on, regardless of category/confidence —
     * they get a draft + manual review instead.
     */
    protected const SENSITIVE_KEYWORDS = [
        'refund', 'chargeback', 'charge back', 'dispute', 'fraud', 'scam',
        'legal', 'lawyer', 'lawsuit', 'sue ', 'court', 'gdpr', 'subpoena',
        'complaint', 'harass', 'threat', 'abuse', 'suicide', 'self-harm',
        'self harm', 'emergency', 'medical', 'death', 'died', 'press',
        'journalist', 'defamation', 'invoice dispute', 'payment failed',
    ];

    public function __construct(
        protected InboxAiReplyDrafter $drafter,
        protected InboxReplyDispatcher $dispatcher,
    ) {}

    /**
     * Evaluate + act on eligible threads for a workspace. Returns the number
     * of threads the agent acted on (sent or staged). Never throws.
     */
    public function run(Workspace $ws): int
    {
        try {
            $cfg = InboxAgentSettings::for($ws);
            if (!($cfg['autopilot_enabled'] ?? false)) {
                return 0;
            }

            $categories = array_values(array_intersect(
                (array) ($cfg['autopilot_categories'] ?? []),
                array_diff(InboxThread::CATEGORIES, InboxAgentSettings::AUTOPILOT_FORBIDDEN_CATEGORIES),
            ));
            if (empty($categories)) {
                return 0;
            }

            if (!AiEngineSettings::isEnabled()) {
                return 0;
            }

            $owner = User::find($ws->owner_user_id);
            if (!$owner || !AiPlanAccess::featureAllowed($owner, self::FEATURE)) {
                return 0;
            }

            $threshold = (float) ($cfg['confidence_threshold'] ?? 0.8);

            // Newly-arrived inbound threads the agent hasn't looked at yet.
            $threads = InboxThread::query()->withoutGlobalScope('workspace')
                ->where('workspace_id', $ws->id)
                ->where('status', 'open')
                ->where('last_sender', 'in')
                ->whereNull('autopilot_state')
                ->whereIn('category', $categories)
                ->orderByDesc('last_message_at')
                ->limit(self::BATCH)
                ->get();

            $acted = 0;
            foreach ($threads as $thread) {
                if ($this->process($thread, $owner, $ws, $threshold)) {
                    $acted++;
                }
            }
            return $acted;
        } catch (\Throwable $e) {
            Log::warning('Inbox autopilot run failed: ' . $e->getMessage());
            return 0;
        }
    }

    protected function process(InboxThread $thread, User $owner, Workspace $ws, float $threshold): bool
    {
        // Defensive: never act twice or on a thread the owner already answered.
        if ($thread->sent_by_ai || $thread->autopilot_state !== null) {
            return false;
        }
        if ($thread->messages()->where('direction', 'out')->exists()) {
            $thread->update(['autopilot_state' => InboxThread::AUTOPILOT_SKIPPED]);
            return false;
        }
        if (!$this->hasReplyChannel($thread)) {
            $thread->update(['autopilot_state' => InboxThread::AUTOPILOT_SKIPPED]);
            return false;
        }

        // Draft first; if drafting fails (no coins / engine off) leave the
        // thread untouched so a later pass can retry.
        try {
            $result = $this->drafter->draft($thread, $owner, $ws);
        } catch (\Throwable $e) {
            Log::info('Autopilot draft skipped for thread ' . $thread->id . ': ' . $e->getMessage());
            return false;
        }

        $draft = trim($result['draft'] ?? '');
        if ($draft === '') {
            return false;
        }

        $confidence = (float) ($thread->category_confidence ?? 0);
        $sensitive  = $thread->category === 'spam' || $this->isSensitive($thread);
        $confident  = $confidence >= $threshold;

        if ($confident && !$sensitive) {
            $send = $this->dispatcher->sendReply($thread, $draft, $owner, [
                'sent_by_ai'  => true,
                'sender_name' => 'Inbox Agent',
            ]);
            if ($send['error']) {
                // Couldn't actually send — stage it for review instead.
                $this->stageReview($thread, $draft, 'send_failed:' . $send['error']);
                return true;
            }
            return true;
        }

        // Not confident enough, or sensitive — stage for a human.
        $reason = $sensitive ? 'sensitive' : 'low_confidence';
        $this->stageReview($thread, $draft, $reason);
        return true;
    }

    protected function stageReview(InboxThread $thread, string $draft, string $reason): void
    {
        $meta = (array) ($thread->meta ?? []);
        $meta['autopilot_reason'] = $reason;

        $thread->update([
            'ai_draft'        => $draft,
            'ai_draft_at'     => now(),
            'autopilot_state' => InboxThread::AUTOPILOT_REVIEW,
            'ai_handled_at'   => now(),
            'meta'            => $meta,
        ]);
    }

    protected function hasReplyChannel(InboxThread $thread): bool
    {
        if ($thread->source_type === 'viewer_dm') {
            return true;
        }
        return $thread->sender_email
            && filter_var($thread->sender_email, FILTER_VALIDATE_EMAIL);
    }

    protected function isSensitive(InboxThread $thread): bool
    {
        $blob = mb_strtolower(trim(
            ($thread->subject ?? '') . ' ' .
            ($thread->summary ?? '') . ' ' .
            ($thread->preview ?? '')
        ));
        if ($blob === '') {
            return false;
        }
        foreach (self::SENSITIVE_KEYWORDS as $kw) {
            if (str_contains($blob, $kw)) {
                return true;
            }
        }
        return false;
    }
}
