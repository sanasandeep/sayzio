@extends('user.layouts.app')
@section('title', 'Embed · ' . $form->title)

@section('content')
<div class="max-w-5xl mx-auto" x-data="{ tab: 'iframe', copied: '' }">
    @include('user.partials.page-hero', [
        'title' => 'Share &amp; Embed',
        'subtitle' => 'Use anywhere — share the public link, embed an iframe, drop a script tag, or add to a Link in Bio page.',
        'icon' => 'fa-code',
        'back' => route('user.forms.show', $form),
        'url' => $form->getPublicUrl(),
    ])

    @include('user.forms._tabs')

    <div class="card-premium p-1 mb-6 inline-flex">
        @foreach(['iframe' => ['Iframe', 'fa-window-maximize'], 'script' => ['Script tag', 'fa-code'], 'link' => ['Direct link', 'fa-link'], 'biolink' => ['Link in Bio block', 'fa-th-large']] as $key => [$label, $icon])
            <button @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'bg-violet-500 text-white' : ''" class="px-4 py-2 rounded-lg text-xs font-semibold" style="color: var(--text-muted);">
                <i class="fas {{ $icon }} text-[10px] mr-1"></i> {{ $label }}
            </button>
        @endforeach
    </div>

    @php $iframe = $form->getIframeEmbed(); $script = $form->getEmbedScript(); $link = $form->getPublicUrl(); @endphp

    <div x-show="tab === 'iframe'" class="card-premium p-6">
        <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Iframe embed</h3>
        <p class="text-[11px] mb-4" style="color: var(--text-faint);">Easiest way to embed in any website. Paste this HTML wherever the form should appear.</p>
        <div class="relative">
            <pre class="p-4 rounded-xl text-xs overflow-x-auto" style="background: #0a0b10; color: #d1d5db; border: 1px solid var(--border-glass);"><code id="iframe-code">{{ $iframe }}</code></pre>
            <button type="button" @click="navigator.clipboard.writeText(document.getElementById('iframe-code').textContent); copied='iframe'; setTimeout(()=>copied='',1800)" class="absolute top-2 right-2 px-3 py-1.5 rounded-lg text-[11px] font-semibold" style="background: rgba(139,92,246,0.2); color: #a78bfa;">
                <span x-show="copied !== 'iframe'"><i class="fas fa-copy text-[10px] mr-1"></i> Copy</span>
                <span x-show="copied === 'iframe'" style="color: #10b981;"><i class="fas fa-check text-[10px] mr-1"></i> Copied!</span>
            </button>
        </div>
        <div class="mt-6">
            <h4 class="text-xs font-bold uppercase tracking-wider mb-2" style="color: var(--text-faint);">Live preview</h4>
            <div class="rounded-xl overflow-hidden" style="border: 1px solid var(--border-glass);">{!! $iframe !!}</div>
        </div>
    </div>

    <div x-show="tab === 'script'" class="card-premium p-6">
        <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Script tag (auto-resizing)</h3>
        <p class="text-[11px] mb-4" style="color: var(--text-faint);">Lightweight loader that injects an iframe into a target div. Great for SPAs and dynamic pages.</p>
        <div class="relative">
            <pre class="p-4 rounded-xl text-xs overflow-x-auto whitespace-pre-wrap" style="background: #0a0b10; color: #d1d5db; border: 1px solid var(--border-glass);"><code id="script-code">{{ $script }}</code></pre>
            <button type="button" @click="navigator.clipboard.writeText(document.getElementById('script-code').textContent); copied='script'; setTimeout(()=>copied='',1800)" class="absolute top-2 right-2 px-3 py-1.5 rounded-lg text-[11px] font-semibold" style="background: rgba(139,92,246,0.2); color: #a78bfa;">
                <span x-show="copied !== 'script'"><i class="fas fa-copy text-[10px] mr-1"></i> Copy</span>
                <span x-show="copied === 'script'" style="color: #10b981;"><i class="fas fa-check text-[10px] mr-1"></i> Copied!</span>
            </button>
        </div>
    </div>

    <div x-show="tab === 'link'" class="card-premium p-6">
        <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Direct shareable link</h3>
        <p class="text-[11px] mb-4" style="color: var(--text-faint);">Send this link in an email, message, or social post — anywhere clickable.</p>
        <div class="flex items-center gap-2">
            <input type="text" readonly value="{{ $link }}" class="theme-input flex-1 text-sm font-mono" onclick="this.select()">
            <button type="button" @click="navigator.clipboard.writeText('{{ $link }}'); copied='link'; setTimeout(()=>copied='',1800)" class="btn-primary text-xs px-4 py-2.5">
                <span x-show="copied !== 'link'"><i class="fas fa-copy text-[10px] mr-1"></i> Copy</span>
                <span x-show="copied === 'link'"><i class="fas fa-check text-[10px] mr-1"></i> Copied!</span>
            </button>
            <a href="{{ $link }}" target="_blank" class="text-xs px-4 py-2.5 rounded-lg" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);"><i class="fas fa-external-link-alt text-[10px] mr-1"></i> Open</a>
        </div>
    </div>

    <div x-show="tab === 'biolink'" class="card-premium p-6">
        <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Add to a biolink</h3>
        <p class="text-[11px] mb-4" style="color: var(--text-faint);">From any biolink editor, add a "Form" block and select <strong>{{ $form->title }}</strong> from the picker.</p>
        <div class="rounded-xl p-5" style="background: linear-gradient(160deg, rgba(139,92,246,0.08), rgba(236,72,153,0.06)); border: 1px solid rgba(139,92,246,0.2);">
            <ol class="text-sm space-y-2" style="color: var(--text-secondary);">
                <li class="flex items-start gap-3"><span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0" style="background: rgba(139,92,246,0.2); color: #a78bfa;">1</span> Open any biolink and click <strong>Edit Blocks</strong>.</li>
                <li class="flex items-start gap-3"><span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0" style="background: rgba(139,92,246,0.2); color: #a78bfa;">2</span> Add a new block of type <strong>Form</strong>.</li>
                <li class="flex items-start gap-3"><span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0" style="background: rgba(139,92,246,0.2); color: #a78bfa;">3</span> In the block settings, select form ID <code class="px-1.5 py-0.5 rounded text-xs" style="background: rgba(255,255,255,0.06);">{{ $form->id }}</code> ({{ $form->title }}).</li>
            </ol>
        </div>
    </div>
</div>
@endsection
