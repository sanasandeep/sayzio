<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\BiolinkWizardDraft;
use App\Modules\User\Models\CardScan;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\ContactPhone;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\CardBrochureExtractionService;
use App\Services\AI\InsufficientCoinsForAiException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Mobile (Sanctum) parity for the web "Scan a card / brochure" feature
 * (see App\Modules\User\Controllers\CardScanController for the web flow).
 *
 * Routes (all under /api/v1, auth:sanctum):
 *   POST /card-scans                 upload one or more images / PDFs and run
 *                                    AI extraction; returns the parsed scan.
 *   GET  /card-scans/{scan}          fetch a scan + duplicate-contact hints.
 *   POST /card-scans/{scan}/save     persist the (edited) extraction as a
 *                                    Contact and/or seed a Biolink wizard draft.
 *
 * The heavy lifting — vault, rasterise, vision call, credit metering and
 * auto-refund — is delegated to the shared
 * {@see CardBrochureExtractionService} so the two surfaces never drift. The
 * same limits apply (6 files, 10 MB each, 4 PDF pages, the AI-credit
 * affordability gate, and the "AI scanning disabled" state).
 *
 * Workspace note: the Sanctum path never runs SetActiveWorkspace, so any
 * BelongsToWorkspace model created here would land with workspace_id = null
 * and be hidden from the workspace-scoped web lists. We resolve and stamp the
 * active workspace id onto the saved Contact so a card scanned on mobile still
 * appears in the creator's Contacts on the website. The CardScan row itself is
 * an internal artifact (no list view), so it follows the standard API
 * behaviour and is always re-fetched by id.
 */
class CardScanController extends Controller
{
    use ApiResponses;

    public function __construct(protected CardBrochureExtractionService $extractor) {}

    /** Upload + run extraction. Mirrors the web store(). */
    public function store(Request $request)
    {
        $request->validate([
            'file'        => 'nullable|file|max:' . (CardBrochureExtractionService::MAX_UPLOAD_MB * 1024),
            'files'       => 'nullable|array|max:' . CardBrochureExtractionService::MAX_UPLOADS,
            'files.*'     => 'file|max:' . (CardBrochureExtractionService::MAX_UPLOAD_MB * 1024),
            'instruction' => 'nullable|string|max:' . CardBrochureExtractionService::MAX_INSTRUCTION_LENGTH,
        ]);

        $uploads = (array) ($request->file('files') ?? []);
        if (!$uploads && $request->hasFile('file')) {
            $uploads = [$request->file('file')];
        }
        if (!$uploads) {
            return $this->fail('Please attach at least one image or PDF.', 422, 'no_files');
        }

        $user = $request->user();

        if (!\App\Services\AI\AiPlanAccess::featureAllowed($user, 'card_scan')) {
            $plan = \App\Services\AI\AiPlanAccess::featureUpgradePlan($user, 'card_scan');
            $msg  = 'The Card & Brochure Scanner is not available on your current plan.';
            if ($plan) {
                $msg .= ' Upgrade to the ' . $plan->name . ' plan to use it.';
            }
            return $this->fail($msg, 403, 'plan_upgrade_required', ['upgrade_plan' => $plan?->slug]);
        }

        if (!AiEngineSettings::isEnabled() || !AiEngineSettings::openAiKey()) {
            return $this->fail(
                'AI scanning is currently unavailable. Please try again later.',
                503,
                'ai_unavailable',
            );
        }

        $instruction = $request->input('instruction') !== null
            ? trim((string) $request->input('instruction'))
            : null;
        if ($instruction === '') $instruction = null;

        try {
            $scan = $this->extractor->extract($user, $user, $uploads, $instruction);
        } catch (InsufficientCoinsForAiException $e) {
            return $this->fail(
                "You need {$e->required} coins to scan a card (you have {$e->balance}).",
                402,
                'insufficient_credits',
                ['required' => $e->required, 'balance' => $e->balance],
            );
        } catch (\RuntimeException $e) {
            // Caller-fixable validation problems (mime / size / page count).
            return $this->fail($e->getMessage(), 422, 'invalid_upload');
        } catch (Throwable $e) {
            report($e);
            return $this->fail(
                'We couldn\'t scan that file. Please try again with a clearer image.',
                500,
                'scan_failed',
            );
        }

        if ($scan->status !== 'completed') {
            return $this->fail(
                'That scan didn\'t complete. Please try again with a clearer image.',
                422,
                'scan_incomplete',
            );
        }

        return $this->created([
            'scan'       => $this->presentScan($scan),
            'duplicates' => $this->findDuplicates($user, is_array($scan->extracted) ? $scan->extracted : []),
        ]);
    }

