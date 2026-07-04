@php($tz = auth()->user()->timezone ?? config('app.timezone'))
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
