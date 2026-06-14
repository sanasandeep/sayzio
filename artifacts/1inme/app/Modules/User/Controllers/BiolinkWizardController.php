<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\TemplateService;
use App\Modules\User\Middleware\CheckPlanLimit;
use App\Modules\User\Models\BiolinkWizardDraft;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Services\BiolinkPageRecipes;
use App\Modules\User\Services\BiolinkWizardQuestions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Guided biolink creation wizard.
 *
 * Walks the user through 4 steps (category → page type → optional industry →
 * detailed Q&A) and then generates an opinionated biolink page from their
 * answers using BiolinkPageRecipes + TemplateService::applyPageToLink.
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
    public function __construct(private TemplateService $templates) {}

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
        $draft->save();

        return response()->noContent();
    }

    /** Land on the wizard — show category step or resume an existing draft. */
    public function index(Request $request)
    {
        $draft = $this->loadDraft($request);

        return view('user.links.wizard', [
            'draft'      => $draft,
            'categories' => BiolinkWizardQuestions::categories(),
            'step'       => $draft?->step ?? 0,
            'pageTypes'  => $draft && $draft->category ? BiolinkWizardQuestions::pageTypes($draft->category) : [],
            'industries' => $draft && $draft->category && $draft->page_type
                                ? BiolinkWizardQuestions::industries($draft->category, $draft->page_type)
                                : [],
            'questions'  => $draft && $draft->category && $draft->page_type
                                ? BiolinkWizardQuestions::questions($draft->category, $draft->page_type, $draft->industry)
                                : [],
        ]);
    }

    /** Save a step and advance/back/finish. */
    public function save(Request $request)
    {
        $draft = $this->loadDraft($request) ?? $this->newDraft($request);
        $action = $request->input('_action', 'next');

        switch ($action) {
            case 'pick_category':
                $draft->category  = $this->validateCategory($request->input('category'));
                $draft->page_type = null;
                $draft->industry  = null;
                $draft->step      = 1;
                break;

            case 'pick_page_type':
                if (!$draft->category) {
                    return back()->with('error', 'Please pick a category first.');
                }
                $draft->page_type = $this->validatePageType($draft->category, $request->input('page_type'));
                $draft->industry  = null;
                $draft->step      = BiolinkWizardQuestions::hasIndustryStep($draft->category, $draft->page_type) ? 2 : 3;
                break;

            case 'pick_industry':
                if (!$draft->category || !$draft->page_type) {
                    return back()->with('error', 'Pick a page type first.');
                }
                $draft->industry = $this->validateIndustry($draft->category, $draft->page_type, $request->input('industry'));
                $draft->step = 3;
                break;

            case 'save_answers':
                if (!$draft->category || !$draft->page_type) {
                    return back()->with('error', 'Pick a category & page type first.');
                }
                $existing = $draft->answers ?? [];
                $merged = array_merge($existing, $this->collectAnswers($request, $draft));
                $draft->answers = $merged;
                $draft->step = 3;
                break;

            case 'back':
                // Walk one logical step back. The industry step (2) is
                // optional — many category/page-type combos skip it — so
                // a blind `step - 1` would land the user on an empty step
                // 2 with no industries to pick. Compute the previous step
                // from the actual taxonomy instead.
                $cur = (int) ($draft->step ?? 0);
                if ($cur >= 3) {
                    $hasIndustry = $draft->category && $draft->page_type
                        && BiolinkWizardQuestions::hasIndustryStep($draft->category, $draft->page_type);
                    $draft->step = $hasIndustry ? 2 : 1;
                } else {
                    $draft->step = max(0, $cur - 1);
                }
                break;

            case 'restart':
                $draft->delete();
                return redirect()->route('user.links.wizard');

            case 'save_and_exit':
                $existing = $draft->answers ?? [];
                $draft->answers = array_merge($existing, $this->collectAnswers($request, $draft));
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
        $draft->answers = array_merge($existing, $this->collectAnswers($request, $draft));
        $draft->save();

        $answers = $draft->answers ?? [];

        // Required: display_name (or, for events, the per-event name field).
        $hasName = !empty($answers['display_name'])
            || !empty($answers['business_name'])
            || !empty($answers['venue_name'])
            || !empty($answers['agent_name'])
            || !empty($answers['coach_name'])
            || !empty($answers['artist_name'])
            || !empty($answers['band_name'])
            || !empty($answers['dj_name'])
            || !empty($answers['firm_name'])
            || !empty($answers['org_name'])
            || !empty($answers['product_name'])
            || !empty($answers['agency_name'])
            || !empty($answers['truck_name'])
            || !empty($answers['couple'])
            || !empty($answers['event_name'])
            || !empty($answers['tutor_name'])
            || !empty($answers['store_name']);
        if (!$hasName) {
            return back()->with('error', 'Please fill in at least the name field before generating your page.');
        }

        $owner = workspace_owner();

        // Plan cap re-check on the workspace owner (the actor middleware
        // CheckPlanLimit checks the *acting* user, which is wrong for team
        // members — we re-validate against the workspace owner here so a
        // member can't exceed the owner's plan caps).
        $features = $owner->plan?->features ?? [];
        $maxLinks = $features['max_links'] ?? 5;
        if ($maxLinks !== -1 && $owner->links()->count() >= $maxLinks) {
            return redirect()->route('user.upgrade')
                ->with('error', "You've reached your plan's link limit ({$maxLinks}) — upgrade to add more.");
        }
        $maxBiolinks = $features['max_biolinks'] ?? 1;
        if ($maxBiolinks !== -1) {
            $usedBiolinks = $owner->links()->whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)->count();
            if ($usedBiolinks >= $maxBiolinks) {
                return redirect()->route('user.upgrade')
                    ->with('error', 'You\'ve reached your plan\'s Link in Bio limit — upgrade to add more.');
            }
        }

        $title = $answers['display_name']
            ?? $answers['business_name']
            ?? $answers['event_name']
            ?? $answers['couple']
            ?? $answers['venue_name']
            ?? $answers['agency_name']
            ?? $answers['store_name']
            ?? $answers['artist_name']
            ?? $answers['band_name']
            ?? $answers['dj_name']
            ?? $answers['agent_name']
            ?? $answers['coach_name']
            ?? $answers['firm_name']
            ?? $answers['org_name']
            ?? $answers['product_name']
            ?? $answers['truck_name']
            ?? $answers['tutor_name']
            ?? 'My Link in Bio';

        // Atomically: create the Link, paint blocks from the recipe, and
        // discard the draft. If applyPageToLink throws (e.g. unknown block
        // type), the transaction rolls back and we won't leave an empty link
        // sitting in the user's dashboard.
        try {
            $link = DB::transaction(function () use ($owner, $title, $draft, $answers) {
                $newLink = Link::create([
                    'user_id'   => $owner->id,
                    'type'      => 'biolink',
                    'alias'     => Link::generateAlias(),
                    'title'     => mb_substr((string) $title, 0, 255),
                    'is_active' => true,
                ]);

                $snapshot = BiolinkPageRecipes::build(
                    $draft->category, $draft->page_type, $draft->industry, $answers,
                );

                $this->templates->applyPageToLink($newLink, $snapshot, /*replace*/ true);

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
            // Industries
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
