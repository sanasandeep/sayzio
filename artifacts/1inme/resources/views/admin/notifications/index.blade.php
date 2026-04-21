@extends('admin.layouts.app')
@section('title', 'Notifications')
@section('content')
<div class="max-w-6xl mx-auto px-4 py-6 space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold" style="color: var(--text-primary);">Notifications</h1>
            <p class="text-sm" style="color: var(--text-muted);">Compose an in-app announcement and audit past broadcasts.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="p-3 rounded-lg text-sm" style="background: rgba(16,185,129,0.10); color:#059669; border:1px solid rgba(16,185,129,0.25);">
            {{ session('success') }}
        </div>
    @endif

    {{-- Compose -------------------------------------------------------- --}}
    <section class="rounded-2xl p-5"
             style="background: var(--bg-card); border:1px solid var(--border-strong);">
        <h2 class="text-base font-semibold mb-4" style="color: var(--text-primary);">
            <i class="fas fa-bullhorn mr-2 text-violet-500"></i> New broadcast
        </h2>

        <form method="POST" action="{{ route('admin.notifications.send') }}" class="space-y-4" x-data="{ kind: '{{ old('target_kind', 'all') }}' }">
            @csrf
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Audience</label>
                    <select name="target_kind" x-model="kind" class="theme-input w-full">
                        <option value="all">Every active user</option>
                        <option value="plan">Users on a specific plan</option>
                        <option value="role">Users with a specific role</option>
                        <option value="country">Users in a specific country</option>
                        <option value="user">A single user (email or ID)</option>
                    </select>
                    @error('target_kind')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">
                        <span x-show="kind === 'plan'">Plan</span>
                        <span x-show="kind === 'role'">Role</span>
                        <span x-show="kind === 'country'">Country code (ISO-2)</span>
                        <span x-show="kind === 'user'">User email or ID</span>
                        <span x-show="kind === 'all'" style="color: var(--text-faint);">— No target needed —</span>
                    </label>

                    <template x-if="kind === 'plan'">
                        <select name="target_value" class="theme-input w-full">
                            @foreach($plans as $p)
                                <option value="{{ $p->slug }}" @selected(old('target_value') === $p->slug)>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </template>

                    <template x-if="kind === 'role'">
                        <select name="target_value" class="theme-input w-full">
                            @foreach(['user', 'super_admin'] as $r)
                                <option value="{{ $r }}" @selected(old('target_value') === $r)>{{ $r }}</option>
                            @endforeach
                        </select>
                    </template>

                    <template x-if="kind === 'country'">
                        <input type="text" name="target_value" maxlength="2" placeholder="US"
                               value="{{ old('target_value') }}"
                               class="theme-input w-full uppercase"/>
                    </template>

                    <template x-if="kind === 'user'">
                        <input type="text" name="target_value" placeholder="user@example.com or 1234"
                               value="{{ old('target_value') }}"
                               class="theme-input w-full"/>
                    </template>

                    <template x-if="kind === 'all'">
                        <input type="text" disabled value="All active users" class="theme-input w-full opacity-60"/>
                    </template>

                    @error('target_value')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Subject</label>
                <input type="text" name="subject" value="{{ old('subject') }}" maxlength="200" required
                       placeholder="Scheduled maintenance Sunday 02:00 UTC"
                       class="theme-input w-full"/>
                @error('subject')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Message</label>
                <textarea name="body" rows="4" maxlength="4000" required
                          placeholder="Hi! We're rolling out a new analytics filter on Sunday…"
                          class="theme-input w-full">{{ old('body') }}</textarea>
                @error('body')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Optional CTA URL</label>
                <input type="url" name="target_url" value="{{ old('target_url') }}" placeholder="https://1inme.com/changelog"
                       class="theme-input w-full"/>
                @error('target_url')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center justify-between pt-2">
                <p class="text-xs" style="color: var(--text-faint);">
                    Recipients who muted “Announcements from 1INME” in their notification preferences will be skipped.
                </p>
                <button type="submit"
                        class="px-4 py-2 rounded-lg text-sm font-semibold bg-violet-600 hover:bg-violet-700 text-white">
                    <i class="fas fa-paper-plane mr-1"></i> Send broadcast
                </button>
            </div>
        </form>
    </section>

    {{-- Past broadcasts ----------------------------------------------- --}}
    <section class="rounded-2xl"
             style="background: var(--bg-card); border:1px solid var(--border-strong);">
        <div class="px-5 py-4 flex items-center justify-between" style="border-bottom:1px solid var(--border-subtle);">
            <h2 class="text-base font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-history mr-2 text-violet-500"></i> Recent broadcasts
            </h2>
            <span class="text-xs" style="color: var(--text-faint);">{{ $broadcasts->total() }} total</span>
        </div>

        @if($broadcasts->count() === 0)
            <div class="p-10 text-center text-sm" style="color: var(--text-muted);">
                Nothing yet. Compose your first broadcast above.
            </div>
        @else
            <div class="divide-y" style="border-color: var(--border-subtle);">
                @foreach($broadcasts as $b)
                    <div class="px-5 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-sm font-semibold" style="color: var(--text-primary);">{{ $b->subject }}</span>
                                    <span class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full"
                                          style="background: rgba(124,58,237,0.12); color:#7c3aed;">
                                        {{ $b->target_kind }}@if($b->target_value): {{ $b->target_value }}@endif
                                    </span>
                                </div>
                                <p class="text-xs whitespace-pre-line" style="color: var(--text-muted);">{{ \Illuminate\Support\Str::limit($b->body, 240) }}</p>
                                @if($b->target_url)
                                    <a href="{{ $b->target_url }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center gap-1 mt-2 text-xs text-violet-500 hover:underline">
                                        <i class="fas fa-external-link"></i> {{ $b->target_url }}
                                    </a>
                                @endif
                            </div>
                            <div class="text-right shrink-0">
                                <div class="text-sm font-semibold" style="color: var(--text-primary);">{{ number_format($b->recipients_count) }}</div>
                                <div class="text-[11px]" style="color: var(--text-faint);">recipients</div>
                                <div class="text-[11px] mt-1" style="color: var(--text-faint);">{{ $b->created_at?->diffForHumans() }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="px-5 py-4">{{ $broadcasts->links() }}</div>
        @endif
    </section>
</div>
@endsection
