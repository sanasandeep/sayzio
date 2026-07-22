@if (isset($errors) && $errors && $errors->any())
    <div class="rounded-xl px-4 py-3 bg-rose-500/10 border border-rose-500/30 text-rose-200 text-sm ak-red">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-[11px] uppercase tracking-wider text-white/50 font-bold mb-1.5 ak-muted">
            Hashtag <span class="text-white/30 normal-case ak-note">(without #)</span>
        </label>
        <input type="text" name="tag" value="{{ old('tag', $hashtag->tag ?? '') }}"
               required maxlength="60" pattern="[a-zA-Z0-9_-]+"
               class="w-full bg-black/30 border border-white/15 rounded-lg px-3 py-2 text-sm text-white font-mono ak-strong"
               placeholder="live-music">
    </div>
    <div>
        <label class="block text-[11px] uppercase tracking-wider text-white/50 font-bold mb-1.5 ak-muted">Sort order</label>
        <input type="number" name="sort_order" value="{{ old('sort_order', $hashtag->sort_order ?? 0) }}"
               class="w-full bg-black/30 border border-white/15 rounded-lg px-3 py-2 text-sm text-white ak-strong">
        <p class="text-[11px] text-white/40 mt-1.5 ak-note">Lower numbers show first, ahead of auto-trending tags.</p>
    </div>
</div>
