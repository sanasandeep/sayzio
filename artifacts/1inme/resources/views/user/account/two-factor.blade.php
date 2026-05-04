@extends('user.layouts.app')

@section('title', 'Two-factor authentication')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Two-factor authentication</h1>
        <p class="text-sm opacity-70 mt-1">Add a second step at sign-in using an authenticator app like Google Authenticator, 1Password, or Authy.</p>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-green-100 text-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 rounded bg-red-100 text-red-800 text-sm">{{ session('error') }}</div>
    @endif

    @if(!empty($recoveryCodes))
        <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4">
            <h3 class="font-semibold text-amber-900 mb-2">Save these recovery codes</h3>
            <p class="text-xs text-amber-800 mb-3">Store these somewhere safe. Each code can be used once if you lose access to your authenticator app. They won't be shown again.</p>
            <div class="grid grid-cols-2 gap-2 font-mono text-sm">
                @foreach($recoveryCodes as $code)
                    <div class="bg-white border border-amber-200 rounded px-3 py-2">{{ $code }}</div>
                @endforeach
            </div>
        </div>
    @endif

    @if($enrolled)
        <div class="rounded-lg border p-6" style="border-color: var(--border-strong); background: var(--bg-card);">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                    <i class="fas fa-shield-check text-green-600"></i>
                </div>
                <div>
                    <h2 class="font-bold" style="color: var(--text-primary);">2FA is enabled</h2>
                    <p class="text-xs opacity-70">You'll be asked for a 6-digit code from your authenticator app every time you sign in.</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 mt-4">
                <form method="POST" action="{{ route('user.account.two-factor.recovery-codes') }}">
                    @csrf
                    <button class="px-3 py-2 rounded border text-sm font-semibold" style="border-color: var(--border-strong); color: var(--text-primary);">
                        <i class="fas fa-rotate mr-1"></i> Regenerate recovery codes
                    </button>
                </form>
                <form method="POST" action="{{ route('user.account.two-factor.disable') }}"
                      onsubmit="return confirm('Disable 2FA? You will lose this layer of protection.');">
                    @csrf @method('DELETE')
                    <button class="px-3 py-2 rounded border border-red-300 bg-red-50 text-red-700 text-sm font-semibold">
                        <i class="fas fa-shield-xmark mr-1"></i> Disable 2FA
                    </button>
                </form>
            </div>

            @if($policyCovers)
                <p class="mt-4 text-xs text-amber-700"><i class="fas fa-info-circle mr-1"></i> One of your workspaces requires 2FA — disabling it may lock you out.</p>
            @endif
        </div>
    @else
        <div class="rounded-lg border p-6" style="border-color: var(--border-strong); background: var(--bg-card);">
            <h2 class="font-bold mb-2" style="color: var(--text-primary);">Set up your authenticator</h2>
            <ol class="text-sm space-y-2 mb-5 list-decimal list-inside opacity-90">
                <li>Open your authenticator app and tap "Add account".</li>
                <li>Scan the QR code below (or paste the secret key manually).</li>
                <li>Enter the 6-digit code your app shows to confirm.</li>
            </ol>

            <div class="flex flex-col md:flex-row gap-6 items-start">
                <div class="bg-white rounded-lg p-3 border">{!! $qrSvg !!}</div>
                <div class="flex-1">
                    <label class="block text-xs uppercase tracking-wider opacity-70 mb-1">Manual secret key</label>
                    <code class="block bg-gray-100 px-3 py-2 rounded text-sm font-mono break-all">{{ $secret }}</code>
                    <p class="text-xs opacity-60 mt-1">Keep this private — anyone with it can generate codes for your account.</p>

                    <form method="POST" action="{{ route('user.account.two-factor.confirm') }}" class="mt-5">
                        @csrf
                        <label class="block text-xs uppercase tracking-wider opacity-70 mb-1">6-digit code</label>
                        <input type="text" name="code" maxlength="6" required autofocus inputmode="numeric"
                               placeholder="000000"
                               class="w-full px-3 py-2 border rounded text-center text-xl tracking-[0.4em] font-bold"
                               style="background: var(--bg-card); border-color: var(--border-strong); color: var(--text-primary);">
                        <button class="mt-3 w-full px-4 py-2 bg-primary-600 text-white rounded font-semibold">
                            Confirm &amp; enable 2FA
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
