@extends('admin.layouts.app')
@section('title', 'Custom Domains')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Custom Domains</h1>
            <p class="text-xs mt-1" style="color: var(--text-faint);">Admin-global domains are selectable by users on tagged plans. User-owned domains appear here for reference and can be deactivated if abused.</p>
        </div>
    </div>

    @if($errors->any())
        <div class="mb-4 p-3 rounded-xl text-red-400 text-xs" style="background: rgba(239,68,68,0.06); border:1px solid rgba(239,68,68,0.15);">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    @if(session('success'))
        <div class="mb-4 p-3 rounded-xl text-emerald-400 text-xs" style="background: rgba(16,185,129,0.06); border:1px solid rgba(16,185,129,0.15);">{{ session('success') }}</div>
    @endif

    {{-- ============ Add new global domain ============ --}}
    <div class="rounded-2xl mb-6 overflow-hidden" style="background: var(--bg-card); border:1px solid var(--border-strong);" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }">
        <button type="button" @click="open = !open" class="w-full flex items-center justify-between px-6 py-4 text-left">
            <span class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: rgba(61,107,255,0.12); border:1px solid rgba(61,107,255,0.25);"><i class="fas fa-globe text-sm text-blue-400"></i></span>
                <span>
                    <span class="block text-base font-semibold" style="color: var(--text-primary);">Add Global Domain</span>
                    <span class="block text-[11px]" style="color: var(--text-faint);">Make a new platform domain selectable when users create links</span>
                </span>
            </span>
            <i class="fas fa-chevron-down text-xs transition-transform" :class="open && 'rotate-180'" style="color: var(--text-faint);"></i>
        </button>

        <form x-show="open" method="POST" action="{{ route('admin.domains.store') }}" enctype="multipart/form-data" class="px-6 pb-6 space-y-5" style="border-top:1px solid var(--border-subtle);" @if(!$errors->any()) x-cloak @endif>
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-5">
                <label class="block">
                    <span class="text-[11px] font-medium uppercase tracking-wide" style="color: var(--text-muted);">Domain</span>
                    <input type="text" name="domain" value="{{ old('domain') }}" placeholder="links.example.com" required
                           class="mt-1.5 w-full px-3 py-2.5 rounded-xl text-sm" style="background: var(--bg-input); border:1px solid var(--border-subtle); color: var(--text-primary);">
                </label>
                <label class="block">
                    <span class="text-[11px] font-medium uppercase tracking-wide" style="color: var(--text-muted);">CNAME target <span class="normal-case font-normal">(optional, defaults to your app host)</span></span>
                    <input type="text" name="cname_target" value="{{ old('cname_target') }}" placeholder="{{ parse_url(config('app.url'), PHP_URL_HOST) ?: 'your-app-host' }}"
                           class="mt-1.5 w-full px-3 py-2.5 rounded-xl text-sm" style="background: var(--bg-input); border:1px solid var(--border-subtle); color: var(--text-primary);">
                </label>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <span class="text-[11px] font-medium uppercase tracking-wide" style="color: var(--text-muted);">Limit to plans</span>
                    <div class="flex flex-wrap gap-1.5 mt-1.5">
                        @foreach($plans as $plan)
                            <label class="admin-chip">
                                <input type="checkbox" name="plan_ids[]" value="{{ $plan->id }}" class="sr-only">
                                <span class="chip-face">{{ $plan->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <span class="text-[11px] font-medium uppercase tracking-wide" style="color: var(--text-muted);">Limit to badges</span>
                    <div class="flex flex-wrap gap-1.5 mt-1.5">
                        @forelse($badges as $badge)
                            <label class="admin-chip">
                                <input type="checkbox" name="badge_ids[]" value="{{ $badge->id }}" class="sr-only">
                                <span class="chip-face">{{ $badge->name }}</span>
                            </label>
                        @empty
                            <span class="text-[11px]" style="color: var(--text-faint);">No badges defined.</span>
                        @endforelse
                    </div>
                </div>
            </div>
            <p class="text-[11px] -mt-2" style="color: var(--text-faint);">Leave every tag unticked to open the domain to <strong>everyone</strong>; otherwise it's offered to accounts matching any ticked plan <strong>or</strong> badge.</p>

            <div class="rounded-xl p-4 space-y-3" style="background: var(--bg-input); border:1px solid var(--border-subtle);">
                <span class="text-[11px] font-medium uppercase tracking-wide" style="color: var(--text-muted);">Branding (optional, non-primary domains only)</span>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    @foreach([['logo_light','Light logo','image/*'],['logo_dark','Dark logo','image/*'],['icon','Icon','image/*,.ico']] as [$f,$l,$a])
                        <label class="admin-file-slot" x-data="{ name: '' }">
                            <input type="file" name="{{ $f }}" accept="{{ $a }}" class="sr-only" @change="name = $event.target.files[0]?.name || ''">
                            <i class="fas fa-upload text-[10px]"></i>
                            <span class="truncate" x-text="name || '{{ $l }}'"></span>
                        </label>
                    @endforeach
                </div>
                <textarea name="relationship_blurb" rows="2" maxlength="500" placeholder="Landing-page relationship blurb, e.g. &ldquo;sayzio.app is part of 1in.me.&rdquo; Leave blank for a sensible default."
                          class="w-full px-3 py-2 rounded-xl text-xs" style="background: var(--bg-card); border:1px solid var(--border-subtle); color: var(--text-primary);">{{ old('relationship_blurb') }}</textarea>
                <p class="text-[11px]" style="color: var(--text-faint);">Blank logo slots fall back to the platform logo.</p>
            </div>

            <div class="flex items-center justify-between gap-3 pt-1">
                <label class="flex items-center gap-2 text-xs cursor-pointer" style="color: var(--text-muted);">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded"> Active immediately
                </label>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white"><i class="fas fa-plus mr-1.5 text-xs"></i>Add domain</button>
            </div>
        </form>
    </div>

    {{-- ============ Global domains list ============ --}}
    <div class="mb-6">
        <h2 class="text-base font-semibold mb-3" style="color: var(--text-primary);">Global Domains <span class="text-xs font-normal" style="color: var(--text-faint);">({{ $domains->count() }})</span></h2>

        <div class="space-y-4">
        @forelse($domains as $d)
            <div class="rounded-2xl overflow-hidden" style="background: var(--bg-card); border:1px solid var(--border-strong);" x-data="{ expanded: false }">
                {{-- Card header --}}
                <div class="flex flex-wrap items-center gap-3 px-5 py-4">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0" style="background: {{ $d->is_primary ? 'rgba(61,107,255,0.12)' : 'var(--bg-input)' }}; border:1px solid {{ $d->is_primary ? 'rgba(61,107,255,0.3)' : 'var(--border-subtle)' }};">
                        <i class="fas {{ $d->is_primary ? 'fa-star text-blue-400' : 'fa-globe' }} text-sm" @unless($d->is_primary) style="color: var(--text-faint);" @endunless></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-sm font-semibold" style="color: var(--text-primary);">{{ $d->domain }}</span>
                            @if($d->is_verified)
                                <span class="admin-pill" style="background: rgba(16,185,129,0.1); border-color: rgba(16,185,129,0.25); color:#34d399;"><i class="fas fa-check mr-1 text-[8px]"></i>verified</span>
                            @else
                                <span class="admin-pill" style="background: rgba(245,158,11,0.1); border-color: rgba(245,158,11,0.25); color:#fbbf24;"><i class="fas fa-clock mr-1 text-[8px]"></i>unverified</span>
                            @endif
                            @if($d->is_primary)
                                <span class="admin-pill" style="background: rgba(61,107,255,0.12); border-color: rgba(61,107,255,0.3); color:#93b4ff;"><i class="fas fa-star mr-1 text-[8px]"></i>primary</span>
                            @endif
                            @if(!$d->is_active)
                                <span class="admin-pill" style="background: rgba(239,68,68,0.1); border-color: rgba(239,68,68,0.25); color:#f87171;">inactive</span>
                            @endif
                        </div>
                        <p class="text-[11px] mt-0.5 truncate" style="color: var(--text-faint);">
                            @php $tagNames = $d->plans->pluck('name')->merge($d->badges->pluck('name')); @endphp
                            {{ $tagNames->isEmpty() ? 'Open to everyone' : 'Limited to: ' . $tagNames->join(', ') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        @if(!$d->is_verified)
                            <button type="submit" form="vfy-{{ $d->id }}" class="px-3 py-1.5 rounded-lg text-xs bg-blue-600 hover:bg-blue-700 text-white" title="Check CNAME via DNS">Verify</button>
                        @endif
                        <button type="button" @click="expanded = !expanded" class="px-3 py-1.5 rounded-lg text-xs" style="background: var(--bg-input); border:1px solid var(--border-subtle); color: var(--text-muted);">
                            <span x-text="expanded ? 'Close' : 'Edit'">Edit</span>
                            <i class="fas fa-chevron-down ml-1 text-[9px] transition-transform" :class="expanded && 'rotate-180'"></i>
                        </button>
                    </div>
                </div>

                {{-- Expanded editor --}}
                <form x-show="expanded" x-cloak method="POST" action="{{ route('admin.domains.update', $d) }}" enctype="multipart/form-data" class="px-5 pb-5 space-y-4" style="border-top:1px solid var(--border-subtle);">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4">
                        <label class="block">
                            <span class="text-[11px] font-medium uppercase tracking-wide" style="color: var(--text-muted);">CNAME target</span>
                            <input type="text" name="cname_target" value="{{ $d->cname_target }}" placeholder="defaults to your app host"
                                   class="mt-1.5 w-full px-3 py-2 rounded-xl text-sm" style="background: var(--bg-input); border:1px solid var(--border-subtle); color: var(--text-primary);">
                        </label>
                        <div class="flex items-end pb-1">
                            <label class="flex items-center gap-2 text-xs cursor-pointer" style="color: var(--text-muted);">
                                <input type="checkbox" name="is_active" value="1" @checked($d->is_active) class="rounded"> Active, offered in the create-link domain picker
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <span class="text-[11px] font-medium uppercase tracking-wide" style="color: var(--text-muted);">Limit to plans</span>
                            <div class="flex flex-wrap gap-1.5 mt-1.5">
                                @foreach($plans as $plan)
                                    <label class="admin-chip">
                                        <input type="checkbox" name="plan_ids[]" value="{{ $plan->id }}" class="sr-only" @checked($d->plans->contains($plan->id))>
                                        <span class="chip-face">{{ $plan->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <span class="text-[11px] font-medium uppercase tracking-wide" style="color: var(--text-muted);">Limit to badges</span>
                            <div class="flex flex-wrap gap-1.5 mt-1.5">
                                @forelse($badges as $badge)
                                    <label class="admin-chip">
                                        <input type="checkbox" name="badge_ids[]" value="{{ $badge->id }}" class="sr-only" @checked($d->badges->contains($badge->id))>
                                        <span class="chip-face">{{ $badge->name }}</span>
                                    </label>
                                @empty
                                    <span class="text-[11px]" style="color: var(--text-faint);">No badges defined.</span>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    @if($d->is_primary)
                        <p class="text-[11px] rounded-xl px-3 py-2" style="color: var(--text-faint); background: var(--bg-input); border:1px solid var(--border-subtle);"><i class="fas fa-info-circle mr-1"></i>This is the primary domain, it always uses the platform logo and shows no relationship section.</p>
                    @else
                        <div class="rounded-xl p-4 space-y-3" style="background: var(--bg-input); border:1px solid var(--border-subtle);">
                            <span class="text-[11px] font-medium uppercase tracking-wide" style="color: var(--text-muted);">Branding</span>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                @php
                                    $__slots = [
                                        ['logo_light', 'Light logo', $d->brand_logo_light_url, 'bg-white',     'image/*'],
                                        ['logo_dark',  'Dark logo',  $d->brand_logo_dark_url,  'bg-slate-800', 'image/*'],
                                        ['icon',       'Icon',       $d->brand_icon_url,       'bg-white',     'image/*,.ico'],
                                    ];
                                @endphp
                                @foreach($__slots as [$__field, $__label, $__cur, $__bg, $__accept])
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div class="w-12 h-9 rounded-lg flex items-center justify-center shrink-0 {{ $__bg }}" style="border:1px solid var(--border-subtle);">
                                            @if($__cur)
                                                <img src="{{ $__cur }}" alt="{{ $__label }}" class="max-h-7 max-w-[44px] object-contain">
                                            @else
                                                <i class="fas fa-image text-[11px]" style="color: var(--text-faint);"></i>
                                            @endif
                                        </div>
                                        <label class="admin-file-slot flex-1 min-w-0" x-data="{ name: '' }">
                                            <input type="file" name="{{ $__field }}" accept="{{ $__accept }}" class="sr-only" @change="name = $event.target.files[0]?.name || ''">
                                            <i class="fas fa-upload text-[10px]"></i>
                                            <span class="truncate" x-text="name || '{{ $__label }}'"></span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            <textarea name="relationship_blurb" rows="2" maxlength="500" placeholder="Relationship blurb shown on this domain's landing page. Leave blank for a sensible default."
                                      class="w-full px-3 py-2 rounded-xl text-xs" style="background: var(--bg-card); border:1px solid var(--border-subtle); color: var(--text-primary);">{{ $d->relationship_blurb }}</textarea>
                        </div>
                    @endif

                    <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                        <div class="flex items-center gap-1.5">
                            @unless($d->is_primary)
                                <button type="submit" form="primary-{{ $d->id }}" class="px-3 py-2 rounded-lg text-xs" style="background: var(--bg-input); border:1px solid var(--border-subtle); color: var(--text-muted);" title="Make this the platform default domain"><i class="fas fa-star mr-1 text-[9px]"></i>Make primary</button>
                                <button type="submit" form="del-{{ $d->id }}" class="px-3 py-2 rounded-lg text-xs bg-red-600/15 text-red-400 hover:bg-red-600/25" style="border:1px solid rgba(239,68,68,0.25);" onclick="return window.themedConfirmAction(this, {title: 'Remove this domain?', message: 'Links bound to it will lose their host.', confirmText: 'Remove', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})"><i class="fas fa-trash mr-1 text-[9px]"></i>Remove</button>
                            @endunless
                        </div>
                        <button type="submit" class="px-5 py-2 rounded-lg text-xs font-medium bg-emerald-600 hover:bg-emerald-700 text-white"><i class="fas fa-check mr-1 text-[9px]"></i>Save changes</button>
                    </div>
                </form>
            </div>
            <form id="del-{{ $d->id }}" method="POST" action="{{ route('admin.domains.destroy', $d) }}">@csrf @method('DELETE')</form>
            @if(!$d->is_verified)
                <form id="vfy-{{ $d->id }}" method="POST" action="{{ route('admin.domains.verify', $d) }}">@csrf</form>
            @endif
            @unless($d->is_primary)
                <form id="primary-{{ $d->id }}" method="POST" action="{{ route('admin.domains.primary', $d) }}">@csrf</form>
            @endunless
        @empty
            <div class="rounded-2xl p-8 text-center" style="background: var(--bg-card); border:1px dashed var(--border-strong);">
                <p class="text-xs" style="color: var(--text-faint);">No global domains yet. Add one above to make it selectable on link create/edit.</p>
            </div>
        @endforelse
        </div>
    </div>

    {{-- ============ User-owned domains ============ --}}
    <div class="rounded-2xl p-6" style="background: var(--bg-card); border:1px solid var(--border-strong);">
        <h2 class="text-base font-semibold mb-4" style="color: var(--text-primary);">User-Owned Domains <span class="text-xs font-normal" style="color: var(--text-faint);">({{ $userDomains->count() }})</span></h2>
        @if($userDomains->isEmpty())
            <p class="text-xs" style="color: var(--text-faint);">No users have added their own domains yet.</p>
        @else
        <table class="w-full text-xs">
            <thead style="color: var(--text-faint);">
                <tr><th class="text-left py-2">Domain</th><th class="text-left">Owner</th><th class="text-left">Verified</th><th class="text-left">Active</th></tr>
            </thead>
            <tbody>
            @foreach($userDomains as $d)
                <tr style="border-top:1px solid var(--border-subtle); color: var(--text-primary);">
                    <td class="py-2.5 font-mono">{{ $d->domain }}</td>
                    <td>{{ $d->user?->email ?? '—' }}</td>
                    <td>{!! $d->is_verified ? '<span class="text-emerald-400">yes</span>' : '<span class="text-amber-400">pending</span>' !!}</td>
                    <td>{!! $d->is_active ? '<span class="text-emerald-400">yes</span>' : '<span class="text-red-400">no</span>' !!}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
    .admin-pill { display:inline-flex; align-items:center; font-size:10px; line-height:1; padding:3px 7px; border-radius:9999px; border:1px solid transparent; }
    .admin-chip { cursor:pointer; }
    .admin-chip .chip-face {
        display:inline-flex; align-items:center; font-size:11px; padding:5px 10px; border-radius:9999px;
        background: var(--bg-input); border:1px solid var(--border-subtle); color: var(--text-muted);
        transition: all .12s ease; user-select:none;
    }
    .admin-chip input:checked + .chip-face {
        background: rgba(61,107,255,0.15); border-color: rgba(61,107,255,0.45); color:#3d6bff;
    }
    .admin-chip input:focus-visible + .chip-face { outline:2px solid rgba(61,107,255,0.5); outline-offset:1px; }
    .admin-file-slot {
        display:inline-flex; align-items:center; gap:6px; cursor:pointer; font-size:11px;
        padding:7px 10px; border-radius:10px; max-width:100%;
        background: var(--bg-card); border:1px dashed var(--border-subtle); color: var(--text-muted);
        transition: border-color .12s ease;
    }
    .admin-file-slot:hover { border-color: rgba(61,107,255,0.45); }
</style>
@endsection
