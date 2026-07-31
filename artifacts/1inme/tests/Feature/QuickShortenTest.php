<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Header clipboard quick-shorten endpoint (Task #6285).
 */
class QuickShortenTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $features = []): User
    {
        $plan = Plan::create([
            'name'     => 'QS Plan ' . Str::random(4),
            'slug'     => 'qs-' . Str::lower(Str::random(8)),
            'features' => array_merge(['max_links' => -1], $features),
            'status'   => true,
        ]);

        $user = User::create([
            'name'     => 'QS User',
            'email'    => 'qs-' . Str::lower(Str::random(8)) . '@example.com',
            'password' => bcrypt('secret123'),
            'plan_id'  => $plan->id,
        ]);

        $ws = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);

        return $user;
    }

    private function post_(User $user, array $payload)
    {
        return $this->actingAs($user)->postJson(route('user.links.quick-shorten'), $payload);
    }

    public function test_shortens_a_web_url(): void
    {
        $user = $this->makeUser();
        $res = $this->post_($user, ['destination' => 'https://example.com/some/page']);

        $res->assertOk()->assertJsonStructure(['id', 'short_url', 'edit_url'])
            ->assertJsonPath('kind', 'url');

        $link = Link::withoutGlobalScopes()->find($res->json('id'));
        $this->assertSame('url', $link->type);
        $this->assertSame('https://example.com/some/page', $link->long_url);
        $this->assertSame($user->id, $link->user_id);
    }

    public function test_bare_domain_gets_https_prefix(): void
    {
        $user = $this->makeUser();
        $res = $this->post_($user, ['destination' => 'example.com/page?a=1']);
        $res->assertOk()->assertJsonPath('long_url', 'https://example.com/page?a=1');
    }

    public function test_email_becomes_mailto(): void
    {
        $user = $this->makeUser();
        $res = $this->post_($user, ['destination' => 'hello@example.com']);
        $res->assertOk()->assertJsonPath('kind', 'email')
            ->assertJsonPath('long_url', 'mailto:hello@example.com');
    }

    public function test_phone_becomes_tel(): void
    {
        $user = $this->makeUser();
        $res = $this->post_($user, ['destination' => '+1 (415) 555-0123']);
        $res->assertOk()->assertJsonPath('kind', 'phone')
            ->assertJsonPath('long_url', 'tel:+14155550123');
    }

    public function test_plain_text_creates_text_page_link(): void
    {
        $user = $this->makeUser();
        $res = $this->post_($user, ['destination' => "just some sentence with words\nsecond line"]);
        $res->assertOk()->assertJsonPath('kind', 'text');

        $link = \App\Modules\User\Models\Link::withoutGlobalScopes()->find($res->json('id'));
        $this->assertSame('text', $link->type);
        $this->assertNull($link->long_url);
        $this->assertSame("just some sentence with words\nsecond line", data_get($link->settings, 'text.content'));
        $this->assertSame('just some sentence with words', $link->title);
    }

    public function test_overlong_url_is_rejected_not_turned_into_text(): void
    {
        $user = $this->makeUser();
        $url = 'https://example.com/?q=' . str_repeat('a', 2100);
        $res = $this->post_($user, ['destination' => $url]);
        $res->assertStatus(422)->assertJsonValidationErrors(['destination']);
    }

    public function test_custom_alias_is_used_and_duplicates_rejected(): void
    {
        $user = $this->makeUser();
        $alias = 'qs-' . Str::lower(Str::random(8));

        $this->post_($user, ['destination' => 'https://example.com', 'alias' => $alias])
            ->assertOk()->assertJsonPath('short_url', url('/' . $alias));

        $this->post_($user, ['destination' => 'https://example.org', 'alias' => $alias])
            ->assertStatus(422)->assertJsonValidationErrors(['alias']);
    }

    public function test_invalid_alias_characters_rejected(): void
    {
        $user = $this->makeUser();
        $this->post_($user, ['destination' => 'https://example.com', 'alias' => 'bad alias!'])
            ->assertStatus(422)->assertJsonValidationErrors(['alias']);
    }

    public function test_quick_shorten_honours_chosen_custom_domain(): void
    {
        $user = $this->makeUser(['custom_domains' => true]);
        $domain = \App\Modules\User\Models\Domain::create([
            'user_id'     => $user->id,
            'domain'      => 'go.qs-example.com',
            'type'        => 'custom',
            'is_verified' => true,
            'is_active'   => true,
        ]);

        $alias = 'qs-' . Str::lower(Str::random(8));
        $res = $this->post_($user, [
            'destination' => 'https://example.com/page',
            'alias'       => $alias,
            'domain_id'   => $domain->id,
        ]);

        $res->assertOk()->assertJsonPath('short_url', "https://go.qs-example.com/{$alias}");

        $link = Link::withoutGlobalScopes()->find($res->json('id'));
        $this->assertSame($domain->id, $link->domain_id);
    }

    public function test_same_alias_allowed_on_different_domain_namespaces(): void
    {
        $user = $this->makeUser(['custom_domains' => true]);
        $domain = \App\Modules\User\Models\Domain::create([
            'user_id'     => $user->id,
            'domain'      => 'brand.qs-example.com',
            'type'        => 'custom',
            'is_verified' => true,
            'is_active'   => true,
        ]);

        $alias = 'qs-' . Str::lower(Str::random(8));
        $this->post_($user, ['destination' => 'https://example.com', 'alias' => $alias])->assertOk();

        // Same alias on the custom domain lives in a different namespace.
        $this->post_($user, ['destination' => 'https://example.org', 'alias' => $alias, 'domain_id' => $domain->id])
            ->assertOk();

        // But a duplicate within the SAME domain namespace is rejected.
        $this->post_($user, ['destination' => 'https://example.net', 'alias' => $alias, 'domain_id' => $domain->id])
            ->assertStatus(422)->assertJsonValidationErrors(['alias']);
    }

    public function test_unavailable_domain_is_rejected(): void
    {
        $user  = $this->makeUser(['custom_domains' => true]);
        $other = $this->makeUser(['custom_domains' => true]);
        $foreign = \App\Modules\User\Models\Domain::create([
            'user_id'     => $other->id,
            'domain'      => 'other.qs-example.com',
            'type'        => 'custom',
            'is_verified' => true,
            'is_active'   => true,
        ]);

        // Re-bind the workspace context to the first user (makeUser leaves
        // the LAST created user's context bound).
        $ws = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);

        $this->post_($user, ['destination' => 'https://example.com', 'domain_id' => $foreign->id])
            ->assertStatus(422)->assertJsonValidationErrors(['domain_id']);
    }

    public function test_domains_picker_endpoint_lists_available_domains(): void
    {
        $user = $this->makeUser(['custom_domains' => true]);
        $domain = \App\Modules\User\Models\Domain::create([
            'user_id'     => $user->id,
            'domain'      => 'pick.qs-example.com',
            'type'        => 'custom',
            'is_verified' => true,
            'is_active'   => true,
        ]);

        $res = $this->actingAs($user)->getJson(route('user.links.quick-shorten.domains'));
        $res->assertOk()->assertJsonStructure(['items', 'primary_domain_id', 'default_host']);
        $this->assertContains($domain->id, array_column($res->json('items'), 'id'));
    }

    public function test_plan_link_cap_returns_json_422(): void
    {
        $user = $this->makeUser(['max_links' => 1]);
        Link::create([
            'type' => 'url', 'long_url' => 'https://example.com',
            'alias' => 'qs-' . Str::lower(Str::random(8)), 'user_id' => $user->id,
        ]);

        $this->post_($user, ['destination' => 'https://example.net'])
            ->assertStatus(422)->assertJsonStructure(['error']);
    }
}
