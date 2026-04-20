@extends('admin.layouts.app')
@section('title', 'Configure '.$row->display_name)
@section('content')
<div class="max-w-2xl mx-auto space-y-4">
    <div>
        <h1 class="text-2xl font-semibold text-white">{{ $row->display_name }}</h1>
        <p class="text-sm text-white/50 font-mono">{{ $row->gateway_slug }}</p>
    </div>
    @if($errors->any())
        <div class="rounded-xl bg-rose-500/10 border border-rose-400/30 p-3 text-sm text-rose-200">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.payment-gateways.update', $row->gateway_slug) }}" class="rounded-2xl border border-white/10 bg-white/[0.02] p-5 space-y-4">
        @csrf @method('PUT')
        <div>
            <label class="block text-xs text-white/50 mb-1">Display name</label>
            <input type="text" name="display_name" value="{{ old('display_name', $row->display_name) }}" required class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-white/50 mb-1">Mode</label>
                <select name="mode" class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white">
                    <option value="test" @selected($row->mode === 'test')>Test</option>
                    <option value="live" @selected($row->mode === 'live')>Live</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-white/50 mb-1">Sort order</label>
                <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $row->sort_order) }}" class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white">
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-white/80">
            <input type="checkbox" name="is_enabled" value="1" @checked($row->is_enabled) class="accent-violet-500">
            Enable this gateway on the checkout page
        </label>

        <div class="pt-3 border-t border-white/10 space-y-3">
            <p class="text-xs text-white/50">Credentials are encrypted at rest. Leave a field blank to keep the stored value unchanged. Stored values are never shown here.</p>
            @foreach($fields as $f)
                @php
                    $isSecret = !in_array($f, ['payee_name','bank_details','upi_id','instructions']);
                    $stored = $row->credential($f);
                    $placeholder = $stored ? '•••• configured ••••' : '';
                    $isLong = in_array($f, ['bank_details','instructions']);
                @endphp
                <div>
                    <label class="block text-xs text-white/50 mb-1">{{ ucwords(str_replace('_',' ', $f)) }}</label>
                    @if($isLong)
                        <textarea name="credentials[{{ $f }}]" rows="3" placeholder="{{ $placeholder }}" class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white font-mono text-xs">{{ old("credentials.$f") }}</textarea>
                    @else
                        <input type="{{ $isSecret ? 'password' : 'text' }}" name="credentials[{{ $f }}]" value="{{ old("credentials.$f") }}" placeholder="{{ $placeholder }}" autocomplete="off" class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white font-mono text-xs">
                    @endif
                </div>
            @endforeach
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-xl font-medium">Save</button>
            <a href="{{ route('admin.payment-gateways.index') }}" class="px-4 py-2 bg-white/5 hover:bg-white/10 text-white/70 rounded-xl">Cancel</a>
        </div>
    </form>
</div>
@endsection
