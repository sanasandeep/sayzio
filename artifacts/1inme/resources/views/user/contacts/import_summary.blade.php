@extends('user.layouts.app')

@section('title', 'Import results')

@section('content')
<div class="max-w-4xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Import results',
        'subtitle' => 'Here\'s what happened with the file you uploaded.',
        'icon' => 'fa-clipboard-check',
        'chips' => [
            ['icon' => 'fa-list text-cyan-400',     'text' => $results['total'] . ' rows in file'],
            ['icon' => 'fa-check text-emerald-400', 'text' => $results['created'] . ' added'],
            ['icon' => 'fa-times text-red-400',     'text' => count($results['failed']) . ' failed'],
        ],
    ])

    @if($results['skippedCap'] > 0)
        <div class="mb-6 px-4 py-3 rounded-xl text-sm" style="background: rgba(245,158,11,0.10); border: 1px solid rgba(245,158,11,0.25); color: #f59e0b;">
            <i class="fas fa-exclamation-triangle mr-1.5"></i>
            {{ $results['skippedCap'] }} row(s) were skipped because the contact cap was reached. Free up space and re-upload them.
        </div>
    @endif

    <div class="card-premium p-5 mb-6">
        <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);">Summary</h3>
        <div class="grid grid-cols-3 gap-4 text-center">
            <div>
                <div class="text-2xl font-bold" style="color:var(--text-primary);">{{ $results['total'] }}</div>
                <div class="text-[11px]" style="color:var(--text-muted);">Rows in file</div>
            </div>
            <div>
                <div class="text-2xl font-bold text-emerald-400">{{ $results['created'] }}</div>
                <div class="text-[11px]" style="color:var(--text-muted);">Contacts added</div>
            </div>
            <div>
                <div class="text-2xl font-bold text-red-400">{{ count($results['failed']) }}</div>
                <div class="text-[11px]" style="color:var(--text-muted);">Failed rows</div>
            </div>
        </div>
    </div>

    @if(count($results['failed']) > 0)
    <div class="card-premium p-5 mb-6">
        <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);">Failures</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase" style="color:var(--text-faint);">
                        <th class="py-2 pr-3">Row</th>
                        <th class="py-2 pr-3">Name</th>
                        <th class="py-2">Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($results['failed'] as $f)
                        <tr style="border-top:1px solid rgba(255,255,255,.06);">
                            <td class="py-2 pr-3 font-mono text-xs" style="color:var(--text-muted);">#{{ $f['row'] }}</td>
                            <td class="py-2 pr-3" style="color:var(--text-primary);">{{ $f['name'] }}</td>
                            <td class="py-2 text-xs" style="color:#fca5a5;">{{ $f['reason'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div class="flex items-center gap-3">
        <a href="{{ route('user.contacts.index') }}" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background:linear-gradient(135deg,#7c3aed,#ec4899);">
            <i class="fas fa-arrow-left mr-1"></i> Back to contacts
        </a>
        <a href="{{ route('user.contacts.import') }}" class="text-xs" style="color:var(--text-muted);">Import another file</a>
    </div>
</div>
@endsection
