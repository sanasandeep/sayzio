@extends('admin.layouts.app')
@section('title', 'Payment Gateways')
@section('content')
<div class="space-y-4">
    <div>
        <h1 class="text-2xl font-semibold text-white">Payment Gateways</h1>
        <p class="text-sm text-white/50">Enable or disable payment providers. Credentials are encrypted at rest; stored secrets are never displayed.</p>
    </div>
    @if(session('success'))<div class="px-4 py-2 rounded-xl bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 text-sm">{{ session('success') }}</div>@endif

    <div class="rounded-2xl border border-white/10 bg-white/[0.02] overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-white/5 text-white/60 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-2 text-left">Gateway</th>
                    <th class="px-4 py-2 text-left">Mode</th>
                    <th class="px-4 py-2 text-center">Enabled</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="text-white/80">
                @foreach($rows as $r)
                    <tr class="border-t border-white/5">
                        <td class="px-4 py-3">
                            <div class="text-white">{{ $r['display_name'] }}</div>
                            <div class="text-xs text-white/50 font-mono">{{ $r['slug'] }}</div>
                        </td>
                        <td class="px-4 py-3 uppercase text-xs">{{ $r['settings']->mode }}</td>
                        <td class="px-4 py-3 text-center">
                            <form method="POST" action="{{ route('admin.payment-gateways.toggle', $r['slug']) }}" class="inline">
                                @csrf
                                <button type="submit" class="px-3 py-1 rounded-full text-xs font-medium {{ $r['settings']->is_enabled ? 'bg-emerald-500/20 text-emerald-200' : 'bg-white/10 text-white/50' }}">
                                    {{ $r['settings']->is_enabled ? 'On' : 'Off' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.payment-gateways.edit', $r['slug']) }}" class="text-violet-300 hover:text-violet-200">Configure</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
