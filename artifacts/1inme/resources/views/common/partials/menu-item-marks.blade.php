{{--
    The marks on one dish, drawn beside its name.

    Parameters:
      $mkMarks  the resolved array from MenuItemMarks::resolve()

    Two shapes in here, and which one a mark gets is decided in admin:

      * a mark with an icon draws it, in its own colour, once per grade --
        one flame mild, three flames hot;
      * a mark without one draws a small text chip. "No garlic" has no
        legible 14px glyph and does not need one. Inventing a picture for
        it would mean a diner guessing.

    The icons are decorative and hidden from screen readers; the wrapper
    carries the whole list in words, so a diner using one hears "Vegetarian,
    Spicy 2" rather than nothing at all.
--}}
@php $mkMarks = $mkMarks ?? []; @endphp
@if(! empty($mkMarks))
    <span class="marks" title="{{ \App\Modules\User\Support\MenuItemMarks::describe($mkMarks) }}">
        <span class="sr-only">{{ \App\Modules\User\Support\MenuItemMarks::describe($mkMarks) }}</span>
        @foreach($mkMarks as $mkMark)
            @if($mkMark['path'])
                <span class="mk" aria-hidden="true" @if($mkMark['color']) style="color:{{ $mkMark['color'] }}" @endif>
                    @for($mkI = 0; $mkI < $mkMark['grade']; $mkI++)
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="{{ $mkMark['path'] }}"/>
                            @if($mkMark['solid'])<path d="{{ $mkMark['solid'] }}" fill="currentColor" stroke="none"/>@endif
                        </svg>
                    @endfor
                </span>
            @else
                <span class="mk-chip" aria-hidden="true">{{ $mkMark['label'] }}</span>
            @endif
        @endforeach
    </span>
@endif
