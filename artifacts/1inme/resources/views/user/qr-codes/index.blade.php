@extends('user.layouts.app')
@section('title', 'QR Codes')

@section('content')
<div class="max-w-7xl mx-auto">
    @include('user.partials.page-hero', [
        'title'    => 'QR Codes',
        'subtitle' => '16 types · custom styles · branded frames',
        'icon'     => 'fa-qrcode',
        'chips'    => array_values(array_filter([
            ['icon' => 'fa-layer-group', 'text' => $qrCodes->total() . ' total'],
            ($savedQrCap ?? -1) >= 0 ? ['icon' => 'fa-gauge-high', 'text' => $savedQrCount . ' of ' . $savedQrCap . ' saved'] : null,
        ])),
        'actions'  => [
            ['url' => route('user.qr-codes.create'), 'label' => 'New QR Code', 'icon' => 'fa-plus', 'class' => 'btn-primary'],
        ],
    ])

    <form data-list-filters method="GET" class="card-premium p-4 mb-4 flex flex-wrap items-center gap-3">
        <div class="relative flex-1 max-w-md min-w-[180px]">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs" style="color: var(--text-faint);"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search QR codes…"
                   class="w-full pl-9 pr-3 py-2 text-sm rounded-lg outline-none"
                   style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
        </div>
        <select data-filter-multiple name="type" onchange="this.form.submit()"
                class="px-3 py-2 text-sm rounded-lg outline-none"
                style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
            <option value="">All types</option>
            @foreach($types as $key => $info)
                <option value="{{ $key }}" @selected(request('type') === $key)>{{ $info['label'] }}</option>
            @endforeach
        </select>
        @if($projects->count())
            <select data-filter-multiple name="project_id" onchange="this.form.submit()"
                    class="px-3 py-2 text-sm rounded-lg outline-none"
                    style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                <option value="">All projects</option>
                @foreach($projects as $p)
                    <option value="{{ $p->id }}" @selected(request('project_id') == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        @endif
        <button type="submit" class="px-4 py-2 text-sm rounded-lg font-semibold" style="background: var(--accent); color: #fff;">Filter</button>
    </form>

    @if($qrCodes->isEmpty())
        <div class="card-premium p-12 text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-2xl flex items-center justify-center" style="background: var(--c-primary-soft);">
                <i class="fas fa-qrcode text-2xl" style="color: var(--c-primary);"></i>
            </div>
            <h3 class="text-lg font-bold mb-2" style="color: var(--text-primary);">No QR codes yet</h3>
            <p class="text-sm mb-5" style="color: var(--text-muted);">Create your first branded QR code, pick a type, customize colors and frames, and download.</p>
            <a href="{{ route('user.qr-codes.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold" style="background: var(--accent); color: #fff;">
                <i class="fas fa-plus"></i> Create your first
            </a>
        </div>
    @else
        <div x-data="{ selected: [] }">
            <form id="qr-bulk" method="POST" action="{{ route('user.qr-codes.move-bulk') }}" class="card-premium p-4 mb-4 flex flex-wrap items-center gap-3">
                @csrf
                <button type="button" class="btn-secondary" @click="selected = selected.length === {{ $qrCodes->count() }} ? [] : @js($qrCodes->pluck('id')->map(fn($id)=>(string)$id)->values())">Select page / clear</button>
                <span x-text="selected.length + ' selected'"></span>
                <select name="project_id" aria-label="Destination folder" class="input-field"><option value="">No folder</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }}</option>@endforeach</select>
                <button name="action" value="folder" class="btn-secondary" :disabled="!selected.length">Move to folder</button>
                @if($workspaces->count())
                <select name="workspace_id" aria-label="Destination workspace" class="input-field">@foreach($workspaces as $workspace)<option value="{{ $workspace->id }}">{{ $workspace->name }}</option>@endforeach</select>
                <button name="action" value="workspace" class="btn-secondary" :disabled="!selected.length">Move to workspace</button>
                @endif
                <p class="text-xs w-full" style="color:var(--text-muted)">Workspace moves clear the folder. Linked pages and QR destinations stay unchanged.</p>
            </form>
            <div class="card-premium qr-library">
            @foreach($qrCodes as $qr)
                @php $info = $types[$qr->type] ?? ['label'=>$qr->type,'icon'=>'fa-qrcode']; @endphp
                <article class="qr-library-row">
                    <input type="checkbox" name="qr_ids[]" form="qr-bulk" value="{{ $qr->id }}" x-model="selected" aria-label="Select {{ $qr->name }}">
                    <a class="qr-library-art" href="{{ route('user.qr-codes.edit', $qr) }}" aria-label="Edit {{ $qr->name }}">
                        @if($qr->preview_url)<img src="{{ $qr->preview_url }}" alt="" loading="lazy">@else<div class="qr-thumb" data-payload="{{ $qr->encoded_payload }}" data-design='@json($qr->design)'></div>@endif
                    </a>
                    <div class="qr-library-info">
                        <a href="{{ route('user.qr-codes.edit', $qr) }}" class="font-semibold hover:underline">{{ $qr->name }}</a>
                        <div class="flex flex-wrap gap-2 mt-2 text-xs"><span class="qr-type-badge"><i class="fas {{ $info['icon'] }}"></i> {{ $info['label'] }}</span><span>{{ $qr->project?->name ?? 'No folder' }}</span><span>Created {{ $qr->created_at->format('d M Y') }}</span></div>
                        @if($qr->link)<a class="text-xs" href="{{ url('/'.$qr->link->alias) }}" target="_blank" rel="noopener">{{ url('/'.$qr->link->alias) }} <i class="fas fa-arrow-up-right-from-square"></i></a>@endif
                    </div>
                    <div class="qr-library-actions">
                        <a href="{{ route('user.qr-codes.edit', $qr) }}" class="btn-secondary"><i class="fas fa-pen"></i> Edit / export</a>
                        <form method="POST" action="{{ route('user.qr-codes.duplicate', $qr) }}">@csrf<button class="btn-secondary" aria-label="Duplicate {{ $qr->name }}"><i class="fas fa-copy"></i></button></form>
                        <form method="POST" action="{{ route('user.qr-codes.destroy', $qr) }}" onsubmit="return window.themedConfirmSubmit(this, {title:'Delete this QR code?', confirmText:'Delete', iconClass:'fa-trash'})">@csrf @method('DELETE')<button class="btn-secondary" aria-label="Delete {{ $qr->name }}" style="color:var(--c-danger)"><i class="fas fa-trash"></i></button></form>
                    </div>
                </article>
            @endforeach
            </div>
        </div>
        <div class="mt-6">{{ $qrCodes->links() }}</div>
    @endif
