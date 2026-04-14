<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { 50:'#f5f3ff',100:'#ede9fe',200:'#ddd6fe',300:'#c4b5fd',400:'#a78bfa',500:'#8b5cf6',600:'#7c3aed',700:'#6d28d9',800:'#5b21b6',900:'#4c1d95' },
                    }
                }
            }
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center gap-8">
                    <a href="{{ route('user.dashboard') }}" class="text-xl font-bold text-gray-900">1INME</a>
                    <div class="hidden md:flex items-center gap-1">
                        <a href="{{ route('user.dashboard') }}" class="px-3 py-2 text-sm rounded-lg {{ request()->routeIs('user.dashboard') ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
                            Dashboard
                        </a>
                        <a href="{{ route('user.links.index') }}" class="px-3 py-2 text-sm rounded-lg {{ request()->routeIs('user.links.*') ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
                            Links
                        </a>
                        <a href="{{ route('user.projects.index') }}" class="px-3 py-2 text-sm rounded-lg {{ request()->routeIs('user.projects.*') ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
                            Projects
                        </a>
                        <a href="{{ route('user.pixels.index') }}" class="px-3 py-2 text-sm rounded-lg {{ request()->routeIs('user.pixels.*') ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
                            Pixels
                        </a>
                        <a href="{{ route('user.qrcode') }}" class="px-3 py-2 text-sm rounded-lg {{ request()->routeIs('user.qrcode*') ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
                            QR Code
                        </a>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    @if(session('impersonate_user_id'))
                    <div class="flex items-center gap-2 bg-yellow-50 border border-yellow-200 text-yellow-800 px-3 py-1 rounded-lg text-sm">
                        <i class="fas fa-user-secret"></i>
                        <span>Admin view</span>
                        <form action="{{ route('user.logout') }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="ml-1 text-yellow-600 hover:text-yellow-800 font-medium">Exit</button>
                        </form>
                    </div>
                    @endif

                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-2 text-gray-600 hover:text-gray-800">
                            <div class="w-8 h-8 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-sm font-medium">
                                {{ substr(auth()->user()->name, 0, 1) }}
                            </div>
                            <span class="hidden md:inline text-sm">{{ auth()->user()->name }}</span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>

                        <div x-show="open" @click.away="open = false" x-cloak
                             class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                            <a href="{{ route('user.profile.edit') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fas fa-user mr-2"></i> Profile
                            </a>
                            <hr class="my-1 border-gray-100">
                            <form action="{{ route('user.logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">{{ session('error') }}</div>
        @endif
        @if(session('info'))
            <div class="mb-4 p-4 bg-purple-50 border border-purple-200 rounded-lg text-purple-800 text-sm">{{ session('info') }}</div>
        @endif

        @yield('content')
    </main>
</body>
</html>
