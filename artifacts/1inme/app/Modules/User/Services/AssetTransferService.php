<?php

namespace App\Modules\User\Services;

use App\Modules\Common\Services\Emailer;
use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Models\AssetTransfer;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Instant, admin-authorized transfer of a link (with its full ownership
 * graph) or a whole workspace from one user account to another.
 *
 * Ownership reassignment follows the same schema-introspection approach as
 * AccountMergeService, but scoped to one asset: every table carrying a
 * `link_id` (or `workspace_id`) column has its `user_id` / `workspace_id`
 * columns rewritten for the affected rows. Transfers always succeed
 * regardless of the recipient's plan caps — over-cap items simply become
 * edit-gated by the existing plan-gating middleware/checks.
 *
 * Notifications (in-app + Emailer) and the asset_transfers audit row are
 * written for every transfer; notification failures never roll back the
 * transfer itself.
 */
class AssetTransferService
{
    /**
     * Tables that reference links but must never be rewritten (audit /
     * infrastructure), mirroring AccountMergeService's conservative stance.
     */
    private const SKIP_TABLES = [
        'links', 'workspaces', 'users', 'sessions', 'migrations', 'jobs',
        'failed_jobs', 'cache', 'cache_locks', 'personal_access_tokens',
        'audit_logs', 'admin_action_audits', 'asset_transfers',
    ];

    public function __construct(
        protected NotificationService $notifications,
    ) {
    }

    /** Guard shared by both transfer kinds. Throws on any violation. */
    public function assertCanTransfer(User $sender, User $recipient): void
    {
        if (!$sender->canTransferAssets()) {
            throw new \RuntimeException('You do not have permission to transfer assets. Ask an administrator to grant it.');
        }
        if ($sender->id === $recipient->id) {
            throw new \InvalidArgumentException('You cannot transfer an asset to yourself.');
        }
    }

    /**
     * Transfer one link (any type) and its full ownership graph to the
     * recipient. The link lands in the recipient's personal workspace.
     *
     * @return AssetTransfer the audit row
     */
    public function transferLink(Link $link, User $sender, User $recipient, string $channel = 'web'): AssetTransfer
    {
        $this->assertCanTransfer($sender, $recipient);
        if ((int) $link->user_id !== (int) $sender->id) {
            throw new \RuntimeException('You can only transfer links you own.');
        }

        $label = $link->title ?: ($link->alias ?: ('Link #' . $link->id));

        $transfer = DB::transaction(function () use ($link, $sender, $recipient, $channel, $label) {
            $targetWs = $recipient->ensureDefaultWorkspace();

            $reassigned = $this->reassignLinkGraph($link, $sender, $recipient, $targetWs->id);

            // The link row itself. Sender-owned attachments that cannot
            // follow the recipient (custom domain, project/folder, splash
            // page) are detached unless they're global resources.
            $updates = [
                'user_id'      => $recipient->id,
                'workspace_id' => $targetWs->id,
            ];
            if (Schema::hasColumn('links', 'project_id')) {
                $updates['project_id'] = null;
            }
            if ($link->domain_id && Schema::hasColumn('links', 'domain_id')) {
                $domainIsGlobal = DB::table('domains')
                    ->where('id', $link->domain_id)
                    ->where('is_global', true)
                    ->exists();
                if (!$domainIsGlobal) {
                    $updates['domain_id'] = null;
                }
            }
            if (Schema::hasColumn('links', 'splash_page_id') && $link->splash_page_id) {
                $updates['splash_page_id'] = null;
            }
            DB::table('links')->where('id', $link->id)->update($updates);

            // Type-specific ownership rows referenced FROM the link
            // (rather than pointing at it): the resume behind a resume link.
            if (Schema::hasColumn('links', 'resume_id') && $link->resume_id && Schema::hasTable('resumes')) {
                $n = DB::table('resumes')->where('id', $link->resume_id)
                    ->where('user_id', $sender->id)
                    ->update(['user_id' => $recipient->id]);
                if ($n > 0) $reassigned['resumes.id'] = $n;
            }

            return AssetTransfer::create([
                'kind'         => AssetTransfer::KIND_LINK,
                'asset_id'     => $link->id,
                'asset_label'  => $label,
                'from_user_id' => $sender->id,
                'to_user_id'   => $recipient->id,
                'from_email'   => $sender->email,
                'to_email'     => $recipient->email,
                'channel'      => $channel,
                'details'      => ['reassigned' => $reassigned],
            ]);
        });

        $this->notifyBothParties($transfer, 'link', $label, $sender, $recipient);

        return $transfer;
    }

