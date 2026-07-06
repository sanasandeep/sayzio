<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Support\DashboardPresets;
use App\Modules\User\Support\DashboardWidgetCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #3822 — keep the dashboard "Customize" modal in sync between the web
 * dashboard and the mobile app.
 *
 * The web dashboard (App\Modules\User\Controllers\DashboardController) and the
 * mobile REST endpoint (App\Modules\Api\Controllers\DashboardLayoutController)
 * both surface the same preset lineup + grouped widget catalog. Today they both
 * delegate to the shared DashboardPresets/DashboardWidgetCatalog support
 * classes, so drift "shouldn't" happen — but nothing stops a future edit from
 * filtering, re-ordering, or dropping a preset/widget on just one surface
 * (e.g. plan-gating the mobile payload, or passing a hand-rolled subset to the
 * web view). These tests pin the two payloads together AND against the source
 * constants so:
 *
 *   - Adding a preset or widget that reaches only one surface fails the test.
 *   - Adding a preset or widget to the source constants without it reaching
 *     BOTH surfaces fails the test.
 *
 * @see \App\Modules\User\Controllers\DashboardController::index
 * @see \App\Modules\Api\Controllers\DashboardLayoutController::show
 * @see \App\Modules\User\Support\DashboardPresets::forFrontend
 * @see \App\Modules\User\Support\DashboardWidgetCatalog::groupedForFrontend
 */
class DashboardCustomizeWebMobileParityTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        $user = User::create(array_merge([
            'name'         => 'U ' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@ex.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            // Past the first-run onboarding gate so the web dashboard renders
            // instead of redirecting (matches DashboardCustomizeStepRegressionTest).
            'onboarded_at' => now()->subMonth(),
        ], $attrs));
        $user->ensureDefaultWorkspace();

        return $user->fresh();
    }

    /**
     * The catalog/grouped-catalog/presets the web dashboard passes to its
     * "Customize dashboard" modal, pulled straight from the rendered view's
     * data so the test reflects exactly what the Blade template receives.
     *
     * @return array{catalog:mixed, grouped_catalog:mixed, presets:mixed}
     */
    private function webPayload(User $user): array
    {
        $response = $this->actingAs($user)
            ->get(route('user.dashboard'))
            ->assertOk();

        return [
            'catalog'         => $response->viewData('dashboardCatalog'),
            'grouped_catalog' => $response->viewData('dashboardGroupedCatalog'),
            'presets'         => $response->viewData('dashboardPresets'),
        ];
    }

    /**
     * The catalog/grouped-catalog/presets the mobile app receives from the
     * GET /api/v1/dashboard/layout endpoint.
     *
     * A real Sanctum token (not Sanctum::actingAs) is required, otherwise the
     * TouchSessionToken middleware 500s on the mocked access token — see the
     * "sanctum-api-tests" memory note.
     *
     * @return array{catalog:mixed, grouped_catalog:mixed, presets:mixed}
     */
    private function mobilePayload(User $user): array
    {
        $token = $user->createToken('parity-test')->plainTextToken;

        $data = $this->withToken($token)
            ->getJson('/api/v1/dashboard/layout')
            ->assertOk()
            ->json('data');

        return [
            'catalog'         => $data['catalog'] ?? null,
            'grouped_catalog' => $data['grouped_catalog'] ?? null,
            'presets'         => $data['presets'] ?? null,
        ];
    }

    public function test_web_and_mobile_expose_identical_presets_and_widget_catalog(): void
    {
        $user = $this->makeUser();

        $web    = $this->webPayload($user);
        $mobile = $this->mobilePayload($this->makeUser());

        // Same keys, labels, tabs, order — byte-for-byte between the two
        // surfaces. array/JSON round-tripping is normalized by decoding the
        // web side through json_encode/json_decode so associative-vs-list
        // shapes compare the same way the mobile client sees them.
        $this->assertSame(
            json_decode(json_encode($web['presets']), true),
            $mobile['presets'],
            'The preset lineup surfaced to mobile drifted from the web dashboard.'
        );
        $this->assertSame(
            json_decode(json_encode($web['catalog']), true),
            $mobile['catalog'],
            'The widget catalog surfaced to mobile drifted from the web dashboard.'
        );
        $this->assertSame(
            json_decode(json_encode($web['grouped_catalog']), true),
            $mobile['grouped_catalog'],
            'The grouped widget catalog surfaced to mobile drifted from the web dashboard.'
        );
    }

    public function test_both_surfaces_cover_every_preset_and_widget_defined_in_source(): void
    {
        $expectedPresetKeys = DashboardPresets::presetKeys();
        $expectedWidgetKeys = DashboardWidgetCatalog::allKeys();
        $expectedTabKeys    = array_keys(DashboardWidgetCatalog::TAB_LABELS);

        foreach (['web' => $this->webPayload($this->makeUser()), 'mobile' => $this->mobilePayload($this->makeUser())] as $surface => $payload) {
            $presets = json_decode(json_encode($payload['presets']), true);
            $catalog = json_decode(json_encode($payload['catalog']), true);
            $grouped = json_decode(json_encode($payload['grouped_catalog']), true);

            $this->assertSame(
                $expectedPresetKeys,
                array_column($presets, 'key'),
                "The {$surface} surface is missing (or reordered) a preset defined in DashboardPresets::PRESETS."
            );

            $this->assertSame(
                $expectedWidgetKeys,
                array_column($catalog, 'key'),
                "The {$surface} surface is missing (or reordered) a widget defined in DashboardWidgetCatalog::WIDGETS."
            );

            // Grouped catalog must expose every catalog widget exactly once,
            // grouped only into tabs that actually declare a label.
            $groupedWidgetKeys = [];
            foreach ($grouped as $group) {
                $this->assertContains(
                    $group['tab'],
                    $expectedTabKeys,
                    "The {$surface} grouped catalog contains an unknown tab '{$group['tab']}'."
                );
                foreach ($group['widgets'] as $widget) {
                    $groupedWidgetKeys[] = $widget['key'];
                }
            }

            sort($groupedWidgetKeys);
            $expectedSorted = $expectedWidgetKeys;
            sort($expectedSorted);
            $this->assertSame(
                $expectedSorted,
                $groupedWidgetKeys,
                "The {$surface} grouped catalog dropped or duplicated a widget relative to the flat catalog."
            );
        }
    }
}
