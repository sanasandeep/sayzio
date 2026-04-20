@php($useModal = $useModal ?? false)
<nav class="sticky top-0 inset-x-0 z-40 bg-[#1e2330]/90 backdrop-blur-xl border-b border-white/5"
     x-data="{ mobileOpen:false {{ $useModal ? ', authOpen:false, authTab:\'login\'' : '' }} }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <a href="{{ route('home') }}" class="inline-flex items-center" aria-label="1INME home">
                @include('common.partials.brand-logo', ['height' => 'h-9'])
            </a>
            <div class="hidden md:flex items-center gap-8">
                <a href="{{ route('home') }}" class="text-sm text-gray-300 hover:text-violet-400">Home</a>
                <a href="{{ route('site.features') }}" class="text-sm text-gray-300 hover:text-violet-400">Features</a>
                <a href="{{ route('site.how-it-works') }}" class="text-sm text-gray-300 hover:text-violet-400">How it works</a>
                <a href="{{ route('site.discovery') }}" class="text-sm text-gray-300 hover:text-violet-400">Discover</a>
                <a href="{{ route('site.creators-feed') }}" class="text-sm text-gray-300 hover:text-violet-400">Feed</a>
                <a href="{{ route('site.about') }}" class="text-sm text-gray-300 hover:text-violet-400">About</a>
                <a href="{{ route('site.contact') }}" class="text-sm text-gray-300 hover:text-violet-400">Contact</a>
            </div>
            <div class="hidden md:flex items-center gap-3">
                @auth
                    <a href="{{ route('user.dashboard') }}" class="px-6 py-2.5 bg-[#7c3aed] text-white rounded-full text-sm font-bold hover:bg-[#6d28d9]">Dashboard</a>
                @else
                    @if($useModal)
                        <button type="button" @click="authTab='login'; authOpen=true" class="px-4 py-2 text-sm font-medium text-gray-300 hover:text-white">Login</button>
                        <button type="button" @click="authTab='register'; authOpen=true" class="px-6 py-2.5 bg-[#7c3aed] text-white rounded-full text-sm font-bold hover:bg-[#6d28d9]">Register</button>
                    @else
                        <a href="{{ route('login.page') }}" class="px-4 py-2 text-sm font-medium text-gray-300 hover:text-white">Login</a>
                        <a href="{{ route('register.page') }}" class="px-6 py-2.5 bg-[#7c3aed] text-white rounded-full text-sm font-bold hover:bg-[#6d28d9]">Register</a>
                    @endif
                @endauth
            </div>
            <button @click="mobileOpen=!mobileOpen" class="md:hidden p-2 text-gray-300" aria-label="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <div x-show="mobileOpen" x-cloak class="md:hidden pb-4 border-t border-white/10 mt-2 pt-3 space-y-1">
            <a href="{{ route('home') }}" class="block px-3 py-2 text-sm text-gray-300">Home</a>
            <a href="{{ route('site.features') }}" class="block px-3 py-2 text-sm text-gray-300">Features</a>
            <a href="{{ route('site.how-it-works') }}" class="block px-3 py-2 text-sm text-gray-300">How it works</a>
            <a href="{{ route('site.discovery') }}" class="block px-3 py-2 text-sm text-gray-300">Discover</a>
            <a href="{{ route('site.creators-feed') }}" class="block px-3 py-2 text-sm text-gray-300">Creators feed</a>
            <a href="{{ route('site.about') }}" class="block px-3 py-2 text-sm text-gray-300">About</a>
            <a href="{{ route('site.contact') }}" class="block px-3 py-2 text-sm text-gray-300">Contact</a>
            <div class="pt-2 border-t border-white/10 space-y-2">
                @auth
                    <a href="{{ route('user.dashboard') }}" class="block px-4 py-2.5 bg-[#7c3aed] text-white rounded-lg text-sm font-bold text-center">Dashboard</a>
                @else
                    @if($useModal)
                        <button type="button" @click="authTab='login'; authOpen=true; mobileOpen=false" class="w-full text-left px-4 py-2 text-sm text-gray-300">Login</button>
                        <button type="button" @click="authTab='register'; authOpen=true; mobileOpen=false" class="block w-full px-4 py-2.5 bg-[#7c3aed] text-white rounded-lg text-sm font-bold text-center">Register</button>
                    @else
                        <a href="{{ route('login.page') }}" class="block px-4 py-2 text-sm text-gray-300">Login</a>
                        <a href="{{ route('register.page') }}" class="block px-4 py-2.5 bg-[#7c3aed] text-white rounded-lg text-sm font-bold text-center">Register</a>
                    @endif
                @endauth
            </div>
        </div>
    </div>

    @if($useModal)
        @include('public.partials.auth-modal')
    @endif
</nav>
