<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\ClientPortal;
use App\Modules\User\Models\ClientPortalAction;
use App\Modules\User\Models\ClientPortalLink;
use App\Modules\User\Models\ClientPortalShare;
use App\Modules\User\Models\CloudFile;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\FeedEvent;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkPerformanceSnapshot;
use App\Modules\User\Models\TaskBoard;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\TaskColumn;
use App\Modules\User\Models\UserNotification;
use App\Services\Billing\GatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PortalController extends Controller
{
    /* ─── Helpers ───────────────────────────────────────────── */

    protected function portal(): ClientPortal
    {
        return app('current_portal');
    }

    protected function link(): ClientPortalLink
    {
        return app('current_portal_link');
    }

    protected function shareOrFail(string $type, ?int $id = null): ClientPortalShare
    {
        $q = $this->portal()->shares()->where('shareable_type', $type);
        if ($id !== null) $q->where('shareable_id', $id);
        $share = $q->first();
        abort_unless($share, 404);
        return $share;
    }

    protected function sharesByType(string $type)
    {
        return $this->portal()->shares()->where('shareable_type', $type)->orderBy('position')->get();
    }

    /* ─── Sections ──────────────────────────────────────────── */

    public function dashboard()
    {
        $portal = $this->portal();
        ClientPortalAction::record($portal, $this->link(), 'viewed', 'dashboard');

        $sections = $portal->shares()->orderBy('position')->get()
            ->groupBy('shareable_type');

        return view('portal.dashboard', compact('portal', 'sections'));
    }

    public function board(int $boardId)
    {
        $share = $this->shareOrFail(ClientPortalShare::TYPE_BOARD, $boardId);
        $board = TaskBoard::query()->withoutGlobalScope('workspace')
            ->where('workspace_id', $this->portal()->workspace_id)
            ->where('id', $boardId)
            ->firstOrFail();

        $columns = TaskColumn::query()->withoutGlobalScope('workspace')
            ->where('board_id', $board->id)
            ->orderBy('position')->get();
        $cards = TaskCard::query()->withoutGlobalScope('workspace')
            ->where('board_id', $board->id)
            ->whereNull('archived_at')
            ->orderBy('position')->get()
            ->groupBy('column_id');

        ClientPortalAction::record($this->portal(), $this->link(), 'viewed_section', 'task_board', $board->id);

        return view('portal.board', compact('board', 'columns', 'cards', 'share'));
    }

    public function deliveryProject(int $projectId)
    {
        $this->shareOrFail(ClientPortalShare::TYPE_DELIVERY_PROJECT, $projectId);

        $project = \App\Modules\User\Models\DeliveryProject::query()->withoutGlobalScope('workspace')
            ->where('workspace_id', $this->portal()->workspace_id)
            ->where('id', $projectId)
            ->with(['tasks.assignee:id,name'])
            ->firstOrFail();

        ClientPortalAction::record($this->portal(), $this->link(), 'viewed_section', 'delivery_project', $project->id);

        $project->load(['comments.author:id,name']);

        return view('portal.delivery-project', compact('project'));
    }

    /**
     * Task #3566 — the logged-in portal client posts a comment/question on a
     * delivery project shared with them; the workspace team is notified.
     */
    public function deliveryProjectComment(Request $request, int $projectId, \App\Modules\Common\Services\DeliveryProjectNotifier $notifier)
    {
        $this->shareOrFail(ClientPortalShare::TYPE_DELIVERY_PROJECT, $projectId);

        $project = \App\Modules\User\Models\DeliveryProject::query()->withoutGlobalScope('workspace')
            ->where('workspace_id', $this->portal()->workspace_id)
            ->where('id', $projectId)
            ->firstOrFail();

        $data = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $comment = $project->comments()->create([
            'workspace_id'   => $project->workspace_id,
            'author_role'    => \App\Modules\User\Models\DeliveryProjectComment::ROLE_CLIENT,
            'author_user_id' => null,
            'author_name'    => $this->portal()->name ?: ($project->client_name ?: null),
            'author_email'   => $this->link()->email ?: $project->client_email,
            'body'           => $data['body'],
        ]);

        ClientPortalAction::record($this->portal(), $this->link(), 'commented', 'delivery_project', $project->id);

        $notifier->clientCommented($project, $comment);

        return back()->with('success', 'Your comment was sent to the team.');
    }

    public function files()
    {
        $portal = $this->portal();
        $shares = $this->sharesByType(ClientPortalShare::TYPE_CLOUD_FOLDER);

        $folders = [];
        foreach ($shares as $share) {
            $provider = $share->settings['provider'] ?? null;
            $path     = $share->settings['folder_path'] ?? null;
            if (!$provider || !$path) continue;

            $files = CloudFile::query()->withoutGlobalScope('workspace')
                ->where('workspace_id', $portal->workspace_id)
                ->where('provider', $provider)
                ->where('parent_folder_path', $path)
                ->orderBy('name')
                ->get();
            $folders[] = ['share' => $share, 'provider' => $provider, 'path' => $path, 'files' => $files];
        }

        ClientPortalAction::record($portal, $this->link(), 'viewed_section', 'files');

        return view('portal.files', compact('portal', 'folders'));
    }

    public function fileDownload(int $cloudFileId)
    {
        $portal = $this->portal();
        $file = CloudFile::query()->withoutGlobalScope('workspace')
            ->where('workspace_id', $portal->workspace_id)
            ->where('id', $cloudFileId)
            ->firstOrFail();

        // Authorise: must belong to one of the portal's shared folders.
        $allowed = $this->sharesByType(ClientPortalShare::TYPE_CLOUD_FOLDER)
            ->contains(function ($s) use ($file) {
                return ($s->settings['provider'] ?? null) === $file->provider
                    && ($s->settings['folder_path'] ?? null) === $file->parent_folder_path;
            });
        abort_unless($allowed, 403);

        ClientPortalAction::record($portal, $this->link(), 'downloaded', 'cloud_file', $file->id, ['name' => $file->name]);

        // Cloud library entries are external links — redirect.
        if ($file->link) {
            return redirect()->away($file->link);
        }
        abort(404, 'File has no downloadable link.');
    }

    public function drafts()
    {
        $portal = $this->portal();
        $shares = $this->sharesByType(ClientPortalShare::TYPE_DRAFT_POST);

        $drafts = $shares->map(function ($share) use ($portal) {
            $post = CreatorPost::query()->withoutGlobalScope('workspace')
                ->where('workspace_id', $portal->workspace_id)
                ->where('id', $share->shareable_id)
                ->first();
            return ['share' => $share, 'post' => $post];
        })->filter(fn ($r) => $r['post']);

        ClientPortalAction::record($portal, $this->link(), 'viewed_section', 'drafts');

        return view('portal.drafts', compact('portal', 'drafts'));
    }

    public function decideDraft(Request $request, int $shareId)
    {
        $data = $request->validate([
            'decision' => 'required|in:approved,rejected,comment',
            'comment'  => 'nullable|string|max:2000',
        ]);

        $portal = $this->portal();
        $share = $portal->shares()->where('id', $shareId)
            ->where('shareable_type', ClientPortalShare::TYPE_DRAFT_POST)
            ->firstOrFail();

        $post = CreatorPost::query()->withoutGlobalScope('workspace')
            ->where('workspace_id', $portal->workspace_id)
            ->where('id', $share->shareable_id)
            ->firstOrFail();

        $email = $this->link()->email;
        $action = $data['decision'];

        if ($action === 'comment') {
            $share->forceFill([
                'approval_comment' => $data['comment'] ?? null,
            ])->save();
        } else {
            $share->forceFill([
                'approval_status'        => $action,
                'approval_decided_at'    => now(),
                'approval_decided_email' => $email,
                'approval_comment'       => $data['comment'] ?? $share->approval_comment,
            ])->save();

            // Mirror the client decision onto the originating draft so the
            // workspace-side post views (drafts list, scheduler, editor) can
            // surface it without joining through the portal share.
            $post->forceFill([
                'client_approval_status' => $action,
                'client_approval_at'     => now(),
                'client_approval_email'  => $email,
            ])->save();
        }

        ClientPortalAction::record($portal, $this->link(), $action, 'creator_post', $post->id, [
            'comment' => $data['comment'] ?? null,
        ]);

        // Notify the workspace owner + post into the activity feed.
        try {
            UserNotification::create([
                'user_id'    => $portal->workspace->owner_user_id,
                'type'       => 'portal_draft_' . $action,
                'data'       => [
                    'portal_id'   => $portal->id,
                    'portal_name' => $portal->name,
                    'post_id'     => $post->id,
                    'post_title'  => $post->title,
                    'email'       => $email,
                    'comment'     => $data['comment'] ?? null,
                    'message'     => "{$email} {$action} draft \"" . ($post->title ?: 'Untitled') . '"',
                    'url'         => route('user.client-portals.edit', $portal->id),
                ],
                'created_at' => now(),
                'emailed_at' => null,
            ]);
        } catch (\Throwable $e) {}

        // FeedEvent is emitted automatically by ClientPortalAction::record below.

        $msg = $action === 'comment'
            ? 'Comment added.'
            : ($action === 'approved' ? 'Draft approved.' : 'Draft sent back for changes.');
        return back()->with('success', $msg);
    }

    public function invoices()
    {
        $portal = $this->portal();
        $shares = $this->sharesByType(ClientPortalShare::TYPE_INVOICE);

        $invoices = collect();
        foreach ($shares as $share) {
            $inv = Invoice::query()
                ->where('user_id', $portal->workspace->owner_user_id)
                ->where('id', $share->shareable_id)
                ->first();
            if ($inv) $invoices->push($inv);
        }

        ClientPortalAction::record($portal, $this->link(), 'viewed_section', 'invoices');

        return view('portal.invoices', compact('portal', 'invoices'));
    }

    public function payInvoice(int $invoiceId, GatewayManager $gateways)
    {
        $portal = $this->portal();
        $share = $portal->shares()
            ->where('shareable_type', ClientPortalShare::TYPE_INVOICE)
            ->where('shareable_id', $invoiceId)
            ->firstOrFail();

        $invoice = Invoice::query()
            ->where('user_id', $portal->workspace->owner_user_id)
            ->where('id', $invoiceId)
            ->firstOrFail();

        if ($invoice->status === 'paid') {
            return back()->with('success', 'This invoice is already paid.');
        }

        ClientPortalAction::record($portal, $this->link(), 'invoice_pay_clicked', 'invoice', $invoice->id, [
            'number' => $invoice->number,
            'total_minor' => $invoice->grand_total_minor,
        ]);

        // Hand off to the platform's existing Stripe Checkout flow. The
        // adapter creates a hosted Stripe session keyed to this invoice;
        // we simply redirect the portal visitor to it. Stripe's webhook
        // (already wired in the billing module) marks the invoice paid
        // and triggers PaymentAttempt accounting on success.
        try {
            $adapter = $gateways->for('stripe');
            $result  = $adapter->createCheckout($invoice);
            if (($result['kind'] ?? null) === 'redirect' && !empty($result['url'])) {
                ClientPortalAction::record($portal, $this->link(), 'invoice_checkout_started', 'invoice', $invoice->id, [
                    'gateway' => 'stripe',
                ]);
                return redirect()->away($result['url']);
            }
            return back()->with('error', 'Could not start the payment session — please try again later.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Payment is currently unavailable. Please contact ' . $portal->brandingName() . ' to arrange settlement.');
        }
    }

    public function report(int $linkId)
    {
        $share = $this->shareOrFail(ClientPortalShare::TYPE_LINK_PERFORMANCE, $linkId);

        $portal = $this->portal();
        $link = Link::query()->withoutGlobalScope('workspace')
            ->where('workspace_id', $portal->workspace_id)
            ->where('id', $linkId)
            ->firstOrFail();

        $snapshots = LinkPerformanceSnapshot::query()
            ->where('link_id', $link->id)
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        ClientPortalAction::record($portal, $this->link(), 'viewed_section', 'link_performance', $link->id);

        return view('portal.report', compact('portal', 'link', 'snapshots', 'share'));
    }
}
