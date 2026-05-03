@php
    /** @var \App\Modules\User\Models\BiolinkBlock $block */
    /** @var array $s */
    use App\Modules\User\Models\RoadmapItem;
    $cfg          = is_array($s) ? $s : [];
    $title        = trim($cfg['title']    ?? 'Roadmap');
    $subtitle     = trim($cfg['subtitle'] ?? 'Suggest ideas, vote on others.');
    $allowSubmit  = (bool) ($cfg['allow_submissions'] ?? true);
    $requireEmail = (bool) ($cfg['require_email']     ?? true);
    $requireLogin = (bool) ($cfg['require_login']     ?? false);
    $columns      = $cfg['show_columns'] ?? RoadmapItem::PUBLIC_STATUSES;
    $columns      = array_values(array_intersect(RoadmapItem::PUBLIC_STATUSES, $columns));
    if (empty($columns)) $columns = RoadmapItem::PUBLIC_STATUSES;

    // Pre-rendered initial item set so the block is meaningful even
    // before the JS bootstraps. The XHR refresh below replaces this
    // with a live view (with vote state) on mount.
    $initial = \App\Modules\User\Models\RoadmapItem::query()
        ->withoutGlobalScope('workspace')
        ->where('block_id', $block->id)
        ->where('is_blocked', false)
        ->whereIn('status', $columns)
        ->orderByDesc('votes_count')
        ->orderByDesc('id')
        ->limit(60)
        ->get()
        ->groupBy('status');

    $statusLabels = [
        'ideas'       => 'Ideas',
        'planned'     => 'Planned',
        'in_progress' => 'In Progress',
        'shipped'     => 'Shipped',
    ];
    $statusAccent = [
        'ideas'       => '#94a3b8',
        'planned'     => '#60a5fa',
        'in_progress' => '#a78bfa',
        'shipped'     => '#34d399',
    ];

    $listUrl   = route('community.roadmap.list',   ['link' => $link->id, 'block' => $block->id]);
    $submitUrl = route('community.roadmap.submit', ['link' => $link->id, 'block' => $block->id]);
@endphp

