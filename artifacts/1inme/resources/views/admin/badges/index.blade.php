@extends('admin.layouts.app')
@section('title', 'Account Badges')
@section('page-title', 'Account Badges')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-6" x-data="{ editing: null }">
    <div class="mb-6">
        <h1 class="text-2xl font-bold mb-1" style="color: var(--text-primary);">Account badges</h1>
        <p class="text-sm" style="color: var(--text-dimmed);">
            Staff-only labels you can attach to user accounts to segment, filter, and bulk-action
            the user list. Badges show on a user's own dashboard but never on their public biolink.
            This is separate from the link verification checkmark.
        </p>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm ak-green">
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="mb-4 px-4 py-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-200 text-sm ak-red">
            {{ $errors->first() }}
        </div>
    @endif

    @if($canManage)
    {{-- Create a new badge. --}}
    <div class="glass rounded-2xl border border-white/10 p-6 mb-6">
        <h3 class="text-lg font-semibold text-white mb-1 ak-strong">Create badge</h3>
        <p class="text-xs text-white/40 mb-4 ak-note">Pick a name and a color. Names must be unique.</p>
        <form method="POST" action="{{ route('admin.badges.store') }}" class="flex flex-col sm:flex-row gap-3 items-stretch">
            @csrf
            <input type="text" name="name" required maxlength="60" placeholder="e.g. VIP, Partner, Flagged" value="{{ old('name') }}"
                   class="flex-1 px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-white/30 focus:ring-2 focus:ring-blue-500/40 outline-none ak-strong ak-input">
            <input type="color" name="color" value="{{ old('color', \App\Modules\Admin\Models\AccountBadge::DEFAULT_COLOR) }}"
                   class="h-11 w-16 bg-white/5 border border-white/10 rounded-xl cursor-pointer ak-input" title="Badge color">
            <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 transition whitespace-nowrap">
                <i class="fas fa-plus mr-1"></i> Create
            </button>
        </form>
    </div>
    @else
    <div class="mb-6 flex items-start gap-3 p-4 rounded-2xl bg-white/5 border border-white/10">
        <i class="fas fa-info-circle text-white/40 mt-0.5 ak-note"></i>
        <div class="text-sm text-white/60 ak-muted">You can view badges, but only staff with user-edit permission can create or change them.</div>
    </div>
    @endif

    <div class="glass rounded-2xl border border-white/10 overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-white/10 text-left text-xs uppercase tracking-wide text-white/40 ak-note">
                    <th class="px-6 py-3">Badge</th>
                    <th class="px-6 py-3">Color</th>
                    <th class="px-6 py-3">Users</th>
                    <th class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($badges as $badge)
                <tr class="border-b border-white/5">
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium"
                              style="background: {{ $badge->color }}1f; color: {{ $badge->color }};">
                            <i class="fas fa-certificate text-[10px]"></i> {{ $badge->name }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-white/50 font-mono ak-muted">{{ $badge->color }}</td>
                    <td class="px-6 py-4 text-sm text-white/60 ak-muted">{{ $badge->users_count }}</td>
                    <td class="px-6 py-4 text-right">
                        @if($canManage)
                        <button type="button" class="text-white/30 hover:text-blue-300 mr-3 ak-note" title="Edit"
                                @click="editing = (editing === {{ $badge->id }} ? null : {{ $badge->id }})">
                            <i class="fas fa-pen"></i>
                        </button>
                        <form action="{{ route('admin.badges.destroy', $badge) }}" method="POST" class="inline"
                              onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete badge?', message: 'It will be removed from every account it is assigned to.', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-white/30 hover:text-red-400 ak-note" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                        @else
                        <span class="text-white/20 ak-note">-</span>
                        @endif
                    </td>
                </tr>
                @if($canManage)
                <tr x-show="editing === {{ $badge->id }}" x-cloak>
                    <td colspan="4" class="px-6 py-4 bg-white/[0.02]">
                        <form method="POST" action="{{ route('admin.badges.update', $badge) }}" class="flex flex-col sm:flex-row gap-3 items-stretch">
                            @csrf @method('PUT')
                            <input type="text" name="name" required maxlength="60" value="{{ $badge->name }}"
                                   class="flex-1 px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-blue-500/40 outline-none ak-strong ak-input">
                            <input type="color" name="color" value="{{ $badge->color }}"
                                   class="h-11 w-16 bg-white/5 border border-white/10 rounded-xl cursor-pointer ak-input">
                            <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 transition whitespace-nowrap">Save</button>
                            <button type="button" class="px-5 py-2.5 bg-white/5 text-white/60 rounded-xl hover:bg-white/10 transition ak-muted" @click="editing = null">Cancel</button>
                        </form>
                    </td>
                </tr>
                @endif
                @empty
                <tr><td colspan="4" class="px-6 py-8 text-center text-white/30 ak-note">No badges yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
