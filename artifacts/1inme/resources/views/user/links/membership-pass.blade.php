@extends('user.layouts.app')
@section('title','Member pass validation')
@section('content')
<section style="padding:28px;border:1px solid var(--border-strong);border-radius:20px;background:var(--bg-card)"><h1 style="font-size:30px;font-weight:700;color:{{ $valid?'#16834d':'#c43939' }}">{{ $valid?'Valid pass':'Invalid pass' }}</h1><h2>{{ $member->name }}</h2><p>{{ $link->title?:$link->alias }} · Member #{{ $member->id }}</p><p>Status: {{ $member->isExpired()?'Expired':ucfirst($member->status) }} · Expiry: {{ $member->expires_at?->format('d M Y')??'No expiry' }}</p>@if($member->profilePhoto && $member->profilePhoto->isScanClean())<img src="{{ $member->profilePhoto->url }}" alt="Member profile picture" style="width:100px;height:100px;object-fit:cover;border-radius:16px">@endif<p>Checked against the current membership record at {{ now()->format('d M Y H:i') }}.</p><a class="btn-primary" href="{{ route('user.links.memberships.scanner',$link) }}">Scan another pass</a></section>
@endsection
