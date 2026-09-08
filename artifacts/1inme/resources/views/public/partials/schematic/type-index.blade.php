{{-- The link-type catalogue: a dense index, not a wall of identical cards. --}}
<div class="sch-index">
    @foreach($types as $i => $type)
        <a class="sch-row" href="{{ $type['url'] ?? route('site.schematic.features') }}">
            <span class="n">{{ str_pad((string) ($i + 2), 2, '0', STR_PAD_LEFT) }}</span>
            <span>
                <span class="name">{{ $type['name'] }}</span>
                <span class="desc">{{ $type['desc'] }}</span>
            </span>
            <span class="go" aria-hidden="true">&#8599;</span>
        </a>
    @endforeach
</div>
