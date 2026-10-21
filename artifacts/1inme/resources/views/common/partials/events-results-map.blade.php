{{--
    The map beside the results on /events.

    One marker per event ON THIS PAGE OF RESULTS that carries real
    coordinates. An event with no stored latitude/longitude gets no
    marker -- it is not placed at its city centre, and it is not
    guessed at from the address string. A pin on a map is a factual
    claim about where something is, and the panel says plainly how
    many of the listed events it could make that claim for.

    Hovering a card raises its marker and vice versa, which is the
    only reason the two halves are worth putting side by side.

    Expects: $events (the paginator for the current page).
--}}
@php
    $mapPoints = $events->getCollection()
        ->map(function ($e) {
            $ics = $e->icsData;
            if (!$ics || $ics->latitude === null || $ics->longitude === null) {
                return null;
            }

            return [
                'alias' => $e->alias,
                'lat'   => (float) $ics->latitude,
                'lng'   => (float) $ics->longitude,
                'label' => $ics->start_date?->format('j M') ?? 'TBC',
                'title' => $e->title ?: $e->alias,
                'venue' => $ics->location ?: null,
                'url'   => url('/' . $e->alias),
            ];
        })
        ->filter()
        ->values();

    $mapped  = $mapPoints->count();
    $listed  = $events->count();
@endphp

<div class="evdir-map-panel" x-data="eventsResultsMap(@js($mapPoints))" x-init="boot()">
    <div class="evdir-map-canvas" x-ref="map"></div>

    @if($listed > 0)
        <div class="evdir-map-note">
            @if($mapped === $listed)
                <i class="fas fa-location-dot"></i> All {{ $listed }} on this page are on the map
            @elseif($mapped === 0)
                <i class="fas fa-location-dot"></i> None of these {{ $listed }} events has a saved location yet
            @else
                <i class="fas fa-location-dot"></i> {{ $mapped }} of {{ $listed }} on the map
                <span class="evdir-map-note-sub">{{ $listed - $mapped }} {{ $listed - $mapped === 1 ? 'has' : 'have' }} no saved location</span>
            @endif
        </div>
    @endif
</div>

@once
@push('scripts')
<script>
function eventsResultsMap(points) {
    return {
        points: points || [],
        map: null,
        markers: {},

        boot() {
            if (!this.points.length) { return; }

            var self = this;
            var start = function () {
                self.$nextTick(function () {
                    // Leaflet measures the container at init and renders a
                    // grey box if it is still 0x0, so wait for layout.
                    requestAnimationFrame(function () { self.draw(); });
                });
            };

            // The page defines this loader in its own pushed script, which
            // lands after this one in the stack. Alpine boots later than
            // both, so it should be there -- but relying on that is relying
            // on push ordering, which is not worth a blank map. Fall back to
            // loading Leaflet directly if it is not.
            if (typeof ensureLeafletForSearch === 'function') {
                ensureLeafletForSearch(start);
            } else if (window.L) {
                start();
            } else {
                var css = document.createElement('link');
                css.rel = 'stylesheet';
                css.href = @js(asset('css/vendor/leaflet.min.css'));
                document.head.appendChild(css);

                var js = document.createElement('script');
                js.src = @js(asset('js/vendor/leaflet.min.js'));
                js.onload = start;
                document.head.appendChild(js);
            }
        },

        draw() {
            var self = this;
            var first = this.points[0];

            this.map = L.map(this.$refs.map, {
                center: [first.lat, first.lng],
                zoom: 11,
                scrollWheelZoom: false,
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap',
                maxZoom: 18,
            }).addTo(this.map);

            var bounds = [];

            this.points.forEach(function (p) {
                var icon = L.divIcon({
                    className: 'evdir-pin-wrap',
                    html: '<span class="evdir-pin" data-alias="' + p.alias + '">' + p.label + '</span>',
                    iconSize: null,
                });

                var m = L.marker([p.lat, p.lng], { icon: icon }).addTo(self.map);

                m.bindPopup(
                    '<strong>' + self.esc(p.title) + '</strong>' +
                    (p.venue ? '<br><span style="opacity:.7">' + self.esc(p.venue) + '</span>' : '') +
                    '<br><a href="' + p.url + '">View event</a>'
                );

                m.on('mouseover', function () { self.highlightCard(p.alias, true); });
                m.on('mouseout',  function () { self.highlightCard(p.alias, false); });

                self.markers[p.alias] = m;
                bounds.push([p.lat, p.lng]);
            });

            if (bounds.length > 1) {
                this.map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
            }

            // Card -> marker. Delegated, so pagination replacing the list
            // does not leave dead listeners behind.
            document.addEventListener('mouseover', function (e) {
                var card = e.target.closest ? e.target.closest('[data-event-alias]') : null;
                if (card) { self.setPinActive(card.dataset.eventAlias, true); }
            });
            document.addEventListener('mouseout', function (e) {
                var card = e.target.closest ? e.target.closest('[data-event-alias]') : null;
                if (card) { self.setPinActive(card.dataset.eventAlias, false); }
            });
        },

        setPinActive(alias, on) {
            var pin = document.querySelector('.evdir-pin[data-alias="' + CSS.escape(alias) + '"]');
            if (pin) { pin.classList.toggle('is-active', !!on); }
        },

        highlightCard(alias, on) {
            var card = document.querySelector('[data-event-alias="' + CSS.escape(alias) + '"]');
            if (card) { card.classList.toggle('is-map-hover', !!on); }
        },

        esc(s) {
            var d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        },
    };
}
</script>
@endpush
@endonce
