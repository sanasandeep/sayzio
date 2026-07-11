<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Return to app" banner + sayzio://billing/refresh auto hand-off on the
 * billing page must only appear for a genuine, freshly-paid checkout that
 * originated from the native app (/pricing?client=app). A plain-web upgrade,
 * or a stale flag left behind by an abandoned app checkout, must never show it.
 */
class BillingAppReturnBannerTest extends TestCase
{
    use RefreshDatabase;

    private const DEEP_LINK = 'sayzio://billing/refresh';

    private function makeUser(): User
    {
        return User::factory()->create(['country' => 'IN']);
    }

    public function test_plain_web_upgrade_never_shows_the_app_return_banner(): void
    {
        $user = $this->makeUser();

        // No app flag in the session at all → banner must not render even when
        // landing with ?paid.
        $this->actingAs($user)
            ->get(route('user.billing.show', ['paid' => 1]))
            ->assertOk()
            ->assertDontSee(self::DEEP_LINK, false);
    }

    public function test_app_originated_paid_return_shows_the_banner_once(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)
            ->withSession(['billing.app_return' => time()])
            ->get(route('user.billing.show', ['paid' => 1]));

        $response->assertOk()->assertSee(self::DEEP_LINK, false);

        // Pull-and-forget: a second visit (flag already consumed) must not
        // re-show the banner.
        $this->actingAs($user)
            ->get(route('user.billing.show', ['paid' => 1]))
            ->assertOk()
            ->assertDontSee(self::DEEP_LINK, false);
    }

    public function test_app_flag_without_paid_does_not_show_the_banner(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->withSession(['billing.app_return' => time()])
            ->get(route('user.billing.show'))
            ->assertOk()
            ->assertDontSee(self::DEEP_LINK, false);
    }

    public function test_stale_app_flag_is_treated_as_expired(): void
    {
        $user = $this->makeUser();

        // Flag set well outside the honor window (an abandoned app checkout).
        $this->actingAs($user)
            ->withSession(['billing.app_return' => time() - 7200])
            ->get(route('user.billing.show', ['paid' => 1]))
            ->assertOk()
            ->assertDontSee(self::DEEP_LINK, false);
    }

    public function test_legacy_boolean_flag_is_treated_as_expired(): void
    {
        $user = $this->makeUser();

        // A flag stored before this change was a bare `true`, which casts to 1
        // (epoch) and must read as long-expired rather than firing the banner.
        $this->actingAs($user)
            ->withSession(['billing.app_return' => true])
            ->get(route('user.billing.show', ['paid' => 1]))
            ->assertOk()
            ->assertDontSee(self::DEEP_LINK, false);
    }
}