    /**
     * Re-run extraction on the SAME vaulted source files with a new
     * instruction — no re-upload required. Mirrors the web rescan(): the
     * instruction is folded into the idempotency key, so a different focus
     * always produces a fresh CardScan row, leaving the original scan (and
     * its saved contact/draft links) intact for comparison.
     */
    public function rescan(Request $request, int $scan)
    {
        $user  = $request->user();
        $model = $this->resolveScan($user, $scan);
        if (!$model) {
            return $this->notFound('Scan not found.');
        }

        $request->validate([
            'instruction' => 'nullable|string|max:' . CardBrochureExtractionService::MAX_INSTRUCTION_LENGTH,
        ]);

        if (!\App\Services\AI\AiPlanAccess::featureAllowed($user, 'card_scan')) {
            $plan = \App\Services\AI\AiPlanAccess::featureUpgradePlan($user, 'card_scan');
            $msg  = 'The Card & Brochure Scanner is not available on your current plan.';
            if ($plan) {
                $msg .= ' Upgrade to the ' . $plan->name . ' plan to use it.';
            }
            return $this->fail($msg, 403, 'plan_upgrade_required', ['upgrade_plan' => $plan?->slug]);
        }

        if (!AiEngineSettings::isEnabled() || !AiEngineSettings::openAiKey()) {
            return $this->fail(
                'AI scanning is currently unavailable. Please try again later.',
                503,
                'ai_unavailable',
            );
        }

        $sourceFiles = $model->sourceFiles()->all();
        if (!$sourceFiles) {
            return $this->fail(
                'The original files for this scan are no longer available — please upload again.',
                422,
                'source_files_missing',
            );
        }

        $instruction = $request->input('instruction') !== null
            ? trim((string) $request->input('instruction'))
            : null;
        if ($instruction === '') $instruction = null;

        try {
            $newScan = $this->extractor->extractFromVaultedFiles($user, $user, $sourceFiles, $instruction);
        } catch (InsufficientCoinsForAiException $e) {
            return $this->fail(
                "You need {$e->required} coins to scan a card (you have {$e->balance}).",
                402,
                'insufficient_credits',
                ['required' => $e->required, 'balance' => $e->balance],
            );
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422, 'invalid_upload');
        } catch (Throwable $e) {
            report($e);
            return $this->fail(
                'We couldn\'t re-scan that card. Please try again.',
                500,
                'scan_failed',
            );
        }

