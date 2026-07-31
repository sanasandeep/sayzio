<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Locks in Text Page (type=text) creation end-to-end:
 *
 *  Web (POST user.links.store):
 *   - happy path persists settings['text']['content'] and redirects to the
 *     link editor with a success flash;
 *   - missing text_content is rejected (required_if:type,text);
 *   - oversized text (> 20,000 chars) is rejected;
 *   - plan gate: module_text=false blocks creation with an inline error;
 *   - plan cap: max_text_pages reached blocks creation with an inline error.
 *
 *  API (POST /api/v1/links):
 *   - happy path (type=text + settings.text.content) returns 201 with the
 *     unified {data:{link}} envelope and stores the content;
 *   - missing settings.text.content returns a 422 {error:{...}} envelope;
 *   - plan gate returns 402 plan_upgrade_required.
 *
 * Link has no factory — links are created via Link::create / relations.
 * Aliases avoid reserved single-letter catch-all prefixes (see PaidPageTest).
 */
class TextPageCreateTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $features = []): Plan
    {
        $slug = 'p' . Str::lower(Str::random(6));
        return Plan::create([
            'name' => $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            'features' => $features,
        ]);
    }

    private function user(?Plan $plan = null): User
    {
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::lower(Str::random(8)) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'handle'   => 'h' . Str::lower(Str::random(10)),
            'plan_id'  => $plan?->id,
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    private function alias(): string
    {
        return 'zt' . Str::lower(Str::random(8));
    }

    /** Existing text link created directly (counts toward max_text_pages). */
    private function existingTextLink(User $u): Link
    {
        return $u->links()->create([
            'user_id'  => $u->id,
            'type'     => 'text',
            'alias'    => $this->alias(),
            'is_active'=> true,
            'title'    => 'Existing text page',
            'settings' => ['text' => ['content' => 'already here']],
        ]);
    }

    // ===== Web form =====

    public function test_web_create_happy_path_persists_content_and_redirects_to_editor(): void
    {
        $u = $this->user();
        $content = "Hello world\nThis is my shareable text page.";

        $response = $this->actingAs($u)->post(route('user.links.store'), [
            'type'         => 'text',
            'title'        => 'My Text Page',
            'alias'        => $this->alias(),
            'text_content' => $content,
        ]);

        $link = Link::where('user_id', $u->id)->where('type', 'text')->first();
        $this->assertNotNull($link, 'text link should be created');
        $this->assertSame($content, $link->settings['text']['content'] ?? null);
        $this->assertSame('My Text Page', $link->title);

        $response->assertRedirect(route('user.links.edit', $link));
        $response->assertSessionHas('success');
    }

    public function test_web_create_rejects_missing_text_content(): void
    {
        $u = $this->user();

        $this->actingAs($u)
            ->from(route('user.links.text.create'))
            ->post(route('user.links.store'), [
                'type'  => 'text',
                'title' => 'No content',
                'alias' => $this->alias(),
            ])
            ->assertSessionHasErrors('text_content');

        $this->assertSame(0, Link::where('user_id', $u->id)->where('type', 'text')->count());
    }

    public function test_web_create_rejects_oversized_text_content(): void
    {
        $u = $this->user();

        $this->actingAs($u)
            ->post(route('user.links.store'), [
                'type'         => 'text',
                'alias'        => $this->alias(),
                'text_content' => str_repeat('a', 20001),
            ])
            ->assertSessionHasErrors('text_content');

        $this->assertSame(0, Link::where('user_id', $u->id)->where('type', 'text')->count());
    }

    public function test_web_create_blocked_when_module_text_disabled(): void
    {
        $u = $this->user($this->plan(['module_text' => false]));

        $response = $this->actingAs($u)->post(route('user.links.store'), [
            'type'         => 'text',
            'alias'        => $this->alias(),
            'text_content' => 'blocked by plan',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Text Page', session('error'));
        $this->assertSame(0, Link::where('user_id', $u->id)->where('type', 'text')->count());
    }

    public function test_web_create_blocked_when_max_text_pages_cap_reached(): void
    {
        $u = $this->user($this->plan(['module_text' => true, 'max_text_pages' => 1]));
        $this->existingTextLink($u);

        $response = $this->actingAs($u)->post(route('user.links.store'), [
            'type'         => 'text',
            'alias'        => $this->alias(),
            'text_content' => 'one too many',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('limit', session('error'));
        $this->assertSame(1, Link::where('user_id', $u->id)->where('type', 'text')->count());
    }

    public function test_web_create_form_renders(): void
    {
        $u = $this->user();

        $this->actingAs($u)
            ->get(route('user.links.text.create'))
            ->assertOk()
            ->assertViewIs('user.links.create-text');
    }

    // ===== REST API =====

    private function token(User $u): string
    {
        return $u->createToken('test')->plainTextToken;
    }

    public function test_api_create_happy_path(): void
    {
        $u = $this->user();
        $content = 'API-created text page body.';

        $response = $this->withToken($this->token($u))->postJson('/api/v1/links', [
            'type'     => 'text',
            'title'    => 'API Text Page',
            'settings' => ['text' => ['content' => $content]],
        ]);

        $response->assertCreated();
        $linkId = $response->json('data.link.id');
        $this->assertNotNull($linkId);

        $link = Link::find($linkId);
        $this->assertSame('text', $link->type);
        $this->assertSame($u->id, $link->user_id);
        $this->assertSame($content, $link->settings['text']['content'] ?? null);
    }

    public function test_api_create_missing_content_returns_error_envelope(): void
    {
        $u = $this->user();

        $response = $this->withToken($this->token($u))->postJson('/api/v1/links', [
            'type' => 'text',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['error' => ['message']]);
        $this->assertSame(0, Link::where('user_id', $u->id)->where('type', 'text')->count());
    }

    public function test_api_create_oversized_content_returns_error_envelope(): void
    {
        $u = $this->user();

        $response = $this->withToken($this->token($u))->postJson('/api/v1/links', [
            'type'     => 'text',
            'settings' => ['text' => ['content' => str_repeat('a', 20001)]],
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['error' => ['message']]);
        $this->assertSame(0, Link::where('user_id', $u->id)->where('type', 'text')->count());
    }

    public function test_api_create_blocked_when_module_text_disabled(): void
    {
        $u = $this->user($this->plan(['module_text' => false]));

        $response = $this->withToken($this->token($u))->postJson('/api/v1/links', [
            'type'     => 'text',
            'settings' => ['text' => ['content' => 'blocked']],
        ]);

        $response->assertStatus(402);
        $response->assertJsonPath('error.code', 'plan_upgrade_required');
        $this->assertSame(0, Link::where('user_id', $u->id)->where('type', 'text')->count());
    }

    public function test_api_create_blocked_when_cap_reached(): void
    {
        $u = $this->user($this->plan(['module_text' => true, 'max_text_pages' => 1]));
        $this->existingTextLink($u);

        $response = $this->withToken($this->token($u))->postJson('/api/v1/links', [
            'type'     => 'text',
            'settings' => ['text' => ['content' => 'one too many']],
        ]);

        $response->assertStatus(402);
        $response->assertJsonPath('error.code', 'plan_upgrade_required');
        $this->assertSame(1, Link::where('user_id', $u->id)->where('type', 'text')->count());
    }

    /** Bearer-token helper (withToken leaks as a default header — scope per call). */
    public function withToken(string $token, string $type = 'Bearer')
    {
        return $this->withHeaders(['Authorization' => $type . ' ' . $token]);
    }
}
