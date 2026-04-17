@extends('user.layouts.app')
@section('title', 'Edit Social Proof')

@section('content')
@php
    $s = $proof->settings ?? [];
    $d = array_merge(\App\Modules\User\Models\SocialProof::defaultDesign(),  (array)($proof->design ?? []));
    $t = array_merge(\App\Modules\User\Models\SocialProof::defaultTargeting(),(array)($proof->targeting ?? []));
    $embedSrc = url('/sp/' . $proof->uuid . '.js');
    $embedTag = '<script src="' . $embedSrc . '" async></script>';
@endphp

<div class="max-w-5xl mx-auto" x-data="{ tab: 'content' }">
    <div class="flex items-center justify-between mb-6 gap-4">
        <div class="min-w-0">
            <a href="{{ route('user.social-proofs.index') }}" class="text-white/50 hover:text-white text-sm"><i class="fas fa-arrow-left mr-1"></i> Back</a>
            <h1 class="text-2xl font-bold text-white mt-2 truncate">{{ $proof->name }}</h1>
            <p class="text-white/40 text-sm mt-1">{{ $proof->typeLabel() }}</p>
        </div>
        <div class="flex items-center gap-2">
            <form action="{{ route('user.social-proofs.toggle', $proof) }}" method="POST">@csrf
                <button class="px-3 py-2 rounded-xl text-sm border border-white/10 text-white/80 hover:bg-white/5">
                    <i class="fas fa-{{ $proof->is_active ? 'pause' : 'play' }} mr-1"></i>
                    {{ $proof->is_active ? 'Pause' : 'Activate' }}
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))<div class="mb-4 px-4 py-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-4 px-4 py-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-sm">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

    {{-- Stats bar --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="glass rounded-2xl p-4">
            <div class="text-white/50 text-xs uppercase tracking-wide">Impressions (30d)</div>
            <div class="text-white text-2xl font-bold mt-1">{{ number_format($stats['impressions_30d']) }}</div>
        </div>
        <div class="glass rounded-2xl p-4">
            <div class="text-white/50 text-xs uppercase tracking-wide">Clicks (30d)</div>
            <div class="text-white text-2xl font-bold mt-1">{{ number_format($stats['clicks_30d']) }}</div>
        </div>
        <div class="glass rounded-2xl p-4">
            <div class="text-white/50 text-xs uppercase tracking-wide">Conversions (30d)</div>
            <div class="text-white text-2xl font-bold mt-1">{{ number_format($stats['conversions_30d']) }}</div>
        </div>
        <div class="glass rounded-2xl p-4">
            <div class="text-white/50 text-xs uppercase tracking-wide">All-time CTR</div>
            <div class="text-white text-2xl font-bold mt-1">{{ $stats['ctr'] }}%</div>
        </div>
    </div>

    {{-- Tab nav --}}
    <div class="flex gap-1 mb-4 border-b border-white/10">
        @foreach(['content'=>'Content','design'=>'Design','targeting'=>'Targeting','embed'=>'Embed'] as $k=>$lbl)
        <button type="button" @click="tab='{{ $k }}'" :class="tab==='{{ $k }}' ? 'text-white border-violet-500' : 'text-white/50 border-transparent'"
                class="px-4 py-2 text-sm font-medium border-b-2 -mb-px hover:text-white">{{ $lbl }}</button>
        @endforeach
    </div>

    <form method="POST" action="{{ route('user.social-proofs.update', $proof) }}" class="space-y-6">
        @csrf @method('PUT')
        <input type="hidden" name="is_active" value="{{ $proof->is_active ? 1 : 0 }}">

        <div class="glass rounded-2xl p-5">
            <label class="block text-white/70 text-sm mb-2">Campaign name</label>
            <input type="text" name="name" required value="{{ old('name', $proof->name) }}"
                   class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-violet-500">
        </div>

        {{-- ================= CONTENT TAB ================= --}}
        <div x-show="tab==='content'" class="space-y-4">
            @switch($proof->type)
                @case('recent_activity')
                    <div class="glass rounded-2xl p-5 space-y-3">
                        <h3 class="text-white font-semibold">Notification template</h3>
                        <div>
                            <label class="text-white/70 text-sm block mb-1">Title template</label>
                            <input type="text" name="settings[title_template]" value="{{ $s['title_template'] ?? '{name} from {location}' }}"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                        </div>
                        <div>
                            <label class="text-white/70 text-sm block mb-1">Body template</label>
                            <input type="text" name="settings[body_template]" value="{{ $s['body_template'] ?? '{action}' }}"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                        </div>
                        <p class="text-white/40 text-xs">Available tokens: <code class="bg-white/5 px-1 rounded">{name}</code> <code class="bg-white/5 px-1 rounded">{location}</code> <code class="bg-white/5 px-1 rounded">{action}</code></p>
                    </div>
                    @break
                @case('visitor_count')
                    <div class="glass rounded-2xl p-5 space-y-3">
                        <h3 class="text-white font-semibold">Visitor counter</h3>
                        <div>
                            <label class="text-white/70 text-sm block mb-1">Display text</label>
                            <input type="text" name="settings[text]" value="{{ $s['text'] ?? '{count} people are viewing this page' }}"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                            <p class="text-white/40 text-xs mt-1">Use <code class="bg-white/5 px-1 rounded">{count}</code> as the counter placeholder.</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div><label class="text-white/70 text-sm block mb-1">Min</label><input type="number" min="0" name="settings[min]" value="{{ $s['min'] ?? 12 }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white"></div>
                            <div><label class="text-white/70 text-sm block mb-1">Max</label><input type="number" min="1" name="settings[max]" value="{{ $s['max'] ?? 48 }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white"></div>
                        </div>
                    </div>
                    @break
                @case('conversion_count')
                    <div class="glass rounded-2xl p-5 space-y-3">
                        <h3 class="text-white font-semibold">Conversion counter</h3>
                        <label class="text-white/70 text-sm block mb-1">Text</label>
                        <input type="text" name="settings[text]" value="{{ $s['text'] ?? '{count} people purchased in the last 24 hours' }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                        <label class="text-white/70 text-sm block mb-1 mt-3">Count to display</label>
                        <input type="number" min="0" name="settings[count]" value="{{ $s['count'] ?? 47 }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                    </div>
                    @break
                @case('email_signup')
                    <div class="glass rounded-2xl p-5 space-y-3">
                        <h3 class="text-white font-semibold">Email signup prompt</h3>
                        <input type="text" name="settings[title]" placeholder="Title" value="{{ $s['title'] ?? 'Join our newsletter' }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                        <input type="text" name="settings[body]"  placeholder="Body"  value="{{ $s['body']  ?? 'Get weekly tips delivered to your inbox.' }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                        <input type="text" name="settings[cta]"   placeholder="Button text" value="{{ $s['cta']   ?? 'Subscribe' }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                    </div>
                    @break
                @case('countdown')
                    <div class="glass rounded-2xl p-5 space-y-3">
                        <h3 class="text-white font-semibold">Countdown</h3>
                        <input type="text" name="settings[title]" placeholder="Title" value="{{ $s['title'] ?? 'Limited offer ends in' }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                        <label class="text-white/70 text-sm block mb-1">Ends at</label>
                        <input type="datetime-local" name="settings[ends_at]" value="{{ \Illuminate\Support\Carbon::parse($s['ends_at'] ?? now()->addDays(3))->format('Y-m-d\TH:i') }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                        <input type="text" name="settings[expired_text]" placeholder="Expired text" value="{{ $s['expired_text'] ?? 'Offer expired' }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                    </div>
                    @break
                @case('review')
                    <div class="glass rounded-2xl p-5 space-y-3">
                        <h3 class="text-white font-semibold">Reviews</h3>
                        <p class="text-white/50 text-sm">Add reviews below as JSON for now. Each: <code class="bg-white/5 px-1 rounded">{"author":"Name","text":"...","rating":5}</code></p>
                        <textarea name="settings[items]" rows="6" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white font-mono text-xs">{{ json_encode($s['items'] ?? [], JSON_PRETTY_PRINT) }}</textarea>
                        <label class="text-white/70 text-sm flex items-center gap-2">
                            <input type="hidden" name="settings[rotate]" value="0">
                            <input type="checkbox" name="settings[rotate]" value="1" {{ ($s['rotate'] ?? true) ? 'checked' : '' }}> Rotate through reviews
                        </label>
                    </div>
                    @break
                @case('custom_html')
                    <div class="glass rounded-2xl p-5 space-y-3">
                        <h3 class="text-white font-semibold">Custom HTML</h3>
                        <p class="text-white/40 text-xs"><i class="fas fa-shield-alt mr-1"></i> &lt;script&gt; tags and inline event handlers are removed for security.</p>
                        <textarea name="settings[html]" rows="10" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white font-mono text-xs">{{ $s['html'] ?? '' }}</textarea>
                    </div>
                    @break
            @endswitch

            {{-- Curated activity items (only relevant for recent_activity) --}}
            @if($proof->type === 'recent_activity')
            <div class="glass rounded-2xl p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-white font-semibold">Activity pool ({{ $items->count() }})</h3>
                </div>
                @if($items->isEmpty())
                    <p class="text-white/40 text-sm mb-3">No activities yet — add a few to seed the rotation.</p>
                @else
                    <div class="space-y-2 mb-4">
                        @foreach($items as $item)
                        <div class="flex items-center gap-3 bg-white/5 rounded-xl p-3">
                            <div class="flex-1 min-w-0">
                                <div class="text-white text-sm font-medium truncate">{{ $item->name ?: '—' }} <span class="text-white/40">{{ $item->location ? '· '.$item->location : '' }}</span></div>
                                <div class="text-white/60 text-xs truncate">{{ $item->action }}</div>
                            </div>
                            <form action="{{ route('user.social-proofs.items.destroy', [$proof, $item]) }}" method="POST">
                                @csrf @method('DELETE')
                                <button class="text-rose-400 hover:text-rose-300 px-2"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="glass rounded-2xl p-5">
                <h4 class="text-white font-medium mb-3">Add activity</h4>
                <form action="{{ route('user.social-proofs.items.store', $proof) }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @csrf
                    <input type="text" name="name" placeholder="Name (e.g. Sarah)" class="bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                    <input type="text" name="location" placeholder="Location (e.g. London)" class="bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                    <input type="text" name="action" placeholder="Action (e.g. purchased Premium Plan)" class="md:col-span-2 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                    <input type="url"  name="image_url" placeholder="Avatar URL (optional)" class="bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                    <input type="url"  name="link_url"  placeholder="Click-through URL (optional)" class="bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                    <input type="text" name="time_label" placeholder="Time label (e.g. 2 minutes ago)" class="md:col-span-2 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                    <div class="md:col-span-2 flex justify-end">
                        <button class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-xl text-sm font-medium"><i class="fas fa-plus mr-1"></i> Add</button>
                    </div>
                </form>
            </div>
            @endif
        </div>

        {{-- ================= DESIGN TAB ================= --}}
        <div x-show="tab==='design'" x-cloak class="space-y-4">
            <div class="glass rounded-2xl p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-white/70 text-sm block mb-1">Position</label>
                    <select name="design[position]" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                        @foreach(['bottom-left'=>'Bottom Left','bottom-right'=>'Bottom Right','top-left'=>'Top Left','top-right'=>'Top Right'] as $v=>$lbl)
                            <option value="{{ $v }}" {{ ($d['position']??'')===$v?'selected':'' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-white/70 text-sm block mb-1">Theme</label>
                    <select name="design[theme]" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                        <option value="light" {{ ($d['theme']??'')==='light'?'selected':'' }}>Light</option>
                        <option value="dark"  {{ ($d['theme']??'')==='dark' ?'selected':'' }}>Dark</option>
                    </select>
                </div>
                <div>
                    <label class="text-white/70 text-sm block mb-1">Accent color</label>
                    <input type="color" name="design[accent]" value="{{ $d['accent'] ?? '#7c3aed' }}" class="w-full h-10 bg-white/5 border border-white/10 rounded-xl">
                </div>
                <div>
                    <label class="text-white/70 text-sm block mb-1">Corner radius</label>
                    <select name="design[rounded]" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                        @foreach(['sm'=>'Small','md'=>'Medium','lg'=>'Large','xl'=>'Extra large','full'=>'Pill'] as $v=>$lbl)
                            <option value="{{ $v }}" {{ ($d['rounded']??'')===$v?'selected':'' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-white/70 text-sm block mb-1">Animation</label>
                    <select name="design[animation]" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                        @foreach(['slide-up'=>'Slide up','fade'=>'Fade','zoom'=>'Zoom'] as $v=>$lbl)
                            <option value="{{ $v }}" {{ ($d['animation']??'')===$v?'selected':'' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-4">
                    <label class="text-white/70 text-sm flex items-center gap-2">
                        <input type="hidden" name="design[shadow]" value="0">
                        <input type="checkbox" name="design[shadow]" value="1" {{ !empty($d['shadow']) ? 'checked' : '' }}> Drop shadow
                    </label>
                    <label class="text-white/70 text-sm flex items-center gap-2">
                        <input type="hidden" name="design[show_close]" value="0">
                        <input type="checkbox" name="design[show_close]" value="1" {{ !empty($d['show_close']) ? 'checked' : '' }}> Show close button
                    </label>
                </div>
            </div>
        </div>

        {{-- ================= TARGETING TAB ================= --}}
        <div x-show="tab==='targeting'" x-cloak class="space-y-4">
            <div class="glass rounded-2xl p-5 space-y-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div><label class="text-white/70 text-sm block mb-1">Initial delay (s)</label><input type="number" min="0" name="targeting[delay]" value="{{ $t['delay'] ?? 3 }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white"></div>
                    <div><label class="text-white/70 text-sm block mb-1">Interval (s)</label><input type="number" min="0" name="targeting[interval]" value="{{ $t['interval'] ?? 8 }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white"></div>
                    <div><label class="text-white/70 text-sm block mb-1">Duration (s)</label><input type="number" min="0" name="targeting[duration]" value="{{ $t['duration'] ?? 5 }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white"></div>
                    <div><label class="text-white/70 text-sm block mb-1">Max per session</label><input type="number" min="0" name="targeting[max_per_session]" value="{{ $t['max_per_session'] ?? 0 }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white" placeholder="0 = unlimited"></div>
                </div>
                <div>
                    <label class="text-white/70 text-sm block mb-2">Devices</label>
                    <div class="flex gap-4 text-white/70 text-sm">
                        @foreach(['desktop'=>'Desktop','tablet'=>'Tablet','mobile'=>'Mobile'] as $v=>$lbl)
                        <label class="flex items-center gap-2"><input type="checkbox" name="targeting[devices][]" value="{{ $v }}" {{ in_array($v, $t['devices'] ?? []) ? 'checked' : '' }}> {{ $lbl }}</label>
                        @endforeach
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-white/70 text-sm block mb-1">Show on these pages (one URL pattern per line, * allowed)</label>
                        <textarea name="targeting[pages_include]" rows="3" placeholder="/landing&#10;/pricing*" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm font-mono">{{ implode("\n", (array)($t['pages_include'] ?? [])) }}</textarea>
                    </div>
                    <div>
                        <label class="text-white/70 text-sm block mb-1">Hide on these pages</label>
                        <textarea name="targeting[pages_exclude]" rows="3" placeholder="/admin*&#10;/checkout/success" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm font-mono">{{ implode("\n", (array)($t['pages_exclude'] ?? [])) }}</textarea>
                    </div>
                </div>
                <p class="text-white/40 text-xs">Empty include list = show on all pages. Exclude wins over include.</p>
            </div>
        </div>

        {{-- ================= EMBED TAB ================= --}}
        <div x-show="tab==='embed'" x-cloak class="space-y-4">
            <div class="glass rounded-2xl p-5">
                <h3 class="text-white font-semibold mb-2">Embed on any website</h3>
                <p class="text-white/60 text-sm mb-3">Paste this snippet anywhere in your site's HTML (ideally just before <code class="bg-white/5 px-1 rounded">&lt;/body&gt;</code>):</p>
                <div class="relative">
                    <textarea readonly id="sp-embed-tag" rows="2" class="w-full bg-black/40 border border-white/10 rounded-xl px-3 py-2 text-emerald-300 font-mono text-xs">{{ $embedTag }}</textarea>
                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('sp-embed-tag').value); this.innerText='Copied!'; setTimeout(()=>this.innerText='Copy',1500)"
                            class="absolute top-2 right-2 px-2 py-1 text-xs bg-violet-600 hover:bg-violet-700 text-white rounded-lg">Copy</button>
                </div>
                <p class="text-white/40 text-xs mt-3"><i class="fas fa-info-circle mr-1"></i> The widget loads asynchronously — it never blocks your page.</p>
            </div>
            <div class="glass rounded-2xl p-5">
                <h3 class="text-white font-semibold mb-2">Use in a biolink</h3>
                <p class="text-white/60 text-sm">In any biolink editor, add a <strong class="text-white">Social Proof</strong> block and pick this campaign — it will render inline on the biolink page.</p>
            </div>
            <div class="glass rounded-2xl p-5">
                <h3 class="text-white font-semibold mb-2">Live preview URL</h3>
                <p class="text-white/60 text-sm mb-2">Open this URL in a new tab to test the widget on a blank page:</p>
                <input type="text" readonly value="{{ url('/sp/' . $proof->uuid . '.json') }}" class="w-full bg-black/40 border border-white/10 rounded-xl px-3 py-2 text-white/80 font-mono text-xs">
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('user.social-proofs.index') }}" class="px-4 py-2 rounded-xl text-white/70 hover:bg-white/5 text-sm">Cancel</a>
            <button class="bg-violet-600 hover:bg-violet-700 text-white px-5 py-2 rounded-xl text-sm font-medium">Save changes</button>
        </div>
    </form>
</div>

{{-- Live preview of THIS notification on the editor itself --}}
<script src="{{ url('/sp/' . $proof->uuid . '.js') }}" async></script>
@endsection
