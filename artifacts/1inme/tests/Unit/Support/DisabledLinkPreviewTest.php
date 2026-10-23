<?php

namespace Tests\Unit\Support;

use App\Modules\Common\Support\OwnerPreview;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\TestCase;

class DisabledLinkPreviewTest extends TestCase
{
    private $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $this->app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('p', 32)));
        $this->app['config']->set('cache.default', 'array');
        $this->app['config']->set('session.driver', 'array');
    }

    protected function tearDown(): void
    {
        $this->app->flush();
        Facade::clearResolvedInstances();
        restore_error_handler();
        restore_exception_handler();
        parent::tearDown();
    }

    private function link(array $attributes = []): Link
    {
        return new Link(array_merge(['alias' => 'private-draft', 'type' => 'biolink', 'is_active' => false, 'settings' => [], 'total_clicks' => 0], $attributes));
    }

    private function signed(int $minutes = 10): Request
    {
        return Request::create(URL::temporarySignedRoute('redirect.handle', now()->addMinutes($minutes), ['alias' => 'private-draft', '_preview' => 1], false));
    }

    public function test_explicit_status_is_idempotent_and_checks_workspace_ownership(): void
    {
        $owner = new \App\Modules\User\Models\User;
        $owner->id = 7;
        $this->app->instance('workspace_owner', $owner);
        $link = new class extends Link {
            public function update(array $attributes = [], array $options = []) { $this->fill($attributes); return true; }
        };
        $link->user_id = 7;
        $link->is_active = true;
        $controller = new \App\Modules\User\Controllers\LinkController;
        $controller->toggleActive(Request::create('/', 'POST', ['is_active' => '0']), $link);
        $controller->toggleActive(Request::create('/', 'POST', ['is_active' => '0']), $link);
        $this->assertFalse($link->is_active);
        $controller->toggleActive(Request::create('/', 'POST', ['is_active' => '1']), $link);
        $this->assertTrue($link->is_active);
        $link->user_id = 8;
        try {
            $controller->toggleActive(Request::create('/', 'POST', ['is_active' => '0']), $link);
            $this->fail('Another workspace link must be rejected.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertTrue($link->is_active);
        }
    }

    public function test_status_control_renders_both_states_and_hides_from_read_only_staff(): void
    {
        $actor = new class extends \App\Modules\User\Models\User {
            public bool $editable = true;
            public function canInWorkspace(\App\Modules\User\Models\Workspace $workspace, string $permission): bool { return $this->editable && $permission === 'links.edit'; }
        };
        $this->app['auth']->guard()->setUser($actor);
        $this->app->instance('current_workspace', new \App\Modules\User\Models\Workspace);
        $link = $this->link(['is_active' => true]);
        $link->id = 42;
        $enabled = view('user.links.partials.status-control', compact('link'))->render();
        $this->assertStringContainsString('Disable link', $enabled);
        $this->assertStringContainsString('name="is_active" value="0"', $enabled);
        $link->is_active = false;
        $disabled = view('user.links.partials.status-control', compact('link'))->render();
        $this->assertStringContainsString('Enable link', $disabled);
        $this->assertStringContainsString('name="is_active" value="1"', $disabled);
        $actor->editable = false;
        $this->assertStringNotContainsString('<form', view('user.links.partials.status-control', compact('link'))->render());
    }

    public function test_disabled_link_remains_private_without_a_valid_preview_signature(): void
    {
        $link = $this->link();
        $this->assertFalse(OwnerPreview::canAccess($link, Request::create('/private-draft')));
        $this->assertFalse(OwnerPreview::canAccess($link, Request::create('/private-draft?_preview=1')));
        $this->assertFalse(OwnerPreview::canAccess($link, $this->signed(-1)));
        $url = $this->signed()->getRequestUri();
        $this->assertFalse(OwnerPreview::canAccess($link, Request::create(str_replace('private-draft', 'another-link', $url))));
    }

    public function test_signed_preview_renders_without_changing_publication_state(): void
    {
        $link = $this->link();
        $this->assertTrue(OwnerPreview::canAccess($link, $this->signed()));
        $this->assertFalse($link->is_active);
        $this->assertFalse($link->isAccessible());
        $this->assertTrue(OwnerPreview::canAccess($link, Request::create($this->signed()->getRequestUri().'&_draft=1&_t=123')));
    }

    public function test_preview_does_not_bypass_expiry_schedules_or_usage_limits(): void
    {
        foreach ([['expires_at' => now()->subDay()], ['settings' => ['start_at' => now()->addDay()->toIso8601String()]], ['settings' => ['max_clicks' => 1], 'total_clicks' => 1]] as $attributes) {
            $this->assertFalse(OwnerPreview::canAccess($this->link($attributes), $this->signed()));
        }
        $this->assertTrue(OwnerPreview::canAccess($this->link(['is_active' => true]), Request::create('/private-draft')));
    }
}
