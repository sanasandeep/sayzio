<footer class="border-t border-white/10 bg-[#161b26] mt-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 grid grid-cols-2 md:grid-cols-4 gap-8 text-sm">
        <div class="col-span-2 md:col-span-1">
            @include('common.partials.brand-logo', ['height' => 'h-8'])
            <p class="mt-3 text-gray-500 text-xs leading-relaxed">Your link, your page, your audience — all in one place.</p>
        </div>
        <div>
            <div class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Product</div>
            <ul class="space-y-2 text-gray-400">
                <li><a href="{{ route('site.features') }}" class="hover:text-white">Features</a></li>
                <li><a href="{{ route('site.how-it-works') }}" class="hover:text-white">How it works</a></li>
                <li><a href="{{ route('site.workspace-team') }}" class="hover:text-white">Workspace &amp; Team</a></li>
                <li><a href="{{ route('site.buzz') }}" class="hover:text-white">Buzz</a></li>
                <li><a href="{{ route('site.discovery') }}" class="hover:text-white">Discover</a></li>
                <li><a href="{{ route('site.creators-feed') }}" class="hover:text-white">Creators feed</a></li>
                <li><a href="{{ route('site.faqs') }}" class="hover:text-white">FAQs</a></li>
            </ul>
        </div>
        <div>
            <div class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Company</div>
            <ul class="space-y-2 text-gray-400">
                <li><a href="{{ route('site.about') }}" class="hover:text-white">About</a></li>
                <li><a href="{{ route('site.contact') }}" class="hover:text-white">Contact</a></li>
                <li><a href="{{ route('login.page') }}" class="hover:text-white">Login</a></li>
                <li><a href="{{ route('register.page') }}" class="hover:text-white">Register</a></li>
            </ul>
        </div>
        <div>
            <div class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Legal</div>
            <ul class="space-y-2 text-gray-400">
                <li><a href="{{ route('site.terms') }}" class="hover:text-white">Terms &amp; Conditions</a></li>
                <li><a href="{{ route('site.refunds') }}" class="hover:text-white">Refunds</a></li>
                <li><a href="{{ route('site.privacy') }}" class="hover:text-white">Privacy</a></li>
                <li><a href="{{ route('site.gdpr') }}" class="hover:text-white">GDPR</a></li>
                <li><a href="{{ route('site.cookies') }}" class="hover:text-white">Cookies</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/5 py-5 text-center text-xs text-gray-500">
        © {{ date('Y') }} {{ config('app.name', '1INME') }}. All rights reserved.
    </div>
</footer>
