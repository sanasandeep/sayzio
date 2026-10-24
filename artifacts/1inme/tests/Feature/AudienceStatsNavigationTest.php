<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Support\NavRailSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AudienceStatsNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_stats_uses_audience_section_and_shared_header(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $page = $this->actingAs($user)->get('/user/stats')->assertOk();
        $page->assertSee('Creator audience, posts, engagement and subscriptions.')->assertSee('Stats date filters')->assertDontSee('>Stats home</h1>', false);
        $this->assertSame('audience', NavRailSections::activeKey());
        $this->assertStringContainsString('class="page-hero mb-6"', $page->getContent());
        $this->assertStringNotContainsString('href="'.route('user.visitors.index').'" class="sidebar-link', $page->getContent());
        if (getenv('AUDIENCE_UI_FIXTURE')) file_put_contents('/tmp/sayzio-audience-stats.html', $page->getContent());
    }
}
