@extends('user.layouts.app')

@section('title', 'No access')

@section('content')
@php
    $reasonText = [
        'no_workspace'       => "We couldn't find an active workspace for this page.",
        'not_a_member'       => "You're not a member of this workspace anymore.",
        'missing_permission' => "Your role in this workspace doesn't include access to this page.",
    ][$reason] ?? "You don't have access to this page in this workspace.";
@endphp
<div class="max-w-lg mx-auto px-4 py-12">
    <div class="rounded-2xl border p-8 text-center"
         style="border-color: var(--border-strong); background: var(--bg-card);">
        <div class="w-14 h-14 mx-auto rounded-full flex items-center justify-center mb-4"
             style="background: rgba(92,131,255,0.12); color:#3d6bff;">
            <i class="fas fa-lock text-2xl"></i>
        </div>
        <h1 class="text-xl font-bold mb-2" style="color: var(--text-primary);">You don't have access</h1>
        <p class="text-sm mb-5" style="color: var(--text-muted);">{{ $reasonText }}</p>

        @if(!empty($permissionLabels ?? []))
            <div class="rounded-lg p-4 mb-5 text-left"
                 style="background: rgba(92,131,255,0.08);">
                <p class="text-xs uppercase tracking-wide mb-2" style="color: var(--text-faint);">
                    This page needs permission to
                </p>
                <ul class="text-sm font-medium space-y-1" style="color: var(--text-primary);">
                    @foreach($permissionLabels as $label)
                        <li><i class="fas fa-key text-xs mr-2" style="color:#3d6bff;"></i>{{ $label }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($workspace)
            <div class="rounded-lg border p-4 mb-5 text-left"
                 style="border-color: var(--border-soft); background: var(--bg-subtle);">
                <p class="text-xs uppercase tracking-wide mb-1" style="color: var(--text-faint);">Workspace</p>
                <p class="text-sm font-semibold mb-3" style="color: var(--text-primary);">{{ $workspace->name }}</p>
                <p class="text-xs uppercase tracking-wide mb-1" style="color: var(--text-faint);">Your role</p>
                <p class="text-sm font-semibold @if(!empty($owner)) mb-3 @endif" style="color: var(--text-primary);">
                    {{ $roleLabel ?? 'Not a member' }}
                </p>
                @if(!empty($owner))
                    <p class="text-xs uppercase tracking-wide mb-1" style="color: var(--text-faint);">Who can grant access</p>
                    <p class="text-sm font-semibold" style="color: var(--text-primary);">
                        {{ $owner->name }} <span class="font-normal" style="color: var(--text-muted);">(workspace owner)</span>
                    </p>
                    @if(!empty($owner->email))
                        <a href="mailto:{{ $owner->email }}" class="text-xs hover:underline" style="color:#3d6bff;">
                            <i class="fas fa-envelope mr-1"></i>{{ $owner->email }}
                        </a>
                    @endif
                    @if(!empty($grantorRoles ?? []))
                        <p class="text-xs mt-2" style="color: var(--text-muted);">
                            This page needs at least the
                            <span class="font-semibold" style="color: var(--text-primary);">{{ implode(' / ', $grantorRoles) }}</span>
                            role — only the workspace owner can change your role.
                        </p>
                    @endif
                @endif
            </div>
        @endif

        @if(session('access_request_sent'))
            <div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">
                <i class="fas fa-check-circle mr-1"></i>
                We let the workspace owner know you'd like access.
            </div>
        @elseif(session('access_request_pending'))
            <div class="mb-4 p-3 rounded-lg bg-sky-50 text-sky-700 text-sm">
                <i class="fas fa-hourglass-half mr-1"></i>
                {{ session('access_request_pending') }}
            </div>
        @elseif(session('access_request_error'))
            <div class="mb-4 p-3 rounded-lg bg-amber-50 text-amber-800 text-sm">
                {{ session('access_request_error') }}
            </div>
        @endif

        <div class="flex flex-col sm:flex-row gap-2 justify-center">
            <a href="{{ route('user.dashboard') }}"
               class="px-4 py-2 rounded-lg text-sm font-semibold border"
               style="border-color: var(--border-strong); color: var(--text-primary);">
                <i class="fas fa-arrow-left mr-1"></i> Back to dashboard
            </a>
            @if($workspace && $reason === 'missing_permission' && !session('access_request_sent'))
                <form action="{{ route('user.workspaces.request-access') }}" method="POST" class="w-full sm:w-auto">
                    @csrf
                    <input type="hidden" name="workspace_id" value="{{ $workspace->id }}">
                    <input type="hidden" name="path" value="{{ request()->path() }}">
                    @foreach(($permissions ?? []) as $perm)
                        <input type="hidden" name="permissions[]" value="{{ $perm }}">
                    @endforeach
                    <div class="text-left mb-2">
                        <label for="access_request_note"
                               class="block text-xs font-medium mb-1"
                               style="color: var(--text-muted);">
                            Add a short note (optional)
                        </label>
                        <textarea id="access_request_note"
                                  name="note"
                                  rows="2"
                                  maxlength="280"
                                  placeholder="e.g. I need this to reply to support tickets today"
                                  class="w-full text-sm rounded-lg border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400"
                                  style="border-color: var(--border-soft); background: var(--bg-card); color: var(--text-primary);"></textarea>
                        <p class="text-xs mt-1" style="color: var(--text-faint);">Up to 280 characters.</p>
                    </div>
                    <button type="submit"
                            class="w-full px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-semibold hover:bg-primary-700">
                        <i class="fas fa-paper-plane mr-1"></i> Ask an admin for access
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