    /**
     * Transfer a whole workspace: the recipient becomes owner, every
     * sender-owned link inside it moves (with its graph) but stays in the
     * workspace, and sender-owned workspace-scoped records follow. The
     * sender is fully detached; a transferred personal workspace becomes a
     * team workspace on the recipient's side (they keep their own personal).
     *
     * @return AssetTransfer the audit row
     */
    public function transferWorkspace(Workspace $workspace, User $sender, User $recipient, string $channel = 'web'): AssetTransfer
    {
        $this->assertCanTransfer($sender, $recipient);
        if ((int) $workspace->owner_user_id !== (int) $sender->id) {
            throw new \RuntimeException('You can only transfer workspaces you own.');
        }

        $label = $workspace->name ?: ('Workspace #' . $workspace->id);

        $transfer = DB::transaction(function () use ($workspace, $sender, $recipient, $channel, $label) {
            $reassigned = [];

            // Membership remap must happen BEFORE any bulk user_id rewrite:
            // workspace_members has a unique (workspace_id, user_id) key, so
            // rewriting a sender row to the recipient would collide when the
            // recipient is already a member. The recipient becomes owner
            // (drop any member row they held); the sender is removed.
            if (Schema::hasTable('workspace_members')) {
                DB::table('workspace_members')
                    ->where('workspace_id', $workspace->id)
                    ->whereIn('user_id', [$sender->id, $recipient->id])
                    ->delete();
            }

            // Move EVERY link in the workspace (full graph), whoever created
            // it, keeping workspace_id — the whole workspace changes hands.
            $linkRows = DB::table('links')
                ->where('workspace_id', $workspace->id)
                ->get(['id', 'user_id']);
            $linkIds = $linkRows->pluck('id');
            foreach ($linkRows as $row) {
                $l = Link::find($row->id);
                if (!$l) continue;
                $prevOwner = (int) $row->user_id === (int) $sender->id
                    ? $sender
                    : (User::find($row->user_id) ?? $sender);
                $counts = $this->reassignLinkGraph($l, $prevOwner, $recipient, $workspace->id);
                foreach ($counts as $k => $v) {
                    $reassigned[$k] = ($reassigned[$k] ?? 0) + $v;
                }
                if (Schema::hasColumn('links', 'resume_id') && $l->resume_id && Schema::hasTable('resumes')) {
                    $n = DB::table('resumes')->where('id', $l->resume_id)
                        ->where('user_id', $sender->id)
                        ->update(['user_id' => $recipient->id]);
                    if ($n > 0) $reassigned['resumes.id'] = ($reassigned['resumes.id'] ?? 0) + $n;
                }
            }
            if ($linkIds->isNotEmpty()) {
                $n = DB::table('links')
                    ->whereIn('id', $linkIds)
                    ->update(['user_id' => $recipient->id]);
                $reassigned['links.user_id'] = $n;
            }

            // Any other sender-owned record scoped to this workspace
            // (subscribers, forms, contacts, etc. — discovered, not listed).
            foreach ($this->tablesWith('workspace_id') as $table) {
                if (!Schema::hasColumn($table, 'user_id')) continue;
                $n = DB::table($table)
                    ->where('workspace_id', $workspace->id)
                    ->where('user_id', $sender->id)
                    ->update(['user_id' => $recipient->id]);
                if ($n > 0) $reassigned[$table . '.user_id'] = ($reassigned[$table . '.user_id'] ?? 0) + $n;
            }

            // Pending invites addressed to the recipient are now moot.
            if (Schema::hasTable('workspace_invites') && $recipient->email) {
                DB::table('workspace_invites')
                    ->where('workspace_id', $workspace->id)
                    ->whereRaw('lower(email) = ?', [strtolower($recipient->email)])
                    ->delete();
            }

            DB::table('workspaces')->where('id', $workspace->id)->update([
                'owner_user_id' => $recipient->id,
                // The recipient already has their own personal workspace;
                // a transferred one always arrives as a team workspace.
                'is_personal'   => false,
            ]);

            // Never leave the sender without a default workspace.
            $sender->ensureDefaultWorkspace();

            return AssetTransfer::create([
                'kind'         => AssetTransfer::KIND_WORKSPACE,
                'asset_id'     => $workspace->id,
                'asset_label'  => $label,
                'from_user_id' => $sender->id,
                'to_user_id'   => $recipient->id,
                'from_email'   => $sender->email,
                'to_email'     => $recipient->email,
                'channel'      => $channel,
                'details'      => ['reassigned' => $reassigned, 'links_moved' => count($linkIds)],
            ]);
        });

        $this->notifyBothParties($transfer, 'workspace', $label, $sender, $recipient);

        return $transfer;
    }

