@extends('admin.layouts.app')
@section('title', 'Send Newsletter')
@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-white">Send a newsletter</h2>
        <a href="{{ route('admin.newsletter.index') }}" class="text-xs text-white/60 hover:text-white">
            <i class="fas fa-arrow-left mr-1"></i> Back to subscribers
        </a>
    </div>

    @if(session('success'))
        <div class="px-3 py-2 bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="px-3 py-2 bg-red-500/10 border border-red-400/30 text-red-200 rounded-lg text-sm">
            {{ session('error') }}
        </div>
    @endif

    <div class="glass rounded-2xl p-6">
        <form id="newsletter-form" method="POST" action="{{ route('admin.newsletter.send') }}" class="space-y-4"
              onsubmit="return confirm('Send this issue to {{ number_format($activeCount) }} active subscriber(s)?');">
            @csrf

            <div class="text-sm text-white/60">
                This will be queued and delivered to
                <span class="text-white font-medium">{{ number_format($activeCount) }}</span>
                active subscriber{{ $activeCount === 1 ? '' : 's' }} (people who have not unsubscribed).
            </div>

            <div>
                <label class="block text-xs uppercase tracking-wider text-white/40 mb-1">Subject</label>
                <input type="text" name="subject" id="nl-subject" required maxlength="255"
                       value="{{ old('subject') }}"
                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                @error('subject')
                    <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs uppercase tracking-wider text-white/40 mb-1">
                        Body (HTML allowed)
                    </label>
                    <textarea name="body_html" id="nl-body" required rows="18"
                              class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono"
                              placeholder="<h1>Hello!</h1>&#10;<p>What's new this month…</p>">{{ old('body_html') }}</textarea>
                    <p class="mt-1 text-[11px] text-white/40">
                        Plain text is fine too — basic HTML tags (h1, p, a, ul, strong, em, br) will render in most email clients.
                    </p>
                    @error('body_html')
                        <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-wider text-white/40 mb-1">
                        Live preview
                    </label>
                    <div class="w-full bg-white rounded-lg overflow-hidden border border-white/10" style="height: 28rem;">
                        <iframe id="nl-preview" sandbox="allow-same-origin"
                                title="Newsletter preview"
                                class="w-full h-full bg-white"></iframe>
                    </div>
                    <p class="mt-1 text-[11px] text-white/40">
                        This is an approximation — final rendering varies between email clients.
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 pt-2">
                <a href="{{ route('admin.newsletter.index') }}"
                   class="px-3 py-2 bg-white/5 border border-white/10 hover:bg-white/10 rounded-lg text-xs text-white">
                    Cancel
                </a>
                <button type="button" id="nl-send-test"
                        formaction="{{ route('admin.newsletter.send-test') }}"
                        class="px-3 py-2 bg-sky-500/20 border border-sky-400/40 hover:bg-sky-500/30 rounded-lg text-xs text-sky-100">
                    <i class="fas fa-vial mr-1"></i> Send test to me
                </button>
                <button type="submit"
                        class="px-4 py-2 bg-emerald-500/20 border border-emerald-400/40 hover:bg-emerald-500/30 rounded-lg text-xs text-emerald-100"
                        @if($activeCount === 0) disabled @endif>
                    <i class="fas fa-paper-plane mr-1"></i> Queue &amp; send
                </button>
            </div>
        </form>
    </div>

    <div class="glass rounded-2xl p-6">
        @php
            $sort     = $sort     ?? 'recent';
            $dir      = $dir      ?? 'desc';
            $highOnly = $highOnly ?? false;
            $highRateThreshold = $highRateThreshold ?? 1.0;

            // Toggle direction when re-clicking the active sort column; otherwise default to desc.
            $rateNextDir = ($sort === 'unsub_rate' && $dir === 'desc') ? 'asc' : 'desc';
            $recentNextDir = 'desc';

            $rateSortUrl = request()->fullUrlWithQuery([
                'sort' => 'unsub_rate', 'dir' => $rateNextDir, 'page' => null,
            ]);
            $recentSortUrl = request()->fullUrlWithQuery([
                'sort' => 'recent', 'dir' => $recentNextDir, 'page' => null,
            ]);
            $toggleHighUrl = request()->fullUrlWithQuery([
                'high_only' => $highOnly ? null : 1, 'page' => null,
            ]);
            $clearUrl = request()->fullUrlWithQuery([
                'sort' => null, 'dir' => null, 'high_only' => null, 'page' => null,
            ]);

            $rateActive   = $sort === 'unsub_rate';
            $recentActive = $sort === 'recent';
            $rateArrow   = $rateActive ? ($dir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-sort';
            // "Recent" only ever sorts descending on the server, so always show the down arrow when active
            // regardless of any leftover dir param in the URL — keeps the indicator honest.
            $recentArrow = $recentActive ? 'fa-arrow-down' : 'fa-sort';
        @endphp

        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <h3 class="text-sm font-semibold text-white">Past issues</h3>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <a href="{{ $toggleHighUrl }}"
                   class="px-2 py-1 rounded-lg border {{ $highOnly ? 'bg-red-500/20 border-red-400/40 text-red-100' : 'bg-white/5 border-white/10 text-white/70 hover:bg-white/10' }}"
                   title="Show only issues with an unsubscribe rate at or above {{ number_format($highRateThreshold, 2) }}%">
                    <i class="fas {{ $highOnly ? 'fa-check-square' : 'fa-square' }} mr-1"></i>
                    High unsubscribe rate only (≥ {{ number_format($highRateThreshold, 2) }}%)
                </a>
                @if($highOnly || $sort !== 'recent')
                    <a href="{{ $clearUrl }}"
                       class="px-2 py-1 rounded-lg bg-white/5 border border-white/10 text-white/60 hover:bg-white/10">
                        <i class="fas fa-xmark mr-1"></i> Clear
                    </a>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-wider text-white/40 border-b border-white/10">
                        <th class="py-2 pr-3">Subject</th>
                        <th class="py-2 pr-3">
                            <a href="{{ $recentSortUrl }}"
                               class="inline-flex items-center gap-1 hover:text-white {{ $recentActive ? 'text-white' : '' }}">
                                Started
                                <i class="fas {{ $recentArrow }} text-[10px]"></i>
                            </a>
                        </th>
                        <th class="py-2 pr-3">Finished</th>
                        <th class="py-2 pr-3">Status</th>
                        <th class="py-2 pr-3">Delivered</th>
                        <th class="py-2 pr-3">
                            <a href="{{ $rateSortUrl }}"
                               class="inline-flex items-center gap-1 hover:text-white {{ $rateActive ? 'text-white' : '' }}"
                               title="Sort by unsubscribe rate">
                                Unsubscribed
                                <i class="fas {{ $rateArrow }} text-[10px]"></i>
                            </a>
                        </th>
                        <th class="py-2 pr-3">Sent by</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse($issues as $issue)
                        <tr class="text-white/80 align-top">
                            <td class="py-2 pr-3 text-white">{{ $issue->subject }}</td>
                            <td class="py-2 pr-3 text-xs text-white/60">
                                {{ optional($issue->sent_at ?? $issue->created_at)->format('Y-m-d H:i') }}
                            </td>
                            <td class="py-2 pr-3 text-xs text-white/60">
                                {{ $issue->finished_at ? $issue->finished_at->format('Y-m-d H:i') : '—' }}
                            </td>
                            <td class="py-2 pr-3 text-xs">
                                @php
                                    $statusClass = match($issue->status) {
                                        'sent'    => 'bg-emerald-500/15 text-emerald-200',
                                        'sending' => 'bg-amber-500/15 text-amber-200',
                                        'failed'  => 'bg-red-500/15 text-red-200',
                                        default   => 'bg-white/5 text-white/60',
                                    };
                                @endphp
                                <span class="px-2 py-0.5 rounded-full {{ $statusClass }}">{{ $issue->status }}</span>
                            </td>
                            <td class="py-2 pr-3 text-xs text-white/60">
                                {{ number_format($issue->sent_count) }} / {{ number_format($issue->recipients_count) }}
                                @if($issue->failed_count > 0)
                                    <span class="text-red-300 ml-1">({{ number_format($issue->failed_count) }} failed)</span>
                                @endif
                            </td>
                            <td class="py-2 pr-3 text-xs">
                                @php
                                    $unsubs = (int) ($issue->unsubscribed_count ?? 0);
                                    $delivered = (int) ($issue->sent_count ?? 0);
                                    $rate = $delivered > 0 ? ($unsubs / $delivered) * 100 : null;
                                    $isHigh = $rate !== null && $rate >= $highRateThreshold;
                                @endphp
                                @if($unsubs > 0)
                                    <span class="text-amber-200">{{ number_format($unsubs) }}</span>
                                    @if($rate !== null)
                                        @if($isHigh)
                                            <span class="ml-1 px-1.5 py-0.5 rounded-full bg-red-500/20 text-red-200 border border-red-400/40"
                                                  title="Unsubscribe rate is unusually high (≥ 1% of delivered)">
                                                {{ number_format($rate, 2) }}%
                                                <i class="fas fa-triangle-exclamation ml-0.5"></i>
                                            </span>
                                        @else
                                            <span class="ml-1 text-white/50">({{ number_format($rate, 2) }}%)</span>
                                        @endif
                                    @else
                                        <span class="ml-1 text-white/40" title="No delivered recipients yet">(—)</span>
                                    @endif
                                @else
                                    <span class="text-white/40">0</span>
                                    @if($rate !== null)
                                        <span class="ml-1 text-white/40">(0.00%)</span>
                                    @else
                                        <span class="ml-1 text-white/40" title="No delivered recipients yet">(—)</span>
                                    @endif
                                @endif
                            </td>
                            <td class="py-2 pr-3 text-xs text-white/60">{{ $issue->sender_email ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-white/40 text-sm">No issues sent yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $issues->links() }}</div>
    </div>
</div>

<script>
(function () {
    var form     = document.getElementById('newsletter-form');
    var bodyEl   = document.getElementById('nl-body');
    var iframe   = document.getElementById('nl-preview');
    var testBtn  = document.getElementById('nl-send-test');
    if (!form || !bodyEl || !iframe || !testBtn) return;

    var sendAction = form.getAttribute('action');
    var testAction = testBtn.getAttribute('formaction');

    function renderPreview() {
        var html = bodyEl.value || '';
        var doc = '<!DOCTYPE html><html><head><meta charset="utf-8">'
                + '<style>body{font-family:Arial,Helvetica,sans-serif;color:#111;background:#fff;padding:16px;line-height:1.5;}'
                + 'a{color:#2563eb;} h1,h2,h3{margin-top:0;}'
                + 'img{max-width:100%;height:auto;}</style></head><body>'
                + html + '</body></html>';
        iframe.setAttribute('srcdoc', doc);
    }

    var renderTimer = null;
    bodyEl.addEventListener('input', function () {
        clearTimeout(renderTimer);
        renderTimer = setTimeout(renderPreview, 120);
    });
    renderPreview();

    // Send Test: submit the same form to the test endpoint without
    // triggering the broadcast confirm() handler, and lock out the button
    // briefly on the client too so it can't be spammed.
    testBtn.addEventListener('click', function (e) {
        e.preventDefault();
        if (testBtn.disabled) return;
        if (!bodyEl.value.trim() || !document.getElementById('nl-subject').value.trim()) {
            alert('Add a subject and body before sending a test.');
            return;
        }
        testBtn.disabled = true;
        var originalLabel = testBtn.innerHTML;
        testBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Sending test…';
        // Re-enable after 8s in case the user navigates back via cache.
        setTimeout(function () {
            testBtn.disabled = false;
            testBtn.innerHTML = originalLabel;
        }, 8000);

        var savedAction = form.getAttribute('action');
        var savedOnSubmit = form.onsubmit;
        form.setAttribute('action', testAction);
        form.onsubmit = null;
        form.submit();
        // Restore (in case submission is intercepted)
        form.setAttribute('action', savedAction);
        form.onsubmit = savedOnSubmit;
    });
})();
</script>
@endsection
