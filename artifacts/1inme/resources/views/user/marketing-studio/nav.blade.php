@if(isset($campaign) && $campaign)
@include('user.marketing-studio.styles')
<div class="ms-page">
@include('user.partials.page-hero',['title'=>$campaign->name,'subtitle'=>'One brief. Connected strategy, editable materials and measurable results.','emblemIcon'=>'fa-wand-magic-sparkles','back'=>route('user.marketing-studio.index'),'actions'=>[['label'=>'Budget & Forecast','url'=>route('user.marketing-plan.create',['from_campaign'=>$campaign->id]),'icon'=>'fa-chart-line','class'=>'btn-ghost']],'chips'=>[['text'=>'AI Marketing Studio'],['text'=>$campaign->plan_id?'Connected to Planner':'Campaign']]])
<nav class="ms-tabs" aria-label="Campaign sections">@foreach(['overview'=>'Overview','strategy'=>'Strategy','create'=>'Create','results'=>'Results'] as $key=>$label)<a href="{{ route('user.marketing-studio.show',[$campaign,'tab'=>$key]) }}" @if(($tab??'')===$key) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
</div>
@endif
