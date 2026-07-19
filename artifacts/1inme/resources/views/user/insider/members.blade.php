@extends('user.layouts.app')

@section('title', 'Insider members: ' . $link->title)

@section('content')
<div class="max-w-6xl mx-auto">
    @include('user.partials.page-hero', [
        'title'    => 'Insider members',
        'subtitle' => $link->title,
        'icon'     => 'fa-users',
        'back'     => route('user.links.insider.index', [$link, $block]),
    ])

    <div class="rounded-2xl overflow-hidden" style="background: rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08);">
        <table class="w-full text-sm text-white/80">
            <thead class="text-xs uppercase text-white/50">
                <tr>
                    <th class="px-4 py-3 text-left">Email</th>
                    <th class="px-4 py-3 text-left">Display name</th>
                    <th class="px-4 py-3 text-left">Tier</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Joined</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($members as $m)
                <tr class="border-t border-white/5">
                    <td class="px-4 py-3">{{ $m->email }}</td>
                    <td class="px-4 py-3">{{ $m->display_name ?: '—' }}</td>
                    <td class="px-4 py-3">{{ ucfirst($m->tier) }}</td>
                    <td class="px-4 py-3">{{ ucfirst($m->status) }}</td>
                    <td class="px-4 py-3">{{ $m->joined_at?->diffForHumans() }}</td>
                    <td class="px-4 py-3 text-right">
                        @if($m->status !== 'banned')
                        <form method="POST" action="{{ route('user.links.insider.members.ban', [$link, $block, $m]) }}" onsubmit="return confirm('Ban this member?');">
                            @csrf
                            <button class="text-red-400 hover:text-red-300 text-xs"><i class="fas fa-ban"></i> Ban</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-12 text-center text-white/40">No members yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $members->links() }}</div>
</div>
@endsection
