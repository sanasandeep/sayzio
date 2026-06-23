<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mobile parity for the graceful AI "engine off" state (Task #1999/#2005).
 *
 * The web app keeps the Ask Coach nav entry visible and degrades the page to
 * an informative "AI features are currently turned off" view (see
 * AiPagesDisabledStateTest). The mobile Ask Coach screen mirrors that: its
 * loader hits GET /api/v1/ai/ask-coach/threads, so that endpoint must answer
 * an informative 200 (`ai_enabled:false`, empty threads) when the engine is
 * off — NOT the hard 404 the mutating actions throw — otherwise the screen
 * alerts and bounces the user straight back out.
 *
 * The mutating/drill-down actions intentionally keep their 404 guard; the
 * screen never calls them once it sees `ai_enabled:false`.
 */
class AskCoachApiDisabledStateTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name'         => 'u' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@ex.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            'onboarded_at' => now(),
        ]);
    }

    public function test_threads_loader_returns_informative_state_when_engine_off(): void
    {
        AiEngineSettings::setEnabled(false);
        // Keep the plan gate open so the loader reaches the engine check
        // rather than short-circuiting on a 403.
        AiEngineSettings::setAskCoachEnabledPlans([]);

        $user = $this->user();
        $this->withToken($user->createToken('test')->plainTextToken);

        $resp = $this->getJson('/api/v1/ai/ask-coach/threads');

        // The key regression guard: an informative 200, not the old 404.
        $resp->assertOk();
        $resp->assertJsonPath('ai_enabled', false);
        $resp->assertJsonPath('threads', []);
    }

    public function test_threads_loader_works_normally_when_engine_on(): void
    {
        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setAskCoachEnabledPlans([]);

        $user = $this->user();
        $this->withToken($user->createToken('test')->plainTextToken);

        $resp = $this->getJson('/api/v1/ai/ask-coach/threads');

        $resp->assertOk();
        $resp->assertJsonPath('ai_enabled', true);
        $resp->assertJsonPath('threads', []);
    }

    public function test_mutating_action_still_hard_404s_when_engine_off(): void
    {
        AiEngineSettings::setEnabled(false);
        AiEngineSettings::setAskCoachEnabledPlans([]);

        $user = $this->user();
        $this->withToken($user->createToken('test')->plainTextToken);

        // Creating a thread is a mutating action: it keeps the 404 guard so
        // no Coach state is created while the engine is off.
        $this->postJson('/api/v1/ai/ask-coach/threads')->assertStatus(404);
    }
}
