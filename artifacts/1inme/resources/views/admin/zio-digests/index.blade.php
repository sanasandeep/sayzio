@extends('admin.layouts.app')
@section('title', 'Zio Digests')
@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-3 min-w-0">
            <span class="inline-flex items-center bg-white/90 rounded-lg px-2 py-1 shrink-0">
                <img src="{{ $brandLogoUrl }}" alt="Zio Digest" class="h-8 w-auto max-w-[220px] object-contain">
            </span>
            <h2 class="text-lg font-semibold text-white truncate ak-strong">Zio Digests</h2>
        </div>
        <a href="{{ route('admin.zio-digests.create') }}"
           class="px-3 py-2 bg-white/10 hover:bg-white/20 border border-white/10 rounded-lg text-sm text-white ak-strong">
            <i class="fas fa-plus mr-1"></i> New digest
        </a>
    </div>

    @if(session('success'))
        <div class="px-3 py-2 bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 rounded-lg text-sm ak-green">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="px-3 py-2 bg-red-500/10 border border-red-400/30 text-red-200 rounded-lg text-sm ak-red">{{ session('error') }}</div>
    @endif

    {{-- Branding (logo) card --}}
    <div class="glass rounded-2xl p-6 space-y-4">
        <div>
            <h3 class="text-sm font-semibold text-white ak-strong"><i class="fas fa-image mr-2 text-white/50 ak-muted"></i>Zio Digest logo</h3>
            <p class="text-xs text-white/50 mt-1 ak-muted">Shown on public digest pages, digest emails, and this admin section. {{ $brandHasCustomLogo ? 'A custom logo is currently in use.' : 'Currently using the bundled default logo.' }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-4">
            <span class="inline-flex items-center bg-white/90 rounded-xl px-4 py-3">
                <img src="{{ $brandLogoUrl }}" alt="Current Zio Digest logo" class="h-12 w-auto max-w-[320px] object-contain">
            </span>
            <form method="POST" action="{{ route('admin.zio-digests.logo.update') }}" enctype="multipart/form-data" class="flex items-center gap-2">
                @csrf
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" required
                       class="text-xs text-white/60 file:mr-2 file:px-3 file:py-2 file:bg-white/10 file:hover:bg-white/20 file:border file:border-white/10 file:rounded-lg file:text-sm file:text-white file:cursor-pointer ak-muted ak-input">
                <button class="px-3 py-2 bg-white/10 hover:bg-white/20 border border-white/10 rounded-lg text-sm text-white shrink-0 ak-strong">Upload new logo</button>
            </form>
            @if($brandHasCustomLogo)
                <form method="POST" action="{{ route('admin.zio-digests.logo.remove') }}"
                      onsubmit="return window.themedConfirmSubmit(this, {title: 'Revert to the default logo?', message: 'The custom logo will be removed and the bundled Zio Digest logo will be used everywhere.', confirmText: 'Revert', confirmIcon: 'fa-rotate-left', iconClass: 'fa-rotate-left'})">
                    @csrf @method('DELETE')
                    <button class="px-3 py-2 bg-white/5 hover:bg-white/10 border border-white/10 rounded-lg text-sm text-white/70 ak-strong">Revert to default</button>
                </form>
            @endif
        </div>
        @error('logo')
            <div class="px-3 py-2 bg-red-500/10 border border-red-400/30 text-red-200 rounded-lg text-sm ak-red">{{ $message }}</div>
        @enderror
    </div>

    {{-- SendGrid settings card --}}
    <div class="glass rounded-2xl p-6 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-white ak-strong"><i class="fas fa-paper-plane mr-2 text-white/50 ak-muted"></i>SendGrid (digest email delivery)</h3>
                <p class="text-xs text-white/50 mt-1 ak-muted">{{ $sendgridStatus['detail'] }}</p>
            </div>
            <span class="px-2 py-1 rounded-full text-xs {{ $sendgridStatus['state'] === 'connected' ? 'bg-emerald-500/10 text-emerald-300 border border-emerald-400/30 ak-green' : 'bg-amber-500/10 text-amber-300 border border-amber-400/30 ak-amber' }}">
                {{ $sendgridStatus['label'] }}
            </span>
        </div>
        <form method="POST" action="{{ route('admin.zio-digests.settings.update') }}" class="grid gap-3 md:grid-cols-3">
            @csrf
            <div>
                <label class="block text-xs uppercase tracking-wider text-white/40 mb-1 ak-note">API key</label>
                <input type="password" name="api_key" autocomplete="new-password"
                       placeholder="{{ $sendgridMasked ?: 'SG.xxxxxxxx' }}"
                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white ak-strong ak-input">
                <label class="mt-1 flex items-center gap-2 text-xs text-white/40 ak-note">
                    <input type="checkbox" name="clear_key" value="1" class="rounded"> Clear stored key
                </label>
            </div>
            <div>
                <label class="block text-xs uppercase tracking-wider text-white/40 mb-1 ak-note">From email</label>
                <input type="email" name="from_email" value="{{ $sendgridFrom['email'] }}"
                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white ak-strong ak-input">
            </div>
            <div>
                <label class="block text-xs uppercase tracking-wider text-white/40 mb-1 ak-note">From name</label>
                <input type="text" name="from_name" value="{{ $sendgridFrom['name'] }}"
                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white ak-strong ak-input">
            </div>
            <div class="md:col-span-3">
                <button class="px-3 py-2 bg-white/10 hover:bg-white/20 border border-white/10 rounded-lg text-sm text-white ak-strong">Save settings</button>
            </div>
        </form>
    </div>

    <div class="glass rounded-2xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wider text-white/40 border-b border-white/10 ak-note">
                    <th class="px-4 py-3">Title</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">WhatsApp</th>
                    <th class="px-4 py-3">Created</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($digests as $digest)
                <tr class="border-b border-white/5 text-white/80 ak-strong">
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.zio-digests.edit', $digest) }}" class="text-white hover:underline ak-strong">{{ $digest->title }}</a>
                        @if($digest->isPublished())
                            <a href="{{ $digest->publicUrl() }}" target="_blank" class="ml-1 text-white/40 hover:text-white ak-note"><i class="fas fa-arrow-up-right-from-square text-xs"></i></a>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs {{ $digest->isPublished() ? 'bg-emerald-500/10 text-emerald-300 ak-green' : 'bg-white/10 text-white/60 ak-muted' }}">{{ ucfirst($digest->status) }}</span>
                    </td>
                    <td class="px-4 py-3 text-xs">
                        {{ ucfirst($digest->email_status) }}
                        @if($digest->email_status !== 'idle')
                            <span class="text-white/40 ak-note">({{ $digest->email_sent_count }} sent / {{ $digest->email_failed_count }} failed / {{ $digest->email_skipped_count }} skipped)</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs">
                        {{ ucfirst($digest->wa_status) }}
                        @if($digest->wa_status !== 'idle')
                            <span class="text-white/40 ak-note">({{ $digest->wa_sent_count }} sent / {{ $digest->wa_failed_count }} failed / {{ $digest->wa_skipped_count }} skipped)</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-white/50 ak-muted">{{ $digest->created_at?->format('M j, Y') }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-2 text-xs">
                            <a href="{{ route('admin.zio-digests.preview', $digest) }}" target="_blank" class="text-white/60 hover:text-white ak-muted" title="Preview"><i class="fas fa-eye"></i></a>
                            <a href="{{ route('admin.zio-digests.report', $digest) }}" class="text-white/60 hover:text-white ak-muted" title="Delivery report"><i class="fas fa-chart-simple"></i></a>
                            <form method="POST" action="{{ route('admin.zio-digests.duplicate', $digest) }}">
                                @csrf
                                <button class="text-white/60 hover:text-white ak-muted" title="Duplicate"><i class="fas fa-copy"></i></button>
                            </form>
                            <form method="POST" action="{{ route('admin.zio-digests.destroy', $digest) }}"
                                  onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this digest?', message: 'This also removes its delivery report. The public page will stop working.', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash', danger: true})">
                                @csrf @method('DELETE')
                                <button class="text-red-300/70 hover:text-red-300 ak-red" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-white/40 text-sm ak-note">No digests yet. Create your first one.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $digests->links() }}</div>
</div>
@endsection
