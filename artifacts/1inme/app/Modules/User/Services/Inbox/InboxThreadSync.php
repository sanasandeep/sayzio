<?php

namespace App\Modules\User\Services\Inbox;

use App\Modules\Common\Models\ViewerDmConversation;
use App\Modules\Common\Models\ViewerDmMessage;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\FormSubmission;
use App\Modules\User\Models\InboxMessage;
use App\Modules\User\Models\InboxThread;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Lazy unifier: pulls every existing inbox source (form submissions,
 * subscribers / form-replies, viewer DMs) into the new `inbox_threads` +
 * `inbox_messages` schema. Idempotent — safe to run on a cron and on
 * demand right before the unified inbox renders.
 */
class InboxThreadSync
{
    public function __construct(
        protected InboxClassifier $classifier,
        protected InboxAiTriage $triage,
        protected InboxAutopilot $autopilot,
    ) {}

    /** Sync everything visible for the given workspace. */
    public function syncWorkspace(Workspace $ws): int
    {
        $touched = 0;
        $touched += $this->syncFormSubmissions($ws);
        $touched += $this->syncSubscribers($ws);
        $touched += $this->syncViewerDms($ws);

        // Once every source is threaded, let the agent act on anything new
        // that just landed (drafts + autonomous sends). Best-effort: this
        // never throws and is a no-op unless autopilot is configured.
        $this->autopilot->run($ws);

        return $touched;
    }

    /** Resolve the workspace owner once for AI metering during a sync pass. */
    protected function owner(Workspace $ws): ?User
    {
        if (!array_key_exists($ws->id, $this->ownerCache)) {
            $this->ownerCache[$ws->id] = User::find($ws->owner_user_id);
        }
        return $this->ownerCache[$ws->id];
    }

    /** @var array<int,?User> */
    protected array $ownerCache = [];

    protected function syncFormSubmissions(Workspace $ws): int
    {
        $formIds = Form::query()->withoutGlobalScope('workspace')
            ->where('workspace_id', $ws->id)->pluck('id');
        if ($formIds->isEmpty()) return 0;

        $rows = FormSubmission::query()->withoutGlobalScope('workspace')
            ->whereIn('form_id', $formIds)
            // Don't thread unpaid / abandoned paid-form attempts; once a charge
            // clears the row becomes 'paid' and the next sync picks it up.
            ->completed()
            ->with('form:id,title')
            ->orderBy('id')
            ->get();

        $count = 0;
        foreach ($rows as $sub) {
            $name  = $this->extractField($sub->data, ['name', 'Name', 'full_name', 'first_name']) ?? '#' . $sub->id;
            $email = $this->extractField($sub->data, ['email', 'Email', 'e_mail', 'email_address']);
            $body  = $this->buildBody($sub->data);
            $isSponsorship = $this->looksLikeSponsorship($sub->form?->title, $body);

            $thread = InboxThread::query()->withoutGlobalScope('workspace')->firstOrNew([
                'source_type' => 'form_submission',
                'source_id'   => $sub->id,
            ]);

            if (!$thread->exists) {
                $cls = $this->triage->triage($body, $sub->form?->title, 'form', (bool) $sub->is_spam, $this->owner($ws), $ws);
                if ($isSponsorship && $cls['confidence'] < 0.8) {
                    $cls['category']   = 'sponsorship';
                    $cls['confidence'] = 0.85;
                    $cls['priority']   = $cls['priority'] === 'urgent' ? 'urgent' : 'high';
                    $cls['reason']     = 'sponsorship_form_title';
                }
                $thread->fill([
                    'workspace_id'        => $ws->id,
                    'user_id'             => $ws->owner_user_id,
                    'channel'             => $isSponsorship ? 'sponsorship' : 'form',
                    'subject'             => 'Form: ' . ($sub->form?->title ?? 'Submission'),
                    'category'            => $cls['category'],
                    'category_confidence' => $cls['confidence'],
                    'category_source'     => 'auto',
                    'priority'            => $cls['priority'],
                    'triage_source'       => $cls['source'],
                    'summary'             => $cls['summary'],
                    'sla_due_at'          => $this->defaultSla($cls['category'], $sub->created_at),
                    'meta'                => ['classifier_reason' => $cls['reason']],
                ]);
            }

            $thread->fill([
                'preview'         => Str::limit(preg_replace('/\s+/', ' ', $body), 240),
                'sender_name'     => $name,
                'sender_email'    => $email,
                'last_message_at' => $sub->created_at,
                'last_sender'     => 'in',
                'is_read'         => (bool) $sub->is_read,
                'is_starred'      => (bool) $sub->is_starred,
                'unread_count'    => $sub->is_read ? 0 : 1,
            ]);
            if ((bool) $sub->is_spam && $thread->category !== 'spam' && $thread->category_source !== 'manual') {
                $thread->category = 'spam';
            }
            $thread->save();

            // Single inbound message representing the submission body.
            InboxMessage::firstOrCreate(
                ['thread_id' => $thread->id, 'external_id' => 'form_submission:' . $sub->id],
                [
                    'direction'   => 'in',
                    'sender_name' => $name,
                    'body'        => $body,
                    'sent_at'     => $sub->created_at,
                ],
            );
            $count++;
        }
        return $count;
    }

