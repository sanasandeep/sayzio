@php
$catalog=$link->listingCatalog()->with(['categories','entries'])->first();
$s=$catalog?->settings ?? [];
$property=$link->type === \App\Modules\User\Models\Link::TYPE_REAL_ESTATE;
$entries=($catalog?->entries ?? collect())->where('is_active',true);
$groups=$entries->groupBy(fn($entry)=>$entry->category_id ?: 0);
$categories=$catalog?->categories ?? collect();
$blkSectionIds=$categories->filter(fn($cat)=>$groups->get($cat->id,collect())->isNotEmpty())->pluck('id')->all();
$colors=[];
foreach(['background'=>'#f6f7f9','surface'=>'#ffffff','text_color'=>'#172033','accent'=>($property ? '#0f766e' : '#4338ca')] as $key=>$default) $colors[$key]=preg_match('/^#[0-9a-fA-F]{6}$/',$s[$key] ?? '') ? $s[$key] : $default;
$accentRgb=array_map(fn($offset)=>hexdec(substr($colors['accent'],$offset,2)),[1,3,5]);
$accentInk=(.299*$accentRgb[0]+.587*$accentRgb[1]+.114*$accentRgb[2])>150 ? '#111827' : '#ffffff';
$font=in_array($s['font'] ?? '',['sans-serif','serif','monospace'],true) ? $s['font'] : 'sans-serif';
$title=$s['business_name'] ?? $link->title ?: ($property ? 'Properties' : 'Education & Training');
$currency=preg_match('/^[A-Z]{3}$/',$s['currency'] ?? '') ? $s['currency'] : 'INR';
$timezone=in_array($s['timezone'] ?? '',timezone_identifiers_list(),true) ? $s['timezone'] : 'Asia/Kolkata';
$safeUrl=fn($url)=>is_string($url) && preg_match('/^https?:\/\//i',$url) && filter_var($url,FILTER_VALIDATE_URL);
$layout=($s['layout'] ?? '') === 'list' ? 'list' : 'grid';
@endphp
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $title }}</title>
@include('common.partials.toolbar-theme-color')
@include('common.partials.biolink-block-assets')
<script defer src="{{ asset('js/vendor/alpine-collapse.min.js') }}"></script><script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
@include('common.catalog.styles')
</head><body>
<main class="catalog {{ $property ? 'properties' : 'courses' }} {{ $layout }}" id="listing-catalog">
@include('common.partials.biolink-block-list',['blkFontColor'=>$colors['text_color'],'blkGlobalTheme'=>[],'blkBtnInline'=>'','blkSlot'=>'top','blkSectionIds'=>$blkSectionIds,'blkEmpty'=>false])
<header class="catalog-hero">
<div class="hero-copy"><p class="eyebrow">{{ $property ? 'FIND YOUR NEXT ADDRESS' : 'MAKE ROOM FOR WHAT’S NEXT' }}</p><h1>{{ $title }}</h1><p>{{ $s['intro'] ?? ($property ? 'Explore spaces to live, work and grow.' : 'Build practical skills with courses that fit your goals.') }}</p>
<div class="contact-links">@if(filter_var($s['email'] ?? '',FILTER_VALIDATE_EMAIL))<a href="mailto:{{ $s['email'] }}">Contact us</a>@endif @php($phone=preg_replace('/[^0-9+]/','',$s['phone'] ?? '')) @if($phone)<a href="tel:{{ $phone }}">Call us</a>@endif</div></div>
@if($safeUrl($s['hero_url'] ?? ''))<img class="hero-image" src="{{ $s['hero_url'] }}" alt="" referrerpolicy="no-referrer">@endif
</header>
@if(session('catalog_success'))<p class="notice" role="status">{{ session('catalog_success') }}</p>@endif
@if($errors->any())<div class="notice" role="alert"><strong>Please check your request.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if($entries->isNotEmpty())
<div class="catalog-filters" role="search"><label>Search<input id="catalog-search" type="search" placeholder="{{ $property ? 'Title, address or amenities' : 'Course, instructor or topic' }}"></label>
<label>{{ $property ? 'Sale / rent' : 'Delivery mode' }}<select id="catalog-kind"><option value="">All</option>@foreach($property ? ['sale'=>'For sale','rent'=>'For rent'] : ['online'=>'Online','in_person'=>'In person','hybrid'=>'Hybrid'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
<label>Category<select id="catalog-category"><option value="">All categories</option>@if($groups->has(0))<option value="0">General</option>@endif @foreach($categories as $cat)@if(in_array($cat->id,$blkSectionIds))<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endif @endforeach</select></label>
<label>Location<select id="catalog-location"><option value="">All locations</option>@foreach($entries->map(fn($e)=>trim($e->details['location'] ?? ''))->filter()->unique()->sort() as $location)<option value="{{ $location }}">{{ $location }}</option>@endforeach</select></label>
<label>Maximum {{ $property ? 'price' : 'fee' }} ({{ $currency }})<input id="catalog-price" type="number" min="0" placeholder="Any"></label></div>
<p id="catalog-result-count" role="status">{{ $entries->count() }} {{ $property ? 'properties' : 'courses' }}</p>
@endif
@include('common.partials.biolink-block-list',['blkFontColor'=>$colors['text_color'],'blkGlobalTheme'=>[],'blkBtnInline'=>'','blkSlot'=>'above','blkSectionIds'=>$blkSectionIds,'blkEmpty'=>false])
@foreach(collect([['id'=>0,'name'=>'General']])->concat($categories->map(fn($c)=>['id'=>$c->id,'name'=>$c->name])) as $category)
@php($members=$groups->get($category['id'],collect()))
@if($members->isNotEmpty())
<section class="catalog-section" data-catalog-section id="category-{{ $category['id'] }}"><h2>{{ $category['name'] }}</h2><div class="listing-grid">
@foreach($members as $entry)
@php
$d=$entry->details ?? [];
$photos=array_values(array_filter($d['photos'] ?? [],$safeUrl));
$price=isset($d['price']) && is_numeric($d['price']) ? (float)$d['price'] : null;
$priceLabel=$price === null ? 'Price on request' : ($price===0.0 ? ($property ? $currency.' 0' : 'Free') : $currency.' '.number_format($price,2));
$statusLabels=$property ? ['available'=>'Available','reserved'=>'Reserved','sold'=>'Sold','rented'=>'Rented'] : ['enrolling'=>'Enrolling now','full'=>'Full','closed'=>'Closed','coming_soon'=>'Coming soon'];
@endphp
<article class="listing" id="listing-{{ $entry->id }}" data-listing data-search="{{ mb_strtolower($entry->title.' '.implode(' ',array_filter([$d['location'] ?? '',$d['description'] ?? '',$d['amenities'] ?? '',$d['instructor'] ?? '',$d['syllabus'] ?? '']))) }}" data-kind="{{ $property ? ($d['purpose'] ?? '') : ($d['mode'] ?? '') }}" data-category="{{ $entry->category_id ?: 0 }}" data-location="{{ $d['location'] ?? '' }}" data-price="{{ $price ?? '' }}">
@if($photos)<img class="listing-photo" src="{{ $photos[0] }}" alt="{{ $entry->title }}" loading="lazy" referrerpolicy="no-referrer">@else<div class="listing-placeholder" aria-hidden="true"><i class="fas {{ $property ? 'fa-house' : 'fa-graduation-cap' }}"></i></div>@endif
<div class="listing-content"><div class="listing-tags"><span>{{ $statusLabels[$d['status'] ?? ''] ?? 'Details coming soon' }}</span>@if($entry->is_featured)<span>Featured</span>@endif</div>
<h3>{{ $entry->title }}</h3><p class="listing-price">{{ $priceLabel }} @if(!empty($d['price_note']))<small>{{ $d['price_note'] }}</small>@endif</p>
@if($property)@include('common.catalog.property-details')@else @include('common.catalog.course-details')@endif
@if(!empty($d['location']))<p class="listing-location"><i class="fas fa-location-dot" aria-hidden="true"></i> {{ $d['location'] }} <a href="https://www.google.com/maps/search/?api=1&query={{ rawurlencode($d['location']) }}" target="_blank" rel="noopener">Map ↗</a></p>@endif
<details class="listing-details"><summary>{{ $property ? 'Explore this property' : 'Course details & batches' }}</summary>
<p class="preserve-lines">{{ $d['description'] ?? '' }}</p>
@if(count($photos)>1)<div class="photo-gallery">@foreach(array_slice($photos,1) as $photo)<a href="{{ $photo }}" target="_blank" rel="noopener"><img src="{{ $photo }}" alt="{{ $entry->title }} photo {{ $loop->iteration+1 }}" loading="lazy" referrerpolicy="no-referrer"></a>@endforeach</div>@endif
@if($property)
@if(!empty($d['amenities']))<h4>Amenities</h4><ul class="amenities">@foreach(preg_split('/\r?\n/',$d['amenities']) as $amenity)@if(trim($amenity))<li>{{ trim($amenity) }}</li>@endif @endforeach</ul>@endif
@else
@include('common.catalog.course-expanded')
@endif
</details>
<div class="listing-actions"><a href="#listing-{{ $entry->id }}" class="permalink">Direct link</a>
@if($entry->acceptsInquiries($property) && ($s['inquiries_enabled'] ?? true))<button type="button" data-open-inquiry="inquiry-{{ $entry->id }}">{{ $property ? 'Request a viewing' : 'Enquire / enroll' }}</button>@endif
@if($safeUrl($d['external_url'] ?? ''))<a class="external-action" href="{{ $d['external_url'] }}" target="_blank" rel="noopener">{{ $property ? 'Contact agent' : 'Enrollment website' }} ↗</a>@endif</div>
@if($entry->acceptsInquiries($property) && ($s['inquiries_enabled'] ?? true))@include('common.catalog.inquiry-form')@endif
</div></article>
@endforeach
</div></section>
@if($category['id'])@include('common.partials.biolink-block-list',['blkFontColor'=>$colors['text_color'],'blkGlobalTheme'=>[],'blkBtnInline'=>'','blkSlot'=>\App\Modules\User\Support\MenuBlockSlot::forSection((int)$category['id']),'blkSectionIds'=>$blkSectionIds,'blkEmpty'=>false])@endif
@endif
@endforeach
@if($entries->isEmpty())<section class="catalog-empty"><h2>{{ $property ? 'Good spaces are worth discovering.' : 'Your next chapter starts here.' }}</h2><p>{{ $property ? 'Our property collection is being prepared. Check back soon for available listings.' : 'Our course catalog is being prepared. Check back soon for courses and upcoming batches.' }}</p></section>@else<p id="catalog-no-results" class="notice" hidden>No matching listings. Try widening your filters.</p>@endif
@include('common.partials.biolink-block-list',['blkFontColor'=>$colors['text_color'],'blkGlobalTheme'=>[],'blkBtnInline'=>'','blkSlot'=>'below','blkSectionIds'=>$blkSectionIds,'blkEmpty'=>false])
</main>
<script src="{{ asset('js/listing-catalog.js') }}" defer></script>
@include('common.partials.share-button',['sbLink'=>$link,'sbUrl'=>$link->getShortUrl(),'sbTitle'=>$title])
</body></html>
