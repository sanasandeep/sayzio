@php
    $d = $dest ?? null;
    $type = old('type', $d?->type ?? 'email');
    $selSources = (array) old('sources', $d?->sources ?? []);
@endphp

<div>
    <label class="text-[10px] font-bold uppercase tracking-wider mb-1.5 block" style="color: var(--text-faint);">Label</label>
    <input type="text" name="label" required maxlength="120" value="{{ old('label', $d?->label) }}" placeholder="My ops inbox"
           class="w-full px-3 py-2 rounded-lg text-sm outline-none"
           style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
</div>

<div>
    <label class="text-[10px] font-bold uppercase tracking-wider mb-1.5 block" style="color: var(--text-faint);">Destination type</label>
    <select name="type" class="w-full px-3 py-2 rounded-lg text-sm outline-none"
            style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
        <option value="email"   @selected($type === 'email')>Email address</option>
        <option value="webhook" @selected($type === 'webhook')>Webhook URL</option>
    </select>
</div>

<div class="md:col-span-2">
    <label class="text-[10px] font-bold uppercase tracking-wider mb-1.5 block" style="color: var(--text-faint);">Target (email or https URL)</label>
    <input type="text" name="target" required maxlength="500" value="{{ old('target', $d?->target) }}"
           placeholder="ops@example.com  or  https://example.com/hook"
           class="w-full px-3 py-2 rounded-lg text-sm outline-none"
           style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
</div>

<div>
    <label class="text-[10px] font-bold uppercase tracking-wider mb-1.5 block" style="color: var(--text-faint);">Webhook method</label>
    <select name="method" class="w-full px-3 py-2 rounded-lg text-sm outline-none"
            style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
        @foreach(['POST','PUT','GET'] as $m)
            <option value="{{ $m }}" @selected(old('method', $d?->method ?? 'POST') === $m)>{{ $m }}</option>
        @endforeach
    </select>
</div>

<div>
    <label class="text-[10px] font-bold uppercase tracking-wider mb-1.5 block" style="color: var(--text-faint);">HMAC secret (webhook only)</label>
    <input type="text" name="secret" maxlength="120" value="{{ old('secret', $d?->secret) }}"
           placeholder="Optional — sent as X-1INME-Signature: sha256=…"
           class="w-full px-3 py-2 rounded-lg text-sm outline-none"
           style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
</div>

<div>
    <label class="text-[10px] font-bold uppercase tracking-wider mb-1.5 block" style="color: var(--text-faint);">Custom header name</label>
    <input type="text" name="header_key" maxlength="120" value="{{ old('header_key', $d?->header_key) }}"
           placeholder="X-Auth-Token"
           class="w-full px-3 py-2 rounded-lg text-sm outline-none"
           style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
</div>

<div>
    <label class="text-[10px] font-bold uppercase tracking-wider mb-1.5 block" style="color: var(--text-faint);">Custom header value</label>
    <input type="text" name="header_value" maxlength="500" value="{{ old('header_value', $d?->header_value) }}"
           class="w-full px-3 py-2 rounded-lg text-sm outline-none"
           style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
</div>

<div class="md:col-span-2">
    <label class="text-[10px] font-bold uppercase tracking-wider mb-1.5 block" style="color: var(--text-faint);">
        Forward only these sources <span style="color: var(--text-muted);">(leave all unchecked to forward every source)</span>
    </label>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-1.5">
        @foreach($sourceLabels as $val => $label)
            <label class="flex items-center gap-2 text-xs px-2 py-1.5 rounded-lg" style="background: var(--bg-glass-input); color: var(--text-secondary);">
                <input type="checkbox" name="sources[]" value="{{ $val }}" @checked(in_array($val, $selSources, true))>
                {{ $label }}
            </label>
        @endforeach
    </div>
</div>

<div class="md:col-span-2">
    <label class="flex items-center gap-2 text-xs" style="color: var(--text-secondary);">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $d?->is_active ?? true))>
        Rule is active
    </label>
</div>
