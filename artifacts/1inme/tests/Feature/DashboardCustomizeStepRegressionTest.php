<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Support\DashboardPresets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #3814 — regression guard for the two dashboard "customize" entry
 * points that Task #3811 deliberately split apart.
 *
 * The "Layout: {label}" badge and the trimmed-tiles "Switch layout" hint
 * must open the modal on its preset-only `quick` step, while the full
 * "Customize dashboard" button must open it on the `picker` step (presets +
 * "Design with AI"). Both surfaces dispatch the same
 * `open-dashboard-customize` browser event, distinguished only by the
 * `step` detail — a future edit could easily collapse them back into an
 * identical modal without anyone noticing. These tests assert the wiring
 * stays distinct.
 *
 * @see \App\Modules\User\Controllers\DashboardController::index
 * @see resources/views/user/dashboard/index.blade.php
 * @see resources/views/user/dashboard/customize-modal.blade.php
 * @see resources/views/user/dashboard/partials/trimmed-hint.blade.php
 */
class DashboardCustomizeStepRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        $user = User::create(array_merge([
            'name'        => 'U ' . Str::random(4),
            'email'       => 'u' . Str::random(8) . '@ex.com',
            'password'    => Hash::make('x'),
            'status'      => 'active',
            // Past the first-run onboarding gate so the dashboard renders
            // instead of redirecting. Dated well outside the 14-day
            // recency window so the one-time WhatsApp/privacy onboarding
            // nudges (RedirectToOnboarding) don't fire either.
            'onboarded_at' => now()->subMonth(),
        ], $attrs));
        $user->ensureDefaultWorkspace();

        return $user->fresh();
    }

    /**
     * Extract every element that dispatches `open-dashboard-customize`,
     * returning [full-button-markup => step] so a test can couple the step
     * to the button's visible label.
     *
     * @return array<string, string>
     */
    private function customizeTriggers(string $html): array
    {
        // Each trigger is a <button> whose @click dispatches the event with a
        // `step: '<x>'` detail. Capture the whole element so we can inspect
        // its inner label text.
        preg_match_all(
            "/<button\b[^>]*open-dashboard-customize[^>]*?step:\s*'(\w+)'.*?<\/button>/s",
            $html,
            $matches,
            PREG_SET_ORDER
        );

        $out = [];
        foreach ($matches as $m) {
            $out[$m[0]] = $m[1];
        }

        return $out;
    }

    public function test_layout_badge_opens_quick_step_and_customize_button_opens_picker_step(): void
    {
        $user = $this->makeUser();

        $html = $this->actingAs($user)
            ->get(route('user.dashboard'))
            ->assertOk()
            ->getContent();

        $triggers = $this->customizeTriggers($html);

        // Locate the two header controls by their visible label.
        $badgeStep    = null; // "Layout: {label}" badge
        $customizeStep = null; // "Customize dashboard" button
        foreach ($triggers as $markup => $step) {
            if (str_contains($markup, 'Layout:')) {
                $badgeStep = $step;
            }
            if (str_contains($markup, 'Customize dashboard')) {
                $customizeStep = $step;
            }
        }

        $this->assertNotNull($badgeStep, 'The "Layout: {label}" badge trigger was not found on the dashboard.');
        $this->assertNotNull($customizeStep, 'The "Customize dashboard" button trigger was not found on the dashboard.');

        // The core regression: the two header controls must land on
        // DISTINCT steps — badge -> quick, customize -> picker.
        $this->assertSame('quick', $badgeStep, 'The layout badge must open the modal on the "quick" (preset-only) step.');
        $this->assertSame('picker', $customizeStep, 'The "Customize dashboard" button must open the modal on the "picker" (presets + Design with AI) step.');
        $this->assertNotSame(
            $badgeStep,
            $customizeStep,
            'The layout badge and "Customize dashboard" button must open different modal steps.'
        );
    }

    public function test_trimmed_tiles_switch_layout_hint_opens_quick_step(): void
    {
        $user = $this->makeUser();

        // A non-default preset that hides at least one Overview-tab tile
        // triggers the "some tiles are hidden" hint on the Overview tab,
        // which carries the "Switch layout" shortcut.
        DashboardPresets::applyPreset($user->fresh(), 'content_posts');

        $html = $this->actingAs($user->fresh())
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('Some tiles are hidden by your current layout.')
            ->getContent();

        $triggers = $this->customizeTriggers($html);

        $hintStep = null; // "Switch layout" trimmed-tiles hint
        foreach ($triggers as $markup => $step) {
            if (str_contains($markup, 'Switch layout')) {
                $hintStep = $step;
            }
        }

        $this->assertNotNull($hintStep, 'The trimmed-tiles "Switch layout" hint trigger was not found on the dashboard.');

        // The hint behaves like the quick picker, NOT the full picker.
        $this->assertSame('quick', $hintStep, 'The "Switch layout" hint must open the modal on the "quick" (preset-only) step.');
    }
}
