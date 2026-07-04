<?php

namespace App\Modules\Api\Controllers;

use App\Jobs\ProcessContactImportJob;
use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Services\ContactCandidateValidator;
use App\Modules\User\Controllers\ContactController as WebContactController;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactDeletionTombstone;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\ContactImport;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\GoogleContactsAccount;
use App\Modules\User\Models\IntegrationConfig;
use App\Modules\User\Models\Link;
use App\Modules\User\Services\Contacts\BiolinkAttachResolver;
use App\Modules\User\Services\Contacts\ContactImportParser;
use App\Modules\User\Services\Contacts\GoogleContactsSyncService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ContactController extends Controller
{
    use ApiResponses;

    /** Parsed-row count above which we punt to a queued job rather than process inline. */
    public const ASYNC_THRESHOLD = 200;

    public function __construct(
        protected BiolinkAttachResolver $resolver,
        protected GoogleContactsSyncService $sync,
        protected ContactImportParser $importParser,
    ) {}

    public function index(Request $request)
    {
        $q = Contact::with(['phones', 'emails'])
            ->where('user_id', $request->user()->id);

        if ($s = $request->string('q')->toString()) {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $s) . '%';
            $q->where(function ($w) use ($like) {
                $w->where('display_name', 'ilike', $like)
                  ->orWhere('given_name', 'ilike', $like)
                  ->orWhere('family_name', 'ilike', $like)
                  ->orWhere('organization', 'ilike', $like)
                  ->orWhereHas('emails', fn ($e) => $e->where('value', 'ilike', $like))
                  ->orWhereHas('phones', fn ($p) => $p->where('value', 'ilike', $like)
                                                     ->orWhere('value_e164', 'ilike', $like));
            });
        }

        $page = $q->orderBy('display_name')
            ->paginate(min(200, max(1, (int) $request->input('per_page', 50))));

        return $this->ok([
            'items' => collect($page->items())->map(fn ($c) => $this->transform($c))->all(),
            'meta'  => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
            'usage' => $this->contactsUsage($request->user(), $page->total()),
        ]);
    }

    /**
     * Consolidated follow-ups list for the mobile app: every contact with a
     * scheduled follow_up_at, soonest-first, split into overdue vs upcoming so
     * users see everything to act on without opening each contact.
     */
    public function followUps(Request $request)
    {
        $contacts = Contact::with(['phones', 'emails'])
            ->where('user_id', $request->user()->id)
            ->whereNotNull('follow_up_at')
            ->orderBy('follow_up_at')
            ->get();

        $now = now();

        return $this->ok([
            'overdue'  => $contacts->filter(fn ($c) => $c->follow_up_at->lte($now))
                ->map(fn ($c) => $this->transform($c))->values()->all(),
            'upcoming' => $contacts->filter(fn ($c) => $c->follow_up_at->gt($now))
                ->map(fn ($c) => $this->transform($c))->values()->all(),
        ]);
    }

    /**
     * Lightweight overdue follow-ups count for the Contacts tab badge: the
     * number of contacts whose scheduled follow_up_at is due already (<= now).
     * Kept as a tiny count-only endpoint so the header badge can poll it
     * without pulling the full follow-ups list.
     */
    public function followUpsCount(Request $request)
    {
        $overdue = Contact::where('user_id', $request->user()->id)
            ->whereNotNull('follow_up_at')
            ->where('follow_up_at', '<=', now())
            ->count();

        return $this->ok(['overdue' => $overdue]);
    }

    /**
     * Plan-based contacts usage gauge, mirrors the web index banner so the
     * mobile app can warn the user before the create/import flow blocks them.
     */
    protected function contactsUsage($user, ?int $total = null): array
    {
        $count = $total ?? Contact::where('user_id', $user->id)->count();
        $cap   = WebContactController::planContactsCap($user);
        $usage = [
            'count'     => $count,
            'cap'       => $cap === -1 ? null : $cap,
            'unlimited' => $cap === -1,
            'percent'   => ($cap === -1 || $cap === 0) ? 0 : min(100, (int) floor(($count / $cap) * 100)),
        ];
        $usage['near_cap'] = !$usage['unlimited'] && $usage['percent'] >= 90 && $count < ($cap ?? 0);
        $usage['at_cap']   = !$usage['unlimited'] && $cap !== null && $count >= $cap;
        return $usage;
    }

    public function show(Request $request, int $id)
    {
        $c = Contact::with(['phones', 'emails'])
            ->where('user_id', $request->user()->id)
            ->find($id);
        if (!$c) return $this->notFound('Contact not found');
        return $this->ok(['contact' => $this->transform($c)]);
    }

    public function store(Request $request)
    {
        $userId = $request->user()->id;

        // Hard validation gate. Even when the legacy mobile UI sends a
        // pre-validated payload, we re-check server-side so a one-click
        // save from the extension can't bypass it.
        $v = (new ContactCandidateValidator($userId))->validate($request->all());
        if (!$v->ok()) {
            return $this->fail('Contact failed validation.', 422, 'contact_invalid', [
                'errors'       => $v->errors,
                'normalized'   => $v->normalized,
                'duplicate_of' => $v->duplicateOf,
            ]);
        }

        // If the caller asked for strict one-click semantics and we found
        // an existing match, refuse to create — let the client surface a
        // merge prompt instead of silently duplicating.
        $strict = filter_var($request->input('validate', ''), FILTER_VALIDATE_BOOL)
            || $request->input('validate') === 'strict';
        if ($strict && $v->duplicateOf !== null) {
            return $this->fail('A matching contact already exists.', 409, 'contact_duplicate', [
                'duplicate_of' => $v->duplicateOf,
                'normalized'   => $v->normalized,
            ]);
        }

        $data = $v->normalized;

        $wsId = $this->activeWorkspaceId($request->user());
        $contact = DB::transaction(function () use ($data, $userId, $wsId) {
            $c = new Contact([
                'user_id'      => $userId,
                'display_name' => $data['display_name'] ?? null,
                'given_name'   => $data['given_name']   ?? null,
                'family_name'  => $data['family_name']  ?? null,
                'organization' => $data['organization'] ?? null,
                'job_title'    => $data['job_title']    ?? null,
                'website'      => $data['website']      ?? null,
                'socials'      => !empty($data['socials']) ? $data['socials'] : null,
                'notes'        => $data['notes']        ?? null,
                'tags'         => !empty($data['tags']) ? $data['tags'] : null,
                'sources'      => !empty($data['source_url']) ? [[
                    'url'  => $data['source_url'],
                    'at'   => now()->toIso8601String(),
                    'kind' => 'extension',
                ]] : null,
                // Mark dirty so the immediate push (and any pull-side conflict
                // guard) treats this as a locally-originated change, matching
                // the web create path.
                'locally_modified_at' => now(),
            ]);
            $c->workspace_id = $wsId;
            $c->save();
            $this->syncEmails($c, $data['emails'] ?? []);
            $this->syncPhones($c, $data['phones'] ?? []);
            return $c->fresh(['phones', 'emails']);
        });

        // Immediately mirror the new contact to Google (best-effort), so it
        // shows up there within seconds instead of at the next scheduled sync.
        $this->pushToGoogleSafely($userId, $contact);

        return $this->created([
            'contact'      => $this->transform($contact->fresh(['phones', 'emails'])),
            'duplicate_of' => $v->duplicateOf, // surfaced for non-strict callers
        ]);
    }

    /**
     * Validate-and-dedupe a candidate without persisting. Drives the
     * extension's "preview before save" card and decides whether the
     * one-click path can proceed.
     */
    public function validateCandidate(Request $request)
    {
        $userId = $request->user()->id;
        $v = (new ContactCandidateValidator($userId))->validate($request->all());
        return $this->ok($v->toArray());
    }

    /**
     * Merge a candidate into an existing contact owned by the signed-in
     * user. Non-empty fields on the existing record are preserved; new
     * emails / phones / socials / tags are appended; the source URL is
     * pushed onto the contact's sources list.
     */
    public function merge(Request $request, int $id)
    {
        $userId = $request->user()->id;
        $existing = Contact::with(['phones', 'emails'])->where('user_id', $userId)->find($id);
        if (!$existing) return $this->notFound('Contact not found');

        $v = (new ContactCandidateValidator($userId))->validate($request->all());
        if (!$v->ok()) {
            return $this->fail('Contact failed validation.', 422, 'contact_invalid', [
                'errors'     => $v->errors,
                'normalized' => $v->normalized,
            ]);
        }
        $data = $v->normalized;

        $contact = DB::transaction(function () use ($existing, $data) {
            $existing->fill(array_filter([
                'display_name' => $existing->display_name ?: ($data['display_name'] ?? null),
                'given_name'   => $existing->given_name   ?: ($data['given_name']   ?? null),
                'family_name'  => $existing->family_name  ?: ($data['family_name']  ?? null),
                'organization' => $existing->organization ?: ($data['organization'] ?? null),
                'job_title'    => $existing->job_title    ?: ($data['job_title']    ?? null),
                'website'      => $existing->website      ?: ($data['website']      ?? null),
                'notes'        => $existing->notes        ?: ($data['notes']        ?? null),
            ], fn ($v) => $v !== null && $v !== ''))->save();

            // Tags: union, preserving existing order.
            $newTags = array_values(array_unique(array_merge(
                (array) ($existing->tags ?? []),
                (array) ($data['tags'] ?? []),
            )));
            if (!empty($newTags)) {
                $existing->tags = $newTags;
                $existing->save();
            }

            // Socials: only set platforms that don't already have a value.
            $socials = (array) ($existing->socials ?? []);
            foreach (($data['socials'] ?? []) as $platform => $value) {
                if (empty($socials[$platform])) $socials[$platform] = $value;
            }
            if (!empty($socials)) {
                $existing->socials = $socials;
                $existing->save();
            }

            $this->mergeEmails($existing, array_map(fn ($e) => [
                'value'      => $e['value'],
                'label'      => $e['label'] ?? null,
            ], $data['emails'] ?? []));
            $this->mergePhones($existing, array_map(fn ($p) => [
                'value'      => $p['value'],
                'value_e164' => $p['value_e164'] ?? null,
                'label'      => $p['label'] ?? null,
            ], $data['phones'] ?? []));

            if (!empty($data['source_url'])) {
                $sources = (array) ($existing->sources ?? []);
                $alreadyHave = collect($sources)->pluck('url')->contains($data['source_url']);
                if (!$alreadyHave) {
                    $sources[] = [
                        'url'  => $data['source_url'],
                        'at'   => now()->toIso8601String(),
                        'kind' => 'extension',
                    ];
                    $existing->sources = $sources;
                    $existing->save();
                }
            }
            // Mark dirty so the immediate push carries the merge and the
            // pull-side conflict guard doesn't clobber it.
            $existing->locally_modified_at = now();
            $existing->save();
            return $existing->fresh(['phones', 'emails']);
        });

        // Immediately mirror the merged contact to Google (best-effort).
        $this->pushToGoogleSafely($userId, $contact);

        return $this->ok(['contact' => $this->transform($contact->fresh(['phones', 'emails']))]);
    }

    public function update(Request $request, int $id)
    {
        $c = Contact::with(['phones', 'emails'])
            ->where('user_id', $request->user()->id)
            ->find($id);
        if (!$c) return $this->notFound('Contact not found');

        $data = $this->validatePayload($request, partial: true);

        $contact = DB::transaction(function () use ($c, $data) {
            $c->fill(array_intersect_key($data, array_flip([
                'display_name', 'given_name', 'family_name',
                'organization', 'job_title', 'notes',
            ])));
            // Mark dirty so the pull-side conflict guard doesn't clobber this
            // edit and the immediate push carries it, matching the web path.
            $c->locally_modified_at = now();
            $c->save();

            if (array_key_exists('emails', $data)) {
                $this->syncEmails($c, $data['emails'] ?? []);
            }
            if (array_key_exists('phones', $data)) {
                $this->syncPhones($c, $data['phones'] ?? []);
            }
            return $c->fresh(['phones', 'emails']);
        });

        // Immediately mirror the edit to Google (best-effort).
        $this->pushToGoogleSafely($request->user()->id, $contact);

        return $this->ok(['contact' => $this->transform($contact->fresh(['phones', 'emails']))]);
    }

    public function destroy(Request $request, int $id)
    {
        $c = Contact::where('user_id', $request->user()->id)->find($id);
        if (!$c) return $this->notFound('Contact not found');

        // Park a deletion tombstone before removing the row so the sync can
        // finalise it on Google, then attempt an immediate best-effort delete.
        // The tombstone is the source of truth and gets retried on failure.
        $tombstone = null;
        if ($c->google_contacts_account_id && $c->google_resource_name) {
            $tombstone = ContactDeletionTombstone::create([
                'user_id'                    => $c->user_id,
                'google_contacts_account_id' => $c->google_contacts_account_id,
                'google_resource_name'       => $c->google_resource_name,
            ]);
        }
        $c->delete();
        $this->deleteFromGoogleSafely($tombstone);
        return $this->noContent();
    }

    /**
     * Bulk-import contacts from a device address book. Each row is matched
     * against the user's existing contacts by primary email or phone (case-
     * insensitive) and updated in place, otherwise created.
     */
    public function bulkImport(Request $request)
    {
        $data = $request->validate([
            'contacts'                       => 'required|array|min:1|max:500',
            'contacts.*.display_name'        => 'nullable|string|max:191',
            'contacts.*.given_name'          => 'nullable|string|max:191',
            'contacts.*.family_name'         => 'nullable|string|max:191',
            'contacts.*.organization'        => 'nullable|string|max:191',
            'contacts.*.emails'              => 'nullable|array',
            'contacts.*.emails.*.value'      => 'required_with:contacts.*.emails.*|email|max:191',
            'contacts.*.emails.*.label'      => 'nullable|string|max:50',
            'contacts.*.phones'              => 'nullable|array',
            'contacts.*.phones.*.value'      => 'required_with:contacts.*.phones.*|string|max:80',
            'contacts.*.phones.*.label'      => 'nullable|string|max:50',
        ]);

        $userId = $request->user()->id;
        $created = 0; $updated = 0; $skipped = 0;

        $wsId = $this->activeWorkspaceId($request->user());
        DB::transaction(function () use ($data, $userId, $wsId, &$created, &$updated, &$skipped) {
            foreach ($data['contacts'] as $row) {
                $emails = $row['emails'] ?? [];
                $phones = $row['phones'] ?? [];
                $name   = $row['display_name'] ?? trim(($row['given_name'] ?? '') . ' ' . ($row['family_name'] ?? ''));

                if (empty($emails) && empty($phones) && trim((string) $name) === '') {
                    $skipped++; continue;
                }

                // Try to find an existing contact by primary email or phone.
                $existing = null;
                foreach ($emails as $e) {
                    $existing = Contact::where('user_id', $userId)
                        ->whereHas('emails', fn ($q) => $q->whereRaw('LOWER(value) = ?', [strtolower($e['value'])]))
                        ->first();
                    if ($existing) break;
                }
                if (!$existing) {
                    foreach ($phones as $p) {
                        $existing = Contact::where('user_id', $userId)
                            ->whereHas('phones', fn ($q) => $q->where('value', $p['value']))
                            ->first();
                        if ($existing) break;
                    }
                }

                if ($existing) {
                    $existing->fill(array_filter([
                        'display_name' => $name ?: $existing->display_name,
                        'given_name'   => $row['given_name']   ?? $existing->given_name,
                        'family_name'  => $row['family_name']  ?? $existing->family_name,
                        'organization' => $row['organization'] ?? $existing->organization,
                    ]))->save();
                    $this->mergeEmails($existing, $emails);
                    $this->mergePhones($existing, $phones);
                    $updated++;
                } else {
                    $c = new Contact([
                        'user_id'      => $userId,
                        'display_name' => $name ?: null,
                        'given_name'   => $row['given_name']   ?? null,
                        'family_name'  => $row['family_name']  ?? null,
                        'organization' => $row['organization'] ?? null,
                    ]);
                    $c->workspace_id = $wsId;
                    $c->save();
                    $this->mergeEmails($c, $emails);
                    $this->mergePhones($c, $phones);
                    $created++;
                }
            }
        });

        return $this->ok(compact('created', 'updated', 'skipped'));
    }

    protected function mergeEmails(Contact $c, array $emails): void
    {
        foreach ($emails as $i => $e) {
            $val = strtolower(trim($e['value']));
            if ($val === '') continue;
            $exists = $c->emails()->whereRaw('LOWER(value) = ?', [$val])->exists();
            if ($exists) continue;
            ContactEmail::create([
                'contact_id' => $c->id,
                'label'      => $e['label'] ?? null,
                'value'      => $e['value'],
                'is_primary' => ($i === 0 && $c->emails()->count() === 0),
            ]);
        }
    }

    protected function mergePhones(Contact $c, array $phones): void
    {
        foreach ($phones as $i => $p) {
            $val = preg_replace('/\s+/', '', $p['value']);
            if ($val === '') continue;
            $exists = $c->phones()->where('value', $p['value'])->exists();
            if ($exists) continue;
            ContactPhone::create([
                'contact_id' => $c->id,
                'label'      => $p['label'] ?? null,
                'value'      => $p['value'],
                'value_e164' => $p['value_e164'] ?? null,
                'is_primary' => ($i === 0 && $c->phones()->count() === 0),
            ]);
        }
    }

    protected function validatePayload(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'nullable';
        return $request->validate([
            'display_name' => [$partial ? 'sometimes' : 'nullable', 'nullable', 'string', 'max:191'],
            'given_name'   => [$req, 'nullable', 'string', 'max:191'],
            'family_name'  => [$req, 'nullable', 'string', 'max:191'],
            'organization' => [$req, 'nullable', 'string', 'max:191'],
            'job_title'    => [$req, 'nullable', 'string', 'max:191'],
            'notes'        => [$req, 'nullable', 'string', 'max:2000'],
            'emails'       => [$req, 'array'],
            'emails.*.value'   => ['required_with:emails.*', 'email', 'max:191'],
            'emails.*.label'   => ['nullable', 'string', 'max:50'],
            'emails.*.is_primary' => ['nullable', 'boolean'],
            'phones'       => [$req, 'array'],
            'phones.*.value'      => ['required_with:phones.*', 'string', 'max:80'],
            'phones.*.value_e164' => ['nullable', 'string', 'max:32'],
            'phones.*.label'      => ['nullable', 'string', 'max:50'],
            'phones.*.is_primary' => ['nullable', 'boolean'],
        ]);
    }

    protected function syncEmails(Contact $c, array $emails): void
    {
        $c->emails()->delete();
        foreach (array_values($emails) as $i => $row) {
            ContactEmail::create([
                'contact_id' => $c->id,
                'label'      => $row['label'] ?? null,
                'value'      => $row['value'],
                'is_primary' => (bool) ($row['is_primary'] ?? ($i === 0)),
            ]);
        }
    }

    protected function syncPhones(Contact $c, array $phones): void
    {
        $c->phones()->delete();
        foreach (array_values($phones) as $i => $row) {
            ContactPhone::create([
                'contact_id' => $c->id,
                'label'      => $row['label'] ?? null,
                'value'      => $row['value'],
                'value_e164' => $row['value_e164'] ?? null,
                'is_primary' => (bool) ($row['is_primary'] ?? ($i === 0)),
            ]);
        }
    }

    protected function transform(Contact $c): array
    {
        return [
            'id'           => $c->id,
            'display_name' => $c->display_name ?: $c->nameForDisplay(),
            'given_name'   => $c->given_name,
            'family_name'  => $c->family_name,
            'organization' => $c->organization,
            'job_title'    => $c->job_title,
            'notes'        => $c->notes,
            'emails'       => $c->emails->map(fn ($e) => [
                'id' => $e->id, 'label' => $e->label, 'value' => $e->value, 'is_primary' => (bool) $e->is_primary,
            ])->values()->all(),
            'phones'       => $c->phones->map(fn ($p) => [
                'id' => $p->id, 'label' => $p->label, 'value' => $p->value,
                'value_e164' => $p->value_e164, 'is_primary' => (bool) $p->is_primary,
            ])->values()->all(),
            'photo_url'    => $c->photoUrl(),
            'manual_profile' => \App\Modules\User\Support\DialerIdentity::normalizeManual($c->manual_profile),
            'follow_up_at'   => optional($c->follow_up_at)->toIso8601String(),
            'follow_up_note' => $c->follow_up_note,
            'follow_up_tz'   => $c->follow_up_tz,
            'created_at'   => optional($c->created_at)->toIso8601String(),
        ];
    }

    /**
     * Set (or reschedule) a follow-up reminder for this contact/lead
     * (Task #3524). `follow_up_at` is an ISO-8601 datetime; if it carries an
     * explicit offset that fixes the instant, otherwise it is interpreted in
     * the optional `follow_up_tz` (Task #3526) — falling back to the app
     * timezone. Stored as an absolute UTC instant so the scheduler fires at
     * the exact moment picked regardless of timezone. Editing/resetting
     * always clears the notified stamp so an updated reminder fires again.
     */
    public function setFollowUp(Request $request, int $id)
    {
        $c = Contact::where('user_id', $request->user()->id)->find($id);
        if (!$c) return $this->notFound('Contact not found');

        $v = $request->validate([
            'follow_up_at'   => ['required', 'date'],
            'follow_up_note' => ['nullable', 'string', 'max:2000'],
            'follow_up_tz'   => ['nullable', 'string', 'timezone'],
        ]);

        $tz = $v['follow_up_tz'] ?? config('app.timezone');
        $at = \Illuminate\Support\Carbon::parse($v['follow_up_at'], $tz)->utc();
        if ($at->isPast()) {
            return $this->fail('Follow-up time must be in the future.', 422, 'follow_up_in_past');
        }

        $c->update([
            'follow_up_at'          => $at,
            'follow_up_note'        => $v['follow_up_note'] ?? null,
            'follow_up_tz'          => $v['follow_up_tz'] ?? null,
            'follow_up_notified_at' => null,
        ]);

        return $this->ok(['contact' => $this->transform($c->fresh(['phones', 'emails']))]);
    }

    /** Clear a scheduled follow-up reminder without firing it. */
    public function clearFollowUp(Request $request, int $id)
    {
        $c = Contact::where('user_id', $request->user()->id)->find($id);
        if (!$c) return $this->notFound('Contact not found');

        $c->update([
            'follow_up_at'          => null,
            'follow_up_note'        => null,
            'follow_up_tz'          => null,
            'follow_up_notified_at' => null,
        ]);

        return $this->ok(['contact' => $this->transform($c->fresh(['phones', 'emails']))]);
    }

    /**
     * Persist the owner's manual Dialer additions (channels / socials /
     * location) for a contact, kept distinct from auto-pulled biolink data.
     */
    public function updateManualProfile(Request $request, int $id)
    {
        $c = Contact::where('user_id', $request->user()->id)->find($id);
        if (!$c) return $this->notFound('Contact not found');

        $data = $request->validate([
            'channels'           => ['array'],
            'channels.*.type'    => ['nullable', 'string', 'max:40'],
            'channels.*.label'   => ['nullable', 'string', 'max:80'],
            'channels.*.value'   => ['nullable', 'string', 'max:255'],
            'socials'            => ['array'],
            'socials.*.platform' => ['nullable', 'string', 'max:40'],
            'socials.*.label'    => ['nullable', 'string', 'max:80'],
            'socials.*.url'      => ['nullable', 'string', 'max:255'],
            'location'           => ['nullable', 'array'],
            'location.label'     => ['nullable', 'string', 'max:120'],
            'location.address'   => ['nullable', 'string', 'max:255'],
            'location.lat'       => ['nullable', 'numeric'],
            'location.lng'       => ['nullable', 'numeric'],
        ]);

        $clean = \App\Modules\User\Support\DialerIdentity::normalizeManual([
            'channels' => $data['channels'] ?? [],
            'socials'  => $data['socials'] ?? [],
            'location' => $data['location'] ?? null,
        ]);

        // Store only the raw editable fields (derived url/source/maps_url are
        // recomputed on read).
        $c->manual_profile = [
            'channels' => array_map(fn ($x) => [
                'type' => $x['type'], 'label' => $x['label'], 'value' => $x['value'],
            ], $clean['channels']),
            'socials'  => array_map(fn ($x) => [
                'platform' => $x['platform'], 'label' => $x['label'], 'url' => $x['url'],
            ], $clean['socials']),
            'location' => $clean['location'] ? [
                'label'   => $clean['location']['label'],
                'address' => $clean['location']['address'],
                'lat'     => $clean['location']['lat'],
                'lng'     => $clean['location']['lng'],
            ] : null,
        ];
        $c->save();

        return $this->ok(['manual_profile' => $clean]);
    }

    // ---- Google Contacts sync -------------------------------------------

    /** Current Google Contacts connection status for the signed-in user. */
    public function googleStatus(Request $request)
    {
        $account = GoogleContactsAccount::where('user_id', $request->user()->id)->first();
        return $this->ok(['account' => $account ? $this->transformGoogleAccount($account) : null]);
    }

    /**
     * Run an on-demand sync for the connected Google account now (pull + push).
     * Throttled per-account via the service so it can't be hammered; returns a
     * clear status the client can surface:
     *   status=synced       — ran now, `stats` populated
     *   status=throttled    — synced very recently, retry after `retry_after`s
     *   status=in_progress  — a sync is already running
     */
    public function googleSync(Request $request)
    {
        $account = GoogleContactsAccount::where('user_id', $request->user()->id)->first();
        if (!$account) return $this->fail('No Google account connected.', 422, 'google_not_connected');

        try {
            $result = $this->sync->syncNow($account);
        } catch (\Throwable $e) {
            \Log::warning('API Google contacts sync failed', ['err' => $e->getMessage()]);
            return $this->fail('Sync failed: ' . $e->getMessage(), 502, 'google_sync_failed');
        }

        if ($result['status'] === 'throttled' || $result['status'] === 'in_progress') {
            return $this->ok([
                'status'      => $result['status'],
                'retry_after' => $result['retry_after'],
                'stats'       => null,
                'account'     => $this->transformGoogleAccount($account->fresh()),
            ]);
        }

        return $this->ok([
            'status'  => 'synced',
            'stats'   => $result['stats'],
            'account' => $this->transformGoogleAccount($account->fresh()),
        ]);
    }

    /** Toggle pull/push preferences on the connected Google account. */
    public function googleUpdate(Request $request)
    {
        $account = GoogleContactsAccount::where('user_id', $request->user()->id)->first();
        if (!$account) return $this->fail('No Google account connected.', 422, 'google_not_connected');

        $data = $request->validate([
            'pull_enabled' => ['sometimes', 'boolean'],
            'push_enabled' => ['sometimes', 'boolean'],
        ]);
        $account->fill($data)->save();

        return $this->ok(['account' => $this->transformGoogleAccount($account->fresh())]);
    }

    /** Disconnect the Google account (local contacts are kept). */
    public function googleDisconnect(Request $request)
    {
        $account = GoogleContactsAccount::where('user_id', $request->user()->id)->first();
        if (!$account) return $this->fail('No Google account connected.', 422, 'google_not_connected');
        $account->delete();
        return $this->ok(['disconnected' => true]);
    }

    /**
     * Push a locally-changed contact to Google now (best-effort). Mirrors the
     * web ContactController helper: only pushes when a push-enabled account
     * exists, and never fails the API request on a Google error.
     */
    private function pushToGoogleSafely(int $userId, Contact $contact): void
    {
        $account = GoogleContactsAccount::where('user_id', $userId)->where('push_enabled', true)->first();
        if (!$account) return;
        try { $this->sync->pushContact($account, $contact); }
        catch (\Throwable $e) { \Log::warning('API push contact failed', ['err' => $e->getMessage()]); }
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
        catch (\Throwable $e) { \Log::warning('API immediate contact delete failed', ['err' => $e->getMessage()]); }
    }

    protected function transformGoogleAccount(GoogleContactsAccount $a): array
    {
        return [
            'id'             => $a->id,
            'account_email'  => $a->account_email,
            'pull_enabled'   => (bool) $a->pull_enabled,
            'push_enabled'   => (bool) $a->push_enabled,
            'last_sync_status' => $a->last_sync_status,
            'last_sync_error'  => $a->last_sync_error,
            'last_synced_at'   => optional($a->last_synced_at)->toIso8601String(),
        ];
    }

    // ---- SMS Link-in-Bio -------------------------------------------------

    /**
     * Text the contact's matched Sayzio biolink URL to one of their saved
     * phone numbers via a configured SMS gateway (Twilio/Plivo). Locked to the
     * contact's own phones so a tampered payload can't repurpose the gateway.
     */
    public function smsBiolink(Request $request, int $id)
    {
        $userId = $request->user()->id;
        $contact = Contact::with(['phones', 'biolinkUser'])
            ->where('user_id', $userId)->find($id);
        if (!$contact) return $this->notFound('Contact not found');

        $preview = $this->biolinkPreview($contact);
        if (!$preview || empty($preview['url'])) {
            return $this->fail('No Link in Bio available to text.', 422, 'no_biolink');
        }

        $to = trim((string) $request->input('to', ''));
        if ($to === '') {
            $primary = $contact->phones->first();
            $to = $primary?->value_e164 ?: $primary?->value ?: '';
        }
        $toClean = preg_replace('/[^\d+]/', '', $to);
        if ($toClean === '' || strlen($toClean) > 20) {
            return $this->fail('This contact has no valid phone number to text.', 422, 'no_phone');
        }

        $allowed = $contact->phones
            ->flatMap(fn ($p) => array_filter([
                preg_replace('/[^\d+]/', '', (string) $p->value_e164),
                preg_replace('/[^\d+]/', '', (string) $p->value),
            ]))
            ->filter()->unique()->values()->all();
        if (!in_array($toClean, $allowed, true)) {
            return $this->fail('You can only text this Link in Bio to a phone number saved on the contact.', 422, 'phone_not_allowed');
        }

        $configId = (int) $request->input('config_id', 0);
        $config = $configId
            ? IntegrationConfig::where('user_id', $userId)->where('id', $configId)->kind('sms')->active()->first()
            : IntegrationConfig::where('user_id', $userId)->kind('sms')->active()
                ->orderByDesc('is_default')->orderBy('id')->first();

        if (!$config) {
            return $this->fail('No active SMS gateway configured. Add Twilio or Plivo under Integrations, or use the device SMS share.', 422, 'no_sms_gateway');
        }

        $name    = $contact->nameForDisplay();
        $message = "Hey " . ($name ?: 'there') . ", here's my Sayzio page: " . $preview['url'];

        try {
            $this->dispatchBiolinkSms($config, $toClean, $message);
        } catch (\Throwable $e) {
            \Log::warning('API biolink SMS send failed', ['err' => $e->getMessage(), 'config_id' => $config->id]);
            return $this->fail('SMS gateway rejected the send: ' . $e->getMessage(), 502, 'sms_send_failed');
        }

        return $this->ok([
            'sent'     => true,
            'to'       => $toClean,
            'provider' => $config->providerLabel(),
        ]);
    }

    /** Mirror of the web ContactController biolink SMS dispatcher. */
    protected function dispatchBiolinkSms(IntegrationConfig $config, string $toClean, string $message): void
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
                throw new \RuntimeException(
                    'SMS provider "' . $config->provider . '" is not yet supported for Link in Bio texting. '
                    . 'Use Twilio or Plivo, or text from a mobile device.'
                );
        }
    }

    protected function biolinkPreview(Contact $contact): ?array
    {
        $u = $contact->biolinkUser;
        if (!$u) return null;
        $bio = Link::where('user_id', $u->id)
            ->whereIn('type', Link::BIOLINK_FAMILY)->where('is_active', true)
            ->orderByDesc('id')->first();
        return [
            'link' => $bio,
            'url'  => $bio ? url('/' . $bio->alias) : null,
        ];
    }

    // ---- Bulk import preview / commit (area 4) ---------------------------

    /**
     * Stage 1: upload + parse a CSV/vCard file, stash the parsed rows to a
     * per-user temp file and return a preview token + first page of rows.
     * Nothing is written to the contacts table until importConfirm().
     */
    public function importParse(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:5120|mimes:csv,txt,vcf,vcard',
        ]);

        $user = $request->user();
        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        try {
            $rows = $this->importParser->parse($file->getRealPath(), $originalName);
        } catch (\Throwable $e) {
            \Log::warning('API contact import: parse failed', [
                'user' => $user->id, 'name' => $originalName, 'err' => $e->getMessage(),
            ]);
            return $this->fail('We couldn\'t read that file. Make sure it\'s a valid CSV or vCard (.vcf) and try again.', 422, 'import_parse_failed');
        }

        $rows = array_map(fn ($r) => $r + ['warnings' => $this->rowWarnings($r)], $rows);

        $token = bin2hex(random_bytes(16));
        Storage::disk('local')->put($this->stashPath($user->id, $token), json_encode([
            'original_name' => $originalName,
            'created_at'    => now()->toIso8601String(),
            'rows'          => $rows,
        ]));

        return $this->created($this->previewPayload($user, $token, $originalName, $rows, 1));
    }

    /** Stage 2: paginated preview of a stashed import. */
    public function importPreview(Request $request, string $token)
    {
        $user = $request->user();
        $stash = $this->loadStash($user->id, $token);
        if (!$stash) return $this->fail('That preview is no longer available. Please re-upload the file.', 410, 'import_expired');

        $page = max(1, (int) $request->query('page', 1));
        return $this->ok($this->previewPayload($user, $token, $stash['original_name'] ?? 'upload', $stash['rows'] ?? [], $page));
    }

    /** Stage 2b: edit a single stashed row (name/phones/emails). */
    public function importRowUpdate(Request $request, string $token, int $index)
    {
        $user = $request->user();
        $stash = $this->loadStash($user->id, $token);
        if (!$stash) return $this->fail('That preview is no longer available. Please re-upload the file.', 410, 'import_expired');

        $rows = $stash['rows'] ?? [];
        if (!isset($rows[$index]) || !is_array($rows[$index])) return $this->notFound('Row not found');

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

        $clean = function (array $items): array {
            $out = [];
            foreach ($items as $it) {
                $val = trim((string) ($it['value'] ?? ''));
                if ($val === '') continue;
                $out[] = ['label' => trim((string) ($it['label'] ?? '')) ?: null, 'value' => $val];
            }
            return $out;
        };

        $updated = array_merge($rows[$index], [
            'display_name' => trim((string) ($v['display_name'] ?? '')) ?: null,
            'given_name'   => trim((string) ($v['given_name'] ?? '')) ?: null,
            'family_name'  => trim((string) ($v['family_name'] ?? '')) ?: null,
            'organization' => trim((string) ($v['organization'] ?? '')) ?: null,
            'phones'       => $clean($v['phones'] ?? []),
            'emails'       => $clean($v['emails'] ?? []),
        ]);
        $updated['warnings'] = $this->rowWarnings($updated);
        $rows[$index] = $updated;

        Storage::disk('local')->put($this->stashPath($user->id, $token), json_encode([
            'original_name' => $stash['original_name'] ?? null,
            'created_at'    => $stash['created_at'] ?? now()->toIso8601String(),
            'rows'          => $rows,
        ]));

        $page = max(1, (int) $request->query('page', 1));
        return $this->ok($this->previewPayload($user, $token, $stash['original_name'] ?? 'upload', $rows, $page));
    }

    /** Stage 2c: drop a single stashed row so it won't be imported. */
    public function importRowSkip(Request $request, string $token, int $index)
    {
        $user = $request->user();
        $stash = $this->loadStash($user->id, $token);
        if (!$stash) return $this->fail('That preview is no longer available. Please re-upload the file.', 410, 'import_expired');

        $rows = $stash['rows'] ?? [];
        if (!isset($rows[$index]) || !is_array($rows[$index])) return $this->notFound('Row not found');

        array_splice($rows, $index, 1);
        Storage::disk('local')->put($this->stashPath($user->id, $token), json_encode([
            'original_name' => $stash['original_name'] ?? null,
            'created_at'    => $stash['created_at'] ?? now()->toIso8601String(),
            'rows'          => $rows,
        ]));

        $perPage = 25;
        $page = max(1, (int) $request->query('page', 1));
        $maxPage = max(1, (int) ceil(count($rows) / $perPage));
        if ($page > $maxPage) $page = $maxPage;

        return $this->ok($this->previewPayload($user, $token, $stash['original_name'] ?? 'upload', $rows, $page));
    }

    /** Stage 2d: discard the whole stash without importing. */
    public function importCancel(Request $request, string $token)
    {
        $this->discardStash($request->user()->id, $token);
        return $this->ok(['cancelled' => true]);
    }

    /**
     * Stage 3: commit the stashed rows. Small lists are processed inline and
     * return a completed result; large lists (> ASYNC_THRESHOLD) are queued
     * and return the ContactImport id to poll via importStatus().
     */
    public function importConfirm(Request $request, string $token)
    {
        $user = $request->user();
        $stash = $this->claimStash($user->id, $token);
        if (!$stash) return $this->fail('That preview is no longer available. Please re-upload the file.', 410, 'import_expired');

        $rows = $stash['rows'];
        $originalName = $stash['original_name'] ?? 'upload';
        $existingCount = Contact::where('user_id', $user->id)->count();
        $cap = WebContactController::planContactsCap($user);

        if (count($rows) > self::ASYNC_THRESHOLD) {
            $import = ContactImport::create([
                'user_id'           => $user->id,
                'original_filename' => $originalName,
                'status'            => 'pending',
                'total_rows'        => count($rows),
                'rows'              => $rows,
            ]);
            ProcessContactImportJob::dispatch($import->id);
            return $this->created([
                'queued' => true,
                'import' => $this->transformImport($import),
            ]);
        }

        $results = ['total' => count($rows), 'created' => 0, 'failed' => [], 'skippedCap' => 0];

        foreach ($rows as $i => $row) {
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
                'phones'       => $row['phones'] ?? [],
                'emails'       => $row['emails'] ?? [],
            ];

            $val = validator($payload, [
                'display_name' => 'nullable|string|max:191',
                'given_name'   => 'nullable|string|max:191',
                'family_name'  => 'nullable|string|max:191',
                'organization' => 'nullable|string|max:191',
                'phones'                 => 'nullable|array|max:10',
                'phones.*.label'         => 'nullable|string|max:50',
                'phones.*.value'         => 'nullable|string|max:80',
                'emails'                 => 'nullable|array|max:10',
                'emails.*.label'         => 'nullable|string|max:50',
                'emails.*.value'         => 'nullable|email|max:191',
            ]);
            if ($val->fails()) {
                $results['failed'][] = ['row' => $rowNum, 'name' => $label, 'reason' => $val->errors()->first()];
                continue;
            }

            $hasAnything = $payload['display_name'] || $payload['given_name'] || $payload['family_name']
                || !empty($payload['phones']) || !empty($payload['emails']);
            if (!$hasAnything) {
                $results['failed'][] = ['row' => $rowNum, 'name' => $label, 'reason' => 'No name, phone, or email found.'];
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
                        'locally_modified_at' => now(),
                    ]);
                    $this->syncImportRows($c, $payload['phones'], $payload['emails']);
                    return $c;
                });
                $this->resolver->resolveFor($contact->fresh('phones'));
                $results['created']++;
            } catch (\Throwable $e) {
                $results['failed'][] = ['row' => $rowNum, 'name' => $label, 'reason' => \Illuminate\Support\Str::limit($e->getMessage(), 200)];
            }
        }

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

        return $this->ok([
            'queued'  => false,
            'import'  => $this->transformImport($import),
            'results' => $results,
        ]);
    }

    /** Poll a queued/inline import's status by id. */
    public function importStatus(Request $request, int $id)
    {
        $import = ContactImport::where('user_id', $request->user()->id)->find($id);
        if (!$import) return $this->notFound('Import not found');
        return $this->ok(['import' => $this->transformImport($import)]);
    }

    protected function transformImport(ContactImport $import): array
    {
        return [
            'id'           => $import->id,
            'status'       => $import->status,
            'total'        => $import->total_rows,
            'processed'    => $import->processed_rows,
            'created'      => $import->created_count,
            'failed'       => $import->failed ?? [],
            'failed_count' => count($import->failed ?? []),
            'skipped_cap'  => $import->skipped_cap_count,
            'percent'      => $import->progressPercent(),
            'in_progress'  => $import->isInProgress(),
        ];
    }

    /** Build the preview payload (paginated rows + stats) for a stash. */
    protected function previewPayload($user, string $token, string $originalName, array $rows, int $page): array
    {
        $perPage = 25;
        $page = max(1, $page);
        $existingCount = Contact::where('user_id', $user->id)->count();
        $cap = WebContactController::planContactsCap($user);
        $remaining = $cap === -1 ? null : max(0, $cap - $existingCount);
        $overCap = $cap === -1 ? 0 : max(0, count($rows) - ($remaining ?? 0));

        return [
            'token'         => $token,
            'original_name' => $originalName,
            'rows'          => array_values(array_slice($rows, ($page - 1) * $perPage, $perPage)),
            'meta'          => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => count($rows),
                'last_page'    => max(1, (int) ceil(count($rows) / $perPage)),
                'offset'       => ($page - 1) * $perPage,
            ],
            'stats'         => [
                'total'     => count($rows),
                'warnings'  => count(array_filter($rows, fn ($r) => !empty($r['warnings']))),
                'remaining' => $remaining,
                'over_cap'  => $overCap,
            ],
        ];
    }

    /** Per-row warnings shown in the preview before commit (mirror of web). */
    protected function rowWarnings(array $row): array
    {
        $warnings = [];
        $hasName  = ($row['display_name'] ?? null) || ($row['given_name'] ?? null) || ($row['family_name'] ?? null);
        $hasPhone = !empty($row['phones']);
        $hasEmail = !empty($row['emails']);

        if (!$hasName && !$hasPhone && !$hasEmail) {
            return ['No name, phone, or email — this row will be skipped.'];
        }
        if (!$hasName) $warnings[] = 'Missing name; the contact will be saved without one.';
        foreach (($row['emails'] ?? []) as $e) {
            $val = trim((string) ($e['value'] ?? ''));
            if ($val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) $warnings[] = 'Invalid email: ' . $val;
        }
        foreach (($row['phones'] ?? []) as $p) {
            $val = trim((string) ($p['value'] ?? ''));
            if ($val !== '' && strlen($val) > 80) $warnings[] = 'Phone value is too long and will be rejected.';
        }
        return $warnings;
    }

    protected function syncImportRows(Contact $contact, array $phones, array $emails): void
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

    private function stashPath(int $userId, string $token): string
    {
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
}
