<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\DeliveryProject;
use App\Modules\User\Models\DeliveryProjectComment;
use App\Modules\User\Models\DeliveryProjectTask;
use App\Modules\User\Models\Workspace;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Task #3566 — one place that turns Delivery Project events into notifications,
 * so the web controller, the API controller, the portal controller and the
 * warranty-reminder command all stay in lockstep.
 *
 * Two directions:
 *   • client/buyer → team   : a new comment notifies the workspace team
 *                             (in-app + push + email, honoring each member's
 *                             notification preferences).
 *   • team → client         : team replies and milestone events
 *                             (task completed, project completed, warranty)
 *                             email the client at their captured address.
 *
 * All delivery is best-effort: a mail/notification failure must never break the
 * request that triggered it (mirrors the swallow-and-log pattern used across
 * the Emailer pipeline).
 */
class DeliveryProjectNotifier
{
    public function __construct(private NotificationService $notifications)
    {
    }

    /**
     * A client/buyer posted a comment — notify every active member of the
     * project's workspace (in-app + push + email per their preferences).
     */
    public function clientCommented(DeliveryProject $project, DeliveryProjectComment $comment): void
    {
        $snippet = Str::limit((string) $comment->body, 140);
        $author  = $comment->displayName();
        $url     = route('user.delivery-projects.show', $project->id);
        $message = $author . ' commented on “' . $project->title . '”';

        foreach ($this->teamRecipients($project) as $user) {
            try {
                $this->notifications->notify($user, 'delivery_project.comment', [
                    'message'    => $message,
                    'project_id' => $project->id,
                    'comment_id' => $comment->id,
                    'author'     => $author,
                    'snippet'    => $snippet,
                    'url'        => $url,
                ]);

                $this->notifications->pushToUser(
                    $user,
                    'delivery_project.comment',
                    'New project comment',
                    $author . ': ' . $snippet,
                    ['project_id' => $project->id, 'url' => $url]
                );

                if ($user->email && $this->notifications->prefersChannel($user->id, 'delivery_project.comment', 'email')) {
                    Emailer::send('delivery_project.client_comment', $user->email, [
                        'author_name'   => $author,
                        'project_title' => $project->title,
                        'body'          => (string) $comment->body,
                        'project_url'   => $url,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('DeliveryProjectNotifier.clientCommented failed', [
                    'project_id' => $project->id,
                    'user_id'    => $user->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }

    /** A workspace member replied — email the client so the loop closes. */
    public function teamReplied(DeliveryProject $project, DeliveryProjectComment $comment): void
    {
        $this->emailClient($project, 'delivery_project.team_reply', [
            'author_name' => $comment->displayName(),
            'body'        => (string) $comment->body,
        ]);
    }

    /** A delivery task was just completed — email the client the progress. */
    public function taskCompleted(DeliveryProject $project, DeliveryProjectTask $task): void
    {
        $this->emailClient($project, 'delivery_project.task_completed', [
            'task_title' => $task->title,
            'progress'   => (string) $project->progressPercent(),
        ]);
    }

    /** The whole project was marked complete — email the client. */
    public function projectCompleted(DeliveryProject $project): void
    {
        $this->emailClient($project, 'delivery_project.completed', []);
    }

    /**
     * Warranty milestone — email the client.
     *
     * @param 'ending'|'expired' $state
     */
    public function warranty(DeliveryProject $project, string $state): void
    {
        $expired = $state === 'expired';

        $this->emailClient($project, 'delivery_project.warranty_reminder', [
            'headline' => $expired ? 'Warranty expired' : 'Warranty ending soon',
            'message'  => $expired
                ? 'The warranty for your project “' . $project->title . '” has now ended.'
                : 'The warranty for your project “' . $project->title . '” is ending soon.',
            'expires_at' => optional($project->warranty_expires_at)->toFormattedDateString() ?: '—',
        ]);
    }

    /**
     * Send a client-facing email, folding in the common client/project tokens.
     * No-op when the project has no captured client email.
     *
     * @param array<string,mixed> $tokens
     */
    private function emailClient(DeliveryProject $project, string $key, array $tokens): void
    {
        if (empty($project->client_email)) {
            return;
        }

        try {
            Emailer::send($key, $project->client_email, array_merge([
                'client_name'   => $project->client_name ?: 'there',
                'project_title' => $project->title,
                'project_url'   => $this->clientUrl($project),
            ], $tokens));
        } catch (\Throwable $e) {
            Log::warning('DeliveryProjectNotifier.emailClient failed', [
                'project_id' => $project->id,
                'key'        => $key,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /** Client-facing URL is the unguessable public share page (no login needed). */
    private function clientUrl(DeliveryProject $project): string
    {
        return route('delivery-project.share', $project->share_token);
    }

    /**
     * Active members of the project's workspace (owner + un-suspended members),
     * de-duplicated by user id. Resolved from the project's workspace_id
     * directly so this works outside a bound workspace context (portal, public
     * share page, scheduled command).
     *
     * @return \App\Modules\User\Models\User[]
     */
    private function teamRecipients(DeliveryProject $project): array
    {
        $ws = Workspace::query()->find($project->workspace_id);
        if (!$ws) {
            return [];
        }

        $users = collect();
        if ($ws->owner) {
            $users->push($ws->owner);
        }
        foreach ($ws->members()->whereNull('suspended_at')->with('user')->get() as $member) {
            if ($member->user) {
                $users->push($member->user);
            }
        }

        return $users->filter()->unique('id')->values()->all();
    }
}
