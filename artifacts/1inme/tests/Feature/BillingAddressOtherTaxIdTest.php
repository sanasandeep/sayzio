<?php

namespace Tests\Feature;

use App\Modules\User\Models\BillingAddress;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the free-text state + "Other" tax-id features on the billing address
 * against silent regressions:
 *
 *   1. A typed state name for a non-IN/US country must be stored verbatim
 *      (case preserved) and repopulate on reload — only IN/US state *codes*
 *      are uppercased.
 *   2. tax_id_kind = OTHER with a custom label + number must persist all three
 *      fields, and the GSTIN/VATIN format gates must NOT fire (those only apply
 *      to the GSTIN/VATIN kinds).
 *   3. The invoice PDF Bill-to block must print the custom label, never the
 *      literal "OTHER".
 *
 * Covers POST /user/profile (ProfileController::update) and the
 * user.invoices.pdf Blade snapshot.
 */
class BillingAddressOtherTaxIdTest extends TestCase
{
    use RefreshDatabase;

    private function webProfilePayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name'     => $user->name,
            'email'    => $user->email,
            'timezone' => 'UTC',
            'language' => 'en',
        ], $overrides);
    }

    public function test_typed_state_for_non_in_us_country_persists_verbatim_and_reloads(): void
    {
        $user = User::factory()->create(['name' => 'Region User'])->fresh();

        // Germany (non-IN/US) with a mixed-case free-text state name. The
        // controller must NOT uppercase this — only IN/US state codes.
        $resp = $this->actingAs($user)->put(
            route('user.profile.update'),
            $this->webProfilePayload($user, [
                'billing_country' => 'DE',
                'billing_region'  => 'Bavaria',
            ])
        );

        $resp->assertSessionHasNoErrors();

        $billing = BillingAddress::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('DE', $billing->country);
        // Verbatim — case preserved, not "BAVARIA".
        $this->assertSame('Bavaria', $billing->region);

        // Reload the edit page: the typed region repopulates in the input.
        $edit = $this->actingAs($user)->get(route('user.profile.edit'));
        $edit->assertOk();
        $edit->assertSee('Bavaria', false);
    }

    public function test_in_us_state_codes_are_still_uppercased(): void
    {
        // Sanity counterpart: IN/US remain code-based and uppercased so the
        // "preserve verbatim" branch above is proven to be country-gated.
        $user = User::factory()->create()->fresh();

        $resp = $this->actingAs($user)->put(
            route('user.profile.update'),
            $this->webProfilePayload($user, [
                'billing_country' => 'IN',
                'billing_region'  => 'mh',
            ])
        );

        $resp->assertSessionHasNoErrors();

        $billing = BillingAddress::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('IN', $billing->country);
        $this->assertSame('MH', $billing->region);
    }

    public function test_other_tax_id_kind_persists_all_three_without_format_validation(): void
    {
        $user = User::factory()->create()->fresh();

        // A number that is NOT a valid GSTIN (15-char + checksum) nor a
        // recognised VATIN — if the OTHER path wrongly ran those gates this
        // would bounce back with a validation error.
        $resp = $this->actingAs($user)->put(
            route('user.profile.update'),
            $this->webProfilePayload($user, [
                'billing_country' => 'AU',
                'tax_id_kind'     => 'OTHER',
                'tax_id_label'    => 'ABN',
                'tax_id'          => '51824753556',
            ])
        );

        $resp->assertSessionHasNoErrors();

        $billing = BillingAddress::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('OTHER', $billing->tax_id_kind);
        $this->assertSame('ABN', $billing->tax_id_label);
        // tax_id is uppercased on save (existing behaviour), but the raw digits
        // round-trip intact.
        $this->assertSame('51824753556', $billing->tax_id);
    }

    public function test_invoice_pdf_shows_custom_tax_label_instead_of_other(): void
    {
        $user = User::factory()->create()->fresh();

        $invoice = Invoice::create([
            'number'                   => 'INV/TEST/00001',
            'financial_year'           => '2026-27',
            'seq'                      => 1,
            'user_id'                  => $user->id,
            'currency'                 => 'AUD',
            'subtotal_minor'           => 10000,
            'tax_total_minor'          => 0,
            'grand_total_minor'        => 10000,
            'billing_address_snapshot' => [
                'buyer_name'    => 'Acme Pty Ltd',
                'business_name' => 'Acme Pty Ltd',
                'line1'         => '1 Test St',
                'city'          => 'Sydney',
                'region'        => 'New South Wales',
                'postal_code'   => '2000',
                'country'       => 'AU',
                'tax_id'        => '51824753556',
                'tax_id_kind'   => 'OTHER',
                'tax_id_label'  => 'ABN',
            ],
            'merchant_snapshot'  => ['name' => 'Sayzio'],
            'line_items'         => [
                ['label' => 'Plan', 'quantity' => 1, 'amount_minor' => 10000, 'line_total_minor' => 10000],
            ],
            'tax_breakdown'      => [],
            'reverse_charge_note' => null,
            'place_of_supply'    => null,
            'issued_at'          => now(),
        ]);

        $html = view('user.invoices.pdf', [
            'invoice'  => $invoice,
            'merchant' => $invoice->merchant_snapshot,
            'address'  => $invoice->billing_address_snapshot,
        ])->render();

        // The custom label is printed with the number, and the literal "OTHER"
        // kind is never surfaced as the label.
        $this->assertStringContainsString('ABN: 51824753556', $html);
        $this->assertStringNotContainsString('OTHER: 51824753556', $html);
        $this->assertStringNotContainsString('OTHER:', $html);
    }
}
