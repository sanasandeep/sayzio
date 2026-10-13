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
@include('user.links.partials.menu-fullscreen')
<div data-screen-panel class="kb-screen" data-kitchen-board="true" x-data="kitchenBoard()" x-init="start()">

    <div class="kb-editor-chrome">
    @include('user.links.partials.editor-header', [
        'link' => $link,
        'activeMainTab' => 'kitchen',
        'editorBack' => $ordersUrl,
    ])
    </div>
    <div class="flex items-center justify-end gap-3 flex-wrap mb-4 text-sm" style="color:var(--text-muted)">
        <strong style="margin-right:auto;color:var(--text-primary)">{{ $link->title ?: $link->alias }} · Kitchen</strong>
        <button type="button" class="btn-ghost" onclick="menuFullscreen(this)">Fullscreen / Exit</button>
        <span><b x-text="board.counts.active"></b> active</span>
        <span x-show="board.oldest_wait">longest wait <b x-text="board.oldest_wait"></b></span>
        <button type="button" class="btn-ghost text-xs py-2" @click="live = !live">
            <i class="fas" :class="live ? 'fa-circle-pause' : 'fa-circle-play'"></i>
            <span x-text="live ? 'Pause' : 'Resume'"></span>
        </button>
    </div>

    <div class="kb-tabs" role="group" aria-label="Order views">
        <template x-for="v in ['active','cancelled','closed']" :key="v"><button type="button" :aria-pressed="view === v" :class="{'is-active':view===v}" :disabled="inFlight" @click="selectView(v)"><span x-text="v.charAt(0).toUpperCase()+v.slice(1)"></span> <b x-text="board.counts[v]"></b></button></template>
    </div>
    <div class="kb-workspace" :class="{'kb-history':view!=='active'}"><main>
    {{-- The free tables, said once and quietly.

         Sana, 2026-10-05: "it looks ugly and not clear". Four free tables
         used to be four full-size cards shouting FREE, with the one table
         that had food on it reduced to a narrow column beside them. The
         board's job is the work; which tables are open is a single line. --}}
    <div class="kb-free-row" x-show="board.free && board.free.length">
        <span class="kb-free-label">Free</span>
        <template x-for="name in (board.free || [])" :key="name">
            <span class="kb-free-chip" x-text="name"></span>
        </template>
    </div>

    <template x-if="board.groups.length === 0">
        <div class="glass rounded-2xl p-10 text-center">
            <i class="fas fa-mug-hot text-3xl text-white/15 mb-3"></i>
            <p class="text-white/50 text-sm"><span x-text="view === 'active' ? 'Nothing waiting. New orders appear automatically.' : 'No ' + view + ' orders.'"></span></p>
        </div>
    </template>

    <div class="kb-grid">
        <template x-for="g in board.groups" :key="g.key">
            <div class="kb-card" :class="view === 'active' ? 'kb-' + g.heat : 'kb-idle'">
                <div class="kb-head" x-show="board.mode === 'tables' && view === 'active'">
                    <div class="kb-name" x-text="g.name"></div>
                    <div class="kb-age" x-show="view === 'active' && g.wait" x-text="g.wait"></div>
                </div>



                <template x-for="t in g.tickets" :key="t.id">
                    <div class="kb-ticket">
                        <div class="kb-ref">
                            <span class="kb-order-number"><small x-text="'Order · ' + t.label"></small> #<span x-text="t.ref"></span></span>
                            <span class="kb-tmin" x-text="view === 'active' ? t.wait : new Date(t.placed).toLocaleString()"></span>
                        </div>
                        <p x-show="t.wanted_at" x-text="'Scheduled: ' + new Date(t.wanted_at).toLocaleString()"></p>
                        <p x-show="t.prepaid">Prepaid coupon serving</p>
                        <div class="kb-who" x-show="t.customer" x-text="t.customer"></div>
                        <ul class="kb-lines">
                            <template x-for="(l, i) in t.lines" :key="i">
                                <li>
                                    <b x-text="l.qty"></b>
                                    <span x-text="l.name"></span>
                                    <em x-show="l.options && l.options.length" x-text="optionText(l.options)"></em><em x-show="l.note" x-text="l.note"></em>
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

    <p class="kb-history-hint" x-show="view !== 'active'">Most recent <span x-text="board.limit"></span> orders. <a href="{{ $ordersUrl }}">View all orders →</a></p>
    </main><aside class="kb-summary" x-show="view === 'active'"><h2>Item totals</h2><p>Across active orders · ready quantities included</p><template x-for="item in board.summary" :key="item.key"><div class="kb-summary-row"><div><strong x-text="item.name"></strong><small x-show="item.options && item.options.length" x-text="optionText(item.options)"></small><small x-show="item.ready" x-text="item.ready + ' ready · ' + (item.qty-item.ready) + ' to prepare'"></small></div><b x-text="item.qty"></b></div></template><p x-show="!board.summary.length">No items waiting.</p></aside></div>
    <div class="kb-foot">
        <span :class="stale ? 'kb-stale' : ''">
            <i class="fas" :class="stale ? 'fa-triangle-exclamation' : 'fa-rotate'"></i>
            <span x-show="!stale">Updated <span x-text="lastAt"></span></span>
            <span x-show="stale">Could not refresh. Last updated <span x-text="lastAt"></span></span>
        </span>
        <span x-show="!live" class="kb-stale"><i class="fas fa-pause"></i> Paused</span>
        <span class="kb-dim">Amber after {{ $warnAfter }} minutes, red after {{ $lateAfter }}.</span>
    </div>
</div>

@push('styles')
<style>
    .kb-screen{width:100%;max-width:none}.kb-screen:fullscreen .kb-editor-chrome,.kb-screen.menu-screen-expanded .kb-editor-chrome{display:none}
    .kb-workspace{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:20px;align-items:start}.kb-workspace.kb-history{grid-template-columns:1fr}.kb-workspace main{min-width:0}.kb-summary{position:sticky;top:16px;background:var(--bg-card);border:1px solid var(--border-glass);border-radius:18px;padding:18px;max-height:80vh;overflow:auto}.kb-summary h2{font-size:20px;font-weight:750}.kb-summary p,.kb-history-hint{font-size:12px;color:var(--text-muted);margin:8px 0 16px}.kb-summary-row{display:flex;align-items:center;gap:12px;padding:12px 0;border-top:1px solid var(--border-glass)}.kb-summary-row div{flex:1;min-width:0;overflow-wrap:anywhere}.kb-summary-row small{display:block;color:var(--text-muted);font-size:12px}.kb-summary-row>b{font-size:28px;font-variant-numeric:tabular-nums}.kb-tabs{display:flex;gap:6px;margin-bottom:16px}.kb-tabs button{border:1px solid var(--border-glass);border-radius:999px;padding:7px 13px;font-size:12px;color:var(--text-muted)}.kb-tabs button.is-active{background:var(--accent,#4263ff);color:white}.kb-tabs b{margin-left:5px}.kb-order-number{font-size:27px;font-weight:800;color:var(--text-primary)}.kb-order-number small{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.1em;font-weight:600;color:var(--text-muted)}.kb-ref{align-items:center}.kb-workspace .kb-name{font-size:17px}.kb-workspace .kb-age{font-size:14px}
    @media(max-width:900px){.kb-workspace{grid-template-columns:1fr}.kb-summary{position:static;max-height:none}}
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
    /* One quiet line, not four cards. */
    .kb-free-row {
        display: flex; align-items: center; gap: 7px; flex-wrap: wrap;
        margin-bottom: 14px;
    }
    .kb-free-label {
        font-size: 10.5px; text-transform: uppercase; letter-spacing: .07em;
        color: var(--text-faint); margin-right: 2px;
    }
    .kb-free-chip {
        font-size: 12px; font-weight: 600;
        color: var(--text-dimmed);
        border: 1px dashed var(--border-glass);
        border-radius: 8px;
        padding: 3px 10px;
        white-space: nowrap;
    }

    .kb-card>.kb-ticket:first-of-type{border-top:0;margin-top:0;padding-top:0}
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
        view: @js($board['view']),
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

        optionText(options) { return (options || []).map(o => typeof o === 'string' ? o : [o.group_name || o.group, o.choice_name || o.choice || o.name || o.label].filter(Boolean).join(': ')).filter(Boolean).join(', '); },
        async selectView(view) { if(this.inFlight) return; this.view=view; await this.tick(true); this.view=this.board.view; },
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

        async tick(force = false) {
            if ((!force && (!this.live || document.hidden)) || this.inFlight) return;
            this.inFlight = true;
            try {
                const pollUrl = new URL(@js($pollUrl),window.location.origin); pollUrl.searchParams.set('view',this.view);
                const res = await fetch(pollUrl, {
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
                    await this.tick(true);
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
