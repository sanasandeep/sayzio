<?php

namespace Tests\Feature;

use App\Modules\Common\Services\GeoIpService;
use App\Modules\Common\Support\SignupCountry;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use App\Services\PricingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The sign-up forms no longer ask for a country, but users.country drives
 * billing currency (config/country_currency.php: IN => INR, else USD).
 * These tests pin that new signups still get a sensible country inferred —
 * from the phone number's dialling code first, then GeoIP on the request
 * IP — so Indian creators keep seeing ₹ pricing. Existing users are
 * untouched (inference only runs at account creation).
 */
class SignupCountryInferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Plan::firstOrCreate(['slug' => 'free'], [
            'name' => 'Free', 'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'grace_days' => 0, 'refund_window_days' => 0,
            'status' => 'active', 'sort_order' => 0, 'features' => [],
            'is_default' => true,
        ]);
    }

    /**
     * Register payload; includes a password pair so the test passes in
     * both auth modes (extra fields are ignored when password login is off).
     */
    private function payload(array $fields): array
    {
        return array_merge([
            'password'              => 'secret-password-1',
            'password_confirmation' => 'secret-password-1',
        ], $fields);
    }

    /** Stub GeoIP so tests never hit the network. */
    private function fakeGeo(?string $country): void
    {
        $geo = new class($country) extends GeoIpService {
            public function __construct(private ?string $cc) {}
            public function detectCountry(string $ip): ?string
            {
                return $this->cc;
            }
        };
        $this->app->instance(GeoIpService::class, $geo);
    }

    public function test_dial_code_maps_to_country(): void
    {
        $this->assertSame('IN', SignupCountry::fromMobile('+91 98765 43210'));
        $this->assertSame('IN', SignupCountry::fromMobile('919876543210'));
        $this->assertSame('US', SignupCountry::fromMobile('+1 555 000 1111'));
        $this->assertSame('BD', SignupCountry::fromMobile('+8801712345678')); // longest prefix wins over +88x families
        $this->assertNull(SignupCountry::fromMobile(''));
        $this->assertNull(SignupCountry::fromMobile(null));
    }

    public function test_phone_dial_code_beats_geoip(): void
    {
        $this->fakeGeo('US');
        $this->assertSame('IN', SignupCountry::infer('+91 98765 43210', '203.0.113.5'));
    }

    public function test_register_without_country_uses_indian_mobile_dial_code(): void
    {
        $this->fakeGeo(null);
        $email = 'in-' . Str::lower(Str::random(8)) . '@example.com';

        $this->post('/user/register', $this->payload([
            'name'   => 'Asha',
            'email'  => $email,
            'mobile' => '+91 98765 43210',
        ]))->assertSessionHasNoErrors();

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user, 'signup should create the account');
        $this->assertSame('IN', $user->country);
        $this->assertSame('INR', PricingResolver::currencyForUser($user));
    }

    public function test_register_without_country_or_mobile_falls_back_to_geoip(): void
    {
        $this->fakeGeo('IN');
        $email = 'geo-' . Str::lower(Str::random(8)) . '@example.com';

        $this->post('/user/register', $this->payload([
            'name'  => 'Ravi',
            'email' => $email,
        ]))->assertSessionHasNoErrors();

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertSame('IN', $user->country);
        $this->assertSame('INR', PricingResolver::currencyForUser($user));
    }

    public function test_register_with_unresolvable_geo_leaves_country_null(): void
    {
        $this->fakeGeo(null);
        $email = 'null-' . Str::lower(Str::random(8)) . '@example.com';

        $this->post('/user/register', $this->payload([
            'name'  => 'Sam',
            'email' => $email,
        ]))->assertSessionHasNoErrors();

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertNull($user->country);
        // No country → USD default (manual switcher / profile still work).
        $this->assertSame('USD', PricingResolver::currencyForUser($user));
    }

    public function test_explicit_country_still_wins_over_inference(): void
    {
        $this->fakeGeo('IN');
        $email = 'gb-' . Str::lower(Str::random(8)) . '@example.com';

        $this->post('/user/register', $this->payload([
            'name'    => 'Tess',
            'email'   => $email,
            'country' => 'gb',
        ]))->assertSessionHasNoErrors();

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertSame('GB', $user->country);
    }
}
