<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for PATCH /api/v1/profile (mobile Edit profile save).
 *
 * Bug (July 2026): the mobile app PATCHes its FULL form payload, including
 * `timezone: null` / `language: null` when those inputs are empty. Both are
 * NOT NULL columns on `users`, so the save 500'd with a Postgres not-null
 * violation. Empty must mean "leave unchanged".
 *
 * Also pins the mobile Safety & Moderation parity added in the same fix:
 * mute_words_text / watermark_enabled / country_block_text /
 * country_allow_text / dmca_email are accepted by the API PATCH (with the
 * same normalisation as the web CreatorProfileController save) and are
 * echoed back in text form by the self UserResource payload.
 *
 * Uses a real personal access token, NOT Sanctum::actingAs (which breaks
 * the TouchSessionToken middleware — see .agents/memory/sanctum-api-tests.md).
 */
class ProfileApiSafetyModerationTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_null_timezone_and_language_leave_values_unchanged(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Kolkata', 'language' => 'en']);

        $res = $this->withToken($this->tokenFor($user))
            ->patchJson('/api/v1/profile', [
                'name'     => 'Sana Sandeep',
                'timezone' => null,
                'language' => null,
            ]);

        $res->assertOk();
        $user->refresh();
        $this->assertSame('Sana Sandeep', $user->name);
        $this->assertSame('Asia/Kolkata', $user->timezone);
        $this->assertSame('en', $user->language);
        $this->flushHeaders();
    }

    public function test_empty_string_timezone_is_ignored_but_real_value_saves(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Kolkata']);

        $this->withToken($this->tokenFor($user))
            ->patchJson('/api/v1/profile', ['timezone' => ' '])
            ->assertOk();
        $this->assertSame('Asia/Kolkata', $user->refresh()->timezone);

        $this->patchJson('/api/v1/profile', ['timezone' => 'Europe/London'])
            ->assertOk();
        $this->assertSame('Europe/London', $user->refresh()->timezone);
        $this->flushHeaders();
    }

    public function test_safety_moderation_fields_save_and_round_trip(): void
    {
        $user = User::factory()->create();

        $res = $this->withToken($this->tokenFor($user))
            ->patchJson('/api/v1/profile', [
                'mute_words_text'    => "Slur1, slur2\nscammer, slur1",
                'watermark_enabled'  => true,
                'country_block_text' => 'us, gb; de xx1',
                'country_allow_text' => 'US, ca',
                'dmca_email'         => 'legal@example.com',
            ]);

        $res->assertOk();
        $user->refresh();
        $this->assertSame(['slur1', 'slur2', 'scammer'], $user->mute_words);
        $this->assertTrue((bool) ($user->watermark_settings['enabled'] ?? false));
        $this->assertSame(['US', 'GB', 'DE'], $user->country_block_list);
        $this->assertSame(['US', 'CA'], $user->country_allow_list);
        $this->assertSame('legal@example.com', $user->dmca_email);

        // Round-trip: the self payload exposes text-form mirrors.
        $show = $this->getJson('/api/v1/profile')->assertOk()->json('data.user');
        $this->assertSame('slur1, slur2, scammer', $show['mute_words_text']);
        $this->assertTrue($show['watermark_enabled']);
        $this->assertSame('US, GB, DE', $show['country_block_text']);
        $this->assertSame('US, CA', $show['country_allow_text']);
        $this->assertSame('legal@example.com', $show['dmca_email']);
        $this->flushHeaders();
    }

    public function test_empty_dmca_email_clears_stored_value(): void
    {
        $user = User::factory()->create(['dmca_email' => 'old@example.com']);

        $this->withToken($this->tokenFor($user))
            ->patchJson('/api/v1/profile', ['dmca_email' => null])
            ->assertOk();

        $this->assertNull($user->refresh()->dmca_email);
        $this->flushHeaders();
    }
}
