@extends('admin.layouts.app')
@section('title', 'Cookie Consent')
@section('page-title', 'Cookie Consent')

@php
    use App\Modules\Common\Support\CookieConsentConfig;
    $cfg = $config;
    $catById = collect($cfg['categories'])->keyBy('id');
    $allCats = ['analytics', 'marketing', 'functional'];
    $catNames = ['analytics' => 'Analytics', 'marketing' => 'Marketing', 'functional' => 'Functional / Personalization'];
    $layoutLabels = [
        'modal'    => 'Centered modal',
        'banner'   => 'Bottom/top banner',
        'corner'   => 'Corner card',
        'inline'   => 'Inline bar (slim)',
        'pill'     => 'Floating pill',
        'takeover' => 'Full-screen takeover',
    ];
    $positionLabels = [
        'bottom-center' => 'Bottom center',
        'bottom-left'   => 'Bottom left',
        'bottom-right'  => 'Bottom right',
        'top-center'    => 'Top center',
        'top-left'      => 'Top left',
        'top-right'     => 'Top right',
        'middle-left'   => 'Middle left',
        'middle-right'  => 'Middle right',
    ];
    $btnLabels = ['primary' => 'Primary (Accept)', 'secondary' => 'Secondary (Reject)', 'tertiary' => 'Tertiary (Customize/Save)'];
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

    <form method="POST" action="{{ route('admin.cookie-consent.update') }}" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-[1fr,360px] gap-6" id="cookie-consent-form">
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
                    <label class="block text-xs text-white/60">Footer reopen link label
                        <input type="text" name="copy[reopen_link_label]" value="{{ $cfg['copy']['reopen_link_label'] }}" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white" placeholder="Cookie preferences">
                    </label>
                    <label class="flex items-end gap-2 text-sm text-white/80">
                        <input type="hidden" name="show_policy_link" value="0">
                        <input type="checkbox" name="show_policy_link" value="1" {{ $cfg['show_policy_link'] ? 'checked' : '' }}>
                        Show "{{ $cfg['copy']['policy_link_label'] }}" link in prompt
                    </label>
                </div>
            </div>

            <div class="glass rounded-2xl p-6 space-y-4">
                <h2 class="text-base font-semibold text-white">Per-language copy</h2>
                <p class="text-xs text-white/50">
                    Add translated versions of the prompt copy. Visitors are matched to the closest language from their browser's
                    <span class="font-mono text-white/70">Accept-Language</span> header (e.g. <span class="font-mono text-white/70">fr-CA</span> falls back to <span class="font-mono text-white/70">fr</span>). Any field left blank uses the default copy above. Use BCP-47 codes like
                    <span class="font-mono text-white/70">fr</span>, <span class="font-mono text-white/70">es</span>, <span class="font-mono text-white/70">pt-BR</span>, <span class="font-mono text-white/70">zh-CN</span>.
                </p>

                <div id="cc_locales" class="space-y-3"></div>

                <div class="flex items-center gap-3">
                    <button type="button" id="cc_locale_add"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium"
                        style="background: rgba(61,107,255,0.15); border: 1px solid rgba(61,107,255,0.35); color: #bccfff;">
                        <i class="fas fa-plus mr-1"></i> Add language
                    </button>
                    <span class="text-[11px] text-white/40">Up to 50 languages.</span>
                </div>

                <template id="cc_locale_row_tpl">
                    <div class="cc-locale-row rounded-xl p-4" style="background:rgba(255,255,255,0.03); border:1px solid var(--border-glass);">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <label class="block text-xs text-white/60 flex-1 max-w-[240px]">Language code (BCP-47)
                                <input type="text" data-cc-locale-code value="" placeholder="fr or pt-BR"
                                    class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white font-mono"
                                    pattern="[A-Za-z]{2,3}([-_][A-Za-z]{2,4})?">
                            </label>
                            <button type="button" data-cc-locale-remove class="text-xs text-red-300 hover:text-red-200 px-2 py-1">
                                <i class="fas fa-trash"></i> Remove
                            </button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <label class="block text-xs text-white/60">Title
                                <input type="text" data-cc-loc="title" class="cc-loc-field mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                            </label>
                            <label class="block text-xs text-white/60">Cookie policy link label
                                <input type="text" data-cc-loc="policy_link_label" class="cc-loc-field mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                            </label>
                            <label class="md:col-span-2 block text-xs text-white/60">Body
                                <textarea rows="2" data-cc-loc="body" class="cc-loc-field mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white"></textarea>
                            </label>
                            <label class="block text-xs text-white/60">Accept all label
                                <input type="text" data-cc-loc="accept_all" class="cc-loc-field mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                            </label>
                            <label class="block text-xs text-white/60">Reject all label
                                <input type="text" data-cc-loc="reject_all" class="cc-loc-field mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                            </label>
                            <label class="block text-xs text-white/60">Customize label
                                <input type="text" data-cc-loc="customize" class="cc-loc-field mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                            </label>
                            <label class="block text-xs text-white/60">Save label
                                <input type="text" data-cc-loc="save" class="cc-loc-field mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                            </label>
                            <label class="md:col-span-2 block text-xs text-white/60">Footer reopen link label
                                <input type="text" data-cc-loc="reopen_link_label" class="cc-loc-field mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                            </label>
                        </div>
                    </div>
                </template>
            </div>

            <div class="glass rounded-2xl p-6 space-y-4">
                <h2 class="text-base font-semibold text-white">Layout &amp; position</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <label class="block text-xs text-white/60">Layout
                        <select id="cc_layout" name="layout" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                            @foreach($layoutLabels as $k=>$v)
                                <option value="{{ $k }}" {{ $cfg['layout']===$k ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-xs text-white/60" id="cc_position_wrap">Position
                        <select id="cc_position" name="position" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                            @foreach($positionLabels as $k=>$v)
                                <option value="{{ $k }}" {{ $cfg['position']===$k ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                        <span class="text-[10px] text-white/40 mt-1 block">Applies to banner / corner / pill layouts.</span>
                    </label>
                    <label class="block text-xs text-white/60">Size
                        <select id="cc_size" name="size" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                            @foreach(['compact'=>'Compact','default'=>'Default','wide'=>'Wide'] as $k=>$v)
                                <option value="{{ $k }}" {{ $cfg['size']===$k ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-xs text-white/60">Max width (px)
                        <input id="cc_maxw" type="number" min="280" max="960" name="max_width" value="{{ $cfg['max_width'] }}" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                    </label>
                    <label class="block text-xs text-white/60">Corner radius (px)
                        <input id="cc_radius" type="number" min="0" max="40" name="radius" value="{{ $cfg['radius'] }}" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                    </label>
                    <label class="block text-xs text-white/60">Theme
                        <select id="cc_theme" name="theme" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                            @foreach(['auto'=>'Auto','light'=>'Light','dark'=>'Dark'] as $k=>$v)
                                <option value="{{ $k }}" {{ $cfg['theme']===$k ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="border-t border-white/5 pt-4">
                    <div class="text-xs uppercase tracking-wider text-white/40 font-semibold mb-2">Per-surface override</div>
                    <p class="text-[11px] text-white/40 mb-3">Leave on "Inherit" to use the global layout/position above. Useful if you want, e.g. a slim inline bar on biolinks but a centered modal on the marketing site.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach(['site' => 'Marketing site', 'biolink' => 'Public biolinks'] as $sk => $sl)
                            <div class="rounded-lg p-3" style="background:rgba(255,255,255,0.03); border:1px solid var(--border-glass);">
                                <div class="text-xs font-semibold text-white/80 mb-2">{{ $sl }}</div>
                                <label class="block text-[11px] text-white/50 mb-2">Layout
                                    <select name="surface_overrides[{{ $sk }}][layout]" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-2 py-1.5 text-xs text-white">
                                        <option value="">Inherit</option>
                                        @foreach($layoutLabels as $k=>$v)
                                            <option value="{{ $k }}" {{ ($cfg['surface_overrides'][$sk]['layout'] ?? '')===$k ? 'selected' : '' }}>{{ $v }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block text-[11px] text-white/50">Position
                                    <select name="surface_overrides[{{ $sk }}][position]" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-2 py-1.5 text-xs text-white">
                                        <option value="">Inherit</option>
                                        @foreach($positionLabels as $k=>$v)
                                            <option value="{{ $k }}" {{ ($cfg['surface_overrides'][$sk]['position'] ?? '')===$k ? 'selected' : '' }}>{{ $v }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="glass rounded-2xl p-6 space-y-4">
                <h2 class="text-base font-semibold text-white">Buttons</h2>
                <p class="text-xs text-white/50">Style each button role independently. "Link" style strips the background and shows the text only.</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    @foreach(['primary','secondary','tertiary'] as $role)
                        @php $b = $cfg['buttons'][$role]; @endphp
                        <div class="rounded-lg p-3" style="background:rgba(255,255,255,0.03); border:1px solid var(--border-glass);">
                            <div class="text-xs font-semibold text-white/80 mb-2">{{ $btnLabels[$role] }}</div>
                            <label class="block text-[11px] text-white/50 mb-2">Background
                                <input id="cc_btn_{{ $role }}_bg" type="color" name="buttons[{{ $role }}][bg]" value="{{ $b['bg'] }}" class="mt-1 w-full h-8 bg-white/5 border border-white/10 rounded cursor-pointer">
                            </label>
                            <label class="block text-[11px] text-white/50 mb-2">Text
                                <input id="cc_btn_{{ $role }}_text" type="color" name="buttons[{{ $role }}][text]" value="{{ $b['text'] }}" class="mt-1 w-full h-8 bg-white/5 border border-white/10 rounded cursor-pointer">
                            </label>
                            <label class="block text-[11px] text-white/50">Style
                                <select id="cc_btn_{{ $role }}_style" name="buttons[{{ $role }}][style]" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-2 py-1.5 text-xs text-white">
                                    @foreach(['solid'=>'Solid','outline'=>'Outline','link'=>'Link'] as $k=>$v)
                                        <option value="{{ $k }}" {{ $b['style']===$k ? 'selected' : '' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    @endforeach
                </div>
                <label class="block text-xs text-white/60 max-w-[200px]">Accent (links / switches)
                    <input id="cc_accent" type="color" name="accent" value="{{ $cfg['accent'] }}" class="mt-1 w-full h-9 bg-white/5 border border-white/10 rounded-lg cursor-pointer">
                </label>
            </div>

            <div class="glass rounded-2xl p-6 space-y-4">
                <h2 class="text-base font-semibold text-white">Backdrop &amp; animation</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <label class="flex items-center gap-2 text-sm text-white/80 mt-5">
                        <input type="hidden" name="backdrop[show]" value="0">
                        <input id="cc_bd_show" type="checkbox" name="backdrop[show]" value="1" {{ $cfg['backdrop']['show'] ? 'checked' : '' }}>
                        Show backdrop (modal / takeover)
                    </label>
                    <label class="block text-xs text-white/60">Backdrop dim (%)
                        <input id="cc_bd_dim" type="number" min="0" max="100" name="backdrop[dim]" value="{{ $cfg['backdrop']['dim'] }}" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                    </label>
                    <label class="flex items-center gap-2 text-sm text-white/80 mt-5">
                        <input type="hidden" name="backdrop[blur]" value="0">
                        <input id="cc_bd_blur" type="checkbox" name="backdrop[blur]" value="1" {{ $cfg['backdrop']['blur'] ? 'checked' : '' }}>
                        Blur the backdrop
                    </label>
                    <label class="block text-xs text-white/60">Animation
                        <select id="cc_anim" name="animation" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                            @foreach(['none'=>'None','fade'=>'Fade','slide-up'=>'Slide up','slide-down'=>'Slide down'] as $k=>$v)
                                <option value="{{ $k }}" {{ $cfg['animation']===$k ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-xs text-white/60">Entrance delay (s)
                        <input type="number" min="0" max="30" name="entrance_delay" value="{{ $cfg['entrance_delay'] }}" class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                    </label>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2 border-t border-white/5">
                    <label class="flex items-center gap-2 text-sm text-white/80">
                        <input type="hidden" name="header_logo_enabled" value="0">
                        <input id="cc_logo_on" type="checkbox" name="header_logo_enabled" value="1" {{ $cfg['header_logo_enabled'] ? 'checked' : '' }}>
                        Show small logo / icon in prompt header
                    </label>
                    <div class="space-y-2">
                        <div class="text-xs text-white/60">Logo image</div>
                        <input type="hidden" name="remove_header_logo" id="cc_logo_remove" value="0">
                        @if(!empty($cfg['header_logo_url']))
                            <div id="cc_logo_preview" class="rounded-lg p-2 flex items-center gap-2" style="background:rgba(255,255,255,0.04); border:1px solid var(--border-glass);">
                                <img src="{{ $cfg['header_logo_url'] }}" alt="Current header logo" class="h-8 w-8 object-contain rounded">
                                <span class="text-[11px] text-white/50 truncate flex-1">{{ $cfg['header_logo_url'] }}</span>
                                <button type="button" id="cc_logo_remove_btn"
                                        class="px-2 py-1 rounded-md text-[11px] font-medium whitespace-nowrap"
                                        style="background: rgba(239,68,68,0.10); border: 1px solid rgba(239,68,68,0.30); color: #fca5a5;">
                                    <i class="fas fa-times mr-1"></i>Remove
                                </button>
                            </div>
                        @endif
                        <input id="cc_logo_file" type="file" name="header_logo_file" accept="image/png,image/jpeg,image/webp,image/svg+xml"
                               class="block w-full text-xs text-white/70 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-blue-600 file:text-white hover:file:bg-blue-700 file:cursor-pointer">
                        @error('header_logo_file')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
                        <label class="block text-[11px] text-white/40">Or paste a URL / path
                            <input id="cc_logo_url" type="text" name="header_logo_url" value="{{ $cfg['header_logo_url'] }}" placeholder="/img/logo.png or https://..." class="mt-1 w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-xs text-white">
                        </label>
                        <p id="cc_logo_remove_hint" class="text-[11px] text-amber-300/80 hidden">
                            Logo will be removed when you save.
                        </p>
                    </div>
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

            <div class="glass rounded-2xl p-6 space-y-3">
                <h2 class="text-base font-semibold text-white">Reopen link</h2>
                <p class="text-xs text-white/50">A "{{ $cfg['copy']['reopen_link_label'] }}" text link is added to the marketing site footer and the public biolink footer area whenever consent is enabled for that surface. Visitors click it to reopen this prompt. The previous floating cookie icon has been retired.</p>
                {{-- show_reopen_button is hard-pinned to false; the field is retained in the schema for back-compat with old payloads. --}}
                <input type="hidden" name="show_reopen_button" value="0">
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700">
                    Save settings
                </button>
                <span class="text-xs text-white/40">Categories changes always trigger a version bump.</span>
            </div>
        </div>

        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <div class="text-xs uppercase tracking-wider text-white/40 font-semibold">Live preview</div>
                <div class="flex items-center gap-1 text-[11px]">
                    <button type="button" data-cc-surface="site"    class="cc-surface-tab px-2 py-1 rounded-md bg-blue-600/30 text-blue-200">Site</button>
                    <button type="button" data-cc-surface="biolink" class="cc-surface-tab px-2 py-1 rounded-md bg-white/5 text-white/60">Link in Bio</button>
                </div>
            </div>
            <div id="cc_preview_wrap" class="rounded-2xl p-4 min-h-[480px] relative overflow-hidden" style="background:#0d1322; border:1px solid var(--border-glass);">
                <div class="absolute inset-0 opacity-30" style="background: radial-gradient(circle at top right, #312e81, transparent 60%);"></div>
                <div id="cc_backdrop" class="absolute inset-0" style="display:none;"></div>
                <div id="cc_preview" class="relative h-full"></div>
                <div id="cc_preview_meta" class="absolute bottom-2 right-3 text-[10px] text-white/30 font-mono"></div>
            </div>
        </div>
    </form>
</div>

<script>
(function() {
    const $ = (id) => document.getElementById(id);
    const preview = $('cc_preview');
    const backdrop = $('cc_backdrop');
    const POSITIONABLE = ['banner', 'corner', 'pill'];

    function escapeHtml(s){return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

    function btnCss(role) {
        const bg = $('cc_btn_'+role+'_bg').value;
        const fg = $('cc_btn_'+role+'_text').value;
        const style = $('cc_btn_'+role+'_style').value;
        if (style === 'outline') return `background:transparent; color:${bg}; border:1.5px solid ${bg};`;
        if (style === 'link')    return `background:transparent; color:${bg}; border:0; text-decoration:underline;`;
        return `background:${bg}; color:${fg}; border:0;`;
    }

    let activeSurface = 'site';
    document.querySelectorAll('.cc-surface-tab').forEach(b => {
        b.addEventListener('click', () => {
            activeSurface = b.getAttribute('data-cc-surface');
            document.querySelectorAll('.cc-surface-tab').forEach(x => {
                const on = x.getAttribute('data-cc-surface') === activeSurface;
                x.className = 'cc-surface-tab px-2 py-1 rounded-md ' + (on ? 'bg-blue-600/30 text-blue-200' : 'bg-white/5 text-white/60');
            });
            build();
        });
    });

    function effective(field) {
        const sel = document.querySelector(`[name="surface_overrides[${activeSurface}][${field}]"]`);
        const v = sel ? sel.value : '';
        return v || $('cc_' + (field === 'layout' ? 'layout' : 'position')).value;
    }

    let animTick = 0;
    function build() {
        const layout = effective('layout');
        const position = effective('position');
        const theme = $('cc_theme').value;
        const accent = $('cc_accent').value;
        const size = $('cc_size').value;
        const maxw = parseInt($('cc_maxw').value, 10) || 440;
        const radius = parseInt($('cc_radius').value, 10) || 16;
        const dark = theme === 'dark' || theme === 'auto';
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
        const showPolicy = document.querySelector('[name="show_policy_link"][type="checkbox"]').checked;
        const anim = $('cc_anim').value || 'none';
        const delay = parseInt(document.querySelector('[name="entrance_delay"]').value, 10) || 0;
        const logoOn = $('cc_logo_on').checked;
        const logoUrl = $('cc_logo_url').value;
        const bdShow = $('cc_bd_show').checked;
        const bdDim = (parseInt($('cc_bd_dim').value, 10) || 0) / 100;
        const bdBlur = $('cc_bd_blur').checked;

        // Disable position picker for layouts where it's irrelevant.
        const posSel = $('cc_position');
        const irrelevant = !POSITIONABLE.includes(layout);
        posSel.disabled = irrelevant;
        posSel.style.opacity = irrelevant ? '0.4' : '1';

        // Backdrop preview
        if ((layout === 'modal' || layout === 'takeover') && bdShow) {
            backdrop.style.display = 'block';
            backdrop.style.background = `rgba(8,10,20,${bdDim})`;
            backdrop.style.backdropFilter = bdBlur ? 'blur(6px)' : 'none';
            backdrop.style.webkitBackdropFilter = bdBlur ? 'blur(6px)' : 'none';
        } else {
            backdrop.style.display = 'none';
        }

        const widthByLayout = {
            modal: Math.min(maxw, 420),
            banner: 0,
            corner: Math.min(maxw, 360),
            inline: 0,
            pill: Math.min(maxw, 480),
            takeover: 0,
        };
        const sizeShrink = size === 'compact' ? 0.85 : (size === 'wide' ? 1.15 : 1);

        let style = '';
        if (layout === 'modal') {
            style = `position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); width:88%; max-width:${Math.round(widthByLayout.modal * sizeShrink)}px;`;
        } else if (layout === 'takeover') {
            style = `position:absolute; inset:18px; display:flex; align-items:center; justify-content:center; padding:24px;`;
        } else if (layout === 'banner') {
            const top = position.startsWith('top');
            style = `position:absolute; left:8px; right:8px; ${top?'top:8px;':'bottom:8px;'}`;
        } else if (layout === 'inline') {
            style = `position:absolute; left:0; right:0; bottom:0; padding:8px 10px;`;
        } else if (layout === 'pill') {
            const top = position.startsWith('top'); const right = position.endsWith('right'); const left = position.endsWith('left'); const center = position.endsWith('center'); const middle = position.startsWith('middle');
            const horiz = center ? 'left:50%; transform:translateX(-50%);' : (right ? 'right:12px;' : 'left:12px;');
            const vert = middle ? 'top:50%;' : (top ? 'top:12px;' : 'bottom:12px;');
            style = `position:absolute; ${vert} ${horiz} max-width:${Math.round(widthByLayout.pill * sizeShrink)}px;`;
        } else { // corner
            const top = position.startsWith('top'); const right = position.endsWith('right'); const left = position.endsWith('left'); const center = position.endsWith('center'); const middle = position.startsWith('middle');
            const horiz = center ? 'left:50%; transform:translateX(-50%);' : (right ? 'right:12px;' : 'left:12px;');
            const vert = middle ? 'top:50%;' : (top ? 'top:12px;' : 'bottom:12px;');
            style = `position:absolute; ${vert} ${horiz} max-width:${Math.round(widthByLayout.corner * sizeShrink)}px;`;
        }

        const cardW = (layout === 'banner' || layout === 'inline') ? 'width:100%;' : '';
        const cardR = layout === 'pill' ? Math.max(radius, 24) + 'px' : radius + 'px';
        const cardPad = layout === 'inline' ? '10px 14px' : (layout === 'pill' ? '10px 16px' : '18px 18px 14px');

        const logoHtml = (logoOn && logoUrl) ? `<img src="${escapeHtml(logoUrl)}" alt="" style="width:22px; height:22px; border-radius:6px; object-fit:cover; margin-right:8px; vertical-align:middle;">` : '';
        const policyHtml = showPolicy ? ` <a href="#" style="color:${accent}; text-decoration:underline;">${escapeHtml(linkLabel)}</a>.` : '';
        const bodyHtml = layout === 'inline'
            ? `<span style="font-size:12px; color:${muted}; margin-right:10px;">${escapeHtml(title)}, ${escapeHtml(body).slice(0,80)}…${policyHtml}</span>`
            : (layout === 'pill'
                ? `<span style="font-size:12.5px; color:${fg};">${escapeHtml(title)}</span>`
                : `<div style="font-weight:600; font-size:15px; margin-bottom:6px;">${logoHtml}${escapeHtml(title)}</div>
                   <div style="font-size:12.5px; line-height:1.5; color:${muted}; margin-bottom:12px;">${escapeHtml(body)}${policyHtml}</div>`);

        // Animation styles, replayed on every build by stamping a unique name.
        animTick++;
        const animKey = `cc_anim_${animTick}`;
        let animCss = '';
        if (anim === 'fade') {
            animCss = `@keyframes ${animKey} { from { opacity:0 } to { opacity:1 } } animation:${animKey} .35s ease both;`;
        } else if (anim === 'slide-up') {
            animCss = `@keyframes ${animKey} { from { opacity:0; transform:translateY(16px) } to { opacity:1; transform:translateY(0) } } animation:${animKey} .35s ease both;`;
        } else if (anim === 'slide-down') {
            animCss = `@keyframes ${animKey} { from { opacity:0; transform:translateY(-16px) } to { opacity:1; transform:translateY(0) } } animation:${animKey} .35s ease both;`;
        }
        const keyframes = animCss.split('}')[0] + (animCss ? '} ' : '');
        const animInline = animCss ? animCss.split('} ')[1] || '' : '';

        preview.innerHTML = `
          ${anim !== 'none' ? `<style>${keyframes}</style>` : ''}
          <div style="${style}">
            <div style="${cardW} background:${bg}; color:${fg}; border:1px solid ${border}; border-radius:${cardR}; box-shadow:0 18px 48px rgba(0,0,0,0.3); padding:${cardPad}; font-family:'Space Grotesk',sans-serif; ${animInline}">
              ${bodyHtml}
              <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <button type="button" style="${btnCss('primary')} padding:8px 14px; border-radius:10px; font-size:12.5px; font-weight:600; cursor:pointer;">${escapeHtml(accept)}</button>
                <button type="button" style="${btnCss('secondary')} padding:8px 14px; border-radius:10px; font-size:12.5px; font-weight:500; cursor:pointer;">${escapeHtml(reject)}</button>
                <button type="button" style="${btnCss('tertiary')} padding:8px 8px; border-radius:10px; font-size:12.5px; font-weight:500; cursor:pointer;">${escapeHtml(customize)}</button>
              </div>
            </div>
          </div>`;

        const meta = document.getElementById('cc_preview_meta');
        if (meta) {
            const ovr = document.querySelector(`[name="surface_overrides[${activeSurface}][layout]"]`).value;
            meta.textContent = `${activeSurface} · ${layout}/${position}${ovr ? ' · override' : ''}${delay ? ' · ' + delay + 's delay' : ''}`;
        }
    }

    document.querySelectorAll('#cookie-consent-form input, #cookie-consent-form select, #cookie-consent-form textarea').forEach(el => {
        el.addEventListener('input', build);
        el.addEventListener('change', build);
    });

    const removeBtn = $('cc_logo_remove_btn');
    if (removeBtn) {
        const urlInput = $('cc_logo_url');
        const fileInput = $('cc_logo_file');
        const flagInput = $('cc_logo_remove');
        const hint = $('cc_logo_remove_hint');

        const cancelRemoval = () => {
            if (!flagInput || flagInput.value !== '1') return;
            flagInput.value = '0';
            if (hint) hint.classList.add('hidden');
        };

        removeBtn.addEventListener('click', () => {
            if (flagInput) flagInput.value = '1';
            if (urlInput) urlInput.value = '';
            if (fileInput) fileInput.value = '';
            const previewEl = $('cc_logo_preview');
            if (previewEl) previewEl.remove();
            if (hint) hint.classList.remove('hidden');
            build();
        });

        // If the admin changes their mind and types a URL or picks a new
        // file after clicking Remove, drop the removal flag so the new
        // value is what actually gets saved.
        if (urlInput) urlInput.addEventListener('input', () => { if (urlInput.value.trim() !== '') cancelRemoval(); });
        if (fileInput) fileInput.addEventListener('change', () => { if (fileInput.files && fileInput.files.length > 0) cancelRemoval(); });
    }

    build();

    // ---- Per-language copy repeater ------------------------------------
    // Each row's inputs are wired to `copy_locales[<code>][<key>]` form
    // names so the existing controller validation + normalizer pick them
    // up. Codes are re-applied to all field names whenever the code input
    // changes so renaming a row doesn't require resaving twice.
    const localesHost = document.getElementById('cc_locales');
    const localeTpl = document.getElementById('cc_locale_row_tpl');
    const addBtn = document.getElementById('cc_locale_add');
    const seeded = @json($cfg['copy_locales'] ?? new \stdClass());
    const COPY_KEYS = ['title','body','accept_all','reject_all','customize','save','policy_link_label','reopen_link_label'];

    function rewireRowNames(row) {
        const codeInput = row.querySelector('[data-cc-locale-code]');
        const raw = (codeInput.value || '').trim();
        // Use a placeholder bucket while the code is empty/invalid so the
        // repeater never collides with another row. Server normalizer
        // discards invalid codes.
        const bucket = raw === '' ? '__pending_' + (row.dataset.rowId || '0') : raw;
        row.querySelectorAll('[data-cc-loc]').forEach(el => {
            const k = el.getAttribute('data-cc-loc');
            el.name = `copy_locales[${bucket}][${k}]`;
        });
    }

    let rowSeq = 0;
    function addRow(code, values) {
        if (localesHost.querySelectorAll('.cc-locale-row').length >= 50) return;
        const node = localeTpl.content.firstElementChild.cloneNode(true);
        node.dataset.rowId = String(++rowSeq);
        const codeInput = node.querySelector('[data-cc-locale-code]');
        codeInput.value = code || '';
        if (values && typeof values === 'object') {
            COPY_KEYS.forEach(k => {
                const f = node.querySelector(`[data-cc-loc="${k}"]`);
                if (f && values[k] != null) f.value = values[k];
            });
        }
        node.querySelector('[data-cc-locale-remove]').addEventListener('click', () => {
            node.remove();
        });
        codeInput.addEventListener('input', () => rewireRowNames(node));
        localesHost.appendChild(node);
        rewireRowNames(node);
    }

    if (addBtn) addBtn.addEventListener('click', () => addRow('', null));

    if (seeded && typeof seeded === 'object' && !Array.isArray(seeded)) {
        Object.keys(seeded).forEach(code => addRow(code, seeded[code]));
    }
})();
</script>
@endsection
