@extends('user.layouts.app')
@section('title', 'DM welcome rules')

@section('content')
<div class="max-w-5xl mx-auto">
    @include('user.partials.page-hero', [
        'title'    => 'Welcome messages',
        'subtitle' => 'Auto-DM new followers and subscribers the moment they join',
        'icon'     => 'fa-magic',
    ])

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('user.inbox.dms.welcome.store') }}" class="space-y-3 bg-white/5 border border-white/10 p-5 rounded-2xl mb-6">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <label class="text-sm">
                <span style="color: var(--text-muted);">Trigger</span>
                <select name="trigger" class="mt-1 w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
                    @foreach($triggers as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm sm:col-span-2">
                <span style="color: var(--text-muted);">Limit to tier (subscriber trigger only)</span>
                <select name="tier_id" class="mt-1 w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
                    <option value="">Any tier</option>
                    @foreach($tiers as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <label class="block text-sm">
            <span style="color: var(--text-muted);">Message</span>
            <textarea name="body" rows="4" required maxlength="5000"
                      class="mt-1 w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm"
                      placeholder="Welcome! Here's a little something just for you…"></textarea>
        </label>
        <details class="text-sm">
            <summary class="cursor-pointer text-blue-400">Attach a (lockable) file</summary>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <input type="url" name="attachment_url" placeholder="https://… (file URL)" class="px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
                <input type="url" name="attachment_thumb_url" placeholder="https://… (thumb / blur preview)" class="px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
                <select name="attachment_kind" class="px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
                    <option value="">Type</option>
                    <option value="image">Image</option>
                    <option value="gallery">Gallery</option>
                    <option value="video">Video</option>
                    <option value="audio">Audio</option>
                    <option value="voice">Voice note</option>
                    <option value="file">File</option>
                </select>
                <input type="number" name="attachment_lock_price_cents" min="0" max="100000" placeholder="Unlock price (cents), 0 = free" class="px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
            </div>
        </details>
        <label class="flex items-center gap-2 text-sm pt-1">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" checked> Active
        </label>
        <div class="flex justify-end pt-2">
            <button class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold">Add rule</button>
        </div>
    </form>

    <div class="space-y-2">
        @forelse($rules as $rule)
            <div class="bg-white/5 border border-white/10 rounded-xl p-4 flex items-start gap-4">
                <div class="flex-1 min-w-0">
                    <div class="text-xs mb-1" style="color: var(--text-faint);">
                        {{ $triggers[$rule->trigger] ?? $rule->trigger }}
                        @if($rule->tier_id)
                            · Tier #{{ $rule->tier_id }}
                        @endif
                        · Sent {{ (int)$rule->sent_count }}x
                    </div>
                    <div class="text-sm">{{ \Illuminate\Support\Str::limit($rule->body, 200, '…') }}</div>
                </div>
                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('user.inbox.dms.welcome.toggle', $rule) }}">
                        @csrf
                        <button class="px-3 py-1.5 rounded-lg {{ $rule->is_active ? 'bg-emerald-500/20 text-emerald-200 border border-emerald-400/40' : 'bg-white/10 border border-white/10 text-slate-300' }} text-xs font-semibold">
                            {{ $rule->is_active ? 'Active' : 'Disabled' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('user.inbox.dms.welcome.destroy', $rule) }}" onsubmit="return confirm('Delete this rule?');">
                        @csrf @method('DELETE')
                        <button class="px-3 py-1.5 rounded-lg bg-white/10 border border-white/10 text-rose-300 text-xs">Delete</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="text-sm" style="color: var(--text-muted);">No welcome rules yet.</div>
        @endforelse
    </div>
</div>
@endsection
