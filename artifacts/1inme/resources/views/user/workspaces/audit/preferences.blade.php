@extends('user.layouts.app')

@section('title', 'Audit alert preferences')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                Audit alert preferences
            </h1>
            <p class="text-sm opacity-70 mt-1">
                Choose which sensitive actions in <strong>{{ $workspace->name }}</strong>
                send an alert email to the workspace owner.
            </p>
        </div>
        <a href="{{ route('user.workspaces.audit.index') }}"
           class="text-sm text-primary-400 hover:text-primary-200 font-semibold">
            <i class="fas fa-arrow-left mr-1"></i> Back to log
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <form method="post" action="{{ route('user.workspaces.audit.preferences.update') }}"
          class="rounded-xl border border-white/10 overflow-hidden" style="background: var(--bg-card);">
        @csrf
        @method('PUT')
        <ul class="divide-y divide-white/5">
            @foreach($rows as $action => $row)
                <li class="px-5 py-4 flex items-center justify-between gap-4">
                    <div>
                        <div class="font-semibold" style="color: var(--text-primary);">{{ $row['label'] }}</div>
                        <div class="text-xs text-gray-500 mt-0.5">
                            <code class="text-[11px] bg-white/5 px-1.5 py-0.5 rounded">{{ $action }}</code>
                            @if($row['default'])
                                <span class="ml-2 text-emerald-400">on by default</span>
                            @else
                                <span class="ml-2 text-gray-500">off by default</span>
                            @endif
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="alerts[{{ $action }}]" value="1"
                               class="sr-only peer" @checked($row['alert_enabled'])>
                        <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none rounded-full
                                    peer-checked:bg-primary-600
                                    after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                    after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all
                                    peer-checked:after:translate-x-5"></div>
                    </label>
                </li>
            @endforeach
        </ul>

        <div class="px-5 py-4 border-t border-white/5 flex justify-end">
            <button type="submit"
                    class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-semibold hover:bg-primary-700">
                Save preferences
            </button>
        </div>
    </form>

    <p class="text-xs text-gray-500 mt-4">
        Audit rows are always written, even when alerts are off.
        Turning a toggle off only stops the email — the action still
        appears in the log.
    </p>
</div>
@endsection
