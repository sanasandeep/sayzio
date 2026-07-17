<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Coverage for the storage:canonicalize-legacy-paths backfill command.
 *
 * Invariants:
 *   1. Legacy `/storage/<path>` values are rewritten to the canonical CDN URL
 *      built via Storage::disk('public')->url() when the disk is S3-backed.
 *   2. Already-canonical values (absolute URLs, bare relative paths, null,
 *      gravatar URLs) are never touched — re-running is a no-op (idempotent).
 *   3. --dry-run reports but writes nothing.
 *   4. --only restricts the run to the named tables.
 *   5. --relative strips the prefix instead of building a CDN URL and works
 *      without an S3 disk.
 *   6. Without --relative and without an S3-backed public disk, the command
 *      refuses to run (exit failure) and changes nothing.
 */
class CanonicalizeLegacyStoragePathsTest extends TestCase
{
    use RefreshDatabase;

    private const CDN = 'https://cdn.example.com';

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    /** Point the public disk at a fake S3 config whose url() we control. */
    private function fakeS3(): void
    {
        config([
            'filesystems.disks.public.driver' => 's3',
            'filesystems.disks.public.url'    => self::CDN,
            'filesystems.disks.public.key'    => 'test',
            'filesystems.disks.public.secret' => 'test',
            'filesystems.disks.public.region' => 'us-east-1',
            'filesystems.disks.public.bucket' => 'test-bucket',
        ]);
    }

    public function test_rewrites_legacy_values_to_cdn_urls_and_is_idempotent(): void
    {
        $this->fakeS3();

        $user = $this->makeUser([
            'avatar'      => '/storage/avatars/a.jpg',
            'cover_image' => '/storage/profile-covers/c.jpg',
        ]);
        $untouched = $this->makeUser([
            'avatar' => 'https://www.gravatar.com/avatar/abc',
        ]);
        $link = Link::create([
            'user_id'       => $user->id,
            'type'          => 'short',
            'alias'         => 'canon-' . uniqid(),
            'long_url'      => 'https://example.com',
            'verified_logo' => '/storage/verification-logos/v.png',
        ]);

        $this->artisan('storage:canonicalize-legacy-paths')->assertSuccessful();

        $this->assertSame(self::CDN . '/avatars/a.jpg', $user->fresh()->avatar);
        $this->assertSame(self::CDN . '/profile-covers/c.jpg', $user->fresh()->cover_image);
        $this->assertSame(self::CDN . '/verification-logos/v.png', $link->fresh()->verified_logo);
        $this->assertSame('https://www.gravatar.com/avatar/abc', $untouched->fresh()->avatar);

        // Second run: nothing left to rewrite.
        $this->artisan('storage:canonicalize-legacy-paths')
            ->expectsOutputToContain('updated=0')
            ->assertSuccessful();
        $this->assertSame(self::CDN . '/avatars/a.jpg', $user->fresh()->avatar);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->fakeS3();
        $user = $this->makeUser(['avatar' => '/storage/avatars/dry.jpg']);

        $this->artisan('storage:canonicalize-legacy-paths --dry-run')
            ->expectsOutputToContain('would update=1')
            ->assertSuccessful();

        $this->assertSame('/storage/avatars/dry.jpg', $user->fresh()->avatar);
    }

