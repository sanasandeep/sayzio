@php
    // Generic preview/landing page used for URL, ICS, and VCF link types when
    // the owner has enabled `settings.show_preview_page`. Renders a branded
    // page that fires marketing pixels and collects engagement (session +
    // heartbeat) before the visitor is forwarded to the actual destination /
    // download via `?_continue=1`.
    $type        = $link->type;
    $title       = $link->title ?: \App\Modules\User\Models\Link::typeLabel($type);
    $continueUrl = request()->fullUrlWithQuery(['_continue' => 1]);

    // Type-specific copy + preview details.
    $icon = 'link';
    $actionLabel = 'Continue';
    $details = [];
    if ($type === 'url') {
        $icon = 'globe';
        $actionLabel = 'Continue to destination';
        $dest = $link->getDestinationUrl();
        if ($dest) {
            // Show host only — never fall back to the full URL, which could
            // expose query tokens / secrets the visitor wasn't meant to see.
            $host = parse_url($dest, PHP_URL_HOST);
            if ($host) {
                $details[] = ['label' => 'Destination', 'value' => $host];
            }
        }
    } elseif ($type === 'ics') {
        $icon = 'calendar';
        $actionLabel = 'Add to calendar';
        $ics = $link->icsData;
        if ($ics) {
            $details[] = ['label' => 'Event', 'value' => $ics->event_name];
            if ($ics->start_date) $details[] = ['label' => 'Starts', 'value' => \Carbon\Carbon::parse($ics->start_date)->format('M j, Y g:i A')];
            if ($ics->location)   $details[] = ['label' => 'Location', 'value' => $ics->location];
        }
    } elseif ($type === 'vcf') {
        $icon = 'user';
        $actionLabel = 'Save contact';
        $vcf = $link->vcfData;
        if ($vcf) {
            $name = trim(($vcf->first_name ?? '') . ' ' . ($vcf->last_name ?? ''));
            if ($name) $details[] = ['label' => 'Name', 'value' => $name];
            if ($vcf->organization) $details[] = ['label' => 'Organization', 'value' => $vcf->organization];
            if ($vcf->title) $details[] = ['label' => 'Title', 'value' => $vcf->title];
            if ($vcf->email) $details[] = ['label' => 'Email', 'value' => $vcf->email];
            if ($vcf->phone) $details[] = ['label' => 'Phone', 'value' => $vcf->phone];
        }
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @if($link->seo_title)
        <title>{{ $link->seo_title }}</title>
        <meta property="og:title" content="{{ $link->seo_title }}">
    @else
        <title>{{ $title }} - 1INME</title>
    @endif
    @if($link->seo_description)
        <meta name="description" content="{{ $link->seo_description }}">
    @endif
    @if($link->favicon)
        <link rel="icon" type="image/png" href="{{ $link->favicon }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Space Grotesk', system-ui, sans-serif; background: #0a0612; }
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(24px) saturate(1.2); border: 1px solid rgba(255,255,255,0.06); box-shadow: 0 4px 32px rgba(0,0,0,0.4); }
        .bg-mesh { position: fixed; inset: 0; pointer-events: none; z-index: 0; background: radial-gradient(ellipse 600px 400px at 15% 20%, rgba(124,58,237,0.07), transparent), radial-gradient(ellipse 500px 350px at 85% 75%, rgba(139,92,246,0.05), transparent); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 text-white">
    <div class="bg-mesh"></div>
    <div class="w-full max-w-lg relative z-10">
        <div class="glass rounded-2xl p-8 text-center">
            <div class="w-16 h-16 bg-violet-500/10 border border-violet-500/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                @switch($icon)
                    @case('globe')
                        <svg class="w-8 h-8 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.6 9h16.8M3.6 15h16.8M12 3a13.5 13.5 0 010 18M12 3a13.5 13.5 0 000 18"/></svg>
                        @break
                    @case('calendar')
                        <svg class="w-8 h-8 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        @break
                    @case('user')
                        <svg class="w-8 h-8 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        @break
                    @default
                        <svg class="w-8 h-8 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 015.656 5.656l-4 4a4 4 0 01-5.656-5.656m-1.656 1.656a4 4 0 01-5.656-5.656l4-4a4 4 0 015.656 5.656"/></svg>
                @endswitch
            </div>

            <h1 class="text-xl font-bold text-white mb-1">{{ $title }}</h1>
            @if($link->seo_description)
                <p class="text-sm text-white/50 mb-5">{{ $link->seo_description }}</p>
            @else
                <div class="mb-5"></div>
            @endif

            @if(!empty($details))
                <div class="text-left mb-6 divide-y divide-white/5 border border-white/5 rounded-xl">
                    @foreach($details as $row)
                        <div class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                            <span class="text-white/40">{{ $row['label'] }}</span>
                            <span class="text-white/80 truncate max-w-[60%]" title="{{ $row['value'] }}">{{ $row['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <a href="{{ $continueUrl }}" class="inline-flex items-center justify-center gap-2 w-full text-white px-6 py-3 rounded-xl font-semibold transition-all"
               style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); box-shadow: 0 4px 16px rgba(124,58,237,0.25);">
                {{ $actionLabel }}
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </a>

            <p class="text-xs text-white/20 mt-4">Shared via <span class="text-white/30">1IN</span><span class="text-violet-400/60">ME</span></p>
        </div>
    </div>

    @include('common.partials.pixel-scripts')
    @include('common.partials.engagement-tracking')
</body>
</html>
