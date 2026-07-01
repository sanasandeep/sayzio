@extends('user.layouts.settings')

@section('title', 'API keys')

@section('settings-content')
<div class="max-w-4xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'API keys',
        'subtitle' => 'Generate keys to call the Sayzio REST API programmatically. Each call counts against your monthly allowance — overage is paid with coins.',
        'icon' => 'fa-key',
        'chips' => [
            ['icon' => 'fa-plug text-blue-400', 'text' => count($keys) . ' active'],
        ],
    ])

    @if(session('success'))
        <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
            <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #ef4444;">
            <i class="fas fa-exclamation-circle mr-1.5"></i> {{ session('error') }}
        </div>
    @endif

    {{-- Newly-created key — shown exactly once. --}}
    @if($newToken)
        <div class="mb-6 rounded-2xl p-5" style="background: rgba(61,107,255,0.08); border: 1px solid rgba(61,107,255,0.3);"
             x-data="{ copied: false }">
            <div class="flex items-center gap-2 text-sm font-semibold mb-2" style="color: var(--text);">
                <i class="fas fa-circle-check text-blue-400"></i>
                Your new key &ldquo;{{ $newTokenName }}&rdquo; is ready
            </div>
            <p class="text-xs mb-3" style="color: var(--text-muted);">
                Copy it now — for security we won't show it again.
            </p>
            <div class="flex items-stretch gap-2">
                <code class="flex-1 px-3 py-2.5 rounded-xl text-xs break-all font-mono"
                      style="background: var(--surface-2, rgba(255,255,255,0.04)); border: 1px solid var(--border, rgba(255,255,255,0.1)); color: var(--text);"
                      x-ref="tok">{{ $newToken }}</code>
                <button type="button"
                        class="px-3 py-2.5 rounded-xl text-sm font-medium whitespace-nowrap transition"
                        style="background: rgba(61,107,255,0.15); border: 1px solid rgba(61,107,255,0.35); color: #90acff;"
                        @click="navigator.clipboard.writeText($refs.tok.innerText); copied = true; setTimeout(() => copied = false, 2000)">
                    <i class="fas" :class="copied ? 'fa-check' : 'fa-copy'"></i>
                    <span x-text="copied ? 'Copied' : 'Copy'"></span>
                </button>
            </div>
        </div>
    @endif

    {{-- Live usage meter --}}
    <div class="rounded-2xl p-5 mb-4" style="background: var(--surface, rgba(255,255,255,0.03)); border: 1px solid var(--border, rgba(255,255,255,0.08));">
        <div class="flex items-center justify-between gap-3 mb-2">
            <div class="text-sm font-semibold" style="color: var(--text);">
                <i class="fas fa-gauge-high text-blue-400 mr-1.5"></i>API usage this period
            </div>
            <div class="text-[11px]" style="color: var(--text-muted);">
                Resets {{ $periodReset->format('M j, Y') }} · {{ $daysLeft }} {{ \Illuminate\Support\Str::plural('day', $daysLeft) }} left
            </div>
        </div>

        @if($unlimited)
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-bold" style="color: var(--text);">{{ number_format($callsUsed) }}</span>
                <span class="text-sm" style="color: var(--text-muted);">calls · <span class="font-semibold text-blue-400">Unlimited</span> allowance</span>
            </div>
        @else
            @php
                $barColor = $percentUsed >= 100 ? '#ef4444' : ($percentUsed >= 80 ? '#f59e0b' : '#3d6bff');
            @endphp
            <div class="flex items-baseline justify-between gap-2 mb-2">
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-bold" style="color: var(--text);">{{ number_format($callsUsed) }}</span>
                    <span class="text-sm" style="color: var(--text-muted);">of {{ number_format($allowance) }} calls</span>
                </div>
                <span class="text-sm font-bold" style="color: {{ $barColor }};">{{ $percentUsed }}%</span>
            </div>
            <div class="h-2.5 w-full rounded-full overflow-hidden" style="background: var(--surface-2, rgba(255,255,255,0.06));">
                <div class="h-full rounded-full transition-all" style="width: {{ $percentUsed }}%; background: {{ $barColor }};"></div>
            </div>
            @if($percentUsed >= 100)
                <p class="text-[11px] mt-2" style="color: #f87171;">
                    <i class="fas fa-triangle-exclamation mr-1"></i>
                    You've used your full allowance. Extra calls
                    @if($overageCalls > 0)
                        ({{ number_format($overageCalls) }} so far) are
                    @else
                        will be
                    @endif
                    paid with coins@if(!$walletEnabled), but coin top-ups are disabled so they'll be rejected@endif.
                </p>
            @elseif($percentUsed >= 80)
                <p class="text-[11px] mt-2" style="color: #fbbf24;">
                    <i class="fas fa-circle-exclamation mr-1"></i>
                    You're approaching your monthly allowance.
                </p>
            @endif
        @endif
    </div>

    {{-- Usage snapshot --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="rounded-2xl p-4" style="background: var(--surface, rgba(255,255,255,0.03)); border: 1px solid var(--border, rgba(255,255,255,0.08));">
            <div class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--text-muted);">Calls this month</div>
            <div class="text-xl font-bold" style="color: var(--text);">{{ number_format($callsUsed) }}</div>
        </div>
        <div class="rounded-2xl p-4" style="background: var(--surface, rgba(255,255,255,0.03)); border: 1px solid var(--border, rgba(255,255,255,0.08));">
            <div class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--text-muted);">Monthly allowance</div>
            <div class="text-xl font-bold" style="color: var(--text);">{{ $unlimited ? 'Unlimited' : number_format($allowance) }}</div>
        </div>
        <div class="rounded-2xl p-4" style="background: var(--surface, rgba(255,255,255,0.03)); border: 1px solid var(--border, rgba(255,255,255,0.08));">
            <div class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--text-muted);">Overage calls</div>
            <div class="text-xl font-bold" style="color: var(--text);">{{ number_format($overageCalls) }}</div>
            <div class="text-[10px] mt-0.5" style="color: var(--text-muted);">{{ number_format($coinsSpent) }} coins spent</div>
        </div>
        <div class="rounded-2xl p-4" style="background: var(--surface, rgba(255,255,255,0.03)); border: 1px solid var(--border, rgba(255,255,255,0.08));">
            <div class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--text-muted);">Coin balance</div>
            <div class="text-xl font-bold" style="color: var(--text);">{{ number_format($coinBalance) }}</div>
            <div class="text-[10px] mt-0.5" style="color: var(--text-muted);">1 coin = {{ number_format($callsPerCoin) }} calls</div>
        </div>
    </div>

    @if(!$unlimited)
        <p class="text-xs mb-6" style="color: var(--text-muted);">
            <i class="fas fa-circle-info mr-1 text-blue-400"></i>
            Your plan includes <strong>{{ number_format($allowance) }}</strong> API calls per month
            @if($rate > 0)(rate-limited to {{ number_format($rate) }}/min)@endif.
            Calls beyond that are charged at <strong>1 coin per {{ number_format($callsPerCoin) }} calls</strong>.
            @if(!$walletEnabled)
                Coin top-ups are currently disabled, so calls beyond the allowance will be rejected.
            @endif
            <a href="{{ route('user.upgrade') }}" class="text-blue-400 hover:underline ml-1">Need more? Upgrade your plan.</a>
        </p>
    @endif

    {{-- Create form --}}
    <div class="rounded-2xl p-5 mb-6" style="background: var(--surface, rgba(255,255,255,0.03)); border: 1px solid var(--border, rgba(255,255,255,0.08));">
        <h3 class="text-sm font-semibold mb-3" style="color: var(--text);">Create a new key</h3>
        <form method="POST" action="{{ route('user.api-keys.store') }}" class="flex flex-col sm:flex-row gap-3">
            @csrf
            <input type="text" name="name" required maxlength="120"
                   placeholder="e.g. Production server, Zapier, Local script"
                   class="flex-1 px-3 py-2.5 rounded-xl text-sm"
                   style="background: var(--surface-2, rgba(255,255,255,0.04)); border: 1px solid var(--border, rgba(255,255,255,0.1)); color: var(--text);">
            <button type="submit"
                    class="px-4 py-2.5 rounded-xl text-sm font-bold whitespace-nowrap transition"
                    style="background: #3d6bff; color: #fff;">
                <i class="fas fa-plus mr-1.5"></i> Generate key
            </button>
        </form>
        @error('name')
            <p class="text-xs mt-2" style="color: #f87171;">{{ $message }}</p>
        @enderror
    </div>

    {{-- Existing keys --}}
    @if($keys->isEmpty())
        <div class="rounded-2xl p-8 text-center" style="background: var(--surface, rgba(255,255,255,0.03)); border: 1px dashed var(--border, rgba(255,255,255,0.12));">
            <i class="fas fa-key text-2xl mb-2" style="color: var(--text-muted);"></i>
            <p class="text-sm" style="color: var(--text-muted);">No API keys yet. Generate one above to start calling the API.</p>
        </div>
    @else
        <div class="space-y-2">
            @foreach($keys as $key)
                <div class="flex items-center justify-between gap-3 rounded-2xl px-4 py-3"
                     style="background: var(--surface, rgba(255,255,255,0.03)); border: 1px solid var(--border, rgba(255,255,255,0.08));">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold truncate" style="color: var(--text);">
                            <i class="fas fa-key text-blue-400 mr-1.5"></i>{{ $key->device_label ?: $key->name }}
                        </div>
                        <div class="text-[11px] mt-0.5" style="color: var(--text-muted);">
                            Created {{ $key->created_at?->diffForHumans() }}
                            · {{ $key->last_used_at ? 'Last used ' . $key->last_used_at->diffForHumans() : 'Never used' }}
                        </div>
                    </div>
                    <form method="POST" action="{{ route('user.api-keys.destroy', $key->id) }}"
                          onsubmit="return confirm('Revoke this API key? Any integration using it will stop working immediately.');">
                        @csrf @method('DELETE')
                        <button type="submit"
                                class="px-3 py-2 rounded-xl text-sm font-medium transition whitespace-nowrap"
                                style="background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.3); color: #f87171;">
                            <i class="fas fa-trash-can mr-1"></i> Revoke
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