<div class="roadmap-block mb-5" data-roadmap-block="{{ $block->id }}"
     data-list-url="{{ $listUrl }}" data-submit-url="{{ $submitUrl }}"
     data-vote-base="{{ url('/community/' . $link->id . '/blocks/' . $block->id . '/roadmap/items') }}">
    <div class="text-center mb-4">
        <h2 class="text-xl md:text-2xl font-bold" style="color: {{ $fontColor ?? '#0f172a' }};">{{ $title }}</h2>
        @if($subtitle !== '')
            <p class="text-sm mt-1" style="color: {{ ($fontColor ?? '#0f172a') }}cc;">{{ $subtitle }}</p>
        @endif
    </div>

    <div class="rm-columns grid grid-cols-1 md:grid-cols-{{ min(4, count($columns)) }} gap-3">
        @foreach($columns as $col)
            @php $items = $initial[$col] ?? collect(); @endphp
            <div class="rm-col rounded-2xl border p-3"
                 data-column="{{ $col }}"
                 style="background: rgba(255,255,255,0.06); border-color: {{ $statusAccent[$col] ?? '#94a3b8' }}55;">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold uppercase tracking-wider" style="color: {{ $statusAccent[$col] ?? '#94a3b8' }};">
                        {{ $statusLabels[$col] ?? ucfirst($col) }}
                    </span>
                    <span class="rm-count text-[11px] px-2 py-0.5 rounded-full" style="background: rgba(0,0,0,.15); color: {{ $fontColor ?? '#0f172a' }};">{{ $items->count() }}</span>
                </div>
                <ul class="rm-list space-y-2 min-h-[40px]">
                    @forelse($items as $i)
                        <li class="rm-item rounded-xl p-2.5 flex items-start gap-2" data-item-id="{{ $i->id }}"
                            style="background: rgba(255,255,255,0.10);">
                            <button type="button" class="rm-vote shrink-0 flex flex-col items-center px-2 py-1 rounded-lg border text-xs font-bold"
                                    aria-label="Upvote"
                                    style="border-color: rgba(255,255,255,.18); color: {{ $fontColor ?? '#0f172a' }};">
                                <i class="fas fa-caret-up"></i>
                                <span class="rm-votes tabular-nums">{{ $i->votes_count }}</span>
                            </button>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold truncate" style="color: {{ $fontColor ?? '#0f172a' }};">{{ $i->title }}</p>
                                @if(!empty($i->description))
                                    <p class="text-xs mt-0.5 line-clamp-2" style="color: {{ ($fontColor ?? '#0f172a') }}b3;">{{ \Illuminate\Support\Str::limit($i->description, 160) }}</p>
                                @endif
                                @if($i->shipped_at)
                                    <p class="text-[11px] mt-1" style="color: {{ $statusAccent['shipped'] }};"><i class="fas fa-rocket mr-1"></i>Shipped {{ $i->shipped_at->diffForHumans() }}</p>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="rm-empty text-xs text-center opacity-60 py-3" style="color: {{ $fontColor ?? '#0f172a' }};">No ideas here yet.</li>
                    @endforelse
                </ul>
            </div>
        @endforeach
    </div>

    @if($allowSubmit)
        <div class="mt-4 flex justify-center">
            <button type="button" class="rm-open-submit text-sm font-semibold px-4 py-2 rounded-xl border"
                    style="border-color: rgba(255,255,255,.25); color: {{ $fontColor ?? '#0f172a' }}; background: rgba(255,255,255,.10);">
                <i class="fas fa-lightbulb mr-1.5"></i> Suggest an idea
            </button>
        </div>

        <form class="rm-submit-form mt-3 hidden rounded-2xl border p-3"
              style="background: rgba(255,255,255,0.10); border-color: rgba(255,255,255,.18);"
              data-require-email="{{ $requireEmail ? '1' : '0' }}"
              data-require-login="{{ $requireLogin ? '1' : '0' }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                <input type="text" name="title" maxlength="160" required placeholder="Your idea (short title)"
                       class="rm-input col-span-2 px-3 py-2 rounded-lg border text-sm bg-white/90 text-slate-900">
                <textarea name="description" rows="2" maxlength="2000" placeholder="(Optional) tell us more"
                          class="rm-input col-span-2 px-3 py-2 rounded-lg border text-sm bg-white/90 text-slate-900"></textarea>
                <input type="text" name="name" maxlength="80" placeholder="Your name (optional)"
                       class="rm-input px-3 py-2 rounded-lg border text-sm bg-white/90 text-slate-900">
                <input type="email" name="email" maxlength="190" {{ $requireEmail ? 'required' : '' }}
                       placeholder="Email{{ $requireEmail ? '' : ' (optional)' }}"
                       class="rm-input px-3 py-2 rounded-lg border text-sm bg-white/90 text-slate-900">
            </div>
            <div class="flex items-center justify-between mt-2 gap-2">
                <p class="rm-feedback text-xs" style="color: {{ $fontColor ?? '#0f172a' }}cc;"></p>
                <div class="flex gap-2">
                    <button type="button" class="rm-cancel text-xs px-3 py-1.5 rounded-lg" style="color: {{ $fontColor ?? '#0f172a' }};">Cancel</button>
                    <button type="submit" class="text-xs font-bold px-3 py-1.5 rounded-lg" style="background: {{ $statusAccent['planned'] ?? '#60a5fa' }}; color: #0b1120;">Submit idea</button>
                </div>
            </div>
        </form>
    @endif
</div>

