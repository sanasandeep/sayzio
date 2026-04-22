@extends('admin.layouts.app')
@section('title', 'Cookie Consent')
@section('page-title', 'Cookie Consent')

@php
    use App\Modules\Common\Support\CookieConsentConfig;
    $cfg = $config;
    $catById = collect($cfg['categories'])->keyBy('id');
    $allCats = ['analytics', 'marketing', 'functional'];
    $catNames = ['analytics' => 'Analytics', 'marketing' => 'Marketing', 'functional' => 'Functional / Personalization'];
@endphp

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    @if(session('success'))
        <div class="rounded-xl px-4 py-3 text-sm" style="background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.20); color: #86efac;">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-xl px-4 py-3 text-sm" style="background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.20); color: #fca5a5;">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="glass rounded-2xl p-5 text-sm text-white/70">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-white font-semibold">Cookie consent banner</div>
                <div class="text-white/50 text-xs">Visible policy version: <span class="font-mono text-white">v{{ $cfg['policy_version'] }}</span> &mdash; bumping it re-prompts every visitor.</div>
            </div>
            <a target="_blank" href="/" class="px-3 py-1.5 rounded-lg text-xs font-medium" style="background: rgba(255,255,255,0.05); border: 1px solid var(--border-glass); color: var(--text-muted);">
                <i class="fas fa-external-link-alt mr-1"></i> Open marketing site
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.cookie-consent.update') }}" class="grid grid-cols-1 lg:grid-cols-[1fr,360px] gap-6" id="cookie-consent-form">
        @csrf
        @method('PUT')

        <div class="space-y-6">
            <div class="glass rounded-2xl p-6 space-y-4">
                <h2 class="text-base font-semibold text-white">Where it shows</h2>
                <label class="flex items-center gap-2 text-sm text-white/80">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" name="enabled" value="1" {{ $cfg['enabled'] ? 'checked' : '' }}> Enable cookie consent globally
                </label>
                <label class="flex items-center gap-2 text-sm text-white/80">
                    <input type="hidden" name="scope_marketing" value="0">
                    <input type="checkbox" name="scope_marketing" value="1" {{ $cfg['scope_marketing'] ? 'checked' : '' }}> Show on marketing site
                </label>
                <label class="flex items-center gap-2 text-sm text-white/80">
                    <input type="hidden" name="scope_biolink" value="0">
                    <input type="checkbox" name="scope_biolink" value="1" {{ $cfg['scope_biolink'] ? 'checked' : '' }}> Show on public biolink pages
                </label>
            </div>

            <div class="glass rounded-2xl p-6 space-y-4">
                <h2 class="text-base font-semibold text-white">Categories</h2>
                <p class="text-xs text-white/50">Essential cookies (session, CSRF, theme) are always on and never blocked. Toggle which optional categories visitors can opt into.</p>

                @foreach($allCats as $i => $cid)
                    @php $row = $catById[$cid] ?? ['id'=>$cid,'name'=>$catNames[$cid],'description'=>'','cookies'=>'','default_on'=>false]; @endphp
                    <div class="rounded-xl p-4" style="background:rgba(255,255,255,0.03); border:1px solid var(--border-glass);">
                        <input type="hidden" name="categories[{{ $i }}][id]" value="{{ $cid }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 space-y-2">
                                <input type="text" name="categories[{{ $i }}][name]" value="{{ $row['name'] }}" placeholder="{{ $catNames[$cid] }}"
                                    class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                                <textarea name="categories[{{ $i }}][description]" rows="2" placeholder="What this category does"
                                    class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">{{ $row['description'] }}</textarea>
                                <textarea name="categories[{{ $i }}][cookies]" rows="2" placeholder="Cookies / scripts in this category (comma separated)"
                                    class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-xs font-mono text-white">{{ $row['cookies'] }}</textarea>
                            </div>
                            <label class="text-xs text-white/70 flex items-center gap-2 whitespace-nowrap pt-2">
                                <input type="hidden" name="categories[{{ $i }}][default_on]" value="0">
                                <input type="checkbox" name="categories[{{ $i }}][default_on]" value="1" {{ !empty($row['default_on']) ? 'checked' : '' }}>
                                Default on
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="glass rounded-2xl p-6 space-y-4">
                <h2 class="text-base font-semibold text-white">Visitor copy</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <label class="block text-xs text-white/60">Title
                        <input id="cc_title" type="text" name="copy[title]" value="{{ $cfg['copy']['title'] }}" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                    </label>
                    <label class="block text-xs text-white/60">Cookie policy link label
                        <input id="cc_link_label" type="text" name="copy[policy_link_label]" value="{{ $cfg['copy']['policy_link_label'] }}" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                    </label>
                    <label class="md:col-span-2 block text-xs text-white/60">Body
                        <textarea id="cc_body" name="copy[body]" rows="3" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">{{ $cfg['copy']['body'] }}</textarea>
                    </label>
                    <label class="block text-xs text-white/60">Accept all label
                        <input id="cc_accept" type="text" name="copy[accept_all]" value="{{ $cfg['copy']['accept_all'] }}" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                    </label>
                    <label class="block text-xs text-white/60">Reject all label
                        <input id="cc_reject" type="text" name="copy[reject_all]" value="{{ $cfg['copy']['reject_all'] }}" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                    </label>
                    <label class="block text-xs text-white/60">Customize label
                        <input id="cc_customize" type="text" name="copy[customize]" value="{{ $cfg['copy']['customize'] }}" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                    </label>
                    <label class="block text-xs text-white/60">Save label
                        <input id="cc_save" type="text" name="copy[save]" value="{{ $cfg['copy']['save'] }}" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                    </label>
                    <label class="md:col-span-2 block text-xs text-white/60">Cookie policy URL
                        <input type="text" name="copy[policy_link_url]" value="{{ $cfg['copy']['policy_link_url'] }}" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white" placeholder="/cookies or https://...">
                    </label>
                </div>
            </div>

            <div class="glass rounded-2xl p-6 space-y-4">
                <h2 class="text-base font-semibold text-white">Appearance</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <label class="block text-xs text-white/60">Layout
                        <select id="cc_layout" name="layout" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                            @foreach(['modal'=>'Centered modal','banner'=>'Bottom banner','corner'=>'Corner card'] as $k=>$v)
                                <option value="{{ $k }}" {{ $cfg['layout']===$k ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-xs text-white/60">Position (banner/corner)
                        <select id="cc_position" name="position" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                            @foreach(['bottom-center'=>'Bottom centered','bottom-left'=>'Bottom left','bottom-right'=>'Bottom right','top-center'=>'Top centered'] as $k=>$v)
                                <option value="{{ $k }}" {{ $cfg['position']===$k ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-xs text-white/60">Theme
                        <select id="cc_theme" name="theme" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                            @foreach(['auto'=>'Auto','light'=>'Light','dark'=>'Dark'] as $k=>$v)
                                <option value="{{ $k }}" {{ $cfg['theme']===$k ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-xs text-white/60">Accent color
                        <input id="cc_accent" type="color" name="accent" value="{{ $cfg['accent'] }}" class="mt-1 w-full h-9 bg-white/5 border border-white/10 rounded-lg cursor-pointer">
                    </label>
                    <label class="md:col-span-2 flex items-center gap-2 text-sm text-white/80 mt-6">
                        <input type="hidden" name="show_reopen_button" value="0">
                        <input type="checkbox" name="show_reopen_button" value="1" {{ $cfg['show_reopen_button'] ? 'checked' : '' }}>
                        Show floating "manage cookies" reopen button after dismissal
                    </label>
                </div>
            </div>

            <div class="glass rounded-2xl p-6 space-y-4">
                <h2 class="text-base font-semibold text-white">Behavior</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <label class="block text-xs text-white/60">Remember choice for (days)
                        <input type="number" min="1" max="730" name="remember_days" value="{{ $cfg['remember_days'] }}" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                    </label>
                    <label class="block text-xs text-white/60">Geo scope
                        <select name="geo_scope" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white" id="cc_geo_scope">
                            <option value="all" {{ $cfg['geo_scope']==='all'?'selected':'' }}>Show to everyone</option>
                            <option value="eu"  {{ $cfg['geo_scope']==='eu' ?'selected':'' }}>EU/EEA + UK only</option>
                            <option value="custom" {{ $cfg['geo_scope']==='custom'?'selected':'' }}>Custom country list</option>
                        </select>
                    </label>
                    <label class="md:col-span-2 block text-xs text-white/60">Custom country codes (ISO-3166 alpha-2, comma separated)
                        <input type="text" name="geo_countries" value="{{ implode(', ', $cfg['geo_countries']) }}" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white font-mono" placeholder="e.g. DE, FR, IT">
                    </label>
                    <label class="flex items-center gap-2 text-sm text-white/80">
                        <input type="hidden" name="reprompt_on_change" value="0">
                        <input type="checkbox" name="reprompt_on_change" value="1" {{ $cfg['reprompt_on_change'] ? 'checked' : '' }}>
                        Re-prompt when policy version changes
                    </label>
                    <label class="flex items-center gap-2 text-sm text-white/80">
                        <input type="hidden" name="scroll_acceptance" value="0">
                        <input type="checkbox" name="scroll_acceptance" value="1" {{ $cfg['scroll_acceptance'] ? 'checked' : '' }}>
                        Treat scrolling as implicit acceptance
                    </label>
                    <label class="md:col-span-2 flex items-center gap-2 text-sm text-white/80">
                        <input type="hidden" name="block_until_consent" value="0">
                        <input type="checkbox" name="block_until_consent" value="1" {{ $cfg['block_until_consent'] ? 'checked' : '' }}>
                        Block non-essential scripts until consent is given
                    </label>
                    <label class="md:col-span-2 flex items-center gap-2 text-sm text-white/80">
                        <input type="hidden" name="bump_version" value="0">
                        <input type="checkbox" name="bump_version" value="1">
                        Bump policy version on save (re-prompt all visitors)
                    </label>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-6 py-2.5 bg-violet-600 text-white rounded-xl font-medium hover:bg-violet-700">
                    Save settings
                </button>
                <span class="text-xs text-white/40">Categories changes always trigger a version bump.</span>
            </div>
        </div>

        <div class="space-y-3">
            <div class="text-xs uppercase tracking-wider text-white/40 font-semibold">Live preview</div>
            <div id="cc_preview_wrap" class="rounded-2xl p-4 min-h-[420px] relative overflow-hidden" style="background:#0d1322; border:1px solid var(--border-glass);">
                <div class="absolute inset-0 opacity-30" style="background: radial-gradient(circle at top right, #312e81, transparent 60%);"></div>
                <div id="cc_preview" class="relative h-full"></div>
            </div>
        </div>
    </form>
</div>

<script>
(function() {
    const $ = (id) => document.getElementById(id);
    const preview = $('cc_preview');
    function build() {
        const layout = $('cc_layout').value;
        const position = $('cc_position').value;
        const theme = $('cc_theme').value;
        const accent = $('cc_accent').value;
        const dark = theme === 'dark' || (theme === 'auto');
        const bg = dark ? '#111827' : '#ffffff';
        const fg = dark ? '#f9fafb' : '#111827';
        const muted = dark ? '#9ca3af' : '#4b5563';
        const border = dark ? 'rgba(255,255,255,0.10)' : 'rgba(0,0,0,0.10)';
        const title = $('cc_title').value || 'We use cookies';
        const body = $('cc_body').value || '';
        const accept = $('cc_accept').value || 'Accept all';
        const reject = $('cc_reject').value || 'Reject all';
        const customize = $('cc_customize').value || 'Customize';
        const linkLabel = $('cc_link_label').value || 'Cookie policy';

        let style = '';
        if (layout === 'modal') {
            style = 'position:absolute; inset:auto; left:50%; top:50%; transform:translate(-50%,-50%); width:88%; max-width:420px;';
        } else if (layout === 'banner') {
            const top = position.startsWith('top');
            style = `position:absolute; left:12px; right:12px; ${top?'top:12px;':'bottom:12px;'}`;
        } else {
            // corner
            const top = position.startsWith('top');
            const right = position.endsWith('right');
            const left = position.endsWith('left');
            const center = position.endsWith('center');
            const horiz = center ? 'left:50%; transform:translateX(-50%);' : (right ? 'right:12px;' : 'left:12px;');
            style = `position:absolute; ${top?'top:12px;':'bottom:12px;'} ${horiz} max-width:340px;`;
        }

        preview.innerHTML = `
          <div style="${style} background:${bg}; color:${fg}; border:1px solid ${border}; border-radius:14px; box-shadow:0 18px 48px rgba(0,0,0,0.3); padding:18px 18px 14px; font-family:'Space Grotesk',sans-serif;">
            <div style="font-weight:600; font-size:15px; margin-bottom:6px;">${escapeHtml(title)}</div>
            <div style="font-size:12.5px; line-height:1.5; color:${muted}; margin-bottom:12px;">${escapeHtml(body)}
              <a href="#" style="color:${accent}; text-decoration:underline;">${escapeHtml(linkLabel)}</a>.
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
              <button type="button" style="background:${accent}; color:#fff; border:0; padding:8px 14px; border-radius:10px; font-size:12.5px; font-weight:600; cursor:pointer;">${escapeHtml(accept)}</button>
              <button type="button" style="background:transparent; color:${fg}; border:1px solid ${border}; padding:8px 14px; border-radius:10px; font-size:12.5px; font-weight:500; cursor:pointer;">${escapeHtml(reject)}</button>
              <button type="button" style="background:transparent; color:${muted}; border:0; padding:8px 8px; border-radius:10px; font-size:12.5px; font-weight:500; cursor:pointer;">${escapeHtml(customize)}</button>
            </div>
          </div>`;
    }
    function escapeHtml(s){return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
    document.querySelectorAll('#cookie-consent-form input, #cookie-consent-form select, #cookie-consent-form textarea').forEach(el => {
        el.addEventListener('input', build);
        el.addEventListener('change', build);
    });
    build();
})();
</script>
@endsection
