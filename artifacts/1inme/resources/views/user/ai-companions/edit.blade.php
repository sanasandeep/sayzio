@extends('user.layouts.app')
@section('title', 'Edit Chat Widget · ' . $companion->name)

@section('content')
@php
    $embedSnippet = '<script src="' . $embedScriptUrl . '" data-companion="' . $companion->public_id .
        '" data-accent="' . ($config['accent'] ?? '#3d6bff') .
        '" data-appearance="' . htmlspecialchars(json_encode(\App\Services\AI\CompanionAppearance::variables($config)), ENT_QUOTES) . '" data-position="' . ($config['position'] ?? 'bottom-right') .
        '" data-label="' . htmlspecialchars($config['launcher_label'] ?? 'Chat', ENT_QUOTES) .
        '" data-greeting="' . htmlspecialchars($config['greeting_bubble'] ?? '', ENT_QUOTES) .
        '" data-placeholder="' . htmlspecialchars($config['placeholder'] ?? 'Ask me anything…', ENT_QUOTES) .
        '" data-theme="' . ($config['theme'] ?? 'auto') .
        '" defer></script>';
    $iframeSnippet = '<iframe src="' . $iframeUrl . '" title="' . htmlspecialchars($companion->name, ENT_QUOTES) .
        '" style="border:0;width:100%;max-width:380px;height:540px;border-radius:18px;overflow:hidden;"></iframe>';
@endphp

