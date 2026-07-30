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
use App\Services\AI\InsufficientCoinsForAiException;
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
        // Plan gate: when the user's plan doesn't unlock the Card & Brochure
        // Scanner, send them to the self-serve upgrade page instead of the
        // uploader (falls back to "on" when the plan predates the key).
        if (!\App\Services\AI\AiPlanAccess::featureAllowed($request->user(), 'card_scan')) {
            return view('user.ai.disabled', [
                'title'       => 'Card & Brochure Scanner',
                'upgradePlan' => \App\Services\AI\AiPlanAccess::featureUpgradePlan($request->user(), 'card_scan'),
            ]);
        }

        $from = $request->query('from') === 'wizard' ? 'wizard' : 'contacts';
        return view('user.contacts.scan_create', [
            'from'      => $from,
            'maxMb'      => CardBrochureExtractionService::MAX_UPLOAD_MB,
            'maxPages'   => CardBrochureExtractionService::MAX_PDF_PAGES,
            'maxUploads' => CardBrochureExtractionService::MAX_UPLOADS,
            'engineOn'  => AiEngineSettings::isEnabled() && (bool) AiEngineSettings::openAiKey(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            // Accept either a single file or an array of files. Cap
            // matches the service-level guard. Laravel takes KB.
            'file'        => 'nullable|file|max:' . (CardBrochureExtractionService::MAX_UPLOAD_MB * 1024),
            'files'       => 'nullable|array|max:' . CardBrochureExtractionService::MAX_UPLOADS,
            'files.*'     => 'file|max:' . (CardBrochureExtractionService::MAX_UPLOAD_MB * 1024),
            'from'        => 'nullable|string|in:contacts,wizard',
            'instruction' => 'nullable|string|max:' . CardBrochureExtractionService::MAX_INSTRUCTION_LENGTH,
        ]);

        $uploads = (array) ($request->file('files') ?? []);
        if (!$uploads && $request->hasFile('file')) {
            $uploads = [$request->file('file')];
        }
        if (!$uploads) {
            return back()->with('error', 'Please attach at least one image or PDF.');
        }

        $owner = workspace_owner();
        $actor = $request->user();

        if (!\App\Services\AI\AiPlanAccess::featureAllowed($actor, 'card_scan')) {
            $plan = \App\Services\AI\AiPlanAccess::featureUpgradePlan($actor, 'card_scan');
            $msg  = 'The Card & Brochure Scanner is not available on your current plan.';
            if ($plan) {
                $msg .= ' Upgrade to the ' . $plan->name . ' plan to use it.';
            }
            return back()->with('error', $msg);
        }

        if (!AiEngineSettings::isEnabled() || !AiEngineSettings::openAiKey()) {
            return back()->with('error', 'AI scanning is currently unavailable. Please try again later.');
        }

        $instruction = $request->input('instruction') !== null
            ? trim((string) $request->input('instruction'))
            : null;
        if ($instruction === '') $instruction = null;

        try {
            $scan = $this->extractor->extract($owner, $actor, $uploads, $instruction);
        } catch (InsufficientCoinsForAiException $e) {
            return redirect()->route('user.wallet.buy')
                ->with('error', "You need {$e->required} coins to scan a card (you have {$e->balance}).");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'We couldn\'t scan that file. Please try again with a clearer image.');
        }

        $from = $request->input('from', 'contacts');

        // Quick-confirm flow: when the upload came from the contacts or dialer
        // entry points, skip the full review and land on the streamlined
        // confirm sheet so the user can save in one tap.
        if (in_array($from, ['contacts', 'dialer'], true)) {
            return redirect()->route('user.contacts.scan.confirm', [
                'scan' => $scan->id,
                'from' => $from,
            ]);
        }

        return redirect()->route('user.contacts.scan.show', [
            'scan' => $scan->id,
            'from' => $from,
        ]);
    }

    /**
     * Re-run extraction on the SAME vaulted source files with a new
     * instruction — no re-upload required. Because the instruction is
     * folded into the idempotency key, a different focus always produces a
     * fresh CardScan row, leaving the original scan (and its saved
     * contact/draft links) intact for comparison.
     */
    public function rescan(Request $request, CardScan $scan)
    {
        $this->authorizeScan($scan);

        $request->validate([
            'instruction' => 'nullable|string|max:' . CardBrochureExtractionService::MAX_INSTRUCTION_LENGTH,
            'from'        => 'nullable|string|in:contacts,wizard',
        ]);

        $owner = workspace_owner();
        $actor = $request->user();

        if (!\App\Services\AI\AiPlanAccess::featureAllowed($actor, 'card_scan')) {
            $plan = \App\Services\AI\AiPlanAccess::featureUpgradePlan($actor, 'card_scan');
            $msg  = 'The Card & Brochure Scanner is not available on your current plan.';
            if ($plan) {
                $msg .= ' Upgrade to the ' . $plan->name . ' plan to use it.';
            }
            return back()->with('error', $msg);
        }

        if (!AiEngineSettings::isEnabled() || !AiEngineSettings::openAiKey()) {
            return back()->with('error', 'AI scanning is currently unavailable. Please try again later.');
        }

        $sourceFiles = $scan->sourceFiles()->all();
        if (!$sourceFiles) {
            return back()->with('error', 'The original files for this scan are no longer available — please upload again.');
        }

        $instruction = $request->input('instruction') !== null
            ? trim((string) $request->input('instruction'))
            : null;
        if ($instruction === '') $instruction = null;

        try {
            $newScan = $this->extractor->extractFromVaultedFiles($owner, $actor, $sourceFiles, $instruction);
        } catch (InsufficientCoinsForAiException $e) {
            return redirect()->route('user.wallet.buy')
                ->with('error', "You need {$e->required} coins to scan a card (you have {$e->balance}).");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'We couldn\'t re-scan that card. Please try again.');
        }

        return redirect()->route('user.contacts.scan.show', [
            'scan' => $newScan->id,
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

            // Brand colours extracted from the card/brochure. Applied to
            // the seeded biolink theme only when the user keeps them.
            'use_brand_colors'      => 'nullable|boolean',
            'brand_color_primary'   => 'nullable|string|max:7',
            'brand_color_secondary' => 'nullable|string|max:7',

            // Products lifted from a brochure → seed native Product blocks.
            'products'               => 'nullable|array|max:6',
            'products.*.name'        => 'nullable|string|max:191',
            'products.*.description' => 'nullable|string|max:500',
            'products.*.price'       => 'nullable|string|max:50',
        ]);

        $wantContact = (bool) ($data['create_contact'] ?? false);
        $wantBiolink = (bool) ($data['create_biolink'] ?? false);

        if (!$wantContact && !$wantBiolink) {
            return back()->with('error', 'Pick at least one: save as contact, or create a Link in Bio draft.');
        }

        $owner = workspace_owner();
        $actor = $request->user();

        // Enforce contacts plan limits inline (we removed the route-level
        // CheckPlanLimit middleware so a biolink-only save still works
        // when the contacts quota is full).
        if ($wantContact) {
            // getPlanFeature honors the user.plan_limits.bypass permission and addons.
            $maxContacts = (int) $owner->getPlanFeature('contacts_max', -1);
            if ($maxContacts !== -1 && $owner->contacts()->count() >= $maxContacts) {
                return back()->with('error',
                    "You've reached your plan's contact limit ({$maxContacts}). Upgrade your plan, or save just the Link in Bio draft instead.");
            }
        }

        $contact = null;
        $draft   = null;

        DB::transaction(function () use ($scan, $owner, $actor, $data, $wantContact, $wantBiolink, &$contact, &$draft) {
            if ($wantContact) {
                $contact = $this->createContact($owner, $data);
                $scan->contact_id = $contact->id;
            }
            if ($wantBiolink) {
                $draft = $this->createWizardDraft($owner, $actor, $data, $scan);
                $scan->wizard_draft_id = $draft->id;
            }
            $scan->save();
        });

        if ($wantBiolink) {
            return redirect()->route('user.links.wizard')
                ->with('success', 'Link in Bio draft seeded from the scan — pick up where you left off.');
        }

        return redirect()->route('user.contacts.show', $contact)
            ->with('success', 'Contact saved from the scan.');
    }

    /**
     * Streamlined confirm sheet: shows just the key contact fields extracted
     * from the scan (name, phones, emails, org/title) and offers a one-tap
     * "Save as contact" CTA. Reached from the contacts-page and dialer
     * entry points after a successful scan.
     */
    public function confirm(Request $request, CardScan $scan)
    {
        $this->authorizeScan($scan);
        $scan->loadMissing('sourceFile');

        if ($scan->status !== 'completed') {
            // Fall back to the full review page if the scan isn't ready.
            return redirect()->route('user.contacts.scan.show', [
                'scan' => $scan->id,
                'from' => $request->query('from', 'contacts'),
            ]);
        }

        $extracted  = is_array($scan->extracted) ? $scan->extracted : [];
        $duplicates = $this->findDuplicates($extracted);
        $from       = $request->query('from') === 'dialer' ? 'dialer' : 'contacts';

        return view('user.contacts.scan_confirm', [
            'scan'       => $scan,
            'extracted'  => $extracted,
            'duplicates' => $duplicates,
            'from'       => $from,
        ]);
    }

    /**
     * Fast-save path from the confirm sheet.
     *
     * Two modes:
     *  • New contact (default) — creates a fresh contact from the scan data.
     *  • Update existing (`update_contact_id`) — appends any new phones/emails
     *    from the scan to an existing contact, never clobbering existing rows.
     *
     * Plan-limit and ownership checks are enforced in both modes.
     */
    public function quickSave(Request $request, CardScan $scan)
    {
        $this->authorizeScan($scan);

        if ($scan->status !== 'completed') {
            return back()->with('error', 'This scan isn\'t ready yet — please wait a moment and try again.');
        }

        $request->validate([
            'update_contact_id' => 'nullable|integer',
        ]);

        $owner    = workspace_owner();
        $extracted = is_array($scan->extracted) ? $scan->extracted : [];

        $updateId = $request->integer('update_contact_id', 0);

        if ($updateId > 0) {
            // ── Update-existing mode ────────────────────────────────────────
            $contact = Contact::where('user_id', $owner->id)->find($updateId);
            if (!$contact) {
                return back()->with('error', 'That contact could not be found in your address book.');
            }

            DB::transaction(function () use ($contact, $extracted, $scan) {
                // Append phones that aren't already on the contact.
                $existingPhones = $contact->phones()->pluck('value')->map(
                    fn ($v) => ContactPhone::normalize($v)
                )->filter()->values()->all();

                foreach (($extracted['phones'] ?? []) as $p) {
                    $val = trim((string) ($p['value'] ?? ''));
                    if ($val === '') continue;
                    $norm = ContactPhone::normalize($val);
                    if (in_array($norm, $existingPhones, true)) continue;
                    $contact->phones()->create([
                        'label'      => $p['label'] ?? null,
                        'value'      => $val,
                        'value_e164' => $norm,
                        'is_primary' => false,
                    ]);
                    $existingPhones[] = $norm;
                }

                // Append emails that aren't already on the contact.
                $existingEmails = $contact->emails()->pluck('value')->map(
                    fn ($v) => ContactEmail::normalize($v)
                )->filter()->values()->all();

                foreach (($extracted['emails'] ?? []) as $e) {
                    $val = trim((string) ($e['value'] ?? ''));
                    if ($val === '') continue;
                    $norm = ContactEmail::normalize($val);
                    if (in_array($norm, $existingEmails, true)) continue;
                    $contact->emails()->create([
                        'label'      => $e['label'] ?? null,
                        'value'      => $norm,
                        'is_primary' => false,
                    ]);
                    $existingEmails[] = $norm;
                }

                // Link the scan to this contact.
                if (!$scan->contact_id) {
                    $scan->contact_id = $contact->id;
                    $scan->save();
                }
            });

            return redirect()->route('user.contacts.show', $contact)
                ->with('success', 'New phone and email details added to ' . $contact->nameForDisplay() . '.');
        }

        // ── Create-new mode ────────────────────────────────────────────────
        // getPlanFeature honors the user.plan_limits.bypass permission and addons.
        $maxContacts = (int) $owner->getPlanFeature('contacts_max', -1);
        if ($maxContacts !== -1 && $owner->contacts()->count() >= $maxContacts) {
            return back()->with('error',
                "You've reached your plan's contact limit ({$maxContacts}). Upgrade your plan to save more contacts.");
        }

        $contact = null;
        DB::transaction(function () use ($scan, $owner, $extracted, &$contact) {
            $contact = $this->createContact($owner, [
                'full_name'  => $extracted['full_name']  ?? null,
                'first_name' => $extracted['first_name'] ?? null,
                'last_name'  => $extracted['last_name']  ?? null,
                'company'    => $extracted['company']    ?? null,
                'title'      => $extracted['title']      ?? null,
                'tagline'    => $extracted['tagline']    ?? null,
                'description'=> $extracted['description']?? null,
                'website'    => $extracted['website']    ?? null,
                'address'    => $extracted['address']    ?? null,
                'phones'     => $extracted['phones']     ?? [],
                'emails'     => $extracted['emails']     ?? [],
            ]);
            $scan->contact_id = $contact->id;
            $scan->save();
        });

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

    /** Validate a #RRGGBB hex string (case-insensitive). Returns uppercase or null. */
    protected function normaliseHex($v): ?string
    {
        if (!is_string($v)) return null;
        $v = strtoupper(trim($v));
        return preg_match('/^#[0-9A-F]{6}$/', $v) ? $v : null;
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
    protected function createWizardDraft($owner, $actor, array $data, ?CardScan $scan = null): BiolinkWizardDraft
    {
        // Pre-fill the wizard's avatar step. We prefer the AI-cropped
        // logo (saved to the vault by CardBrochureExtractionService) when
        // it's available, and fall back to the raw upload for image
        // sources so the user always lands on the wizard with a real
        // brand image already attached.
        $extracted = is_array($scan?->extracted) ? $scan->extracted : [];
        $avatarUrl = is_string($extracted['logo_url'] ?? null) && $extracted['logo_url'] !== ''
            ? $extracted['logo_url']
            : null;
        if (!$avatarUrl && $scan && $scan->sourceFile && str_starts_with((string) $scan->sourceFile->mime_type, 'image/')) {
            $avatarUrl = $scan->sourceFile->url;
        }

        $answers = array_filter([
            'avatar'       => $avatarUrl,
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

        // Brand colours → theme the seeded page. `brand_color` is the key
        // BiolinkPageRecipes maps onto biolink.theme_color; the secondary
        // is preserved alongside it for future accent use. Only applied
        // when the user opted to keep them on the review screen.
        if (!empty($data['use_brand_colors'])) {
            if ($primary = $this->normaliseHex($data['brand_color_primary'] ?? null)) {
                $answers['brand_color'] = $primary;
            }
            if ($secondary = $this->normaliseHex($data['brand_color_secondary'] ?? null)) {
                $answers['brand_color_secondary'] = $secondary;
            }
        }

        // Products lifted from a brochure → seed native Product blocks.
        // Stored as a flat list under `scanned_products`, which
        // BiolinkPageRecipes emits as `product` blocks regardless of the
        // chosen page type. Rows without a name are dropped.
        $products = [];
        foreach ((array) ($data['products'] ?? []) as $p) {
            if (!is_array($p)) continue;
            $name = trim((string) ($p['name'] ?? ''));
            if ($name === '') continue;
            $products[] = [
                'name'        => $name,
                'description' => trim((string) ($p['description'] ?? '')),
                'price'       => trim((string) ($p['price'] ?? '')),
            ];
            if (count($products) >= 6) break;
        }
        if ($products) {
            $answers['scanned_products'] = $products;
        }

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
