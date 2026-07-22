<?php

namespace Tests\Feature;

use App\Modules\User\Models\BrandStudioPreset;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Saved kit combos in AI Brand Studio (Task #5577).
 *
 * Users can save the current kit composition under a name, see it alongside
 * the built-in presets (web index + mobile API index), re-apply it, and
 * delete it. Presets are owned per user, capped at MAX_PER_USER, and names
 * are upserted (same name overwrites).
 */
class BrandStudioPresetTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Combo Saver',
            'email'    => 'combo-' . Str::random(8) . '@example.com',
            'password' => bcrypt('secret-password'),
        ]);
    }

    private function rows(): array
    {
        return [
            ['kind' => 'biolink', 'count' => 1, 'purpose' => 'Main page'],
            ['kind' => 'qr_code', 'count' => 2, 'purpose' => ''],
        ];
    }

    public function test_web_store_apply_shape_and_delete(): void
    {
        AiEngineSettings::setEnabled(true);
        $user = $this->makeUser();

        $res = $this->actingAs($user)->postJson(route('user.brand-studio.presets.store'), [
            'name'        => 'Event kit',
            'composition' => $this->rows(),
        ]);

        $res->assertOk();
        $res->assertJsonPath('preset.label', 'Event kit');
        $res->assertJsonPath('preset.rows.0.kind', 'biolink');
        $id = $res->json('preset.id');

        // Same name upserts, does not duplicate.
        $this->actingAs($user)->postJson(route('user.brand-studio.presets.store'), [
            'name'        => 'Event kit',
            'composition' => [['kind' => 'form', 'count' => 1, 'purpose' => 'RSVP']],
        ])->assertOk();
        $this->assertSame(1, BrandStudioPreset::where('user_id', $user->id)->count());

        // Index page exposes the saved preset.
        $page = $this->actingAs($user)->get(route('user.brand-studio.index'));
        $page->assertOk();
        $page->assertSee('Event kit');

        // Delete.
        $this->actingAs($user)
            ->deleteJson(route('user.brand-studio.presets.destroy', $id))
            ->assertOk();
        $this->assertSame(0, BrandStudioPreset::count());
    }

    public function test_web_rejects_invalid_kind_and_enforces_cap(): void
    {
        AiEngineSettings::setEnabled(true);
        $user = $this->makeUser();

        $this->actingAs($user)->postJson(route('user.brand-studio.presets.store'), [
            'name'        => 'Bad',
            'composition' => [['kind' => 'nonsense', 'count' => 1]],
        ])->assertStatus(422);

        for ($i = 0; $i < BrandStudioPreset::MAX_PER_USER; $i++) {
            BrandStudioPreset::create([
                'user_id'     => $user->id,
                'name'        => 'Combo ' . $i,
                'composition' => $this->rows(),
            ]);
        }

        $this->actingAs($user)->postJson(route('user.brand-studio.presets.store'), [
            'name'        => 'One too many',
            'composition' => $this->rows(),
        ])->assertStatus(422);
    }

    public function test_web_cannot_delete_foreign_preset(): void
    {
        AiEngineSettings::setEnabled(true);
        $owner    = $this->makeUser();
        $intruder = $this->makeUser();

        $preset = BrandStudioPreset::create([
            'user_id'     => $owner->id,
            'name'        => 'Mine',
            'composition' => $this->rows(),
        ]);

        $this->actingAs($intruder)
            ->deleteJson(route('user.brand-studio.presets.destroy', $preset->id))
            ->assertForbidden();
        $this->assertSame(1, BrandStudioPreset::count());
    }

    public function test_web_rename_preserves_rows_and_unique_names(): void
    {
        AiEngineSettings::setEnabled(true);
        $user = $this->makeUser();

        $preset = BrandStudioPreset::create([
            'user_id'     => $user->id,
            'name'        => 'Event kit',
            'composition' => $this->rows(),
        ]);
        $other = BrandStudioPreset::create([
            'user_id'     => $user->id,
            'name'        => 'Launch pack',
            'composition' => $this->rows(),
        ]);

        // Rename keeps the composition intact.
        $res = $this->actingAs($user)->patchJson(
            route('user.brand-studio.presets.rename', $preset->id),
            ['name' => 'Conference kit'],
        );
        $res->assertOk();
        $res->assertJsonPath('preset.label', 'Conference kit');
        $res->assertJsonPath('preset.rows.0.kind', 'biolink');
        $this->assertSame('Conference kit', $preset->fresh()->name);

        // Renaming onto another combo's name is rejected (unique per user).
        $this->actingAs($user)->patchJson(
            route('user.brand-studio.presets.rename', $preset->id),
            ['name' => 'Launch pack'],
        )->assertStatus(422);
        $this->assertSame('Conference kit', $preset->fresh()->name);

        // Renaming to its own current name is a no-op success.
        $this->actingAs($user)->patchJson(
            route('user.brand-studio.presets.rename', $other->id),
            ['name' => 'Launch pack'],
        )->assertOk();

        // Blank names are rejected.
        $this->actingAs($user)->patchJson(
            route('user.brand-studio.presets.rename', $preset->id),
            ['name' => '   '],
        )->assertStatus(422);
    }

    public function test_web_cannot_rename_foreign_preset(): void
    {
        AiEngineSettings::setEnabled(true);
        $owner    = $this->makeUser();
        $intruder = $this->makeUser();

        $preset = BrandStudioPreset::create([
            'user_id'     => $owner->id,
            'name'        => 'Mine',
            'composition' => $this->rows(),
        ]);

        $this->actingAs($intruder)->patchJson(
            route('user.brand-studio.presets.rename', $preset->id),
            ['name' => 'Stolen'],
        )->assertForbidden();
        $this->assertSame('Mine', $preset->fresh()->name);
    }

    public function test_api_rename(): void
    {
        AiEngineSettings::setEnabled(true);
        $user  = $this->makeUser();
        $token = $user->createToken('t')->plainTextToken;

        $preset = BrandStudioPreset::create([
            'user_id'     => $user->id,
            'name'        => 'Old name',
            'composition' => $this->rows(),
        ]);

        $res = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/brand-studio/presets/' . $preset->id, ['name' => 'New name']);
        $res->assertOk();
        $res->assertJsonPath('data.preset.label', 'New name');
        $res->assertJsonPath('data.preset.rows.1.kind', 'qr_code');
        $this->assertSame('New name', $preset->fresh()->name);

        // Foreign rename is a 404.
        $other      = $this->makeUser();
        $otherToken = $other->createToken('t')->plainTextToken;
        $this->flushHeaders();
        $this->withHeader('Authorization', 'Bearer ' . $otherToken)
            ->patchJson('/api/v1/brand-studio/presets/' . $preset->id, ['name' => 'Hijack'])
            ->assertNotFound();
        $this->assertSame('New name', $preset->fresh()->name);
    }

    public function test_api_index_store_and_delete(): void
    {
        AiEngineSettings::setEnabled(true);
        $user  = $this->makeUser();
        $token = $user->createToken('t')->plainTextToken;

        $store = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/brand-studio/presets', [
                'name'        => 'Launch combo',
                'composition' => $this->rows(),
            ]);
        $store->assertOk();
        $store->assertJsonPath('data.preset.label', 'Launch combo');
        $id = $store->json('data.preset.id');

        $this->flushHeaders();
        $index = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/brand-studio');
        $index->assertOk();
        $index->assertJsonPath('data.saved_presets.0.label', 'Launch combo');
        $index->assertJsonPath('data.saved_presets.0.rows.1.kind', 'qr_code');

        // Foreign delete is a 404, own delete succeeds.
        $other      = $this->makeUser();
        $otherToken = $other->createToken('t')->plainTextToken;
        $this->flushHeaders();
        $this->withHeader('Authorization', 'Bearer ' . $otherToken)
            ->deleteJson('/api/v1/brand-studio/presets/' . $id)
            ->assertNotFound();

        $this->flushHeaders();
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/brand-studio/presets/' . $id)
            ->assertOk();
        $this->assertSame(0, BrandStudioPreset::count());
    }
}
