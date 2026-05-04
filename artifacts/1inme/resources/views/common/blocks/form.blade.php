    @php
        $formId = $s['form_id'] ?? null;
        $formModel = $formId ? \App\Modules\User\Models\Form::find($formId) : null;
    @endphp
    @if($formModel && $formModel->is_active)
        <div class="mb-4 rounded-xl overflow-hidden glass-block" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);">
            <iframe src="{{ $formModel->getPublicUrl() }}/iframe"
                    class="w-full block"
                    style="height: {{ $s['height'] ?? 600 }}px; border: 0; background: transparent;"
                    loading="lazy"
                    data-form-frame="{{ $formModel->id }}"
                    title="{{ $formModel->title }}"></iframe>
        </div>
        <script>
            (function () {
                if (window.__1inmeFormResizeBound) return;
                window.__1inmeFormResizeBound = true;
                window.addEventListener('message', function (e) {
                    if (!e.data || e.data.type !== '1inme-form-resize') return;
                    document.querySelectorAll('iframe[data-form-frame]').forEach(function (f) {
                        if (f.contentWindow === e.source) f.style.height = (e.data.height + 4) + 'px';
                    });
                });
            })();
        </script>
    @else
        <div class="mb-4 glass-block rounded-xl p-4 text-center text-xs text-white/40">
            <i class="fas fa-wpforms mb-1"></i>
            <p>{{ $formModel ? 'This form is currently disabled.' : 'Form not configured.' }}</p>
        </div>
    @endif
