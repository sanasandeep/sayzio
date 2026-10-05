{{--
    One header for every screen that hangs off a menu.

    Sana, 2026-10-05: "header and paylayout isnt same accross.. fix it
    uniform".

    He was right, and it was the usual cause: Orders grew its own header,
    then the Counter grew another, then the Kitchen grew a third. Each one
    was reasonable alone and together they made the section feel like three
    products -- different title sizes, different back links, the status
    chip in a different place each time.

    This is the one header. A screen passes its title, its one-line
    subtitle, and the actions that belong to it; it does not get to decide
    where any of those sit.

    Parameters:
      $mhTitle     the screen's name ("Orders", "Kitchen")
      $mhLink      the Link, for the name under it
      $mhSub       optional extra after the page name (a live dot, a range)
      $mhBack      where the arrow goes, and what it is called
      $mhBackLabel
      $mhActions   right-hand slot, already rendered
--}}
<div class="mph">
    <div class="mph-id">
        @if($mhBack ?? null)
            <a href="{{ $mhBack }}" class="mph-back" title="{{ $mhBackLabel ?? 'Back' }}">
                <i class="fas fa-arrow-left"></i>
            </a>
        @endif
        <div class="min-w-0">
            <h1 class="mph-title">
                @if($mhIcon ?? null)<i class="fas {{ $mhIcon }} mph-icon"></i>@endif
                {{ $mhTitle }}
            </h1>
            <p class="mph-sub">
                {{ $mhLink->title ?: $mhLink->alias }}@if(! empty($mhSub)) <span class="mph-dot">·</span> {!! $mhSub !!}@endif
            </p>
        </div>
    </div>

    @if(! empty($mhActions))
        <div class="mph-actions">{!! $mhActions !!}</div>
    @endif
</div>

@once
<style>
    .mph {
        display: flex; align-items: flex-start; justify-content: space-between;
        gap: 16px; flex-wrap: wrap;
        margin-bottom: 18px;
    }
    /* The identity side claims its width before anything wraps, so a long
       restaurant name never squeezes the title to an ellipsis while four
       buttons sit comfortably beside it. */
    .mph-id { display: flex; align-items: flex-start; gap: 12px; flex: 1 1 320px; min-width: 0; }
    .mph-back {
        display: inline-flex; align-items: center; justify-content: center;
        width: 32px; height: 32px; flex: none; margin-top: 2px;
        border-radius: 10px;
        border: 1px solid var(--border-glass);
        color: var(--text-faint);
        text-decoration: none;
        transition: color .15s, border-color .15s;
    }
    .mph-back:hover { color: var(--text-primary); border-color: var(--text-faint); }
    .mph-title {
        font-size: 21px; font-weight: 800; line-height: 1.2;
        color: var(--text-primary);
        display: flex; align-items: center; gap: 9px;
        overflow-wrap: anywhere;
    }
    .mph-icon { font-size: 17px; color: var(--accent, #5c83ff); }
    .mph-sub { font-size: 12.5px; color: var(--text-muted); margin-top: 2px; }
    .mph-dot { opacity: .5; }
    .mph-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

    /* The shared action pill, so a button on Orders and a button on the
       Kitchen are the same object rather than two that look nearly alike. */
    .mph-btn {
        display: inline-flex; align-items: center; gap: 7px;
        font-size: 12.5px; font-weight: 600;
        color: var(--text-muted);
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        border-radius: 999px;
        padding: 7px 14px;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
        transition: color .15s, border-color .15s;
    }
    .mph-btn:hover { color: var(--text-primary); }
    .mph-btn-warn { color: #f59e0b; border-color: rgba(245,158,11,.35); }
    /* Sana, 2026-10-05: "need direct button to kitchen order". Beside the
       page name, where a screen you GO to belongs -- not in the row of
       files you take away, where it read as a third export format. */
    .mph-btn-kitchen {
        color: #fb923c;
        border-color: rgba(251,146,60,.35);
        background: rgba(251,146,60,.07);
    }
    .mph-btn-kitchen:hover { color: #fdba74; }
    .ro-dot { width:8px; height:8px; border-radius:50%; background:#10b981; animation:ropulse 1.6s infinite; margin-right:2px; }
    @keyframes ropulse { 0%,100%{opacity:1}50%{opacity:.3} }
    .mph-stat { font-weight: 500; }
    .mph-stat b { color: var(--text-primary); font-weight: 700; }

    @media (max-width: 640px) {
        .mph-actions { width: 100%; }
        .mph-title { font-size: 19px; }
    }
</style>
@endonce
