@extends('portal.layout')
@section('title', 'Overview')
@section('content')
<div class="bg-white border border-slate-200 rounded-xl p-6 mb-6">
    <h1 class="text-2xl font-bold mb-1">Welcome to your portal</h1>
    <p class="text-slate-500 text-sm">{{ $portal->welcome_message ?: 'Everything ' . $portal->brandingName() . ' has shared with you lives here. Use the menu above to navigate sections.' }}</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse($sections as $type => $items)
        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <div class="flex items-center gap-2 mb-3">
                <span class="brand-pill px-2 py-1 rounded text-xs font-semibold">
                    {{ \App\Modules\User\Models\ClientPortalShare::TYPES[$type] ?? $type }}
                </span>
                <span class="text-xs text-slate-400">{{ $items->count() }} item{{ $items->count() === 1 ? '' : 's' }}</span>
            </div>
            <ul class="space-y-2 text-sm">
                @foreach($items as $share)
                    @php
                        $href = match($type) {
                            'task_board'         => route('portal.board', $share->shareable_id),
                            'cloud_folder'       => route('portal.files'),
                            'creator_post'       => route('portal.drafts'),
                            'invoice'            => route('portal.invoices'),
                            'link_performance'   => route('portal.report', $share->shareable_id),
                            default              => '#',
                        };
                    @endphp
                    <li>
                        <a href="{{ $href }}" class="block px-3 py-2 rounded hover:bg-slate-50 brand-text">
                            <i class="fas fa-arrow-right text-xs mr-2 opacity-60"></i>{{ $share->label ?: ('Item #' . $share->shareable_id) }}
                            @if($share->requires_approval)
                                <span class="ml-2 text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">{{ $share->approval_status ?: 'pending' }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @empty
        <div class="md:col-span-2 lg:col-span-3 bg-white border border-dashed border-slate-300 rounded-xl p-10 text-center text-slate-500">
            <i class="fas fa-inbox text-4xl mb-2 opacity-50"></i>
            <p>Nothing has been shared with you yet.</p>
        </div>
    @endforelse
</div>
@endsection
