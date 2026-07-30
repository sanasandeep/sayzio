<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Middleware\CheckPlanLimit;
use App\Modules\User\Models\BiolinkWizardDraft;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\UserFile;
use App\Modules\Admin\Models\PageTemplate;
use App\Modules\User\Services\BiolinkPageRecipes;
use App\Modules\User\Services\BiolinkWizardGenerator;
use App\Modules\User\Services\BiolinkWizardQuestions;
use App\Modules\User\Services\PersonaCatalog;
use App\Modules\User\Services\WizardAiDraftService;
use App\Modules\User\Services\WizardStartingDesignService;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\InsufficientCoinsForAiException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Guided biolink creation wizard.
 *
 * Walks the user through 4 steps (industry → profile type [+ optional niche
 * refinement folded in] → basic profile & branding → additional content) and
 * then generates an opinionated biolink page from their answers using
 * BiolinkPageRecipes + TemplateService::applyPageToLink.
 *
 * State is held in a per-(user, workspace) row in `biolink_wizard_drafts` so
 * the user can close the tab and resume later. The draft is deleted once the
 * link is successfully created.
 *
 * All write actions are gated with `workspace.can:links.create` at the route
 * level. This controller never bypasses workspace ownership — it uses
 * `workspace_owner_id()` for the link's owner so a team member's wizard
 * creates a link in the workspace they were viewing, not on their personal
 * account.
 */
class BiolinkWizardController extends Controller
{
    public function __construct(
        private BiolinkWizardGenerator $generator,
        private WizardAiDraftService $aiDraft,
        private WizardStartingDesignService $startingDesigns,
    ) {}

    /**
     * Discard any in-flight draft and land on the first step. Useful for the
     * "Start over" entry point — the wizard auto-resumes by default.
     */
    public function start(Request $request)
    {
        $draft = $this->loadDraft($request);
        $draft?->delete();
        return redirect()->route('user.links.wizard');
    }

    /**
     * Explicit resume entry point. The wizard's index() already auto-resumes
     * the latest draft for this (user, workspace), so this is just a named
     * alias for deep links / emails.
     */
    public function resume(Request $request)
    {
        return redirect()->route('user.links.wizard');
    }

    /**
     * Background autosave — accepts a JSON `answers` patch and merges it into
     * the current draft without changing the step. Returns 204 on success so
     * the browser fetch() never has to parse a body.
     */
    public function draft(Request $request)
    {
        $draft = $this->loadDraft($request);
        if (!$draft) {
            // No draft yet — nothing to autosave. Return 204 anyway so the
            // client doesn't surface a spurious error.
            return response()->noContent();
        }

        $patch = $request->input('answers');
        if (!is_array($patch)) {
            return response()->json(['error' => 'invalid_payload'], 422);
        }

        // Drop anything that isn't scalar/array — defensive against junk
        // accidentally serialised from the page (e.g. File objects).
        $clean = [];
        foreach ($patch as $k => $v) {
            if (!is_string($k) || strlen($k) > 64) continue;
            if (is_scalar($v) || is_array($v) || $v === null) {
                $clean[$k] = $v;
            }
        }

        $existing = $draft->answers ?? [];
        $draft->answers = array_merge($existing, $clean);

        // Autosave the AI resource selections alongside the answers so the
        // chosen brains/files survive a refresh and feed the AI auto-draft.
        $this->applyResourceInputs($request, $draft);

        $draft->save();

        return response()->noContent();
    }

    /**
     * Pull the AI Brain (Mind) + vault-file selections off the request and
     * persist them onto the draft. Shared by the autosave endpoint and the
     * step-save flow. Tolerant of missing keys (leaves existing values).
     */
    protected function applyResourceInputs(Request $request, BiolinkWizardDraft $draft): void
    {
        // When the resource picker is actually on the submitted form it carries
        // a `_resources_present` sentinel. In that case the inputs are
        // AUTHORITATIVE: browsers omit unchecked boxes, so a missing array/flag
        // means "the user cleared it", not "leave as-is" — otherwise stale
        // brains/files/platform toggle could never be unselected. Forms without
        // the picker (back, plain autosave) omit the sentinel, so we fall back
        // to the tolerant has()-guarded behaviour and leave existing values.
        $authoritative = $request->boolean('_resources_present');

        if ($authoritative || $request->has('ai_mind_ids')) {
            $ids = (array) $request->input('ai_mind_ids', []);
            $draft->ai_mind_ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        }
        if ($authoritative || $request->has('include_platform_mind')) {
            $draft->include_platform_mind = $request->boolean('include_platform_mind');
        }
        if ($authoritative || $request->has('file_ids')) {
            $ids = (array) $request->input('file_ids', []);
            $draft->file_ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        }
    }

