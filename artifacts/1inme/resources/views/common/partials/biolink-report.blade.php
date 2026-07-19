@php
    // Visitor "Report" affordance for public biolinks. Owners see a
    // moderation banner instead (with appeal link if their biolink was
    // warned/hidden); everyone else sees the discreet Report link + modal.
    $__reportA = random_int(1, 9);
    $__reportB = random_int(1, 9);
    $__reportReasons = \App\Modules\Common\Models\BiolinkReport::REASONS;
    $__reportEndpoint = url('/' . $link->alias . '/report');
@endphp

@if($__ccIsOwner && in_array($link->moderation_state ?? null, ['warned', 'hidden'], true))
    <div style="position:fixed;left:50%;bottom:18px;transform:translateX(-50%);z-index:9998;
                max-width:520px;width:calc(100% - 32px);background:#1a0612;color:#fee2e2;
                border:1px solid rgba(239,68,68,0.45);border-radius:14px;padding:14px 16px;
                font-family:inherit;font-size:13px;line-height:1.5;
                box-shadow:0 14px 40px rgba(0,0,0,.45);">
        <div style="font-weight:600;color:#fff;margin-bottom:4px;">
            @if($link->moderation_state === 'hidden')
                Your Link in Bio is hidden from visitors
            @else
                Your Link in Bio received a moderation warning
            @endif
        </div>
        <div style="opacity:.85;">
            Reason: <strong>{{ \App\Modules\Common\Models\BiolinkReport::REASONS[$link->moderation_reason] ?? 'Policy review' }}</strong>.
            @if($link->moderation_note)
                <div style="margin-top:6px;opacity:.9;">{{ $link->moderation_note }}</div>
            @endif
        </div>
        @auth
        <details style="margin-top:10px;">
            <summary style="cursor:pointer;color:#fda4af;font-weight:500;">
                @if($link->moderation_appealed_at) Appeal submitted @else Submit an appeal @endif
            </summary>
            @if($link->moderation_appealed_at)
                <div style="margin-top:8px;opacity:.8;">
                    Submitted {{ \Carbon\Carbon::parse($link->moderation_appealed_at)->diffForHumans() }}.
                    Our team will review it.
                </div>
            @else
                <form method="POST" action="{{ url('/biolink/' . $link->id . '/appeal') }}" style="margin-top:10px;">
                    @csrf
                    <textarea name="message" required maxlength="2000" rows="3"
                        placeholder="Tell us why this should be restored…"
                        style="width:100%;background:rgba(0,0,0,.3);color:#fff;border:1px solid rgba(255,255,255,.1);
                               border-radius:8px;padding:8px;font-family:inherit;font-size:13px;"></textarea>
                    <button type="submit" style="margin-top:8px;background:#3d6bff;color:#fff;border:none;
                            padding:8px 14px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;">
                        Send appeal
                    </button>
                </form>
            @endif
        </details>
        @endauth
    </div>
