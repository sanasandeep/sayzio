<?php

namespace Tests\Feature\Api;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\EventsModule;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #6729 — the mobile app learns the platform-wide Events module state
 * from the feature-states bootstrap response (`events_module_enabled`) so it
 * can hide event entry points instead of surfacing API 404s. These tests pin
 * the field's presence and its wiring to the EventsModule/AppSetting toggle.
 *
 * NOTE: auth uses a real Bearer token, NOT Sanctum::actingAs — the latter
 * skips the TouchSessionToken middleware the API path relies on.
 */
class FeatureStatesEventsModuleTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Feat ' . Str::random(4),
            'email'    => 'feat-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ])->fresh();
    }

    public function test_feature_states_reports_events_module_enabled_by_default(): void
    {
        $user = $this->makeUser();

        $res = $this->getJson('/api/v1/feature-states', [
            'Authorization' => 'Bearer ' . $this->tokenFor($user),
        ]);

        $res->assertOk()
            ->assertJsonPath('data.events_module_enabled', true);

        $this->assertIsArray($res->json('data.features'));
    }

    public function test_feature_states_reports_events_module_disabled_when_switched_off(): void
    {
        AppSetting::put(EventsModule::KEY, false);

        $user = $this->makeUser();

        $res = $this->getJson('/api/v1/feature-states', [
            'Authorization' => 'Bearer ' . $this->tokenFor($user),
        ]);

        $res->assertOk()
            ->assertJsonPath('data.events_module_enabled', false);
    }
}