    protected function syncSubscribers(Workspace $ws): int
    {
        $rows = Subscriber::query()->withoutGlobalScope('workspace')
            ->where('workspace_id', $ws->id)
            ->orderBy('id')
            ->get();

        $count = 0;
        foreach ($rows as $sub) {
            $body = trim((string) (is_array($sub->metadata ?? null) ? ($sub->metadata['message'] ?? '') : ''));
            if ($body === '') {
                $body = trim(implode(' · ', array_filter([$sub->email, $sub->phone, $sub->channel_url])));
            }

            $thread = InboxThread::query()->withoutGlobalScope('workspace')->firstOrNew([
                'source_type' => 'subscriber',
                'source_id'   => $sub->id,
            ]);

            if (!$thread->exists) {
                $cls = $this->triage->triage($body, null, 'email', (bool) $sub->is_spam, $this->owner($ws), $ws);
                $thread->fill([
                    'workspace_id'        => $ws->id,
                    'user_id'             => $ws->owner_user_id,
                    'channel'             => 'email',
                    'subject'             => $sub->type === 'contact_form' ? 'Contact form reply' : 'Subscriber',
                    'category'            => $cls['category'],
                    'category_confidence' => $cls['confidence'],
                    'category_source'     => 'auto',
                    'priority'            => $cls['priority'],
                    'triage_source'       => $cls['source'],
                    'summary'             => $cls['summary'],
                    'sla_due_at'          => $this->defaultSla($cls['category'], $sub->subscribed_at ?? $sub->created_at),
                    'meta'                => ['subscriber_type' => $sub->type, 'classifier_reason' => $cls['reason']],
                ]);
            }

            $thread->fill([
                'preview'         => Str::limit($body, 240),
                'sender_name'     => $sub->name ?: ($sub->email ?: ($sub->phone ?: '#' . $sub->id)),
                'sender_email'    => $sub->email,
                'last_message_at' => $sub->subscribed_at ?? $sub->created_at,
                'last_sender'     => 'in',
                'is_read'         => (bool) $sub->is_read,
                'is_starred'      => (bool) $sub->is_starred,
                'unread_count'    => $sub->is_read ? 0 : 1,
            ]);
            if ((bool) $sub->is_spam && $thread->category_source !== 'manual') {
                $thread->category = 'spam';
            }
            $thread->save();

            InboxMessage::firstOrCreate(
                ['thread_id' => $thread->id, 'external_id' => 'subscriber:' . $sub->id],
                [
                    'direction'   => 'in',
                    'sender_name' => $thread->sender_name,
                    'body'        => $body !== '' ? $body : '(no message)',
                    'sent_at'     => $sub->subscribed_at ?? $sub->created_at,
                ],
            );
            $count++;
        }
        return $count;
    }

