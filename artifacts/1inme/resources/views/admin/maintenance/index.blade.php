@extends('admin.layouts.app')
@section('title', 'Maintenance Mode')
@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    @if(session('success'))
        <div class="px-3 py-2 bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="glass rounded-2xl p-6">
        <h1 class="text-xl font-semibold text-white">Maintenance Mode</h1>
        <p class="text-sm text-white/60 mt-1">
            Take parts of the site offline to visitors while you push updates. Logged-in admins are never blocked, and the admin panel itself stays reachable so you can switch toggles back off.
        </p>
    </div>

    <form method="POST" action="{{ route('admin.maintenance.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="glass rounded-2xl p-6 space-y-4">
            <div>
                <h2 class="text-base font-semibold text-white">Admin-only lockdown</h2>
                <p class="text-xs text-white/50">One switch that takes the entire app offline for everyone except staff who hold an admin role.</p>
            </div>

            <label class="flex items-start gap-4 p-4 rounded-xl border border-amber-400/30 bg-amber-500/[0.06] hover:bg-amber-500/[0.09] transition cursor-pointer">
                <input type="hidden" name="admin_only" value="0">
                <input type="checkbox"
                       name="admin_only"
                       value="1"
                       @checked(old('admin_only', $adminOnly))
                       class="mt-1 w-5 h-5 accent-amber-500 cursor-pointer">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-white">Lock down the whole app (admins only)</span>
                        @if($adminOnly)
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-amber-500/15 border border-amber-400/30 text-amber-300">Live now</span>
                        @endif
                    </div>
                    <p class="text-xs text-white/50 mt-0.5">
                        While on, this overrides every area switch below: guests, regular users and API clients all get the maintenance page / a 503. Anyone holding an admin role (admin panel staff or a web user with a platform role) keeps full access across all surfaces.
                    </p>
                </div>
            </label>
        </div>

        <div class="glass rounded-2xl p-6 space-y-4">
            <div>
                <h2 class="text-base font-semibold text-white">Areas</h2>
                <p class="text-xs text-white/50">Each switch controls one surface independently. Ignored while the admin-only lockdown above is on.</p>
            </div>

            <div class="space-y-3">
                @foreach($areas as $key => $area)
                    <label class="flex items-start gap-4 p-4 rounded-xl border border-white/10 bg-white/[0.03] hover:bg-white/[0.05] transition cursor-pointer">
                        <input type="hidden" name="areas[{{ $key }}]" value="0">
                        <input type="checkbox"
                               name="areas[{{ $key }}]"
                               value="1"
                               @checked($area['enabled'])
                               class="mt-1 w-5 h-5 accent-blue-500 cursor-pointer">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-white">{{ $area['label'] }}</span>
                                @if($area['enabled'])
                                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-amber-500/15 border border-amber-400/30 text-amber-300">Live now</span>
                                @endif
                            </div>
                            <p class="text-xs text-white/50 mt-0.5">
                                @switch($key)
                                    @case('marketing')
                                        Public landing, pricing, blog, contact and policy pages.
                                        @break
                                    @case('user_app')
                                        The whole user surface at <code class="text-white/70">/user/*</code>, including login and registration. Admins still get in via <code class="text-white/70">/admin</code>.
                                        @break
                                    @case('api')
                                        Returns a 503 JSON envelope to the mobile app and any other API clients.
                                        @break
                                    @case('biolinks')
                                        Public Link in Bio profile pages and short-link redirects.
                                        @break
                                @endswitch
                            </p>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="glass rounded-2xl p-6 space-y-4">
            <div>
                <h2 class="text-base font-semibold text-white">Visitor page style</h2>
                <p class="text-xs text-white/50">Choose what the maintenance page looks like to visitors. The API 503 envelope is unaffected.</p>
            </div>

            @php $currentStyle = old('style', $style ?? 'standard'); @endphp
            <div class="grid sm:grid-cols-2 gap-3">
                <label class="flex items-start gap-3 p-4 rounded-xl border border-white/10 bg-white/[0.03] hover:bg-white/[0.05] transition cursor-pointer">
                    <input type="radio" name="style" value="standard"
                           @checked($currentStyle !== 'upgrade')
                           class="mt-1 w-4 h-4 accent-blue-500 cursor-pointer">
                    <div class="flex-1 min-w-0">
                        <span class="text-sm font-semibold text-white">Standard</span>
                        <p class="text-xs text-white/50 mt-0.5">The default &ldquo;We&rsquo;ll be right back&rdquo; maintenance page.</p>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-4 rounded-xl border border-blue-400/30 bg-blue-500/[0.06] hover:bg-blue-500/[0.09] transition cursor-pointer">
                    <input type="radio" name="style" value="upgrade"
                           @checked($currentStyle === 'upgrade')
                           class="mt-1 w-4 h-4 accent-blue-500 cursor-pointer">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-white">Upgrade &mdash; Sayzio 2.0</span>
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-blue-500/15 border border-blue-400/30 text-blue-300">On-brand</span>
                        </div>
                        <p class="text-xs text-white/50 mt-0.5">A polished &ldquo;Sayzio 2.0 is coming&rdquo; announcement teasing the new AI &ldquo;digital aging&rdquo; feature. Your message &amp; ETA below still show.</p>
                    </div>
                </label>
            </div>
            @error('style')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
        </div>

        <div class="glass rounded-2xl p-6 space-y-4">
            <div>
                <h2 class="text-base font-semibold text-white">Visitor message</h2>
                <p class="text-xs text-white/50">Shown on the 503 page and inside the API error envelope. Both fields are optional.</p>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">Message</label>
                <textarea name="message" rows="3" maxlength="500"
                          placeholder="We're upgrading our infrastructure. Thanks for your patience!"
                          class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ old('message', $message) }}</textarea>
                @error('message')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">Estimated back online</label>
                <input type="text" name="eta" value="{{ old('eta', $eta) }}" maxlength="120"
                       placeholder="e.g. Today at 6:00 PM UTC"
                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                @error('eta')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold transition">
                <i class="fas fa-save mr-1.5"></i> Save settings
            </button>
        </div>
    </form>
</div>
@endsection
