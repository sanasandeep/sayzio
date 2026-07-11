<?php

namespace Tests\Feature\WhatsApp;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\CardScan;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Services\AI\CardBrochureExtractionService;
use App\Services\WhatsApp\WhatsAppAgentTools;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the WhatsApp agent scan_card tool.
 *
 * Tests run against WhatsAppAgentTools in isolation — the LLM loop and
 * WhatsApp Cloud API are not involved. CardBrochureExtractionService is
 * mocked so no real OpenAI call is made.
 */
class WhatsAppCardScanTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $planFeatures = []): User
    {
        if ($planFeatures) {
            $plan = Plan::create([
                'name'     => 'Test ' . Str::random(4),
                'slug'     => 'test-' . Str::lower(Str::random(8)),
                'status'   => true,
                'features' => $planFeatures,
            ]);
            return User::factory()->create(['plan_id' => $plan->id]);
        }
        return User::factory()->create();
    }

    private function makeTools(User $user, array $pending = []): WhatsAppAgentTools
    {
        return new WhatsAppAgentTools($user, $pending);
    }

    private function makeImageFile(User $user): UserFile
    {
        return UserFile::create([
            'user_id'       => $user->id,
            'original_name' => 'card.jpg',
            'filename'      => 'card-' . Str::random(8) . '.jpg',
            'mime_type'     => 'image/jpeg',
            'size_bytes'    => 12345,
            'type'          => 'image',
            'disk'          => 'public',
            'path'          => 'test/card.jpg',
        ]);
    }

    private function makePendingItem(UserFile $file): array
    {
        return [
            'kind'         => 'image',
            'user_file_id' => $file->id,
            'url'          => 'https://example.com/card.jpg',
            'name'         => $file->original_name,
        ];
    }

    private function completedScan(User $user, array $fileIds, array $extracted = []): CardScan
    {
        return CardScan::create([
            'user_id'         => $user->id,
            'actor_user_id'   => $user->id,
            'source_file_id'  => $fileIds[0] ?? null,
            'source_file_ids' => $fileIds,
            'status'          => 'completed',
            'idempotency_key' => 'card_scan:' . Str::random(16),
            'extracted'       => array_merge([
                'kind'       => 'card',
                'full_name'  => 'Jane Smith',
                'first_name' => 'Jane',
                'last_name'  => 'Smith',
                'title'      => 'CEO',
                'company'    => 'Acme Corp',
                'emails'     => [['value' => 'jane@acme.com', 'label' => 'Work']],
                'phones'     => [['value' => '+1-555-0100', 'label' => 'Mobile']],
                'website'    => 'https://acme.com',
                'address'    => null,
                'socials'    => [
                    'linkedin'  => 'janesmith',
                    'instagram' => null,
                    'tiktok'    => null,
                    'twitter'   => null,
                    'youtube'   => null,
                    'facebook'  => null,
                ],
                'branding'   => ['primary_color_hex' => null, 'secondary_color_hex' => null, 'has_logo' => false, 'logo_bbox' => null],
                'logo_url'   => null,
                'products'   => [],
                'confidence' => ['overall' => 0.95, 'name' => 0.98, 'email' => 0.95, 'phone' => 0.90, 'company' => 0.97],
            ], $extracted),
        ]);
    }

    public function test_scan_card_returns_extracted_details(): void
    {
        $user = $this->makeUser();
        $file = $this->makeImageFile($user);
        $scan = $this->completedScan($user, [$file->id]);

        $this->instance(CardBrochureExtractionService::class, \Mockery::mock(CardBrochureExtractionService::class, function ($m) use ($scan) {
            $m->shouldReceive('extractFromVaultedFiles')->once()->andReturn($scan);
        }));

        $tools  = $this->makeTools($user, [$this->makePendingItem($file)]);
        $result = $tools->run('scan_card', []);

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('Jane Smith', $result['summary']);
        $this->assertStringContainsString('CEO', $result['summary']);
        $this->assertStringContainsString('Acme Corp', $result['summary']);
        $this->assertStringContainsString('jane@acme.com', $result['summary']);
        $this->assertStringContainsString('+1-555-0100', $result['summary']);
        $this->assertStringContainsString('https://acme.com', $result['summary']);
        $this->assertStringContainsString('janesmith', $result['summary']);
    }

    public function test_front_and_back_both_consumed_in_one_scan(): void
    {
        $user  = $this->makeUser();
        $front = $this->makeImageFile($user);
        $back  = $this->makeImageFile($user);
        $scan  = $this->completedScan($user, [$front->id, $back->id], [
            'full_name' => 'Bob Lee', 'first_name' => 'Bob', 'last_name' => 'Lee',
        ]);

        $capturedFiles = null;
        $this->instance(CardBrochureExtractionService::class, \Mockery::mock(CardBrochureExtractionService::class, function ($m) use ($scan, &$capturedFiles) {
            $m->shouldReceive('extractFromVaultedFiles')
                ->once()
                ->withArgs(function ($owner, $actor, $files) use (&$capturedFiles) {
                    $capturedFiles = $files;
                    return true;
                })
                ->andReturn($scan);
        }));

        $tools = $this->makeTools($user, [
            $this->makePendingItem($front),
            $this->makePendingItem($back),
        ]);
        $result = $tools->run('scan_card', []);

        $this->assertTrue($result['ok']);
        $this->assertCount(2, $capturedFiles);
        $this->assertStringContainsString('Bob Lee', $result['summary']);
    }

    public function test_save_as_contact_creates_contact_record(): void
    {
        $user = $this->makeUser();
        $file = $this->makeImageFile($user);
        $scan = $this->completedScan($user, [$file->id]);

        $this->instance(CardBrochureExtractionService::class, \Mockery::mock(CardBrochureExtractionService::class, function ($m) use ($scan) {
            $m->shouldReceive('extractFromVaultedFiles')->once()->andReturn($scan);
        }));

        $tools  = $this->makeTools($user, [$this->makePendingItem($file)]);
        $result = $tools->run('scan_card', ['save_as_contact' => true]);

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('Saved as a contact', $result['summary']);

        $this->assertDatabaseHas('contacts', [
            'user_id'      => $user->id,
            'display_name' => 'Jane Smith',
            'organization' => 'Acme Corp',
            'job_title'    => 'CEO',
        ]);
        $this->assertDatabaseHas('contact_phones', ['value' => '+1-555-0100']);
        $this->assertDatabaseHas('contact_emails', ['value' => 'jane@acme.com']);
    }

    public function test_no_image_in_pending_returns_friendly_error(): void
    {
        $user   = $this->makeUser();
        $tools  = $this->makeTools($user, []);
        $result = $tools->run('scan_card', []);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsStringIgnoringCase('photo', $result['summary']);
    }

    public function test_plan_gate_blocks_user_without_card_scan(): void
    {
        $user = $this->makeUser(['card_scan' => false]);
        $file = $this->makeImageFile($user);

        $tools  = $this->makeTools($user, [$this->makePendingItem($file)]);
        $result = $tools->run('scan_card', []);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsStringIgnoringCase('plan', $result['summary']);
    }

    public function test_extraction_failure_returns_error_summary(): void
    {
        $user = $this->makeUser();
        $file = $this->makeImageFile($user);

        $this->instance(CardBrochureExtractionService::class, \Mockery::mock(CardBrochureExtractionService::class, function ($m) {
            $m->shouldReceive('extractFromVaultedFiles')
                ->once()
                ->andThrow(new \RuntimeException('Vision API timed out'));
        }));

        $tools  = $this->makeTools($user, [$this->makePendingItem($file)]);
        $result = $tools->run('scan_card', []);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Vision API timed out', $result['summary']);
    }

    public function test_non_completed_scan_returns_error(): void
    {
        $user = $this->makeUser();
        $file = $this->makeImageFile($user);

        $failedScan = CardScan::create([
            'user_id'         => $user->id,
            'actor_user_id'   => $user->id,
            'source_file_id'  => $file->id,
            'source_file_ids' => [$file->id],
            'status'          => 'failed',
            'idempotency_key' => 'card_scan:' . Str::random(16),
            'error'           => 'AI model returned no data',
        ]);

        $this->instance(CardBrochureExtractionService::class, \Mockery::mock(CardBrochureExtractionService::class, function ($m) use ($failedScan) {
            $m->shouldReceive('extractFromVaultedFiles')->once()->andReturn($failedScan);
        }));

        $tools  = $this->makeTools($user, [$this->makePendingItem($file)]);
        $result = $tools->run('scan_card', []);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsStringIgnoringCase('complete', $result['summary']);
    }
}
