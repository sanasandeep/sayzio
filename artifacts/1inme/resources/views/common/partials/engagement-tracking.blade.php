{{-- Reusable engagement tracking: page session + heartbeat (no block observer).
     Mirrors the session/heartbeat behaviour used on biolink pages so dwell
     time, geo/device, and language data are captured for any rendered page
     (file download page, url/ics/vcf preview pages, etc).
     Requires: $link in scope (used to derive the alias for tracking URLs). --}}
<script>
(function(){
    // Under the browser E2E flag, skip tracking beacons so a preview/render
    // doesn't saturate the local PHP-CLI workers during the test suite. No
    // effect in production, where __E2E__ is never set.
    if (window.__E2E__) return;
    var ALIAS = @json($link->_used_alias ?? $link->alias);
    var startUrl = '/' + ALIAS + '/track/session';
    var hbUrl    = '/' + ALIAS + '/track/heartbeat';
    var sessionId = null;
    var lastActive = Date.now();
    var totalActiveMs = 0;
    var hidden = false;

    function tickActive(){
        if(!hidden){
            var n = Date.now();
            totalActiveMs += (n - lastActive);
            lastActive = n;
        }
    }
    function activeSeconds(){ return Math.floor(totalActiveMs / 1000); }

    function sendHeartbeat(final){
        if(!sessionId) return;
        tickActive();
        var payload = JSON.stringify({
            session_id: sessionId,
            duration_seconds: activeSeconds(),
            ended: !!final,
            block_views: []
        });
        try {
            if(final && navigator.sendBeacon){
                var blob = new Blob([payload], {type: 'application/json'});
                navigator.sendBeacon(hbUrl, blob);
            } else {
                fetch(hbUrl, {method:'POST', headers:{'Content-Type':'application/json'}, body: payload, keepalive: true});
            }
        } catch(e){}
    }

    function startSession(){
        fetch(startUrl, {method:'POST', headers:{'Content-Type':'application/json'}, body: '{}'})
            .then(function(r){ return r.json(); })
            .then(function(d){ if(d && d.session_id){ sessionId = d.session_id; } })
            .catch(function(){});
    }

    document.addEventListener('visibilitychange', function(){
        if(document.hidden){ tickActive(); hidden = true; sendHeartbeat(false); }
        else { hidden = false; lastActive = Date.now(); }
    });
    window.addEventListener('pagehide', function(){ sendHeartbeat(true); });
    window.addEventListener('beforeunload', function(){ sendHeartbeat(true); });
    setInterval(function(){ sendHeartbeat(false); }, 15000);

    if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', startSession);
    } else {
        startSession();
    }
})();
</script>
