@extends('admin.layouts.app')
@section('title', 'Announcements')
@section('content')
@php
    $audienceHints = [
        'marketing'      => 'Shown to everyone on the marketing / public pages (this app and the 1inme.com site).',
        'guests'         => 'Shown on public pages only to visitors who are NOT logged in.',
        'users'          => 'Shown on public pages only to visitors who ARE logged in.',
        'user_dashboard' => 'Shown inside the logged-in user dashboard.',
    ];
@endphp
<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-white ak-strong">Public announcements</h1>
        <p class="text-xs text-white/50 mt-1 ak-muted">
            Publish a short banner ("public notify") to each audience independently. Leave a message blank or
            untick <em>Show this banner</em> to clear it from that surface. Visitors can dismiss a banner; editing
            its text re-shows it to everyone again.
        </p>
    </div>

    @if(session('success'))
        <div class="px-3 py-2 bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 rounded-lg text-sm ak-green">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.announcements.update') }}" class="space-y-5">
        @csrf
        @method('PUT')

        @foreach($audiences as $audienceKey => $audienceLabel)
            @php $row = $announcements[$audienceKey]; @endphp
            <div class="glass rounded-2xl p-6 space-y-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-base font-semibold text-white ak-strong">{{ $audienceLabel }}</h2>
                        <p class="text-xs text-white/50 mt-0.5 ak-muted">{{ $audienceHints[$audienceKey] ?? '' }}</p>
                    </div>
                    <label class="inline-flex items-center gap-2 text-xs font-medium text-white/70 shrink-0 cursor-pointer ak-strong">
                        <input type="hidden" name="announcements[{{ $audienceKey }}][enabled]" value="0">
                        <input type="checkbox" name="announcements[{{ $audienceKey }}][enabled]" value="1"
                               @checked(old("announcements.$audienceKey.enabled", $row['enabled']))
                               class="rounded border-white/20 bg-white/5 text-blue-500 focus:ring-blue-500 ak-input">
                        Show this banner
                    </label>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5 ak-muted">Message</label>
                    <textarea name="announcements[{{ $audienceKey }}][message]" rows="2" maxlength="280"
                              placeholder="e.g. We just shipped scheduled posts, try it now!"
                              class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white ak-strong ak-input">{{ old("announcements.$audienceKey.message", $row['message']) }}</textarea>
                    @error("announcements.$audienceKey.message")<p class="mt-1 text-xs text-red-400 ak-red">{{ $message }}</p>@enderror
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5 ak-muted">Link URL <span class="text-white/30 normal-case font-normal ak-note">(optional)</span></label>
                        <input type="url" name="announcements[{{ $audienceKey }}][link_url]"
                               value="{{ old("announcements.$audienceKey.link_url", $row['link_url']) }}"
                               placeholder="https://1in.me/pricing"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white ak-strong ak-input">
                        @error("announcements.$audienceKey.link_url")<p class="mt-1 text-xs text-red-400 ak-red">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5 ak-muted">Link label <span class="text-white/30 normal-case font-normal ak-note">(optional)</span></label>
                        <input type="text" name="announcements[{{ $audienceKey }}][link_label]" maxlength="60"
                               value="{{ old("announcements.$audienceKey.link_label", $row['link_label']) }}"
                               placeholder="Learn more"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white ak-strong ak-input">
                        @error("announcements.$audienceKey.link_label")<p class="mt-1 text-xs text-red-400 ak-red">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
        @endforeach

        <div class="flex justify-end">
            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold transition-colors">
                <i class="fas fa-bullhorn text-xs"></i> Save announcements
            </button>
        </div>
    </form>
</div>
@endsection