@once
@push('scripts')
<script>
(function () {
    function getCsrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }
    function fmt(n) { return new Intl.NumberFormat().format(n || 0); }
    function escapeHtml(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"})[c]; }); }

    function init(root) {
        if (root.__rmInit) return; root.__rmInit = true;
        var listUrl = root.dataset.listUrl, submitUrl = root.dataset.submitUrl, voteBase = root.dataset.voteBase;

        function refresh() {
            fetch(listUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j || !j.ok) return;
                    var voted = new Set(j.voted || []);
                    Object.keys(j.columns || {}).forEach(function (col) {
                        var ul = root.querySelector('.rm-col[data-column="' + col + '"] .rm-list');
                        if (!ul) return;
                        var items = j.columns[col] || [];
                        ul.innerHTML = items.length ? items.map(function (i) {
                            var didVote = voted.has(i.id);
                            return '<li class="rm-item rounded-xl p-2.5 flex items-start gap-2" data-item-id="' + i.id + '" style="background: rgba(255,255,255,0.10);">' +
                                '<button type="button" class="rm-vote shrink-0 flex flex-col items-center px-2 py-1 rounded-lg border text-xs font-bold' + (didVote ? ' rm-voted' : '') + '" aria-label="Upvote" style="border-color: rgba(255,255,255,.18); color: inherit;' + (didVote ? 'background: rgba(125,211,252,.25);' : '') + '">' +
                                  '<i class="fas fa-caret-up"></i><span class="rm-votes tabular-nums">' + fmt(i.votes) + '</span>' +
                                '</button>' +
                                '<div class="min-w-0 flex-1">' +
                                  '<p class="text-sm font-semibold truncate">' + escapeHtml(i.title) + '</p>' +
                                  (i.description ? '<p class="text-xs mt-0.5 line-clamp-2" style="opacity:.75;">' + escapeHtml(i.description) + '</p>' : '') +
                                  (i.shipped_at ? '<p class="text-[11px] mt-1" style="color:#34d399;"><i class="fas fa-rocket mr-1"></i>Shipped</p>' : '') +
                                '</div></li>';
                        }).join('') : '<li class="rm-empty text-xs text-center opacity-60 py-3">No ideas here yet.</li>';
                        var c = root.querySelector('.rm-col[data-column="' + col + '"] .rm-count');
                        if (c) c.textContent = items.length;
                    });
                })
                .catch(function () {});
        }

        root.addEventListener('click', function (e) {
            var voteBtn = e.target.closest('.rm-vote');
            if (voteBtn) {
                var li = voteBtn.closest('.rm-item');
                if (!li) return;
                voteBtn.disabled = true;
                fetch(voteBase + '/' + li.dataset.itemId + '/vote', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrf(), 'Content-Type': 'application/json' },
                }).then(function (r) { return r.json(); })
                  .then(function (j) {
                      voteBtn.disabled = false;
                      if (j && j.ok) {
                          var v = li.querySelector('.rm-votes');
                          if (v) v.textContent = fmt(j.count);
                          voteBtn.classList.toggle('rm-voted', !!j.voted);
                          voteBtn.style.background = j.voted ? 'rgba(125,211,252,.25)' : '';
                      } else if (j && j.error) {
                          alert(j.error);
                      }
                  }).catch(function () { voteBtn.disabled = false; });
                return;
            }
            if (e.target.closest('.rm-open-submit')) {
                var f = root.querySelector('.rm-submit-form'); if (f) f.classList.remove('hidden');
                return;
            }
            if (e.target.closest('.rm-cancel')) {
                var f2 = root.querySelector('.rm-submit-form'); if (f2) f2.classList.add('hidden');
                return;
            }
        });

        var form = root.querySelector('.rm-submit-form');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var fd = new FormData(form);
                var feedback = form.querySelector('.rm-feedback');
                feedback.textContent = 'Submitting…';
                fetch(submitUrl, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
                    body: fd,
                }).then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
                  .then(function (res) {
                      if (res.body && res.body.ok) {
                          feedback.textContent = res.body.pending ? 'Thanks! Your idea is pending review.' : 'Thanks! Your idea is live.';
                          form.reset();
                          setTimeout(function () { form.classList.add('hidden'); refresh(); }, 1200);
                      } else {
                          feedback.textContent = (res.body && res.body.error) ? res.body.error : 'Could not submit. Please try again.';
                      }
                  }).catch(function () { feedback.textContent = 'Network error. Please try again.'; });
            });
        }

        refresh();
    }

    function boot() { document.querySelectorAll('[data-roadmap-block]').forEach(init); }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else { boot(); }
})();
</script>
<style>
  .rm-voted { background: rgba(125, 211, 252, .25); }
  .rm-vote:hover { background: rgba(255,255,255,.18); }
  .rm-item .line-clamp-2 { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
</style>
@endpush
@endonce
