@extends('user.layouts.app')

@section('title', '2FA required')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-12">
    <div class="rounded-lg border border-amber-300 bg-amber-50 p-6 mb-6 text-amber-900">
        <div class="flex items-start gap-3">
            <i class="fas fa-shield-halved text-2xl"></i>
            <div>
                <h1 class="text-xl font-bold">Your workspace requires 2FA</h1>
                <p class="text-sm mt-1">The owner of one of your workspaces turned on a two-factor authentication policy. To keep using this workspace you need to enroll an authenticator app now.</p>
            </div>
        </div>
    </div>

    <div class="text-center">
        <a href="{{ $continueUrl }}"
           class="inline-flex items-center px-5 py-3 bg-primary-600 text-white rounded-lg font-semibold">
            <i class="fas fa-arrow-right mr-2"></i> Continue to setup
        </a>
        <p class="text-xs opacity-60 mt-3">You can sign out instead, but you won't be able to come back in until enrollment is complete.</p>
        <form method="POST" action="{{ route('user.logout') }}" class="mt-2">
            @csrf
            <button class="text-xs underline opacity-70">Sign out</button>
        </form>
    </div>
</div>
@endsection
