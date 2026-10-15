@php
$trustBadges = \App\Modules\Common\Support\SitePagesContent::normalizeHeroBadges((array) \App\Modules\Admin\Models\AppSetting::get('marketing_hero_badges', [])) ?: \App\Modules\Common\Support\SitePagesContent::heroBadgesDefault();
@endphp
@include('home.partials.compact-home-styles')
<div class="ch-trust pb-band" aria-label="Sayzio at a glance">@foreach($trustBadges as $badge)<span><b>{{ $badge['value'] }}</b> {{ $badge['label'] }}</span>@endforeach</div>
