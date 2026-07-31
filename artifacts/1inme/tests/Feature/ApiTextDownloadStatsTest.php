<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkClick;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mobile-parity: GET /api/v1/links/{id}/analytics exposes txt_downloads /
 * txt_raw counts for text-type links (same bot exclusions as the web
 * analytics page — the default LinkClick global scope).
 */
class ApiTextDownloadStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(): User
    {
        $plan = Plan::create([
            'name'     => 'TD Plan ' . Str::random(4),
            'slug'     => 'td-' . Str::lower(Str::random(8)),
            'status'   => true,
            'features' => [],
        ]);

        return User::factory()->create(['plan_id' => $plan->id]);
    }

    protected function makeLink(User $user, string $type): Link
    {
        return Link::create([
            'user_id'  => $user->id,
            'type'     => $type,
            'alias'    => 'tds-' . Str::lower(Str::random(10)),
            'settings' => $type === 'text' ? ['text' => ['content' => 'hi']] : [],
        ]);
    }

    protected function click(Link $link, string $source, bool $bot = false): void
    {
        LinkClick::withoutEvents(function () use ($link, $source, $bot) {
            LinkClick::create([
                'link_id'    => $link->id,
                'alias'      => $link->alias,
                'source'     => $source,
                'is_bot'     => $bot,
                'clicked_at' => now()->subDay(),
            ]);
        });
    }

    public function test_text_link_analytics_includes_txt_counts_excluding_bots(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user, 'text');

        $this->click($link, 'txt_download');
        $this->click($link, 'txt_download');
        $this->click($link, 'txt_raw');
        $this->click($link, 'txt_download', bot: true);

        $token = $user->createToken('t')->plainTextToken;
        $res = $this->withToken($token)->getJson("/api/v1/links/{$link->id}/analytics");

        $res->assertOk();
        $this->assertSame('text', $res->json('data.analytics.link_type'));
        $this->assertSame(2, $res->json('data.analytics.txt_downloads'));
        $this->assertSame(1, $res->json('data.analytics.txt_raw'));
    }

    public function test_non_text_link_reports_zero_txt_counts(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user, 'biolink');

        $token = $user->createToken('t')->plainTextToken;
        $res = $this->withToken($token)->getJson("/api/v1/links/{$link->id}/analytics");

        $res->assertOk();
        $this->assertSame('biolink', $res->json('data.analytics.link_type'));
        $this->assertSame(0, $res->json('data.analytics.txt_downloads'));
        $this->assertSame(0, $res->json('data.analytics.txt_raw'));
    }
}