<div class="widget-page w-full space-y-6">
    @if(session('status'))<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">
            <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @include('user.partials.page-hero', ['title'=>$companion->name,'subtitle'=>'Design your chat experience. Preview changes instantly, then save to publish.','icon'=>'fa-comments','back'=>route('user.ai-companions.index'),'actions'=>[['label'=>'Conversations','url'=>route('user.ai-companions.conversations',$companion),'icon'=>'fa-comment','class'=>'btn-secondary']],'chips'=>[['text'=>'Chat Widget','icon'=>'fa-comments']]])
    @include('user.partials.overview-styles')
    <div class="widget-dashboard-stats grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach([
            ['label'=>'Turns this month','value'=>number_format($usage['turns']),'note'=>'Hard cap: '.($companion->hard_cap_per_month ?: 'Platform default'),'color'=>'#3b82f6','tone'=>'#123c85','kind'=>'sessions'],
            ['label'=>'Coins used','value'=>number_format($usage['credits']),'note'=>'Balance: '.number_format($balance),'color'=>'#8b5cf6','tone'=>'#512b89','kind'=>'pages'],
            ['label'=>'Est. coins / day','value'=>'≈ '.number_format($estimate['per_day']),'note'=>'~'.$estimate['avg_per_turn'].' coins / turn (estimate)','color'=>'#0d9488','tone'=>'#075953','kind'=>'clicks'],
            ['label'=>'Free turns / month','value'=>$companion->free_turns_per_month < 0 ? 'Unlimited' : number_format($companion->free_turns_per_month),'note'=>$companion->free_turns_per_month < 0 ? 'No monthly free-turn limit' : 'Additional turns use coins','color'=>'#f59e0b','tone'=>'#804210','kind'=>'bot'],
        ] as $stat)
            <article class="ov-panel ov-count" style="--ov-color:{{ $stat['color'] }};--ov-tone:{{ $stat['tone'] }};--ov-number-size:{{ strlen($stat['value']) > 7 ? '30px' : '54px' }}">
                <div class="ov-count-top"><h2 class="ov-count-label">{{ $stat['label'] }}</h2></div>
                @include('user.partials.count-card-art',['kind'=>$stat['kind'],'artId'=>'widget-stat-'.$loop->index])
                <div class="ov-count-bottom"><strong>{{ $stat['value'] }}</strong><p>{{ $stat['note'] }}</p></div>
            </article>
        @endforeach
    </div>
    <style>html .widget-dashboard-stats .ov-count-label,html .widget-dashboard-stats .ov-count strong{color:#fff!important}html .widget-dashboard-stats .ov-count-bottom p{color:rgba(255,255,255,.85)!important}</style>

    <div class="widget-editor-grid">
    <form method="POST" action="{{ route('user.ai-companions.update', $companion) }}" id="widget-editor" x-data="{ tab: 'basic', placement: @js(old('placement',$companion->placement)) }" @invalid.capture="tab=$event.target.closest('[data-widget-tab]')?.dataset.widgetTab || 'basic'" class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-5">
        @csrf @method('PUT')
        <nav class="widget-tabs" aria-label="Widget settings"><template x-for="item in [{id:'basic',label:'Basic info'},{id:'more',label:'More info'},{id:'advanced',label:'Advanced'}]"><button type="button" :aria-pressed="tab===item.id" @click="tab=item.id" x-text="item.label"></button></template></nav>
        <p class="text-xs text-white/50">Basic settings are enough to start. Preview changes on the right; save when ready.</p>

        <div data-widget-tab="basic" x-show="tab==='basic'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-white/70 mb-1">Name</label>
                <input name="name" required maxlength="120" value="{{ old('name', $companion->name) }}"
                       class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
            </div>
            <div>
                <label class="block text-xs font-semibold text-white/70 mb-1">Agent (the brain)</label>
                <select name="persona_id" required class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                    @foreach($personas as $p)
                        <option value="{{ $p->id }}" @selected(old('persona_id', $companion->persona_id)==$p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-white/70 mb-1">Placement</label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    @foreach($placements as $key => $label)
                        <label class="rounded-xl border border-white/10 bg-white/[0.03] p-3 text-sm text-white cursor-pointer hover:bg-white/[0.06]">
                            <input type="radio" x-model="placement" name="placement" value="{{ $key }}" @checked(old('placement', $companion->placement)===$key) class="mr-2">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        <fieldset data-widget-tab="more" x-show="tab==='more'" class="space-y-3">
            <legend class="text-xs font-bold text-white/80 uppercase tracking-wider">Visuals</legend>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-white/60 mb-1">Theme</label>
                    <select name="config[theme]" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                        @foreach(['auto','light','dark'] as $t)<option value="{{ $t }}" @selected(($config['theme'] ?? 'auto')===$t)>{{ ucfirst($t) }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-white/60 mb-1">Accent</label>
                    <input type="color" name="config[accent]" value="{{ $config['accent'] ?? '#3d6bff' }}" class="w-full h-10 bg-black/30 border border-white/10 rounded-lg">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-white/60 mb-1">Launcher position</label>
                    <select name="config[position]" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                        <option value="bottom-right" @selected(($config['position'] ?? 'bottom-right')==='bottom-right')>Bottom-right</option>
                        <option value="bottom-left"  @selected(($config['position'] ?? '')==='bottom-left')>Bottom-left</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-white/60 mb-1">Launcher label</label>
                    <input name="config[launcher_label]" maxlength="60" value="{{ $config['launcher_label'] ?? 'Chat' }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-white/60 mb-1">Greeting bubble</label>
                    <input name="config[greeting_bubble]" maxlength="280" value="{{ $config['greeting_bubble'] ?? '' }}" placeholder="Hi! Got a question?" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-white/60 mb-1">Input placeholder</label>
                    <input name="config[placeholder]" maxlength="120" value="{{ $config['placeholder'] ?? 'Ask me anything…' }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                </div>
            </div>
            <div class="flex flex-wrap gap-4 pt-2 text-xs text-white/70">
                <label><input type="hidden" name="config[show_branding]" value="0"><input type="checkbox" name="config[show_branding]" value="1" @checked(!empty($config['show_branding']))> Show "Powered by Sayzio"</label>
                <label><input type="hidden" name="config[inline]" value="0"><input type="checkbox" name="config[inline]" value="1" @checked(!empty($config['inline']))> Inline mode (Link in Bio only)</label>
                <label x-show="placement==='inbox'"><input type="hidden" name="config[auto_send_inbox]" value="0"><input type="checkbox" name="config[auto_send_inbox]" value="1" @checked(!empty($config['auto_send_inbox']))> Auto-send inbox replies</label>
            </div>
        </fieldset>

        <div data-widget-tab="more" x-show="tab==='more'">@include('user.ai-companions.appearance')</div>
        <fieldset data-widget-tab="advanced" x-show="tab==='advanced'" class="space-y-3">
            <legend class="text-xs font-bold text-white/80 uppercase tracking-wider">Limits & guardrails</legend>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-white/60 mb-1">Free turns / month</label>
                    <input type="number" min="0" name="free_turns_per_month" @disabled($companion->free_turns_per_month < 0) value="{{ max(0,$companion->free_turns_per_month) }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-white/60 mb-1">Hard monthly cap (0 = platform default)</label>
                    <input type="number" min="0" name="hard_cap_per_month" value="{{ $companion->hard_cap_per_month }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                </div>
            </div>
            <div><label class="block text-xs text-white/60 mb-1">Auto-open delay (milliseconds, 0 = off)</label><input type="number" min="0" max="60000" name="config[auto_open_after_ms]" value="{{ $config['auto_open_after_ms'] ?? 0 }}" class="theme-input w-full"></div>
        </fieldset>

        <div data-widget-tab="advanced" x-show="tab==='advanced'">
            <fieldset x-show="placement==='embed'" class="space-y-3">
                <legend class="text-xs font-bold text-white/80 uppercase tracking-wider">Allowed domains</legend>
                <p class="text-[11px] text-white/50">Only the listed domains can embed this chat widget. Use a leading dot (e.g. <code>.example.com</code>) to allow subdomains.</p>
                @php $domains = old('allowed_domains', $companion->allowed_domains ?: ['']); @endphp
                <div id="domains-list" class="space-y-2">
                    @foreach(($domains ?: ['']) as $i => $d)
                        <div class="flex gap-2">
                            <input name="allowed_domains[]" value="{{ $d }}" placeholder="example.com or .example.com"
                                   class="flex-1 bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                            <button type="button" onclick="this.parentElement.remove()" class="px-2 py-1 rounded-lg bg-red-500/10 text-red-300 text-xs"><i class="fas fa-times"></i></button>
                        </div>
                    @endforeach
                </div>
                <button type="button" onclick="document.getElementById('domains-list').insertAdjacentHTML('beforeend','<div class=\'flex gap-2\'><input name=\'allowed_domains[]\' placeholder=\'example.com\' class=\'flex-1 bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white\'><button type=button onclick=this.parentElement.remove() class=\'px-2 py-1 rounded-lg bg-red-500/10 text-red-300 text-xs\'><i class=\'fas fa-times\'></i></button></div>')"
                        class="text-xs text-blue-300 hover:text-blue-200"><i class="fas fa-plus"></i> Add domain</button>
            </fieldset>
            </div>

        <div data-widget-tab="more" x-show="tab==='more'">
            <fieldset x-show="placement==='biolink'" class="space-y-2">
                <legend class="text-xs font-bold text-white/80 uppercase tracking-wider">Link in Bio pages to render on</legend>
                <p class="text-[11px] text-white/50">Add an "AI Companion" block to one of these Link in Bio pages to actually show the chatbot. This list just scopes which links it's allowed on.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-56 overflow-auto pr-2">
                    @forelse($links as $l)
                        <label class="flex items-center gap-2 text-xs text-white/70">
                            <input type="checkbox" name="link_ids[]" value="{{ $l->id }}" @checked(in_array($l->id, $attachedLinks))>
                            <span class="truncate">{{ $l->title ?: $l->alias }}</span>
                            <span class="text-white/30">/{{ $l->alias }}</span>
                        </label>
                    @empty
                        <p class="text-xs text-white/40">No Link in Bio pages yet.</p>
                    @endforelse
                </div>
            </fieldset>
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <button class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-sm">Save Chat Widget</button>
        </div>
    </form>
    @include('user.ai-companions.preview')
    </div>

    @if($companion->placement === 'embed')
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-3">
            <h2 class="text-sm font-bold text-white">Embed snippet</h2>
            <p class="text-[11px] text-white/50">Paste this script tag right before the closing <code>&lt;/body&gt;</code> on any allowed domain. Visitors get a floating chat launcher.</p>
            <textarea readonly rows="5" class="w-full bg-black/40 border border-white/10 rounded-lg p-3 font-mono text-xs text-emerald-200" onclick="this.select()">{{ $embedSnippet }}</textarea>
            <h2 class="text-sm font-bold text-white pt-2">iframe fallback</h2>
            <p class="text-[11px] text-white/50">When the embedding site blocks third-party scripts, drop in this iframe instead.</p>
            <textarea readonly rows="3" class="w-full bg-black/40 border border-white/10 rounded-lg p-3 font-mono text-xs text-emerald-200" onclick="this.select()">{{ $iframeSnippet }}</textarea>
        </div>
    @elseif($companion->placement === 'inbox')
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-2 text-sm text-white/80">
            <h2 class="font-bold">How to use the inbox bot</h2>
            <p>Open any DM thread under <a href="{{ route('user.inbox.dms.index') }}" class="text-blue-300 underline">Inbox → DMs</a>, switch on "Auto-reply with this chat widget", and your next viewer message will get a drafted reply. Toggle <em>Auto-send</em> above to send the reply without confirming.</p>
        </div>
    @else
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-2 text-sm text-white/80">
            <h2 class="font-bold">How to add it to a Link in Bio</h2>
            <p>Open the Link in Bio builder, add an <strong>AI Companion</strong> block, and pick this chat widget from the dropdown. It renders as a floating launcher (or inline if you toggled Inline mode above).</p>
        </div>
    @endif
</div>
@endsection
