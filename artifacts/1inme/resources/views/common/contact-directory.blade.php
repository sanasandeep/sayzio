@php
    $directory = $link->contactDirectory()->with(['categories', 'contacts'])->first();
    $s = $directory?->settings ?? [];
    $allCategories = $directory?->categories ?? collect();
    $categories = $allCategories->whereNull('parent_id')->flatMap(fn ($category) => collect([$category])->concat($allCategories->where('parent_id', $category->id)));
    $categoryName = fn ($category) => ($category->parent_id ? ($allCategories->firstWhere('id', $category->parent_id)?->name.' / ') : '').$category->name;
    $contacts = ($directory?->contacts ?? collect())->where('is_active', true);
    $groups = $contacts->groupBy(fn($contact) => $contact->category_id ?: 0);
    $blkSectionIds = $categories->filter(fn($category) => $groups->get($category->id, collect())->isNotEmpty())->pluck('id')->all();
    $colors = [];
    foreach (['background'=>'#f5f7fb','surface'=>'#ffffff','text_color'=>'#172033','accent'=>'#2563eb'] as $key => $default) {
        $colors[$key] = preg_match('/^#[0-9a-fA-F]{6}$/', $s[$key] ?? '') ? $s[$key] : $default;
    }
    $font = in_array($s['font'] ?? '', ['sans-serif','serif','monospace'], true) ? $s['font'] : 'sans-serif';
    $timezone = in_array($s['timezone'] ?? '', timezone_identifiers_list(), true) ? $s['timezone'] : 'UTC';
    $layout = in_array($s['layout'] ?? '', ['grid','compact'], true) ? $s['layout'] : 'list';
    $title = $link->title ?: 'Contact directory';
    $surfaceInk = $colors['text_color'];
    $pbBs = $link->settings['biolink'] ?? [];
    $pbOn = \App\Modules\User\Support\PageBackground::chosen($pbBs);
    $pb = $pbOn ? \App\Modules\User\Support\PageBackground::resolve($pbBs) : null;
    $presentation = \App\Modules\User\Support\MenuPresentation::resolve($pbBs, $s, $colors['accent']);
    if (!empty($pbBs['font_family'])) {
        $font = $presentation['font_css'];
    }
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $pbBs['font_color'] ?? '')) {
        $colors['text_color'] = $pbBs['font_color'];
    }