    /** Land on the wizard — show the persona group step or resume a draft. */
    public function index(Request $request)
    {
        // Carry a user-typed custom alias (Custom URL) from the Create Link
        // page into the wizard. When present we validate it with the same rules
        // as the manual picker and stash it on the draft so the eventual
        // biolink is created with it. An invalid/taken alias bounces back to the
        // Create Link page with the error so the user can fix it (mirrors the
        // manual chooseType() flow). Blank → no-op (auto-generate as before).
        if (($aliasError = $this->captureCustomAlias($request)) !== null) {
            return $aliasError;
        }

        $draft = $this->loadDraft($request);

        // One-tap prefill from the "Create Link" goal prompt: `?group=<persona
        // group>` seeds the persona-group step so a user who already picked a
        // goal (e.g. "Show a menu") skips the wizard's first question. An
        // optional `?persona=<slug>` (only sent for goals that map to exactly
        // one persona) *also* seeds the persona, so the wizard skips its second
        // question too and lands on the starting-design step. Only applied to a
        // fresh/empty draft so an in-progress wizard is never clobbered — the
        // wizard otherwise auto-resumes the latest draft.
        $prefillGroup = $request->query('group');
        if (is_string($prefillGroup) && PersonaCatalog::isValidGroup($prefillGroup)) {
            $draft ??= $this->newDraft($request);
            if (!$draft->persona_group && !$draft->persona) {
                $draft->persona_group = $prefillGroup;
                $draft->step = 1;

                // Seed the persona too when the goal maps unambiguously to a
                // single one. Validate it actually belongs to the prefilled
                // group (defends against a hand-crafted ?persona= for another
                // group); on success resolve the legacy combo and jump to the
                // starting-design step exactly like the manual pick_persona
                // action does. A bad/foreign persona silently falls through to
                // the group-only behaviour (stop on the persona step).
                $prefillPersona = $request->query('persona');
                if (is_string($prefillPersona)
                    && PersonaCatalog::groupOf($prefillPersona) === $prefillGroup) {
                    $combo = PersonaCatalog::wizardResolution($prefillPersona);
                    $draft->persona   = $prefillPersona;
                    $draft->category  = $combo['category'];
                    $draft->page_type = $combo['page_type'];
                    $draft->industry  = null;
                    $draft->step      = 2;
                }

                $draft->save();
            }
        }

        $category = $draft?->category;
        $pageType = $draft?->page_type;

        // Persona now drives the first two steps. Guard against legacy/partial
        // drafts (e.g. created before personas existed, or interrupted) by
        // never rendering a step the draft can't satisfy — this is read-only,
        // the next POST corrects the stored step.
        $step = (int) ($draft?->step ?? 0);
        if (!$draft?->persona && $step >= 2) {
            $step = $draft?->persona_group ? 1 : 0;
        }
        if (!$draft?->persona_group && $step >= 1) {
            $step = 0;
        }

        // Step 0 — the persona groups (the new "category" tiles).
        $groups = PersonaCatalog::groups();

        // Step 1 — the personas in the chosen group, plus any optional niche
        // refinement (industries) available for each persona's resolved combo.
        $groupPersonas = [];
        $industriesByPersona = [];
        if ($draft?->persona_group) {
            $groupPersonas = PersonaCatalog::personasInGroup($draft->persona_group);
            foreach ($groupPersonas as $p) {
                $combo = PersonaCatalog::wizardResolution($p['slug']);
                $specific = BiolinkWizardQuestions::industries($combo['category'], $combo['page_type']);
                if (!empty($specific)) {
                    $industriesByPersona[$p['slug']] = array_map(static fn ($i) => $i + [
                        'icon' => BiolinkWizardQuestions::industryIcon($i['slug']),
                    ], $specific);
                }
            }
        }

        // Step 2 — persona-tagged starting designs (+ "Start from scratch" in
        // the view). Built via the shared service so mobile shows the same set.
        $owner = workspace_owner();
        $startingDesigns = [];
        if ($step === 2 && $draft?->persona) {
            $startingDesigns = $this->startingDesigns->forPersona($draft->persona, $owner);
        }

        // The detailed question set, split into the two content steps.
        $split = ($category && $pageType)
            ? BiolinkWizardQuestions::splitQuestions(
                BiolinkWizardQuestions::questions($category, $pageType, $draft->industry),
            )
            : ['basics' => [], 'additional' => []];

        // AI-draft resources (only needed on the content steps). The user can
        // ground an AI auto-draft in their own AI Brains (Minds) + vault files.
        $myMinds = [];
        $platformMinds = [];
        $vaultFiles = [];
        if ($draft && $step >= 3) {
            \App\Modules\User\Models\AiMind::query()
                ->where('is_disabled', false)
                ->where(function ($q) use ($owner) {
                    $q->where('user_id', $owner->id)
                      ->orWhere(fn ($qq) => $qq->whereNull('user_id')->where('is_default', true));
                })
                ->orderBy('name')
                ->get(['id', 'name', 'user_id'])
                ->each(function ($m) use (&$myMinds, &$platformMinds) {
                    if ($m->user_id === null) {
                        $platformMinds[] = ['id' => (int) $m->id, 'name' => (string) $m->name];
                    } else {
                        $myMinds[] = ['id' => (int) $m->id, 'name' => (string) $m->name];
                    }
                });

            $vaultFiles = UserFile::where('user_id', $owner->id)
                ->orderByDesc('id')
                ->limit(60)
                ->get(['id', 'original_name', 'type', 'filename'])
                ->map(fn ($f) => [
                    'id'    => (int) $f->id,
                    'name'  => (string) $f->original_name,
                    'type'  => (string) $f->type,
                    'url'   => $f->url_path,
                ])
                ->all();
        }

        return view('user.links.wizard', [
            'draft'               => $draft,
            'step'                => $step,
            'groups'              => $groups,
            'personaGroup'        => $draft?->persona_group,
            'groupPersonas'       => $groupPersonas,
            'industriesByPersona' => $industriesByPersona,
            'selectedPersona'     => $draft?->persona,
            'personaLabel'        => PersonaCatalog::labelFor($draft?->persona),
            'startingDesigns'     => $startingDesigns,
            'selectedTemplateId'  => $draft?->template_id,
            'basics'              => $split['basics'],
            'additional'          => $split['additional'],
            'myMinds'             => $myMinds,
            'platformMinds'       => $platformMinds,
            'vaultFiles'          => $vaultFiles,
            'aiEnabled'           => AiEngineSettings::isEnabled(),
            'selectedMindIds'     => $draft?->ai_mind_ids ?? [],
            'includePlatformMind' => (bool) ($draft?->include_platform_mind ?? false),
            'selectedFileIds'     => $draft?->file_ids ?? [],
        ]);
    }