    /**
     * Rewrite ownership columns on every table that references this link
     * (blocks, analytics, QR codes, subscribers, files, forms, orders, …).
     * Rows keep their link_id — only user_id / workspace_id move.
     *
     * @return array<string,int> per-table.column reassignment counts
     */
    protected function reassignLinkGraph(Link $link, User $sender, User $recipient, int $targetWorkspaceId): array
    {
        $reassigned = [];
        foreach ($this->tablesWith('link_id') as $table) {
            if (Schema::hasColumn($table, 'user_id')) {
                $n = DB::table($table)
                    ->where('link_id', $link->id)
                    ->where('user_id', $sender->id)
                    ->update(['user_id' => $recipient->id]);
                if ($n > 0) $reassigned[$table . '.user_id'] = $n;
            }
            if (Schema::hasColumn($table, 'workspace_id')) {
                $n = DB::table($table)
                    ->where('link_id', $link->id)
                    ->update(['workspace_id' => $targetWorkspaceId]);
                if ($n > 0) $reassigned[$table . '.workspace_id'] = $n;
            }
        }
        return $reassigned;
    }

    /** All non-skipped tables carrying $column, discovered via the schema. */
    protected function tablesWith(string $column): array
    {
        static $cache = [];
        if (isset($cache[$column])) return $cache[$column];

        $out = [];
        foreach ($this->allTables() as $table) {
            if (in_array($table, self::SKIP_TABLES, true)) continue;
            if (Schema::hasColumn($table, $column)) $out[] = $table;
        }
        return $cache[$column] = $out;
    }

    private function allTables(): array
    {
        try {
            $names = [];
            foreach (Schema::getTables() as $t) {
                $names[] = is_array($t) ? ($t['name'] ?? '') : (is_object($t) ? ($t->name ?? '') : (string) $t);
            }
            return array_filter($names);
        } catch (\Throwable) {
            $rows = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
            return array_map(fn ($r) => $r->name, $rows);
        }
    }

    /** In-app + email for both parties. Best-effort — never throws. */
    protected function notifyBothParties(AssetTransfer $transfer, string $kind, string $label, User $sender, User $recipient): void
    {
        $kindLabel = $kind === 'workspace' ? 'workspace' : 'link';
        try {
            $this->notifications->notify($sender, 'asset_transfer', [
                'direction'   => 'sent',
                'kind'        => $kind,
                'asset_label' => $label,
                'other_name'  => $recipient->name ?: $recipient->email,
                'other_email' => $recipient->email,
                'transfer_id' => $transfer->id,
            ]);
            $this->notifications->notify($recipient, 'asset_transfer', [
                'direction'   => 'received',
                'kind'        => $kind,
                'asset_label' => $label,
                'other_name'  => $sender->name ?: $sender->email,
                'other_email' => $sender->email,
                'transfer_id' => $transfer->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Asset transfer in-app notification failed: ' . $e->getMessage());
        }

        try {
            if ($sender->email) {
                Emailer::send('transfer.sent', $sender->email, [
                    'asset_kind'      => $kindLabel,
                    'asset_label'     => $label,
                    'recipient_name'  => $recipient->name ?: (string) $recipient->email,
                    'recipient_email' => (string) $recipient->email,
                ]);
            }
            if ($recipient->email) {
                Emailer::send('transfer.received', $recipient->email, [
                    'asset_kind'   => $kindLabel,
                    'asset_label'  => $label,
                    'sender_name'  => $sender->name ?: (string) $sender->email,
                    'sender_email' => (string) $sender->email,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Asset transfer email failed: ' . $e->getMessage());
        }
    }
}
