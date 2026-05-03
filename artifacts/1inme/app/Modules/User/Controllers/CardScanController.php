<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BiolinkWizardDraft;
use App\Modules\User\Models\CardScan;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\ContactPhone;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\CardBrochureExtractionService;
use App\Services\AI\InsufficientAiCreditsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Creator-facing endpoints for the AI card / brochure scanner.
 *
 * Routes:
 *   GET  /contacts/scan                start screen (upload form)
 *   POST /contacts/scan                upload + run extraction
 *   GET  /contacts/scan/{scan}         review screen
 *   POST /contacts/scan/{scan}/save    create Contact and/or Biolink draft
 *
 * Every write is workspace-scoped via {@see workspace_owner_id()};
 * actions in a team workspace create the resulting Contact / Biolink
 * draft on the workspace owner, never on the acting member.
 */
class CardScanController extends Controller
{
    public function __construct(protected CardBrochureExtractionService $extractor) {}

    /** Upload form. Shared between Contacts entry and the wizard shortcut. */
    public function create(Request $request)
    {
        $from = $request->query('from') === 'wizard' ? 'wizard' : 'contacts';
        return view('user.contacts.scan_create', [
            'from'      => $from,
            'maxMb'     => CardBrochureExtractionService::MAX_UPLOAD_MB,
            'maxPages'  => CardBrochureExtractionService::MAX_PDF_PAGES,
            'engineOn'  => AiEngineSettings::isEnabled() && (bool) AiEngineSettings::openAiKey(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            // Cap matches the service-level guard. Laravel takes KB.
            'file' => 'required|file|max:' . (CardBrochureExtractionService::MAX_UPLOAD_MB * 1024),
            'from' => 'nullable|string|in:contacts,wizard',
        ]);

        $owner = workspace_owner();
        $actor = $request->user();

        if (!AiEngineSettings::isEnabled() || !AiEngineSettings::openAiKey()) {
            return back()->with('error', 'AI scanning is currently unavailable. Please try again later.');
        }

        try {
            $scan = $this->extractor->extract($owner, $actor, $request->file('file'));
        } catch (InsufficientAiCreditsException $e) {
            return redirect()->route('user.ai-credits.show')
                ->with('error', "You need {$e->required} AI credits to scan a card (you have {$e->balance}).");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'We couldn\'t scan that file. Please try again with a clearer image.');
        }

        return redirect()->route('user.contacts.scan.show', [
            'scan' => $scan->id,
            'from' => $request->input('from', 'contacts'),
        ]);
    }

    public function show(Request $request, CardScan $scan)
    {
        $this->authorizeScan($scan);

        $scan->loadMissing('sourceFile', 'contact', 'wizardDraft');

        $extracted = is_array($scan->extracted) ? $scan->extracted : [];

        // Surface any potential duplicates by email/phone so the user
        // can decide whether to merge before creating a new contact.
        $duplicates = $this->findDuplicates($extracted);

        $from = $request->query('from') === 'wizard' ? 'wizard' : 'contacts';

        return view('user.contacts.scan_show', [
            'scan'       => $scan,
            'extracted'  => $extracted,
            'duplicates' => $duplicates,
            'from'       => $from,
        ]);
    }

