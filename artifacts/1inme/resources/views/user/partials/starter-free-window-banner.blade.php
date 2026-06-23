@auth
    @php
        // Re-confirmation nudge for free Starter-plan users whose rolling
        // 1-year free window is about to lapse. Reminder only — lapsing never
        // locks the account or downgrades anything. Render only when:
        //   - the user is on the lineup default (free Starter) plan, and
        //   - the free window is within the lead time of lapsing.
        $__starterUser = auth()->user();
        $__showStarterRenew = $__starterUser
            && $__starterUser->onDefaultPlan()
            && $__starterUser->starterFreeWindowDueWithin(14);
        $__starterEndsAt = $__starterUser?->starter_free_window_ends_at;
    @endphp
    @if($__showStarterRenew)
        <div x-data="{
                dismissed: sessionStorage.getItem('starterRenewDismissed') === '1',
                dismiss() { this.dismissed = true; sessionStorage.setItem('starterRenewDismissed', '1'); }
             }"
             x-show="!dismissed"
             x-cloak
             class="mb-4 p-3.5 rounded-xl text-violet-200 text-xs font-medium"
             style="border: 1px solid rgba(124,58,237,0.25); background: rgba(124,58,237,0.08);">
            <div class="flex items-center gap-2.5">
                <i class="fas fa-gift"></i>
                <span class="flex-1">
                    Your free <strong>Starter</strong> year
                    @if($__starterEndsAt)
                        @if($__starterEndsAt->isPast())
                            has lapsed — renew free to keep checking in. Nothing is locked; your account and links are untouched.
                        @else
                            ends {{ $__starterEndsAt->diffForHumans() }}. Renew free for another year — your account and links stay exactly as they are.
                        @endif
                    @else
                        is up for renewal. Renew free for another year — nothing changes.
                    @endif
                </span>

                <form action="{{ route('user.starter.renew-free-window') }}" method="POST" class="inline-flex">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold whitespace-nowrap"
                            style="border: 1px solid rgba(124,58,237,0.4); background: rgba(124,58,237,0.12); color: #c4b5fd;">
                        <i class="fas fa-rotate text-[9px]"></i> Renew free
                    </button>
                </form>

                <button type="button"
                        @click="dismiss()"
                        class="inline-flex items-center justify-center w-6 h-6 rounded-full text-violet-200/70 hover:text-violet-100 hover:bg-violet-500/10 transition-colors"
                        title="Dismiss"
                        aria-label="Dismiss this reminder">
                    <i class="fas fa-times text-[10px]"></i>
                </button>
            </div>
        </div>
    @endif
@endauth
