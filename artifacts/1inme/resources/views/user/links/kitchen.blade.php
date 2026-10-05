@extends('user.layouts.app')
@section('title', 'Kitchen - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))
@section('content')
{{--
    The screen that gets propped up next to the pass.

    Sana, 2026-10-05: "Need another dashboard like kitchen.... whowing all
    table names if exists with current order status... aurto refresh also".

    ---- Written to be read from four feet away -----------------------------

    Big type, few words, and the one number that matters on every card: how
    long it has been waiting. Colour carries the same thing as the number,
    because a colour does not have to be READ -- and the person looking at
    this is holding a pan.

    No prices anywhere. A kitchen ticket with money on it is a receipt, and
    reading past the price to find the dish is how an order goes out wrong.

    ---- It refreshes itself, and says when it last managed to --------------

    A board that silently stops updating is worse than one that is obviously
    broken: the kitchen keeps believing it. So the footer shows the time of
    the last successful refresh, and goes amber the moment one fails.
--}}
<div class="w-full max-w-[1600px] mx-auto" x-data="kitchenBoard()" x-init="start()">

    <div class="flex items-center gap-4 mb-5 flex-wrap">
        <a href="{{ $ordersUrl }}" class="text-white/30 hover:text-white transition-colors" title="Back to orders"><i class="fas fa-arrow-left"></i></a>
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                <i class="fas fa-fire-burner text-orange-400"></i> Kitchen
            </h1>
            <p class="text-xs text-white/40 mt-0.5">{{ $link->title ?: $link->alias }}</p>
        </div>

        <div class="ml-auto flex items-center gap-2 flex-wrap">
            <span class="kb-pill"><b x-text="board.open"></b> open</span>
            <span class="kb-pill" x-show="board.oldest_minutes !== null">
                longest wait <b x-text="board.oldest_minutes + 'm'"></b>
            </span>
            {{-- The off switch. Somebody reading a long ticket does not want
                 the board reordering itself under their eyes. --}}
            <button type="button" class="kb-btn" @click="live = !live"
                    :class="live ? '' : 'kb-btn-off'">
                <i class="fas" :class="live ? 'fa-circle-pause' : 'fa-circle-play'"></i>
                <span x-text="live ? 'Pause' : 'Resume'"></span>
            </button>
        </div>
    </div>

    <template x-if="board.groups.length === 0">
        <div class="glass rounded-2xl p-10 text-center">
            <i class="fas fa-mug-hot text-3xl text-white/15 mb-3"></i>
            <p class="text-white/50 text-sm">Nothing waiting. Orders appear here the moment they come in.</p>
        </div>
    </template>

    <div class="kb-grid">
        <template x-for="g in board.groups" :key="g.key">
            <div class="kb-card" :class="'kb-' + g.heat">
                <div class="kb-head">
                    <div class="kb-name" x-text="g.name"></div>
                    <div class="kb-age" x-show="g.minutes !== null">
                        <span x-text="g.minutes"></span>m
                    </div>
                </div>

                <div class="kb-status" x-text="g.label"></div>

                {{-- An empty table is ON the board and says so. A board that
                     lists only tables with orders cannot tell you table 7 is
                     free, which is half of what it is being read for. --}}
                <template x-if="g.empty">
                    <div class="kb-free">Free</div>
                </template>

                <template x-for="t in g.tickets" :key="t.id">
                    <div class="kb-ticket">
                        <div class="kb-ref">
                            <span>#<span x-text="t.ref"></span></span>
                            <span class="kb-tmin" x-text="t.minutes + 'm'"></span>
                        </div>
                        <div class="kb-who" x-show="t.customer" x-text="t.customer"></div>
                        <ul class="kb-lines">
                            <template x-for="(l, i) in t.lines" :key="i">
                                <li>
                                    <b x-text="l.qty"></b>
                                    <span x-text="l.name"></span>
                                    <em x-show="l.note" x-text="l.note"></em>
                                </li>
                            </template>
                        </ul>
                        <div class="kb-note" x-show="t.note" x-text="t.note"></div>
                        <div class="kb-moves">
                            <template x-for="n in t.next" :key="n">
                                {{-- Only the moves this order can actually
                                     make, from the model's own transition
                                     map -- not a second copy of it here that
                                     offers a button the server rejects. --}}
                                <button type="button" class="kb-move"
                                        :disabled="busy === t.id"
                                        @click="move(t, n)"
                                        x-text="board.statuses[n] || n"></button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>

    <div class="kb-foot">
        <span :class="stale ? 'kb-stale' : ''">
            <i class="fas" :class="stale ? 'fa-triangle-exclamation' : 'fa-rotate'"></i>
            <span x-show="!stale">Updated <span x-text="lastAt"></span></span>
            <span x-show="stale">Could not refresh — last updated <span x-text="lastAt"></span></span>
        </span>
        <span x-show="!live" class="kb-stale"><i class="fas fa-pause"></i> Paused</span>
        <span class="kb-dim">Amber after {{ $warnAfter }} minutes, red after {{ $lateAfter }}.</span>
    </div>
</div>

@push('styles')
<style>
    .kb-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 14px;
        align-items: start;
    }
    .kb-card {
        background: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: 16px;
        padding: 14px 15px;
        min-width: 0;
        /* The left edge carries the heat, so a glance down the board reads
           as a column of colour rather than a grid of text. */
        border-left-width: 5px;
        border-left-color: rgba(255,255,255,.10);
    }
    .kb-idle  { opacity: .55; }
    .kb-fresh { border-left-color: #22c55e; }
    .kb-warn  { border-left-color: #f59e0b; }
    .kb-late  { border-left-color: #ef4444; }

    .kb-head { display: flex; align-items: baseline; gap: 10px; }
    .kb-name {
        font-size: 19px; font-weight: 800; color: var(--text-primary);
        line-height: 1.15; overflow-wrap: anywhere; min-width: 0;
    }
    .kb-age {
        margin-left: auto; font-size: 19px; font-weight: 800;
        font-variant-numeric: tabular-nums; color: var(--text-muted);
        white-space: nowrap;
    }
    .kb-late .kb-age { color: #ef4444; }
    .kb-warn .kb-age { color: #f59e0b; }

    .kb-status {
        font-size: 11px; text-transform: uppercase; letter-spacing: .07em;
        color: var(--text-faint); margin-top: 2px;
    }
    .kb-free { font-size: 13px; color: var(--text-dimmed); margin-top: 10px; }

    .kb-ticket {
        margin-top: 11px; padding-top: 11px;
        border-top: 1px solid var(--border-glass);
    }
    .kb-ref {
        display: flex; gap: 8px; font-size: 11.5px; color: var(--text-faint);
        font-variant-numeric: tabular-nums;
    }
    .kb-tmin { margin-left: auto; }
    .kb-who { font-size: 12.5px; color: var(--text-muted); margin-top: 1px; }

    .kb-lines { margin: 6px 0 0; padding: 0; list-style: none; }
    .kb-lines li {
        font-size: 15px; line-height: 1.45; color: var(--text-primary);
        overflow-wrap: anywhere;
    }
    .kb-lines b {
        display: inline-block; min-width: 1.6em;
        color: var(--text-muted); font-variant-numeric: tabular-nums;
    }
    .kb-lines em { color: var(--text-dimmed); font-size: 12.5px; font-style: normal; }

    .kb-note {
        margin-top: 7px; font-size: 12.5px; color: #f59e0b;
        background: rgba(245,158,11,.08);
        border-radius: 8px; padding: 5px 8px;
        overflow-wrap: anywhere;
    }

    .kb-moves { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 9px; }
    .kb-move {
        font-size: 12px; font-weight: 600;
        color: var(--text-muted);
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        border-radius: 999px; padding: 6px 12px;
        cursor: pointer; transition: all .15s;
    }
    .kb-move:hover:not(:disabled) { color: var(--text-primary); }
    .kb-move:disabled { opacity: .4; cursor: default; }

    .kb-pill, .kb-btn {
        font-size: 12px; color: var(--text-muted);
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        border-radius: 999px; padding: 6px 13px;
        white-space: nowrap;
    }
    .kb-pill b { color: var(--text-primary); }
    .kb-btn { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
    .kb-btn-off { color: #f59e0b; border-color: rgba(245,158,11,.35); }

    .kb-foot {
        display: flex; gap: 14px; flex-wrap: wrap;
        margin-top: 16px; font-size: 11.5px; color: var(--text-dimmed);
    }
    .kb-stale { color: #f59e0b; }
    .kb-dim { margin-left: auto; }

    @media (max-width: 640px) {
        .kb-grid { grid-template-columns: 1fr; }
        .kb-dim { margin-left: 0; }
    }
</style>
@endpush

@push('scripts')
<script>
function kitchenBoard() {
    return {
        board: @js($board),
        live: true,
        stale: false,
        busy: null,
        lastAt: 'just now',
        // One request in flight at a time. On a busy Saturday a slow
        // response must not queue a second and then a third -- a board that
        // falls behind by stacking requests is worse than one that skips a
        // beat.
        inFlight: false,
        lastStamp: Date.now(),

        start() {
            setInterval(() => this.tick(), 10000);
            // The "updated 2m ago" clock has to move on its own, or a board
            // that stopped refreshing still reads as current.
            setInterval(() => this.retime(), 15000);
        },

        retime() {
            const mins = Math.floor((Date.now() - this.lastStamp) / 60000);
            this.lastAt = mins < 1 ? 'just now' : mins + 'm ago';
        },

        async tick() {
            if (!this.live || this.inFlight || document.hidden) return;
            this.inFlight = true;
            try {
                const res = await fetch(@js($pollUrl), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error('poll failed');
                const body = await res.json();
                if (body && body.data) {
                    this.board = body.data;
                    this.lastStamp = Date.now();
                    this.lastAt = 'just now';
                    this.stale = false;
                }
            } catch (e) {
                // Said on the screen rather than swallowed. A board that
                // quietly stops updating is worse than one that is
                // obviously broken: the kitchen keeps believing it.
                this.stale = true;
            } finally {
                this.inFlight = false;
            }
        },

        async move(ticket, next) {
            if (this.busy) return;
            this.busy = ticket.id;
            try {
                const url = @js($statusUrl).replace('__ID__', ticket.id);
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ status: next }),
                });
                if (res.ok) {
                    // Straight back to the server rather than patching the
                    // card here. Two people work this screen at once, and a
                    // board that trusts its own guess drifts from what the
                    // other one did.
                    await this.tick();
                }
            } catch (e) {
                this.stale = true;
            } finally {
                this.busy = null;
            }
        },
    };
}
</script>
@endpush
@endsection
