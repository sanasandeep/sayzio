@extends('admin.layouts.app')
@section('title', 'Site Pages')
@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="grid sm:grid-cols-2 gap-3">
        <a href="{{ route('admin.marketing-settings.index') }}" class="glass rounded-2xl p-5 hover:bg-white/[.06] transition flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-violet-500/15 text-violet-300 flex items-center justify-center"><i class="fas fa-bullhorn"></i></div>
            <div>
                <div class="text-sm font-semibold text-white">Marketing settings</div>
                <div class="text-[11px] text-white/50">Share image, GA4, Meta Pixel, trust strip, testimonials.</div>
            </div>
        </a>
        <a href="{{ route('admin.newsletter.index') }}" class="glass rounded-2xl p-5 hover:bg-white/[.06] transition flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-cyan-500/15 text-cyan-300 flex items-center justify-center"><i class="fas fa-envelope-open-text"></i></div>
            <div>
                <div class="text-sm font-semibold text-white">Newsletter subscribers</div>
                <div class="text-[11px] text-white/50">View, export, or remove subscribers.</div>
            </div>
        </a>
    </div>
    <div class="glass rounded-2xl p-6">
        <h2 class="text-lg font-semibold text-white mb-1">Site Pages</h2>
        <p class="text-sm text-white/50 mb-5">Edit the public marketing & legal pages. Changes go live immediately.</p>
        <div class="divide-y divide-white/5">
            @foreach($pages as $p)
                <div class="flex items-center justify-between py-3">
                    <div>
                        <div class="text-sm font-semibold text-white">{{ $p->title }}</div>
                        @php
                            $errorPageLabels = [
                                'error-403' => '403 No access',
                                'error-404' => '404 Not found',
                                'error-405' => '405 Invalid page',
                                'error-500' => '500 Server error',
                                'error-503' => '503 Maintenance',
                                'error-419' => '419 Session expired',
                                'error-429' => '429 Too many requests',
                            ];
                        @endphp
                        <div class="text-[11px] text-white/40">
                            @if(isset($errorPageLabels[$p->slug]))
                                Error page · {{ $errorPageLabels[$p->slug] }}
                            @else
                                /{{ $p->slug === 'home' ? '' : $p->slug }}
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('admin.site-pages.edit', $p->slug) }}" class="px-4 py-2 rounded-xl text-xs font-medium bg-violet-600 hover:bg-violet-700 text-white">Edit</a>
                </div>
            @endforeach
        </div>
    </div>

    <div class="glass rounded-2xl p-6">
        <h3 class="text-sm font-semibold text-white mb-1">Contact form recipient</h3>
        <p class="text-xs text-white/50 mb-3">Where contact form submissions are emailed. Leave blank to disable email notifications (messages are still saved).</p>
        <form method="POST" action="{{ route('admin.site-pages.contact-recipient') }}" class="flex gap-3">
            @csrf
            <input type="email" name="contact_recipient_email" value="{{ $recipient }}" placeholder="hello@yourdomain.com" class="flex-1 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
            <button type="submit" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 rounded-lg text-sm font-medium text-white">Save</button>
        </form>
    </div>
</div>
@endsection
