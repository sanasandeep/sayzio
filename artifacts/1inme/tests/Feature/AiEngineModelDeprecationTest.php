<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Services\AI\AiEngineSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * OpenAI is retiring the gpt-5 / gpt-5-mini / gpt-5-nano snapshots on
 * Dec 11, 2026, and the gpt-4.1 / gpt-4o families are off the live
 * price sheet. The admin AI Engine page must flag deprecated model
 * names with the retirement date and suggested GPT-5.6 replacement,
 * and the default model list must include the successors.
 */
class AiEngineModelDeprecationTest extends TestCase
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

    public function test_deprecation_helper_flags_retired_models(): void
    {
        $msg = AiEngineSettings::modelDeprecationMessage('gpt-5');
        $this->assertStringContainsString('Dec 11, 2026', $msg);
        $this->assertStringContainsString('gpt-5.6-sol', $msg);

        // Dated snapshots inherit the family deprecation (longest prefix).
        $this->assertStringContainsString('gpt-5.6-terra',
            AiEngineSettings::modelDeprecationMessage('gpt-5-mini-2025-08-07'));

        // Off-price-sheet families get a date-less warning.
        $msg4o = AiEngineSettings::modelDeprecationMessage('gpt-4o-mini');
        $this->assertStringContainsString('deprecated', $msg4o);
        $this->assertStringContainsString('gpt-5.6-terra', $msg4o);

        // Successors are clean.
        $this->assertNull(AiEngineSettings::modelDeprecation('gpt-5.6-sol'));
        $this->assertNull(AiEngineSettings::modelDeprecation('gpt-5.6-terra'));
        $this->assertNull(AiEngineSettings::modelDeprecation('gpt-5.6-luna'));
    }

    public function test_default_models_include_gpt56_successors(): void
    {
        $names = array_column(AiEngineSettings::defaultModels(), null, 'name');
        foreach (['gpt-5.6-sol' => [10.0, 60.0], 'gpt-5.6-terra' => [5.0, 30.0], 'gpt-5.6-luna' => [2.0, 12.0]] as $name => [$in, $out]) {
            $this->assertArrayHasKey($name, $names);
            $this->assertSame($in, $names[$name]['in_coins_per_1k']);
            $this->assertSame($out, $names[$name]['out_coins_per_1k']);
            $this->assertTrue($names[$name]['enabled']);
            $this->assertSame('chat', $names[$name]['kind']);
        }
    }

    public function test_admin_ai_engine_page_shows_deprecation_warnings(): void
    {
        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('admin.ai-engine.edit'));

        $resp->assertOk();
        $resp->assertSee('OpenAI is retiring some of these models');
        $resp->assertSee('Dec 11, 2026');
        $resp->assertSee('gpt-5.6-sol');
        $resp->assertSee('gpt-5.6-terra');
        $resp->assertSee('gpt-5.6-luna');
    }
}
