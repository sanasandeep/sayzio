<?php

namespace Tests\Unit\Services;

use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\OpenAiService;
use App\Services\Billing\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Vision path of {@see OpenAiService} (Task #6654):
 *
 *  - Multimodal (image_url) messages ride the existing prepay/charge flow
 *    with a FLAT per-image token estimate — never json_encode of the
 *    base64 payload (that would explode the worst-case prepay to
 *    hundreds of thousands of credits and wall off every user).
 *  - guardVision() rejects image parts sent to a model whose entry lacks
 *    vision support, before any HTTP or charging happens.
 */
class OpenAiServiceVisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setOpenAiKey('sk-test-key');
        AiEngineSettings::setModels([
            ['name' => 'gpt-vision', 'kind' => 'chat', 'enabled' => true,
             'in_coins_per_1k' => 1000, 'out_coins_per_1k' => 1000,
             'supports_vision' => true],
            ['name' => 'text-only', 'kind' => 'chat', 'enabled' => true,
             'in_coins_per_1k' => 1000, 'out_coins_per_1k' => 1000,
             'supports_vision' => false],
        ]);
    }

    private function multimodalMessages(): array
    {
        // ~120KB of base64 — big enough that a json_encode-based estimate
        // would dwarf the flat per-image allowance.
        $img = 'data:image/jpeg;base64,'.base64_encode(str_repeat('x', 90_000));
        return [
            ['role' => 'system', 'content' => 'You are Zio.'],
            ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => 'What does this image show?'],
                ['type' => 'image_url', 'image_url' => ['url' => $img, 'detail' => 'auto']],
            ]],
        ];
    }

    public function test_image_prepay_uses_flat_estimate_not_base64_length(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        // Enough for the flat image estimate (~1100 tokens ≈ 1100 credits at
        // our 1000/1k test rate + text + output allowance), but NOWHERE near
        // enough if the base64 payload were counted as prompt chars
        // (90KB/4 ≈ 22.5k tokens ≈ 22,500+ credits).
        app(WalletService::class)->credit($user, 8_000, ['reason' => 'test seed']);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'A bridge at sunset.']]],
                'usage'   => ['prompt_tokens' => 1200, 'completion_tokens' => 20, 'total_tokens' => 1220],
            ]),
        ]);

        $result = app(OpenAiService::class)->chat(
            $user, 'gpt-vision', $this->multimodalMessages(), ['feature' => 'site_assistant']
        );

        $this->assertSame('A bridge at sunset.', $result['content']);
        $this->assertGreaterThan(0, $result['credits_spent']);

        // The request body carried the image part untouched.
        Http::assertSent(function ($request) {
            $content = $request['messages'][1]['content'] ?? null;
            return is_array($content) && ($content[1]['type'] ?? null) === 'image_url';
        });
    }

    public function test_guard_vision_rejects_images_on_non_vision_model(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        app(WalletService::class)->credit($user, 8_000, ['reason' => 'test seed']);
        Http::fake();

        $this->expectException(\RuntimeException::class);
        try {
            app(OpenAiService::class)->chat(
                $user, 'text-only', $this->multimodalMessages(), ['feature' => 'site_assistant']
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_vision_chat_model_selection(): void
    {
        // Preferred model wins when it supports vision…
        $this->assertSame('gpt-vision', AiEngineSettings::visionChatModel('gpt-vision'));
        // …a non-vision preference falls back to the first vision model…
        $this->assertSame('gpt-vision', AiEngineSettings::visionChatModel('text-only'));
        $this->assertTrue(AiEngineSettings::modelSupportsVision('gpt-vision'));
        $this->assertFalse(AiEngineSettings::modelSupportsVision('text-only'));

        // …and with no vision-capable rows at all, selection returns null.
        AiEngineSettings::setModels([
            ['name' => 'text-only', 'kind' => 'chat', 'enabled' => true,
             'in_coins_per_1k' => 1000, 'out_coins_per_1k' => 1000,
             'supports_vision' => false],
        ]);
        $this->assertNull(AiEngineSettings::visionChatModel('text-only'));
    }
}
