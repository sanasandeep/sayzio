{{--
    The Download / Copy pair that sits in a chart card's <div class="section-head">.

    @include('user.links.partials.chart-actions', [
        'target' => 'clicksChart',          // element id: a <canvas>, or the heat grid
        'title'  => 'Clicks Over Time',     // printed on the exported image
        'slug'   => 'clicks-over-time',     // goes in the filename
        'footer' => $chartFooter,           // link + period, printed under the artwork
    ])

    The behaviour lives in chart-export.blade.php, which binds every
    [data-chart-action] on the page once. The Copy button is removed there,
    not here, because whether the clipboard will take an image is a runtime
    question about the browser, not something Blade can answer.
--}}
<div class="chart-actions">
    <button type="button" class="table-action" data-chart-action="download"
            data-chart-target="{{ $target }}"
            data-chart-title="{{ $title }}"
            data-chart-slug="{{ $slug }}"
            data-chart-footer="{{ $footer ?? '' }}"
            title="Download this chart as a PNG">
        <i class="fas fa-download"></i> <span class="hidden sm:inline">PNG</span>
    </button>
    <button type="button" class="table-action" data-chart-action="copy"
            data-chart-target="{{ $target }}"
            data-chart-title="{{ $title }}"
            data-chart-slug="{{ $slug }}"
            data-chart-footer="{{ $footer ?? '' }}"
            title="Copy this chart to your clipboard">
        <i class="fas fa-copy"></i> <span class="hidden sm:inline">Copy</span>
    </button>
</div>
