<p class="listing-meta">{{ ($d['purpose'] ?? '') === 'rent' ? 'For rent' : 'For sale' }} · {{ ucfirst($d['property_type'] ?? 'Property') }}</p>
<div class="property-facts">
@if(isset($d['bedrooms']))<span><i class="fas fa-bed" aria-hidden="true"></i> {{ $d['bedrooms'] }} beds</span>@endif
@if(isset($d['bathrooms']))<span><i class="fas fa-bath" aria-hidden="true"></i> {{ $d['bathrooms'] }} baths</span>@endif
@if(isset($d['area']))<span>{{ number_format((float)$d['area']) }} {{ ['sq_ft'=>'sq ft','sq_m'=>'sq m','acres'=>'acres'][$d['area_unit'] ?? ''] ?? '' }}</span>@endif
</div>
