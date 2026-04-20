@if ($errors->any())
    <div class="rounded-xl px-4 py-3 bg-rose-500/10 border border-rose-500/30 text-rose-200 text-sm">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div>
    <label class="block text-[11px] uppercase tracking-wider text-white/50 font-bold mb-1.5">Name</label>
    <input type="text" name="name" value="{{ old('name', $item->name ?? '') }}"
           required maxlength="100" autocomplete="off"
           class="w-full bg-black/30 border border-white/15 rounded-lg px-3 py-2 text-sm text-white font-mono"
           placeholder="e.g. admin, support, login">
    <p class="text-[11px] text-white/40 mt-1.5">
        Letters, numbers, hyphens and underscores only. Matching is case-insensitive.
    </p>
</div>

<div>
    <label class="block text-[11px] uppercase tracking-wider text-white/50 font-bold mb-1.5">
        Note <span class="text-white/30 normal-case">(optional)</span>
    </label>
    <textarea name="note" rows="3" maxlength="500"
              class="w-full bg-black/30 border border-white/15 rounded-lg px-3 py-2 text-sm text-white"
              placeholder="Why this name is reserved (only shown to admins).">{{ old('note', $item->note ?? '') }}</textarea>
</div>
