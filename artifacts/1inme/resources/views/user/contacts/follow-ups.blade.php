@extends('user.layouts.app')

@section('title', 'Follow-ups')

@section('content')
@php($tz = auth()->user()->timezone ?? config('app.timezone'))
<div class="max-w-4xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Follow-ups',
        'subtitle' => 'Everything you need to follow up on, soonest first — no need to open each contact.',
        'icon' => 'fa-bell',
        'chips' => [
            ['icon' => 'fa-exclamation-circle text-red-400', 'text' => $overdue->count() . ' overdue'],
            ['icon' => 'fa-clock text-cyan-400',            'text' => $upcoming->count() . ' upcoming'],
        ],
    ])

    <div class="mb-6">
        <a href="{{ route('user.contacts.index') }}" class="inline-flex items-center gap-1.5 text-xs font-medium" style="color:var(--text-muted);">
            <i class="fas fa-arrow-left text-[10px]"></i> Back to contacts
        </a>
    </div>

    @if($overdue->isEmpty() && $upcoming->isEmpty())
        <div class="card-premium p-10 text-center">
            <div class="mx-auto mb-4 w-14 h-14 rounded-full flex items-center justify-center" style="background:rgba(61,107,255,.10);">
                <i class="fas fa-bell-slash text-xl" style="color:#90acff;"></i>
            </div>
            <h3 class="text-base font-bold mb-1" style="color:var(--text-primary);">No follow-ups scheduled</h3>
            <p class="text-sm mb-5" style="color:var(--text-muted);">Set a reminder on any contact and it'll show up here.</p>
            <a href="{{ route('user.contacts.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold" style="background:linear-gradient(135deg,#3d6bff,#ec4899);color:#fff;">
                <i class="fas fa-address-book"></i> Go to contacts
            </a>
        </div>
    @endif

    @if($overdue->isNotEmpty())
        <div class="mb-8">
            <h2 class="text-[11px] font-bold uppercase tracking-wider mb-3 flex items-center gap-1.5" style="color:#ef4444;">
                <i class="fas fa-exclamation-circle"></i> Overdue ({{ $overdue->count() }})
            </h2>
            <div class="space-y-2">
                @foreach($overdue as $contact)
                    @include('user.contacts._follow_up_row', ['contact' => $contact, 'tz' => $tz, 'overdue' => true])
                @endforeach
            </div>
        </div>
    @endif

    @if($upcoming->isNotEmpty())
        <div class="mb-8">
            <h2 class="text-[11px] font-bold uppercase tracking-wider mb-3 flex items-center gap-1.5" style="color:var(--text-faint);">
                <i class="fas fa-clock" style="color:#90acff;"></i> Upcoming ({{ $upcoming->count() }})
            </h2>
            <div class="space-y-2">
                @foreach($upcoming as $contact)
                    @include('user.contacts._follow_up_row', ['contact' => $contact, 'tz' => $tz, 'overdue' => false])
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