    /** Save a step and advance/back/finish. */
    public function save(Request $request)
    {
        $draft = $this->loadDraft($request) ?? $this->newDraft($request);
        $action = $request->input('_action', 'next');

        switch ($action) {
            case 'pick_group':
                // Step 0 → 1. Picking a (new) group resets the downstream
                // persona/combo/template so a change of mind never strands a
                // stale starting design or page type.
                $group = $this->validateGroup($request->input('persona_group'));
                if ($group !== $draft->persona_group) {
                    $draft->persona      = null;
                    $draft->category     = null;
                    $draft->page_type    = null;
                    $draft->industry     = null;
                    $draft->template_id  = null;
                }
                $draft->persona_group = $group;
                $draft->step          = 1;
                break;

            case 'pick_persona':
                if (!$draft->persona_group) {
                    return back()->with('error', 'Please pick a category first.');
                }
                $persona = $this->validatePersona($draft->persona_group, $request->input('persona'));
                // Changing persona invalidates the previously chosen design.
                if ($persona !== $draft->persona) {
                    $draft->template_id = null;
                }
                $draft->persona = $persona;
                // PersonaCatalog is the single taxonomy source: resolve the
                // persona to the legacy (category, page_type) combo that drives
                // the question set + deterministic recipe engine.
                $combo = PersonaCatalog::wizardResolution($persona);
                $draft->category  = $combo['category'];
                $draft->page_type = $combo['page_type'];
                // Optional niche refinement is folded into this step. Only
                // combos with a specific industries() list accept one; anything
                // else is forced to null (→ category placeholder imagery).
                $draft->industry  = $this->validateIndustry($draft->category, $draft->page_type, $request->input('industry'));
                $draft->step      = 2;
                break;

            case 'pick_template':
                if (!$draft->persona) {
                    return back()->with('error', 'Please pick what best describes you first.');
                }
                // null = "Start from scratch" (the original recipe/AI path).
                $draft->template_id = $this->validateTemplate($request->input('template_id'));
                $draft->step        = 3;
                break;

            case 'save_basics':
                if (!$draft->category || !$draft->page_type) {
                    return back()->with('error', 'Pick a category & profile type first.');
                }
                $existing = $draft->answers ?? [];
                $merged = array_merge($existing, $this->collectAnswers($request, $draft));
                $this->applyResourceInputs($request, $draft);

                // Block advancement until the required "basics" fields are
                // valid. Scope validation to just this step's keys so a later
                // step's required field can't trap the user here.
                $basicKeys = array_column(
                    BiolinkWizardQuestions::splitQuestions(
                        BiolinkWizardQuestions::questions($draft->category, $draft->page_type, $draft->industry),
                    )['basics'],
                    'key',
                );
                $errors = BiolinkWizardQuestions::validateAnswers(
                    $draft->category, $draft->page_type, $draft->industry, $merged, $basicKeys,
                );
                if ($errors) {
                    // Persist what they entered so nothing is lost, then bounce
                    // back to the step with inline field errors.
                    $draft->answers = $merged;
                    $draft->save();
                    return back()->withErrors($errors, 'wizard')->withInput();
                }

                $draft->answers = $merged;
                $draft->step = 4;
                break;

            case 'back':
                // Steps are a clean 0..4 ladder (group → persona → starting
                // design → basics → additional), so a plain `step - 1` walks
                // back correctly without any special-casing.
                $cur = (int) ($draft->step ?? 0);
                $draft->step = max(0, $cur - 1);
                break;

            case 'restart':
                $draft->delete();
                return redirect()->route('user.links.wizard');

            case 'save_and_exit':
                $existing = $draft->answers ?? [];
                $draft->answers = array_merge($existing, $this->collectAnswers($request, $draft));
                $this->applyResourceInputs($request, $draft);
                $draft->save();
                return redirect()->route('user.links.index')
                    ->with('success', 'Wizard saved — pick up where you left off any time.');
        }

        $draft->save();
        return redirect()->route('user.links.wizard');
    }