@elseif(!$__ccIsOwner)
    <a href="#" id="bio-report-trigger"
       style="position:fixed;right:14px;bottom:14px;z-index:9997;
              background:rgba(0,0,0,.55);color:rgba(255,255,255,.7);
              text-decoration:none;font-size:11px;font-family:inherit;
              padding:6px 10px;border-radius:8px;border:1px solid rgba(255,255,255,.08);
              backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);">
        <i class="fas fa-flag" style="margin-right:4px;font-size:10px;"></i> Report
    </a>

    <div id="bio-report-modal" style="display:none;position:fixed;inset:0;z-index:9999;
         background:rgba(0,0,0,.7);align-items:center;justify-content:center;padding:20px;font-family:inherit;">
        <div style="background:#1a0533;color:#fff;max-width:420px;width:100%;border-radius:16px;
             padding:22px;border:1px solid rgba(255,255,255,.1);box-shadow:0 20px 60px rgba(0,0,0,.6);">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                <strong style="font-size:15px;">Report this Link in Bio</strong>
                <button type="button" id="bio-report-close" aria-label="Close"
                        style="background:transparent;border:none;color:rgba(255,255,255,.6);font-size:18px;cursor:pointer;">×</button>
            </div>
            <div id="bio-report-status" style="display:none;font-size:13px;margin-bottom:10px;"></div>
            <form id="bio-report-form" autocomplete="off">
                @csrf
                <input type="text" name="website" tabindex="-1" autocomplete="off"
                       style="position:absolute;left:-9999px;opacity:0;" aria-hidden="true">
                <label style="display:block;font-size:12px;opacity:.75;margin-bottom:6px;">Reason</label>
                <select name="reason" required
                        style="width:100%;background:rgba(0,0,0,.3);color:#fff;border:1px solid rgba(255,255,255,.1);
                               border-radius:8px;padding:9px;font-size:13px;margin-bottom:12px;">
                    @foreach($__reportReasons as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <label style="display:block;font-size:12px;opacity:.75;margin-bottom:6px;">Details (optional)</label>
                <textarea name="comment" rows="3" maxlength="1000"
                          style="width:100%;background:rgba(0,0,0,.3);color:#fff;border:1px solid rgba(255,255,255,.1);
                                 border-radius:8px;padding:9px;font-size:13px;font-family:inherit;margin-bottom:12px;"></textarea>
                <label style="display:block;font-size:12px;opacity:.75;margin-bottom:6px;">
                    Quick check: what is <span id="bio-report-cap-q">{{ $__reportA }} + {{ $__reportB }}</span>?
                </label>
                <input type="hidden" name="captcha_a" id="bio-report-cap-a" value="{{ $__reportA }}">
                <input type="hidden" name="captcha_b" id="bio-report-cap-b" value="{{ $__reportB }}">
                <input type="number" name="captcha" required inputmode="numeric"
                       style="width:100%;background:rgba(0,0,0,.3);color:#fff;border:1px solid rgba(255,255,255,.1);
                              border-radius:8px;padding:9px;font-size:13px;margin-bottom:14px;">
                <button type="submit"
                        style="width:100%;background:#3d6bff;color:#fff;border:none;padding:10px;
                               border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;">
                    Submit report
                </button>
            </form>
        </div>
    </div>

    <script>
    (function () {
        var trigger = document.getElementById('bio-report-trigger');
        var modal   = document.getElementById('bio-report-modal');
        var closer  = document.getElementById('bio-report-close');
        var form    = document.getElementById('bio-report-form');
        var status  = document.getElementById('bio-report-status');
        if (!trigger || !modal || !form) return;

        function showModal() { modal.style.display = 'flex'; }
        function hideModal() { modal.style.display = 'none'; }
        function setStatus(msg, ok) {
            status.style.display = 'block';
            status.style.color = ok ? '#86efac' : '#fca5a5';
            status.textContent = msg;
        }
        function newCaptcha() {
            var a = Math.floor(Math.random() * 9) + 1;
            var b = Math.floor(Math.random() * 9) + 1;
            document.getElementById('bio-report-cap-a').value = a;
            document.getElementById('bio-report-cap-b').value = b;
            document.getElementById('bio-report-cap-q').textContent = a + ' + ' + b;
        }

        trigger.addEventListener('click', function (e) { e.preventDefault(); status.style.display='none'; showModal(); });
        closer.addEventListener('click', hideModal);
        modal.addEventListener('click', function (e) { if (e.target === modal) hideModal(); });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(form);
            var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || fd.get('_token');
            fetch({!! json_encode($__reportEndpoint) !!}, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
                credentials: 'same-origin'
            }).then(function (r) {
                return r.json().then(function (j) { return { ok: r.ok, status: r.status, body: j }; });
            }).then(function (res) {
                if (res.ok && res.body.ok) {
                    setStatus(res.body.coalesced
                        ? 'Thanks, we already received reports about this page and counted yours.'
                        : 'Thanks, your report has been submitted.', true);
                    form.reset();
                    newCaptcha();
                    setTimeout(hideModal, 1800);
                } else if (res.status === 429) {
                    setStatus('You\u2019ve submitted several reports recently. Try again later.', false);
                } else if (res.body && res.body.error === 'captcha') {
                    setStatus('That answer wasn\u2019t right, please try the new question.', false);
                    newCaptcha();
                } else {
                    setStatus('Something went wrong. Please try again.', false);
                    newCaptcha();
                }
            }).catch(function () {
                setStatus('Network error. Please try again.', false);
            });
        });
    })();
    </script>
@endif
