@extends('user.layouts.app')
@section('title','Catalog inquiries')
@section('content')
@include('user.links.partials.editor-header',['activeMainTab'=>'catalog'])
@include('user.links.catalog.editor-css')
<div class="catalog-editor"><section><h2>Inquiries</h2><p>Contact visitors to arrange a viewing or discuss enrollment. No booking or seat is automatically confirmed.</p><a class="action" href="{{ route('user.links.catalog.editor',$link) }}">Back to catalog</a>
@forelse($inquiries as $inquiry)<article class="inquiry"><h3>{{ $inquiry->entry_title }}</h3><p>{{ $inquiry->name }} · {{ $inquiry->created_at->format('d M Y H:i') }} UTC</p><p><a href="mailto:{{ $inquiry->email }}">{{ $inquiry->email }}</a> @if($inquiry->phone)· {{ $inquiry->phone }}@endif</p>
@if($inquiry->preferred_date)<p>Preferred viewing date: {{ $inquiry->preferred_date->format('d M Y') }}</p>@endif
@if($inquiry->batch)<p>Requested batch: {{ $inquiry->batch }}</p>@endif<p style="white-space:pre-wrap">{{ $inquiry->message }}</p>
<form method="post" action="{{ route('user.links.catalog.save',[$link,'inquiry']) }}">@csrf<input type="hidden" name="id" value="{{ $inquiry->id }}"><label>Status<select name="status">@foreach(['new','contacted','closed'] as $status)<option @selected($inquiry->status===$status)>{{ $status }}</option>@endforeach</select></label><button>Update status</button></form></article>@empty<p>No inquiries yet. Requests will appear here when visitors submit them.</p>@endforelse
{{ $inquiries->links() }}</section></div>
@endsection
