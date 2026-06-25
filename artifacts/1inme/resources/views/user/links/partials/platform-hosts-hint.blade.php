@php
    /**
     * Small helper line listing the additional platform hosts a short
     * link is also reachable on (e.g. Replit dev domain, deployed
     * *.replit.app) alongside the host already shown above.
     *
     * Usage:
     *   @include('user.links.partials.platform-hosts-hint', [
     *       'primary' => $aliasHost,
     *       'alias'   => $link->alias, // optional, used for examples
     *   ])
     */
    use App\Modules\Common\Support\PlatformHosts;
    $primary = $primary ?? null;
    $alias   = $alias ?? null;
    $others  = PlatformHosts::others($primary);
@endphp
@if(!empty($others))
    <div class="mt-2 flex items-start gap-2 text-[11px]" style="color: var(--text-faint, rgba(255,255,255,0.4));">
        <i class="fas fa-globe mt-0.5 text-blue-400/70"></i>
        <div class="min-w-0">
            <span>Also live on:</span>
            @foreach($others as $h)
                <span class="inline-flex items-center px-1.5 py-0.5 ml-1 rounded font-mono text-[10px]"
                      style="background: rgba(61,107,255,0.08); color: #90acff; border: 1px solid rgba(61,107,255,0.18);"
                      title="{{ $alias ? $h.'/'.$alias : $h }}">{{ $h }}</span>
            @endforeach
        </div>
    </div>
@endif
