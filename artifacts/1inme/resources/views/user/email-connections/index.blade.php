@extends('user.layouts.app')
@section('title', 'SMTP Connections')

@section('content')
<div class="max-w-5xl mx-auto">
    @include('user.partials.page-hero', [
        'title'    => 'SMTP Connections',
        'subtitle' => 'Reusable email connections for everything the platform sends on your behalf — form notifications, autoresponders, subscriber broadcasts, and billing emails.',
        'icon'     => 'fa-server',
        'chips'    => [
            ['icon' => 'fa-layer-group', 'text' => $connections->count() . ' connection' . ($connections->count() === 1 ? '' : 's')],
        ],
    ])

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-lg text-sm flex items-center gap-2"
             style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.25); color: #10b981;">
            <i class="fas fa-check-circle"></i>{{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 px-4 py-3 rounded-lg text-sm flex items-center gap-2"
             style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); color: #ef4444;">
            <i class="fas fa-triangle-exclamation"></i>{{ session('error') }}
        </div>
    @endif
    @if($errors->has('test_email'))
        <div class="mb-4 px-4 py-3 rounded-lg text-sm flex items-center gap-2"
             style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); color: #ef4444;">
            <i class="fas fa-triangle-exclamation"></i>{{ $errors->first('test_email') }}
        </div>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
        <p class="text-xs" style="color: var(--text-muted);">
            Add as many connections as you need — different domains, brands, or providers. The default one is pre-selected wherever you pick a connection.
        </p>
        <a href="{{ route('user.integrations.create', ['kind' => 'email', 'return_to' => 'connections']) }}"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold"
           style="background: var(--accent); color: #fff;">
            <i class="fas fa-plus"></i> Add connection
        </a>
    </div>

    @if($connections->isEmpty())
        <div class="card-premium p-12 text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-2xl flex items-center justify-center" style="background: rgba(99,102,241,0.12);">
                <i class="fas fa-server text-2xl" style="color: #6366f1;"></i>
            </div>
            <h3 class="text-lg font-bold mb-2" style="color: var(--text-primary);">No email connections yet</h3>
            <p class="text-sm mb-5 max-w-md mx-auto" style="color: var(--text-muted);">
                Save your SMTP or SendGrid details once, then send form notifications, subscriber broadcasts, and billing emails from your own address everywhere.
            </p>
            <a href="{{ route('user.integrations.create', ['kind' => 'email', 'return_to' => 'connections']) }}"
               class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold"
               style="background: var(--accent); color: #fff;">
                <i class="fas fa-plus"></i> Add your first connection
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($connections as $c)
                @php $cMeta = (array) $c->meta; @endphp
                <div class="card-premium p-5 flex flex-col" x-data="{ testOpen: false }">
                    <div class="flex items-start gap-3 mb-3">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                             style="background: {{ $c->providerColor() }}20;">
                            <i class="fas {{ $c->providerIcon() }} text-lg" style="color: {{ $c->providerColor() }};"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <a href="{{ route('user.integrations.edit', ['integrationConfig' => $c, 'return_to' => 'connections']) }}"
                               class="font-bold text-base hover:underline truncate block"
                               style="color: var(--text-primary);">{{ $c->name }}</a>
                            <div class="text-xs truncate" style="color: var(--text-muted);">
                                {{ $c->providerLabel() }}@if(!empty($cMeta['from_email'])) · {{ $cMeta['from_email'] }}@endif
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            @if($c->is_default)
                                <span class="text-[10px] px-2 py-1 rounded-full font-semibold" style="background: rgba(99,102,241,0.15); color: #818cf8;">DEFAULT</span>
                            @endif
                            @if($c->is_active)
                                <span class="text-[10px] px-2 py-1 rounded-full font-semibold" style="background: rgba(16,185,129,0.12); color: #10b981;">ACTIVE</span>
                            @else
                                <span class="text-[10px] px-2 py-1 rounded-full font-semibold" style="background: rgba(148,163,184,0.15); color: var(--text-muted);">DISABLED</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 mt-auto pt-3" style="border-top: 1px solid var(--border-subtle);">
                        <a href="{{ route('user.integrations.edit', ['integrationConfig' => $c, 'return_to' => 'connections']) }}"
                           class="px-3 py-1.5 rounded-lg text-xs font-semibold"
                           style="background: var(--bg-glass-input); color: var(--text-primary);">
                            <i class="fas fa-pen mr-1"></i>Edit
                        </a>
                        <button type="button" @click="testOpen = !testOpen"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold"
                                style="background: rgba(61,107,255,0.12); color: var(--accent);">
                            <i class="fas fa-paper-plane mr-1"></i>Send test email
                        </button>
                        @unless($c->is_default)
                            <form method="POST" action="{{ route('user.integrations.set-default', $c) }}">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-semibold"
                                        style="background: var(--bg-glass-input); color: var(--text-muted);">
                                    <i class="fas fa-star mr-1"></i>Make default
                                </button>
                            </form>
                        @endunless
                        <form method="POST" action="{{ route('user.integrations.toggle', $c) }}">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-semibold"
                                    style="background: var(--bg-glass-input); color: var(--text-muted);">
                                <i class="fas fa-power-off mr-1"></i>{{ $c->is_active ? 'Disable' : 'Enable' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('user.integrations.destroy', ['integrationConfig' => $c, 'return_to' => 'connections']) }}"
                              onsubmit="return confirm('Delete this connection? Features that use it will fall back to the platform mailer.');">
                            @csrf @method('DELETE')
                            <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-semibold"
                                    style="background: rgba(239,68,68,0.10); color: #ef4444;">
                                <i class="fas fa-trash mr-1"></i>Delete
                            </button>
                        </form>
                    </div>

                    <div x-show="testOpen" x-cloak class="mt-3">
                        <form method="POST" action="{{ route('user.email-connections.test', $c) }}" class="flex items-center gap-2">
                            @csrf
                            <input type="email" name="test_email" required
                                   value="{{ old('test_email', auth()->user()->email) }}"
                                   placeholder="you@example.com"
                                   class="flex-1 px-3 py-2 rounded-lg text-xs outline-none"
                                   style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                            <button type="submit" class="px-3 py-2 rounded-lg text-xs font-semibold flex-shrink-0 text-white"
                                    style="background: var(--accent);">
                                Send test
                            </button>
                        </form>
                        <p class="text-[10px] mt-1" style="color: var(--text-faint);">Only your own account email or this connection's from address is allowed.</p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
