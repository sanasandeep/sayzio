@extends('user.layouts.app')
@section('title','Ask Zio conversations')
@section('content')
@include('user.partials.ai-workspace-nav')
<div class="card p-6 space-y-4">
<h1 class="text-2xl font-bold">Ask Zio · Page guidance conversations</h1>
<p>Your saved conversations with floating Ask Zio. Empty page visits are hidden. General chat and Business insights keep their own thread lists.</p>
@if($conversation)
<a href="{{ route('user.ai.zio-conversations') }}">← All sessions</a>
@foreach($messages as $message)
<div class="border rounded-xl p-4"><strong>{{ $message->role==='user' ? 'You' : 'Zio' }}</strong><p class="whitespace-pre-wrap">{{ $message->content }}</p><small>{{ $message->created_at }}</small></div>
@endforeach
{{ $messages->links() }}
@else
<p class="text-sm opacity-70">Message counts include guidance and supported actions, which may not call AI. Coins show recorded conversation usage; no recorded charge does not prove that no AI was used. Your wallet transaction history is the billing record.</p>
@forelse($conversations as $session)
<a class="block rounded-2xl p-5" style="border:1px solid var(--border-glass,#e2e8f0);background:var(--bg-glass-light,#fff)" href="{{ route('user.ai.zio-conversations.show',$session) }}">
<strong>{{ \Illuminate\Support\Str::limit($session->messages->first()?->content ?: 'Ask Zio conversation',90) }}</strong>
<p class="text-xs opacity-60 mt-2">{{ $session->last_message_at ?? $session->created_at }} · {{ $session->user_messages_count }} messages from you · {{ $session->assistant_messages_count }} replies</p>
@php $recordedCoins=max((int)$session->credits_spent,(int)$session->messages_sum_credits_spent); @endphp
<p class="text-sm mt-2">{{ $recordedCoins > 0 ? number_format($recordedCoins).' recorded coins' : 'No coin charge recorded for this conversation' }}</p>
@if($session->last_page_title)<p class="text-xs opacity-60 mt-1">Last page: {{ $session->last_page_title }}</p>@endif
</a>
@empty<p>No saved page guidance sessions linked to this account yet.</p>@endforelse
{{ $conversations->links() }}
@endif
</div>
@endsection
