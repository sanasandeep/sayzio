<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkClick;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Text Page .txt download + raw plain-text view (Task #6323).
 */
class TextPageDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $plan = Plan::create([
            'name'     => 'TP Plan ' . Str::random(4),
            'slug'     => 'tp-' . Str::lower(Str::random(8)),
            'features' => ['max_links' => -1],
            'status'   => true,
        ]);

        $user = User::create([
            'name'     => 'TP User',
            'email'    => 'tp-' . Str::lower(Str::random(8)) . '@example.com',
            'password' => bcrypt('secret123'),
            'plan_id'  => $plan->id,
        ]);

        $ws = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);

        return $user;
    }

    private function makeTextLink(User $user, string $content, array $attrs = []): Link
    {
        $link = Link::create(array_merge([
            'user_id'  => $user->id,
            'alias'    => 'txt-' . Str::lower(Str::random(10)),
            'type'     => 'text',
            'title'    => 'Notes',
            'settings' => ['text' => ['content' => $content]],
        ], $attrs));

        return $link;
    }

    private function asVisitor(): void
    {
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');
    }

    public function test_download_txt_streams_content_as_attachment(): void
    {
        $user = $this->makeUser();
        $link = $this->makeTextLink($user, "hello world\nline two");
        $this->asVisitor();

        $res = $this->get("/{$link->alias}/download.txt");

        $res->assertOk();
        $this->assertStringStartsWith('text/plain', $res->headers->get('Content-Type'));
        $this->assertSame(
            'attachment; filename="' . $link->alias . '.txt"',
            $res->headers->get('Content-Disposition')
        );
        $this->assertSame("hello world\nline two", $res->getContent());
    }

    public function test_raw_view_serves_plain_text_inline(): void
    {
        $user = $this->makeUser();
        $link = $this->makeTextLink($user, 'raw body');
        $this->asVisitor();

        $res = $this->get("/{$link->alias}/raw");

        $res->assertOk();
        $this->assertStringStartsWith('text/plain', $res->headers->get('Content-Type'));
        $this->assertSame('inline', $res->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $res->headers->get('X-Content-Type-Options'));
        $this->assertSame('raw body', $res->getContent());
    }

    public function test_download_is_recorded_in_link_analytics(): void
    {
        $user = $this->makeUser();
        $link = $this->makeTextLink($user, 'tracked content');
        $this->asVisitor();

        $this->get("/{$link->alias}/download.txt")->assertOk();

        // The click buffer flushes on app termination; flush explicitly.
        app(\App\Modules\Common\Services\ClickWriteBuffer::class)->flush();

        $click = LinkClick::where('link_id', $link->id)->latest('clicked_at')->first();
        $this->assertNotNull($click, 'download should create a link_clicks row');
        $this->assertSame('txt_download', $click->source);
    }

    public function test_non_text_links_404_on_companion_urls(): void
    {
        $user = $this->makeUser();
        $link = Link::create([
            'user_id'  => $user->id,
            'alias'    => 'url-' . Str::lower(Str::random(10)),
            'type'     => 'url',
            'long_url' => 'https://example.com',
        ]);
        $this->asVisitor();

        $this->get("/{$link->alias}/download.txt")->assertNotFound();
        $this->get("/{$link->alias}/raw")->assertNotFound();
    }

    public function test_password_protected_text_link_blocks_download(): void
    {
        $user = $this->makeUser();
        $link = $this->makeTextLink($user, 'secret text', [
            'is_password_protected' => true,
            'password'              => bcrypt('pw'),
        ]);
        $this->asVisitor();

        $this->get("/{$link->alias}/download.txt")->assertForbidden();
        $this->get("/{$link->alias}/raw")->assertForbidden();
    }

    public function test_analytics_page_shows_download_and_raw_counts_for_text_links(): void
    {
        $user = $this->makeUser();
        $link = $this->makeTextLink($user, 'analytics body');
        $this->asVisitor();

        $this->get("/{$link->alias}/download.txt")->assertOk();
        $this->get("/{$link->alias}/download.txt")->assertOk();
        $this->get("/{$link->alias}/raw")->assertOk();
        app(\App\Modules\Common\Services\ClickWriteBuffer::class)->flush();

        // Re-bind the workspace context (asVisitor cleared it) before hitting
        // the owner-side analytics page.
        $ws = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);

        $res = $this->actingAs($user)->get(route('user.links.show', $link));
        $res->assertOk();
        $res->assertSee('Downloads');
        $res->assertSee('Raw fetches');

        $this->assertSame(2, (int) LinkClick::where('link_id', $link->id)->where('source', 'txt_download')->count());
        $this->assertSame(1, (int) LinkClick::where('link_id', $link->id)->where('source', 'txt_raw')->count());
    }

    public function test_analytics_download_counts_exclude_bot_hits(): void
    {
        $user = $this->makeUser();
        $link = $this->makeTextLink($user, 'bot body');
        $this->asVisitor();

        // A crawler downloading the .txt: recorded with is_bot=true, so the
        // default (bot-excluding) scope used by the analytics page must not
        // count it.
        $this->get("/{$link->alias}/download.txt", ['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])->assertOk();
        app(\App\Modules\Common\Services\ClickWriteBuffer::class)->flush();

        $this->assertSame(0, (int) LinkClick::where('link_id', $link->id)->where('source', 'txt_download')->count());
        $this->assertSame(1, (int) LinkClick::withBots()->where('link_id', $link->id)->where('source', 'txt_download')->count());
    }

    public function test_download_button_rendered_on_public_text_page(): void
    {
        $user = $this->makeUser();
        $link = $this->makeTextLink($user, 'page body');
        $this->asVisitor();

        $res = $this->get("/{$link->alias}");
        $res->assertOk();
        $res->assertSee("/{$link->alias}/download.txt");
        $res->assertSee('Download .txt');
    }
}
