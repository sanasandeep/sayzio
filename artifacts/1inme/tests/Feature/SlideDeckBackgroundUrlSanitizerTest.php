<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkSlideDeck;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Route-level companion to BiolinkPageSettingsUrlSanitizerTest for the slides
 * save path: slide backgrounds (background.video_url / image_url / images[])
 * are persisted by POST /user/links/{link}/slides (SlideDeckController::save),
 * NOT the page-settings route, and render on the public slides page
 * (common/biolink-slides.blade.php) as <source src>, background-image:url()
 * and <img src>. Proves an unsafe value (javascript:, //evil.com, backslash
 * smuggling) submitted through the real save endpoint is blanked/dropped in
 * both the live slide rows and the published snapshot the public page reads,
 * while safe https and /f/... vault URLs round-trip unchanged.
 */
class SlideDeckBackgroundUrlSanitizerTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $features = []): Plan
    {
        $slug = 'p' . Str::random(6);
        return Plan::create([
            'name' => $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            'features' => $features,
        ]);
    }

    private function user(): User
    {
        $plan = $this->plan(['max_links' => 100, 'max_biolinks' => 5]);
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => $plan->id,
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    private function slidesLink(User $u): Link
    {
        return $u->links()->create([
            'user_id' => $u->id, 'type' => 'slides',
            'alias'   => 'sl' . substr(Str::random(8), 0, 8),
            'is_active' => true,
        ]);
    }

    private function saveDeck(User $u, Link $link, array $background, bool $publish = true)
    {
        return $this->actingAs($u)->postJson('/user/links/' . $link->id . '/slides', [
            'is_published' => $publish,
            'slides' => [
                ['title' => 'S1', 'background' => $background],
            ],
        ]);
    }

    /** The persisted background of the first (only) slide row. */
    private function slideBackground(Link $link): array
    {
        $deck = LinkSlideDeck::withoutGlobalScope('workspace')
            ->where('link_id', $link->id)->firstOrFail();
        return $deck->slides()->orderBy('sort_order')->firstOrFail()->background ?? [];
    }

    /** The background inside the published snapshot the public page renders. */
    private function snapshotBackground(Link $link): array
    {
        $deck = LinkSlideDeck::withoutGlobalScope('workspace')
            ->where('link_id', $link->id)->firstOrFail();
        return $deck->published_snapshot['slides'][0]['background'] ?? [];
    }

    public static function unsafeUrls(): array
    {
        return [
            'javascript scheme'      => ['javascript:alert(1)'],
            'protocol-relative host' => ['//evil.com/x.mp4'],
            'backslash smuggling'    => ['/f/\\evil'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeUrls')]
    public function test_unsafe_video_background_url_is_blanked(string $bad): void
    {
        $u = $this->user();
        $link = $this->slidesLink($u);

        $this->saveDeck($u, $link, [
            'type'      => 'video',
            'video_url' => $bad,
        ])->assertOk();

        $this->assertSame('', $this->slideBackground($link)['video_url'] ?? null,
            'unsafe video_url must be blanked — it renders as <source src> on the public slides page');
        $this->assertSame('', $this->snapshotBackground($link)['video_url'] ?? null,
            'unsafe video_url must not survive into the published snapshot');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeUrls')]
    public function test_unsafe_image_background_url_is_blanked(string $bad): void
    {
        $u = $this->user();
        $link = $this->slidesLink($u);

        $this->saveDeck($u, $link, [
            'type'      => 'image',
            'image_url' => $bad,
        ])->assertOk();

        $this->assertSame('', $this->slideBackground($link)['image_url'] ?? null,
            'unsafe image_url must be blanked — it renders inside background-image:url()');
        $this->assertSame('', $this->snapshotBackground($link)['image_url'] ?? null);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeUrls')]
    public function test_unsafe_slideshow_image_urls_are_dropped(string $bad): void
    {
        $u = $this->user();
        $link = $this->slidesLink($u);

        $this->saveDeck($u, $link, [
            'type'   => 'slideshow',
            'images' => [$bad, 'https://cdn.example.com/ok.jpg'],
        ])->assertOk();

        $imgs = $this->slideBackground($link)['images'] ?? [];
        $this->assertSame(['https://cdn.example.com/ok.jpg'], $imgs,
            'unsafe slideshow images must be dropped, safe ones kept');
        $this->assertSame(['https://cdn.example.com/ok.jpg'],
            $this->snapshotBackground($link)['images'] ?? []);
    }

    public function test_safe_background_urls_round_trip_unchanged(): void
    {
        $u = $this->user();
        $link = $this->slidesLink($u);

        $this->saveDeck($u, $link, [
            'type'      => 'video',
            'video_url' => 'https://cdn.example.com/bg.mp4',
            'image_url' => '/f/123/poster.jpg',
            'images'    => ['https://cdn.example.com/a.jpg', '/f/9/b.png'],
        ])->assertOk();

        $bg = $this->slideBackground($link);
        $this->assertSame('https://cdn.example.com/bg.mp4', $bg['video_url'] ?? null);
        $this->assertSame('/f/123/poster.jpg', $bg['image_url'] ?? null);
        $this->assertSame(['https://cdn.example.com/a.jpg', '/f/9/b.png'], $bg['images'] ?? null);

        // Non-URL knobs are untouched.
        $this->assertSame('video', $bg['type'] ?? null);
    }
}
