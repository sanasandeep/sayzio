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

    <form method="GET" class="card-premium p-4 mb-4 flex flex-wrap items-center gap-3">
        <div class="relative flex-1 max-w-md min-w-[180px]">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs" style="color: var(--text-faint);"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search QR codes…"
                   class="w-full pl-9 pr-3 py-2 text-sm rounded-lg outline-none"
                   style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
        </div>
        <select name="type" onchange="this.form.submit()"
                class="px-3 py-2 text-sm rounded-lg outline-none"
                style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
            <option value="">All types</option>
            @foreach($types as $key => $info)
                <option value="{{ $key }}" @selected(request('type') === $key)>{{ $info['label'] }}</option>
            @endforeach
        </select>
        @if($projects->count())
            <select name="project_id" onchange="this.form.submit()"
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
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @foreach($qrCodes as $qr)
                @php $info = $types[$qr->type] ?? ['label'=>$qr->type,'icon'=>'fa-qrcode']; @endphp
                <div class="card-premium p-4 flex flex-col">
                    <div class="rounded-lg p-4 mb-3 flex items-center justify-center min-h-[160px]" style="background: {{ $qr->design['bg_color'] ?? '#fff' }}; border: 1px solid var(--border-glass);">
                        @if($qr->preview_url)
                            <img src="{{ $qr->preview_url }}" alt="" class="max-w-full max-h-32 object-contain">
                        @else
                            <div class="qr-thumb" data-payload="{{ $qr->encoded_payload }}"
                                 data-design='@json($qr->design)'></div>
                        @endif
                    </div>
                    <div class="flex items-start gap-2 mb-2">
                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold" style="background: var(--c-primary-soft); color: var(--c-primary);"><i class="fas {{ $info['icon'] }} mr-1"></i>{{ $info['label'] }}</span>
                        @if($qr->link)
                            <span class="px-1.5 py-0.5 rounded text-[10px]" style="background: var(--bg-glass-hover); color: var(--text-muted);"><i class="fas fa-link mr-1"></i>/{{ $qr->link->alias }}</span>
                        @endif
                    </div>
                    <a href="{{ route('user.qr-codes.edit', $qr) }}" class="font-semibold text-sm truncate hover:underline mb-3" style="color: var(--text-primary);">{{ $qr->name }}</a>
                    <div class="flex items-center gap-1.5 mt-auto pt-3 border-t" style="border-color: var(--border-subtle);">
                        <a href="{{ route('user.qr-codes.edit', $qr) }}" class="flex-1 text-center px-3 py-1.5 text-xs rounded-lg font-semibold" style="background: var(--bg-glass-hover); color: var(--text-primary);"><i class="fas fa-pen mr-1"></i> Edit</a>
                        <form method="POST" action="{{ route('user.qr-codes.duplicate', $qr) }}">@csrf
                            <button type="submit" class="px-2.5 py-1.5 text-xs rounded-lg" style="background: var(--bg-glass-hover); color: var(--text-secondary);" title="Duplicate"><i class="fas fa-copy"></i></button>
                        </form>
                        <form method="POST" action="{{ route('user.qr-codes.destroy', $qr) }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this QR code?', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">@csrf @method('DELETE')
                            <button type="submit" class="px-2.5 py-1.5 text-xs rounded-lg" style="background: var(--bg-glass-hover); color: var(--c-danger);" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $qrCodes->links() }}</div>
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/qr-code-styling@1.6.0-rc.1/lib/qr-code-styling.js"></script>
<script>
document.querySelectorAll('.qr-thumb').forEach(el => {
    try {
        const design = JSON.parse(el.dataset.design || '{}');
        const data = el.dataset.payload || 'preview';
        const qr = new QRCodeStyling({
            width: 140, height: 140, type: 'svg',
            data: data || 'preview',
            margin: 2,
            qrOptions: { errorCorrectionLevel: design.error_correction || 'M' },
            backgroundOptions: { color: design.transparent_bg ? 'transparent' : (design.bg_color || '#ffffff') },
            dotsOptions: { type: design.dot_style || 'rounded', color: design.fg_color || '#000' },
            cornersSquareOptions: { type: design.corner_square_style || 'extra-rounded', color: design.corner_square_color || design.fg_color || '#000' },
            cornersDotOptions: { type: design.corner_dot_style || 'dot', color: design.corner_dot_color || design.fg_color || '#000' },
        });
        qr.append(el);
    } catch (e) { console.warn('thumb render', e); }
});
</script>
@endsection