    protected function syncViewerDms(Workspace $ws): int
    {
        $convos = ViewerDmConversation::query()
            ->where('owner_user_id', $ws->owner_user_id)
            ->with(['viewer:id,name,email,profile_picture', 'link:id,alias'])
            ->get();

        $count = 0;
        foreach ($convos as $c) {
            $thread = InboxThread::query()->withoutGlobalScope('workspace')->firstOrNew([
                'source_type' => 'viewer_dm',
                'source_id'   => $c->id,
            ]);

            if (!$thread->exists) {
                $cls = $this->triage->triage((string) $c->last_message_preview, null, 'biolink_dm', $c->status === 'blocked', $this->owner($ws), $ws);
                $blocked = $c->status === 'blocked';
                $thread->fill([
                    'workspace_id'        => $ws->id,
                    'user_id'             => $ws->owner_user_id,
                    'channel'             => 'biolink_dm',
                    'subject'             => 'DM via /' . ($c->link?->alias ?? 'biolink'),
                    'category'            => $blocked ? 'spam' : $cls['category'],
                    'category_confidence' => $cls['confidence'],
                    'category_source'     => 'auto',
                    'priority'            => $blocked ? 'low' : $cls['priority'],
                    'triage_source'       => $cls['source'],
                    'summary'             => $cls['summary'],
                    'sla_due_at'          => $this->defaultSla($cls['category'], $c->last_message_at),
                    'meta'                => ['classifier_reason' => $cls['reason']],
                ]);
            }

            $thread->fill([
                'preview'         => Str::limit((string) $c->last_message_preview, 240),
                'sender_name'     => $c->viewer?->name ?: 'Viewer',
                'sender_email'    => $c->viewer?->email,
                'sender_avatar'   => $c->viewer?->profile_picture,
                'last_message_at' => $c->last_message_at,
                'last_sender'     => $c->last_sender === 'owner' ? 'out' : 'in',
                'is_read'         => $c->owner_unread_count === 0,
                'unread_count'    => (int) $c->owner_unread_count,
                'status'          => $c->status === 'blocked' ? 'archived' : 'open',
            ]);
            $thread->save();

            // Sync the last 50 messages for the thread reader.
            $msgs = ViewerDmMessage::where('conversation_id', $c->id)
                ->orderBy('created_at')->limit(200)->get();
            foreach ($msgs as $m) {
                InboxMessage::firstOrCreate(
                    ['thread_id' => $thread->id, 'external_id' => 'viewer_dm:' . $m->id],
                    [
                        'direction'   => $m->sender_type === 'owner' ? 'out' : 'in',
                        'sender_name' => $m->sender_type === 'owner' ? 'You' : ($c->viewer?->name ?: 'Viewer'),
                        'body'        => (string) $m->body,
                        'sent_at'     => $m->created_at,
                    ],
                );
            }
            $count++;
        }
        return $count;
    }

    protected function extractField(?array $data, array $keys): ?string
    {
        if (!is_array($data)) return null;
        foreach ($keys as $k) {
            if (!empty($data[$k]) && is_string($data[$k])) return (string) $data[$k];
        }
        return null;
    }

    protected function buildBody(?array $data): string
    {
        if (!is_array($data)) return '';
        $message = $this->extractField($data, ['message', 'Message', 'note', 'comments', 'body']);
        if ($message) return $message;
        return collect($data)->reject(fn($v) => is_array($v))
            ->take(8)->map(fn($v, $k) => "$k: $v")->implode("\n");
    }

    protected function looksLikeSponsorship(?string $formTitle, string $body): bool
    {
        $blob = mb_strtolower(($formTitle ?? '') . ' ' . $body);
        foreach (['sponsor', 'partnership', 'collab', 'brand deal', 'media kit', 'rate card'] as $kw) {
            if (str_contains($blob, $kw)) return true;
        }
        return false;
    }

    protected function defaultSla(string $category, ?Carbon $from): ?Carbon
    {
        $hours = InboxThread::DEFAULT_SLA_HOURS[$category] ?? null;
        if (!$hours) return null;
        return ($from ?: now())->copy()->addHours($hours);
    }
}
