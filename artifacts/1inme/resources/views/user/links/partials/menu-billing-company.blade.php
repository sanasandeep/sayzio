<div class="rm-row">
    <label class="rm-label">Billing company</label>
    <select class="rm-input" x-model="billingCompanyId" @change="saveSettings()">
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
