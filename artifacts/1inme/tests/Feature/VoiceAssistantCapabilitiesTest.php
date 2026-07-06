<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Services\AI\AiUsageCharger;
use App\Services\Billing\WalletService;
use App\Services\AI\AiEngineSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the Voice Assistant capabilities endpoint.
 *
 * `/voice/capabilities` powers the in-product "What I can do / can't do"
 * help panel. If its shape silently drifts users will see lies about
 * what voice can do (wrong pricing, missing limitations, admin-only
 * tools leaking to regular users, etc.) and the bug will only surface
 * via support tickets. These tests pin:
 *
 *   1. Top-level shape — `enabled`, `balance`, `rate_limit`, and the
 *      `pricing` map are present and correct for an allowed caller.
 *   2. Permission gating — the `admin_grant_credits` tool appears for
 *      admin callers and is filtered out for non-admin callers.
 *   3. Limitations contract — the `limitations` array is non-empty and
 *      includes the documented "no phone calls / no wake word /
 *      no scheduled actions" entries that the panel promises users.
 */
class VoiceAssistantCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AiEngineSettings::setVoiceEnabled(true);
        // Empty allow-list = every plan is allowed by default.
        AiEngineSettings::setVoiceEnabledPlans([]);
    }

    private function makeUser(string $tag = 'c', array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Voice ' . $tag,
            'email' => $tag . '-' . Str::random(8) . '@example.com',
            'role' => 'user',
        ], $overrides));
    }

    // ── 1) top-level shape ────────────────────────────────────────────────────

    public function test_capabilities_returns_enabled_balance_rate_limit_and_pricing_for_allowed_user(): void
    {
        // Pin pricing + rate limit so this test is independent of any
        // future change to the AppSetting defaults.
        AiEngineSettings::setVoiceSttCreditsPerMinute(30);
        AiEngineSettings::setVoiceTtsCreditsPer1kChars(50);
        AiEngineSettings::setVoiceTurnsPerMinute(12);

        $user = $this->makeUser('shape');
        app(WalletService::class)->credit($user, 250, ['reason' => 'test seed']);

        $resp = $this->actingAs($user)->getJson(route('user.ai.voice.capabilities'));

        $resp->assertOk();
        $resp->assertJsonPath('enabled', true);
        $resp->assertJsonPath('balance', 250);
        $resp->assertJsonPath('rate_limit', 12);
        $resp->assertJsonPath('pricing.stt_credits_per_minute', 30);
        $resp->assertJsonPath('pricing.tts_credits_per_1k_chars', 50);

        // The grouped tool catalogue must be an object (categories are
        // keys) and contain at least one category for the panel to
        // render against.
        $tools = $resp->json('tools');
        $this->assertIsArray($tools);
        $this->assertNotEmpty($tools, 'Tool catalogue must not be empty for an allowed caller.');
    }

    // ── 2) admin-only tools are gated ─────────────────────────────────────────

    public function test_admin_only_tools_appear_for_admin_callers(): void
    {
        $admin = $this->makeUser('adm');
        $userAdminRoleId = \Illuminate\Support\Facades\DB::table('roles')
            ->where('slug', 'user-admin')->where('guard', 'web')
            ->value('id');
        $this->assertNotNull($userAdminRoleId, 'user-admin role must be seeded for this test');
        $admin->roles()->syncWithoutDetaching([$userAdminRoleId]);
        $admin->flushPermissionCache();

        $resp = $this->actingAs($admin)->getJson(route('user.ai.voice.capabilities'));

        $resp->assertOk();
        $tools = $resp->json('tools');
        $this->assertIsArray($tools);

        $names = $this->toolNames($tools);
        $this->assertContains(
            'admin_grant_credits',
            $names,
            'Admin callers must see the admin_grant_credits tool in their capabilities list.'
        );

        // Sanity: every admin-roled tool surfaced must declare role=admin.
        foreach ($tools['admin'] ?? [] as $entry) {
            $this->assertSame('admin', $entry['role']);
        }
    }

    public function test_admin_only_tools_are_hidden_from_non_admin_callers(): void
    {
        $user = $this->makeUser('reg');

        $resp = $this->actingAs($user)->getJson(route('user.ai.voice.capabilities'));

        $resp->assertOk();
        $tools = $resp->json('tools');
        $this->assertIsArray($tools);

        $names = $this->toolNames($tools);
        $this->assertNotContains(
            'admin_grant_credits',
            $names,
            'Non-admin callers must not see admin_grant_credits — leaking it would mis-advertise destructive admin powers.'
        );

        // No category on a non-admin response should be flagged role=admin.
        foreach ($tools as $entries) {
            foreach ($entries as $entry) {
                $this->assertNotSame(
                    'admin',
                    $entry['role'],
                    "Tool '{$entry['name']}' is admin-roled but was returned to a non-admin caller."
                );
            }
        }
    }

    // ── 3) limitations array contract ─────────────────────────────────────────

    public function test_limitations_list_is_non_empty_and_documents_the_promised_exclusions(): void
    {
        $user = $this->makeUser('lim');

        $resp = $this->actingAs($user)->getJson(route('user.ai.voice.capabilities'));

        $resp->assertOk();
        $limits = $resp->json('limitations');
        $this->assertIsArray($limits);
        $this->assertNotEmpty($limits, 'Limitations list must not be empty — the help panel renders it verbatim.');

        $haystack = strtolower(implode("\n", $limits));
        $this->assertStringContainsString(
            'phone call',
            $haystack,
            'Limitations must document "no phone calls" so users do not expect outbound dialing.'
        );
        $this->assertStringContainsString(
            'wake word',
            $haystack,
            'Limitations must document "no wake word" so users know to press the mic.'
        );
        $this->assertStringContainsString(
            'scheduled action',
            $haystack,
            'Limitations must document that voice does not run scheduled actions.'
        );
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Flatten the grouped `tools` payload (`category => [{name, ...}, ...]`)
     * down to a flat list of tool names for membership assertions.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $grouped
     * @return array<int, string>
     */
    private function toolNames(array $grouped): array
    {
        $names = [];
        foreach ($grouped as $entries) {
            foreach ($entries as $entry) {
                if (isset($entry['name'])) {
                    $names[] = $entry['name'];
                }
            }
        }
        return $names;
    }
}
