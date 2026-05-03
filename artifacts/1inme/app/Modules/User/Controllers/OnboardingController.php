<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Services\TemplateService;
use App\Modules\User\Models\Link;
use App\Modules\User\Services\PersonaCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OnboardingController extends Controller
{
    public function __construct(private TemplateService $templates) {}

    /**
     * Single-page onboarding: persona list (left) + matching templates
     * (right) + a live mini-preview that opens before any biolink is
     * created. Replaces the old two-step persona/template flow.
     */
    public function index()
    {
        $user = Auth::user();
        $persona = $user->persona;

        return view('user.onboarding.index', [
            'personas'    => PersonaCatalog::all(),
            'grouped'     => PersonaCatalog::grouped(),
            'current'     => $persona,
            'personaLabel'=> PersonaCatalog::pluralLabelFor($persona),
            'initialGrid' => $this->renderTemplateGrid($persona),
        ]);
    }

    /**
     * Returns a Blade-rendered HTML fragment of the template cards for
     * the given persona slug (recommended-first). When persona is empty
     * or unknown, returns all templates ungrouped. Used by the right
     * panel to refresh without a full page reload.
     */
    public function templatesJson(Request $request)
    {
        $persona = $request->query('persona');
        if ($persona !== null && $persona !== '' && !PersonaCatalog::isValid($persona)) {
            $persona = null;
        }
        return response($this->renderTemplateGrid($persona))
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Renders a live preview of the chosen template as it would look
     * on a real biolink page. Implemented inside a DB transaction that
     * always rolls back, so no link / blocks / settings persist — the
     * user only commits to a template when they click "Use this
     * template", which goes through `applyTemplate` below.
     */
    public function templatePreview(Request $request, $id)
    {
        $tpl = PageTemplate::active()->where('id', $id)->firstOrFail();
        $user = Auth::user();

        DB::beginTransaction();
        try {
            $link = Link::create([
                'user_id'  => $user->id,
                'type'     => 'biolink',
                'alias'    => 'preview-' . Link::generateAlias(),
                'title'    => $tpl->name,
                'is_active'=> true,
            ]);
            $this->templates->applyPageToLink($link, $tpl->snapshot, /*replace*/ true);
            $link->refresh();
            $link->load('user');

            $html = view('common.biolink', compact('link'))->render();
        } finally {
            DB::rollBack();
        }

        return response($html)
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Content-Security-Policy', "frame-ancestors 'self'")
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    /**
     * Render the recommended + others grid for a given persona slug.
     * Returns an empty-state card if there are no templates.
     */
    private function renderTemplateGrid(?string $personaSlug): string
    {
        $user = Auth::user();
        $userPlanSlug = $user->plan?->slug;
        $linkTplCtrl = app(LinkTemplateController::class);
        $lockedFn = fn(?string $required) => $linkTplCtrl->isLocked($required, $userPlanSlug);

        $templates = PageTemplate::active()->get();
        [$recommended, $others] = $templates->partition(function ($t) use ($personaSlug) {
            $tags = $t->recommended_personas ?? [];
            return $personaSlug && is_array($tags) && in_array($personaSlug, $tags, true);
        });

        return view('user.onboarding._template_grid_panel', [
            'recommended' => $recommended->values(),
            'others'      => $others->values(),
            'lockedFn'    => $lockedFn,
            'persona'     => $personaSlug,
            'personaLabel'=> PersonaCatalog::pluralLabelFor($personaSlug),
        ])->render();
    }

    /**
     * Persists the chosen persona. Returns JSON for AJAX callers (the
     * single-page flow) and falls back to a redirect for legacy form
     * posts. Never marks the user as onboarded by itself — onboarding
     * completes when they apply a template (or hit "Skip for now").
     */
    public function savePersona(Request $request)
    {
        $validated = $request->validate([
            'persona' => 'nullable|string|in:' . implode(',', PersonaCatalog::slugs()),
        ]);

        $user = Auth::user();

        if (!empty($validated['persona'])) {
            $user->forceFill([
                'persona' => $validated['persona'],
            ])->save();
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'persona' => $validated['persona'] ?? null,
            ]);
        }

        return redirect()->route('user.onboarding.index');
    }

    /**
     * Apply the chosen template — creates the user's first biolink (or
     * reuses the most recent empty one) and applies the snapshot, then
     * drops them into the biolink editor.
     */
    public function applyTemplate(Request $request)
    {
        $validated = $request->validate([
            'template_id' => 'nullable|integer|exists:page_templates,id',
            'skip'        => 'nullable|boolean',
        ]);

        $user = Auth::user();

        // Always finalize onboarding regardless of the path taken.
        if (!$user->onboarded_at) {
            $user->forceFill(['onboarded_at' => now()])->save();
        }

        if ($request->boolean('skip') || empty($validated['template_id'])) {
            return redirect()->route('user.dashboard')
                ->with('success', "You're all set — create a link whenever you're ready.");
        }

        $tpl = PageTemplate::active()->where('id', $validated['template_id'])->first();
        if (!$tpl) {
            return redirect()->route('user.dashboard')->with('error', 'That template is no longer available.');
        }

        $userPlanSlug = $user->plan?->slug;
        if (app(LinkTemplateController::class)->isLocked($tpl->plan_tier, $userPlanSlug)) {
            return back()->with('error', 'That template needs a higher plan — pick another one or skip.');
        }

        // Prefer an existing biolink — never silently create another one for
        // users who already have biolinks (this could bypass plan caps when
        // existing users hit the wizard via the dashboard banner). If they
        // already have one, apply the template to their most recent biolink
        // that has no blocks yet; otherwise nudge them to the picker.
        $existing = $user->links()->where('type', 'biolink')->latest('id')->get();

        if ($existing->isEmpty()) {
            // First-time creation — enforce the same plan caps used elsewhere.
            $features = $user->plan?->features ?? [];
            $maxBiolinks = $features['max_biolinks'] ?? 1;
            if ($maxBiolinks !== -1 && 0 >= $maxBiolinks) {
                return redirect()->route('user.dashboard')
                    ->with('error', "Your plan doesn't include a Link in Bio yet — upgrade to enable it.");
            }
            $link = Link::create([
                'user_id'  => $user->id,
                'type'     => 'biolink',
                'alias'    => Link::generateAlias(),
                'title'    => $user->name ? $user->name . "'s page" : 'My Link in Bio',
                'is_active'=> true,
            ]);
        } else {
            // Pick the first biolink that has no blocks; if all have content,
            // send them to the in-app template picker for that link so the
            // server-side "confirm overwrite" guard kicks in instead of
            // silently destroying their work.
            $link = $existing->first(fn($l) => !$l->biolinkBlocks()->exists());
            if (!$link) {
                return redirect()->route('user.links.templates.picker', [
                    'link' => $existing->first()->id,
                ])->with('info', 'Pick a template to apply to your existing page — your current blocks will be replaced only if you confirm.');
            }
        }

        $this->templates->applyPageToLink($link, $tpl->snapshot, /*replace*/ true);

        return redirect()->route('user.links.blocks.editor', $link)
            ->with('success', 'Welcome aboard — your "' . $tpl->name . '" page is ready to edit.');
    }

    /**
     * Bail out of onboarding straight to the dashboard. Marks the user
     * as onboarded so the gate middleware doesn't pull them back in
     * next login. Works whether or not a persona has been picked.
     */
    public function goToDashboard(Request $request)
    {
        $user = Auth::user();
        if (!$user->onboarded_at) {
            $user->forceFill(['onboarded_at' => now()])->save();
        }
        return redirect()->route('user.dashboard')
            ->with('success', "You're all set — explore your dashboard. You can pick a persona or template anytime.");
    }

    /** Mark the dashboard banner as dismissed (settings JSON flag). */
    public function dismissBanner(Request $request)
    {
        $user = Auth::user();
        $settings = $user->settings ?? [];
        $settings['persona_banner_dismissed_at'] = now()->toIso8601String();
        $user->forceFill(['settings' => $settings])->save();
        return back();
    }
}
