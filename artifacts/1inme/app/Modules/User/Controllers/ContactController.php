<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessContactExportJob;
use App\Jobs\ProcessContactImportJob;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\ContactExport;
use App\Modules\User\Models\ContactImport;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\ContactDeletionTombstone;
use App\Modules\User\Models\ContactWorkspaceShare;
use App\Modules\User\Models\GoogleContactsAccount;
use App\Modules\User\Models\IntegrationConfig;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\Contacts\BiolinkAttachResolver;
use App\Modules\User\Services\Contacts\ContactDuplicateDetector;
use App\Modules\User\Services\Contacts\ContactExportBuilder;
use App\Modules\User\Services\Contacts\ContactImportParser;
use App\Modules\User\Services\Contacts\ContactMergeService;
use App\Modules\User\Services\Contacts\GoogleContactsSyncService;
use App\Modules\User\Support\ContactWorkspaceShareHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ContactController extends Controller
{
    public const PHONE_LABELS = ['Mobile', 'Work', 'Home', 'Main', 'Other'];
    public const EMAIL_LABELS = ['Personal', 'Work', 'Other'];

    /** Contacts above this threshold trigger a queued export job. */
    public const EXPORT_ASYNC_THRESHOLD = 500;

    public function __construct(
        protected BiolinkAttachResolver $resolver,
        protected GoogleContactsSyncService $sync,
        protected ContactImportParser $importParser,
        protected ContactExportBuilder $exportBuilder,
        protected ContactDuplicateDetector $detector,
        protected ContactMergeService $mergeService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $tab  = $request->query('tab') === 'biolink' ? 'biolink' : 'all';
        $search = trim((string) $request->query('q', ''));

        // The contacts address book is account-wide (matching the dialer
        // finder and the Sanctum/mobile API), so opt out of the workspace
        // global scope; the user_id predicate still scopes to the owner.
        $tag  = trim((string) $request->query('tag', ''));
        $sort = $request->query('sort') === 'activity' ? 'activity' : 'name';

        $query = Contact::withoutGlobalScope('workspace')
            ->where('user_id', $user->id)
            ->with(['phones', 'emails', 'biolinkUser']);

        if ($tab === 'biolink') $query->whereNotNull('biolink_user_id');
        if ($tag !== '') {
            // Filter contacts that include the requested tag in their JSON tags array.
            $query->whereJsonContains('tags', $tag);
        }
        if ($search !== '') {
            $needle = '%' . $search . '%';
            $phoneNeedle = '%' . ContactPhone::normalize($search) . '%';
            $query->where(function ($q) use ($needle, $phoneNeedle) {
                $q->where('display_name', 'ilike', $needle)
                  ->orWhere('given_name', 'ilike', $needle)
                  ->orWhere('family_name', 'ilike', $needle)
                  ->orWhere('organization', 'ilike', $needle)
                  ->orWhereHas('phones',  fn ($q2) => $q2->where('value_e164', 'ilike', $phoneNeedle))
                  ->orWhereHas('emails',  fn ($q2) => $q2->where('value', 'ilike', $needle));
            });
        }

        // "Most active" sort (Task #6510): one bulk UNION-ALL count pass over
        // the capture tables joined in as a derived table — never per-contact
        // subqueries. Falls back to alphabetical if the join fails.
        if ($sort === 'activity') {
            try {
                $totals = app(\App\Modules\User\Services\Contacts\ContactActivityService::class)
                    ->activityTotalsQuery((int) $user->id);
                $query->leftJoinSub($totals, 'contact_activity', 'contact_activity.contact_id', '=', 'contacts.id')
                    ->select('contacts.*')
                    ->orderByRaw('COALESCE(contact_activity.activity_total, 0) DESC')
                    ->orderBy('display_name');
            } catch (\Throwable) {
                $sort = 'name';
                $query->orderBy('display_name');
            }
        } else {
            $query->orderBy('display_name');
        }

        $contacts = $query->paginate(40)->withQueryString();
        $googleAccount = GoogleContactsAccount::where('user_id', $user->id)->first();

        $totalContacts = Contact::withoutGlobalScope('workspace')->where('user_id', $user->id)->count();
        $stats = [
            'total'   => $totalContacts,
            'biolink' => Contact::withoutGlobalScope('workspace')->where('user_id', $user->id)->whereNotNull('biolink_user_id')->count(),
        ];

        $cap = $this->planContactsCap($user);
        $usage = [
            'count'     => $totalContacts,
            'cap'       => $cap === -1 ? null : $cap,
            'unlimited' => $cap === -1,
            // Soft-warn at 90% of the cap so users get a heads-up before the
            // import or create flow blocks them outright.
            'percent'   => ($cap === -1 || $cap === 0) ? 0 : min(100, (int) floor(($totalContacts / $cap) * 100)),
        ];
        $usage['near_cap'] = !$usage['unlimited'] && $usage['percent'] >= 90 && $totalContacts < ($cap ?? 0);
        $usage['at_cap']   = !$usage['unlimited'] && $cap !== null && $totalContacts >= $cap;

        // Show an "Import in progress" banner if a queued import is still running.
        $activeImport = ContactImport::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'processing'])
            ->orderByDesc('id')
            ->first();

        // Contacts shared with the current team workspace by other members.
        // Only loaded when a non-personal workspace is active so the list
        // doesn't add overhead for personal accounts.
        $sharedContacts = collect();
        $currentWorkspace = null;
        if (app()->bound('current_workspace')) {
            $ws = app('current_workspace');
            if ($ws && !$ws->is_personal) {
                $currentWorkspace = $ws;
                $sharedContacts = ContactWorkspaceShareHelper::contactsSharedToWorkspace(
                    (int) $ws->id, (int) $user->id, $search, $tab
                );
            }
        }

        // Unified contact activity counts (Task #6501) for the visible page —
        // one grouped query per capture table against the contact_id indexes.
        $activityCounts = [];
        try {
            $activityCounts = app(\App\Modules\User\Services\Contacts\ContactActivityService::class)
                ->countsFor((int) $user->id, $contacts->pluck('id')->all());
        } catch (\Throwable) {}

        // Live as-you-type search / tab switch / pagination fetch just the list
        // body so the page never reloads. The full page is returned otherwise.
        if ($request->ajax()) {
            return view('user.contacts._list', compact('contacts', 'tab', 'search', 'tag', 'sort', 'sharedContacts', 'currentWorkspace', 'activityCounts'));
        }

        // Duplicate count for the banner — best-effort, never blocks the page
        $duplicateCount = 0;
        try {
            $duplicateCount = $this->detector->count($user->id);
        } catch (\Throwable) {}

        return view('user.contacts.index', compact('contacts', 'tab', 'search', 'tag', 'sort', 'googleAccount', 'stats', 'usage', 'activeImport', 'sharedContacts', 'currentWorkspace', 'duplicateCount', 'activityCounts'));
    }

    /**
     * Consolidated follow-ups list: every contact with a scheduled
     * follow_up_at, soonest-first, split into overdue vs upcoming so users
     * can see everything they need to act on without opening each contact.
     */
    public function followUps(Request $request)
    {
        $user = $request->user();

        // Account-wide (see index()): follow-ups span the owner's whole
        // address book, not just the active workspace.
        $contacts = Contact::withoutGlobalScope('workspace')
            ->where('user_id', $user->id)
            ->whereNotNull('follow_up_at')
            ->with(['phones', 'emails'])
            ->orderBy('follow_up_at')
            ->get();

        $now = now();
        $overdue  = $contacts->filter(fn ($c) => $c->follow_up_at->lte($now))->values();
        $upcoming = $contacts->filter(fn ($c) => $c->follow_up_at->gt($now))->values();

        // Inline quick-actions (Done / Snooze) refresh just the list body so
        // the page never fully reloads; the full page is returned otherwise.
        if ($request->ajax()) {
            return view('user.contacts._follow_ups_body', compact('overdue', 'upcoming'));
        }

        return view('user.contacts.follow-ups', compact('overdue', 'upcoming'));
    }

    public function create(Request $request)
    {
        return view('user.contacts.create', [
            'phoneLabels' => self::PHONE_LABELS,
            'emailLabels' => self::EMAIL_LABELS,
            'prefillPhone' => $request->query('phone'),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $v = $this->validatePayload($request);

        $contact = DB::transaction(function () use ($user, $v, $request) {
            $tags = array_values(array_unique(array_filter((array) ($v['tags'] ?? []), fn ($t) => $t !== '')));
            $contact = Contact::create([
                'user_id'      => $user->id,
                'display_name' => $v['display_name'] ?: trim(($v['given_name'] ?? '') . ' ' . ($v['family_name'] ?? '')),
                'given_name'   => $v['given_name'] ?? null,
                'family_name'  => $v['family_name'] ?? null,
                'organization' => $v['organization'] ?? null,
                'job_title'    => $v['job_title'] ?? null,
                'notes'        => $v['notes'] ?? null,
                'tags'         => $tags ?: null,
                'photo_path'   => $request->hasFile('photo') ? $request->file('photo')->store('contact-photos', 'public') : null,
                'locally_modified_at' => now(),
            ]);
            $this->syncRows($contact, $v['phones'] ?? [], $v['emails'] ?? []);
            return $contact;
        });

        $this->resolver->resolveFor($contact->fresh('phones'));
        $this->pushToGoogleSafely($user->id, $contact);

        $redirect = redirect()->route('user.contacts.show', $contact)->with('success', 'Contact added.');
        if ($this->detector->contactHasDuplicate($contact->user_id, $contact->id)) {
            $redirect->with('duplicate_notice', 'This contact looks like a duplicate of an existing contact.');
        }
        return $redirect;
    }

    public function show(Request $request, Contact $contact)
    {
        $user = $request->user();
        $this->authorizeContactView($contact, $user);
        $contact->load(['phones', 'emails', 'biolinkUser', 'user']);
        $biolinkPreview = $this->biolinkPreview($contact);

        // Workspace sharing context for the share/unshare UI panel.
        $shareContext = $this->buildShareContext($contact, $user);

        // Unified contact activity (Task #6501): grouped cross-feature
        // history + read-side follower bridge.
        $activityService = app(\App\Modules\User\Services\Contacts\ContactActivityService::class);
        $activityGroups = $activityService->timeline($contact);
        $followerBridge = $activityService->followerBridge($contact);

        // Recent merges into this contact that can still be undone.
        $undoableMerges = collect();
        if ($contact->user_id === workspace_owner_id()) {
            try {
                $undoableMerges = \App\Modules\User\Models\ContactMergeAudit::query()
                    ->where('user_id', $contact->user_id)
                    ->where('primary_contact_id', $contact->id)
                    ->undoable()
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get();
            } catch (\Throwable) {}
        }

        return view('user.contacts.show', compact('contact', 'biolinkPreview', 'shareContext', 'activityGroups', 'followerBridge', 'undoableMerges'));
    }

    public function edit(Request $request, Contact $contact)
    {
        $user = $request->user();
        $this->authorizeContactEdit($contact, $user);
        $contact->load(['phones', 'emails']);
        return view('user.contacts.edit', [
            'contact'     => $contact,
            'phoneLabels' => self::PHONE_LABELS,
            'emailLabels' => self::EMAIL_LABELS,
        ]);
    }

    public function update(Request $request, Contact $contact)
    {
        $this->authorizeContactEdit($contact, $request->user());
        $v = $this->validatePayload($request);

        DB::transaction(function () use ($contact, $v, $request) {
            $tags = array_values(array_unique(array_filter((array) ($v['tags'] ?? []), fn ($t) => $t !== '')));
            $payload = [
                'display_name' => $v['display_name'] ?: trim(($v['given_name'] ?? '') . ' ' . ($v['family_name'] ?? '')),
                'given_name'   => $v['given_name'] ?? null,
                'family_name'  => $v['family_name'] ?? null,
                'organization' => $v['organization'] ?? null,
                'job_title'    => $v['job_title'] ?? null,
                'notes'        => $v['notes'] ?? null,
                'tags'         => $tags ?: null,
            ];
            if ($request->boolean('remove_photo') && $contact->photo_path) {
                Storage::disk('public')->delete($contact->photo_path);
                $payload['photo_path'] = null;
            } elseif ($request->hasFile('photo')) {
                if ($contact->photo_path) Storage::disk('public')->delete($contact->photo_path);
                $payload['photo_path'] = $request->file('photo')->store('contact-photos', 'public');
            }
            $payload['locally_modified_at'] = now();
            $contact->update($payload);
            $this->syncRows($contact, $v['phones'] ?? [], $v['emails'] ?? []);
        });

        $this->resolver->resolveFor($contact->fresh('phones'));
        $this->pushToGoogleSafely($contact->user_id, $contact);

        $redirect = redirect()->route('user.contacts.show', $contact)->with('success', 'Contact updated.');
        if ($this->detector->contactHasDuplicate($contact->user_id, $contact->id)) {
            $redirect->with('duplicate_notice', 'This edit makes the contact match an existing contact.');
        }
        return $redirect;
    }

    public function destroy(Request $request, Contact $contact)
    {
        // Only the contact owner (or workspace owner via legacy check) can delete.
        // Workspace members with edit access can edit shared contacts but not delete them.
        abort_if($contact->user_id !== workspace_owner_id(), 403);
        if ($contact->photo_path) Storage::disk('public')->delete($contact->photo_path);
        // Park a deletion tombstone before removing the row so the next sync
        // can finalise it on Google. Best-effort immediate attempt too — but
        // the tombstone is the source of truth and gets retried on failure.
        $tombstone = null;
        if ($contact->google_contacts_account_id && $contact->google_resource_name) {
            $tombstone = ContactDeletionTombstone::create([
                'user_id'                    => $contact->user_id,
                'google_contacts_account_id' => $contact->google_contacts_account_id,
                'google_resource_name'       => $contact->google_resource_name,
            ]);
        }
        $contact->delete();
        $this->deleteFromGoogleSafely($tombstone);
        return redirect()->route('user.contacts.index')->with('success', 'Contact deleted.');
    }

    public function detachBiolink(Request $request, Contact $contact)
    {
        $this->authorizeContactEdit($contact, $request->user());
        if ($contact->biolink_user_id) {
            $this->resolver->detach($contact, $contact->biolink_user_id);
        }
        return back()->with('success', 'Link in Bio removed from this contact.');
    }

    public function attachBiolink(Request $request, Contact $contact)
    {
        $this->authorizeContactEdit($contact, $request->user());
        // Force a re-resolve clearing the detach marker for any user that the
        // current phones now resolve to.
        $contact->loadMissing('phones');
        foreach ($contact->phones as $p) {
            $needle = $p->value_e164 ?: ContactPhone::normalize($p->value);
            $matched = LinkedIdentifier::resolveUser('phone', $needle);
            if (!$matched || $matched->id === $contact->user_id) continue;
            $list = collect($contact->detached_biolink_user_ids ?? [])->reject(fn($id) => $id === $matched->id)->values()->all();
            $contact->forceFill(['detached_biolink_user_ids' => $list])->save();
        }
        $this->resolver->resolveFor($contact->fresh('phones'));
        return back()->with('success', 'Link in Bio reattached if a matching Sayzio user was found.');
    }

    /**
     * Set (or reschedule) a follow-up reminder for this contact/lead
     * (Task #3524). `follow_up_at` is a datetime-local string interpreted in
     * the picked timezone (`follow_up_tz`, Task #3526) — falling back to the
     * signed-in user's account timezone — and stored as an absolute UTC
     * instant. Clearing/resetting always resets the notified stamp so an
     * edited reminder fires again.
     */
    public function setFollowUp(Request $request, Contact $contact)
    {
        $this->authorizeContactEdit($contact, $request->user());
        $v = $request->validate([
            'follow_up_at'   => ['required', 'date'],
            'follow_up_note' => ['nullable', 'string', 'max:2000'],
            'follow_up_tz'   => ['nullable', 'string', 'timezone'],
            'restore'        => ['sometimes', 'boolean'],
        ]);

        $tz = $v['follow_up_tz'] ?? ($request->user()->timezone ?? config('app.timezone'));
        $at = \Illuminate\Support\Carbon::parse($v['follow_up_at'], $tz)->utc();

        // Undo of an accidentally-cleared reminder may restore a moment that is
        // already in the past (e.g. an overdue follow-up). Allow that when the
        // caller is explicitly restoring, but keep the future-only rule for
        // fresh sets and snoozes.
        if (!$request->boolean('restore') && $at->isPast()) {
            if ($request->ajax()) {
                abort(422, 'Follow-up time must be in the future.');
            }
            return back()->withErrors(['follow_up_at' => 'Follow-up time must be in the future.'])->withInput();
        }

        $contact->update([
            'follow_up_at'          => $at,
            'follow_up_note'        => $v['follow_up_note'] ?? null,
            'follow_up_tz'          => $v['follow_up_tz'] ?? null,
            'follow_up_notified_at' => null,
        ]);

        if ($request->ajax()) {
            return response()->json(['data' => ['follow_up_at' => $contact->follow_up_at->toIso8601String(), 'follow_up_note' => $contact->follow_up_note, 'follow_up_tz' => $contact->follow_up_tz]]);
        }
        return back()->with('success', 'Follow-up reminder set.');
    }

    /**
     * Return the authenticated user's distinct contact tags for autocomplete.
     * Returns an alphabetically-sorted unique list. Only tags that already
     * exist on at least one of their contacts are returned; there is no
     * global tag library.
     */
    public function allTags(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $rows = Contact::withoutGlobalScope('workspace')
            ->where('user_id', $user->id)
            ->whereNotNull('tags')
            ->pluck('tags');

        $tags = $rows->flatMap(fn ($t) => (array) $t)
            ->filter(fn ($t) => is_string($t) && $t !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();

        return response()->json(['data' => $tags]);
    }

    /**
     * Quick inline-patch for the notes field only — used by the show page
     * AJAX editor so the user never has to leave the contact detail view.
     * Accepts `notes` (nullable) and returns the fresh value.
     */
    public function updateNotes(Request $request, Contact $contact): \Illuminate\Http\JsonResponse
    {
        abort_if($contact->user_id !== workspace_owner_id(), 403);
        $v = $request->validate(['notes' => ['nullable', 'string', 'max:5000']]);
        $contact->update(['notes' => $v['notes'] ?? null]);
        return response()->json(['data' => ['notes' => $contact->notes]]);
    }

    /**
     * Quick inline-patch for the tags field — replaces the tag list with the
     * submitted array. Used by the tag chip editor on the show page and the
     * list-row tag management UI.
     */
    public function updateTags(Request $request, Contact $contact): \Illuminate\Http\JsonResponse
    {
        abort_if($contact->user_id !== workspace_owner_id(), 403);
        $v = $request->validate([
            'tags'   => ['nullable', 'array', 'max:50'],
            'tags.*' => ['required', 'string', 'max:80'],
        ]);
        $tags = array_values(array_unique(array_filter((array) ($v['tags'] ?? []), fn ($t) => $t !== '')));
        $contact->update(['tags' => $tags ?: null]);
        return response()->json(['data' => ['tags' => $contact->fresh()->tags ?? []]]);
    }

    /** Clear a scheduled follow-up reminder without firing it. */
    public function clearFollowUp(Request $request, Contact $contact)
    {
        $this->authorizeContactEdit($contact, $request->user());
        $contact->update([
            'follow_up_at'          => null,
            'follow_up_note'        => null,
            'follow_up_tz'          => null,
            'follow_up_notified_at' => null,
        ]);

        if ($request->ajax()) {
            return response()->json(['data' => ['cleared' => true]]);
        }
        return back()->with('success', 'Follow-up reminder cleared.');
    }

    // ---- duplicate detection & merge -------------------------------------

    /**
     * Duplicate review page: show all undismissed duplicate groups with
     * side-by-side contact cards so the user can pick a primary and merge.
     */
    public function duplicates(Request $request)
    {
        $userId = workspace_owner_id();
        $rawGroups = $this->detector->detect($userId);

        // Load full contact models for each group
        $allIds = collect($rawGroups)->flatMap(fn ($g) => $g['ids'])->unique()->all();
        $contactMap = Contact::withoutGlobalScope('workspace')
            ->where('user_id', $userId)
            ->with(['phones', 'emails'])
            ->whereIn('id', $allIds)
            ->get()
            ->keyBy('id');

        $groups = [];
        foreach ($rawGroups as $g) {
            $contacts = array_values(array_filter(
                array_map(fn ($id) => $contactMap->get($id), $g['ids']),
                fn ($c) => $c !== null
            ));
            if (count($contacts) < 2) continue;
            $groups[] = [
                'ids'      => $g['ids'],
                'reason'   => $g['reason'],
                'contacts' => array_map(fn ($c) => [
                    'id'           => $c->id,
                    'display_name' => $c->nameForDisplay(),
                    'organization' => $c->organization,
                    'notes'        => $c->notes,
                    'photo_url'    => $c->photoUrl(),
                    'phones'       => $c->phones->map(fn ($p) => ['value' => $p->value, 'label' => $p->label])->all(),
                    'emails'       => $c->emails->map(fn ($e) => ['value' => $e->value, 'label' => $e->label])->all(),
                ], $contacts),
            ];
        }

        $groupCount = count($groups);

        // Recently merged contacts that can still be undone — surfaced here
        // so an accidental merge can be reversed right where it happened.
        $undoableMerges = collect();
        try {
            $undoableMerges = \App\Modules\User\Models\ContactMergeAudit::query()
                ->where('user_id', $userId)
                ->undoable()
                ->orderByDesc('id')
                ->limit(10)
                ->get();
        } catch (\Throwable) {}

        return view('user.contacts.duplicates', compact('groups', 'groupCount', 'undoableMerges'));
    }

    /**
     * Undo a recent contact merge: recreates the merged-away contact from
     * the audit snapshot and repoints the recorded rows back to it.
     *
     * POST /contacts/merges/{audit}/undo
     * Owner-safe (audit must belong to the workspace owner), idempotent
     * (the undo service locks + stamps the audit row) and time-limited
     * (ContactMergeAudit::UNDO_WINDOW_DAYS).
     */
    public function undoMerge(Request $request, int $audit)
    {
        $row = \App\Modules\User\Models\ContactMergeAudit::query()
            ->whereKey($audit)
            ->where('user_id', workspace_owner_id())
            ->first();
        abort_if(!$row, 404);

        try {
            $restored = app(\App\Modules\User\Services\Contacts\ContactMergeUndoService::class)->undo($row);
        } catch (\Throwable $e) {
            \Log::warning('ContactController::undoMerge failed', ['audit' => $row->id, 'err' => $e->getMessage()]);
            return back()->with('error', 'Could not undo the merge: ' . $e->getMessage());
        }

        return redirect()->route('user.contacts.show', $restored)
            ->with('success', 'Merge undone — "' . ($restored->nameForDisplay() ?: 'contact') . '" has been restored with its activity.');
    }

    /**
     * Dismiss one or more contact pairs so they never re-surface as duplicates.
     * Accepts `pairs[]` = "idA:idB" strings (canonical min:max order).
     */
    public function duplicatesDismiss(Request $request)
    {
        $request->validate([
            'pairs'   => 'required|array|min:1|max:100',
            'pairs.*' => 'string',
        ]);

        $userId = workspace_owner_id();
        $now    = now();

        foreach ($request->input('pairs', []) as $pair) {
            if (!preg_match('/^(\d+):(\d+)$/', (string) $pair, $m)) continue;
            $a = (int) min($m[1], $m[2]);
            $b = (int) max($m[1], $m[2]);
            if ($a === $b) continue;

            // Verify both contacts belong to this user
            $count = Contact::withoutGlobalScope('workspace')
                ->where('user_id', $userId)
                ->whereIn('id', [$a, $b])
                ->count();
            if ($count < 2) continue;

            DB::table('contact_dismissed_pairs')->upsert(
                [['user_id' => $userId, 'contact_id_a' => $a, 'contact_id_b' => $b, 'dismissed_at' => $now]],
                ['user_id', 'contact_id_a', 'contact_id_b'],
                ['dismissed_at']
            );
        }

        // Dismissed pairs change the group count but bypass model events
        // (raw upsert), so invalidate the cached badge count explicitly.
        ContactDuplicateDetector::flushCountCache($userId);

        return redirect()->route('user.contacts.duplicates')
            ->with('success', 'Marked as not duplicates — they won\'t appear here again.');
    }

    /**
     * Lightweight JSON count of undismissed duplicate groups — used by the
     * contacts index to refresh the banner without a full page reload
     * (e.g. after returning via the browser back button).
     */
    public function duplicatesCount(Request $request): \Illuminate\Http\JsonResponse
    {
        $count = 0;
        try {
            $count = $this->detector->count(workspace_owner_id());
        } catch (\Throwable) {}
        return response()->json(['data' => ['count' => $count]]);
    }

    /**
     * Merge loser contacts into the chosen primary.
     *
     * POST /contacts/{contact}/merge-duplicate
     * Body: loser_ids[] — IDs of contacts to absorb into {contact}.
     * The primary is the route-model-bound {contact}; loser_ids should
     * include ALL contacts in the group (primary excluded server-side).
     */
    public function mergeContacts(Request $request, Contact $contact)
    {
        abort_if($contact->user_id !== workspace_owner_id(), 403);

        $request->validate([
            'loser_ids'   => 'required|array|min:1|max:50',
            'loser_ids.*' => 'integer',
        ]);

        $userId   = workspace_owner_id();
        $loserIds = array_filter(
            array_map('intval', $request->input('loser_ids', [])),
            fn ($id) => $id !== $contact->id
        );

        if (empty($loserIds)) {
            return redirect()->route('user.contacts.duplicates')
                ->with('error', 'No contacts to merge — select at least one non-primary contact.');
        }

        $losers = Contact::withoutGlobalScope('workspace')
            ->where('user_id', $userId)
            ->whereIn('id', $loserIds)
            ->get()
            ->all();

        if (empty($losers)) {
            return redirect()->route('user.contacts.duplicates')
                ->with('error', 'Could not find the contacts to merge.');
        }

        try {
            $this->mergeService->merge($contact, $losers);
        } catch (\Throwable $e) {
            \Log::warning('ContactController::mergeContacts failed', ['err' => $e->getMessage()]);
            return redirect()->route('user.contacts.duplicates')
                ->with('error', 'Merge failed: ' . $e->getMessage());
        }

        $merged = count($losers);
        return redirect()->route('user.contacts.show', $contact)
            ->with('success', "Merged {$merged} contact" . ($merged === 1 ? '' : 's') . ' into this one — no data was lost.');
    }

    /**
     * Bulk-merge every duplicate group in one action.
     *
     * POST /contacts/duplicates/merge-all
     * For each detected group the first contact becomes the primary and the
     * rest are merged into it (same semantics as a per-group merge). Each
     * group is merged in its own transaction via ContactMergeService, so a
     * failure in one group doesn't roll back the others.
     */
    public function mergeAllDuplicates(Request $request)
    {
        $userId    = workspace_owner_id();
        $rawGroups = $this->detector->detect($userId);

        if (empty($rawGroups)) {
            return redirect()->route('user.contacts.duplicates')
                ->with('error', 'No duplicate groups to merge.');
        }

        [$mergedGroups, $removedContacts, $failed] = $this->mergeAllGroups($userId, $rawGroups);

        if ($mergedGroups === 0) {
            return redirect()->route('user.contacts.duplicates')
                ->with('error', 'Could not merge any duplicate groups.');
        }

        $msg = "Merged {$mergedGroups} group" . ($mergedGroups === 1 ? '' : 's')
             . " — {$removedContacts} duplicate contact" . ($removedContacts === 1 ? '' : 's') . ' removed.';
        if ($failed > 0) {
            $msg .= " {$failed} group" . ($failed === 1 ? '' : 's') . ' could not be merged.';
        }

        return redirect()->route('user.contacts.duplicates')->with('success', $msg);
    }

    /**
     * Shared bulk-merge loop: merges each group's tail contacts into its
     * first contact. Returns [groupsMerged, contactsRemoved, groupsFailed].
     *
     * @param array $rawGroups Output of ContactDuplicateDetector::detect().
     */
    protected function mergeAllGroups(int $userId, array $rawGroups): array
    {
        $mergedGroups    = 0;
        $removedContacts = 0;
        $failed          = 0;
        // A contact may appear in more than one group (e.g. same phone AND
        // same name); once merged away it must not be reused as a primary
        // or loser in a later group.
        $consumed = [];

        foreach ($rawGroups as $g) {
            $ids = array_values(array_filter(
                array_map('intval', $g['ids'] ?? []),
                fn ($id) => !isset($consumed[$id])
            ));
            if (count($ids) < 2) continue;

            $primaryId = array_shift($ids);
            $primary = Contact::withoutGlobalScope('workspace')
                ->where('user_id', $userId)
                ->find($primaryId);
            if (!$primary) continue;

            $losers = Contact::withoutGlobalScope('workspace')
                ->where('user_id', $userId)
                ->whereIn('id', $ids)
                ->get()
                ->all();
            if (empty($losers)) continue;

            try {
                $this->mergeService->merge($primary, $losers);
            } catch (\Throwable $e) {
                \Log::warning('ContactController::mergeAllDuplicates group failed', [
                    'user' => $userId, 'primary' => $primaryId, 'err' => $e->getMessage(),
                ]);
                $failed++;
                continue;
            }

            $mergedGroups++;
            $removedContacts += count($losers);
            foreach ($losers as $l) $consumed[$l->id] = true;
        }

        return [$mergedGroups, $removedContacts, $failed];
    }

    /**
     * JSON candidate list for the "Merge into…" picker on the contact page.
     *
     * GET /contacts/{contact}/merge-candidates?q=…
     * Returns up to 20 of the owner's other contacts matching the query
     * (name, organization, email or phone), never the contact itself.
     */
    public function mergeCandidates(Request $request, Contact $contact): \Illuminate\Http\JsonResponse
    {
        abort_if($contact->user_id !== workspace_owner_id(), 403);

        $search = trim((string) $request->query('q', ''));

        $query = Contact::withoutGlobalScope('workspace')
            ->where('user_id', workspace_owner_id())
            ->where('id', '!=', $contact->id)
            ->with(['phones', 'emails']);

        if ($search !== '') {
            $needle = '%' . $search . '%';
            $phoneNeedle = '%' . ContactPhone::normalize($search) . '%';
            $query->where(function ($q) use ($needle, $phoneNeedle) {
                $q->where('display_name', 'ilike', $needle)
                  ->orWhere('given_name', 'ilike', $needle)
                  ->orWhere('family_name', 'ilike', $needle)
                  ->orWhere('organization', 'ilike', $needle)
                  ->orWhereHas('phones', fn ($q2) => $q2->where('value_e164', 'ilike', $phoneNeedle))
                  ->orWhereHas('emails', fn ($q2) => $q2->where('value', 'ilike', $needle));
            });
        }

        $candidates = $query->orderBy('display_name')->limit(20)->get()->map(fn ($c) => [
            'id'               => $c->id,
            'display_name'     => $c->nameForDisplay(),
            'organization'     => $c->organization,
            'photo_url'        => $c->photoUrl(),
            'is_auto_captured' => (bool) $c->is_auto_captured,
            'email'            => optional($c->emails->first())->value,
            'phone'            => optional($c->phones->first())->value,
        ])->values();

        return response()->json(['data' => ['candidates' => $candidates]]);
    }

    /**
     * Merge {contact} INTO another contact picked by the user — the inverse
     * direction of mergeContacts(). {contact} is the duplicate that gets
     * absorbed and deleted; target_id is the contact that survives with all
     * emails/phones and repointed capture rows (subscribers, form
     * submissions, orders, bookings, RSVPs, tickets, reviews, threads).
     *
     * POST /contacts/{contact}/merge-into
     * Body: target_id — the surviving contact's id.
     */
    public function mergeInto(Request $request, Contact $contact)
    {
        abort_if($contact->user_id !== workspace_owner_id(), 403);

        $request->validate([
            'target_id' => 'required|integer',
        ]);

        $targetId = (int) $request->input('target_id');
        if ($targetId === $contact->id) {
            return redirect()->route('user.contacts.show', $contact)
                ->with('error', 'A contact cannot be merged into itself.');
        }

        $target = Contact::withoutGlobalScope('workspace')
            ->where('user_id', workspace_owner_id())
            ->find($targetId);

        if (!$target) {
            return redirect()->route('user.contacts.show', $contact)
                ->with('error', 'Could not find the contact to merge into.');
        }

        try {
            $this->mergeService->merge($target, [$contact]);
        } catch (\Throwable $e) {
            \Log::warning('ContactController::mergeInto failed', ['err' => $e->getMessage()]);
            return redirect()->route('user.contacts.show', $contact)
                ->with('error', 'Merge failed: ' . $e->getMessage());
        }

        return redirect()->route('user.contacts.show', $target)
            ->with('success', 'Merged "' . ($contact->nameForDisplay() ?: 'contact') . '" into this contact — no data was lost.');
    }

    // ---- bulk import ------------------------------------------------------

    public function importForm(Request $request)
    {
        $cap = $this->planContactsCap($request->user());
        // Account-wide count (see index()) so the plan-cap remaining figure
        // matches the address book the user actually sees.
        $existing = Contact::withoutGlobalScope('workspace')->where('user_id', workspace_owner_id())->count();
        return view('user.contacts.import', [
            'softCap'   => $cap === -1 ? null : $cap,
            'remaining' => $cap === -1 ? null : max(0, $cap - $existing),
        ]);
    }

    /** Parsed-row count above which we punt to a queued job rather than process inline. */
    public const ASYNC_THRESHOLD = 200;

    public function import(Request $request)
    {
        $request->validate([
            // CSV/vCard text files; cap at 5MB to keep parsing predictable.
            'file' => 'required|file|max:5120|mimes:csv,txt,vcf,vcard',
        ]);

        $user = $request->user();
        // Plan-level cap is enforced by CheckPlanLimit middleware and again
        // at importConfirm; nothing to compute here for the preview stage.

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        try {
            $rows = $this->importParser->parse($file->getRealPath(), $originalName);
        } catch (\Throwable $e) {
            \Log::warning('Contact import: parse failed', [
                'user' => $user->id, 'name' => $originalName, 'err' => $e->getMessage(),
            ]);
            return back()->with('error', 'We couldn\'t read that file. Make sure it\'s a valid CSV or vCard (.vcf) and try again.');
        }

        // Annotate each parsed row with any per-row warnings so the preview
        // can flag mistakes (bad email, no usable data, etc.) before commit.
        $rows = array_map(fn ($r) => $r + ['warnings' => $this->rowWarnings($r)], $rows);

        // Stash to a per-user temp file rather than the DB; this gets cleaned
        // up on confirm/cancel and is not visible anywhere else in the UI.
        $token = bin2hex(random_bytes(16));
        Storage::disk('local')->put($this->stashPath($user->id, $token), json_encode([
            'original_name' => $originalName,
            'created_at'    => now()->toIso8601String(),
            'rows'          => $rows,
        ]));

        return redirect()->route('user.contacts.import.preview', ['token' => $token]);
    }


    public function importPreview(Request $request, string $token)
    {
        $user = $request->user();
        $stash = $this->loadStash($user->id, $token);
        if (!$stash) {
            return redirect()->route('user.contacts.import')
                ->with('error', 'That preview is no longer available. Please re-upload the file.');
        }

        $rows = $stash['rows'];
        $perPage = 25;
        $page = max(1, (int) $request->query('page', 1));
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            array_slice($rows, ($page - 1) * $perPage, $perPage),
            count($rows),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $existingCount = Contact::withoutGlobalScope('workspace')->where('user_id', $user->id)->count();
        $cap = $this->planContactsCap($user);
        $remaining = $cap === -1 ? null : max(0, $cap - $existingCount);
        $overCap = $cap === -1 ? 0 : max(0, count($rows) - ($remaining ?? 0));

        $stats = [
            'total'    => count($rows),
            'warnings' => count(array_filter($rows, fn ($r) => !empty($r['warnings']))),
            'remaining'=> $remaining,
            'overCap'  => $overCap,
        ];

        return view('user.contacts.import_preview', [
            'token'        => $token,
            'originalName' => $stash['original_name'] ?? 'upload',
            'rows'         => $paginator,
            'stats'        => $stats,
        ]);
    }

    public function importRowUpdate(Request $request, string $token, int $index)
    {
        $user = $request->user();
        $stash = $this->loadStash($user->id, $token);
        if (!$stash) {
            return redirect()->route('user.contacts.import')
                ->with('error', 'That preview is no longer available. Please re-upload the file.');
        }

        $rows = $stash['rows'] ?? [];
        if (!isset($rows[$index]) || !is_array($rows[$index])) {
            abort(404);
        }

        // Use string rules for phone/email values here so the user can save
        // their fix even if it's still malformed; the row warnings will flag
        // it and importConfirm validates strictly before creating contacts.
        $v = $request->validate([
            'display_name' => 'nullable|string|max:191',
            'given_name'   => 'nullable|string|max:191',
            'family_name'  => 'nullable|string|max:191',
            'organization' => 'nullable|string|max:191',
            'phones'                 => 'nullable|array|max:10',
            'phones.*.label'         => 'nullable|string|max:50',
            'phones.*.value'         => 'nullable|string|max:80',
            'emails'                 => 'nullable|array|max:10',
            'emails.*.label'         => 'nullable|string|max:50',
            'emails.*.value'         => 'nullable|string|max:191',
        ]);

        $clean = function (array $items, string $valKey = 'value'): array {
            $out = [];
            foreach ($items as $it) {
                $val = trim((string) ($it[$valKey] ?? ''));
                if ($val === '') continue;
                $out[] = [
                    'label' => trim((string) ($it['label'] ?? '')) ?: null,
                    'value' => $val,
                ];
            }
            return $out;
        };

        $existing = $rows[$index];
        $updated = array_merge($existing, [
            'display_name' => trim((string) ($v['display_name'] ?? '')) ?: null,
            'given_name'   => trim((string) ($v['given_name'] ?? '')) ?: null,
            'family_name'  => trim((string) ($v['family_name'] ?? '')) ?: null,
            'organization' => trim((string) ($v['organization'] ?? '')) ?: null,
            'phones'       => $clean($v['phones'] ?? []),
            'emails'       => $clean($v['emails'] ?? []),
        ]);
        // Re-run per-row warnings so the preview updates immediately and the
        // "warnings" stat at the top reflects the user's fixes.
        $updated['warnings'] = $this->rowWarnings($updated);

        $rows[$index] = $updated;
        Storage::disk('local')->put($this->stashPath($user->id, $token), json_encode([
            'original_name' => $stash['original_name'] ?? null,
            'created_at'    => $stash['created_at'] ?? now()->toIso8601String(),
            'rows'          => $rows,
        ]));

        $page = max(1, (int) $request->query('page', 1));
        return redirect()->route('user.contacts.import.preview', ['token' => $token, 'page' => $page])
            ->with('success', 'Row updated.');
    }

    public function importRowSkip(Request $request, string $token, int $index)
    {
        $user = $request->user();
        $stash = $this->loadStash($user->id, $token);
        if (!$stash) {
            return redirect()->route('user.contacts.import')
                ->with('error', 'That preview is no longer available. Please re-upload the file.');
        }

        $rows = $stash['rows'] ?? [];
        if (!isset($rows[$index]) || !is_array($rows[$index])) {
            abort(404);
        }

        // Drop the row entirely from the stash and re-key so the remaining
        // rows keep contiguous indexes (the edit/skip routes index by offset).
        array_splice($rows, $index, 1);

        Storage::disk('local')->put($this->stashPath($user->id, $token), json_encode([
            'original_name' => $stash['original_name'] ?? null,
            'created_at'    => $stash['created_at'] ?? now()->toIso8601String(),
            'rows'          => $rows,
        ]));

        // If the page the user was on is now past the end (e.g. they skipped
        // the last row on the last page), step back one page so the redirect
        // lands on something useful.
        $perPage = 25;
        $page = max(1, (int) $request->query('page', 1));
        $maxPage = max(1, (int) ceil(count($rows) / $perPage));
        if ($page > $maxPage) $page = $maxPage;

        return redirect()->route('user.contacts.import.preview', ['token' => $token, 'page' => $page])
            ->with('success', 'Row skipped — it won\'t be imported.');
    }

    public function importCancel(Request $request, string $token)
    {
        $this->discardStash(workspace_owner_id(), $token);
        return redirect()->route('user.contacts.import')
            ->with('success', 'Import cancelled — nothing was added.');
    }

    public function importConfirm(Request $request, string $token)
    {
        $user = $request->user();
        // Atomically "claim" the stash before processing so a double-submit
        // (browser back, refresh, double-click) can't import the same rows
        // twice. rename() is atomic on POSIX; only one caller wins.
        $stash = $this->claimStash($user->id, $token);
        if (!$stash) {
            return redirect()->route('user.contacts.import')
                ->with('error', 'That preview is no longer available. Please re-upload the file.');
        }

        $rows = $stash['rows'];
        $originalName = $stash['original_name'] ?? 'upload';
        $existingCount = Contact::withoutGlobalScope('workspace')->where('user_id', $user->id)->count();
        $cap = $this->planContactsCap($user);
        $remaining = $cap === -1 ? null : max(0, $cap - $existingCount);

        // Large lists go to a queued worker so the user isn't held inside a
        // synchronous PHP timeout while we build thousands of rows + push to
        // Google. The summary page polls the persisted ContactImport row.
        if (count($rows) > self::ASYNC_THRESHOLD) {
            $import = ContactImport::create([
                'user_id'           => $user->id,
                'original_filename' => $originalName,
                'status'            => 'pending',
                'total_rows'        => count($rows),
                'rows'              => $rows,
            ]);
            ProcessContactImportJob::dispatch($import->id);
            return redirect()->route('user.contacts.import.show', $import)
                ->with('success', 'Import queued — we\'ll add the rows in the background.');
        }

        $results = [
            'total'      => count($rows),
            'created'    => 0,
            'failed'     => [],
            'skippedCap' => 0,
        ];

        foreach ($rows as $i => $row) {
            // Prefer the parser's source_line so error messages point at the
            // user's actual file (CSV header offset, vCard BEGIN: blocks).
            $rowNum = $row['source_line'] ?? ($i + 1);
            $label  = $row['display_name'] ?: trim(($row['given_name'] ?? '') . ' ' . ($row['family_name'] ?? ''));
            $label  = $label !== '' ? $label : ('Row ' . $rowNum);

            if ($cap !== -1 && $existingCount + $results['created'] >= $cap) {
                $results['skippedCap']++;
                continue;
            }

            $payload = [
                'display_name' => $row['display_name'] ?: trim(($row['given_name'] ?? '') . ' ' . ($row['family_name'] ?? '')),
                'given_name'   => $row['given_name'] ?? null,
                'family_name'  => $row['family_name'] ?? null,
                'organization' => $row['organization'] ?? null,
                'job_title'    => $row['job_title']   ?? null,
                'notes'        => $row['notes']        ?? null,
                'tags'         => $row['tags']         ?? null,
                'phones'       => $row['phones'] ?? [],
                'emails'       => $row['emails'] ?? [],
            ];

            // Reuse the same validation rules as manual create so failures
            // show up identically (e.g. invalid email).
            $v = validator($payload, [
                'display_name' => 'nullable|string|max:191',
                'given_name'   => 'nullable|string|max:191',
                'family_name'  => 'nullable|string|max:191',
                'organization' => 'nullable|string|max:191',
                'job_title'    => 'nullable|string|max:191',
                'notes'        => 'nullable|string|max:5000',
                'tags'         => 'nullable|array',
                'phones'                 => 'nullable|array|max:10',
                'phones.*.label'         => 'nullable|string|max:50',
                'phones.*.value'         => 'nullable|string|max:80',
                'emails'                 => 'nullable|array|max:10',
                'emails.*.label'         => 'nullable|string|max:50',
                'emails.*.value'         => 'nullable|email|max:191',
            ]);
            if ($v->fails()) {
                $results['failed'][] = [
                    'row'    => $rowNum,
                    'name'   => $label,
                    'reason' => $v->errors()->first(),
                ];
                continue;
            }

            $hasAnything = $payload['display_name'] || $payload['given_name'] || $payload['family_name']
                || !empty($payload['phones']) || !empty($payload['emails']);
            if (!$hasAnything) {
                $results['failed'][] = [
                    'row' => $rowNum, 'name' => $label,
                    'reason' => 'No name, phone, or email found.',
                ];
                continue;
            }

            try {
                $contact = DB::transaction(function () use ($user, $payload) {
                    $c = Contact::create([
                        'user_id'      => $user->id,
                        'display_name' => $payload['display_name'] ?: trim(($payload['given_name'] ?? '') . ' ' . ($payload['family_name'] ?? '')),
                        'given_name'   => $payload['given_name'],
                        'family_name'  => $payload['family_name'],
                        'organization' => $payload['organization'],
                        'job_title'    => $payload['job_title'] ?? null,
                        'notes'        => $payload['notes']     ?? null,
                        'tags'         => !empty($payload['tags']) ? $payload['tags'] : null,
                        'locally_modified_at' => now(),
                    ]);
                    $this->syncRows($c, $payload['phones'], $payload['emails']);
                    return $c;
                });

                // Same post-create steps as the manual store() path so
                // biolink auto-attach and best-effort Google push still run.
                $this->resolver->resolveFor($contact->fresh('phones'));
                $this->pushToGoogleSafely($user->id, $contact);

                $results['created']++;
            } catch (\Throwable $e) {
                $results['failed'][] = [
                    'row'    => $rowNum,
                    'name'   => $label,
                    'reason' => \Illuminate\Support\Str::limit($e->getMessage(), 200),
                ];
            }
        }

        // Stash was already removed by claimStash(); nothing to clean up.
        // Persist the result so users can revisit it from history. The
        // summary view (Blade) drives off this row for both inline and
        // queued imports, so the inline path also writes here.
        $import = ContactImport::create([
            'user_id'           => $user->id,
            'original_filename' => $originalName,
            'status'            => 'completed',
            'total_rows'        => $results['total'],
            'processed_rows'    => $results['total'],
            'created_count'     => $results['created'],
            'skipped_cap_count' => $results['skippedCap'],
            'failed'            => $results['failed'],
            'started_at'        => now(),
            'completed_at'      => now(),
        ]);

        return redirect()->route('user.contacts.import.show', $import);
    }

    /** Persisted summary page — works for both inline and queued imports. */
    public function importShow(Request $request, ContactImport $import)
    {
        abort_if($import->user_id !== workspace_owner_id(), 403);
        $duplicateCount = null;
        if ($import->status === 'completed') {
            try {
                $duplicateCount = $this->detector->count(workspace_owner_id());
            } catch (\Throwable) {
                // non-fatal: detection may fail on missing pg_trgm or new env
            }
        }
        return view('user.contacts.import_summary', compact('import', 'duplicateCount'));
    }

    /** Tiny JSON endpoint the summary page polls while a job is running. */
    public function importStatus(Request $request, ContactImport $import)
    {
        abort_if($import->user_id !== workspace_owner_id(), 403);
        return response()->json([
            'status'         => $import->status,
            'total'          => $import->total_rows,
            'processed'      => $import->processed_rows,
            'created'        => $import->created_count,
            'failed'         => count($import->failed ?? []),
            'skipped_cap'    => $import->skipped_cap_count,
            'percent'        => $import->progressPercent(),
            'in_progress'    => $import->isInProgress(),
        ]);
    }

    /**
     * Send the contact's matched Sayzio biolink URL to one of their phone
     * numbers via a configured SMS gateway (Twilio today; other providers are
     * audit-logged until their HTTP transports are wired up). The Blade views
     * already provide a one-tap `sms:` deeplink for mobile devices — this
     * endpoint is the desktop fallback when the user has an SMS integration
     * configured.
     */
    public function smsBiolink(Request $request, Contact $contact)
    {
        $this->authorizeContactEdit($contact, $request->user());
        $contact->loadMissing(['phones', 'biolinkUser']);

        $preview = $this->biolinkPreview($contact);
        if (!$preview || empty($preview['url'])) {
            return back()->with('error', 'No Link in Bio available to text.');
        }

        $to = trim((string) $request->input('to', ''));
        if ($to === '') {
            $primary = $contact->phones->first();
            $to = $primary?->value_e164 ?: $primary?->value ?: '';
        }
        $toClean = preg_replace('/[^\d+]/', '', $to);
        if ($toClean === '' || strlen($toClean) > 20) {
            return back()->with('error', 'This contact has no valid phone number to text.');
        }

        // Lock the destination to one of the contact's saved phones so a
        // tampered hidden field cannot turn the user's SMS gateway into a
        // generic outbound texter.
        $allowed = $contact->phones
            ->flatMap(fn ($p) => array_filter([
                preg_replace('/[^\d+]/', '', (string) $p->value_e164),
                preg_replace('/[^\d+]/', '', (string) $p->value),
            ]))
            ->filter()->unique()->values()->all();
        if (!in_array($toClean, $allowed, true)) {
            return back()->with('error', 'You can only text this Link in Bio to a phone number saved on the contact.');
        }

        $userId = workspace_owner_id();
        $configId = (int) $request->input('config_id', 0);
        $config = $configId
            ? IntegrationConfig::where('user_id', $userId)->where('id', $configId)->kind('sms')->active()->first()
            : IntegrationConfig::where('user_id', $userId)->kind('sms')->active()
                ->orderByDesc('is_default')->orderBy('id')->first();

        if (!$config) {
            return back()->with('error', 'No active SMS gateway configured. Add Twilio or Plivo under Integrations, or use the Text Link in Bio button on a mobile device.');
        }

        $name    = $contact->nameForDisplay();
        $message = "Hey " . ($name ?: 'there') . ", here's my Sayzio page: " . $preview['url'];

        try {
            $this->dispatchBiolinkSms($config, $toClean, $message);
        } catch (\Throwable $e) {
            \Log::warning('Biolink SMS send failed', ['err' => $e->getMessage(), 'config_id' => $config->id]);
            return back()->with('error', 'SMS gateway rejected the send: ' . $e->getMessage());
        }

        return back()->with('success', 'Link in Bio texted to ' . $toClean . ' via ' . $config->providerLabel() . '.');
    }

    /**
     * Mirror of FormController::sendSmsViaConfig, scoped to the biolink-text
     * use case. Twilio is wired end-to-end; other providers are audit-logged
     * with a structured trail until their transports are added.
     */
    private function dispatchBiolinkSms(IntegrationConfig $config, string $toClean, string $message): void
    {
        $cred = (array) $config->credentials;
        $meta = (array) $config->meta;

        switch ($config->provider) {
            case 'twilio':
                $sid   = $meta['account_sid'] ?? null;
                $token = $cred['auth_token']  ?? null;
                $from  = $meta['from_number'] ?? null;
                if (!$sid || !$token || !$from) {
                    throw new \RuntimeException('Twilio credentials incomplete.');
                }
                \Illuminate\Support\Facades\Http::withBasicAuth($sid, $token)
                    ->asForm()->timeout(10)
                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                        'From' => $from, 'To' => $toClean, 'Body' => $message,
                    ])->throw();
                break;

            case 'plivo':
                $authId = $meta['auth_id']    ?? null;
                $token  = $cred['auth_token'] ?? null;
                $from   = $meta['from_number'] ?? null;
                if (!$authId || !$token || !$from) {
                    throw new \RuntimeException('Plivo credentials incomplete.');
                }
                \Illuminate\Support\Facades\Http::withBasicAuth($authId, $token)
                    ->asJson()->timeout(10)
                    ->post("https://api.plivo.com/v1/Account/{$authId}/Message/", [
                        'src' => $from, 'dst' => $toClean, 'text' => $message,
                    ])->throw();
                break;

            default:
                // Provider recognised in IntegrationConfigRegistry but its HTTP
                // transport isn't wired up here yet. Fail loudly so the user
                // doesn't see a green "sent" toast for a message that never
                // actually left the building.
                throw new \RuntimeException(
                    'SMS provider "' . $config->provider . '" is not yet supported for Link in Bio texting. '
                    . 'Use Twilio or Plivo, or text from a mobile device.'
                );
        }
    }

    // ---- Workspace sharing ------------------------------------------------

    /**
     * Share a contact with the currently-active (or specified) workspace.
     * Only the contact owner can initiate sharing.
     *
     * POST contacts/{contact}/share
     */
    public function share(Request $request, Contact $contact)
    {
        $user = $request->user();
        abort_if((int) $contact->user_id !== (int) workspace_owner_id(), 403);

        $wsId = (int) ($request->input('workspace_id') ?: 0);
        $ws   = $wsId ? Workspace::find($wsId) : (app()->bound('current_workspace') ? app('current_workspace') : null);

        if (!$ws || $ws->is_personal) {
            return back()->with('error', 'Select a team workspace to share this contact with.');
        }

        // Requester must belong to the target workspace.
        $isMember = ((int) $ws->owner_user_id === (int) $user->id)
            || $user->workspaceMemberships()->where('workspace_id', $ws->id)->exists();
        abort_unless($isMember, 403);

        ContactWorkspaceShareHelper::share($contact, $ws, $user);

        if ($request->ajax()) {
            return response()->json(['data' => ['shared' => true, 'workspace_id' => $ws->id]]);
        }
        return back()->with('success', 'Contact shared with "' . $ws->name . '".');
    }

    /**
     * Remove a contact's share from the specified workspace.
     * Only the contact owner or workspace owner may unshare.
     *
     * DELETE contacts/{contact}/share
     */
    public function unshare(Request $request, Contact $contact)
    {
        $user = $request->user();

        $wsId = (int) ($request->input('workspace_id') ?: 0);
        $ws   = $wsId ? Workspace::find($wsId) : (app()->bound('current_workspace') ? app('current_workspace') : null);
        if (!$ws) return back()->with('error', 'Workspace not found.');

        abort_unless(
            ContactWorkspaceShareHelper::userCanManageShare($user, $contact, $ws),
            403
        );

        ContactWorkspaceShareHelper::unshare($contact, $ws->id);

        if ($request->ajax()) {
            return response()->json(['data' => ['shared' => false, 'workspace_id' => $ws->id]]);
        }
        return back()->with('success', 'Contact removed from "' . $ws->name . '".');
    }

    /**
     * Bulk-share selected contacts with a workspace.
     * Only shares contacts that the authenticated user owns.
     *
     * POST contacts/bulk-share
     */
    public function bulkShare(Request $request)
    {
        $user = $request->user();
        $v = $request->validate([
            'contact_ids'  => ['required', 'array', 'min:1', 'max:200'],
            'contact_ids.*'=> ['integer'],
            'workspace_id' => ['required', 'integer'],
        ]);

        $ws = Workspace::find($v['workspace_id']);
        if (!$ws || $ws->is_personal) {
            return back()->with('error', 'Invalid workspace.');
        }
        $isMember = ((int) $ws->owner_user_id === (int) $user->id)
            || $user->workspaceMemberships()->where('workspace_id', $ws->id)->exists();
        abort_unless($isMember, 403);

        $owned = Contact::withoutGlobalScope('workspace')
            ->where('user_id', workspace_owner_id())
            ->whereIn('id', $v['contact_ids'])
            ->pluck('id');

        $count = 0;
        foreach ($owned as $cid) {
            $c = Contact::withoutGlobalScope('workspace')->find($cid);
            if ($c) { ContactWorkspaceShareHelper::share($c, $ws, $user); $count++; }
        }

        return back()->with('success', $count . ' contact(s) shared with "' . $ws->name . '".');
    }

    // ---- Authorization helpers --------------------------------------------

    /**
     * Allow viewing a contact if:
     *  1. The contact belongs to the workspace owner (legacy check), OR
     *  2. The contact is shared with the currently-bound workspace AND the
     *     viewer is a member of that workspace (any role with settings.view).
     */
    private function authorizeContactView(Contact $contact, $user): void
    {
        if ((int) $contact->user_id === (int) workspace_owner_id()) return;

        // Check shared access via current workspace.
        if (app()->bound('current_workspace')) {
            $ws = app('current_workspace');
            if ($ws && !$ws->is_personal) {
                $share = ContactWorkspaceShareHelper::findShare($contact->id, $ws->id);
                if ($share && ContactWorkspaceShareHelper::userCanViewShared($user, $ws)) return;
            }
        }

        abort(403);
    }

    /**
     * Allow editing a contact if:
     *  1. The contact belongs to the workspace owner (legacy check), OR
     *  2. The contact is shared with the currently-bound workspace AND the
     *     viewer has 'edit' permission (settings.edit) in that workspace.
     */
    private function authorizeContactEdit(Contact $contact, $user): void
    {
        if ((int) $contact->user_id === (int) workspace_owner_id()) return;

        if (app()->bound('current_workspace')) {
            $ws = app('current_workspace');
            if ($ws && !$ws->is_personal) {
                $share = ContactWorkspaceShareHelper::findShare($contact->id, $ws->id);
                if ($share && ContactWorkspaceShareHelper::userCanEditShared($user, $ws)) return;
            }
        }

        abort(403);
    }

    /**
     * Build workspace-sharing context for the show view.
     * Returns an array with:
     *  - is_owner: bool — the authenticated user owns this contact
     *  - is_shared_contact: bool — viewing a contact shared by someone else
     *  - shared_by: ?User — who shared it (when is_shared_contact)
     *  - current_workspace: ?Workspace
     *  - shareable_workspaces: Collection — workspaces the owner can share with
     *  - shares: Collection<ContactWorkspaceShare> — existing shares
     */
    private function buildShareContext(Contact $contact, $user): array
    {
        $ws = app()->bound('current_workspace') ? app('current_workspace') : null;
        $isOwner = (int) $contact->user_id === (int) $user->id;

        $shares = $contact->workspaceShares()->with('workspace', 'sharedBy')->get();

        $sharedBy = null;
        $isSharedContact = false;
        if (!$isOwner && $ws) {
            $share = $shares->firstWhere('workspace_id', $ws->id);
            if ($share) {
                $isSharedContact = true;
                $sharedBy = $share->sharedBy;
            }
        }

        // Workspaces the owner can share this contact with (all their non-personal workspaces).
        $shareableWorkspaces = collect();
        if ($isOwner) {
            $shareableWorkspaces = $user->accessibleWorkspaces()->filter(fn ($w) => !$w->is_personal);
        }

        return [
            'is_owner'             => $isOwner,
            'is_shared_contact'    => $isSharedContact,
            'shared_by'            => $sharedBy,
            'current_workspace'    => $ws,
            'shareable_workspaces' => $shareableWorkspaces,
            'shares'               => $shares,
        ];
    }

    // ---- helpers ----------------------------------------------------------

    /** Resolve the plan-based contacts_max cap. Returns -1 for unlimited. */
    public static function planContactsCap($user): int
    {
        // Bypass holders are effectively unlimited (same contract as
        // User::getPlanFeature / CheckPlanLimit).
        if ($user && method_exists($user, 'hasPermission') && $user->hasPermission('user.plan_limits.bypass')) {
            return -1;
        }
        $features = ($user && $user->plan && $user->plan->features) ? $user->plan->features : [];
        return (int) ($features['contacts_max'] ?? 5000);
    }

    /** Per-row warnings shown in the preview before commit. */
    private function rowWarnings(array $row): array
    {
        $warnings = [];

        $hasName = ($row['display_name'] ?? null) || ($row['given_name'] ?? null) || ($row['family_name'] ?? null);
        $hasPhone = !empty($row['phones']);
        $hasEmail = !empty($row['emails']);

        if (!$hasName && !$hasPhone && !$hasEmail) {
            $warnings[] = 'No name, phone, or email — this row will be skipped.';
            return $warnings;
        }
        if (!$hasName) {
            $warnings[] = 'Missing name; the contact will be saved without one.';
        }
        foreach (($row['emails'] ?? []) as $e) {
            $val = trim((string) ($e['value'] ?? ''));
            if ($val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                $warnings[] = 'Invalid email: ' . $val;
            }
        }
        foreach (($row['phones'] ?? []) as $p) {
            $val = trim((string) ($p['value'] ?? ''));
            if ($val !== '' && strlen($val) > 80) {
                $warnings[] = 'Phone value is too long and will be rejected.';
            }
        }
        return $warnings;
    }

    private function stashPath(int $userId, string $token): string
    {
        // Reject anything that doesn't look like our generated token so a
        // crafted URL can't escape the imports directory.
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            abort(404);
        }
        return "imports/{$userId}/{$token}.json";
    }

    private function loadStash(int $userId, string $token): ?array
    {
        $path = $this->stashPath($userId, $token);
        if (!Storage::disk('local')->exists($path)) return null;
        $data = json_decode(Storage::disk('local')->get($path), true);
        return is_array($data) ? $data : null;
    }

    private function discardStash(int $userId, string $token): void
    {
        $path = $this->stashPath($userId, $token);
        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * Atomically take ownership of a stash file and return its contents.
     * Returns null if the file is missing OR if another concurrent confirm
     * already claimed it. Uses rename() so only one caller wins the race.
     */
    private function claimStash(int $userId, string $token): ?array
    {
        $disk = Storage::disk('local');
        $path = $this->stashPath($userId, $token);
        if (!$disk->exists($path)) return null;

        $src   = $disk->path($path);
        $claim = $src . '.claimed.' . bin2hex(random_bytes(4));
        if (!@rename($src, $claim)) return null;

        $raw = @file_get_contents($claim);
        @unlink($claim);
        if ($raw === false) return null;
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'display_name' => 'nullable|string|max:191',
            'given_name'   => 'nullable|string|max:191',
            'family_name'  => 'nullable|string|max:191',
            'organization' => 'nullable|string|max:191',
            'job_title'    => 'nullable|string|max:191',
            'notes'        => 'nullable|string|max:5000',
            'tags'         => 'nullable|array|max:50',
            'tags.*'       => 'nullable|string|max:80',
            'photo'        => 'nullable|image|max:5120',
            'phones'                 => 'nullable|array|max:10',
            'phones.*.label'         => 'nullable|string|max:50',
            'phones.*.value'         => 'nullable|string|max:80',
            'emails'                 => 'nullable|array|max:10',
            'emails.*.label'         => 'nullable|string|max:50',
            'emails.*.value'         => 'nullable|email|max:191',
        ]);
    }

    private function syncRows(Contact $contact, array $phones, array $emails): void
    {
        $contact->phones()->delete();
        foreach ($phones as $p) {
            $val = trim((string) ($p['value'] ?? ''));
            if ($val === '') continue;
            $contact->phones()->create([
                'label'      => $p['label'] ?? null,
                'value'      => $val,
                'value_e164' => ContactPhone::normalize($val),
                'is_primary' => false,
            ]);
        }
        $contact->emails()->delete();
        foreach ($emails as $e) {
            $val = trim((string) ($e['value'] ?? ''));
            if ($val === '') continue;
            $contact->emails()->create([
                'label'      => $e['label'] ?? null,
                'value'      => ContactEmail::normalize($val),
                'is_primary' => false,
            ]);
        }
    }

    /** Push to Google in the background-best-effort. Never fails the request. */
    private function pushToGoogleSafely(int $userId, Contact $contact): void
    {
        $account = GoogleContactsAccount::where('user_id', $userId)->where('push_enabled', true)->first();
        if (!$account) return;
        try { $this->sync->pushContact($account, $contact); }
        catch (\Throwable $e) { \Log::warning('Push contact failed', ['err' => $e->getMessage()]); }
    }

    /**
     * Immediately try to finalise a just-created deletion tombstone on Google.
     * Best-effort: the tombstone is the source of truth and the scheduled sync
     * retries it on failure, so this never fails the request.
     */
    private function deleteFromGoogleSafely(?ContactDeletionTombstone $tombstone): void
    {
        if (!$tombstone) return;
        $account = GoogleContactsAccount::where('id', $tombstone->google_contacts_account_id)
            ->where('push_enabled', true)->first();
        if (!$account) return; // push disabled → leave for the scheduled drain
        try { $this->sync->attemptTombstoneDelete($account, $tombstone); }
        catch (\Throwable $e) { \Log::warning('Immediate contact delete failed', ['err' => $e->getMessage()]); }
    }

    // ---- bulk export ------------------------------------------------------

    /** Show the export options form. */
    public function exportRequest(Request $request)
    {
        $user  = $request->user();
        $total = Contact::withoutGlobalScope('workspace')->where('user_id', $user->id)->count();
        $tab   = in_array($request->query('tab'), ['all', 'biolink'], true) ? $request->query('tab') : 'all';
        $q     = trim((string) $request->query('q', ''));
        return view('user.contacts.export_form', compact('total', 'tab', 'q'));
    }

    /** POST: create or stream the export. */
    public function export(Request $request)
    {
        $user = $request->user();
        $v = $request->validate([
            'format' => 'required|in:csv,vcf',
            'scope'  => 'required|in:all,filtered',
            'tab'    => 'nullable|in:all,biolink',
            'q'      => 'nullable|string|max:255',
        ]);

        $scope = [];
        if ($v['scope'] === 'filtered') {
            $scope['tab'] = $v['tab'] ?? 'all';
            $scope['q']   = $v['q']  ?? '';
        }

        $query = Contact::withoutGlobalScope('workspace')->where('user_id', $user->id);
        if (($scope['tab'] ?? '') === 'biolink') $query->whereNotNull('biolink_user_id');
        if (!empty($scope['q'])) {
            $needle = '%' . $scope['q'] . '%';
            $query->where(function ($w) use ($needle) {
                $w->where('display_name', 'ilike', $needle)
                  ->orWhere('given_name',  'ilike', $needle)
                  ->orWhere('family_name', 'ilike', $needle)
                  ->orWhere('organization','ilike', $needle)
                  ->orWhereHas('emails',  fn ($e) => $e->where('value', 'ilike', $needle))
                  ->orWhereHas('phones',  fn ($p) => $p->where('value', 'ilike', $needle));
            });
        }
        $count = $query->count();

        // Small address books: generate synchronously and stream back.
        if ($count <= self::EXPORT_ASYNC_THRESHOLD) {
            $contacts = $query->with(['phones', 'emails'])->orderBy('display_name')->get();
            $content  = $v['format'] === 'vcf'
                ? $this->exportBuilder->buildVcf($contacts)
                : $this->exportBuilder->buildCsv($contacts);
            $date     = now()->format('Y-m-d');
            $filename = "contacts-{$date}.{$v['format']}";
            $mime     = $v['format'] === 'vcf' ? 'text/vcard; charset=utf-8' : 'text/csv; charset=utf-8';
            return response($content, 200, [
                'Content-Type'        => $mime,
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        // Large address books: queue and redirect to a polling page.
        $export = ContactExport::create([
            'user_id'       => $user->id,
            'format'        => $v['format'],
            'scope'         => $scope ?: null,
            'status'        => 'pending',
            'contact_count' => $count,
        ]);
        ProcessContactExportJob::dispatch($export->id);
        return redirect()->route('user.contacts.export.show', $export)
            ->with('success', 'Export queued — we\'ll generate your file in the background.');
    }

    /** Status/download page for a queued export. */
    public function exportShow(Request $request, ContactExport $export)
    {
        abort_if($export->user_id !== workspace_owner_id(), 403);
        return view('user.contacts.export_show', compact('export'));
    }

    /** JSON poll endpoint the status page calls every 2 seconds. */
    public function exportStatus(Request $request, ContactExport $export)
    {
        abort_if($export->user_id !== workspace_owner_id(), 403);
        return response()->json([
            'status'        => $export->status,
            'contact_count' => $export->contact_count,
            'is_ready'      => $export->isReady(),
            'in_progress'   => $export->isInProgress(),
        ]);
    }

    /** Stream the generated export file to the browser (auth-gated). */
    public function exportDownload(Request $request, ContactExport $export)
    {
        abort_if($export->user_id !== workspace_owner_id(), 403);
        return $this->streamExport($export);
    }

    /**
     * Serve a background export via a temporary signed URL — used by the
     * mobile/API path so the app can open the URL without a bearer token.
     * The `signed` middleware on the route is the only authorization.
     */
    public function exportSignedDownload(Request $request, ContactExport $export)
    {
        return $this->streamExport($export);
    }

    private function streamExport(ContactExport $export): \Illuminate\Http\Response
    {
        abort_if(!$export->isReady(), 404, 'Export file is not ready yet.');
        $content = Storage::disk('local')->get($export->file_path);
        if ($content === null) {
            abort(404, 'Export file not found. Please start a new export.');
        }
        return response($content, 200, [
            'Content-Type'        => $export->mimeType(),
            'Content-Disposition' => 'attachment; filename="' . $export->downloadFilename() . '"',
        ]);
    }

    public function biolinkPreview(Contact $contact): ?array
    {
        $u = $contact->biolinkUser;
        if (!$u) return null;
        $bio = \App\Modules\User\Models\Link::where('user_id', $u->id)
            ->whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)->where('is_active', true)
            ->orderByDesc('id')->first();
        return [
            'user'   => $u,
            'link'   => $bio,
            'url'    => $bio ? url('/' . $bio->alias) : null,
        ];
    }
}
