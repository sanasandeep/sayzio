@php
    $name = $feat['name'] ?? $feat[0] ?? '';
    $description = $feat['description'] ?? $feat[1] ?? '';
    $link = trim((string) ($feat['link'] ?? ''));
@endphp
<div class="fx-feature-row">
    <i class="fas fa-check" aria-hidden="true"></i>
    <div>
        @if($link !== '')<a href="{{ $link }}">{{ $name }} <span aria-hidden="true">↗</span></a>@else<strong>{{ $name }}</strong>@endif
        <p>{{ $description }}</p>
    </div>
</div>
