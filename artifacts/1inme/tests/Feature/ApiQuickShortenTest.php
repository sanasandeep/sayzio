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

    public function test_honours_chosen_custom_domain(): void
    {
        $user = $this->makeUser(['custom_domains' => true]);
        $domain = \App\Modules\User\Models\Domain::create([
            'user_id'     => $user->id,
            'domain'      => 'go.aqs-example.com',
            'type'        => 'custom',
            'is_verified' => true,
            'is_active'   => true,
        ]);

        $alias = 'aqs-' . Str::lower(Str::random(8));
        $res = $this->post_($user, [
            'destination' => 'https://example.com/page',
            'alias'       => $alias,
            'domain_id'   => $domain->id,
        ]);

        $res->assertCreated()
            ->assertJsonPath('data.short_url', "https://go.aqs-example.com/{$alias}");

        $link = Link::withoutGlobalScopes()->find($res->json('data.id'));
        $this->assertSame($domain->id, $link->domain_id);
    }

    public function test_same_alias_allowed_on_different_domain_namespaces(): void
    {
        $user = $this->makeUser(['custom_domains' => true]);
        $domain = \App\Modules\User\Models\Domain::create([
            'user_id'     => $user->id,
            'domain'      => 'brand.aqs-example.com',
            'type'        => 'custom',
            'is_verified' => true,
            'is_active'   => true,
        ]);

        $alias = 'aqs-' . Str::lower(Str::random(8));
        $this->post_($user, ['destination' => 'https://example.com', 'alias' => $alias])->assertCreated();

        // Same alias on the custom domain lives in a different namespace.
        $this->post_($user, ['destination' => 'https://example.org', 'alias' => $alias, 'domain_id' => $domain->id])
            ->assertCreated();

        // But a duplicate within the SAME domain namespace is rejected.
        $this->post_($user, ['destination' => 'https://example.net', 'alias' => $alias, 'domain_id' => $domain->id])
            ->assertStatus(422);
    }

    public function test_unavailable_domain_is_rejected(): void
    {
        $user  = $this->makeUser(['custom_domains' => true]);
        $other = $this->makeUser(['custom_domains' => true]);
        $foreign = \App\Modules\User\Models\Domain::create([
            'user_id'     => $other->id,
            'domain'      => 'other.aqs-example.com',
            'type'        => 'custom',
            'is_verified' => true,
            'is_active'   => true,
        ]);

        $this->post_($user, ['destination' => 'https://example.com', 'domain_id' => $foreign->id])
            ->assertStatus(422);
        $this->assertSame(0, Link::withoutGlobalScopes()->where('user_id', $user->id)->count());
    }

    public function test_honours_requested_workspace_id(): void
    {
        $user = $this->makeUser();
        $ws = \App\Modules\User\Models\Workspace::create([
            'owner_user_id' => $user->id,
            'name'          => 'AQS WS',
        ]);

        $res = $this->post_($user, [
            'destination'  => 'https://example.com/ws',
            'workspace_id' => $ws->id,
        ]);

        $res->assertCreated();
        $this->assertSame(
            $ws->id,
            Link::withoutGlobalScopes()->find($res->json('data.id'))->workspace_id
        );
    }

    public function test_foreign_workspace_id_falls_back_to_active_workspace(): void
    {
        $user  = $this->makeUser();
        $other = $this->makeUser();
        $foreign = \App\Modules\User\Models\Workspace::create([
            'owner_user_id' => $other->id,
            'name'          => 'Foreign WS',
        ]);

        $res = $this->post_($user, [
            'destination'  => 'https://example.com/foreign-ws',
            'workspace_id' => $foreign->id,
        ]);

        $res->assertCreated();
        $link = Link::withoutGlobalScopes()->find($res->json('data.id'));
        $this->assertNotSame($foreign->id, $link->workspace_id);
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