    public function test_only_filter_restricts_tables(): void
    {
        $this->fakeS3();
        $user = $this->makeUser(['avatar' => '/storage/avatars/only.jpg']);
        DB::table('blog_posts')->insert([
            'title'       => 'Only test',
            'slug'        => 'only-test-' . uniqid(),
            'cover_image' => '/storage/blogs/cover.jpg',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->artisan('storage:canonicalize-legacy-paths --only=blog_posts')->assertSuccessful();

        $this->assertSame('/storage/avatars/only.jpg', $user->fresh()->avatar);
        $this->assertSame(
            self::CDN . '/blogs/cover.jpg',
            DB::table('blog_posts')->where('slug', 'like', 'only-test-%')->value('cover_image')
        );
    }

    public function test_relative_mode_strips_prefix_without_s3(): void
    {
        config(['filesystems.disks.public.driver' => 'local']);
        $user = $this->makeUser(['avatar' => '/storage/avatars/rel.jpg']);

        $this->artisan('storage:canonicalize-legacy-paths --relative --only=users')
            ->assertSuccessful();

        $this->assertSame('avatars/rel.jpg', $user->fresh()->avatar);
    }

    public function test_refuses_cdn_mode_on_local_disk(): void
    {
        config(['filesystems.disks.public.driver' => 'local']);
        $user = $this->makeUser(['avatar' => '/storage/avatars/refuse.jpg']);

        $this->artisan('storage:canonicalize-legacy-paths')->assertFailed();

        $this->assertSame('/storage/avatars/refuse.jpg', $user->fresh()->avatar);
    }

    public function test_rejects_unknown_only_table(): void
    {
        $this->fakeS3();
        $this->artisan('storage:canonicalize-legacy-paths --only=nope')->assertFailed();
    }

    // ── JSON columns ─────────────────────────────────────────────────

    public function test_rewrites_organizer_logo_inside_json_and_is_idempotent(): void
    {
        $this->fakeS3();

        $user = $this->makeUser([
            'organizer_profile' => [
                'name'    => 'Acme Events',
                'logo'    => '/storage/organizer-logos/logo.png',
                'website' => 'https://acme.test',
            ],
        ]);
        $untouched = $this->makeUser([
            'organizer_profile' => [
                'name' => 'No Legacy',
                'logo' => 'https://cdn.other.com/logo.png',
            ],
        ]);

        $this->artisan('storage:canonicalize-legacy-paths --only=users')->assertSuccessful();

        $profile = $user->fresh()->organizer_profile;
        $this->assertSame(self::CDN . '/organizer-logos/logo.png', $profile['logo']);
        // Sibling keys survive untouched.
        $this->assertSame('Acme Events', $profile['name']);
        $this->assertSame('https://acme.test', $profile['website']);
        // Already-canonical values stay put.
        $this->assertSame('https://cdn.other.com/logo.png', $untouched->fresh()->organizer_profile['logo']);

        // Second run: nothing left to rewrite.
        $this->artisan('storage:canonicalize-legacy-paths --only=users')
            ->expectsOutputToContain('updated=0')
            ->assertSuccessful();
        $this->assertSame(self::CDN . '/organizer-logos/logo.png', $user->fresh()->organizer_profile['logo']);
    }

    public function test_rewrites_site_page_extra_image_urls(): void
    {
        $this->fakeS3();

        DB::table('site_pages')->insert([
            'slug'       => 'about-json-' . uniqid(),
            'title'      => 'About',
            'extra'      => json_encode([
                'hero' => [
                    'badge_label' => 'Hi',
                    'side_image'  => '/storage/blogs/hero.jpg',
                ],
                'story_images' => [
                    'office'    => ['url' => '/storage/blogs/office.jpg', 'alt' => 'Office'],
                    'values'    => ['url' => 'https://example.com/values.jpg', 'alt' => 'Values'],
                    'team_band' => ['url' => '/storage/blogs/team.jpg', 'alt' => 'Team'],
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('storage:canonicalize-legacy-paths --only=site_pages')->assertSuccessful();

        $extra = json_decode(
            DB::table('site_pages')->where('slug', 'like', 'about-json-%')->value('extra'),
            true
        );
        $this->assertSame(self::CDN . '/blogs/hero.jpg', $extra['hero']['side_image']);
        $this->assertSame(self::CDN . '/blogs/office.jpg', $extra['story_images']['office']['url']);
        $this->assertSame(self::CDN . '/blogs/team.jpg', $extra['story_images']['team_band']['url']);
        // Absolute URL and sibling keys stay untouched.
        $this->assertSame('https://example.com/values.jpg', $extra['story_images']['values']['url']);
        $this->assertSame('Hi', $extra['hero']['badge_label']);
        $this->assertSame('Office', $extra['story_images']['office']['alt']);
    }

    public function test_json_dry_run_writes_nothing(): void
    {
        $this->fakeS3();
        $user = $this->makeUser([
            'organizer_profile' => ['logo' => '/storage/organizer-logos/dry.png'],
        ]);

        $this->artisan('storage:canonicalize-legacy-paths --dry-run --only=users')
            ->expectsOutputToContain('would update=1')
            ->assertSuccessful();

        $this->assertSame('/storage/organizer-logos/dry.png', $user->fresh()->organizer_profile['logo']);
    }

    public function test_json_relative_mode_strips_prefix(): void
    {
        config(['filesystems.disks.public.driver' => 'local']);
        $user = $this->makeUser([
            'organizer_profile' => ['logo' => '/storage/organizer-logos/rel.png'],
        ]);

        $this->artisan('storage:canonicalize-legacy-paths --relative --only=users')
            ->assertSuccessful();

        $this->assertSame('organizer-logos/rel.png', $user->fresh()->organizer_profile['logo']);
    }

    public function test_json_untouched_rows_and_malformed_paths_are_skipped(): void
    {
        $this->fakeS3();

        // Row with the substring in a non-configured key must not change.
        $user = $this->makeUser([
            'organizer_profile' => [
                'description' => 'See /storage/organizer-logos/in-text.png inline',
            ],
        ]);
        // Degenerate "/storage/" value stays as-is.
        $degenerate = $this->makeUser([
            'organizer_profile' => ['logo' => '/storage/'],
        ]);

        $this->artisan('storage:canonicalize-legacy-paths --only=users')->assertSuccessful();

        $this->assertSame(
            'See /storage/organizer-logos/in-text.png inline',
            $user->fresh()->organizer_profile['description']
        );
        $this->assertSame('/storage/', $degenerate->fresh()->organizer_profile['logo']);
    }
}
