@extends('admin.layouts.app')

@section('title', 'Marketing Plan Tool Costs')

@section('content')
<div class="max-w-4xl">
    <h1 class="text-xl font-bold ak-strong">Marketing Plan — Tool Costs</h1>
    <p class="text-sm ak-muted mt-1">
        Default estimated monthly costs (₹) for the "If you didn't use Sayzio — what you'd need instead"
        table in the Marketing Plan Calculator's ROI tab. Lock the table to make these costs read-only for users.
    </p>

    @if(session('status'))
        <div class="mt-3 rounded-lg px-4 py-2 text-sm ak-green" style="background: rgba(16,185,129,.12);" data-admin-status>{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.marketing-plan-tool-costs.update') }}" class="mt-4">
        @csrf
        @method('PUT')

        <label class="flex items-center gap-2 rounded-xl border px-4 py-3 cursor-pointer" style="border-color: var(--border, rgba(148,163,184,.25));">
            <input type="checkbox" name="locked" value="1" @checked($locked) class="h-4 w-4" data-admin-tools-lock>
            <span class="text-sm font-semibold ak-strong">Lock costs for users</span>
            <span class="text-xs ak-muted">— when checked, the Est. monthly cost column becomes read-only in every user's calculator.</span>
        </label>

        <div class="mt-4 overflow-x-auto rounded-xl border" style="border-color: var(--border, rgba(148,163,184,.25));">
            <table class="w-full min-w-[640px] text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide ak-muted">
                        <th class="px-4 py-2">Sayzio feature</th>
                        <th class="px-4 py-2">Example standalone tool</th>
                        <th class="px-4 py-2">Est. monthly cost (₹)</th>
                        <th class="px-4 py-2">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tools as $tool)
                        <tr class="border-t" style="border-color: var(--border, rgba(148,163,184,.15));">
                            <td class="px-4 py-2 font-semibold ak-strong">{{ $tool['feature'] }}</td>
                            <td class="px-4 py-2 ak-muted">{{ $tool['example'] }}</td>
                            <td class="px-4 py-2">
                                <input type="number" min="0" step="1" name="costs[{{ $tool['key'] }}]"
                                       value="{{ old('costs.' . $tool['key'], $tool['cost']) }}"
                                       class="w-32 rounded-lg border bg-transparent px-3 py-1.5 ak-strong"
                                       style="border-color: var(--border, rgba(148,163,184,.3));"
                                       data-admin-tool-cost="{{ $tool['key'] }}">
                                @error('costs.' . $tool['key'])
                                    <p class="text-xs ak-red mt-1">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="px-4 py-2 text-xs ak-muted">{{ $tool['notes'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <button type="submit" class="mt-4 rounded-xl px-5 py-2.5 text-sm font-semibold text-white" style="background: var(--color-primary-600, #2563eb);" data-admin-tools-save>
            Save tool costs
        </button>
    </form>
</div>
@endsection
