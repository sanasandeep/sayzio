<?php

namespace Tests\Feature;

use App\Modules\Common\Models\SiteAssistantConversation;
use App\Modules\User\Models\AiCreditBalance;
use App\Modules\User\Models\User;
use App\Services\AI\AiCreditService;
use App\Services\AI\AiMindQueryService;
use App\Services\AI\OpenAiService;
use App\Services\AI\SiteAssistantRuntime;
use App\Services\AI\SiteAssistantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for {@see SiteAssistantRuntime::lowBalanceSignal()} —
 * specifically that it surfaces the locale-resolved CTA label so a
 * French-speaking visitor sees "Recharger", not the built-in English
 * "Top up" / "See plans" copy. A regression here silently regresses
 * every non-English deployment.
 */
class SiteAssistantLowBalanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Subclass the runtime so the test can:
     *   1. Force {@see billingUser()} to return a known account
     *      (anonymous visitors otherwise depend on a roles/permissions
     *      lookup that is irrelevant to the label-resolution branch
     *      this test pins).
     *   2. Reach the protected `lowBalanceSignal()` directly without
     *      driving a full `openSession()` (which pulls in retrieval,
     *      cache, page hints, etc. — all noise for this assertion).
     */
    private function makeRuntime(User $forcedBillingUser): SiteAssistantRuntime
    {
        return new class(
            app(OpenAiService::class),
            app(AiMindQueryService::class),
            app(AiCreditService::class),
            $forcedBillingUser,
        ) extends SiteAssistantRuntime {
            public function __construct(
                OpenAiService $o,
                AiMindQueryService $m,
                AiCreditService $c,
                public User $forcedBillingUser,
            ) {
                parent::__construct($o, $m, $c);
            }
            protected function billingUser(?User $user): ?User
            {
                return $user ?: $this->forcedBillingUser;
            }
            public function publicLowBalanceSignal(SiteAssistantConversation $conv, ?User $user): array
            {
                return $this->lowBalanceSignal($conv, $user);
            }
        };
    }

    private function makeUserWithBalance(int $balance): User
    {
        $user = User::create([
            'name'     => 'Low Balance Tester',
            'email'    => 'low-' . Str::random(6) . '@example.test',
            'password' => bcrypt('x'),
            'status'   => 'active',
            'role'     => 'user',
        ]);
        AiCreditBalance::create(['user_id' => $user->id, 'balance' => $balance]);
        return $user;
    }

    private function makeConversation(?User $user, string $surface = 'app'): SiteAssistantConversation
    {
        return SiteAssistantConversation::create([
            'visitor_token' => 'sa_' . Str::random(28),
            'surface'       => $surface,
            'user_id'       => $user?->id,
            'bound_user_id' => $user?->id,
        ]);
    }

    public function test_low_balance_signal_returns_localized_label_for_signed_in_visitor(): void
    {
        SiteAssistantSettings::update([
            'low_balance_default_credits'      => 50,
            'low_balance_multiplier'           => 3,   // threshold = 150 credits
            'low_balance_topup_label'          => 'Add credits',
            'low_balance_topup_label_locales' => [
                'fr' => 'Recharger',
            ],
        ]);

        // Drive the request-bound Accept-Language so the runtime's
        // implicit `request()->server('HTTP_ACCEPT_LANGUAGE')` lookup
        // (used when callers don't pass an explicit value) resolves to
        // the French override instead of the global default.
        $this->call('GET', '/', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'fr-CA,fr;q=0.9']);

        $user = $this->makeUserWithBalance(80); // below 150 → "low"
        $conv = $this->makeConversation($user);

        $signal = $this->makeRuntime($user)->publicLowBalanceSignal($conv, $user);

        $this->assertTrue($signal['low']);
        $this->assertSame('Recharger', $signal['topup_label']);
        $this->assertSame(1, (int) $signal['remaining_replies']); // floor(80/50) = 1
    }

    public function test_low_balance_signal_returns_localized_label_for_anonymous_visitor(): void
    {
        SiteAssistantSettings::update([
            'low_balance_default_credits'      => 50,
            'low_balance_multiplier'           => 3,
            'low_balance_topup_label'          => '', // no global default
            'low_balance_topup_label_locales' => [
                'fr' => 'Voir les forfaits',
            ],
            // Make sure the anonymous bubble has a non-default message
            // so the test exercises the localised override end-to-end.
            'low_balance_message_locales'      => [
                'fr' => ['anonymous' => 'Crédits bientôt épuisés.'],
            ],
        ]);

        $this->call('GET', '/', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        // Billing user owns the wallet that anonymous visitors charge
        // against; we just need a low balance on it to trip the signal.
        $billing = $this->makeUserWithBalance(80);
        $conv    = $this->makeConversation(null, surface: 'marketing');

        $signal = $this->makeRuntime($billing)->publicLowBalanceSignal($conv, null);

        $this->assertTrue($signal['low']);
        $this->assertSame('Voir les forfaits', $signal['topup_label']);
        $this->assertSame('Crédits bientôt épuisés.', $signal['message']);
        // Anonymous callers must never receive a numeric remaining count
        // — that would leak the platform-wide wallet status.
        $this->assertNull($signal['remaining_replies']);
    }

    public function test_low_balance_signal_uses_admin_default_label_when_no_locale_match(): void
    {
        // Spanish-speaking visitor with only a French override
        // configured falls back to the admin-configured default,
        // not the built-in "Top up" copy.
        SiteAssistantSettings::update([
            'low_balance_default_credits'      => 50,
            'low_balance_multiplier'           => 3,
            'low_balance_topup_label'          => 'Add credits',
            'low_balance_topup_label_locales' => [
                'fr' => 'Recharger',
            ],
        ]);

        $this->call('GET', '/', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'es-ES']);

        $user = $this->makeUserWithBalance(80);
        $conv = $this->makeConversation($user);

        $signal = $this->makeRuntime($user)->publicLowBalanceSignal($conv, $user);

        $this->assertTrue($signal['low']);
        $this->assertSame('Add credits', $signal['topup_label']);
    }
}
