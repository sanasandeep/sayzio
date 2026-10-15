@extends('user.layouts.app')
@section('title','Ask Zio conversations')
@section('content')
@include('user.partials.ai-workspace-nav')
<div class="card p-6 space-y-4">
<h1 class="text-2xl font-bold">Ask Zio · Page guidance conversations</h1>
<p>Saved sessions linked to your account. General chat and Business insights keep their own thread lists.</p>
@if($conversation)
<a href="{{ route('user.ai.zio-conversations') }}">← All sessions</a>
@foreach($messages as $message)
<div class="border rounded-xl p-4"><strong>{{ $message->role==='user' ? 'You' : 'Zio' }}</strong><p class="whitespace-pre-wrap">{{ $message->content }}</p><small>{{ $message->created_at }}</small></div>
@endforeach
{{ $messages->links() }}
@else
@forelse($conversations as $session)
<a class="block border rounded-xl p-4" href="{{ route('user.ai.zio-conversations.show',$session) }}"><strong>{{ $session->last_page_title ?: 'Page guidance session' }}</strong><p>{{ $session->last_message_at }} · {{ $session->turns_count }} turns · {{ $session->credits_spent }} coins</p></a>
@empty<p>No saved page guidance sessions linked to this account yet.</p>@endforelse
{{ $conversations->links() }}
@endif
</div>
@endsection
