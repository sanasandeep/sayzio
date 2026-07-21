<?php

namespace Tests\Feature;

use App\Modules\User\Models\DialerNumberFlag;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * GET /api/v1/dialer/flags — the list of numbers the user flagged as
 * spam/blocked that feeds the mobile caller-ID directory sync, so the
 * native incoming-call card can show a red warning while the JS runtime
 * is dead. Display-only: nothing here blocks or silences a call.
 *
 * Uses a REAL Bearer token (see .agents/memory/sanctum-api-tests.md —
 * Sanctum::actingAs breaks the TouchSessionToken middleware).
 */
class DialerFlagsEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $prefix = 'u'): User
    {
        return User::factory()->create([
            'name' => $prefix . Str::random(4),
            'email' => $prefix . '-' . Str::random(8) . '@example.com',
        ]);
    }

    public function test_returns_only_flagged_numbers_for_the_authenticated_user(): void
    {
        $user = $this->makeUser('a');
        $other = $this->makeUser('b');

        DialerNumberFlag::create([
            'user_id' => $user->id, 'number_e164' => '+15550001111',
            'is_spam' => true, 'is_blocked' => false,
        ]);
        DialerNumberFlag::create([
            'user_id' => $user->id, 'number_e164' => '+15550002222',
            'is_spam' => false, 'is_blocked' => true,
        ]);
        // Cleared flag row — must be omitted.
        DialerNumberFlag::create([
            'user_id' => $user->id, 'number_e164' => '+15550003333',
            'is_spam' => false, 'is_blocked' => false,
        ]);
        // Another user's flag — must never leak.
        DialerNumberFlag::create([
            'user_id' => $other->id, 'number_e164' => '+15550004444',
            'is_spam' => true, 'is_blocked' => false,
        ]);

        $this->withToken($user->createToken('dialer-flags-test')->plainTextToken);
        $res = $this->getJson('/api/v1/dialer/flags');

        $res->assertOk();
        $items = $res->json('data.items');
        $this->assertSame([
            ['number_e164' => '+15550001111', 'is_spam' => true, 'is_blocked' => false],
            ['number_e164' => '+15550002222', 'is_spam' => false, 'is_blocked' => true],
        ], $items);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/dialer/flags')->assertStatus(401);
    }
}
