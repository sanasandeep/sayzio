<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceAuditAlertPref;
use App\Modules\User\Models\WorkspaceAuditEvent;
use App\Modules\User\Models\WorkspaceAuditReport;
use App\Modules\User\Models\User;
use App\Modules\User\Services\SensitiveActionLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Owner-facing audit log of sensitive workspace actions, the
 * "this wasn't me" report flow opened from alert emails, and the
 * per-action email-alert preferences page.
 */
class WorkspaceAuditController extends Controller
{
    /**
     * Active workspace + access gate.
     *
     * `$ownerOnly = true` enforces the stricter "owner or super-admin"
     * check used for alert preferences (so a workspace admin cannot
     * silence the owner's alerts). `$ownerOnly = false` allows
     * workspace admins to view the read-only audit log alongside the
     * owner.
     */
    protected function workspace(Request $request, bool $ownerOnly = false): Workspace
    {
        $ws = app('current_workspace');
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $isOwner      = (int) $ws->owner_user_id === (int) $user->id;
        $isSuperAdmin = $user->isSuperAdmin();
        $isWsAdmin    = optional($user->membershipFor($ws))->role === 'admin';

        $allowed = $ownerOnly
            ? ($isOwner || $isSuperAdmin)
            : ($isOwner || $isSuperAdmin || $isWsAdmin);

        abort_unless(
            $allowed,
            403,
            $ownerOnly
                ? 'Only the workspace owner can change audit alert preferences.'
                : 'Only the workspace owner or an Admin can view the audit log.',
        );
        return $ws;
    }

