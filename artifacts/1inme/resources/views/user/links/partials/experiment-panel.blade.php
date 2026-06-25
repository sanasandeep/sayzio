{{--
    A/B test panel for the biolink editor. Surfaces the current
    experiment status, a Start/Stop control, and a live results
    readout that polls links.experiment.results once per 8s.

    During a running experiment, the editor's normal block editing
    saves into Variant B (the live blocks). Variant A is the frozen
    snapshot taken when the test started.
--}}
@php
    $__activeExp = $link->activeBiolinkExperiment ?? null;
    $__lastExp = $link->biolinkExperiments()->orderByDesc('id')->first();
@endphp

<style>
    #ab-test-panel .ab-input {
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        color: var(--text-primary, inherit);
    }
    #ab-test-panel .ab-input:focus {
        background: var(--bg-glass-input-focus);
        border-color: var(--border-glass-light);
        outline: none;
    }
    #ab-test-panel .ab-ghost-btn {
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        color: var(--text-primary, inherit);
    }
    #ab-test-panel .ab-ghost-btn:hover {
        background: var(--bg-glass-input-focus);
        border-color: var(--border-glass-light);
    }
</style>
<div id="ab-test-panel"
     class="rounded-2xl border p-4 mb-4"
     style="background:linear-gradient(135deg, rgba(61,107,255,0.06), rgba(6,182,212,0.04)); border-color:var(--border-glass);">
    <div class="flex items-center justify-between mb-3 gap-2 flex-wrap">
        <div class="flex items-center gap-2">
            <i class="fas fa-flask text-indigo-400"></i>
            <h3 class="font-semibold text-sm">Layout A/B test</h3>
            @if($__activeExp)
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                      style="background:rgba(16,185,129,0.18); color:#10b981;">
                    Running
                </span>
            @elseif($__lastExp && $__lastExp->status === 'completed')
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                      style="background:rgba(61,107,255,0.18); color:#90acff;">
                    Last winner: {{ strtoupper($__lastExp->winner ?? '—') }}
                </span>
            @endif
        </div>
        @if($__activeExp)
            <form method="POST" action="{{ route('user.links.experiment.stop', $link) }}" class="flex items-center gap-2">
                @csrf
                <button type="submit" name="winner" value="a" class="ab-ghost-btn px-3 py-1.5 text-xs font-semibold rounded-lg">
                    Stop &amp; promote A
                </button>
                <button type="submit" name="winner" value="b" class="ab-ghost-btn px-3 py-1.5 text-xs font-semibold rounded-lg">
                    Stop &amp; promote B
                </button>
                <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded-lg text-red-400 hover:bg-red-500/10">
                    Stop without winner
                </button>
            </form>
        @endif
    </div>

    @if(session('status'))
        <div class="text-xs text-emerald-400 mb-2">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="text-xs text-red-400 mb-2">{{ session('error') }}</div>
    @endif

    @if(!$__activeExp)
        <p class="text-xs theme-text-muted mb-3">
            Snapshot the current layout as <strong>Variant A</strong>, then keep editing —
            your edits become <strong>Variant B</strong>. Visitors are split 50/50 (sticky per visitor).
        </p>
        <form method="POST" action="{{ route('user.links.experiment.start', $link) }}"
              class="flex items-end gap-2 flex-wrap">
            @csrf
            <label class="text-[11px] theme-text-muted flex flex-col gap-1">
                <span>Stop when</span>
                <select name="stop_condition" id="ab-stop-condition"
                        onchange="document.getElementById('ab-sample').style.display=this.value==='sample_size'?'flex':'none';
                                  document.getElementById('ab-end').style.display=this.value==='end_date'?'flex':'none';"
                        class="ab-input rounded px-2 py-1.5 text-xs">
                    <option value="manual">I stop it manually</option>
                    <option value="sample_size">Reach a visitor count</option>
                    <option value="end_date">Reach a date</option>
                </select>
            </label>
            <label id="ab-sample" class="text-[11px] theme-text-muted flex flex-col gap-1" style="display:none;">
                <span>Total visits across A+B (min 50)</span>
                <input type="number" name="stop_sample_size" min="50" max="1000000" value="400"
                       class="ab-input rounded px-2 py-1.5 text-xs w-32">
            </label>
            <label id="ab-end" class="text-[11px] theme-text-muted flex flex-col gap-1" style="display:none;">
                <span>End date</span>
                <input type="datetime-local" name="stop_end_date"
                       class="ab-input rounded px-2 py-1.5 text-xs">
            </label>
            <button type="submit"
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white">
                <i class="fas fa-play text-[10px] mr-1"></i> Start A/B test
            </button>
        </form>
    @else
        <div class="grid grid-cols-2 gap-3 text-sm">
            @foreach(['a','b'] as $__v)
                @php
                    $__visits = (int) $__activeExp->{"variant_{$__v}_visits"};
                    $__clicks = (int) $__activeExp->{"variant_{$__v}_clicks"};
                    $__convs  = (int) $__activeExp->{"variant_{$__v}_conversions"};
                    $__ctr    = $__activeExp->ctrFor($__v);
                @endphp
                <div class="rounded-xl border border-white/10 p-3" style="background:rgba(255,255,255,0.03);">
                    <div class="text-[10px] uppercase tracking-wider theme-text-dimmed mb-1">Variant {{ strtoupper($__v) }}</div>
                    <div class="flex items-baseline gap-3">
                        <div>
                            <div class="text-[10px] theme-text-faint">Visits</div>
                            <div class="font-bold" data-ab-stat="{{ $__v }}-visits">{{ $__visits }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] theme-text-faint">Clicks</div>
                            <div class="font-bold" data-ab-stat="{{ $__v }}-clicks">{{ $__clicks }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] theme-text-faint">CTR</div>
                            <div class="font-bold" data-ab-stat="{{ $__v }}-ctr">{{ number_format($__ctr * 100, 1) }}%</div>
                        </div>
                        <div>
                            <div class="text-[10px] theme-text-faint">Conv.</div>
                            <div class="font-bold" data-ab-stat="{{ $__v }}-conv">{{ $__convs }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <p class="mt-2 text-[11px] theme-text-dimmed">
            Variant A is frozen at the snapshot taken when you started this test.
            Edits made to blocks below now apply to <strong>Variant B</strong>.
            @if($__activeExp->stop_condition === 'sample_size' && $__activeExp->stop_sample_size)
                Auto-promotes when total visits reach <strong>{{ number_format($__activeExp->stop_sample_size) }}</strong>
                (currently {{ number_format($__activeExp->totalVisits()) }}).
            @elseif($__activeExp->stop_condition === 'end_date' && $__activeExp->stop_end_date)
                Auto-promotes after <strong>{{ $__activeExp->stop_end_date->format('M j, Y g:ia') }}</strong>.
            @endif
        </p>
    @endif
</div>

@if($__activeExp)
<script>
(function(){
    if (window.__abResultsPoll) { clearInterval(window.__abResultsPoll); window.__abResultsPoll = null; }
    var url = @json(route('user.links.experiment.results', $link));
    function refresh(){
        fetch(url, {headers:{'Accept':'application/json'}, credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (!d || !d.experiment) return;
                var e = d.experiment;
                ['a','b'].forEach(function(v){
                    var prefix = 'variant_' + v;
                    var stats = e[prefix === 'variant_a' ? 'variant_a' : 'variant_b'];
                    if (!stats) return;
                    var set = function(key, val){
                        var el = document.querySelector('[data-ab-stat="' + v + '-' + key + '"]');
                        if (el) el.textContent = val;
                    };
                    set('visits', stats.visits);
                    set('clicks', stats.clicks);
                    set('conv',   stats.conversions);
                    set('ctr',    (stats.ctr * 100).toFixed(1) + '%');
                });
                if (e.status !== 'running') {
                    // Auto-promoted while we were watching — refresh the
                    // page so the UI reflects the new state.
                    setTimeout(function(){ window.location.reload(); }, 800);
                }
            })
            .catch(function(){});
    }
    window.__abResultsPoll = setInterval(refresh, 8000);
})();
</script>
@endif
