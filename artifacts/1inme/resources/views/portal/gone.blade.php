@extends('portal.layout')
@section('title', 'Link unavailable')
@section('content')
<div class="max-w-md mx-auto mt-16 text-center bg-white border border-slate-200 rounded-xl p-10">
    <i class="fas fa-link-slash text-4xl text-slate-300 mb-3"></i>
    <h1 class="text-xl font-bold mb-2">This portal link is no longer available</h1>
    <p class="text-sm text-slate-500">
        @switch($reason ?? 'invalid')
            @case('expired')   The link has expired. Please ask for a fresh invitation. @break
            @case('revoked')   This link was revoked by the workspace owner. @break
            @case('disabled')  The portal is currently disabled. @break
            @case('logged_out')You've been signed out of the portal. @break
            @default          The link is invalid or already used.
        @endswitch
    </p>
</div>
@endsection