        return $this->created([
            'scan'       => $this->presentScan($newScan),
            'duplicates' => $this->findDuplicates($user, is_array($newScan->extracted) ? $newScan->extracted : []),
        ]);
    }

    /** Review screen — fetch a single scan + duplicate hints. */
    public function show(Request $request, int $scan)
    {
        $user  = $request->user();
        $model = $this->resolveScan($user, $scan);
        if (!$model) {
            return $this->notFound('Scan not found.');
        }

        return $this->ok([
            'scan'       => $this->presentScan($model),
            'duplicates' => $this->findDuplicates($user, is_array($model->extracted) ? $model->extracted : []),
        ]);
    }

    /** Persist the edited extraction as a Contact and/or a Biolink draft. */
    public function save(Request $request, int $scan)
    {
        $user  = $request->user();
        $model = $this->resolveScan($user, $scan);
        if (!$model) {
            return $this->notFound('Scan not found.');
        }
        if ($model->status !== 'completed') {
            return $this->fail('This scan isn\'t ready yet.', 422, 'scan_incomplete');
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

            'emails'         => 'nullable|array|max:10',
            'emails.*.value' => 'nullable|email|max:191',
            'emails.*.label' => 'nullable|string|max:50',
            'phones'         => 'nullable|array|max:10',
            'phones.*.value' => 'nullable|string|max:80',
            'phones.*.label' => 'nullable|string|max:50',

            'socials'           => 'nullable|array',
            'socials.instagram' => 'nullable|string|max:100',
            'socials.tiktok'    => 'nullable|string|max:100',
            'socials.youtube'   => 'nullable|string|max:100',
            'socials.twitter'   => 'nullable|string|max:100',
            'socials.linkedin'  => 'nullable|string|max:100',
            'socials.facebook'  => 'nullable|string|max:100',
        ]);

        $wantContact = (bool) ($data['create_contact'] ?? false);
        $wantBiolink = (bool) ($data['create_biolink'] ?? false);

        if (!$wantContact && !$wantBiolink) {
            return $this->fail(
                'Pick at least one: save as contact, or create a Link in Bio draft.',
                422,
                'nothing_selected',
            );
        }

        // Enforce the contacts plan limit inline (a biolink-only save still
        // succeeds when the contacts quota is full).
        if ($wantContact) {
            $features    = $user->plan?->features ?? [];
            $maxContacts = $features['contacts_max'] ?? -1;
            if ($maxContacts !== -1) {
                $usedContacts = $user->contacts()->count();
                if ($usedContacts >= $maxContacts) {
                    return $this->planGate(
                        "You've reached your plan's contact limit ({$maxContacts}). Upgrade your plan, or save just the Link in Bio draft instead.",
                        'contacts_max',
                        $user,
                        403,
                        'plan_limit',
                        $usedContacts,
                    );
                }
            }
        }

        $contact = null;
        $draft   = null;

        DB::transaction(function () use ($model, $user, $data, $wantContact, $wantBiolink, &$contact, &$draft) {
            if ($wantContact) {
                $contact = $this->createContact($user, $data);
                $model->contact_id = $contact->id;
            }
            if ($wantBiolink) {
                $draft = $this->createWizardDraft($user, $data, $model);
                $model->wizard_draft_id = $draft->id;
            }
            $model->save();
        });

        $payload = [];
        if ($contact) {
            $payload['contact'] = [
                'id'           => $contact->id,
                'display_name' => $contact->display_name,
            ];
        }
        if ($draft) {
            // The mobile wizard is stateless, so we return the seeded answers
            // (and the category the web draft uses) so the app can hand off to
            // its in-memory Guided Link-in-Bio wizard pre-filled.
            $payload['biolink'] = [
                'draft_id' => $draft->id,
                'category' => $draft->category,
                'answers'  => is_array($draft->answers) ? $draft->answers : [],
            ];
        }

        return $this->ok($payload);
    }

    // ── helpers ──────────────────────────────────────────────────────

    /**
     * Resolve a scan owned by the signed-in user. The Sanctum path binds no
     * current_workspace so the BelongsToWorkspace global scope is skipped; we
     * therefore authorise explicitly on user_id.
     */
    protected function resolveScan($user, int $id): ?CardScan
    {
        $scan = CardScan::withoutGlobalScope('workspace')->find($id);
        if (!$scan || $scan->user_id !== $user->id) {
            return null;
        }
        return $scan;
    }

    /** Flatten a CardScan into the mobile review payload. */
    protected function presentScan(CardScan $scan): array
    {
        $extracted = is_array($scan->extracted) ? $scan->extracted : [];

        $sourceImages = $scan->sourceFiles()
            ->filter(fn ($f) => $f && str_starts_with((string) $f->mime_type, 'image/'))
            ->map(fn ($f) => $f->url)
            ->values()
            ->all();

        return [
            'id'            => $scan->id,
            'status'        => $scan->status,
            'kind'          => $extracted['kind'] ?? 'card',
            'extracted'     => $extracted,
            'logo_url'      => $extracted['logo_url'] ?? null,
            'source_images' => $sourceImages,
            'credits_spent' => (int) $scan->credits_spent,
            'error'         => $scan->error,
        ];
    }

    /**
     * Existing contacts that share an email/phone with the extraction, so the
     * review UI can surface a soft "possible duplicate" warning.
     *
     * @return array<int,array{type:string,value:string,contacts:array<int,array{id:int,name:string}>}>
     */
    protected function findDuplicates($user, array $extracted): array
    {
        $hits = [];

        foreach (($extracted['emails'] ?? []) as $row) {
            $val = is_string($row['value'] ?? null) ? ContactEmail::normalize($row['value']) : '';
            if ($val === '') continue;
            $matches = Contact::where('user_id', $user->id)
                ->whereHas('emails', fn ($q) => $q->where('value', $val))
                ->limit(3)->get(['id', 'display_name', 'given_name', 'family_name']);
            if ($matches->isNotEmpty()) {
                $hits[] = [
                    'type'     => 'email',
                    'value'    => $val,
                    'contacts' => $matches->map(fn ($c) => ['id' => $c->id, 'name' => $c->nameForDisplay()])->all(),
                ];
            }
        }
        foreach (($extracted['phones'] ?? []) as $row) {
            $val = is_string($row['value'] ?? null) ? ContactPhone::normalize($row['value']) : '';
            if ($val === '') continue;
            $matches = Contact::where('user_id', $user->id)
                ->whereHas('phones', fn ($q) => $q->where('value_e164', $val))
                ->limit(3)->get(['id', 'display_name', 'given_name', 'family_name']);
            if ($matches->isNotEmpty()) {
                $hits[] = [
                    'type'     => 'phone',
                    'value'    => $val,
                    'contacts' => $matches->map(fn ($c) => ['id' => $c->id, 'name' => $c->nameForDisplay()])->all(),
                ];
            }
        }
        return $hits;
    }

    protected function createContact($user, array $data): Contact
    {
        $display = trim((string) ($data['full_name'] ?? '')) ?: trim(
            ((string) ($data['first_name'] ?? '')) . ' ' . ((string) ($data['last_name'] ?? ''))
        );

        $contact = new Contact([
            'user_id'             => $user->id,
            'display_name'        => $display ?: ($data['company'] ?? null),
            'given_name'          => $data['first_name'] ?? null,
            'family_name'         => $data['last_name']  ?? null,
            'organization'        => $data['company']    ?? null,
            'job_title'           => $data['title']      ?? null,
            'notes'               => $this->composeNotes($data),
            'locally_modified_at' => now(),
        ]);
        // Sanctum path: stamp the active workspace so the new contact shows up
        // in the workspace-scoped web Contacts list (workspace_id is guarded).
        $contact->workspace_id = $this->resolveWorkspaceId($user);
        $contact->save();

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

    /** Pack tagline / description / website / address into the notes column. */
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
     * Seed a BiolinkWizardDraft from the extracted answer keys that
     * BiolinkPageRecipes understands (mirrors the web createWizardDraft).
     */
    protected function createWizardDraft($user, array $data, CardScan $scan): BiolinkWizardDraft
    {
        $extracted = is_array($scan->extracted) ? $scan->extracted : [];
        $avatarUrl = is_string($extracted['logo_url'] ?? null) && $extracted['logo_url'] !== ''
            ? $extracted['logo_url']
            : null;
        if (!$avatarUrl && $scan->sourceFile && str_starts_with((string) $scan->sourceFile->mime_type, 'image/')) {
            $avatarUrl = $scan->sourceFile->url;
        }

        $answers = array_filter([
            'avatar'        => $avatarUrl,
            'display_name'  => trim((string) ($data['full_name'] ?? '')) ?: null,
            'business_name' => $data['company'] ?? null,
            'tagline'       => $data['tagline'] ?? null,
            'bio'           => $data['description'] ?? null,
            'website'       => $data['website'] ?? null,
            'address'       => $data['address'] ?? null,
            'job_title'     => $data['title'] ?? null,
            'instagram'     => $data['socials']['instagram'] ?? null,
            'tiktok'        => $data['socials']['tiktok']    ?? null,
            'youtube'       => $data['socials']['youtube']   ?? null,
            'twitter'       => $data['socials']['twitter']   ?? null,
            'linkedin'      => $data['socials']['linkedin']  ?? null,
            'facebook'      => $data['socials']['facebook']  ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $firstEmail = collect($data['emails'] ?? [])
            ->pluck('value')->filter(fn ($v) => is_string($v) && trim($v) !== '')->first();
        $firstPhone = collect($data['phones'] ?? [])
            ->pluck('value')->filter(fn ($v) => is_string($v) && trim($v) !== '')->first();
        if ($firstEmail) $answers['email'] = $firstEmail;
        if ($firstPhone) $answers['phone'] = $firstPhone;

        $draft = new BiolinkWizardDraft([
            'user_id'       => $user->id,
            'actor_user_id' => $user->id,
            'category'      => 'business',
            'page_type'     => null,
            'industry'      => null,
            'step'          => 1,
            'answers'       => $answers,
        ]);
        $draft->workspace_id = $this->resolveWorkspaceId($user);
        $draft->save();

        return $draft;
    }
}
