@extends('admin.layouts.app', ['pageTitle' => 'Adult content moderation'])

@section('content')
<div class="max-w-6xl mx-auto py-6 px-4">

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Adult content moderation</h1>
            <p class="text-xs text-slate-500 mt-1">
                Review profiles flagged 18+. Suspending the flag flips the public surfaces back
                to SFW; the creator's consent + age-affirmation audit trail is preserved.
            </p>
        </div>

        <form method="GET" class="flex gap-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="text" name="q" value="{{ $q }}" placeholder="Search name, handle, email"
                   class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm">
            <button class="px-3 py-1.5 rounded-lg bg-slate-900 text-white text-xs font-semibold">Search</button>
        </form>
    </div>

    <div class="border-b border-slate-200 mb-4 flex gap-1">
        @php $tabs = ['enabled' => 'Enabled', 'suspended' => 'Suspended']; @endphp
        @foreach($tabs as $key => $label)
            <a href="{{ route('admin.adult-moderation.index', ['tab' => $key]) }}"
               class="px-4 py-2 text-sm font-semibold border-b-2 -mb-px {{ $tab === $key ? 'border-rose-600 text-rose-700' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
                {{ $label }} <span class="ml-1 text-xs text-slate-400">({{ $counts[$key] }})</span>
            </a>
        @endforeach
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="text-left px-4 py-3">Creator</th>
                    <th class="text-left px-4 py-3">Status</th>
                    <th class="text-left px-4 py-3">Enabled</th>
                    <th class="text-right px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($users as $u)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @if($u->avatar)
                                    <img src="{{ $u->avatar }}" class="w-8 h-8 rounded-full object-cover" alt="">
                                @else
                                    <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-500 inline-flex items-center justify-center text-xs font-bold">
                                        {{ strtoupper(substr($u->name ?? '?', 0, 1)) }}
                                    </div>
                                @endif
                                <div>
                                    <div class="font-semibold text-slate-900">{{ $u->name }}</div>
                                    <div class="text-xs text-slate-500">
                                        @if($u->handle)
                                            <a href="{{ url('/@' . $u->handle) }}" target="_blank" class="hover:underline">&#64;{{ $u->handle }}</a>
                                            &middot;
                                        @endif
                                        {{ $u->email }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if($u->adult_flag_suspended_at)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 text-[11px] font-bold uppercase">
                                    <i class="fas fa-shield-halved"></i> Suspended
                                </span>
                                @if($u->adult_flag_suspended_reason)
                                    <div class="text-xs text-slate-500 mt-1 max-w-md">{{ $u->adult_flag_suspended_reason }}</div>
                                @endif
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[11px] font-bold uppercase">
                                    <i class="fas fa-fire"></i> 18+
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            @if($u->adult_content_enabled_at){{ $u->adult_content_enabled_at->diffForHumans() }}@else &mdash; @endif
                            @if($u->age_verified_at)
                                <div class="text-[11px] text-slate-400">age-affirmed {{ $u->age_verified_at->diffForHumans() }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if($u->adult_flag_suspended_at)
                                <form method="POST" action="{{ route('admin.adult-moderation.restore', ['user' => $u->id]) }}" class="inline">
                                    @csrf
                                    <button class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-500">
                                        <i class="fas fa-rotate-left"></i> Restore
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.adult-moderation.suspend', ['user' => $u->id]) }}" class="inline-flex items-center gap-2"
                                      onsubmit="return confirm('Suspend the 18+ flag for @{{ $u->handle }}? Their profile will be treated as SFW until restored.');">
                                    @csrf
                                    <input type="text" name="reason" required maxlength="500" placeholder="Reason"
                                           class="px-2 py-1 rounded border border-slate-200 text-xs w-44">
                                    <button class="px-3 py-1.5 rounded-lg bg-rose-600 text-white text-xs font-semibold hover:bg-rose-500">
                                        <i class="fas fa-shield-halved"></i> Suspend
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-12 text-center text-slate-500">No profiles in this view.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
</div>
@endsection
