@extends('user.layouts.app')
@section('title','New campaign · AI Marketing Studio')
@section('content')
@include('user.marketing-studio.styles')
<div class="ms-page">
@include('user.partials.page-hero',['title'=>'Start a campaign','subtitle'=>'A few details are enough. You can refine everything later.','emblemIcon'=>'fa-wand-magic-sparkles','back'=>route('user.marketing-studio.index'),'chips'=>[['text'=>'AI Marketing Studio']]])
@if($errors->any())<div class="bk-feedback" role="alert">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('user.marketing-studio.store') }}" class="ms-card mt-6">@csrf
@if(in_array(request('output'),\App\Services\Brand\AiBrandStudioService::ASSET_KINDS))<input type="hidden" name="output" value="{{ request('output') }}">@endif
<div class="bk-form-grid"><label class="bk-check"><input type="radio" name="path" value="quick" checked><span><strong>Quick create</strong><span class="block bk-muted">I already know what I need.</span></span></label><label class="bk-check"><input type="radio" name="path" value="strategy"><span><strong>Plan with AI</strong><span class="block bk-muted">Help me decide what to do.</span></span></label></div>
@include('user.marketing-studio.brief')
<div class="bk-actions mt-5"><button type="submit" class="btn-primary">Continue</button><span class="bk-muted">Saving the brief is free. AI estimates appear before generation.</span></div>
</form></div>
@endsection
