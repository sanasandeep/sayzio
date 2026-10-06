<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\BillingCompany;
use Illuminate\Validation\ValidationException;

class MenuBillingCompany
{
    public static function companies(int $ownerId)
    {
        return BillingCompany::where('user_id', $ownerId)->with(['defaultTaxRule', 'taxRules'])->orderBy('name')->get();
    }

    public static function apply(array $settings, ?int $id, int $ownerId, ?int $ruleId = null): array
    {
        if (!$id) {
            unset($settings['tax']['components']);
            unset($settings['billing_company_id'], $settings['billing_company'], $settings['billing_tax_rule_id']);
            return $settings;
        }
        $company = BillingCompany::where('user_id', $ownerId)->with('defaultTaxRule')->find($id);
        if (!$company) throw ValidationException::withMessages(['billing_company_id' => 'Choose one of your billing companies.']);
        $rule = $ruleId ? \App\Modules\User\Models\TaxRule::where('user_id', $ownerId)->where('billing_company_id', $id)->where('is_active', true)->find($ruleId) : $company->defaultTaxRule;
        if ($ruleId && !$rule) throw ValidationException::withMessages(['billing_tax_rule_id' => 'Choose an active tax profile for this company.']);
        $settings['billing_tax_rule_id'] = $ruleId;
        if ($rule && ((int) $rule->user_id !== $ownerId || ($rule->billing_company_id && (int) $rule->billing_company_id !== $id))) {
            throw ValidationException::withMessages(['billing_company_id' => 'The company tax rule does not belong to this business.']);
        }
        $settings['billing_company_id'] = $company->id;
        $settings['billing_company'] = $company->toSnapshot();
        $settings['tax'] = [
            'enabled' => $rule && $rule->is_active && $rule->rate_bps > 0,
            'rate' => $rule && $rule->is_active ? max(0, min(100, $rule->ratePercent())) : 0,
            'components' => $rule && $rule->is_active ? ($rule->components ?? []) : [],
            'label' => $rule?->name ?: 'Tax', 'inclusive' => (bool) $rule?->inclusive,
        ];
        return $settings;
    }

    public static function storeBill($menu, float $subtotal, ?string $fulfilment): array
    {
        $settings = (array) $menu->settings;
        $charges = MenuFulfilment::applicable($settings, $fulfilment, $subtotal);
        $amount = MenuFulfilment::total($charges);
        $tax = !empty($settings['billing_company_id']) ? ($settings['tax'] ?? []) : [];
        $rate = !empty($tax['enabled']) ? max(0, min(100, (float) ($tax['rate'] ?? 0))) : 0;
        $inclusive = (bool) ($tax['inclusive'] ?? false);
        $base = $subtotal;
        $taxAmount = round($inclusive ? $base - $base / (1 + $rate / 100) : $base * $rate / 100, 2);
        return ['subtotal' => $subtotal, 'fulfilment' => $fulfilment, 'charges' => $charges, 'charges_amount' => $amount,
            'tax_enabled' => $rate > 0, 'tax_label' => $tax['label'] ?? 'Tax', 'tax_rate' => $rate,
            'tax_inclusive' => $inclusive, 'tax_amount' => $taxAmount,
            'tax_breakdown' => \App\Services\Billing\TaxComponents::allocate((int) round($taxAmount * 100), $tax['components'] ?? []),
            'billing_company' => $settings['billing_company'] ?? null,
            'total' => round($subtotal + ($inclusive ? 0 : $taxAmount) + $amount, 2), 'currency' => $menu->currency, 'is_estimate' => true];
    }
}