    /** Searchable, filterable view of every sensitive-action event. */
    public function index(Request $request)
    {
        $ws = $this->workspace($request);

        // Initialise filters up-front so the view always receives
        // stable values regardless of which query params were sent.
        $search  = trim((string) $request->get('q', ''));
        $action  = (string) $request->get('action', '');
        $actor   = (string) $request->get('actor_id', '');
        $flagged = (string) $request->get('flagged', '');

        $q = WorkspaceAuditEvent::query()
            ->with(['actor:id,name,email,avatar', 'reports'])
            ->orderByDesc('occurred_at');

        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('target_label', 'like', "%{$search}%")
                  ->orWhere('ip', 'like', "%{$search}%");
            });
        }
        if ($action !== '' && array_key_exists($action, SensitiveActionLogger::CATALOG)) {
            $q->where('action', $action);
        }
        if ($actor !== '' && ctype_digit($actor)) {
            $q->where('actor_user_id', (int) $actor);
        }
        if ($flagged === '1') {
            $q->whereNotNull('reported_unauthorized_at');
        }

        $events  = $q->paginate(50)->withQueryString();
        $catalog = SensitiveActionLogger::CATALOG;
        $chain   = WorkspaceAuditEvent::verifyChain($ws->id);

        return view('user.workspaces.audit.index', [
            'workspace' => $ws,
            'events'    => $events,
            'catalog'   => $catalog,
            'chain'     => $chain,
            'filters'   => [
                'q'        => $search,
                'action'   => $action,
                'actor_id' => $actor,
                'flagged'  => $flagged,
            ],
        ]);
    }

    /** Per-action email-alert preferences page. Owner-only. */
    public function preferences(Request $request)
    {
        $ws = $this->workspace($request, ownerOnly: true);

        $prefs = WorkspaceAuditAlertPref::where('workspace_id', $ws->id)
            ->get()
            ->keyBy('action');

        $rows = [];
        foreach (SensitiveActionLogger::CATALOG as $action => $meta) {
            $rows[$action] = [
                'label'         => $meta['label'],
                'default'       => (bool) ($meta['default_alert'] ?? false),
                'alert_enabled' => $prefs->has($action)
                    ? (bool) $prefs[$action]->alert_enabled
                    : (bool) ($meta['default_alert'] ?? false),
            ];
        }

        return view('user.workspaces.audit.preferences', [
            'workspace' => $ws,
            'rows'      => $rows,
        ]);
    }

    public function updatePreferences(Request $request)
    {
        $ws = $this->workspace($request, ownerOnly: true);

        $request->validate([
            'alerts'   => 'nullable|array',
            'alerts.*' => 'in:0,1,on',
        ]);
        $checked = (array) $request->input('alerts', []);

        foreach (array_keys(SensitiveActionLogger::CATALOG) as $action) {
            $enabled = !empty($checked[$action]) && $checked[$action] !== '0';
            WorkspaceAuditAlertPref::updateOrCreate(
                ['workspace_id' => $ws->id, 'action' => $action],
                ['alert_enabled' => $enabled],
            );
        }

        return redirect()->route('user.workspaces.audit.preferences')
            ->with('success', 'Audit alert preferences saved.');
    }

    /**
     * Resolve the recipient embedded in the signed URL, and verify
     * they are still an owner/admin of the event's workspace. The
     * signed middleware has already proven the URL was issued by us
     * (with this exact recipient id), but membership may have
     * changed since the email was sent — re-check on every hit.
     *
     * Returns the validated User or aborts 403.
     */
    protected function resolveSignedRecipient(Request $request, WorkspaceAuditEvent $event): User
    {
        $recipientId = (int) $request->query('recipient');
        abort_if($recipientId <= 0, 403, 'Missing recipient.');

        $user = User::find($recipientId);
        abort_unless($user, 403, 'Unknown recipient.');

        $ws = $event->workspace;
        abort_unless($ws, 404, 'Workspace not found.');

        $allowed = $user->isSuperAdmin()
            || (int) $ws->owner_user_id === (int) $user->id
            || (optional($user->membershipFor($ws))->role === 'admin');
        abort_unless($allowed, 403, 'You are no longer an owner or admin of this workspace.');

        return $user;
    }

    /**
     * Investigation view opened from the alert email's "this wasn't
     * authorised" button. The route is signed (verified by middleware)
     * so it works even without a session.
     */
    public function reportShow(Request $request, WorkspaceAuditEvent $event)
    {
        $recipient = $this->resolveSignedRecipient($request, $event);

        $event->loadMissing(['actor:id,name,email', 'workspace', 'reports.reporter']);

        // Show ±6h surrounding events for context — same workspace.
        $surrounding = WorkspaceAuditEvent::query()
            ->withoutWorkspaceScope()
            ->where('workspace_id', $event->workspace_id)
            ->whereBetween('occurred_at', [
                $event->occurred_at?->copy()->subHours(6) ?? now()->subHours(6),
                $event->occurred_at?->copy()->addHours(6) ?? now(),
            ])
            ->where('id', '!=', $event->id)
            ->orderByDesc('occurred_at')
            ->limit(20)
            ->get();

        $chain = WorkspaceAuditEvent::verifyChain($event->workspace_id);

        // Issue a short-lived signed URL specifically for the POST
        // submission, so the form action is itself signature-protected.
        $reportPostUrl = URL::temporarySignedRoute(
            'user.workspaces.audit.report.store',
            now()->addHours(2),
            ['event' => $event->id, 'recipient' => $recipient->id],
        );

        return view('user.workspaces.audit.report', [
            'event'         => $event,
            'workspace'     => $event->workspace,
            'surrounding'   => $surrounding,
            'chain'         => $chain,
            'recipient'     => $recipient,
            'reportPostUrl' => $reportPostUrl,
        ]);
    }

    /**
     * Persist a "this wasn't me" report and stamp the event. The
     * `signed` middleware on the route guarantees the URL (including
     * the embedded `recipient` id) was issued by us, so we can trust
     * `recipient` as the reporter identity. We still re-verify the
     * recipient is currently an owner/admin of this event's workspace.
     */
    public function reportStore(Request $request, WorkspaceAuditEvent $event)
    {
        $recipient = $this->resolveSignedRecipient($request, $event);

        $data = $request->validate([
            'note' => 'nullable|string|max:2000',
        ]);

        WorkspaceAuditReport::create([
            'workspace_audit_event_id' => $event->id,
            'reporter_user_id'         => $recipient->id,
            'reporter_email'           => $recipient->email,
            'ip'                       => $request->ip(),
            'note'                     => trim((string) ($data['note'] ?? '')) ?: null,
            'created_at'               => now(),
        ]);

        // Stamp the event itself so the log view can highlight it. We
        // intentionally do NOT mutate the hash columns — flagging is
        // metadata, not part of the immutable record.
        if (!$event->reported_unauthorized_at) {
            $event->forceFill([
                'reported_unauthorized_at' => now(),
                'reported_by_user_id'      => $recipient->id,
            ])->save();
        }

        // Bounce back to a fresh signed report.show so the page loads
        // without re-using the just-consumed POST signature.
        $followUp = URL::signedRoute(
            'user.workspaces.audit.report.show',
            ['event' => $event->id, 'recipient' => $recipient->id],
            now()->addDays(30),
        );

        return redirect($followUp)
            ->with('success', 'Report filed. Our security team and the workspace owners have been notified.');
    }
}