@endphp
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $title }}</title>
@include('common.partials.toolbar-theme-color')
@include('common.partials.biolink-block-assets')
@if($presentation['font_href'])
<link rel="stylesheet" href="{{ $presentation['font_href'] }}">
@endif
<script defer src="{{ asset('js/vendor/alpine-collapse.min.js') }}"></script><script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
<style>
body{margin:0;background:{{ $colors['background'] }};color:{{ $colors['text_color'] }};font-family:{{ $font }}}.directory{max-width:900px;margin:auto;padding:32px 20px 90px}.directory h1{font-size:clamp(28px,6vw,44px);line-height:1.1;margin:16px 0}.directory p{line-height:1.55}.directory input{font:inherit;width:100%;box-sizing:border-box;border:1px solid currentColor;border-radius:10px;padding:13px;background:{{ $colors['surface'] }};color:inherit}.directory nav{display:flex;gap:8px;flex-wrap:wrap;margin:18px 0}.directory a{color:inherit}.directory nav a,.contact-actions a{border:1px solid currentColor;padding:8px 12px;border-radius:9px;text-decoration:none;font-size:14px}.directory section{scroll-margin-top:20px;margin-top:28px}.directory h2{font-size:23px;margin-bottom:14px}.contact-list{display:grid;gap:12px}.directory.grid .contact-list{grid-template-columns:repeat(auto-fit,minmax(260px,1fr))}.contact{background:{{ $colors['surface'] }};border:1px solid color-mix(in srgb,currentColor 18%,transparent);border-radius:16px;padding:20px;scroll-margin-top:20px}.directory.compact .contact{padding:12px;border-radius:8px}.contact-heading{display:flex;gap:14px;align-items:center}.contact img{width:58px;height:58px;object-fit:cover;border-radius:12px}.contact h3{font-size:19px;font-weight:700;margin:0}.contact small{display:block;margin-top:5px}.contact-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:15px}.availability{font-size:13px;margin-top:10px}.emergency{border-left:4px solid {{ $colors['accent'] }};padding:12px 16px;background:{{ $colors['surface'] }}}[hidden]{display:none!important}.empty{padding:48px 20px;text-align:center}.no-results{padding:20px}
.directory header{padding:8px 0 24px;border-bottom:1px solid color-mix(in srgb,currentColor 15%,transparent)}.directory header>p:first-child{font-size:11px;letter-spacing:.16em;font-weight:700;opacity:.65}.directory .empty{margin-top:28px;padding:36px 24px;border:1px solid color-mix(in srgb,currentColor 15%,transparent);border-radius:20px;background:{{ $colors['surface'] }};color:{{ $surfaceInk }}}.empty-icon{display:inline-grid;place-items:center;width:56px;height:56px;border-radius:16px;background:color-mix(in srgb,{{ $colors['accent'] }} 12%,transparent);color:{{ $colors['accent'] }};font-size:24px}.directory .empty h2{margin:20px 0 10px}.directory .empty p{max-width:360px;margin:0 auto}.directory .empty a{display:inline-block;margin-top:24px;padding:10px 16px;border:1px solid currentColor;border-radius:10px;text-decoration:none;font-weight:600}
/* A single composed public landing state, rather than two disconnected headings. */
.directory-empty{max-width:720px;padding-top:clamp(28px,8vh,72px)}.directory-welcome{text-align:center;margin:0!important;padding:32px 24px 40px;border:1px solid color-mix(in srgb,{{ $surfaceInk }} 12%,transparent);border-radius:28px;background:{{ $colors['surface'] }};color:{{ $surfaceInk }};box-shadow:0 12px 40px rgba(0,0,0,.04);--directory-surface:{{ $colors['surface'] }}}.directory-illustration{width:210px;max-width:70%;margin:0 auto 12px;color:{{ $colors['accent'] }}}.directory-illustration svg{display:block;width:100%;height:auto}.directory-welcome .welcome-eyebrow{font-size:10px;font-weight:700;letter-spacing:.18em;opacity:.6;margin:0 0 12px}.directory-welcome h1{font-weight:750;letter-spacing:-.035em;font-size:clamp(27px,6vw,40px);margin:0 0 12px;overflow-wrap:anywhere}.directory-welcome .welcome-intro{font-size:16px;max-width:420px;margin:0 auto;opacity:.75}.welcome-status{display:inline-flex;align-items:center;gap:8px;margin:28px 0 14px;padding:9px 13px;font-size:12px;font-weight:600;border-radius:999px;background:color-mix(in srgb,{{ $surfaceInk }} 6%,transparent)}.welcome-status span{width:6px;height:6px;border-radius:50%;background:currentColor;opacity:.5}.directory-welcome .welcome-note{font-size:13px;line-height:1.7;max-width:330px;margin:0 auto;opacity:.65}.welcome-setup{display:inline-block;margin-top:24px;padding:10px 16px;border:1px solid currentColor;border-radius:10px;text-decoration:none;font-size:13px;font-weight:600}@media(max-width:480px){.directory-empty{padding:24px 16px 80px}.directory-welcome{padding:24px 20px 32px;border-radius:22px}.directory-illustration{width:180px}}
@if($pbOn)
body { @include('common.page-background.body-declarations') }
@include('common.page-background.css')
@endif
</style></head><body>
@if($pbOn)
@include('common.page-background.layers')
@endif
<main class="directory {{ $layout }} {{ $contacts->isEmpty() ? 'directory-empty' : '' }}" id="directory">
@include('common.partials.biolink-block-list', ['blkFontColor'=>$colors['text_color'],'blkGlobalTheme'=>[],'blkBtnInline'=>'','blkSlot'=>'top','blkSectionIds'=>$blkSectionIds,'blkEmpty'=>false])
@if($contacts->isNotEmpty())
<header><p>CONTACT DIRECTORY</p><h1>{{ $title }}</h1><p>{{ $s['intro'] ?? 'Find the right department and get in touch.' }}</p></header>
@endif
@if(!empty($s['emergency_note']))<p class="emergency">{{ $s['emergency_note'] }}</p>@endif
@if($contacts->isNotEmpty())
<label for="directory-search">Search people, departments or locations</label><input id="directory-search" type="search" placeholder="Who can we help you reach?" autocomplete="off">
@if(!empty($s['reference_label']))<label for="directory-reference">{{ $s['reference_label'] }} (optional)</label><input id="directory-reference" maxlength="100" placeholder="Included only when you open a message">@endif
<nav aria-label="Departments">@if($groups->has(0))<a href="#category-0">General</a>@endif @foreach($categories as $category)@if(in_array($category->id,$blkSectionIds))<a href="#category-{{ $category->id }}">{{ $categoryName($category) }}</a>@endif @endforeach</nav>
@endif
@include('common.partials.biolink-block-list', ['blkFontColor'=>$colors['text_color'],'blkGlobalTheme'=>[],'blkBtnInline'=>'','blkSlot'=>'above','blkSectionIds'=>$blkSectionIds,'blkEmpty'=>false])
@foreach(collect([['id'=>0,'name'=>'General']])->concat($categories->map(fn($c)=>['id'=>$c->id,'name'=>$categoryName($c)])) as $category)
@php
$members=$groups->get($category['id'],collect());
@endphp
@if($members->isNotEmpty())
<section id="category-{{ $category['id'] }}" data-category><h2>{{ $category['name'] }}</h2><div class="contact-list">
@foreach($members as $contact)
@php
$d=$contact->details ?? [];
$phone=preg_replace('/[^0-9+]/','',$d['phone'] ?? '');
$alternate=preg_replace('/[^0-9+]/','',$d['alternate_phone'] ?? '');
$wa=preg_replace('/[^0-9]/','',$d['whatsapp'] ?? '');
$available=$contact->availability($timezone);
$extension=ctype_digit((string)($d['extension'] ?? '')) ? ';ext='.$d['extension'] : '';
@endphp
<article class="contact" id="contact-{{ $contact->id }}" data-contact data-search="{{ mb_strtolower($contact->name.' '.$category['name'].' '.($d['role'] ?? '').' '.($d['location'] ?? '').' '.($d['description'] ?? '')) }}">
<div class="contact-heading">
@if(filter_var($d['photo_url'] ?? '',FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//i',$d['photo_url']))<img src="{{ $d['photo_url'] }}" alt="" loading="lazy" referrerpolicy="no-referrer">@endif
<div><h3>@if(in_array($d['icon'] ?? '', ['address-book','bell','building','headset','shield','briefcase','utensils','taxi'], true))<i class="fas fa-{{ $d['icon'] }}" aria-hidden="true"></i> @endif{{ $contact->name }}</h3>@if($contact->is_pinned)<small>Priority contact</small>@endif<small>{{ $d['role'] ?? '' }}</small></div></div>
@if(!empty($d['description']))<p>{{ $d['description'] }}</p>@endif
@if(!empty($d['location']))<p>{{ $d['location'] }}</p>@endif
@if($available !== null)<p class="availability">{{ $available ? 'Within working hours' : 'Outside working hours' }} · {{ $d['opens'] }}–{{ $d['closes'] }} ({{ $timezone }})@if(!$available && !empty($d['closed_note']))<br>{{ $d['closed_note'] }}@endif</p>@endif
<div class="contact-actions">
@if($phone)<a href="tel:{{ $phone.$extension }}">Call @if($extension)(ext. {{ $d['extension'] }})@endif</a><a href="sms:{{ $phone }}?body={{ rawurlencode($d['message'] ?? '') }}" data-message="{{ $d['message'] ?? '' }}" data-message-base="sms:{{ $phone }}?body=">SMS</a>@endif
@if($alternate)<a href="tel:{{ $alternate }}">Alternate / after-hours</a>@endif
@if($wa)<a href="https://wa.me/{{ $wa }}?text={{ rawurlencode($d['message'] ?? '') }}" data-message="{{ $d['message'] ?? '' }}" data-message-base="https://wa.me/{{ $wa }}?text=" target="_blank" rel="noopener">WhatsApp</a>@endif
@if(filter_var($d['email'] ?? '',FILTER_VALIDATE_EMAIL))<a href="mailto:{{ $d['email'] }}">Email</a>@endif
@foreach(['website'=>'Website','support_url'=>'Get support','appointment_url'=>'Appointment'] as $key=>$label)@if(preg_match('/^https?:\/\//i',$d[$key] ?? ''))<a href="{{ $d[$key] }}" target="_blank" rel="noopener">{{ $label }}</a>@endif @endforeach
@if(!empty($d['location']))<a href="https://www.google.com/maps/search/?api=1&query={{ rawurlencode($d['location']) }}" target="_blank" rel="noopener">Directions</a>@endif
<a href="#contact-{{ $contact->id }}" aria-label="Direct link to {{ $contact->name }}">Permalink</a>
</div></article>
@endforeach
</div></section>
@if($category['id'])@include('common.partials.biolink-block-list', ['blkFontColor'=>$colors['text_color'],'blkGlobalTheme'=>[],'blkBtnInline'=>'','blkSlot'=>\App\Modules\User\Support\MenuBlockSlot::forSection((int)$category['id']),'blkSectionIds'=>$blkSectionIds,'blkEmpty'=>false])@endif
@endif
@endforeach
@if($contacts->isEmpty())
<section class="directory-welcome" aria-labelledby="directory-title">
<div class="directory-illustration" aria-hidden="true">
<svg viewBox="0 0 240 190" fill="none"><circle cx="120" cy="95" r="85" fill="currentColor" opacity=".06"/><rect x="48" y="36" width="144" height="120" rx="18" fill="var(--directory-surface)" stroke="currentColor" stroke-opacity=".25"/><path d="M48 70h144" stroke="currentColor" stroke-opacity=".18"/><circle cx="68" cy="54" r="3" fill="currentColor"/><circle cx="80" cy="54" r="3" fill="currentColor" opacity=".4"/><circle cx="92" cy="54" r="3" fill="currentColor" opacity=".2"/><rect x="67" y="86" width="32" height="32" rx="10" fill="currentColor" opacity=".1"/><path d="M77 98h12m-12 7h8M112 92h60M112 103h42M67 133h105" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-opacity=".3"/><circle cx="181" cy="144" r="24" fill="var(--directory-surface)" stroke="currentColor" stroke-opacity=".25"/><path d="M174 144h14m-7-7v14" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
</div>
<p class="welcome-eyebrow">CONTACT DIRECTORY</p>
<h1 id="directory-title">{{ $title }}</h1>
<p class="welcome-intro">{{ filled($s['intro'] ?? '') ? $s['intro'] : 'The right people. One place to connect.' }}</p>
<div class="welcome-status"><span aria-hidden="true"></span>Contact details coming soon</div>
<p class="welcome-note">This directory is being prepared. Please check back soon for departments, contact details and ways to get in touch.</p>
@if(auth()->check() && (int)auth()->id() === (int)$link->user_id)
<a class="welcome-setup" href="{{ route('user.links.directory.editor', $link) }}" target="_top">Set up your contacts →</a>
@endif
</section>
@else<p id="directory-no-results" class="no-results" hidden role="status">No matching contacts. Try a different name or department.</p>@endif
@include('common.partials.biolink-block-list', ['blkFontColor'=>$colors['text_color'],'blkGlobalTheme'=>[],'blkBtnInline'=>'','blkSlot'=>'below','blkSectionIds'=>$blkSectionIds,'blkEmpty'=>false])
</main>
<script src="{{ asset('js/contact-directory.js') }}" defer></script>
@include('common.partials.share-button', ['sbLink'=>$link,'sbUrl'=>$link->getShortUrl(),'sbTitle'=>$title])
</body></html>
