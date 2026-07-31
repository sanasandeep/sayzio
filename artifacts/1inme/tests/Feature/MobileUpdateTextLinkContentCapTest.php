<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * REST API parity for the Text Page 20k-char body cap on the *update* path.
 *
 * The create path (Api\LinkController::store) validates settings.text.content
 * with max:20000, matching the web edit form's text_content rule. The PATCH
 * update() path accepted any settings array with no per-key validation, so a
 * third-party API client could store an arbitrarily large body on an existing
 * text link, bypassing the shared limit.
 *
 * Sanctum API tests authenticate with a real Bearer token — Sanctum::actingAs
 * breaks the TouchSessionToken middleware, so we mint a real token.
 */
class MobileUpdateTextLinkContentCapTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create()->fresh();
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function makeTextLink(User $user, string $content = 'hello'): Link
    {
        return Link::create([
            'user_id'   => $user->id,
            'type'      => 'text',
            'alias'     => 'txt' . strtolower(str()->random(8)),
            'is_active' => true,
            'settings'  => ['text' => ['content' => $content]],
        ]);
    }

    public function test_update_rejects_oversized_text_content(): void
    {
        $user = $this->makeUser();
        $link = $this->makeTextLink($user);

        $resp = $this->withToken($this->token($user))
            ->patchJson('/api/v1/links/' . $link->id, [
                'settings' => ['text' => ['content' => str_repeat('a', 20001)]],
            ]);

        $resp->assertStatus(422);
        $resp->assertJsonPath('error.code', 'validation_failed');
        $resp->assertJsonStructure(['error' => ['details' => ['settings.text.content']]]);
        $this->assertSame(
            'hello',
            $link->fresh()->settings['text']['content'],
            'an oversized body must not be stored via the REST update path'
        );
    }

    public function test_update_accepts_text_content_at_the_cap(): void
    {
        $user = $this->makeUser();
        $link = $this->makeTextLink($user);
        $body = str_repeat('b', 20000);

        $resp = $this->withToken($this->token($user))
            ->patchJson('/api/v1/links/' . $link->id, [
                'settings' => ['text' => ['content' => $body]],
            ]);

        $resp->assertStatus(200);
        $this->assertSame($body, $link->fresh()->settings['text']['content']);
    }

    public function test_update_other_settings_keys_still_deep_merge(): void
    {
        // The cap must not disturb the existing deep-merge behavior for other
        // settings keys, and non-text links stay unvalidated for this key.
        $user = $this->makeUser();
        $link = $this->makeTextLink($user, 'keep-me');

        $resp = $this->withToken($this->token($user))
            ->patchJson('/api/v1/links/' . $link->id, [
                'settings' => ['appearance' => ['theme' => 'dark']],
            ]);

        $resp->assertStatus(200);
        $fresh = $link->fresh();
        $this->assertSame('dark', $fresh->settings['appearance']['theme']);
        $this->assertSame('keep-me', $fresh->settings['text']['content']);
    }
}
