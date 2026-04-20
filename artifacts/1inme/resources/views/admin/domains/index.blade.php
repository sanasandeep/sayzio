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

    {{-- Add new global domain --}}
    <div class="rounded-2xl p-6 mb-6" style="background: var(--bg-card); border:1px solid var(--border-strong);">
        <h2 class="text-base font-semibold mb-4" style="color: var(--text-primary);">Add Global Domain</h2>
        <form method="POST" action="{{ route('admin.domains.store') }}" class="grid grid-cols-1 md:grid-cols-12 gap-3">
            @csrf
            <input type="text" name="domain" placeholder="links.example.com" required
                   class="md:col-span-3 px-3 py-2 rounded-lg text-sm" style="background: var(--bg-input); border:1px solid var(--border-subtle); color: var(--text-primary);">
            <input type="text" name="cname_target" placeholder="CNAME target (defaults to your app host)"
                   class="md:col-span-3 px-3 py-2 rounded-lg text-sm" style="background: var(--bg-input); border:1px solid var(--border-subtle); color: var(--text-primary);">
            <select name="plan_ids[]" multiple class="md:col-span-4 px-3 py-2 rounded-lg text-sm" style="background: var(--bg-input); border:1px solid var(--border-subtle); color: var(--text-primary); min-height: 80px;">
                @foreach($plans as $plan)
                    <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                @endforeach
            </select>
            <label class="md:col-span-1 flex items-center gap-2 text-xs" style="color: var(--text-muted);">
                <input type="checkbox" name="is_active" value="1" checked> Active
            </label>
            <button type="submit" class="md:col-span-1 px-4 py-2 rounded-lg text-sm font-medium bg-violet-600 hover:bg-violet-700 text-white">Add</button>
            <p class="md:col-span-12 text-[11px]" style="color: var(--text-faint);">Hold Ctrl/Cmd to tag multiple plans. Leave blank to allow every plan to use this domain.</p>
        </form>
    </div>

    {{-- Global domains list --}}
    <div class="rounded-2xl p-6 mb-6" style="background: var(--bg-card); border:1px solid var(--border-strong);">
        <h2 class="text-base font-semibold mb-4" style="color: var(--text-primary);">Global Domains ({{ $domains->count() }})</h2>
        @forelse($domains as $d)
            <form method="POST" action="{{ route('admin.domains.update', $d) }}" class="grid grid-cols-12 gap-3 items-center py-3" style="border-top:1px solid var(--border-subtle);">
                @csrf @method('PUT')
                <div class="col-span-3 text-sm font-mono" style="color: var(--text-primary);">
                    {{ $d->domain }}
                    @if($d->is_verified)
                        <span class="text-emerald-400 text-[10px] ml-1">verified</span>
                    @else
                        <span class="text-amber-400 text-[10px] ml-1">unverified</span>
                    @endif
                </div>
                <input type="text" name="cname_target" value="{{ $d->cname_target }}" class="col-span-3 px-2 py-1.5 rounded-md text-xs" style="background: var(--bg-input); border:1px solid var(--border-subtle); color: var(--text-primary);">
                <select name="plan_ids[]" multiple class="col-span-3 px-2 py-1.5 rounded-md text-xs" style="background: var(--bg-input); border:1px solid var(--border-subtle); color: var(--text-primary); min-height: 60px;">
                    @foreach($plans as $plan)
                        <option value="{{ $plan->id }}" @selected($d->plans->contains($plan->id))>{{ $plan->name }}</option>
                    @endforeach
                </select>
                <label class="col-span-1 flex items-center gap-1 text-[11px]" style="color: var(--text-muted);">
                    <input type="checkbox" name="is_active" value="1" @checked($d->is_active)> Active
                </label>
                <button type="submit" class="col-span-1 px-3 py-1.5 rounded-md text-xs bg-emerald-600 hover:bg-emerald-700 text-white">Save</button>
                <div class="col-span-1 flex items-center gap-1">
                    @if(!$d->is_verified)
                        <button type="submit" form="vfy-{{ $d->id }}" class="px-2 py-1.5 rounded-md text-xs bg-violet-600 hover:bg-violet-700 text-white" title="Check CNAME via DNS">Verify</button>
                    @endif
                    <button type="submit" form="del-{{ $d->id }}" class="px-2 py-1.5 rounded-md text-xs bg-red-600/20 text-red-400 hover:bg-red-600/30" onclick="return confirm('Remove this domain? Links bound to it will lose their host.')">Del</button>
                </div>
            </form>
            <form id="del-{{ $d->id }}" method="POST" action="{{ route('admin.domains.destroy', $d) }}">@csrf @method('DELETE')</form>
            @if(!$d->is_verified)
                <form id="vfy-{{ $d->id }}" method="POST" action="{{ route('admin.domains.verify', $d) }}">@csrf</form>
            @endif
        @empty
            <p class="text-xs" style="color: var(--text-faint);">No global domains yet. Add one above to make it selectable on link create/edit.</p>
        @endforelse
    </div>

    {{-- User-owned domains --}}
    <div class="rounded-2xl p-6" style="background: var(--bg-card); border:1px solid var(--border-strong);">
        <h2 class="text-base font-semibold mb-4" style="color: var(--text-primary);">User-Owned Domains ({{ $userDomains->count() }})</h2>
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
                    <td class="py-2 font-mono">{{ $d->domain }}</td>
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
@endsection
