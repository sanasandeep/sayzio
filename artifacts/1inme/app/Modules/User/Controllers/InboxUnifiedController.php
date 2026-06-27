<?php

namespace App\Modules\User\Controllers;

use App\Modules\Common\Models\ViewerDmConversation;
use App\Modules\Common\Models\ViewerDmMessage;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\FormSubmission;
use App\Modules\User\Models\InboxMessage;
use App\Modules\User\Models\InboxReply;
use App\Modules\User\Models\InboxSnippet;
use App\Modules\User\Models\InboxThread;
use App\Modules\User\Models\InboxThreadAssignment;
use App\Modules\User\Models\InboxThreadConversion;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\TaskBoard;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\TaskColumn;
use App\Modules\User\Models\User;
use App\Modules\User\Models\VaultClient;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\Inbox\InboxAgentSettings;
use App\Modules\User\Services\Inbox\InboxAiReplyDrafter;
use App\Modules\User\Services\Inbox\InboxClassifier;
use App\Modules\User\Services\Inbox\InboxReplyDispatcher;
use App\Modules\User\Services\Inbox\InboxReplySuggester;
use App\Modules\User\Services\WorkspaceActivityRecorder;
use App\Modules\User\Services\Inbox\InboxThreadSync;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\InsufficientCoinsForAiException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Inbox 2.0 — unified, triaged, actionable view across forms, subscribers,
 * viewer DMs, sponsorship enquiries and incoming email forwards.
 *
 * Backed by the new `inbox_threads` + `inbox_messages` schema, kept in
 * sync from the underlying source tables by InboxThreadSync. Every reply
 * dispatches back to the original channel via the matching legacy path
 * so we don't fork the send pipeline.
 */
class InboxUnifiedController
{
    public function __construct(
        protected InboxThreadSync $sync,
        protected InboxClassifier $classifier,
        protected InboxReplySuggester $suggester,
        protected InboxReplyDispatcher $dispatcher,
        protected InboxAiReplyDrafter $drafter,
    ) {}

