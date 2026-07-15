<?php

namespace Tests\Feature\Admin;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Coverage for the admin compare & edit grid (GET /admin/plans/compare).
 *
 * The page once 500'd in production because a non-existent Collection
 * method (`mapKeys`) slipped into the Blade-embedded `__planNames` script
 * with no test exercising the rendered view. These tests render the real
 * view so any Blade/PHP crash on the page fails the suite:
 *
 *   1. Comparing two plans returns HTTP 200 and the inline script
 *      contains a valid `__planNames` object keyed by plan id (string
 *      keys) mapping to the plan names.
 *   2. The guard paths still redirect (fewer than 2 ids, more than 6
 *      ids, and unknown ids leaving fewer than 2 loadable plans).
 */
class AdminPlanCompareTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );

        return Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    private function makePlan(array $attrs = []): Plan
    {
        return Plan::create(array_merge([
            'name'               => 'Plan ' . uniqid(),
            'slug'               => 'plan-' . uniqid(),
            'description'        => 'x',
            'monthly_price'      => 10,
            'annual_price'       => 100,
            'trial_days'         => 0,
            'grace_days'         => 0,
            'refund_window_days' => 0,
            'features'           => ['max_links' => 5],
            'status'             => 'active',
            'sort_order'         => 0,
        ], $attrs));
    }

    /**
     * Extract the JSON object assigned to `const __planNames = ...;` from
     * the rendered page and decode it. Fails the test if the assignment is
     * missing or not valid JSON — exactly the breakage class that shipped
     * the production 500.
     */
    private function extractPlanNames(string $html): array
    {
        $matched = preg_match('/const __planNames = (.+?);\s*$/m', $html, $m);
        $this->assertSame(1, $matched, 'Rendered page is missing the __planNames assignment.');

        $expr = $m[1];

        // Blade's @js emits either a bare JSON literal or
        // JSON.parse('<json with \uXXXX escapes>'). Unwrap the latter by
        // decoding the single-quoted JS string first.
        if (preg_match("/^JSON\\.parse\\('(.*)'\\)$/s", $expr, $inner)) {
            $json = json_decode('"' . str_replace('"', '\"', $inner[1]) . '"');
            $this->assertIsString($json, '__planNames JSON.parse payload is not a decodable string: ' . $expr);
        } else {
            $json = html_entity_decode($expr, ENT_QUOTES | ENT_HTML5);
        }

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded, '__planNames is not a valid JSON object: ' . $expr);

        return $decoded;
    }

    public function test_compare_page_renders_with_valid_plan_names_object(): void
    {
        $a = $this->makePlan(['name' => 'Compare Alpha ' . uniqid(), 'sort_order' => 1]);
        $b = $this->makePlan(['name' => 'Compare Beta ' . uniqid(), 'sort_order' => 2]);

        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('admin.plans.compare', ['ids' => [$a->id, $b->id]]));

        $resp->assertOk();
        $resp->assertSee($a->name);
        $resp->assertSee($b->name);

        $names = $this->extractPlanNames($resp->getContent());

        // Keys must be STRING plan ids (the JS looks up __planNames[String(id)]).
        $this->assertArrayHasKey((string) $a->id, $names);
        $this->assertArrayHasKey((string) $b->id, $names);
        $this->assertSame($a->name, $names[(string) $a->id]);
        $this->assertSame($b->name, $names[(string) $b->id]);
    }

    public function test_compare_page_renders_at_the_six_plan_maximum(): void
    {
        $ids = [];
        for ($i = 0; $i < 6; $i++) {
            $ids[] = $this->makePlan(['sort_order' => $i])->id;
        }

        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('admin.plans.compare', ['ids' => $ids]));

        $resp->assertOk();

        $names = $this->extractPlanNames($resp->getContent());
        $this->assertCount(6, array_intersect_key($names, array_flip(array_map('strval', $ids))));
    }

    public function test_fewer_than_two_ids_redirects_back_to_index(): void
    {
        $plan = $this->makePlan();

        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('admin.plans.compare', ['ids' => [$plan->id]]));

        $resp->assertRedirect(route('admin.plans.index'));
        $resp->assertSessionHas('error');
    }

    public function test_more_than_six_ids_redirects_back_to_index(): void
    {
        $ids = [];
        for ($i = 0; $i < 7; $i++) {
            $ids[] = $this->makePlan()->id;
        }

        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('admin.plans.compare', ['ids' => $ids]));

        $resp->assertRedirect(route('admin.plans.index'));
        $resp->assertSessionHas('error');
    }

    public function test_unknown_ids_leaving_fewer_than_two_plans_redirects(): void
    {
        $plan = $this->makePlan();

        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('admin.plans.compare', ['ids' => [$plan->id, 999999999]]));

        $resp->assertRedirect(route('admin.plans.index'));
        $resp->assertSessionHas('error');
    }
}
