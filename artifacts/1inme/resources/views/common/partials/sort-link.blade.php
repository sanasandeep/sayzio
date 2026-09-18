{{--
    A sortable column header for a table the DATABASE paginates.

    Usage, inside the existing <th> so each page keeps its own header styling:

        <th class="px-6 py-3 ...">
            @include('common.partials.sort-link', ['key' => 'name', 'label' => 'User'])
        </th>

    Expects $sort = ['key' => ..., 'dir' => ...] in the view, which is what
    App\Support\TableSort::apply() returns. If the controller has not been
    wired up yet the include degrades to a plain label rather than rendering a
    link that would sort by a key nothing honours.

    It is an anchor, not a click handler: sorting is a property of the URL, so
    it survives a reload, can be linked to, and works before any script runs.
    `page => null` matters -- re-sorting while on page 7 and staying on page 7
    of a different ordering is disorienting and looks like data loss.
--}}
@php
    $sortState = $sort ?? null;
    $isActive  = is_array($sortState) && ($sortState['key'] ?? null) === $key;
    $activeDir = $isActive ? ($sortState['dir'] ?? 'desc') : null;
    // Clicking the active column flips it; clicking a new one starts ascending.
    $nextDir   = $activeDir === 'asc' ? 'desc' : 'asc';
@endphp
@if(is_array($sortState))
    <a href="{{ request()->fullUrlWithQuery(['sort' => $key, 'dir' => $nextDir, 'page' => null]) }}"
       class="et-sort-link{{ $isActive ? ' is-active' : '' }}"
       aria-label="Sort by {{ $label }}{{ $isActive ? ', currently ' . ($activeDir === 'asc' ? 'ascending' : 'descending') : '' }}">
        {{ $label }}
        <i class="fas {{ $isActive ? ($activeDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort' }} et-sort-ind"
           aria-hidden="true"></i>
    </a>
@else
    {{ $label }}
@endif