    public function index(Request $request)
    {
        $ws = $this->workspace();
        $this->sync->syncWorkspace($ws);

        $filters = [
            'q'         => trim((string) $request->get('q')),
            'channel'   => $request->get('channel'),
            'category'  => $request->get('category'),
            'status'    => $request->get('status', 'open'),
            'assignee'  => $request->get('assignee'),
            'starred'   => $request->boolean('starred'),
            'overdue'   => $request->boolean('overdue'),
            'review'    => $request->boolean('review'),
        ];

        $q = InboxThread::query()->where('workspace_id', $ws->id);

        // Private threads (sponsorships by default) only show to the
        // workspace owner, the assignee, or members holding inbox.edit.
        $uid = (int) auth()->id();
        $hasEdit = \App\Modules\User\Services\WorkspacePermissions::userCan('inbox.edit');
        if (!$hasEdit && $uid !== (int) $ws->owner_user_id) {
            $q->where(function ($w) use ($uid) {
                $w->where('is_private', false)->orWhere('assignee_user_id', $uid);
            });
        }

        if ($filters['status']) {
            if ($filters['status'] === 'all') {
                // no-op — show every status
            } else {
                $q->where('status', $filters['status']);
            }
        }
        if ($filters['channel'])  $q->where('channel', $filters['channel']);
        if ($filters['category']) $q->where('category', $filters['category']);
        if ($filters['assignee'] === 'me')        $q->where('assignee_user_id', auth()->id());
        elseif ($filters['assignee'] === 'unassigned') $q->whereNull('assignee_user_id');
        elseif (is_numeric($filters['assignee'])) $q->where('assignee_user_id', (int) $filters['assignee']);
        if ($filters['starred']) $q->where('is_starred', true);
        if ($filters['overdue']) $q->whereNotNull('sla_due_at')->where('sla_due_at', '<', now())->where('status', 'open');
        if ($filters['review'])  $q->awaitingReview();
        if ($filters['q'] !== '') {
            $needle = '%' . $filters['q'] . '%';
            $q->where(function ($w) use ($needle) {
                $w->where('subject', 'ilike', $needle)
                  ->orWhere('preview', 'ilike', $needle)
                  ->orWhere('sender_name', 'ilike', $needle)
                  ->orWhere('sender_email', 'ilike', $needle);
            });
        }

        // Overdue first, then newest activity.
        $threads = $q->orderByRaw("CASE WHEN sla_due_at IS NOT NULL AND sla_due_at < now() AND status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('last_message_at')
            ->paginate(25)
            ->appends($request->except('page'));

        $counts = [
            'all'     => InboxThread::where('workspace_id', $ws->id)->count(),
            'unread'  => InboxThread::where('workspace_id', $ws->id)->where('status', 'open')->where('is_read', false)->count(),
            'overdue' => InboxThread::where('workspace_id', $ws->id)->where('status', 'open')
                            ->whereNotNull('sla_due_at')->where('sla_due_at', '<', now())->count(),
            'review'  => InboxThread::where('workspace_id', $ws->id)->awaitingReview()->count(),
        ];
        $byCategory = InboxThread::where('workspace_id', $ws->id)
            ->where('status', 'open')
            ->selectRaw('category, count(*) as c')
            ->groupBy('category')->pluck('c', 'category')->all();

        $teammates = $this->teammates($ws);
        $snippets  = InboxSnippet::orderBy('shortcut')->get();

        return view('user.inbox.unified.index', compact(
            'threads', 'filters', 'counts', 'byCategory', 'teammates', 'snippets'
        ));
    }

    public function show(Request $request, InboxThread $thread)
    {
        $this->authorize($thread);

        $thread->load(['messages', 'assignments.toUser:id,name', 'assignments.fromUser:id,name', 'assignments.actor:id,name']);
        $assignments = $thread->assignments;
        if (!$thread->is_read || $thread->unread_count > 0) {
            $thread->update(['is_read' => true, 'unread_count' => 0]);
            $this->markSourceRead($thread);
        }

        // Suggest 3 replies based on the user's prior outgoing tone.
        $priorOut = InboxMessage::query()
            ->whereIn('thread_id', InboxThread::where('workspace_id', $thread->workspace_id)->pluck('id'))
            ->where('direction', 'out')
            ->orderByDesc('id')->limit(50)->pluck('body')->all();
        $suggestions = $this->suggester->suggest($thread, $priorOut);

        $snippets   = InboxSnippet::orderBy('shortcut')->get();
        $teammates  = $this->teammates($this->workspace());
        $conversions = $thread->conversions()->orderByDesc('id')->get();

        // Inbox attachments — currently only form_submission threads carry
        // uploaded files. Each entry is [field_label, url, UserFile|null] so
        // the view can render scan-status badges and gate downloads.
        $attachments = [];
        if ($thread->source_type === 'form_submission') {
            $sub = FormSubmission::withoutGlobalScope('workspace')->find($thread->source_id);
            if ($sub && is_array($sub->files)) {
                foreach ($sub->files as $field => $url) {
                    $userFile = \App\Modules\User\Models\UserFile::fromServeUrl($url);
                    $attachments[] = [
                        'label'    => $sub->data[$field] ?? $field,
                        'url'      => $url,
                        'userFile' => $userFile,
                    ];
                }
            }
        }

        return view('user.inbox.unified.show', compact(
            'thread', 'suggestions', 'snippets', 'teammates', 'conversions', 'assignments', 'attachments'
        ));
    }

    public function reply(Request $request, InboxThread $thread)
    {
        $this->authorize($thread);
        $data = $request->validate(['body' => ['required', 'string', 'min:1', 'max:20000']]);
        $body = trim($data['body']);

        $sentVia = $this->dispatcher->sendReply($thread, $body, $request->user());
        if ($sentVia['error']) {
            return back()->withInput()->with('error', $sentVia['error']);
        }

        return back()->with('success', 'Reply sent via ' . $sentVia['via'] . '.');
    }

    /**
     * Generate an AI reply draft for a thread (manual "Draft with AI" /
     * "Regenerate" composer action). Charged to the workspace owner via
     * OpenAiService metering. Returns JSON for the inline composer.
     */
    public function aiDraft(Request $request, InboxThread $thread)
    {
        $this->authorize($thread);
        $ws = $this->workspace();
        $owner = User::find($ws->owner_user_id) ?? $request->user();

        if (!AiEngineSettings::isEnabled()) {
            return response()->json(['error' => ['message' => 'AI features are currently unavailable. Please try again later.']], 503);
        }
        if (!AiPlanAccess::featureAllowed($owner, 'inbox_agent')) {
            $plan = AiPlanAccess::featureUpgradePlan($owner, 'inbox_agent');
            return response()->json([
                'error' => [
                    'message'      => 'AI inbox drafting is not included in your current plan.',
                    'upgrade_plan' => $plan?->name,
                    'upgrade_url'  => route('user.upgrade'),
                ],
            ], 403);
        }

        try {
            $result = $this->drafter->draft($thread, $owner, $ws);
        } catch (InsufficientCoinsForAiException $e) {
            return response()->json([
                'error' => [
                    'message'  => 'Not enough coins to draft this reply. Top up your wallet to continue.',
                    'required' => $e->required,
                    'balance'  => $e->balance,
                    'topup_url'=> route('user.wallet.show'),
                ],
            ], 402);
        } catch (\Throwable $e) {
            return response()->json(['error' => ['message' => 'Could not draft a reply right now. Please try again.']], 502);
        }

        $thread->update(['ai_draft' => $result['draft'], 'ai_draft_at' => now()]);

        return response()->json([
            'data' => [
                'draft'         => $result['draft'],
                'credits_spent' => $result['credits_spent'],
            ],
        ]);
    }

    /** Inbox Agent settings panel. */
    public function agentSettings(Request $request)
    {
        $ws = $this->workspace();
        $owner = User::find($ws->owner_user_id) ?? $request->user();

        $settings   = InboxAgentSettings::for($ws);
        $engineOn   = AiEngineSettings::isEnabled();
        $planAllows = AiPlanAccess::featureAllowed($owner, 'inbox_agent');
        $upgradePlan = $planAllows ? null : AiPlanAccess::featureUpgradePlan($owner, 'inbox_agent');

        return view('user.inbox.unified.agent', [
            'settings'    => $settings,
            'tones'       => InboxAgentSettings::TONES,
            'categories'  => array_values(array_diff(InboxThread::CATEGORIES, InboxAgentSettings::AUTOPILOT_FORBIDDEN_CATEGORIES)),
            'engineOn'    => $engineOn,
            'planAllows'  => $planAllows,
            'upgradePlan' => $upgradePlan,
        ]);
    }

    /** Persist Inbox Agent settings. */
    public function agentSettingsUpdate(Request $request)
    {
        $ws = $this->workspace();

        $data = $request->validate([
            'ai_triage'              => ['nullable', 'boolean'],
            'tone'                   => ['nullable', 'string', 'in:' . implode(',', array_keys(InboxAgentSettings::TONES))],
            'persona'                => ['nullable', 'string', 'max:2000'],
            'signature'              => ['nullable', 'string', 'max:500'],
            'autopilot_enabled'      => ['nullable', 'boolean'],
            'autopilot_categories'   => ['nullable', 'array'],
            'autopilot_categories.*' => ['string', 'in:' . implode(',', InboxThread::CATEGORIES)],
            'confidence_threshold'   => ['nullable', 'numeric', 'min:0.5', 'max:0.99'],
        ]);

        InboxAgentSettings::save($ws, [
            'ai_triage'            => $request->boolean('ai_triage'),
            'tone'                 => $data['tone'] ?? 'auto',
            'persona'              => $data['persona'] ?? '',
            'signature'            => $data['signature'] ?? '',
            'autopilot_enabled'    => $request->boolean('autopilot_enabled'),
            'autopilot_categories' => $data['autopilot_categories'] ?? [],
            'confidence_threshold' => $data['confidence_threshold'] ?? 0.8,
        ]);

        return redirect()->route('user.inbox.unified.agent')->with('success', 'Inbox Agent settings saved.');
    }

    public function update(Request $request, InboxThread $thread)
    {
        $this->authorize($thread);
        $action = $request->input('action');
        $valid = ['star', 'unstar', 'archive', 'unarchive', 'snooze', 'mark_read', 'mark_unread', 'set_category', 'assign', 'set_sla', 'set_private'];
        abort_unless(in_array($action, $valid, true), 422);

        switch ($action) {
            case 'star':       $thread->update(['is_starred' => true]); break;
            case 'unstar':     $thread->update(['is_starred' => false]); break;
            case 'archive':
                $thread->update(['status' => 'archived']);
                $this->recordResolution($thread, 'archived', (string) $request->input('note', ''));
                break;
            case 'unarchive':  $thread->update(['status' => 'open']); break;
            case 'snooze':
                $thread->update(['status' => 'snoozed']);
                $this->recordResolution($thread, 'snoozed', (string) $request->input('note', ''));
                break;
            case 'mark_read':  $thread->update(['is_read' => true, 'unread_count' => 0]); break;
            case 'mark_unread':$thread->update(['is_read' => false, 'unread_count' => 1]); break;
            case 'set_category':
                $cat = $request->input('category');
                abort_unless(in_array($cat, InboxThread::CATEGORIES, true), 422);
                $thread->update($this->classifier->manualOverride($cat) + ['category_source' => 'manual']);
                break;
            case 'assign':
                $uid = $request->input('assignee_user_id');
                $uid = $uid ? (int) $uid : null;
                if ($uid && !$this->teammateExists($this->workspace(), $uid)) {
                    return back()->with('error', 'That teammate is not in this workspace.');
                }
                $note = trim((string) $request->input('note', ''));
                $this->applyAssignment($thread, $uid, $note !== '' ? $note : null);
                break;
            case 'set_private':
                $thread->update(['is_private' => $request->boolean('value')]);
                break;
            case 'set_sla':
                $hours = (int) $request->input('hours');
                $thread->update([
                    'sla_due_at' => $hours > 0 ? now()->addHours($hours) : null,
                    'sla_overdue_notified' => false,
                ]);
                break;
        }
        return back()->with('success', 'Updated.');
    }

    public function bulk(Request $request)
    {
        $ws = $this->workspace();
        $action = $request->input('action');
        $ids = array_filter(array_map('intval', (array) $request->input('thread_ids', [])));
        $valid = ['archive', 'mark_read', 'set_category'];
        abort_unless(in_array($action, $valid, true), 422);

        $threads = InboxThread::where('workspace_id', $ws->id)->whereIn('id', $ids)->get();
        foreach ($threads as $t) {
            if ($action === 'archive')   $t->update(['status' => 'archived']);
            if ($action === 'mark_read') $t->update(['is_read' => true, 'unread_count' => 0]);
            if ($action === 'set_category') {
                $cat = $request->input('category');
                if (in_array($cat, InboxThread::CATEGORIES, true)) {
                    $t->update($this->classifier->manualOverride($cat) + ['category_source' => 'manual']);
                }
            }
        }
        return back()->with('success', count($threads) . ' thread(s) updated.');
    }

    // ------------------------------------------------------------------
    // One-click conversions
    // ------------------------------------------------------------------

    public function convertToKanban(Request $request, InboxThread $thread)
    {
        $this->authorize($thread);
        $boardId = (int) $request->input('board_id');
        $board = TaskBoard::find($boardId);
        abort_unless($board && $board->workspace_id === $thread->workspace_id, 404);

        $column = TaskColumn::where('board_id', $board->id)->orderBy('position')->first();
        abort_unless($column, 422, 'This board has no columns yet.');

        $card = TaskCard::create([
            'board_id'           => $board->id,
            'column_id'          => $column->id,
            'created_by_user_id' => auth()->id(),
            'title'              => Str::limit(($thread->subject ?: 'Inbox thread') . ' · ' . ($thread->sender_name ?: ''), 200),
            'description'        => $this->conversionContextBlob($thread),
            'position'           => (int) (TaskCard::where('column_id', $column->id)->max('position') ?? 0) + 1,
        ]);

        InboxThreadConversion::create([
            'thread_id'          => $thread->id,
            'kind'               => 'kanban',
            'target_id'          => $card->id,
            'created_by_user_id' => auth()->id(),
            'meta'               => ['board_id' => $board->id, 'column_id' => $column->id],
        ]);

        return back()->with('success', 'Kanban card created on "' . $board->name . '".');
    }

    public function convertToContact(Request $request, InboxThread $thread)
    {
        $this->authorize($thread);

        $contact = Contact::create([
            'user_id'      => $thread->user_id,
            'display_name' => $thread->sender_name ?: ($thread->sender_email ?: 'Inbox contact'),
            'notes'        => "Created from Inbox thread #{$thread->id} ({$thread->channelLabel()}).\n\n" . $this->conversionContextBlob($thread, false),
        ]);
        if ($thread->sender_email) {
            ContactEmail::create(['contact_id' => $contact->id, 'value' => ContactEmail::normalize($thread->sender_email), 'label' => 'inbox']);
        }

        InboxThreadConversion::create([
            'thread_id'          => $thread->id,
            'kind'               => 'contact',
            'target_id'          => $contact->id,
            'created_by_user_id' => auth()->id(),
        ]);
        return back()->with('success', 'Saved to Contacts.');
    }

    public function convertToVault(Request $request, InboxThread $thread)
    {
        $this->authorize($thread);

        $client = VaultClient::create([
            'created_by_user_id' => auth()->id(),
            'name'               => $thread->sender_name ?: ($thread->sender_email ?: 'Inbox lead'),
            'primary_email'      => $thread->sender_email,
            'visibility'         => 'workspace',
            'tags'               => [$thread->category],
        ]);
        $client->setEncrypted('notes', "Created from Inbox thread #{$thread->id} ({$thread->channelLabel()}).\n\n" . $this->conversionContextBlob($thread, false));
        $client->save();

        InboxThreadConversion::create([
            'thread_id'          => $thread->id,
            'kind'               => 'vault',
            'target_id'          => $client->id,
            'created_by_user_id' => auth()->id(),
        ]);
        return back()->with('success', 'Saved to Vault clients.');
    }

    public function convertToCalendar(Request $request, InboxThread $thread)
    {
        $this->authorize($thread);
        $data = $request->validate([
            'when' => ['required', 'date'],
        ]);

        // We don't have a first-class user-owned calendar event table, so
        // we represent the booking as a kanban card on a "Calendar" column
        // if available, otherwise fall back to creating a snippet-style
        // record in `inbox_thread_conversions.meta`. This keeps the UX
        // single-click without inventing an unowned schema.
        InboxThreadConversion::create([
            'thread_id'          => $thread->id,
            'kind'               => 'calendar',
            'target_id'          => 0,
            'created_by_user_id' => auth()->id(),
            'meta'               => [
                'when'    => Carbon::parse($data['when'])->toIso8601String(),
                'subject' => $thread->subject,
                'with'    => $thread->sender_name,
            ],
        ]);
        return back()->with('success', 'Calendar entry recorded for ' . Carbon::parse($data['when'])->format('M j, H:i') . '.');
    }

    // ------------------------------------------------------------------
    // Snippets
    // ------------------------------------------------------------------

    public function snippetsIndex()
    {
        $snippets = InboxSnippet::orderBy('shortcut')->paginate(25);
        return view('user.inbox.unified.snippets', compact('snippets'));
    }

    public function snippetsStore(Request $request)
    {
        $data = $request->validate([
            'shortcut' => ['required', 'string', 'max:64'],
            'label'    => ['required', 'string', 'max:200'],
            'body'     => ['required', 'string', 'max:5000'],
        ]);
        $data['shortcut'] = '/' . ltrim(Str::slug($data['shortcut'], ''), '/');
        $data['created_by_user_id'] = auth()->id();
        InboxSnippet::create($data);
        return back()->with('success', 'Snippet saved.');
    }

    public function snippetsDestroy(InboxSnippet $snippet)
    {
        $snippet->delete();
        return back()->with('success', 'Snippet removed.');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function workspace(): Workspace
    {
        $ws = app()->bound('current_workspace') ? app('current_workspace') : null;
        abort_unless($ws, 403, 'No active workspace.');
        return $ws;
    }

    /**
     * Workspace + per-thread access control.
     *
     * Private threads (sponsorship enquiries by default, plus anything an
     * inbound webhook flagged) are only visible to the workspace owner,
     * the thread's current assignee, or members holding `inbox.edit`.
     * Everyone else inside the workspace gets a 403, even if their seat
     * grants `inbox.view`.
     */
    protected function authorize(InboxThread $thread): void
    {
        $ws = $this->workspace();
        abort_unless($thread->workspace_id === $ws->id, 404);

        if (!$thread->is_private) return;

        $uid = (int) auth()->id();
        $isOwner    = $uid === (int) $ws->owner_user_id;
        $isAssignee = $thread->assignee_user_id && (int) $thread->assignee_user_id === $uid;
        $hasEdit    = \App\Modules\User\Services\WorkspacePermissions::userCan('inbox.edit');

        abort_unless($isOwner || $isAssignee || $hasEdit, 403, 'This thread is restricted to the assignee.');
    }

    /** @return array<int, array{id:int,name:string}> */
    protected function teammates(Workspace $ws): array
    {
        $members = WorkspaceMember::with('user:id,name')->where('workspace_id', $ws->id)->get();
        $rows = [];
        if ($ws->owner) $rows[] = ['id' => $ws->owner->id, 'name' => $ws->owner->name . ' (owner)'];
        foreach ($members as $m) {
            if ($m->user) $rows[] = ['id' => $m->user->id, 'name' => $m->user->name];
        }
        return $rows;
    }

    /**
     * Apply an assignee change, log it to the assignment-history table,
     * and notify the teammates entering / leaving the thread. Skips the
     * notification when the assignee isn't actually changing so we don't
     * spam people on idempotent form submits.
     */
    protected function applyAssignment(InboxThread $thread, ?int $newId, ?string $note): void
    {
        $oldId = $thread->assignee_user_id ? (int) $thread->assignee_user_id : null;
        if ($oldId === $newId && !$note) return;

        $thread->update(['assignee_user_id' => $newId]);

        $action = match (true) {
            $newId === null && $oldId !== null => 'unassign',
            $newId !== null && $oldId === null => 'assign',
            default => 'reassign',
        };
        $actorId = (int) auth()->id();

        InboxThreadAssignment::create([
            'thread_id'     => $thread->id,
            'from_user_id'  => $oldId,
            'to_user_id'    => $newId,
            'actor_user_id' => $actorId,
            'action'        => $action,
            'note'          => $note,
            'created_at'    => now(),
        ]);

        $actorName = optional(auth()->user())->name ?? 'A teammate';
        $subject = $thread->subject ?: ($thread->sender_name ?: 'an inbox thread');
        $url = route('user.inbox.unified.show', $thread->id);

        // Notify the *new* assignee (unless they assigned themselves).
        if ($newId && $newId !== $actorId) {
            UserNotification::create([
                'user_id'    => $newId,
                'type'       => 'inbox_assigned',
                'data'       => [
                    'message'   => $actorName . ' assigned you a thread: ' . $subject,
                    'thread_id' => $thread->id,
                    'note'      => $note,
                    'url'       => $url,
                ],
                'created_at' => now(),
            ]);
        }
        // Notify the *previous* assignee that the handoff happened (skip
        // if they're the one who reassigned — they already know).
        if ($oldId && $oldId !== $actorId && $oldId !== $newId) {
            UserNotification::create([
                'user_id'    => $oldId,
                'type'       => 'inbox_unassigned',
                'data'       => [
                    'message'   => $actorName . ' reassigned a thread you were handling: ' . $subject,
                    'thread_id' => $thread->id,
                    'note'      => $note,
                    'url'       => $url,
                ],
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Stamp an assignment-history row when a thread is closed/snoozed so
     * we keep an audit of who was holding it at the moment it left the
     * open queue. No-op for unassigned threads.
     */
    protected function recordResolution(InboxThread $thread, string $why, string $note): void
    {
        $assigneeId = $thread->assignee_user_id ? (int) $thread->assignee_user_id : null;
        if (!$assigneeId) return;

        InboxThreadAssignment::create([
            'thread_id'     => $thread->id,
            'from_user_id'  => $assigneeId,
            'to_user_id'    => $assigneeId,
            'actor_user_id' => (int) auth()->id(),
            'action'        => 'resolved',
            'note'          => trim($note) !== '' ? trim($note . ' (' . $why . ')') : $why,
            'created_at'    => now(),
        ]);
    }

    protected function teammateExists(Workspace $ws, int $userId): bool
    {
        if ((int) $ws->owner_user_id === $userId) return true;
        return WorkspaceMember::where('workspace_id', $ws->id)->where('user_id', $userId)->exists();
    }

    protected function markSourceRead(InboxThread $thread): void
    {
        if ($thread->source_type === 'form_submission') {
            FormSubmission::query()->withoutGlobalScope('workspace')
                ->where('id', $thread->source_id)->update(['is_read' => true]);
        } elseif ($thread->source_type === 'subscriber') {
            Subscriber::query()->withoutGlobalScope('workspace')
                ->where('id', $thread->source_id)->update(['is_read' => true, 'read_at' => now()]);
        } elseif ($thread->source_type === 'viewer_dm') {
            $c = ViewerDmConversation::find($thread->source_id);
            if ($c && $c->owner_unread_count > 0) {
                $c->messages()->where('sender_type', 'viewer')->whereNull('read_at')->update(['read_at' => now()]);
                $c->owner_unread_count = 0;
                $c->save();
            }
        }
    }

    protected function conversionContextBlob(InboxThread $thread, bool $heading = true): string
    {
        $lines = [];
        if ($heading) $lines[] = "From inbox: {$thread->channelLabel()} · {$thread->categoryLabel()}";
        if ($thread->sender_name)  $lines[] = 'Sender: ' . $thread->sender_name;
        if ($thread->sender_email) $lines[] = 'Email: '  . $thread->sender_email;
        if ($thread->subject)      $lines[] = 'Subject: ' . $thread->subject;
        $lines[] = '';
        $lines[] = $thread->preview ?: '';
        $lines[] = '';
        $lines[] = 'Source: inbox thread #' . $thread->id;
        return implode("\n", $lines);
    }
}
