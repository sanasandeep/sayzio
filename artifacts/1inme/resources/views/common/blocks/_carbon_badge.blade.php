@php
    $resolver = app(\App\Modules\Common\Services\Carbon\CarbonSettingsResolver::class);
    $__carbonShow = $resolver->badgeVisibleForLink($link);
@endphp
@if ($__carbonShow)
    <div data-carbon-badge data-link-id="{{ $link->id }}"
         data-fetch-url="{{ route('public.carbon.badge', ['link' => $link->id]) }}"
         class="fixed bottom-3 right-3 z-40">
        <button type="button" class="cb-toggle group inline-flex items-center gap-2 px-3 py-2 rounded-full bg-emerald-600/95 text-white text-xs font-semibold shadow-lg hover:bg-emerald-700 transition">
            <i class="fa-solid fa-leaf"></i>
            <span>Carbon Neutral</span>
        </button>
        <div class="cb-popover hidden absolute bottom-12 right-0 w-72 rounded-xl bg-white text-gray-800 shadow-2xl border p-4 text-xs">
            <div class="font-bold text-emerald-700 mb-1">Carbon Neutral biolink</div>
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

            function load() {
                if (loaded) return;
                loaded = true;
                fetch(root.dataset.fetchUrl, { credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (j) {
                        if (!j || !j.ok) { body.textContent = 'Methodology unavailable.'; return; }
                        var grams = (j.grams_co2 != null) ? Number(j.grams_co2).toFixed(0) : '—';
                        var off   = (j.grams_offset != null) ? Number(j.grams_offset).toFixed(0) : '0';
                        var prj   = j.project_name || 'Verified renewables portfolio';
                        var period = j.period || 'this period';
                        var sandbox = j.sandbox ? ' <span class="ml-1 px-1 py-0.5 bg-amber-100 text-amber-800 rounded text-[10px]">sandbox</span>' : '';
                        body.innerHTML = 'For <b>' + period + '</b>, this page produced about <b>' + grams + ' g</b> CO₂ from visitor traffic — fully matched by <b>' + off + ' g</b> of offsets via ' + prj + sandbox + '.';
                        if (j.methodology_url) meth.href = j.methodology_url;
                        if (j.certificate)    { cert.href = j.certificate; cert.classList.remove('hidden'); }
                    })
                    .catch(function () { body.textContent = 'Methodology unavailable.'; });
            }

            btn.addEventListener('click', function () {
                var hidden = pop.classList.toggle('hidden');
                if (!hidden) load();
            });
            document.addEventListener('click', function (e) {
                if (!root.contains(e.target)) pop.classList.add('hidden');
            });
        });
    })();
    </script>
@endif
