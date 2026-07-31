<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * REST /api/v1/links/quick-shorten — mobile parity for the web header
 * clipboard quick-shorten (QuickShortenTest covers the web endpoint).
 *
 * Sanctum API tests authenticate with a real Bearer token — Sanctum::actingAs
 * breaks the TouchSessionToken middleware, so we mint a real token and send it
 * via withToken().
 */
class ApiQuickShortenTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $features = []): User
    {
        $plan = Plan::create([
            'name'     => 'AQS Plan ' . Str::random(4),
            'slug'     => 'aqs-' . Str::lower(Str::random(8)),
            'features' => array_merge(['max_links' => -1], $features),
            'status'   => true,
        ]);

        return User::create([
            'name'     => 'AQS User',
            'email'    => 'aqs-' . Str::lower(Str::random(8)) . '@example.com',
            'password' => bcrypt('secret123'),
            'plan_id'  => $plan->id,
        ]);
    }

    private function post_(User $user, array $payload)
    {
        $token = $user->createToken('test')->plainTextToken;
        return $this->withToken($token)->postJson('/api/v1/links/quick-shorten', $payload);
    }

    public function test_shortens_a_web_url(): void
    {
        $user = $this->makeUser();
        $res = $this->post_($user, ['destination' => 'https://example.com/some/page']);

        $res->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'short_url', 'long_url', 'kind']])
            ->assertJsonPath('data.kind', 'url');

        $link = Link::withoutGlobalScopes()->find($res->json('data.id'));
        $this->assertSame('url', $link->type);
        $this->assertSame('https://example.com/some/page', $link->long_url);
        $this->assertSame($user->id, $link->user_id);
        // The Sanctum path never runs SetActiveWorkspace — the controller must
        // stamp the active workspace itself so the link shows on the web list.
        $this->assertNotNull($link->workspace_id);
    }

    public function test_shortens_an_email_and_phone(): void
    {
        $user = $this->makeUser();

        $res = $this->post_($user, ['destination' => 'someone@example.com']);
        $res->assertCreated()->assertJsonPath('data.kind', 'email');
        $this->assertSame(
            'mailto:someone@example.com',
            Link::withoutGlobalScopes()->find($res->json('data.id'))->long_url
        );

        $res = $this->post_($user, ['destination' => '+1 (555) 010-2030']);
        $res->assertCreated()->assertJsonPath('data.kind', 'phone');
        $this->assertSame(
            'tel:+15550102030',
            Link::withoutGlobalScopes()->find($res->json('data.id'))->long_url
        );
    }

    public function test_rejects_unshortenable_content(): void
    {
        $user = $this->makeUser();
        $res = $this->post_($user, ['destination' => 'just some sentence copied from notes']);

        $res->assertStatus(422)->assertJsonPath('error.code', 'not_shortenable');
        $this->assertSame(0, Link::withoutGlobalScopes()->where('user_id', $user->id)->count());
    }

    public function test_honours_custom_alias_and_rejects_taken_alias(): void
    {
        $user  = $this->makeUser();
        $alias = 'aqs-' . Str::lower(Str::random(8));

        $res = $this->post_($user, ['destination' => 'https://example.com', 'alias' => $alias]);
        $res->assertCreated();
        $this->assertSame($alias, Link::withoutGlobalScopes()->find($res->json('data.id'))->alias);

        // Same alias again — must trip the UniqueAliasCi rule (422 validation).
        $this->post_($user, ['destination' => 'https://example.org', 'alias' => $alias])
            ->assertStatus(422);
    }

    public function test_enforces_plan_link_cap(): void
    {
        $user = $this->makeUser(['max_links' => 1]);

        $this->post_($user, ['destination' => 'https://example.com/one'])->assertCreated();

        $this->post_($user, ['destination' => 'https://example.com/two'])
            ->assertStatus(402)
            ->assertJsonPath('error.code', 'plan_upgrade_required');
    }
}