    /**
     * Persist the user's edited extraction as a Contact, a Biolink wizard
     * draft, or both.
     */
    public function save(Request $request, CardScan $scan)
    {
        $this->authorizeScan($scan);

        if ($scan->status !== 'completed') {
            return back()->with('error', 'This scan isn\'t ready yet.');
        }

        $data = $request->validate([
            'create_contact' => 'nullable|boolean',
            'create_biolink' => 'nullable|boolean',

            'full_name'   => 'nullable|string|max:191',
            'first_name'  => 'nullable|string|max:191',
            'last_name'   => 'nullable|string|max:191',
            'title'       => 'nullable|string|max:191',
            'company'     => 'nullable|string|max:191',
            'tagline'     => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'website'     => 'nullable|string|max:500',
            'address'     => 'nullable|string|max:500',

            'emails'                 => 'nullable|array|max:10',
            'emails.*.value'         => 'nullable|email|max:191',
            'emails.*.label'         => 'nullable|string|max:50',
            'phones'                 => 'nullable|array|max:10',
            'phones.*.value'         => 'nullable|string|max:80',
            'phones.*.label'         => 'nullable|string|max:50',

            'socials'              => 'nullable|array',
            'socials.instagram'    => 'nullable|string|max:100',
            'socials.tiktok'       => 'nullable|string|max:100',
            'socials.youtube'      => 'nullable|string|max:100',
            'socials.twitter'      => 'nullable|string|max:100',
            'socials.linkedin'     => 'nullable|string|max:100',
            'socials.facebook'     => 'nullable|string|max:100',
        ]);

        $wantContact = (bool) ($data['create_contact'] ?? false);
        $wantBiolink = (bool) ($data['create_biolink'] ?? false);

        if (!$wantContact && !$wantBiolink) {
            return back()->with('error', 'Pick at least one: save as contact, or create a biolink draft.');
        }

        $owner = workspace_owner();
        $actor = $request->user();

        $contact = null;
        $draft   = null;

        DB::transaction(function () use ($scan, $owner, $actor, $data, $wantContact, $wantBiolink, &$contact, &$draft) {
            if ($wantContact) {
                $contact = $this->createContact($owner, $data);
                $scan->contact_id = $contact->id;
            }
            if ($wantBiolink) {
                $draft = $this->createWizardDraft($owner, $actor, $data);
                $scan->wizard_draft_id = $draft->id;
            }
            $scan->save();
        });

        if ($wantBiolink) {
            return redirect()->route('user.links.wizard')
                ->with('success', 'Biolink draft seeded from the scan — pick up where you left off.');
        }

        return redirect()->route('user.contacts.show', $contact)
            ->with('success', 'Contact saved from the scan.');
    }

    // ---- helpers ---------------------------------------------------------

    protected function authorizeScan(CardScan $scan): void
    {
        abort_if($scan->user_id !== workspace_owner_id(), 403);
    }

    /**
     * Identify existing contacts that share an email or phone with the
     * extracted scan so the review UI can surface a soft-warning.
     *
     * @return array<int,array{type:string,value:string,contacts:array<int,array{id:int,name:string}>}>
     */
    protected function findDuplicates(array $extracted): array
    {
        $ownerId = workspace_owner_id();
        $hits = [];

        foreach (($extracted['emails'] ?? []) as $row) {
            $val = is_string($row['value'] ?? null) ? ContactEmail::normalize($row['value']) : '';
            if ($val === '') continue;
            $matches = Contact::where('user_id', $ownerId)
                ->whereHas('emails', fn ($q) => $q->where('value', $val))
                ->limit(3)->get(['id', 'display_name', 'given_name', 'family_name']);
            if ($matches->isNotEmpty()) {
                $hits[] = [
                    'type'  => 'email', 'value' => $val,
                    'contacts' => $matches->map(fn ($c) => ['id' => $c->id, 'name' => $c->nameForDisplay()])->all(),
                ];
            }
        }
        foreach (($extracted['phones'] ?? []) as $row) {
            $val = is_string($row['value'] ?? null) ? ContactPhone::normalize($row['value']) : '';
            if ($val === '') continue;
            $matches = Contact::where('user_id', $ownerId)
                ->whereHas('phones', fn ($q) => $q->where('value_e164', $val))
                ->limit(3)->get(['id', 'display_name', 'given_name', 'family_name']);
            if ($matches->isNotEmpty()) {
                $hits[] = [
                    'type'  => 'phone', 'value' => $val,
                    'contacts' => $matches->map(fn ($c) => ['id' => $c->id, 'name' => $c->nameForDisplay()])->all(),
                ];
            }
        }
        return $hits;
    }

