@extends('user.layouts.app')
@section('title', 'Edit Chat Widget · ' . $companion->name)

@section('content')
@php
    $embedSnippet = '<script src="' . $embedScriptUrl . '" data-companion="' . $companion->public_id .
        '" data-accent="' . ($config['accent'] ?? '#3d6bff') .
        '" data-position="' . ($config['position'] ?? 'bottom-right') .
        '" data-label="' . htmlspecialchars($config['launcher_label'] ?? 'Chat', ENT_QUOTES) .
        '" data-greeting="' . htmlspecialchars($config['greeting_bubble'] ?? '', ENT_QUOTES) .
        '" data-placeholder="' . htmlspecialchars($config['placeholder'] ?? 'Ask me anything…', ENT_QUOTES) .
        '" data-theme="' . ($config['theme'] ?? 'auto') .
        '" defer></script>';
    $iframeSnippet = '<iframe src="' . $iframeUrl . '" title="' . htmlspecialchars($companion->name, ENT_QUOTES) .
        '" style="border:0;width:100%;max-width:380px;height:540px;border-radius:18px;overflow:hidden;"></iframe>';
@endphp

<div class="max-w-5xl mx-auto px-4 py-8 space-y-6">
    @if(session('status'))<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">
            <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="flex items-start justify-between gap-3">
        <div>
            <a href="{{ route('user.ai-companions.index') }}" class="text-xs text-white/40 hover:text-white/70"><i class="fas fa-arrow-left"></i> Back</a>
            <h1 class="text-2xl font-bold text-white mt-1">{{ $companion->name }}</h1>
            <p class="text-xs text-white/50 mt-1">Public id: <span class="font-mono">{{ $companion->public_id }}</span></p>
        </div>
        <a href="{{ route('user.ai-companions.conversations', $companion) }}" class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-white text-sm">
            <i class="fas fa-comment"></i> View conversations
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 text-center">
            <p class="text-[10px] uppercase tracking-wider text-white/40">Turns this month</p>
            <p class="text-2xl font-bold text-white mt-1">{{ number_format($usage['turns']) }}</p>
            <p class="text-[10px] text-white/40">Hard cap: {{ $companion->hard_cap_per_month ?: '∞' }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 text-center">
            <p class="text-[10px] uppercase tracking-wider text-white/40">AI credits used</p>
            <p class="text-2xl font-bold text-white mt-1">{{ number_format($usage['credits']) }}</p>
            <p class="text-[10px] text-white/40">Balance: {{ number_format($balance) }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 text-center">
            <p class="text-[10px] uppercase tracking-wider text-white/40">Free turns / mo</p>
            <p class="text-2xl font-bold text-white mt-1">{{ $companion->free_turns_per_month }}</p>
            <p class="text-[10px] text-white/40">Beyond this, turns charge AI credits</p>
        </div>
    </div>

    <form method="POST" action="{{ route('user.ai-companions.update', $companion) }}" class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-5">
        @csrf @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                            <input type="radio" name="placement" value="{{ $key }}" @checked(old('placement', $companion->placement)===$key) class="mr-2">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        <fieldset class="space-y-3">
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
                <label><input type="hidden" name="config[auto_send_inbox]" value="0"><input type="checkbox" name="config[auto_send_inbox]" value="1" @checked(!empty($config['auto_send_inbox']))> Auto-send inbox replies</label>
            </div>
        </fieldset>

        <fieldset class="space-y-3">
            <legend class="text-xs font-bold text-white/80 uppercase tracking-wider">Limits & guardrails</legend>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-white/60 mb-1">Free turns / month</label>
                    <input type="number" min="0" name="free_turns_per_month" value="{{ $companion->free_turns_per_month }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-white/60 mb-1">Hard monthly cap (0 = platform default)</label>
                    <input type="number" min="0" name="hard_cap_per_month" value="{{ $companion->hard_cap_per_month }}" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                </div>
            </div>
        </fieldset>

        @if($companion->placement === 'embed')
            <fieldset class="space-y-3">
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
        @endif

        @if($companion->placement === 'biolink')
            <fieldset class="space-y-2">
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
        @endif

        <div class="flex justify-end gap-2 pt-2">
            <button class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-sm">Save Chat Widget</button>
        </div>
    </form>

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
