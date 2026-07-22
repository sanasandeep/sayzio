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
        <label class="block text-[11px] uppercase tracking-wider text-white/50 font-bold mb-1.5 ak-muted">Name</label>
        <input type="text" name="name" value="{{ old('name', $template->name ?? '') }}"
               required maxlength="100"
               class="w-full bg-black/30 border border-white/15 rounded-lg px-3 py-2 text-sm text-white ak-strong"
               placeholder="e.g. Aurora Drift">
    </div>
    <div>
        <label class="block text-[11px] uppercase tracking-wider text-white/50 font-bold mb-1.5 ak-muted">Slug</label>
        <input type="text" name="slug" value="{{ old('slug', $template->slug ?? '') }}"
               required maxlength="100" pattern="[a-z0-9][a-z0-9\-]*"
               class="w-full bg-black/30 border border-white/15 rounded-lg px-3 py-2 text-sm text-white font-mono ak-strong"
               placeholder="aurora-drift">
        <p class="text-[11px] text-white/40 mt-1.5 ak-note">
            Used in the CSS selector <code class="text-white/60 ak-muted">.bg-template-&lt;slug&gt;</code>. Lowercase, dashes only.
        </p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
        <label class="block text-[11px] uppercase tracking-wider text-white/50 font-bold mb-1.5 ak-muted">Category</label>
        <select name="category" required
                class="w-full bg-black/30 border border-white/15 rounded-lg px-3 py-2 text-sm text-white ak-strong">
            @foreach($categories as $cat)
                <option value="{{ $cat }}" @selected(old('category', $template->category ?? 'pattern') === $cat)>{{ ucfirst($cat) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-[11px] uppercase tracking-wider text-white/50 font-bold mb-1.5 ak-muted">Sort order</label>
        <input type="number" name="sort_order" value="{{ old('sort_order', $template->sort_order ?? 0) }}"
               class="w-full bg-black/30 border border-white/15 rounded-lg px-3 py-2 text-sm text-white ak-strong">
    </div>
    <div>
        <label class="block text-[11px] uppercase tracking-wider text-white/50 font-bold mb-1.5 ak-muted">Status</label>
        <label class="flex items-center gap-2 cursor-pointer h-[42px] px-3 rounded-lg bg-black/30 border border-white/15">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', $template->is_active ?? true))
                   class="rounded">
            <span class="text-sm text-white/80 ak-strong">Active &amp; visible to users</span>
        </label>
    </div>
</div>

<div>
    <label class="block text-[11px] uppercase tracking-wider text-white/50 font-bold mb-1.5 ak-muted">
        Preview swatch <span class="text-white/30 normal-case ak-note">(any CSS background value)</span>
    </label>
    <input type="text" name="preview_color" value="{{ old('preview_color', $template->preview_color ?? '') }}"
           class="w-full bg-black/30 border border-white/15 rounded-lg px-3 py-2 text-sm text-white font-mono ak-strong"
           placeholder="linear-gradient(135deg, #1a0533, #0d1b2a)">
    <p class="text-[11px] text-white/40 mt-1.5 ak-note">
        Used as a fallback fill behind the live CSS preview swatch in the user picker.
    </p>
</div>

<div>
    <label class="block text-[11px] uppercase tracking-wider text-white/50 font-bold mb-1.5 ak-muted">CSS</label>
    <textarea name="css" rows="14" required
              class="w-full bg-black/40 border border-white/15 rounded-lg px-3 py-2 text-xs text-white font-mono leading-relaxed ak-strong"
              placeholder=".bg-template-my-slug { position:fixed; inset:0; z-index:-1; background: ...; }">{{ old('css', $template->css ?? '') }}</textarea>
    <p class="text-[11px] text-white/40 mt-1.5 ak-note">
        Scope every rule under <code class="text-white/60 ak-muted">.bg-template-&lt;slug&gt;</code> and keep the wrapper at
        <code class="text-white/60 ak-muted">position:fixed; inset:0; z-index:-1</code> so it sits behind biolink content.
    </p>
</div>

<div>
    <label class="block text-[11px] uppercase tracking-wider text-white/50 font-bold mb-1.5 ak-muted">
        JavaScript <span class="text-white/30 normal-case ak-note">(optional, runs after the layer mounts)</span>
    </label>
    <textarea name="js" rows="6"
              class="w-full bg-black/40 border border-white/15 rounded-lg px-3 py-2 text-xs text-white font-mono leading-relaxed ak-strong"
              placeholder="// var container = …; for (var i = 0; i < 40; i++) { … }">{{ old('js', $template->js ?? '') }}</textarea>
    <p class="text-[11px] text-white/40 mt-1.5 ak-note">
        Runs inside an IIFE after page load. <code class="text-white/60 ak-muted">container</code> is the
        <code class="text-white/60 ak-muted">.bg-template-&lt;slug&gt;</code> element.
    </p>
</div>

@if(!empty($template->slug))
<div>
    <label class="block text-[11px] uppercase tracking-wider text-white/50 font-bold mb-1.5 ak-muted">Live preview</label>
    <style>{!! str_replace(['.bg-template-','position:fixed','position: fixed','z-index:-1','z-index: -1'], ['.bg-thumb-','position:absolute','position:absolute','z-index:0','z-index:0'], $template->css) !!}</style>
    <div class="rounded-xl overflow-hidden relative border border-white/10 mx-auto"
         style="width: 220px; aspect-ratio: 9/16; background: {{ $template->preview_color }};">
        <div class="bg-thumb-{{ $template->slug }}" style="position:absolute;inset:0;"></div>
    </div>
</div>
@endif
