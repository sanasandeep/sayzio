<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
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

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);

        $contact = DB::transaction(function () use ($data, $request) {
            $c = Contact::create([
                'user_id'      => $request->user()->id,
                'display_name' => $data['display_name'] ?? trim(
                    ($data['given_name'] ?? '') . ' ' . ($data['family_name'] ?? '')
                ) ?: null,
                'given_name'   => $data['given_name']   ?? null,
                'family_name'  => $data['family_name']  ?? null,
                'organization' => $data['organization'] ?? null,
                'job_title'    => $data['job_title']    ?? null,
                'notes'        => $data['notes']        ?? null,
            ]);
            $this->syncEmails($c, $data['emails'] ?? []);
            $this->syncPhones($c, $data['phones'] ?? []);
            return $c->fresh(['phones', 'emails']);
        });

        return $this->created(['contact' => $this->transform($contact)]);
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
