<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\ContactDeletionTombstone;
use App\Modules\User\Models\GoogleContactsAccount;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Services\Contacts\BiolinkAttachResolver;
use App\Modules\User\Services\Contacts\GoogleContactsSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ContactController extends Controller
{
    public const PHONE_LABELS = ['Mobile', 'Work', 'Home', 'Main', 'Other'];
    public const EMAIL_LABELS = ['Personal', 'Work', 'Other'];
    public const SOFT_CAP     = 5000; // soft per-user cap

    public function __construct(
        protected BiolinkAttachResolver $resolver,
        protected GoogleContactsSyncService $sync,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $tab  = $request->query('tab') === 'biolink' ? 'biolink' : 'all';
        $search = trim((string) $request->query('q', ''));

        $query = Contact::where('user_id', $user->id)
            ->with(['phones', 'emails', 'biolinkUser']);

        if ($tab === 'biolink') $query->whereNotNull('biolink_user_id');
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

        $contacts = $query->orderBy('display_name')->paginate(40)->withQueryString();
        $googleAccount = GoogleContactsAccount::where('user_id', $user->id)->first();

        $stats = [
            'total'   => Contact::where('user_id', $user->id)->count(),
            'biolink' => Contact::where('user_id', $user->id)->whereNotNull('biolink_user_id')->count(),
        ];

        return view('user.contacts.index', compact('contacts', 'tab', 'search', 'googleAccount', 'stats'));
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
        if (Contact::where('user_id', $user->id)->count() >= self::SOFT_CAP) {
            return back()->with('error', 'Contact limit reached (' . self::SOFT_CAP . ').');
        }
        $v = $this->validatePayload($request);

        $contact = DB::transaction(function () use ($user, $v, $request) {
            $contact = Contact::create([
                'user_id'      => $user->id,
                'display_name' => $v['display_name'] ?: trim(($v['given_name'] ?? '') . ' ' . ($v['family_name'] ?? '')),
                'given_name'   => $v['given_name'] ?? null,
                'family_name'  => $v['family_name'] ?? null,
                'organization' => $v['organization'] ?? null,
                'job_title'    => $v['job_title'] ?? null,
                'notes'        => $v['notes'] ?? null,
                'photo_path'   => $request->hasFile('photo') ? $request->file('photo')->store('contact-photos', 'public') : null,
                'locally_modified_at' => now(),
            ]);
            $this->syncRows($contact, $v['phones'] ?? [], $v['emails'] ?? []);
            return $contact;
        });

        $this->resolver->resolveFor($contact->fresh('phones'));
        $this->pushToGoogleSafely($user->id, $contact);

        return redirect()->route('user.contacts.show', $contact)->with('success', 'Contact added.');
    }

    public function show(Request $request, Contact $contact)
    {
        abort_if($contact->user_id !== $request->user()->id, 403);
        $contact->load(['phones', 'emails', 'biolinkUser']);
        $biolinkPreview = $this->biolinkPreview($contact);
        return view('user.contacts.show', compact('contact', 'biolinkPreview'));
    }

    public function edit(Request $request, Contact $contact)
    {
        abort_if($contact->user_id !== $request->user()->id, 403);
        $contact->load(['phones', 'emails']);
        return view('user.contacts.edit', [
            'contact'     => $contact,
            'phoneLabels' => self::PHONE_LABELS,
            'emailLabels' => self::EMAIL_LABELS,
        ]);
    }

    public function update(Request $request, Contact $contact)
    {
        abort_if($contact->user_id !== $request->user()->id, 403);
        $v = $this->validatePayload($request);

        DB::transaction(function () use ($contact, $v, $request) {
            $payload = [
                'display_name' => $v['display_name'] ?: trim(($v['given_name'] ?? '') . ' ' . ($v['family_name'] ?? '')),
                'given_name'   => $v['given_name'] ?? null,
                'family_name'  => $v['family_name'] ?? null,
                'organization' => $v['organization'] ?? null,
                'job_title'    => $v['job_title'] ?? null,
                'notes'        => $v['notes'] ?? null,
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

        return redirect()->route('user.contacts.show', $contact)->with('success', 'Contact updated.');
    }

    public function destroy(Request $request, Contact $contact)
    {
        abort_if($contact->user_id !== $request->user()->id, 403);
        if ($contact->photo_path) Storage::disk('public')->delete($contact->photo_path);
        // Park a deletion tombstone before removing the row so the next sync
        // can finalise it on Google. Best-effort immediate attempt too — but
        // the tombstone is the source of truth and gets retried.
        if ($contact->google_contacts_account_id && $contact->google_resource_name) {
            ContactDeletionTombstone::create([
                'user_id'                    => $contact->user_id,
                'google_contacts_account_id' => $contact->google_contacts_account_id,
                'google_resource_name'       => $contact->google_resource_name,
            ]);
        }
        $contact->delete();
        return redirect()->route('user.contacts.index')->with('success', 'Contact deleted.');
    }

    public function detachBiolink(Request $request, Contact $contact)
    {
        abort_if($contact->user_id !== $request->user()->id, 403);
        if ($contact->biolink_user_id) {
            $this->resolver->detach($contact, $contact->biolink_user_id);
        }
        return back()->with('success', 'Biolink removed from this contact.');
    }

    public function attachBiolink(Request $request, Contact $contact)
    {
        abort_if($contact->user_id !== $request->user()->id, 403);
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
        return back()->with('success', 'Biolink reattached if a matching 1INME user was found.');
    }

    // ---- helpers ----------------------------------------------------------

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'display_name' => 'nullable|string|max:191',
            'given_name'   => 'nullable|string|max:191',
            'family_name'  => 'nullable|string|max:191',
            'organization' => 'nullable|string|max:191',
            'job_title'    => 'nullable|string|max:191',
            'notes'        => 'nullable|string|max:5000',
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

    public function biolinkPreview(Contact $contact): ?array
    {
        $u = $contact->biolinkUser;
        if (!$u) return null;
        $bio = \App\Modules\User\Models\Link::where('user_id', $u->id)
            ->where('type', 'biolink')->where('is_active', true)
            ->orderByDesc('id')->first();
        return [
            'user'   => $u,
            'link'   => $bio,
            'url'    => $bio ? url('/' . $bio->alias) : null,
        ];
    }
}
