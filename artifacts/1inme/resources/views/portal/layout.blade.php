<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    @php
        $portal = app()->bound('current_portal') ? app('current_portal') : ($portal ?? null);
        $brandName = $portal?->brandingName() ?? config('app.name');
        $brandColor = $portal?->brandingColor() ?? '#3d6bff';
        $brandLogo = $portal?->brand_logo_url;
    @endphp
    <title>@yield('title', 'Client Portal') · {{ $brandName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --portal-brand: {{ $brandColor }}; }
        body { font-family: 'Inter', system-ui, sans-serif; background: #f8fafc; color: #0f172a; }
        .brand-bg { background: var(--portal-brand); }
        .brand-text { color: var(--portal-brand); }
        .brand-border { border-color: var(--portal-brand); }
        .brand-btn { background: var(--portal-brand); color: #fff; }
        .brand-btn:hover { filter: brightness(0.92); }
        .brand-pill { background: color-mix(in srgb, var(--portal-brand) 12%, transparent); color: var(--portal-brand); }
    </style>
</head>
<body class="min-h-screen">
    @if($portal)
        <header class="bg-white border-b border-slate-200">
            <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-3">
                @if($brandLogo)
                    <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="h-8 w-8 rounded object-cover">
                @else
                    <div class="h-8 w-8 rounded brand-bg text-white flex items-center justify-center text-sm font-bold">
                        {{ strtoupper(mb_substr($brandName, 0, 1)) }}
                    </div>
                @endif
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold truncate">{{ $brandName }}</div>
                    <div class="text-xs text-slate-500 truncate">{{ $portal->name }}</div>
                </div>
                <form action="{{ route('portal.logout') }}" method="POST" class="ml-auto">
                    @csrf
                    <button class="text-xs text-slate-500 hover:text-slate-800">
                        <i class="fas fa-sign-out-alt mr-1"></i>Sign out
                    </button>
                </form>
            </div>
            <nav class="max-w-6xl mx-auto px-4 flex flex-wrap gap-1 text-sm">
                @php
                    $sections = $portal->shares()->get()->groupBy('shareable_type');
                    $tabs = [
                        ['route' => 'portal.dashboard', 'label' => 'Overview', 'icon' => 'fa-home', 'always' => true],
                    ];
                    if ($sections->has('task_board'))         $tabs[] = ['route' => null, 'label' => 'Boards', 'icon' => 'fa-columns', 'first_type' => 'task_board'];
                    if ($sections->has('cloud_folder'))       $tabs[] = ['route' => 'portal.files',   'label' => 'Files',    'icon' => 'fa-folder-open'];
                    if ($sections->has('creator_post'))       $tabs[] = ['route' => 'portal.drafts',  'label' => 'Drafts',   'icon' => 'fa-file-signature'];
                    if ($sections->has('invoice'))            $tabs[] = ['route' => 'portal.invoices','label' => 'Invoices', 'icon' => 'fa-file-invoice-dollar'];
                    if ($sections->has('link_performance'))   $tabs[] = ['route' => null, 'label' => 'Reports', 'icon' => 'fa-chart-line', 'first_type' => 'link_performance'];
                @endphp
                @foreach($tabs as $tab)
                    @php
                        $href = $tab['route'] ? route($tab['route']) : '#';
                        if (!$tab['route'] && ($tab['first_type'] ?? null) === 'task_board') {
                            $first = $sections['task_board']->first();
                            $href = $first ? route('portal.board', $first->shareable_id) : '#';
                        } elseif (!$tab['route'] && ($tab['first_type'] ?? null) === 'link_performance') {
                            $first = $sections['link_performance']->first();
                            $href = $first ? route('portal.report', $first->shareable_id) : '#';
                        }
                    @endphp
                    <a href="{{ $href }}" class="px-3 py-2 -mb-px border-b-2 border-transparent hover:brand-text {{ url()->current() === $href ? 'brand-border brand-text font-semibold' : 'text-slate-600' }}">
                        <i class="fas {{ $tab['icon'] }} mr-1.5"></i>{{ $tab['label'] }}
                    </a>
                @endforeach
            </nav>
        </header>
    @endif

    <main class="max-w-6xl mx-auto px-4 py-6">
        @if(session('success'))
            <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">
                <i class="fas fa-check-circle mr-1"></i>{{ session('success') }}
            </div>
        @endif
        @if(session('error') || $errors->any())
            <div class="mb-4 px-4 py-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-sm">
                {{ session('error') ?: $errors->first() }}
            </div>
        @endif
        @yield('content')
    </main>

    <footer class="max-w-6xl mx-auto px-4 py-8 text-center text-xs text-slate-400">
        Powered by {{ config('app.name') }} · You are viewing a private read-only portal shared with you.
    </footer>
</body>
</html>
