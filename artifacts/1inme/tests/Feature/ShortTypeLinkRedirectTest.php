<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Links stored with type='short' (created by the smart-routing API path,
 * imports, or manual seeding) must redirect like a plain 'url' link instead
 * of falling into the RedirectController match default => abort(404)
 * (Task #6407).
 */
class ShortTypeLinkRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $plan = Plan::create([
            'name'     => 'ST Plan ' . Str::random(4),
            'slug'     => 'st-' . Str::lower(Str::random(8)),
            'features' => ['max_links' => -1],
            'status'   => true,
        ]);

        $user = User::create([
            'name'     => 'ST User',
            'email'    => 'st-' . Str::lower(Str::random(8)) . '@example.com',
            'password' => bcrypt('secret123'),
            'plan_id'  => $plan->id,
        ]);

        $ws = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);

        return $user;
    }

    private function makeLink(User $user, string $type, array $attrs = []): Link
    {
        return Link::create(array_merge([
            'user_id'   => $user->id,
            'alias'     => 'st-' . Str::lower(Str::random(10)),
            'type'      => $type,
            'long_url'  => 'https://example.com/destination',
            'is_active' => true,
        ], $attrs));
    }

    private function asVisitor(): void
    {
        // The public /{alias} catch-all runs without SetActiveWorkspace, so a
        // real visitor request carries no bound workspace context. Drop the
        // test-setup binding so Link's workspace global scope doesn't leak.
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');
    }

    public function test_short_type_link_redirects_like_url(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user, 'short');
        $this->asVisitor();

        $res = $this->get('/' . $link->alias);

        $res->assertRedirect('https://example.com/destination');
        $this->assertSame(301, $res->getStatusCode());
    }

    public function test_short_type_link_honors_redirect_type(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user, 'short', ['redirect_type' => 302]);
        $this->asVisitor();

        $res = $this->get('/' . $link->alias);

        $res->assertRedirect('https://example.com/destination');
        $this->assertSame(302, $res->getStatusCode());
    }

    public function test_url_type_link_still_redirects(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user, 'url');
        $this->asVisitor();

        $this->get('/' . $link->alias)
            ->assertRedirect('https://example.com/destination');
    }
}
