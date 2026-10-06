@php
    $componentValues = collect(old(($componentPrefix ?? '') . 'components', $ruleComponents ?? []))->map(fn ($c) => ['name' => $c['name'], 'rate_percent' => $c['rate_percent'] ?? (($c['rate_bps'] ?? 0) / 100)])->values();
@endphp
<div class="mt-3" x-data="{ parts: @js($componentValues) }">
    <p class="text-xs" style="color:var(--text-muted)">Optional components share the same taxable base and inclusive/exclusive setting. Their percentages replace the single rate above.</p>
    <template x-for="(part, idx) in parts" :key="idx">
        <div class="flex gap-2 mt-2">
            <input :name="@js(($componentPrefix ?? '') === 'new_tax.' ? 'new_tax[components]' : 'components')+'['+idx+'][name]'" x-model="part.name" required maxlength="64" aria-label="Tax component name" placeholder="CGST / SGST / VAT" class="w-1/2 p-2 rounded border" style="background:var(--bg-glass-input);color:var(--text-primary)">
            <input :name="@js(($componentPrefix ?? '') === 'new_tax.' ? 'new_tax[components]' : 'components')+'['+idx+'][rate_percent]'" x-model="part.rate_percent" required type="number" min="0" max="100" step="0.01" aria-label="Component rate percent" placeholder="Rate %" class="w-1/3 p-2 rounded border" style="background:var(--bg-glass-input);color:var(--text-primary)">
            <button type="button" @click="parts.splice(idx,1)" class="px-3 py-2 rounded-lg border text-rose-600" style="border-color:var(--border-soft)" aria-label="Remove component">×</button>
        </div>
    </template>
    <p class="text-xs mt-2" x-show="parts.length" x-text="'Combined rate: ' + parts.reduce((sum, part) => sum + Number(part.rate_percent || 0), 0).toFixed(2) + '%'"></p>
    <input type="hidden" name="{{ ($componentPrefix ?? '') === 'new_tax.' ? 'new_tax[components]' : 'components' }}" value="" :disabled="parts.length > 0">
    <button type="button" @click="if(parts.length < 8) parts.push({name:'',rate_percent:0})" class="text-sm mt-3 px-4 py-2 rounded-lg border" style="border-color:var(--border-soft);color:var(--text-primary)">+ Tax component</button>
</div>
