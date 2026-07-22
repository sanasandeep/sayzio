<?php

namespace Tests\Feature;

use App\Modules\User\Models\ConversationFlow;
use App\Modules\User\Models\ConversationSession;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToCheckExistence;
use Tests\TestCase;

/**
 * Guards the cv-uploads:prune-abandoned command against the production
 * failure where an S3-backed public disk throws UnableToCheckExistence on
 * a directory-existence pre-check. The command must never bubble an
 * uncaught storage exception: listing failures are a graceful FAILURE with
 * a message, an empty prefix is a SUCCESS, and normal pruning keeps
 * referenced files, deletes old orphans, and skips recent files.
 */
class PruneAbandonedChatUploadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_prefix_is_success_without_existence_check(): void
    {
        Storage::fake('public');

        // Fake disk (no cv_uploads dir at all): must succeed, and must not
        // depend on a directory-existence probe (removed for S3 safety).
        $this->artisan('cv-uploads:prune-abandoned', ['--days' => 7])
            ->expectsOutputToContain('Pruned 0 orphaned upload(s)')
            ->assertExitCode(0);
    }

    public function test_listing_failure_is_graceful_failure_not_uncaught(): void
    {
        $disk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $disk->shouldReceive('allFiles')->with('cv_uploads')
            ->andThrow(UnableToCheckExistence::forLocation('cv_uploads'));
        // The command must never call exists() — an S3 directory check can
        // throw and previously crashed the job before any pruning.
        $disk->shouldNotReceive('exists');
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        $this->artisan('cv-uploads:prune-abandoned', ['--days' => 7])
            ->expectsOutputToContain('Failed to list cv_uploads files')
            ->assertExitCode(1);
    }

    public function test_prunes_old_orphans_keeps_referenced_and_recent(): void
    {
        $fake = Storage::fake('public');

        $fake->put('cv_uploads/2026/05/referenced.bin', 'r');
        $fake->put('cv_uploads/2026/05/orphan.bin', 'o');
        $fake->put('cv_uploads/2026/07/recent.bin', 'n');

        // Age the first two files past the 7-day window.
        $old = now()->subDays(30)->getTimestamp();
        touch($fake->path('cv_uploads/2026/05/referenced.bin'), $old);
        touch($fake->path('cv_uploads/2026/05/orphan.bin'), $old);

        $user = User::create([
            'name'     => 'Prune Tester',
            'email'    => 'prune-' . uniqid() . '@example.com',
            'password' => bcrypt('secret-pass'),
        ]);
        $link = Link::create([
            'user_id' => $user->id,
            'type'    => 'conversational',
            'alias'   => 'prune-' . uniqid(),
            'url'     => 'https://example.com',
        ]);
        $flow = ConversationFlow::create([
            'link_id' => $link->id,
            'name'    => 'Test flow',
        ]);

        ConversationSession::create([
            'flow_id'   => $flow->id,
            'link_id'   => $link->id,
            'public_id' => 'sess-' . uniqid(),
            'completed' => true,
            'answers'   => ['cv' => '/storage/cv_uploads/2026/05/referenced.bin'],
        ]);

        $this->artisan('cv-uploads:prune-abandoned', ['--days' => 7])
            ->assertExitCode(0);

        $this->assertTrue($fake->exists('cv_uploads/2026/05/referenced.bin'), 'referenced file must be kept');
        $this->assertFalse($fake->exists('cv_uploads/2026/05/orphan.bin'), 'old orphan must be deleted');
        $this->assertTrue($fake->exists('cv_uploads/2026/07/recent.bin'), 'recent file must be skipped');
    }
}
