@extends('user.layouts.app')
@section('title', 'Connect QR - ' . ($link->title ?: $link->alias))

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.show', $link) }}" class="text-white/30 hover:text-white/50"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-2xl font-bold text-white">Connect QR</h1>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="glass rounded-2xl p-6">
            <h2 class="text-lg font-semibold text-white mb-3">Scan &amp; connect in one step</h2>
            <p class="text-sm text-white/60 mb-4">
                Guests who scan this QR land on your event page with a special
                <span class="font-semibold text-white/80">RSVP &amp; Connect</span> prompt: one code from their
                email or phone signs them in (creating an account automatically if they're new),
                saves a "Going" RSVP, and connects them to you as a follower.
            </p>
            <div class="mb-4">
                <label class="block text-sm font-medium text-white/60 mb-1">It encodes</label>
                <div class="text-sm text-blue-400 bg-blue-500/10 px-3 py-2 rounded-xl font-mono break-all">{{ $connectUrl }}</div>
            </div>
            <p class="text-xs text-white/40">
                Scan results appear on the <a href="{{ route('user.links.visitors', $link) }}" class="text-blue-400 hover:underline">Visitor Insights</a>
                page in the "QR Connect" panel — scans, new signups vs. existing users, RSVPs, and new followers.
            </p>
        </div>

        <div class="glass rounded-2xl p-6 flex flex-col items-center">
            <div class="bg-white rounded-2xl p-4 mb-5">
                {!! $qrSvg !!}
            </div>
            <a href="{{ route('user.links.connect-qr', [$link, 'download' => 'poster']) }}" target="_blank"
               class="w-full text-center px-4 py-2.5 rounded-xl text-sm font-bold bg-white text-gray-900 hover:bg-white/90 mb-3">
                <i class="fas fa-print mr-1.5"></i> Print poster
            </a>
            <div class="flex gap-3 w-full">
                <a href="{{ route('user.links.connect-qr', [$link, 'download' => 'png']) }}"
                   class="flex-1 text-center px-4 py-2.5 rounded-xl text-sm font-bold bg-blue-600 hover:bg-blue-500 text-white">
                    <i class="fas fa-download mr-1.5"></i> Download PNG
                </a>
                <a href="{{ route('user.links.connect-qr', [$link, 'download' => 'svg']) }}"
                   class="flex-1 text-center px-4 py-2.5 rounded-xl text-sm font-bold bg-white/10 hover:bg-white/20 text-white">
                    <i class="fas fa-download mr-1.5"></i> Download SVG
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