    protected function createContact($owner, array $data): Contact
    {
        $display = trim((string) ($data['full_name'] ?? '')) ?: trim(
            ((string) ($data['first_name'] ?? '')) . ' ' . ((string) ($data['last_name'] ?? ''))
        );
        $contact = Contact::create([
            'user_id'             => $owner->id,
            'display_name'        => $display ?: ($data['company'] ?? null),
            'given_name'          => $data['first_name'] ?? null,
            'family_name'         => $data['last_name']  ?? null,
            'organization'        => $data['company']    ?? null,
            'job_title'           => $data['title']      ?? null,
            'notes'               => $this->composeNotes($data),
            'locally_modified_at' => now(),
        ]);

        foreach (($data['phones'] ?? []) as $p) {
            $val = trim((string) ($p['value'] ?? ''));
            if ($val === '') continue;
            $contact->phones()->create([
                'label'      => $p['label'] ?? null,
                'value'      => $val,
                'value_e164' => ContactPhone::normalize($val),
                'is_primary' => false,
            ]);
        }
        foreach (($data['emails'] ?? []) as $e) {
            $val = trim((string) ($e['value'] ?? ''));
            if ($val === '') continue;
            $contact->emails()->create([
                'label'      => $e['label'] ?? null,
                'value'      => ContactEmail::normalize($val),
                'is_primary' => false,
            ]);
        }
        return $contact->load(['phones', 'emails']);
    }

    /** Optional notes column — pack tagline / address / website if present. */
    protected function composeNotes(array $data): ?string
    {
        $bits = [];
        foreach (['tagline', 'description', 'website', 'address'] as $k) {
            $v = trim((string) ($data[$k] ?? ''));
            if ($v !== '') $bits[] = ucfirst($k) . ': ' . $v;
        }
        return $bits ? implode("\n", $bits) : null;
    }

    /**
     * Seed a BiolinkWizardDraft using extracted answer keys that
     * BiolinkPageRecipes already understands, so the user lands on the
     * wizard's question step with most fields pre-populated.
     */
    protected function createWizardDraft($owner, $actor, array $data): BiolinkWizardDraft
    {
        $answers = array_filter([
            // Recipes look for the first non-empty of these for the title.
            'display_name' => trim((string) ($data['full_name'] ?? '')) ?: null,
            'business_name'=> $data['company'] ?? null,
            'tagline'      => $data['tagline'] ?? null,
            'bio'          => $data['description'] ?? null,
            'website'      => $data['website'] ?? null,
            'address'      => $data['address'] ?? null,
            'job_title'    => $data['title'] ?? null,
            'instagram'    => $data['socials']['instagram'] ?? null,
            'tiktok'       => $data['socials']['tiktok']    ?? null,
            'youtube'      => $data['socials']['youtube']   ?? null,
            'twitter'      => $data['socials']['twitter']   ?? null,
            'linkedin'     => $data['socials']['linkedin']  ?? null,
            'facebook'     => $data['socials']['facebook']  ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        // First detected email/phone goes into the recipes' single-value slots.
        $firstEmail = collect($data['emails'] ?? [])
            ->pluck('value')->filter(fn ($v) => is_string($v) && trim($v) !== '')->first();
        $firstPhone = collect($data['phones'] ?? [])
            ->pluck('value')->filter(fn ($v) => is_string($v) && trim($v) !== '')->first();
        if ($firstEmail) $answers['email'] = $firstEmail;
        if ($firstPhone) $answers['phone'] = $firstPhone;

        return BiolinkWizardDraft::create([
            'user_id'       => $owner->id,
            'actor_user_id' => $actor->id,
            'workspace_id'  => $owner->id,
            'category'      => 'business',
            'page_type'     => null,
            'industry'      => null,
            'step'          => 1,
            'answers'       => $answers,
        ]);
    }
}
