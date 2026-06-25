@extends('admin.layouts.app')
@section('title', $row->exists ? 'Edit jurisdiction' : 'New jurisdiction')
@section('content')
<div class="max-w-xl">
    <h1 class="text-2xl font-semibold text-white mb-6">{{ $row->exists ? 'Edit jurisdiction' : 'New jurisdiction' }}</h1>
    <form method="POST" action="{{ $row->exists ? route('admin.taxes.update', $row) : route('admin.taxes.store') }}" class="space-y-4 glass rounded-2xl p-6">
        @csrf
        @if($row->exists)@method('PUT')@endif

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs text-white/50 mb-1">Country (ISO-2)</label>
                <input type="text" name="country" maxlength="2" value="{{ old('country', $row->country) }}" required class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white uppercase">
                @error('country')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs text-white/50 mb-1">Region (state code, optional)</label>
                <input type="text" name="region" maxlength="8" value="{{ old('region', $row->region) }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white uppercase">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs text-white/50 mb-1">Kind</label>
                <select name="kind" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white">
                    @foreach(['GST_INTRA','GST_INTER','VAT','SALES','NONE'] as $k)
                        <option value="{{ $k }}" {{ old('kind', $row->kind) === $k ? 'selected' : '' }} class="bg-[#0d0818]">{{ $k }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-white/50 mb-1">Rate (%)</label>
                <input type="number" step="0.001" name="rate_percent" value="{{ old('rate_percent', $row->rate_percent) }}" required class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white">
                @error('rate_percent')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label class="block text-xs text-white/50 mb-1">Label</label>
            <input type="text" name="label" value="{{ old('label', $row->label) }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white">
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs text-white/50 mb-1">Effective from</label>
                <input type="date" name="effective_from" value="{{ old('effective_from', optional($row->effective_from)->format('Y-m-d')) }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white">
            </div>
            <div>
                <label class="block text-xs text-white/50 mb-1">Effective to</label>
                <input type="date" name="effective_to" value="{{ old('effective_to', optional($row->effective_to)->format('Y-m-d')) }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white">
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-white/80">
            <input type="checkbox" name="b2b_reverse_charge" value="1" {{ old('b2b_reverse_charge', $row->b2b_reverse_charge) ? 'checked' : '' }}>
            B2B reverse-charge eligible (zeroes the tax for cross-border buyers with a valid tax-id)
        </label>
        <label class="flex items-center gap-2 text-sm text-white/80">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $row->is_active ?? true) ? 'checked' : '' }}>
            Active
        </label>

        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.taxes.index') }}" class="px-4 py-2 bg-white/5 text-white/70 rounded-xl">Cancel</a>
            <button class="px-5 py-2 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700">{{ $row->exists ? 'Save' : 'Create' }}</button>
        </div>
    </form>
</div>
@endsection
