<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Services\TemplateService;
use App\Modules\User\Models\Link;
use App\Modules\User\Services\PersonaCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OnboardingController extends Controller
{
    public function __construct(private TemplateService $templates) {}

    /**
     * Step 1 — pick a persona. Renders even if the user has already
     * been onboarded, because they can also reach this from the
     * dashboard banner ("change later") or the profile page.
     */
    public function persona()
    {
        return view('user.onboarding.persona', [
            'personas' => PersonaCatalog::all(),
            'current'  => Auth::user()->persona,
        ]);
    }

    /**
     * Saves the chosen persona (or skip) and forwards to the template
     * step. "Skip" still marks the user as onboarded so they don't get
     * trapped in a loop, but leaves persona null — the dashboard then
     * shows the soft "want suggestions?" banner.
     */
    public function savePersona(Request $request)
    {
        $validated = $request->validate([
            'persona' => 'nullable|string|in:' . implode(',', PersonaCatalog::slugs()),
            'skip'    => 'nullable|boolean',
        ]);

        $user = Auth::user();

        if ($request->boolean('skip')) {
            $user->forceFill([
                'onboarded_at' => $user->onboarded_at ?: now(),
            ])->save();
            return redirect()->route('user.dashboard')
                ->with('success', "No problem — you can personalise your setup any time from your profile.");
        }

        if (empty($validated['persona'])) {
            return back()->with('error', 'Pick a persona that fits, or hit "Skip for now".');
        }

        $user->forceFill([
            'persona'      => $validated['persona'],
            'onboarded_at' => $user->onboarded_at ?: now(),
        ])->save();

        return redirect()->route('user.onboarding.template');
    }

    /**
     * Step 2 — pick a starter template. Templates tagged with the
     * user's persona float to the top; the user can still pick any
     * template (subject to plan-tier locks) or skip and start blank.
     */
    public function template()
    {
        $user = Auth::user();
        $persona = $user->persona;

        $templates = PageTemplate::active()->get();
        $userPlanSlug = $user->plan?->slug;
        $linkTplCtrl = app(LinkTemplateController::class);
        $lockedFn = fn(?string $required) => $linkTplCtrl->isLocked($required, $userPlanSlug);

        // Recommended-first ordering
        [$recommended, $others] = $templates->partition(function ($t) use ($persona) {
            $tags = $t->recommended_personas ?? [];
            return $persona && is_array($tags) && in_array($persona, $tags, true);
        });

        return view('user.onboarding.template', [
            'persona'     => $persona,
            'personaLabel'=> PersonaCatalog::labelFor($persona),
            'recommended' => $recommended->values(),
            'others'      => $others->values(),
            'lockedFn'    => $lockedFn,
        ]);
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

        // Create a starter biolink for the user. Alias is auto-generated.
        $link = Link::create([
            'user_id'  => $user->id,
            'type'     => 'biolink',
            'alias'    => Link::generateAlias(),
            'title'    => $user->name ? $user->name . "'s page" : 'My Link in Bio',
            'is_active'=> true,
        ]);

        $this->templates->applyPageToLink($link, $tpl->snapshot, /*replace*/ true);

        return redirect()->route('user.links.blocks.editor', $link)
            ->with('success', 'Welcome aboard — your "' . $tpl->name . '" page is ready to edit.');
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
