<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Services\AI\AiEngineSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regression: after the AI-credits → coin-wallet migration, voice prices
 * are stored as FRACTIONAL coins (coins-per-minute / coins-per-1k-chars,
 * rounded to 4dp). The migration also rewrites old credit voice prices to
 * decimal coin values (e.g. 2 credits @ rate 10 → 0.2 coins). The admin
 * AI Engine form must accept those decimals — validating them as integers
 * silently blocked every save on a migrated install.
 */
class AiEngineVoicePriceSaveTest extends TestCase
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

    public function test_admin_can_save_fractional_voice_coin_prices(): void
    {
        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->put(route('admin.ai-engine.update'), [
                'voice_price_stt' => 0.2,
                'voice_price_tts' => 0.5,
            ]);

        $resp->assertRedirect(route('admin.ai-engine.edit'));
        $resp->assertSessionHasNoErrors();

        $this->assertSame(0.2, AiEngineSettings::voiceSttCoinsPerMinute());
        $this->assertSame(0.5, AiEngineSettings::voiceTtsCoinsPer1kChars());
    }

    public function test_negative_voice_price_is_rejected(): void
    {
        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->put(route('admin.ai-engine.update'), [
                'voice_price_stt' => -1,
            ]);

        $resp->assertSessionHasErrors('voice_price_stt');
    }
}