</div>

<style>
.qr-library-row{display:flex;align-items:center;gap:16px;padding:18px;border-bottom:1px solid var(--border-subtle);color:var(--text-primary)}
.qr-library-row:last-child{border-bottom:0}
.qr-library-art{width:80px;height:80px;flex:none;display:grid;place-items:center;background:#fff;border-radius:12px;padding:6px;overflow:hidden}
.qr-library-art svg,.qr-library-art img{max-width:100%;max-height:100%;width:100%;height:auto}
.qr-library-art .qr-thumb{width:100%;height:100%;display:flex;align-items:center;justify-content:center}
.qr-library-art .qr-thumb svg{width:100%;height:100%}
.qr-library-art img{height:100%;object-fit:contain}
.qr-library-actions .btn-secondary,#qr-bulk .btn-secondary{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:9px 12px;border:1px solid var(--border-glass);border-radius:10px;background:var(--bg-glass-hover);color:var(--text-primary);font-size:12px;font-weight:600;min-height:38px}
.qr-library-actions .btn-secondary:hover,#qr-bulk .btn-secondary:hover{border-color:var(--accent);background:var(--c-primary-soft)}
#qr-bulk select{padding:9px 12px;border:1px solid var(--border-glass);border-radius:10px;background:var(--bg-glass-input);color:var(--text-primary);font-size:12px}
#qr-bulk button[name="action"]{color:var(--accent);background:var(--c-primary-soft)}
.qr-library-info{flex:1;min-width:0;overflow-wrap:anywhere}
.qr-library-info .text-xs{color:var(--text-muted)}
.qr-library-actions{display:flex;flex-wrap:wrap;gap:8px}
.qr-type-badge{padding:3px 8px;border-radius:8px;background:var(--c-primary-soft);color:var(--accent)}
#qr-bulk select{max-width:220px;min-width:0}
#qr-bulk button:disabled{opacity:.45}
@media(max-width:767px){.qr-library-row{flex-wrap:wrap;padding:14px;gap:10px}
.qr-library-info{flex-basis:calc(100% - 120px)}
.qr-library-actions{width:100%;justify-content:flex-end}
#qr-bulk select{max-width:100%}
}

</style>
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
<script src="{{ asset('js/qr-studio/engine.js') }}?v={{ filemtime(public_path('js/qr-studio/engine.js')) }}"></script>
<script>
document.querySelectorAll('.qr-thumb').forEach(async el => {
    try {
        const d=JSON.parse(el.dataset.design||'{}');
        const opts={data:el.dataset.payload||'preview',modulePx:10,errorCorrection:d.error_correction,margin:d.margin,dotShape:d.dot_style,outerEyeShape:d.corner_square_style,innerEyeShape:d.corner_dot_style,fgColor:d.fg_color,bgColor:d.bg_color,transparentBg:d.transparent_bg,cornerSquareColor:d.corner_square_color,cornerDotColor:d.corner_dot_color,gradient:d.gradient,eyeOuterGradient:d.eye_outer_gradient,eyeInnerGradient:d.eye_inner_gradient,bgGradient:d.bg_gradient,eyesPerCorner:d.eyes_per_corner,corners:(d.eye_corners||[]).map(c=>({outerShape:c.outer_style,innerShape:c.inner_style,outerColor:c.outer_color,innerColor:c.inner_color})),frame:d.frame,qrRotation:d.qr_rotation,dropShadow:d.drop_shadow,logos:{background:d.logo_background,center:d.logo_center,foreground:d.logo_foreground},hideDotsBehindLogo:d.hide_dots_behind_logo};
        await QrStudio.preloadLogos(opts);
        el.innerHTML=QrStudio.render(opts).svg;
    } catch(e) { el.textContent='QR'; }
});
</script>
@endsection