    /**
     * Finalise — generate the biolink and apply the recipe.
     * Goes through CheckPlanLimit:links to enforce the workspace owner's plan.
     */
    public function finish(Request $request)
    {
        // Re-run the plan limit check inline (the route already enforces it,
        // but `finish` also creates the Link — better to be explicit so the
        // user gets the same friendly redirect on edge cases).
        $draft = $this->loadDraft($request);
        if (!$draft || !$draft->category || !$draft->page_type) {
            return redirect()->route('user.links.wizard')
                ->with('error', 'Finish the wizard before generating the page.');
        }

        // Capture any last-step answer changes before building.
        $existing = $draft->answers ?? [];
        $merged = array_merge($existing, $this->collectAnswers($request, $draft));
        $this->applyResourceInputs($request, $draft);
        $draft->answers = $merged;
        $draft->save();

        $answers = $draft->answers ?? [];

        // Full required-field validation across the whole question set before
        // generating — inline errors block "Generate" the same way "Next" is
        // blocked on the basics step.
        $errors = BiolinkWizardQuestions::validateAnswers(
            $draft->category, $draft->page_type, $draft->industry, $answers,
        );
        if ($errors) {
            return back()->withErrors($errors, 'wizard')->withInput();
        }

        // Required: display_name (or, for events, the per-event name field).
        if (!BiolinkWizardQuestions::hasName($answers)) {
            return back()->with('error', 'Please fill in at least the name field before generating your page.');
        }

        $owner = workspace_owner();

        // Plan cap re-check on the workspace owner (the actor middleware
        // CheckPlanLimit checks the *acting* user, which is wrong for team
        // members — we re-validate against the workspace owner here so a
        // member can't exceed the owner's plan caps). Plan caps are
        // account-wide, so count across all workspaces (the BelongsToWorkspace
        // global scope would otherwise hide links outside the active one).
        $maxLinks = (int) $owner->getPlanFeature('max_links', 5);
        if ($maxLinks !== -1 && $owner->links()->withoutGlobalScope('workspace')->count() >= $maxLinks) {
            return redirect()->route('user.upgrade')
                ->with('error', "You've reached your plan's link limit ({$maxLinks}) — upgrade to add more.");
        }
        $maxBiolinks = (int) $owner->getPlanFeature('max_biolinks', 1);
        if ($maxBiolinks !== -1) {
            $usedBiolinks = $owner->links()->withoutGlobalScope('workspace')->whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)->count();
            if ($usedBiolinks >= $maxBiolinks) {
                return redirect()->route('user.upgrade')
                    ->with('error', 'You\'ve reached your plan\'s Link in Bio limit — upgrade to add more.');
            }
        }

