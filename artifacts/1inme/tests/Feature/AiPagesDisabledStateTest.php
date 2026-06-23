<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guards the graceful-degradation behaviour added in Task #1999.
 *
 * The AI nav group now renders even when the engine is off (see
 * UserSidebarMenuGatingTest), so every AI entry-point page it links to must
 * answer with an informative "AI features are currently turned off" view
 * (HTTP 200) instead of the abort(404) those controllers used to throw.
 *
 * Each listed GET action performs an early `view('user.ai.disabled', ...)`
 * return BEFORE its `ensureEnabled()` 404 guard. A regression that removed
 * that early return — or re-ordered it after the guard — would turn these
 * back into 404s and strand users who clicked the (still-visible) nav link.
 *
 * Mutating/POST actions and drill-down sub-pages keep their 404 guard and are
 * intentionally NOT covered here.
 */
class AiPagesDisabledStateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * AI entry-point GET routes that must degrade to the disabled view.
     * Keyed by the human label the controller stamps into the view title.
     */
    private const ENTRY_POINT_ROUTES = [
        'Mind'       => 'user.ai.mind.show',
        'Minds'      => 'user.minds.index',
        'Persona'    => 'user.ai.persona.show',
        'Personas'   => 'user.ai-personas.index',
        'Companion'  => 'user.ai.companion.show',
        'Companions' => 'user.ai-companions.index',
        'Coach'      => 'user.ai.coach.show',
        'Ask Coach'  => 'user.ai.ask-coach.show',
    ];

    private function user(): User
    {
        return User::create([
            'name'         => 'u' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@ex.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            // Skip the onboarding redirect so the page actually renders.
            'onboarded_at' => now(),
        ]);
    }

    public function test_every_ai_entry_point_returns_disabled_view_when_engine_off(): void
    {
        AiEngineSettings::setEnabled(false);
        // Keep the Ask Coach plan gate open so its show() reaches the engine
        // check rather than short-circuiting elsewhere.
        AiEngineSettings::setAskCoachEnabledPlans([]);

        $user = $this->user();

        foreach (self::ENTRY_POINT_ROUTES as $label => $route) {
            $resp = $this->actingAs($user)->get(route($route, [], false));

            // The key regression guard: an informative 200, not the old 404.
            $this->assertSame(
                200,
                $resp->getStatusCode(),
                "AI page '{$label}' ({$route}) must return 200 when the engine "
                . "is off, got {$resp->getStatusCode()}."
            );
            // It must be the shared disabled view, not the real feature page.
            $resp->assertViewIs('user.ai.disabled');
            $resp->assertSee('AI features are currently turned off');
        }
    }
}
