@extends('user.layouts.settings')
@section('title', 'Contact privacy')
@section('settings-content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Contact privacy</h1>
        <p class="text-sm mt-1" style="color: var(--text-muted);">
            Control what strangers can see about you when they look you up in the dialer's caller-ID
            or find you through search. People who've already saved you as a contact — and you
            yourself — always see everything, no matter what you choose here.
        </p>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('user.settings.privacy.update') }}"
          class="rounded-2xl p-5 space-y-6"
          style="background: var(--bg-card); border:1px solid var(--border-soft);">
        @csrf
        @method('PUT')

        @php
            $fields = [
                'share_phone'    => ['label' => 'Phone number', 'desc' => 'Your number, plus call / text / WhatsApp-by-number / FaceTime shortcuts.'],
                'share_email'    => ['label' => 'Email address', 'desc' => 'Your email, when available on a lookup.'],
                'share_location' => ['label' => 'Exact location', 'desc' => 'Precise map location(s) you\'ve shared on your biolink.'],
                'share_socials'  => ['label' => 'Socials & other channels', 'desc' => 'Instagram, WhatsApp, Telegram and other links pulled from your biolink.'],
            ];
        @endphp

        <div class="divide-y" style="border-color: var(--border-soft);">
            @foreach($fields as $key => $meta)
                @php $current = $prefs[$key] ?? null; @endphp
                <div class="py-4 first:pt-0">
                    <div class="text-sm font-semibold" style="color: var(--text-primary);">{{ $meta['label'] }}</div>
                    <div class="text-xs mt-0.5 mb-2" style="color: var(--text-muted);">{{ $meta['desc'] }}</div>
                    <div class="flex items-center gap-4">
                        <label class="inline-flex items-center gap-1.5 text-xs cursor-pointer">
                            <input type="radio" name="{{ $key }}" value="" class="accent-blue-600" @checked($current === null)>
                            <span style="color: var(--text-muted);">Shown <span class="opacity-60">(default)</span></span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 text-xs cursor-pointer">
                            <input type="radio" name="{{ $key }}" value="1" class="accent-blue-600" @checked($current === true)>
                            <span style="color: var(--text-muted);">Always shown</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 text-xs cursor-pointer">
                            <input type="radio" name="{{ $key }}" value="0" class="accent-blue-600" @checked($current === false)>
                            <span style="color: var(--text-muted);">Hidden from strangers</span>
                        </label>
                    </div>
                </div>
            @endforeach
        </div>

        @if(!empty($candidates['socials']) || !empty($candidates['channels']))
            <div class="pt-2 border-t" style="border-color: var(--border-soft);">
                <div class="text-sm font-semibold mb-1" style="color: var(--text-primary);">Un-share individual channels</div>
                <p class="text-xs mb-3" style="color: var(--text-muted);">
                    Even when "Socials & other channels" above is shown, you can hide specific ones
                    one at a time.
                </p>
                <div class="grid sm:grid-cols-2 gap-2">
                    @foreach($candidates['socials'] as $s)
                        <label class="inline-flex items-center gap-2 text-xs rounded-lg px-3 py-2" style="background: var(--bg-soft, rgba(61,107,255,0.06));">
                            <input type="checkbox" name="hidden_channels[]" value="{{ $s['key'] }}" class="accent-blue-600" @checked($s['hidden'])>
                            <span style="color: var(--text-muted);">{{ $s['label'] ?? ucfirst($s['platform'] ?? 'Social') }}</span>
                        </label>
                    @endforeach
                    @foreach($candidates['channels'] as $c)
                        <label class="inline-flex items-center gap-2 text-xs rounded-lg px-3 py-2" style="background: var(--bg-soft, rgba(61,107,255,0.06));">
                            <input type="checkbox" name="hidden_channels[]" value="{{ $c['key'] }}" class="accent-blue-600" @checked($c['hidden'])>
                            <span style="color: var(--text-muted);">{{ $c['label'] ?? ucfirst($c['type'] ?? 'Channel') }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="pt-2">
            <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background: var(--color-primary-600, #2563eb);">
                Save privacy preferences
            </button>
        </div>
    </form>
</div>
@endsection
