<div class="kpi-strip mb-6">
    <div class="kpi-cell">
        <div class="kpi-cell-head"><i class="fas fa-eye"></i> Page visits</div>
        @include('user.partials.count-card-art',['kind'=>'visitors','artId'=>'secondary-page-visits'])
        <div class="kpi-cell-value">{{ number_format($pageVisitsInRange) }}</div>
        <div class="kpi-cell-note">Selected period</div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-cell-head"><i class="fas fa-th-large"></i> Block clicks</div>
        @include('user.partials.count-card-art',['kind'=>'clicks','artId'=>'secondary-block-clicks'])
        <div class="kpi-cell-value">{{ number_format($blockClicksInRange) }}</div>
        <div class="kpi-cell-note">Tracked content interactions</div>
    </div>
    @if($hasPollBlocks || $pollVotesInRange > 0)
    <a href="#poll-engagement" class="kpi-cell kpi-cell-link"
       title="Total poll responses across all poll blocks on this link in the selected date range. Click to jump to the per-poll breakdown.">
        <div class="kpi-cell-head"><i class="fas fa-square-poll-vertical"></i> Poll votes</div>
        @include('user.partials.count-card-art',['kind'=>'pages','artId'=>'secondary-poll-votes'])
        <div class="kpi-cell-value">{{ number_format($pollVotesInRange) }}</div>
        <div class="kpi-cell-note">Recorded poll responses</div>
    </a>
    @endif
    @if($link->type === 'text')
    <div class="kpi-cell"
         title="Times a visitor downloaded this text page as a .txt file. Same bot filtering as all other numbers.">
        <div class="kpi-cell-head"><i class="fas fa-download"></i> Downloads</div>
        @include('user.partials.count-card-art',['kind'=>'pages','artId'=>'secondary-downloads'])
        <div class="kpi-cell-value">{{ number_format($txtDownloadsInRange) }}</div>
        <div class="kpi-cell-note">Text file downloads</div>
    </div>
    @if($txtRawInRange > 0)
    <div class="kpi-cell"
         title="Plain-text fetches of this page via its /raw URL (usually scripts or programmatic readers).">
        <div class="kpi-cell-head"><i class="fas fa-code"></i> Raw fetches</div>
        @include('user.partials.count-card-art',['kind'=>'pages','artId'=>'secondary-raw-fetches'])
        <div class="kpi-cell-value">{{ number_format($txtRawInRange) }}</div>
        <div class="kpi-cell-note">Plain-text requests</div>
    </div>
    @endif
    @endif
    <div class="kpi-cell">
        <div class="kpi-cell-head"><i class="fas fa-hourglass-half"></i> Engaged time</div>
        @include('user.partials.count-card-art',['kind'=>'sessions','artId'=>'secondary-engaged-time'])
        <div class="kpi-cell-value">{{ $totalSessions > 0 ? _fmtSecs($totalEngagedSeconds) : '—' }}</div>
        <div class="kpi-cell-note">{{ $totalSessions > 0 ? 'Total tracked session time' : 'No sessions tracked' }}</div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-cell-head"><i class="fas fa-running"></i> Bounce rate</div>
        @include('user.partials.count-card-art',['kind'=>'sessions','artId'=>'secondary-bounce-rate'])
        <div class="kpi-cell-value">{{ $totalSessions > 0 ? $bounceRate.'%' : '—' }}</div>
        <div class="kpi-cell-note">{{ $totalSessions > 0 ? 'Sessions under 5 seconds' : 'No sessions tracked' }}</div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-cell-head"><i class="fas fa-stopwatch"></i> Avg. time</div>
        @include('user.partials.count-card-art',['kind'=>'sessions','artId'=>'secondary-avg-time'])
        <div class="kpi-cell-value">{{ $totalSessions > 0 ? _fmtSecs($avgSessionSeconds) : '—' }}</div>
        <div class="kpi-cell-note">{{ $totalSessions > 0 ? 'Per tracked session' : 'No sessions tracked' }}</div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-cell-head"><i class="fas fa-infinity"></i> All-time clicks</div>
        @include('user.partials.count-card-art',['kind'=>'clicks','artId'=>'secondary-all-time-clicks'])
        <div class="kpi-cell-value">{{ number_format($link->total_clicks) }}</div>
        <div class="kpi-cell-note">All retained traffic</div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-cell-head"><i class="fas fa-users"></i> All-time addresses</div>
        @include('user.partials.count-card-art',['kind'=>'visitors','artId'=>'secondary-all-time-addresses'])
        <div class="kpi-cell-value">{{ number_format($link->unique_clicks) }}</div>
        <div class="kpi-cell-note">Distinct IP addresses</div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-cell-head"><i class="fas fa-percentage"></i> Visitor ratio</div>
        @include('user.partials.count-card-art',['kind'=>'visitors','artId'=>'secondary-visitor-ratio'])
        <div class="kpi-cell-value">{{ $totalInRange > 0 ? round(($uniqueInRange / $totalInRange) * 100).'%' : '—' }}</div>
        <div class="kpi-cell-note">Distinct addresses / clicks</div>
    </div>
    @if($botClicksInRange > 0)
    <button type="button"
       class="kpi-cell kpi-cell-link text-left"
       onclick="(function(el){var p=document.getElementById('bot-breakdown-panel');if(!p)return;p.hidden=!p.hidden;el.setAttribute('aria-expanded', p.hidden ? 'false' : 'true');if(!p.hidden){p.scrollIntoView({behavior:'smooth',block:'nearest'});}})(this)"
       aria-expanded="false"
       aria-controls="bot-breakdown-panel"
       title="We exclude obvious crawlers like Googlebot, AhrefsBot, headless Chrome, etc. from your totals so your numbers reflect real humans. Click to see which bots are hitting this link.">
        <div class="kpi-cell-head"><i class="fas fa-robot"></i> Bot hits filtered</div>
        @include('user.partials.count-card-art',['kind'=>'bot','artId'=>'secondary-bot-hits-filtered'])
        <div class="kpi-cell-value">{{ number_format($botClicksInRange) }} <i class="fas fa-chevron-down text-[10px] ml-1" style="color: #e7edff;"></i></div>
        <div class="kpi-cell-note">Excluded from traffic totals</div>
    </button>
    @endif
</div>
