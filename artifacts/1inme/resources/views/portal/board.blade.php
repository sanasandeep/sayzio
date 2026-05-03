@extends('portal.layout')
@section('title', $board->name)
@section('content')
<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-xl font-bold">{{ $board->name }}</h1>
        <p class="text-sm text-slate-500">Read-only kanban view</p>
    </div>
</div>

<div class="overflow-x-auto pb-4">
    <div class="flex gap-4 min-w-max">
        @foreach($columns as $column)
            <div class="w-72 bg-slate-100 rounded-lg p-3 flex-shrink-0">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full" style="background: {{ $column->color ?: '#94a3b8' }}"></span>
                        <span class="text-sm font-semibold">{{ $column->name }}</span>
                    </div>
                    <span class="text-xs text-slate-500">{{ optional($cards->get($column->id))->count() ?? 0 }}</span>
                </div>
                <div class="space-y-2">
                    @foreach(($cards->get($column->id) ?? collect()) as $card)
                        <div class="bg-white rounded p-3 shadow-sm border border-slate-200">
                            <div class="font-medium text-sm mb-1">{{ $card->title }}</div>
                            @if($card->description)
                                <div class="text-xs text-slate-500 line-clamp-3">{{ \Illuminate\Support\Str::limit($card->description, 140) }}</div>
                            @endif
                            <div class="flex items-center gap-2 mt-2 text-xs text-slate-500">
                                @if($card->due_date)
                                    <span><i class="far fa-calendar mr-1"></i>{{ $card->due_date->format('M j') }}</span>
                                @endif
                                @if($card->priority && $card->priority !== 'normal')
                                    <span class="brand-pill px-1.5 py-0.5 rounded">{{ ucfirst($card->priority) }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
