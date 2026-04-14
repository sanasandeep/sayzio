<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { 50:'#f5f3ff',100:'#ede9fe',200:'#ddd6fe',300:'#c4b5fd',400:'#a78bfa',500:'#8b5cf6',600:'#7c3aed',700:'#6d28d9',800:'#5b21b6',900:'#4c1d95' },
                        dark: { 50:'#f8fafc',100:'#f1f5f9',200:'#e2e8f0',300:'#cbd5e1',400:'#94a3b8',500:'#64748b',600:'#475569',700:'#334155',800:'#1e293b',900:'#0f172a' }
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        .sidebar-link { @apply flex items-center gap-3 px-4 py-2.5 text-sm rounded-lg transition-colors; }
        .sidebar-link:hover { @apply bg-dark-700 text-white; }
        .sidebar-link.active { @apply bg-primary-600 text-white; }
    </style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-dark-50 min-h-screen">
    <div class="flex min-h-screen" x-data="{ sidebarOpen: true }">
        @include('admin.partials.sidebar')

        <div class="flex-1 flex flex-col min-w-0">
            @include('admin.partials.header')

            <main class="flex-1 p-6">
                @if(session('success'))
                    <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm">
                        {{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">
                        {{ session('error') }}
                    </div>
                @endif
                @if(session('info'))
                    <div class="mb-4 p-4 bg-purple-50 border border-purple-200 rounded-lg text-purple-800 text-sm">
                        {{ session('info') }}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
