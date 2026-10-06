<div class="rm-row">
    <label class="rm-label">Billing company</label>
    <select class="rm-input" x-model="billingCompanyId" @change="billingTaxRuleId = ''; saveSettings()">
        <option value="">No linked company — use menu settings</option>
        @foreach($billingCompanies as $menuCompany)
            <option value="{{ $menuCompany->id }}">{{ $menuCompany->name }} — {{ $menuCompany->defaultTaxRule?->is_active ? $menuCompany->defaultTaxRule->name.' ('.$menuCompany->defaultTaxRule->ratePercent().'%)' : 'No tax' }}</option>
        @endforeach
    </select>
    <p class="text-xs mt-2" style="color:var(--text-muted)">The selected company's default tax rule and billing identity are copied when settings are saved. A company with no active default rule adds no tax. Existing orders retain their saved details.</p>
    <a class="text-sm" href="{{ route('user.billing.companies.index') }}" target="_blank" rel="noopener">Manage billing companies and tax rules</a>
    <a class="text-sm ml-3" href="{{ route('user.billing.companies.create') }}" target="_blank" rel="noopener">+ Create company</a>
    @if($billingCompanies->isEmpty())
        <p class="text-sm mt-2">Create a billing company first, then reload this editor.</p>
    @endif
</div>

@php
    $menuTaxProfiles = $billingCompanies->flatMap(fn ($company) => $company->taxRules->where('is_active', true)->map(fn ($rule) => ['id' => $rule->id, 'company_id' => $company->id, 'label' => $rule->name.' ('.$rule->ratePercent().'%'.($rule->inclusive ? ', inclusive' : ', exclusive').')']))->values();
@endphp
<div class="rm-row" x-show="billingCompanyId" x-data="{ profiles: @js($menuTaxProfiles) }">
    <label class="rm-label">Tax profile</label>
    <select class="rm-input" x-model="billingTaxRuleId" @change="saveSettings()">
        <option value="">Company default</option>
        <template x-for="profile in profiles.filter(p => String(p.company_id) === String(billingCompanyId))" :key="profile.id">
            <option :value="profile.id" x-text="profile.label"></option>
        </template>
    </select>
</div>
