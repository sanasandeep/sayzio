<?php

namespace Tests\Feature;

use App\Modules\Api\Controllers\VoiceAssistantController;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\Voice\VoiceAssistantService;
use App\Services\AI\Voice\VoiceToolRegistry;
use App\Services\AI\WhisperService;
use Tests\TestCase;

/**
 * Coverage for the wake-phrase matcher used by the mobile foreground
 * "Hey Sayzio" listener. The matcher must:
 *
 *   1. Accept the spelled-out and digit forms of the brand name and
 *      tolerate Whisper's punctuation, spacing, and casing variants.
 *   2. Reject unrelated chatter so we don't pop the voice sheet open
 *      every time the user says "hey" to someone in the room.
 *
 * Hitting the endpoint directly would require mocking Whisper; the
 * matcher is the only piece of new logic and is worth pinning on its
 * own so future tweaks to the phrase list don't silently regress
 * either side.
 */
class VoiceAssistantWakeCheckTest extends TestCase
{
    private function controller(): VoiceAssistantController
    {
        return new VoiceAssistantController(
            $this->createMock(VoiceAssistantService::class),
            $this->createMock(VoiceToolRegistry::class),
            $this->createMock(AiUsageCharger::class),
            $this->createMock(WhisperService::class),
        );
    }

    public function test_matches_canonical_wake_phrase_variants(): void
    {
        $c = $this->controller();
        $cases = [
            'Hey Sayzio',
            'hey 1inme!',
            'Hey, Sayzio.',
            'Hey one in me',
            'Hey OneInMe',
            'Okay Sayzio, what time is it?',
            'Hi 1inme',
        ];
        foreach ($cases as $t) {
            $this->assertTrue(
                $c->matchesWakePhrase($t),
                "Expected wake match for transcript: {$t}"
            );
        }
    }

    public function test_rejects_unrelated_speech(): void
    {
        $c = $this->controller();
        $cases = [
            '',
            'hello there',
            'hey siri what time is it',
            'one in me sounds like a song lyric',
            'play me a song',
            'inme is a brand i guess',
        ];
        foreach ($cases as $t) {
            $this->assertFalse(
                $c->matchesWakePhrase($t),
                "Expected NO wake match for transcript: {$t}"
            );
        }
    }
}
