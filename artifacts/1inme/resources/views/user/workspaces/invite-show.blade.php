@extends('user.layouts.app')

@section('title', 'Workspace invite')

@section('content')
<div class="max-w-md mx-auto px-4 py-12">
    <div class="rounded-lg border p-6 text-center" style="border-color: var(--border-strong); background: var(--bg-card);">
        <i class="fas fa-users text-4xl text-primary-600 mb-3"></i>
        <h1 class="text-xl font-bold mb-2">You've been invited</h1>
        <p class="text-sm opacity-80">
            <strong>{{ optional($invite->inviter)->name ?? 'Someone' }}</strong>
            invited you to join the
            <strong>{{ optional($invite->workspace)->name ?? 'workspace' }}</strong>
            workspace as <strong>{{ ucfirst($invite->role) }}</strong>.
        </p>
        <p class="text-xs opacity-60 mt-2">Invite is for {{ $invite->email }}</p>

        @if($user && !$loggedInWithRightEmail)
            <div class="mt-4 p-3 rounded bg-amber-50 text-amber-800 text-sm">
                You're signed in as {{ $user->email }}. Please sign in with {{ $invite->email }} to accept.
            </div>
        @endif

        <form method="POST" action="{{ route('user.workspaces.invite.accept', ['token' => $invite->token]) }}" class="mt-5">
            @csrf
            <button class="w-full py-3 bg-primary-600 text-white font-semibold rounded-lg">
                @if($user && $loggedInWithRightEmail)
                    Accept invite
                @elseif($user)
                    Switch account & accept
                @else
                    Sign in / Sign up to accept
                @endif
            </button>
        </form>
    </div>
</div>
@endsection
