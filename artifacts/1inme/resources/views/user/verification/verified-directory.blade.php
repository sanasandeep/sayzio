@extends($directoryLayout)
@section('title','Verified profiles & links')
@section('content')
<div class="max-w-6xl mx-auto space-y-5">
    <div><h1 class="text-2xl font-bold">Verified profiles &amp; links</h1><p style="color:var(--text-muted)">Review active verification across personal profiles, workspace profiles and every link type. Revoking verification does not delete the page.</p></div>
    <nav class="flex flex-wrap gap-2" aria-label="Verification directory">
        @foreach(['accounts'=>'Personal profiles','workspaces'=>'Workspace profiles','links'=>'All link types'] as $key=>$label)
        <a href="{{ route($directoryPrefix.'.index',['kind'=>$key]) }}" aria-current="{{ $kind === $key ? 'page' : 'false' }}" class="rounded-xl border px-4 py-2 {{ $kind === $key ? 'font-bold' : '' }}">{{ $label }}</a>
        @endforeach
        @if(!request()->routeIs('admin.*'))
        <a class="rounded-xl border px-4 py-2" href="{{ route('user.profile-verification.admin.index') }}">Profile requests</a>
        <a class="rounded-xl border px-4 py-2" href="{{ route('user.verification.admin') }}">Link requests</a>
        @endif
    </nav>
    <form method="GET" class="flex gap-2"><input type="hidden" name="kind" value="{{ $kind }}"><label class="sr-only" for="verified-search">Search verified items</label><input id="verified-search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Search name or handle…" class="rounded-xl border p-3 flex-1 min-w-0" style="background:var(--bg-input)"><button class="rounded-xl border px-4">Search</button></form>
    @forelse($items as $item)
    <article class="rounded-2xl border p-5" style="background:var(--bg-card);border-color:var(--border-soft)">
        <div class="flex flex-wrap justify-between gap-3"><div><h2 class="font-bold">{{ $kind === 'links' ? ($item->title ?: $item->alias) : ($item->name ?: $item->workspace?->name) }}</h2><p class="text-sm" style="color:var(--text-muted)">{{ $kind === 'links' ? $item->type : ($kind === 'workspaces' ? $item->workspace?->name : 'Personal account') }} · {{ $kind === 'links' ? $item->alias : '@'.$item->handle }}</p></div><a class="underline" target="_blank" rel="noopener" href="{{ $kind === 'links' ? $item->getShortUrl() : url('/@'.$item->handle) }}">View page</a></div>
        <details class="mt-4"><summary class="cursor-pointer text-sm font-semibold">Remove verification</summary><form method="POST" action="{{ route($directoryPrefix.'.revoke',['kind'=>$kind,'id'=>$item->id]) }}" class="mt-3 space-y-2">@csrf<label class="block text-sm" for="reason-{{ $item->id }}">Reason for removal (recorded in the audit history)</label><textarea required maxlength="2000" id="reason-{{ $item->id }}" name="reason" rows="2" class="w-full border rounded-xl p-3" style="background:var(--bg-input)"></textarea><button class="rounded-xl px-4 py-2 bg-red-600 text-white">Revoke verification</button></form></details>
    </article>
    @empty<p class="rounded-xl border p-6">No verified items found.</p>@endforelse
    {{ $items->links() }}
</div>
@endsection