        // Atomically: create the Link, paint blocks from the recipe, and
        // discard the draft. If applyPageToLink throws (e.g. unknown block
        // type), the transaction rolls back and we won't leave an empty link
        // sitting in the user's dashboard. The recipe → Link generation core
        // lives in BiolinkWizardGenerator, shared with the mobile API wizard.
        // Resolve the chosen starting design (null = "Start from scratch").
        // A locked/inactive template degrades gracefully to scratch rather than
        // blocking generation — the picker already hides locked picks.
        $templateSnapshot = $this->resolveTemplateSnapshot($draft, $owner);

        try {
            $link = DB::transaction(function () use ($owner, $draft, $answers, $templateSnapshot) {
                $newLink = $this->generator->generate(
                    $owner, $draft->category, $draft->page_type, $draft->industry, $answers, $templateSnapshot, $draft->alias,
                );

                // Wizard is single-shot — discard the draft now that the
                // page exists. Done inside the transaction so a failure
                // anywhere upstream leaves the draft for the user to retry.
                $draft->delete();

                return $newLink;
            });
        } catch (Throwable $e) {
            report($e);
            return redirect()->route('user.links.wizard')
                ->with('error', 'We hit a snag generating your page. Your answers were saved — please try again.');
        }

        return redirect()->route('user.links.blocks.editor', $link)
            ->with('success', 'Your page is ready — tweak any block to make it yours.');
    }

    /**
     * Auto-draft the page with AI instead of the deterministic recipe.
     *
     * Reuses everything finish() validates (draft present, required answers,
     * name, plan caps) then hands off to WizardAiDraftService, which grounds
     * the build in the selected AI Brains + vault files and charges/refunds
     * the `biolink_builder` AI credit. Goes through CheckPlanLimit:links.
     */
    public function finishAi(Request $request)
    {
        $draft = $this->loadDraft($request);
        if (!$draft || !$draft->category || !$draft->page_type) {
            return redirect()->route('user.links.wizard')
                ->with('error', 'Finish the wizard before generating the page.');
        }

        if (!AiEngineSettings::isEnabled()) {
            return back()->with('error', 'AI generation is currently unavailable. You can still generate your page instantly.');
        }

        // Capture final answers + resource selections before building.
        $existing = $draft->answers ?? [];
        $merged = array_merge($existing, $this->collectAnswers($request, $draft));
        $this->applyResourceInputs($request, $draft);
        $draft->answers = $merged;
        $draft->save();

        $answers = $draft->answers ?? [];

        $errors = BiolinkWizardQuestions::validateAnswers(
            $draft->category, $draft->page_type, $draft->industry, $answers,
        );
        if ($errors) {
            return back()->withErrors($errors, 'wizard')->withInput();
        }
        if (!BiolinkWizardQuestions::hasName($answers)) {
            return back()->with('error', 'Please fill in at least the name field before generating your page.');
        }

        $owner = workspace_owner();

        // Same plan-cap re-check as finish() (against the workspace owner,
        // account-wide across all workspaces).
        $maxLinks = (int) $owner->getPlanFeature('max_links', 5);
        if ($maxLinks !== -1 && $owner->links()->withoutGlobalScope('workspace')->count() >= $maxLinks) {
            return redirect()->route('user.upgrade')
                ->with('error', "You've reached your plan's link limit ({$maxLinks}) — upgrade to add more.");
        }
        $maxBiolinks = (int) $owner->getPlanFeature('max_biolinks', 1);
        if ($maxBiolinks !== -1) {
            $usedBiolinks = $owner->links()->withoutGlobalScope('workspace')->whereIn('type', Link::BIOLINK_FAMILY)->count();
            if ($usedBiolinks >= $maxBiolinks) {
                return redirect()->route('user.upgrade')
                    ->with('error', 'You\'ve reached your plan\'s Link in Bio limit — upgrade to add more.');
            }
        }

        // Seed the chosen starting design (null = "Start from scratch") so the
        // AI draft layers on top of it instead of replacing the page.
        $templateSnapshot = $this->resolveTemplateSnapshot($draft, $owner);

        try {
            $link = $this->aiDraft->generate(
                $owner,
                $draft->category,
                $draft->page_type,
                $draft->industry,
                $answers,
                $draft->ai_mind_ids ?? [],
                (bool) ($draft->include_platform_mind ?? false),
                $draft->file_ids ?? [],
                $templateSnapshot,
                $draft->alias,
            );
        } catch (InsufficientCoinsForAiException $e) {
            return redirect()->route('user.upgrade')
                ->with('error', 'You don\'t have enough coins for AI generation — top up or generate instantly instead.');
        } catch (Throwable $e) {
            report($e);
            return back()->with('error', 'The AI couldn\'t draft your page this time. Your answers were saved — try again or generate instantly.');
        }

        // Success — the AI build replaces the recipe, so discard the draft.
        $draft->delete();

        return redirect()->route('user.links.blocks.editor', $link)
            ->with('success', 'Your AI-drafted page is ready — tweak any block to make it yours.');
    }

    /**
     * SVG placeholder served from the app (never hot-linked) — used as the
     * default avatar/cover image when the user skips the upload step.
     * Public, no auth required (referenced by published pages).
     */
    public function placeholder(string $slug): Response
    {
        $slug = strtolower(preg_replace('/[^a-z0-9_]/i', '', $slug)) ?: 'default';

        // Category palette (and a few industry overrides).
        $palette = [
            'creator'      => ['#fdf2f8', '#ec4899', '🎬'],
            'business'     => ['#f3e8ff', '#7c3aed', '💼'],
            'restaurant'   => ['#fff7ed', '#ea580c', '🍽'],
            'musician'     => ['#faf5ff', '#a855f7', '🎵'],
            'real_estate'  => ['#e0f2fe', '#0284c7', '🏠'],
            'coach'        => ['#dcfce7', '#059669', '🎯'],
            'personal'     => ['#e0e7ff', '#4f46e5', '👤'],
            'event'        => ['#fef3c7', '#d97706', '🎉'],
            'health_wellness' => ['#ccfbf1', '#0d9488', '🧘'],
            'nonprofit'    => ['#dcfce7', '#16a34a', '💚'],
            'fashion_beauty'=> ['#fce7f3', '#db2777', '👗'],
            'photographer' => ['#e2e8f0', '#475569', '📷'],
            'travel_creator'=> ['#e0f2fe', '#0284c7', '✈'],
            'faith'        => ['#ede9fe', '#6d28d9', '🙏'],
            'education'    => ['#dbeafe', '#2563eb', '🎓'],
            // Industries
            'streetwear'   => ['#f1f5f9', '#1e293b', '🧢'],
            'luxury'       => ['#1e1b4b', '#fbbf24', '💎'],
            'sustainable'  => ['#dcfce7', '#16a34a', '🌿'],
            'jewelry'      => ['#fdf2f8', '#db2777', '💍'],
            'bakery'       => ['#fff7ed', '#c2410c', '🥐'],
            'salon'        => ['#fdf2f8', '#db2777', '💇'],
            'gym'          => ['#fef2f2', '#dc2626', '💪'],
            'pet_store'    => ['#ecfdf5', '#059669', '🐾'],
            'florist'      => ['#fdf2f8', '#db2777', '💐'],
            'auto'         => ['#f1f5f9', '#475569', '🚗'],
            'cleaning'     => ['#ecfeff', '#0891b2', '🧼'],
            'fashion'      => ['#fdf2f8', '#db2777', '👗'],
            'beauty'       => ['#fdf2f8', '#db2777', '💄'],
            'food'         => ['#fff7ed', '#ea580c', '🍴'],
            'home'         => ['#f0fdf4', '#16a34a', '🏡'],
            'digital'      => ['#ecfeff', '#0891b2', '💾'],
            'italian'      => ['#fef2f2', '#dc2626', '🍝'],
            'asian'        => ['#fef2f2', '#dc2626', '🍜'],
            'mexican'      => ['#fff7ed', '#ea580c', '🌮'],
            'american'     => ['#fef2f2', '#dc2626', '🍔'],
            'mediterranean'=> ['#ecfdf5', '#059669', '🥗'],
            'vegan'        => ['#dcfce7', '#16a34a', '🥑'],
            'fine_dining'  => ['#1e1b4b', '#fbbf24', '🍷'],
            'lifestyle'    => ['#fdf2f8', '#ec4899', '✨'],
            'travel'       => ['#e0f2fe', '#0284c7', '✈'],
            'gaming'       => ['#1e1b4b', '#a855f7', '🎮'],
            'parenting'    => ['#fef3c7', '#d97706', '👨\u{200d}👩\u{200d}👧'],
            'pt'           => ['#fef2f2', '#dc2626', '🏋'],
            'yoga'         => ['#dcfce7', '#16a34a', '🧘'],
            'nutrition'    => ['#ecfdf5', '#059669', '🥗'],
            'crossfit'     => ['#1e1b4b', '#dc2626', '🏋'],
            'pilates'      => ['#fdf2f8', '#db2777', '🧘'],
        ];
        [$bg, $fg, $emoji] = $palette[$slug] ?? ['#1f1f23', '#a78bfa', '✨'];

        // Build SVG. Emoji positioning uses a font-stack that prefers system
        // colour-emoji fonts so the symbol renders nicely on most platforms.
        $svg = <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="{$bg}"/>
      <stop offset="100%" stop-color="{$fg}" stop-opacity="0.45"/>
    </linearGradient>
  </defs>
  <rect width="512" height="512" fill="url(#g)"/>
  <circle cx="256" cy="256" r="120" fill="{$fg}" fill-opacity="0.15"/>
  <text x="256" y="296" text-anchor="middle" font-size="160"
        font-family="'Apple Color Emoji','Segoe UI Emoji','Noto Color Emoji',sans-serif">{$emoji}</text>
</svg>
SVG;

        return response($svg, 200, [
            'Content-Type'  => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /* ────────────────────────────────────────────────────────────── */

    protected function loadDraft(Request $request): ?BiolinkWizardDraft
    {
        // Key drafts by the actor (the human at the keyboard) so each team
        // member resumes *their own* in-progress wizard inside the same
        // workspace — never another member's. The workspace_owner_id is
        // still recorded on the row for audit + the eventual link's owner.
        $actorId = Auth::id();
        if (!$actorId) return null;
        $wsId = optional(app()->bound('current_workspace') ? app('current_workspace') : null)->id;
        return BiolinkWizardDraft::query()
            ->where('actor_user_id', $actorId)
            ->when($wsId, fn ($q) => $q->where('workspace_id', $wsId), fn ($q) => $q->whereNull('workspace_id'))
            ->latest('id')
            ->first();
    }

    /**
     * Capture a custom alias passed through from the Create Link page (the
     * guided wizard hero forwards `?alias=...` typed in the manual picker).
     *
     * Validates with the same rules as the manual chooseType() flow. On
     * success the alias is stashed on the (loaded-or-new) draft and null is
     * returned so index() proceeds. On failure a redirect back to the Create
     * Link page (with errors + old input) is returned. A blank/absent alias is
     * a no-op (null) — the wizard auto-generates an alias exactly as before.
     */
    protected function captureCustomAlias(Request $request)
    {
        $alias = trim((string) $request->query('alias', ''));
        if ($alias === '') {
            return null;
        }

        $limits = workspace_owner()->getAliasLengthLimits();
        $validator = Validator::make(['alias' => $alias], [
            'alias' => [
                'string', new \App\Modules\User\Rules\AliasFormat(),
                'min:' . $limits['min'],
                'max:' . $limits['max'],
                new \App\Modules\User\Rules\UniqueAliasCi(),
                new \App\Modules\Admin\Rules\NotBannedName(),
            ],
        ]);

        if ($validator->fails()) {
            return redirect()->route('user.links.create')
                ->withErrors($validator)
                ->withInput(['alias' => $alias]);
        }

        $draft = $this->loadDraft($request) ?? $this->newDraft($request);
        $draft->alias = $alias;
        $draft->save();

        return null;
    }

    protected function newDraft(Request $request): BiolinkWizardDraft
    {
        $wsId = optional(app()->bound('current_workspace') ? app('current_workspace') : null)->id;
        return new BiolinkWizardDraft([
            'user_id'       => workspace_owner_id(),
            'actor_user_id' => Auth::id(),
            'workspace_id'  => $wsId,
            'step'          => 0,
            'answers'       => [],
        ]);
    }

    protected function validateCategory($value): string
    {
        $slugs = array_column(BiolinkWizardQuestions::categories(), 'slug');
        if (!in_array($value, $slugs, true)) {
            abort(422, 'Invalid category.');
        }
        return $value;
    }

    protected function validatePageType(string $category, $value): string
    {
        $slugs = array_column(BiolinkWizardQuestions::pageTypes($category), 'slug');
        if (!in_array($value, $slugs, true)) {
            abort(422, 'Invalid page type.');
        }
        return $value;
    }

    /** Validate the chosen persona group (step 0). */
    protected function validateGroup($value): string
    {
        if (!is_string($value) || !PersonaCatalog::isValidGroup($value)) {
            abort(422, 'Invalid category.');
        }
        return $value;
    }

    /** Validate that the chosen persona belongs to the chosen group (step 1). */
    protected function validatePersona(string $group, $value): string
    {
        $slugs = array_column(PersonaCatalog::personasInGroup($group), 'slug');
        if (!is_string($value) || !in_array($value, $slugs, true)) {
            abort(422, 'Invalid persona.');
        }
        return $value;
    }

    /**
     * Validate the chosen starting design (step 2). Blank/0 → null = "Start
     * from scratch". A real id must reference an *active* template; lock state
     * is tolerated here (re-checked at generation) so a stale pick never 422s.
     */
    protected function validateTemplate($value): ?int
    {
        if ($value === null || $value === '' || $value === '0' || $value === 0) {
            return null;
        }
        if (!is_numeric($value)) {
            abort(422, 'Invalid template.');
        }
        $id = (int) $value;
        if (!PageTemplate::active()->whereKey($id)->exists()) {
            abort(422, 'Invalid template.');
        }
        return $id;
    }

    /**
     * Resolve a draft's chosen starting design to a snapshot to seed, or null
     * for "Start from scratch". Inactive or plan-locked picks degrade to null
     * so generation never hard-fails on a stale/over-tier selection.
     *
     * @return array<string,mixed>|null
     */
    protected function resolveTemplateSnapshot(BiolinkWizardDraft $draft, $owner): ?array
    {
        if (empty($draft->template_id)) {
            return null;
        }
        $template = PageTemplate::active()->find($draft->template_id);
        if (!$template) {
            return null;
        }
        if ($this->startingDesigns->isLocked($template->plan_tier, $owner?->plan?->slug)) {
            return null;
        }
        $snapshot = $template->snapshot;
        return is_array($snapshot) ? $snapshot : null;
    }

    /**
     * Validate the optional niche refinement folded into the profile-type
     * step. Only combos with a *specific* industries() list accept one — any
     * combo without a list (or an empty/blank value) resolves to null, which
     * the recipe treats as "use the category placeholder imagery".
     */
    protected function validateIndustry(string $category, string $pageType, $value): ?string
    {
        $slugs = array_column(BiolinkWizardQuestions::industries($category, $pageType), 'slug');
        if (empty($slugs)) return null;
        if ($value === null || $value === '') return null;
        if (!in_array($value, $slugs, true)) {
            abort(422, 'Invalid industry.');
        }
        return $value;
    }

    /**
     * Pull answers off the request, sanitise, handle one-off avatar upload.
     * Unknown fields are ignored — only keys defined in the question set for
     * the current (category, page_type, industry) are accepted.
     */
    protected function collectAnswers(Request $request, BiolinkWizardDraft $draft): array
    {
        if (!$draft->category || !$draft->page_type) return [];
        $questions = BiolinkWizardQuestions::questions($draft->category, $draft->page_type, $draft->industry);
        $payload = (array) $request->input('a', []);
        $out = [];

        foreach ($questions as $q) {
            $key = $q['key'];
            $type = $q['type'] ?? 'text';

            if ($type === 'image') {
                if ($request->hasFile("a_files.{$key}")) {
                    try {
                        $file = $request->file("a_files.{$key}");
                        $rules = \App\Services\UploadPolicy::rule('biolink.avatar', $request->user());
                        $request->validate(["a_files.{$key}" => $rules]);
                        // Wizard image answers are typically avatars / hero photos —
                        // shrink raster originals so they don't bloat the vault.
                        $out[$key] = UserFile::createFromUpload($file, $request->user(), [
                            'compress_image' => true,
                            'max_width'      => 800,
                            'max_height'     => 800,
                            'quality'        => 85,
                        ])->url;
                    } catch (\Throwable $e) {
                        // Skip — placeholder will be used.
                    }
                } elseif (!empty($payload[$key]) && is_string($payload[$key])) {
                    $out[$key] = $payload[$key];
                }
                continue;
            }

            if (!array_key_exists($key, $payload)) continue;
            $val = $payload[$key];
            if (!is_string($val)) continue;
            $val = trim($val);
            if ($val === '') continue;

            // Light per-type validation — invalid input is silently dropped
            // (the wizard is a fast-path; users can refine later in editor).
            switch ($type) {
                case 'url':
                    if (!preg_match('#^https?://#i', $val)) {
                        $val = 'https://' . ltrim($val, '/');
                    }
                    if (!filter_var($val, FILTER_VALIDATE_URL)) continue 2;
                    $val = mb_substr($val, 0, 2048);
                    break;
                case 'email':
                    if (!filter_var($val, FILTER_VALIDATE_EMAIL)) continue 2;
                    $val = mb_substr($val, 0, 255);
                    break;
                case 'color':
                    if (!preg_match('/^#[0-9a-f]{3,8}$/i', $val)) continue 2;
                    break;
                case 'phone':
                    $val = mb_substr(preg_replace('/[^\d+\s\-()]/', '', $val), 0, 30);
                    break;
                case 'select':
                    $opts = array_column($q['options'] ?? [], 'v');
                    if (!in_array($val, $opts, true)) continue 2;
                    break;
                case 'textarea':
                    $val = mb_substr($val, 0, 2000);
                    break;
                default:
                    $val = mb_substr($val, 0, 500);
            }

            $out[$key] = $val;
        }
        return $out;
    }
}
