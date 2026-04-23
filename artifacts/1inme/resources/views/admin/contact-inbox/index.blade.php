@extends('admin.layouts.app')
@section('title', 'Contact Inbox')
@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="glass rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-white">Contact Inbox</h2>
            <div class="flex items-center gap-2">
                @foreach(['all'=>'All','new'=>'New','read'=>'Read','archived'=>'Archived'] as $key=>$label)
                    <a href="{{ route('admin.contact-inbox.index', ['status'=>$key]) }}"
                       class="px-3 py-1.5 rounded-lg text-xs {{ $status===$key ? 'bg-violet-600 text-white' : 'bg-white/5 text-white/70 hover:bg-white/10' }}">{{ $label }}</a>
                @endforeach
                <a href="{{ route('admin.contact-inbox.index', ['status'=>$status,'sort'=>$sort==='asc'?'desc':'asc']) }}"
                   class="px-3 py-1.5 rounded-lg text-xs bg-white/5 text-white/70 hover:bg-white/10">
                    Date {{ $sort==='asc' ? '↑' : '↓' }}
                </a>
            </div>
        </div>

        @if($messages->count() === 0)
            <div class="text-center text-white/40 py-10 text-sm">No messages.</div>
        @else
            <div class="space-y-3">
                @foreach($messages as $m)
                    <div class="bg-white/5 border border-white/10 rounded-xl p-4" x-data="{ open:false }">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-sm font-semibold text-white">{{ $m->name }}</span>
                                    <span class="text-xs text-white/40">&lt;{{ $m->email }}&gt;</span>
                                    <span class="text-[10px] px-2 py-0.5 rounded-full
                                        {{ $m->status==='new' ? 'bg-violet-500/20 text-violet-300' : ($m->status==='read' ? 'bg-white/10 text-white/60' : 'bg-gray-500/20 text-gray-400') }}">
                                        {{ ucfirst($m->status) }}
                                    </span>
                                </div>
                                <div class="text-sm text-white">{{ $m->subject }}</div>
                                <div class="text-[11px] text-white/40 mt-1">{{ $m->created_at->format('M j, Y g:i a') }}</div>
                                <div x-show="open" x-cloak class="mt-3 text-sm text-white/80 whitespace-pre-line">{{ $m->message }}</div>
                            </div>
                            <div class="flex flex-col gap-1.5 flex-shrink-0">
                                <button type="button" @click="open=!open" class="px-3 py-1.5 bg-white/10 hover:bg-white/20 rounded-lg text-xs text-white"><span x-text="open?'Hide':'View'"></span></button>
                                @if($m->status !== 'read')
                                    <form method="POST" action="{{ route('admin.contact-inbox.read', $m) }}">@csrf
                                        <button type="submit" class="w-full px-3 py-1.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-xs text-white">Mark read</button>
                                    </form>
                                @endif
                                @if($m->status !== 'archived')
                                    <form method="POST" action="{{ route('admin.contact-inbox.archive', $m) }}">@csrf
                                        <button type="submit" class="w-full px-3 py-1.5 bg-white/5 hover:bg-white/10 rounded-lg text-xs text-white/80">Archive</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.contact-inbox.destroy', $m) }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this message?', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">@csrf @method('DELETE')
                                    <button type="submit" class="w-full px-3 py-1.5 bg-red-500/10 hover:bg-red-500/20 text-red-300 rounded-lg text-xs">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-5">{{ $messages->links() }}</div>
        @endif
    </div>
</div>
@endsection
