@php
$formats=\App\Modules\Common\Support\HomeFormatCatalogue::items($linkTypes??[]);
$formatGroups=['all'=>'All','pages'=>'Pages & profiles','sell'=>'Sell & book','connect'=>'Collect & connect','share'=>'Share & redirect'];
$first=$formats[0];
@endphp
<section id="create" class="sf-showcase sec-rule" aria-labelledby="create-h">
<div class="sf-container">
<header class="sf-heading"><span>What you can create</span><h2 id="create-h">What would you like to create?</h2><p>From a simple short link to a complete business page. Choose what fits your next idea.</p><b class="sf-total">Explore {{ count($formats) }} formats</b></header>
<div class="sf-toolbar"><div class="sf-filters" role="group" aria-label="Format categories">@foreach($formatGroups as $id=>$label)<button type="button" data-sf-filter="{{ $id }}" aria-pressed="{{ $id==='all'?'true':'false' }}">{{ $label }} <span data-sf-count="{{ $id }}">{{ $id==='all'?count($formats):collect($formats)->where('group',$id)->count() }}</span></button>@endforeach</div><label class="sf-search"><i class="fas fa-search" aria-hidden="true"></i><input type="search" placeholder="Find a format…" aria-label="Search creation formats" data-sf-search></label></div>
<p class="sf-results" role="status" data-sf-results>Showing {{ count($formats) }} of {{ count($formats) }} formats</p>
<div class="sf-layout">
<div class="sf-list" aria-label="Creation formats">
@foreach($formats as $i=>$format)
<button type="button" class="sf-card {{ $i===0?'sf-selected':'' }}" style="--sf-accent:{{ $format['color'] }}" data-sf-card="{{ $i }}" data-art="{{ $format['art'] }}" data-group="{{ $format['group'] }}" data-name="{{ $format['name'] }}" data-desc="{{ $format['desc'] }}" data-usage="{{ $format['usage'] }}" data-demo="{{ $format['demo']??'' }}" data-create="{{ $format['create'] }}" data-color="{{ $format['color'] }}" aria-pressed="{{ $i===0?'true':'false' }}" aria-controls="sf-preview">
<span class="sf-card-art">@include('home.partials.format-art',['art'=>$format['art'],'artId'=>'format-art-'.$i])</span><strong>{{ $format['name'] }}</strong><span class="sf-card-desc">{{ $format['desc'] }}</span><span class="sf-card-hint">Preview <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
</button>
@endforeach
<div class="sf-empty" data-sf-empty hidden><i class="fas fa-search" aria-hidden="true"></i><strong>No matching formats</strong><p>Try another keyword or category.</p><button type="button" data-sf-reset>Clear filters</button></div>
</div>
<aside id="sf-preview" data-art="{{ $first['art'] }}" class="sf-preview" style="--sf-accent:{{ $first['color'] }}" aria-label="Selected format preview">
<div class="sf-preview-top"><span>Explore the experience</span><button type="button" data-sf-motion aria-label="Pause preview animation"><i class="fas fa-pause" aria-hidden="true"></i></button></div>
<h3 data-sf-title>{{ $first['name'] }}</h3><p data-sf-description>{{ $first['desc'] }}</p>
<div class="sf-preview-scene" data-sf-scene>@include('home.partials.link-type-scene',['ltSlug'=>$first['slug'],'ltColor'=>$first['color']])</div>
<p class="sf-preview-caption">Illustrative preview · editable in Sayzio</p>
<div class="sf-use" data-sf-use @if(!$first['usage']) hidden @endif><strong>How you could use it</strong><p data-sf-usage>{{ $first['usage'] }}</p></div>
<div class="sf-preview-actions">@auth<a class="sf-create" data-sf-create href="{{ $first['create'] }}">Create this <i class="fas fa-arrow-right" aria-hidden="true"></i></a>@else<a class="sf-create" data-sf-register href="{{ route('register.page') }}">Create this <i class="fas fa-arrow-right" aria-hidden="true"></i></a>@endauth<a class="sf-demo" data-sf-demo @if($first['demo']) href="{{ $first['demo'] }}" @else hidden @endif target="_blank" rel="noopener">Try demo <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a></div>
</aside>
</div>
@foreach($formats as $i=>$format)<template data-sf-template="{{ $i }}">@include('home.partials.link-type-scene',['ltSlug'=>$format['slug'],'ltColor'=>$format['color']])</template>@endforeach
</div>
@include('home.partials.format-showcase-styles')
@include('home.partials.format-showcase-script')
</section>
