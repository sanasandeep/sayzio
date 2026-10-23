@extends('admin.layouts.app')
@section('title','Creator phone ownership')
@section('content')
<div class="max-w-6xl mx-auto space-y-5">
<h1 class="text-2xl font-bold">Creator phone ownership</h1>
<p style="color:var(--text-muted)">One number, one workspace creator profile. Approving an ownership claim atomically revokes any previous profile allocation and attaches the number to the requesting profile. Account login identifiers are unchanged.</p>
<a class="underline" href="{{ route('admin.verified-directory.index') }}">Verified profiles &amp; links</a>
<h2 class="text-lg font-bold">Ownership requests</h2>
@forelse($claims as $claim)
<article class="border rounded-2xl p-5 space-y-3" style="background:var(--bg-card);border-color:var(--border-soft)"><h3 class="font-semibold">{{ $claim->phone }} · {{ $claim->workspace_name }} · {{ $claim->status }}</h3><p>Current allocation: {{ $claim->held_workspace_name ?? 'None' }} {{ $claim->held_handle ? ' · @'.$claim->held_handle : '' }}</p><p>Target profile: {{ $claim->handle ? '@'.$claim->handle : 'No handle yet' }}</p><p class="whitespace-pre-wrap">{{ $claim->message }}</p>
@foreach(json_decode($claim->supporting_urls ?: '[]',true) as $url)<a class="block underline break-all" href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ $url }}</a>@endforeach
@foreach(json_decode($claim->proof_files ?: '[]',true) as $file)<a class="block underline" href="{{ \App\Support\PublicStorageUrl::resolve($file) }}" target="_blank" rel="noopener noreferrer">View proof {{ $loop->iteration }}</a>@endforeach
@if($claim->status === 'pending')<form method="POST" action="{{ route('admin.creator-phone-claims.review',$claim->id) }}" class="space-y-2">@csrf<label class="block">Review notes<textarea name="admin_notes" required maxlength="2000" class="block w-full rounded-xl border p-3" style="background:var(--bg-input)"></textarea></label><button name="decision" value="approve" class="rounded-xl border px-4 py-2">Approve ownership &amp; assign</button><button name="decision" value="reject" class="rounded-xl border px-4 py-2">Reject claim</button></form>@else<p>{{ $claim->admin_notes }}</p>@endif</article>
@empty<p>No ownership requests.</p>@endforelse
{{ $claims->links() }}
<h2 class="text-lg font-bold">Attached numbers</h2>
@forelse($numbers as $number)<article class="border rounded-xl p-4"><strong>{{ $number->phone }}</strong> · {{ $number->workspace_name }} · {{ $number->handle ? '@'.$number->handle : 'Profile #'.$number->creator_profile_id }}<details class="mt-2"><summary>Revoke number attachment</summary><form method="POST" action="{{ route('admin.creator-phone-claims.revoke',$number->id) }}" class="mt-2 space-y-2">@csrf<label class="block">Reason<textarea name="reason" required maxlength="2000" class="block w-full border rounded-xl p-3" style="background:var(--bg-input)"></textarea></label><button class="bg-red-600 text-white rounded-xl px-4 py-2">Revoke attachment</button></form></details></article>@empty<p>No attached numbers.</p>@endforelse
{{ $numbers->links() }}
</div>
@endsection
