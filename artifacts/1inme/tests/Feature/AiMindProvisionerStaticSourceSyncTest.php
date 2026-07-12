<?php

namespace Tests\Feature;

use App\Jobs\IngestAiMindSourceJob;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiMindSource;
use App\Services\AI\AiMindProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Verifies the platform default Mind keeps its two code-defined static text
 * sources (About + FAQ) in sync with the code constants: created on first
 * provision, refreshed on drift, and a true no-op when unchanged.
 */
class AiMindProvisionerStaticSourceSyncTest extends TestCase
{
    use RefreshDatabase;

    private function aboutSource(AiMind $mind): ?AiMindSource
    {
        return AiMindSource::where('mind_id', $mind->id)
            ->where('type', AiMindSource::TYPE_TEXT)
            ->where('title', 'About Sayzio')
            ->first();
    }

    private function faqSource(AiMind $mind): ?AiMindSource
    {
        return AiMindSource::where('mind_id', $mind->id)
            ->where('type', AiMindSource::TYPE_FAQ)
            ->where('title', 'Common questions')
            ->first();
    }

    public function test_first_provision_creates_tagged_static_sources_and_queues_ingestion(): void
    {
        Bus::fake();

        $mind = AiMindProvisioner::ensurePlatformDefault();

        $about = $this->aboutSource($mind);
        $faq   = $this->faqSource($mind);

        $this->assertNotNull($about);
        $this->assertNotNull($faq);
        $this->assertSame('about', $about->meta['managed_key'] ?? null);
        $this->assertSame('faq', $faq->meta['managed_key'] ?? null);
        $this->assertSame(AiMindSource::STATUS_QUEUED, $about->status);
        $this->assertStringContainsString('Sayzio is a creator platform', (string) $about->body);

        Bus::assertDispatched(IngestAiMindSourceJob::class,
            fn (IngestAiMindSourceJob $j) => $j->sourceId === $about->id);
        Bus::assertDispatched(IngestAiMindSourceJob::class,
            fn (IngestAiMindSourceJob $j) => $j->sourceId === $faq->id);
    }

    public function test_second_provision_is_a_no_op_when_body_unchanged(): void
    {
        Bus::fake();
        $mind = AiMindProvisioner::ensurePlatformDefault();

        // Fresh fake — the second provision, with no code drift and every
        // feature/static source already present, must dispatch nothing.
        Bus::fake();
        AiMindProvisioner::ensurePlatformDefault();

        Bus::assertNotDispatched(IngestAiMindSourceJob::class);
    }

    public function test_drift_in_stored_body_is_refreshed_and_reingested(): void
    {
        Bus::fake();
        $mind = AiMindProvisioner::ensurePlatformDefault();

        // Simulate an install whose stored copy is stale (older product docs)
        // and already embedded (READY) so nothing but drift can re-queue it.
        $about = $this->aboutSource($mind);
        $about->forceFill([
            'body'   => 'Outdated overview from an earlier release.',
            'status' => AiMindSource::STATUS_READY,
        ])->save();

        Bus::fake();
        AiMindProvisioner::ensurePlatformDefault();

        $about->refresh();
        $this->assertStringContainsString('Sayzio is a creator platform', (string) $about->body);
        $this->assertSame(AiMindSource::STATUS_QUEUED, $about->status);

        Bus::assertDispatched(IngestAiMindSourceJob::class,
            fn (IngestAiMindSourceJob $j) => $j->sourceId === $about->id);
        // The untouched FAQ source must NOT be re-queued.
        $faq = $this->faqSource($mind);
        Bus::assertNotDispatched(IngestAiMindSourceJob::class,
            fn (IngestAiMindSourceJob $j) => $j->sourceId === $faq->id);
    }

    public function test_legacy_untagged_source_is_adopted_without_needless_reingest(): void
    {
        // A Mind seeded by an older build: the About source has no
        // managed_key tag but its body already matches current code.
        $mind = AiMind::create([
            'user_id'    => null,
            'name'       => AiMindProvisioner::PLATFORM_NAME,
            'is_default' => true,
        ]);
        $legacy = AiMindSource::create([
            'mind_id' => $mind->id,
            'type'    => AiMindSource::TYPE_TEXT,
            'title'   => 'About Sayzio',
            'body'    => $this->currentAboutBody(),
            'status'  => AiMindSource::STATUS_READY,
        ]);

        Bus::fake();
        AiMindProvisioner::ensurePlatformDefault();

        // No duplicate created — exactly one About source, now tagged.
        $this->assertSame(1, AiMindSource::where('mind_id', $mind->id)
            ->where('type', AiMindSource::TYPE_TEXT)
            ->where('title', 'About Sayzio')->count());

        $legacy->refresh();
        $this->assertSame('about', $legacy->meta['managed_key'] ?? null);
        $this->assertSame(AiMindSource::STATUS_READY, $legacy->status);

        // Body already matched, so adopting it must not trigger a re-embed.
        Bus::assertNotDispatched(IngestAiMindSourceJob::class,
            fn (IngestAiMindSourceJob $j) => $j->sourceId === $legacy->id);
    }

    /** The current code About body, read the same way the provisioner writes it. */
    private function currentAboutBody(): string
    {
        $ref = new \ReflectionMethod(AiMindProvisioner::class, 'aboutText');
        $ref->setAccessible(true);
        return (string) $ref->invoke(null);
    }
}
