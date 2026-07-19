@extends('admin.layouts.app')
@section('title', 'Contact Inbox')
@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="glass rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-white">Contact Inbox</h2>
            <div class="flex items-center gap-2">
                @foreach(['all'=>'All','new'=>'New','read'=>'Read','replied'=>'Replied','archived'=>'Archived','spam'=>'Spam'] as $key=>$label)
                    <a href="{{ route('admin.contact-inbox.index', ['status'=>$key]) }}"
                       class="px-3 py-1.5 rounded-lg text-xs {{ $status===$key ? 'bg-blue-600 text-white' : 'bg-white/5 text-white/70 hover:bg-white/10' }}">
                        {{ $label }}@if($key==='spam' && ($spamCount ?? 0) > 0) <span class="ml-1 px-1.5 py-0.5 rounded-full bg-red-500/20 text-red-300 text-[10px]">{{ $spamCount }}</span>@endif
                    </a>
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
                    @php($replyFailed = old('reply_message_id') == $m->id && $errors->any())
                    <div class="bg-white/5 border border-white/10 rounded-xl p-4" x-data="{ open:{{ $replyFailed ? 'true' : 'false' }}, replying:{{ $replyFailed ? 'true' : 'false' }} }">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 mb-1 flex-wrap">
                                    <span class="text-sm font-semibold text-white">{{ $m->name }}</span>
                                    @if($m->email)
                                        <span class="text-xs text-white/40">&lt;{{ $m->email }}&gt;</span>
                                    @elseif($m->contact_phone)
                                        <span class="text-xs text-white/40">{{ $m->contact_phone }}</span>
                                    @endif
                                    <span class="text-[10px] px-2 py-0.5 rounded-full
                                        {{ $m->status==='new' ? 'bg-blue-500/20 text-blue-300' : ($m->status==='replied' ? 'bg-green-500/20 text-green-300' : ($m->status==='read' ? 'bg-white/10 text-white/60' : ($m->status==='spam' ? 'bg-red-500/20 text-red-300' : 'bg-gray-500/20 text-gray-400'))) }}">
                                        {{ ucfirst($m->status) }}
                                    </span>
                                    @if(!empty($m->contact_channel))
                                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-primary-500/20 text-primary-200">
                                            {{ \App\Modules\Common\Services\QuickContactService::channelLabel($m->contact_channel) }}
                                        </span>
                                    @endif
                                </div>
                                <div class="text-sm text-white">{{ $m->subject }}</div>
                                @if(!$m->email && $m->contact_phone)
                                    <div class="text-xs text-white/60 mt-1">{{ __('Phone') }}: <span class="text-white/80">{{ $m->contact_phone }}</span></div>
                                @endif
                                <div class="text-[11px] text-white/40 mt-1">{{ $m->created_at->format('M j, Y g:i a') }}</div>
                                <div x-show="open" x-cloak class="mt-3 text-sm text-white/80 whitespace-pre-line">{{ $m->message }}</div>

                                {{-- Reply thread --}}
                                @if($m->replies->isNotEmpty())
                                    <div x-show="open" x-cloak class="mt-4 space-y-3">
                                        @foreach($m->replies as $reply)
                                            <div class="border-l-2 border-green-500/30 pl-3">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-green-500/15 text-green-300 font-medium">Reply</span>
                                                    <span class="text-[11px] text-white/40">{{ $reply->admin->name ?? 'Admin' }} &middot; {{ $reply->created_at->format('M j, Y g:i a') }}</span>
                                                </div>
                                                <div class="text-xs text-white/70 font-medium mb-1">{{ $reply->subject }}</div>
                                                <div class="text-sm text-white/80 whitespace-pre-line">{{ $reply->body }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Reply compose box --}}
                                @if($m->email)
                                    <div x-show="replying" x-cloak class="mt-4">
                                        <form method="POST" action="{{ route('admin.contact-inbox.reply', $m) }}">
                                            @csrf
                                            <input type="hidden" name="reply_message_id" value="{{ $m->id }}">
                                            <div class="space-y-2">
                                                @if($replyFailed)
                                                    <div class="text-xs text-red-300 bg-red-500/10 border border-red-500/20 rounded-lg px-3 py-2 space-y-0.5">
                                                        @if($errors->has('reply'))
                                                            <div>{{ $errors->first('reply') }}</div>
                                                        @endif
                                                        @if($errors->has('reply_subject'))
                                                            <div>{{ $errors->first('reply_subject') }}</div>
                                                        @endif
                                                        @if($errors->has('reply_body'))
                                                            <div>{{ $errors->first('reply_body') }}</div>
                                                        @endif
                                                    </div>
                                                @endif
                                                <input type="text" name="reply_subject" value="{{ $replyFailed ? old('reply_subject') : 'Re: ' . $m->subject }}"
                                                    class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/30 focus:outline-none focus:border-blue-500/50"
                                                    placeholder="Subject" required maxlength="500">
                                                <textarea name="reply_body" rows="5" required maxlength="20000"
                                                    class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/30 focus:outline-none focus:border-blue-500/50 resize-y"
                                                    placeholder="Write your reply…">{{ $replyFailed ? old('reply_body') : '' }}</textarea>
                                                <div class="flex items-center gap-2">
                                                    <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg text-xs text-white font-medium">Send reply</button>
                                                    <button type="button" @click="replying=false" class="px-4 py-2 bg-white/5 hover:bg-white/10 rounded-lg text-xs text-white/70">Cancel</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                @else
                                    <div x-show="replying" x-cloak class="mt-3 text-xs text-white/40 italic">
                                        No email address, reply via {{ $m->contact_phone ? 'phone: ' . $m->contact_phone : ($m->contact_channel ?? 'the sender\'s channel') }}.
                                    </div>
                                @endif
                            </div>
                            <div class="flex flex-col gap-1.5 flex-shrink-0">
                                <button type="button" @click="open=!open" class="px-3 py-1.5 bg-white/10 hover:bg-white/20 rounded-lg text-xs text-white"><span x-text="open?'Hide':'View'"></span></button>
                                @if($m->email)
                                    <button type="button" @click="open=true; replying=!replying"
                                        class="px-3 py-1.5 bg-green-600/80 hover:bg-green-600 rounded-lg text-xs text-white">
                                        Reply
                                    </button>
                                @endif
                                @if($m->status !== 'read' && $m->status !== 'replied')
                                    <form method="POST" action="{{ route('admin.contact-inbox.read', $m) }}">@csrf
                                        <button type="submit" class="w-full px-3 py-1.5 bg-blue-600 hover:bg-blue-700 rounded-lg text-xs text-white">Mark read</button>
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
