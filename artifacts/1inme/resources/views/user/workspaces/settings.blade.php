@extends('user.layouts.app')

@section('title', $workspace->name . ': Workspace settings')

@section('content')
@php
    $isPersonal   = $workspace->is_personal;
    $canDelete    = !$isPersonal && $ownedCount > 1;
    $currentIcon  = ($workspace->settings['appearance']['icon'] ?? null) ?: ($isPersonal ? 'user' : 'users');
    $currentColor = ($workspace->settings['appearance']['color'] ?? null) ?: ($isPersonal ? '#3d6bff' : '#10b981');
@endphp
<div class="max-w-2xl mx-auto px-4 py-8"
     x-data="{
         icon: '{{ $currentIcon }}',
         color: '{{ $currentColor }}',
         showDelete: false,
         confirmName: '',
         deleteError: ''
     }">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                {{ $workspace->name }}, Settings
            </h1>
            <p class="text-sm opacity-70 mt-1">Rename, change the icon or colour, or delete this workspace.</p>
        </div>
        <a href="{{ route('user.team.index') }}"
           class="px-3 py-2 rounded-lg text-sm font-semibold border glass-hover"
           style="border-color: var(--border-strong); color: var(--text-primary);">
            <i class="fas fa-users mr-1"></i> Team
        </a>
    </div>

    @if(!empty($autoSwitched))
        <div class="mb-4 p-3 rounded bg-blue-100 text-blue-800 text-sm flex items-start gap-2">
            <i class="fas fa-circle-info mt-0.5"></i>
            <span>You're now editing <strong>{{ $workspace->name }}</strong>. Your active workspace has been switched to it, so the sidebar and the rest of the app now match this page.</span>
        </div>
    @endif
    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-green-100 text-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 rounded bg-red-100 text-red-800 text-sm">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-3 rounded bg-red-100 text-red-800 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ── Name & appearance card ────────────────────────────────────────── --}}
    <div class="rounded-xl border mb-6" style="border-color: var(--border-strong); background: var(--bg-card);">
        <div class="px-6 py-4 border-b" style="border-color: var(--border-strong);">
            <h2 class="text-base font-semibold" style="color: var(--text-primary);">Name &amp; appearance</h2>
        </div>
        <form method="POST" action="{{ route('user.workspaces.update', $workspace) }}" class="px-6 py-5 flex flex-col gap-5">
            @csrf
            @method('PUT')
            <input type="hidden" name="icon"  :value="icon">
            <input type="hidden" name="color" :value="color">

            {{-- Live preview badge --}}
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-lg flex-shrink-0 transition-colors"
                     :style="'background:' + color">
                    @foreach(\App\Modules\User\Models\Workspace::ICON_CHOICES as $key => $fa)
                        <i class="fas {{ $fa }}" x-show="icon === '{{ $key }}'" x-cloak></i>
                    @endforeach
                    {{-- fallback shown while Alpine boots --}}
                    <i class="fas {{ $workspace->iconSymbol() }}" x-cloak style="display:none"></i>
                </div>
                <div>
                    <span class="text-sm font-semibold" style="color: var(--text-primary);" x-text="$el.closest('form').querySelector('[name=name]').value || '{{ addslashes($workspace->name) }}'"></span>
                    <span class="block text-xs opacity-60">
                        {{ $isPersonal ? 'Personal workspace' : 'Team workspace' }}
                    </span>
                </div>
            </div>

            {{-- Name --}}
            <div class="flex flex-col gap-1">
                <label for="ws-name" class="text-sm font-semibold" style="color: var(--text-primary);">
                    Workspace name
                </label>
                <input id="ws-name" type="text" name="name"
                       value="{{ old('name', $workspace->name) }}"
                       required maxlength="120"
                       class="w-full px-3 py-2 text-sm border rounded-lg"
                       style="background: var(--bg-card); border-color: var(--border-strong); color: var(--text-primary);">
            </div>

            {{-- Symbol picker --}}
            <div class="flex flex-col gap-2">
                <span class="text-sm font-semibold" style="color: var(--text-primary);">Symbol</span>
                <div class="grid grid-cols-6 sm:grid-cols-12 gap-2">
                    @foreach(\App\Modules\User\Models\Workspace::ICON_CHOICES as $key => $fa)
                        <button type="button" @click="icon = '{{ $key }}'"
                                :class="icon === '{{ $key }}' ? 'ring-2 ring-offset-1' : 'opacity-60 hover:opacity-100'"
                                class="w-9 h-9 rounded-lg flex items-center justify-center text-sm transition-all"
                                :style="icon === '{{ $key }}' ? 'background:' + color + ';color:#fff;' : 'background:var(--bg-card);color:var(--text-primary);border:1px solid var(--border-strong);'"
                                title="{{ ucwords(str_replace('-', ' ', $key)) }}">
                            <i class="fas {{ $fa }}"></i>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Colour picker --}}
            <div class="flex flex-col gap-2">
                <span class="text-sm font-semibold" style="color: var(--text-primary);">Colour</span>
                <div class="flex flex-wrap gap-2">
                    @foreach(\App\Modules\User\Models\Workspace::COLOR_CHOICES as $swatch)
                        <button type="button" @click="color = '{{ $swatch }}'"
                                class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 transition-all"
                                :class="color === '{{ $swatch }}' ? 'ring-2 ring-offset-2' : ''"
                                style="background: {{ $swatch }};"
                                title="{{ $swatch }}">
                            <i class="fas fa-check text-[9px] text-white" x-show="color === '{{ $swatch }}'"></i>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center gap-3 pt-1">
                <button type="submit"
                        class="px-5 py-2 bg-primary-600 text-white rounded-lg text-sm font-semibold hover:bg-primary-700 transition-colors">
                    Save changes
                </button>
            </div>
        </form>
    </div>

    {{-- ── Caller ID card ────────────────────────────────────────────────── --}}
    @php
        $cidCfg      = $workspace->callerIdConfig();
        $cidResolved = $workspace->resolvedCallerId();
        $brandKit    = \App\Modules\User\Models\BrandKit::defaultFor((int) $workspace->owner_user_id);
    @endphp
    <div class="rounded-xl border mb-6" style="border-color: var(--border-strong); background: var(--bg-card);"
         x-data="{ cidType: '{{ $cidCfg['type'] }}', cidAutoSync: {{ $cidCfg['brand']['auto_sync'] ? 'true' : 'false' }} }">
        <div class="px-6 py-4 border-b" style="border-color: var(--border-strong);">
            <h2 class="text-base font-semibold" style="color: var(--text-primary);">Caller ID</h2>
            <p class="text-xs opacity-60 mt-0.5">Choose how calls from this workspace appear to other Sayzio users. If they've saved you as a contact, their saved name always wins.</p>
        </div>
        <form method="POST" action="{{ route('user.workspaces.caller-id.update', $workspace) }}" class="px-6 py-5 flex flex-col gap-4">
            @csrf
            @method('PUT')

            <label class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer"
                   :style="cidType === 'personal' ? 'border-color: var(--accent);' : 'border-color: var(--border-strong);'">
                <input type="radio" name="type" value="personal" x-model="cidType" class="mt-1">
                <span>
                    <span class="block text-sm font-semibold" style="color: var(--text-primary);">Personal</span>
                    <span class="block text-xs opacity-70">Show your own name, photo and biolink (default).</span>
                </span>
            </label>

            <label class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer"
                   :style="cidType === 'brand' ? 'border-color: var(--accent);' : 'border-color: var(--border-strong);'">
                <input type="radio" name="type" value="brand" x-model="cidType" class="mt-1">
                <span>
                    <span class="block text-sm font-semibold" style="color: var(--text-primary);">Brand</span>
                    <span class="block text-xs opacity-70">Show this workspace's brand name, logo and tagline instead.</span>
                </span>
            </label>

            <div x-show="cidType === 'brand'" x-cloak class="flex flex-col gap-4 pl-1">
                <label class="flex items-center gap-2 text-sm" style="color: var(--text-primary);">
                    <input type="hidden" name="brand_auto_sync" :value="cidAutoSync ? 1 : 0">
                    <input type="checkbox" x-model="cidAutoSync">
                    <span>Auto-sync from my Brand Kit
                        @if($brandKit)
                            <span class="opacity-60">(currently “{{ $brandKit->name }}”)</span>
                        @else
                            <span class="opacity-60">(no Brand Kit yet — fields below are used)</span>
                        @endif
                    </span>
                </label>
                <p class="text-xs opacity-60 -mt-2">With auto-sync on, blank fields below follow your Brand Kit automatically; anything you type here overrides it.</p>

                <div class="flex flex-col gap-1">
                    <label class="text-sm font-semibold" style="color: var(--text-primary);">Brand name</label>
                    <input type="text" name="brand_name" maxlength="120"
                           value="{{ old('brand_name', $cidCfg['brand']['name']) }}"
                           placeholder="{{ $brandKit?->name ?: $workspace->name }}"
                           class="w-full px-3 py-2 text-sm border rounded-lg"
                           style="background: var(--bg-card); border-color: var(--border-strong); color: var(--text-primary);">
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-semibold" style="color: var(--text-primary);">Logo URL</label>
                    <input type="url" name="brand_logo_url" maxlength="2048"
                           value="{{ old('brand_logo_url', $cidCfg['brand']['logo_url']) }}"
                           placeholder="https://…"
                           class="w-full px-3 py-2 text-sm border rounded-lg"
                           style="background: var(--bg-card); border-color: var(--border-strong); color: var(--text-primary);">
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-semibold" style="color: var(--text-primary);">Tagline</label>
                    <input type="text" name="brand_tagline" maxlength="160"
                           value="{{ old('brand_tagline', $cidCfg['brand']['tagline']) }}"
                           placeholder="A short line shown under your brand name"
                           class="w-full px-3 py-2 text-sm border rounded-lg"
                           style="background: var(--bg-card); border-color: var(--border-strong); color: var(--text-primary);">
                </div>

                @if($cidResolved['type'] === 'brand')
                    <div class="flex items-center gap-3 p-3 rounded-lg border" style="border-color: var(--border-strong);">
                        @if(!empty($cidResolved['logo_url']))
                            <img src="{{ $cidResolved['logo_url'] }}" alt="" class="w-10 h-10 rounded-lg object-cover flex-shrink-0">
                        @else
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style="background: var(--border-strong);">
                                <i class="fas fa-building text-sm opacity-70"></i>
                            </div>
                        @endif
                        <div class="min-w-0">
                            <span class="block text-sm font-semibold truncate" style="color: var(--text-primary);">{{ $cidResolved['name'] }}</span>
                            @if(!empty($cidResolved['tagline']))
                                <span class="block text-xs opacity-60 truncate">{{ $cidResolved['tagline'] }}</span>
                            @endif
                        </div>
                        <span class="ml-auto text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full flex-shrink-0" style="background: var(--border-strong); color: var(--text-primary);">Preview</span>
                    </div>
                @endif
            </div>

            <div class="pt-1">
                <button type="submit"
                        class="px-5 py-2 bg-primary-600 text-white rounded-lg text-sm font-semibold hover:bg-primary-700 transition-colors">
                    Save caller ID
                </button>
            </div>
        </form>
    </div>

    {{-- ── Danger zone ───────────────────────────────────────────────────── --}}
    <div class="rounded-xl border" style="border-color: var(--border-strong); background: var(--bg-card);">
        <div class="px-6 py-4 border-b" style="border-color: var(--border-strong);">
            <h2 class="text-base font-semibold text-red-600">Danger zone</h2>
        </div>
        <div class="px-6 py-5">
            @if($isPersonal)
                <p class="text-sm opacity-70">Your <strong>personal workspace</strong> cannot be deleted.</p>
            @elseif(!$canDelete)
                <p class="text-sm opacity-70">This is your only workspace, you cannot delete it. Create another workspace first if you want to remove this one.</p>
            @else
                @if(auth()->user()->canTransferAssets())
                {{-- Admin-granted cross-account transfer. Capability +
                     ownership are re-checked server-side. --}}
                <div class="flex items-start justify-between gap-4 flex-wrap mb-6 pb-6 border-b" style="border-color: var(--border-strong);"
                     x-data="{ showTransfer: false }">
                    <div>
                        <p class="text-sm font-semibold" style="color: var(--text-primary);">Transfer this workspace</p>
                        <p class="text-sm opacity-70 mt-0.5">
                            Move this workspace, its links and data to another user's account. Instant and cannot be undone.
                        </p>
                    </div>
                    <button type="button" @click="showTransfer = true"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors flex-shrink-0">
                        Transfer workspace
                    </button>

                    <div x-show="showTransfer" x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center p-4"
                         style="background: rgba(0,0,0,.6);"
                         @keydown.escape.window="showTransfer = false">
                        <div class="w-full max-w-md rounded-2xl border p-6 shadow-2xl"
                             style="background: var(--bg-card); border-color: var(--border-strong);"
                             @click.stop>
                            <h3 class="text-lg font-bold mb-2" style="color: var(--text-primary);">Transfer workspace</h3>
                            <p class="text-sm opacity-70 mb-4">
                                Enter the email of the account that should become the new owner of <strong>{{ $workspace->name }}</strong>. All links and workspace data move with it. This is instant and cannot be undone.
                            </p>
                            <form method="POST" action="{{ route('user.workspaces.transfer', $workspace) }}">
                                @csrf
                                <input type="email" name="recipient_email" required
                                       placeholder="recipient@example.com"
                                       class="w-full px-3 py-2 text-sm border rounded-lg mb-4"
                                       style="background: var(--bg-card); border-color: var(--border-strong); color: var(--text-primary);">
                                <div class="flex items-center gap-3">
                                    <button type="submit"
                                            class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">
                                        Transfer ownership
                                    </button>
                                    <button type="button" @click="showTransfer = false"
                                            class="px-4 py-2 rounded-lg text-sm border"
                                            style="border-color: var(--border-strong); color: var(--text-primary);">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endif

                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <p class="text-sm font-semibold" style="color: var(--text-primary);">Delete this workspace</p>
                        <p class="text-sm opacity-70 mt-0.5">
                            All members will lose access immediately. This action cannot be undone.
                        </p>
                    </div>
                    <button type="button" @click="showDelete = true; confirmName = ''; deleteError = ''"
                            class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700 transition-colors flex-shrink-0">
                        Delete workspace
                    </button>
                </div>

                {{-- Confirmation modal --}}
                <div x-show="showDelete" x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center p-4"
                     style="background: rgba(0,0,0,.6);"
                     @keydown.escape.window="showDelete = false">
                    <div class="w-full max-w-md rounded-2xl border p-6 shadow-2xl"
                         style="background: var(--bg-card); border-color: var(--border-strong);"
                         @click.stop>
                        <h3 class="text-lg font-bold mb-2" style="color: var(--text-primary);">Delete workspace</h3>
                        <p class="text-sm opacity-70 mb-4">
                            Type <strong>{{ $workspace->name }}</strong> to confirm you want to permanently delete this workspace and remove all members.
                        </p>

                        <input type="text" x-model="confirmName"
                               placeholder="{{ $workspace->name }}"
                               class="w-full px-3 py-2 text-sm border rounded-lg mb-1"
                               style="background: var(--bg-card); border-color: var(--border-strong); color: var(--text-primary);">
                        <p x-show="deleteError" x-text="deleteError" class="text-xs text-red-600 mb-3"></p>

                        <div class="flex items-center gap-3 mt-4">
                            <form method="POST" action="{{ route('user.workspaces.destroy', $workspace) }}"
                                  id="ws-delete-form">
                                @csrf
                                @method('DELETE')
                            </form>
                            <button type="button"
                                    @click="if(confirmName.trim() !== '{{ addslashes($workspace->name) }}') { deleteError = 'Name does not match.'; } else { $el.closest('.fixed').querySelector('#ws-delete-form').submit(); }"
                                    class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700 transition-colors">
                                Yes, delete permanently
                            </button>
                            <button type="button" @click="showDelete = false"
                                    class="px-4 py-2 rounded-lg text-sm border"
                                    style="border-color: var(--border-strong); color: var(--text-primary);">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
