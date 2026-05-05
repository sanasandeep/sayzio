<?php

namespace App\Services\CreatorPayouts;

use App\Modules\User\Models\CreatorPaymentConnection;
use App\Modules\User\Models\User;

/**
 * Static registry describing every payout provider 1INME supports for
 * creator earnings. Each entry is a thin descriptor — the provider's
 * own onboarding flow and KYC are hosted; we only persist the resulting
 * connected-account id, status, and provider metadata.
 *
 * Listed providers:
 *   - stripe   — Stripe Connect (default for SFW)
 *   - paypal   — PayPal Commerce Platform / payouts
 *   - razorpay — Razorpay Route (India)
 *   - ccbill   — CCBill (adult-friendly)
 *   - segpay   — Segpay (adult-friendly)
 *
 * The platform takes 0% from creator earnings — fees are entirely the
 * provider's, surfaced from this registry so the dashboard can show
 * the trade-offs up front.
 */
class PayoutProviderRegistry
{
    public const PROVIDERS = [
        'stripe' => [
            'slug'            => 'stripe',
            'name'            => 'Stripe Connect',
            'icon'            => 'fab fa-stripe-s',
            'tint'            => '#635bff',
            'short'           => 'Default for SFW creators in 40+ countries with full charge-and-payout support.',
            'countries'       => '40+ countries (US, UK, EU, AU, NZ, JP, CA, SG, …)',
            'payout_speed'    => 'Daily / weekly / monthly (T+2)',
            'fees'            => '2.9% + 30¢ per transaction (varies by country)',
            'adult_friendly'  => false,
            'env_keys'        => ['STRIPE_CONNECT_CLIENT_ID', 'STRIPE_SECRET_KEY'],
            'docs_url'        => 'https://stripe.com/connect',
        ],
        'paypal' => [
            'slug'            => 'paypal',
            'name'            => 'PayPal',
            'icon'            => 'fab fa-paypal',
            'tint'            => '#0070ba',
            'short'           => 'Use a PayPal business account for payouts. Familiar to most creators.',
            'countries'       => '200+ countries',
            'payout_speed'    => 'Instant to PayPal balance, 1-3 days to bank',
            'fees'            => 'Standard PayPal Commerce Platform fees',
            'adult_friendly'  => false,
            'env_keys'        => ['PAYPAL_CLIENT_ID', 'PAYPAL_CLIENT_SECRET'],
            'docs_url'        => 'https://developer.paypal.com/docs/multiparty/',
        ],
        'razorpay' => [
            'slug'            => 'razorpay',
            'name'            => 'Razorpay Route',
            'icon'            => 'fas fa-indian-rupee-sign',
            'tint'            => '#3395ff',
            'short'           => 'Best fit for India-based creators. Routed payouts in INR via Razorpay.',
            'countries'       => 'India only',
            'payout_speed'    => 'T+2 to Indian bank accounts',
            'fees'            => '2% + GST per transaction',
            'adult_friendly'  => false,
            'env_keys'        => ['RAZORPAY_KEY_ID', 'RAZORPAY_KEY_SECRET'],
            'docs_url'        => 'https://razorpay.com/route/',
        ],
        'ccbill' => [
            'slug'            => 'ccbill',
            'name'            => 'CCBill',
            'icon'            => 'fas fa-credit-card',
            'tint'            => '#e63946',
            'short'           => 'Adult-friendly processor with high-risk merchant accounts and global card support.',
            'countries'       => 'Global (adult-friendly markets)',
            'payout_speed'    => 'Weekly via wire / ACH',
            'fees'            => '~10–15% effective rate (high-risk pricing)',
            'adult_friendly'  => true,
            'env_keys'        => ['CCBILL_AFFILIATE_ID', 'CCBILL_API_TOKEN'],
            'docs_url'        => 'https://ccbill.com/',
        ],
        'segpay' => [
            'slug'            => 'segpay',
            'name'            => 'Segpay',
            'icon'            => 'fas fa-shield-halved',
            'tint'            => '#7b1fa2',
            'short'           => 'Adult-friendly processor with strong chargeback protection and EU presence.',
            'countries'       => 'Global (adult-friendly markets, EU-friendly)',
            'payout_speed'    => 'Bi-weekly via wire / ACH / Paxum',
            'fees'            => '~10–14% effective rate (high-risk pricing)',
            'adult_friendly'  => true,
            'env_keys'        => ['SEGPAY_PACKAGE_ID', 'SEGPAY_API_KEY'],
            'docs_url'        => 'https://www.segpay.com/',
        ],
    ];

    /**
     * Return all providers, optionally filtered by adult-friendliness.
     * When $includeAdult is false, the adult-only providers are hidden
     * (used for SFW creators).
     */
    public static function all(bool $includeAdult = true): array
    {
        if ($includeAdult) return self::PROVIDERS;
        return array_filter(self::PROVIDERS, fn ($p) => empty($p['adult_friendly']));
    }

    public static function get(string $slug): ?array
    {
        return self::PROVIDERS[$slug] ?? null;
    }

    public static function isAdultFriendly(string $slug): bool
    {
        return (bool) (self::PROVIDERS[$slug]['adult_friendly'] ?? false);
    }

    /** Slugs of the two adult-friendly processors. */
    public static function adultFriendlySlugs(): array
    {
        return array_values(array_filter(
            array_keys(self::PROVIDERS),
            fn ($s) => self::PROVIDERS[$s]['adult_friendly']
        ));
    }

    /**
     * Resolve the lightweight adapter for a provider. Each adapter is a
     * thin object that knows how to build the hosted onboarding URL and
     * the dashboard link for that provider. Real signature verification
     * + webhook handling lives behind these classes; the platform-side
     * routing in this task just stashes the connected-account id.
     */
    public static function adapter(string $slug): PayoutProviderAdapter
    {
        $provider = self::get($slug);
        if (!$provider) abort(404, "Unknown payout provider: {$slug}");

        return match ($slug) {
            'stripe'   => new Adapters\StripeConnectAdapter($provider),
            'paypal'   => new Adapters\PaypalPayoutAdapter($provider),
            'razorpay' => new Adapters\RazorpayRouteAdapter($provider),
            'ccbill'   => new Adapters\CcbillAdapter($provider),
            'segpay'   => new Adapters\SegpayAdapter($provider),
        };
    }

    /**
     * Atomically pick a new default connection for the user. Clears the
     * is_default flag on every other row so the "exactly one default"
     * invariant holds without needing a partial unique index.
     */
    public static function setDefault(User $user, CreatorPaymentConnection $conn): void
    {
        \DB::transaction(function () use ($user, $conn) {
            CreatorPaymentConnection::where('user_id', $user->id)
                ->where('id', '!=', $conn->id)
                ->update(['is_default' => false]);
            $conn->is_default = true;
            $conn->save();
        });
    }
}
