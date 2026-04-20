<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessContactImportJob;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\ContactImport;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\ContactDeletionTombstone;
use App\Modules\User\Models\GoogleContactsAccount;
use App\Modules\User\Models\IntegrationConfig;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Services\Contacts\BiolinkAttachResolver;
use App\Modules\User\Services\Contacts\ContactImportParser;
use App\Modules\User\Services\Contacts\GoogleContactsSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ContactController extends Controller
{
    public const PHONE_LABELS = ['Mobile', 'Work', 'Home', 'Main', 'Other'];
    public const EMAIL_LABELS = ['Personal', 'Work', 'Other'];

    public function __construct(
        protected BiolinkAttachResolver $resolver,
        protected GoogleContactsSyncService $sync,
        protected ContactImportParser $importParser,
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

        $totalContacts = Contact::where('user_id', $user->id)->count();
        $stats = [
            'total'   => $totalContacts,
            'biolink' => Contact::where('user_id', $user->id)->whereNotNull('biolink_user_id')->count(),
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

        return view('user.contacts.index', compact('contacts', 'tab', 'search', 'googleAccount', 'stats', 'usage', 'activeImport'));
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

    // ---- bulk import ------------------------------------------------------

    public function importForm(Request $request)
    {
        $cap = $this->planContactsCap($request->user());
        $existing = Contact::where('user_id', $request->user()->id)->count();
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

        $existingCount = Contact::where('user_id', $user->id)->count();
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

    public function importCancel(Request $request, string $token)
    {
        $this->discardStash($request->user()->id, $token);
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
        $existingCount = Contact::where('user_id', $user->id)->count();
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
        abort_if($import->user_id !== $request->user()->id, 403);
        return view('user.contacts.import_summary', ['import' => $import]);
    }

    /** Tiny JSON endpoint the summary page polls while a job is running. */
    public function importStatus(Request $request, ContactImport $import)
    {
        abort_if($import->user_id !== $request->user()->id, 403);
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
     * Send the contact's matched 1INME biolink URL to one of their phone
     * numbers via a configured SMS gateway (Twilio today; other providers are
     * audit-logged until their HTTP transports are wired up). The Blade views
     * already provide a one-tap `sms:` deeplink for mobile devices — this
     * endpoint is the desktop fallback when the user has an SMS integration
     * configured.
     */
    public function smsBiolink(Request $request, Contact $contact)
    {
        abort_if($contact->user_id !== $request->user()->id, 403);
        $contact->loadMissing(['phones', 'biolinkUser']);

        $preview = $this->biolinkPreview($contact);
        if (!$preview || empty($preview['url'])) {
            return back()->with('error', 'No biolink available to text.');
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
            return back()->with('error', 'You can only text this biolink to a phone number saved on the contact.');
        }

        $userId = $request->user()->id;
        $configId = (int) $request->input('config_id', 0);
        $config = $configId
            ? IntegrationConfig::where('user_id', $userId)->where('id', $configId)->kind('sms')->active()->first()
            : IntegrationConfig::where('user_id', $userId)->kind('sms')->active()
                ->orderByDesc('is_default')->orderBy('id')->first();

        if (!$config) {
            return back()->with('error', 'No active SMS gateway configured. Add Twilio or Plivo under Integrations, or use the Text biolink button on a mobile device.');
        }

        $name    = $contact->nameForDisplay();
        $message = "Hey " . ($name ?: 'there') . ", here's my 1INME page: " . $preview['url'];

        try {
            $this->dispatchBiolinkSms($config, $toClean, $message);
        } catch (\Throwable $e) {
            \Log::warning('Biolink SMS send failed', ['err' => $e->getMessage(), 'config_id' => $config->id]);
            return back()->with('error', 'SMS gateway rejected the send: ' . $e->getMessage());
        }

        return back()->with('success', 'Biolink texted to ' . $toClean . ' via ' . $config->providerLabel() . '.');
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
                    'SMS provider "' . $config->provider . '" is not yet supported for biolink texting. '
                    . 'Use Twilio or Plivo, or text from a mobile device.'
                );
        }
    }

    // ---- helpers ----------------------------------------------------------

    /** Resolve the plan-based contacts_max cap. Returns -1 for unlimited. */
    public static function planContactsCap($user): int
    {
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
