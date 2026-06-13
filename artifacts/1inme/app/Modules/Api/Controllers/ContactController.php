<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Services\ContactCandidateValidator;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\ContactPhone;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class ContactController extends Controller
{
    use ApiResponses;

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
        ]);
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
            ]);
            $c->workspace_id = $wsId;
            $c->save();
            $this->syncEmails($c, $data['emails'] ?? []);
            $this->syncPhones($c, $data['phones'] ?? []);
            return $c->fresh(['phones', 'emails']);
        });

        return $this->created([
            'contact'      => $this->transform($contact),
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
            return $existing->fresh(['phones', 'emails']);
        });

        return $this->ok(['contact' => $this->transform($contact)]);
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
            ])))->save();

            if (array_key_exists('emails', $data)) {
                $this->syncEmails($c, $data['emails'] ?? []);
            }
            if (array_key_exists('phones', $data)) {
                $this->syncPhones($c, $data['phones'] ?? []);
            }
            return $c->fresh(['phones', 'emails']);
        });

        return $this->ok(['contact' => $this->transform($contact)]);
    }

    public function destroy(Request $request, int $id)
    {
        $c = Contact::where('user_id', $request->user()->id)->find($id);
        if (!$c) return $this->notFound('Contact not found');
        $c->delete();
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
            'created_at'   => optional($c->created_at)->toIso8601String(),
        ];
    }
}
