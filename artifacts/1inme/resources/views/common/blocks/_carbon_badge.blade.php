@php
    $resolver = app(\App\Modules\Common\Services\Carbon\CarbonSettingsResolver::class);
    $__carbonShow = $resolver->badgeVisibleForLink($link);
@endphp
@if ($__carbonShow)
    {{-- Hidden by default. We only render the "Carbon Neutral" pill
         AFTER the badge JSON endpoint confirms the most recent
         snapshot has a settled (purchased/sandbox) offset. If the
         endpoint returns 404 (pending/capped/failed/no snapshot
         yet), the pill stays hidden so we never advertise carbon
         neutrality on traffic that wasn't actually offset. --}}
    <div data-carbon-badge data-link-id="{{ $link->id }}"
         data-fetch-url="{{ route('public.carbon.badge', ['link' => $link->id]) }}"
         class="hidden fixed bottom-3 right-3 z-40">
        <button type="button" class="cb-toggle group inline-flex items-center gap-2 px-3 py-2 rounded-full bg-emerald-600/95 text-white text-xs font-semibold shadow-lg hover:bg-emerald-700 transition">
            <i class="fa-solid fa-leaf"></i>
            <span>Carbon Neutral</span>
        </button>
        <div class="cb-popover hidden absolute bottom-12 right-0 w-72 rounded-xl bg-white text-gray-800 shadow-2xl border p-4 text-xs">
            <div class="font-bold text-emerald-700 mb-1">Carbon Neutral Link in Bio</div>
            <div class="cb-body text-gray-600">Loading methodology…</div>
            <div class="mt-3 pt-3 border-t flex items-center justify-between">
                <a class="cb-method text-emerald-700 underline" href="#" target="_blank" rel="noopener">How we estimate</a>
                <a class="cb-cert hidden text-emerald-700 underline" href="#" target="_blank" rel="noopener">Certificate</a>
            </div>
        </div>
    </div>
    <script>
    (function () {
        document.querySelectorAll('[data-carbon-badge]').forEach(function (root) {
            var btn = root.querySelector('.cb-toggle');
            var pop = root.querySelector('.cb-popover');
            var body = root.querySelector('.cb-body');
            var meth = root.querySelector('.cb-method');
            var cert = root.querySelector('.cb-cert');
            var loaded = false;
            var prefetchedJson = null;

            // Probe the badge endpoint up-front. Only reveal the
            // "Carbon Neutral" pill if the endpoint confirms a
            // settled offset (HTTP 200 + ok=true). Anything else
            // (404 = no offset/pending/capped/failed, network error,
            // bad JSON) keeps the pill hidden, so the public page
            // makes no carbon-neutral claim it can't back up.
            fetch(root.dataset.fetchUrl, { credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (j) {
                    if (j && j.ok) {
                        prefetchedJson = j;
                        root.classList.remove('hidden');
                        loaded = true;
                        render(j);
                    }
                })
                .catch(function () { /* keep hidden on any error */ });

            function render(j) {
                        if (!j || !j.ok) { body.textContent = 'Methodology unavailable.'; return; }
                        var grams = (j.grams_co2 != null) ? Number(j.grams_co2).toFixed(0) : '—';
                        var off   = (j.grams_offset != null) ? Number(j.grams_offset).toFixed(0) : '0';
                        // Build the popover via DOM nodes — values like
                        // project_name and period come from a third-party
                        // provider response (Cloverly/Patch) and would be
                        // an XSS vector if interpolated into innerHTML.
                        body.textContent = '';
                        function strong(t) { var s = document.createElement('strong'); s.textContent = t; return s; }
                        body.appendChild(document.createTextNode('For '));
                        body.appendChild(strong(j.period || 'this period'));
                        body.appendChild(document.createTextNode(', this page produced about '));
                        body.appendChild(strong(grams + ' g'));
                        body.appendChild(document.createTextNode(' CO₂ from visitor traffic — matched by '));
                        body.appendChild(strong(off + ' g'));
                        body.appendChild(document.createTextNode(' of offsets via ' + (j.project_name || 'a verified portfolio') + '.'));
                        if (j.sandbox) {
                            var pill = document.createElement('span');
                            pill.className = 'ml-1 px-1 py-0.5 bg-amber-100 text-amber-800 rounded text-[10px]';
                            pill.textContent = 'sandbox';
                            body.appendChild(document.createTextNode(' '));
                            body.appendChild(pill);
                        }
                        if (j.methodology_url) meth.href = j.methodology_url;
                        if (j.certificate)    { cert.href = j.certificate; cert.classList.remove('hidden'); }
            }

            btn.addEventListener('click', function () {
                pop.classList.toggle('hidden');
                if (prefetchedJson && !loaded) { loaded = true; render(prefetchedJson); }
            });
            document.addEventListener('click', function (e) {
                if (!root.contains(e.target)) pop.classList.add('hidden');
            });
        });
    })();
    </script>
@endif
