<span hidden data-list-history-scope="{{ auth()->id() }}.{{ (app()->bound('current_workspace') ? app('current_workspace')->id : 'personal') }}"></span>
<style>
.app-filter-surface { display:block!important; width:100%; min-width:0; padding:14px!important; margin-bottom:16px; background:var(--bg-card)!important; border:1px solid var(--border-soft)!important; border-radius:16px!important; }
.app-search-bar { display:flex; align-items:center; gap:8px; padding:6px; min-width:0; border:1px solid var(--border-soft); border-radius:28px; background:var(--bg-glass-hover); }
.app-filter-toggle { display:grid; place-items:center; width:40px; height:40px; flex:none; border-radius:50%; background:var(--bg-card); color:var(--text-muted); font-size:22px; }
.app-search-bar .app-search-input { flex:1; width:0!important; min-width:0!important; max-width:none!important; height:40px; padding:8px!important; border:0!important; background:transparent!important; color:var(--text-primary)!important; font-size:13px; }
.app-filter-title { flex:1; font-size:13px; color:var(--text-muted); }
.app-filter-submit { min-height:40px; flex:none; border-radius:22px; padding:0 18px; font-size:12px; color:white; background:linear-gradient(135deg,var(--accent),var(--accent-light)); }
.app-filter-panel { display:flex; align-items:end; flex-wrap:wrap; gap:12px; margin-top:14px; padding:14px; border:1px solid var(--border-soft); border-radius:14px; }
.app-filter-panel > * { max-width:100%; min-width:0!important; }
.app-filter-panel select,.app-filter-panel input:not([type=hidden]):not([type=checkbox]) { min-height:42px; border-radius:10px; font-size:12px; }
.app-filter-panel label { display:flex; flex-direction:column; gap:6px; color:var(--text-muted); font-size:11px; }
.app-filter-panel button[type=submit],.app-filter-panel button:not([type]) { border:0!important; min-height:40px; padding:0 16px; border-radius:20px; background:linear-gradient(135deg,var(--accent),var(--accent-light))!important; color:white!important; font-size:12px; }
.app-filter-panel select { max-width:100%; }
.app-filter-surface [hidden] { display:none!important; }
.app-filter-tags { display:flex; align-items:center; flex-wrap:wrap; gap:8px; margin-top:12px; }
.app-filter-tag { display:inline-flex; align-items:center; min-height:34px; padding:7px 11px; border:1px solid var(--border-soft); border-radius:18px; background:var(--bg-glass-hover); color:var(--text-secondary); font-size:11px; overflow-wrap:anywhere; max-width:100%; }
.app-filter-clear { color:var(--accent); font-size:11px; padding:8px; }
.app-search-recent { border:1px solid var(--border-soft); border-radius:14px; padding:8px; margin-top:8px; }
.app-recent-heading { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:4px 8px; font-size:11px; color:var(--text-faint); }
.app-recent-row { display:flex; border-radius:9px; }
.app-recent-row:hover { background:var(--bg-glass-hover); }
.app-recent-term { text-align:left; flex:1; min-width:0; overflow-wrap:anywhere; padding:10px; font-size:12px; color:var(--text-secondary); }
.app-recent-remove { width:40px; min-height:40px; color:var(--text-muted); }
.app-filter-surface button:focus-visible,.app-filter-surface a:focus-visible { outline:2px solid var(--accent); outline-offset:2px; }
.app-collection-table { border-collapse:separate!important; border-spacing:0 8px!important; width:100%; }
.app-collection-table tbody td { background:var(--bg-card); border-top:1px solid var(--border-soft)!important; border-bottom:1px solid var(--border-soft)!important; padding:14px 12px!important; vertical-align:middle; }
.app-collection-table tbody td:first-child { border-left:1px solid var(--border-soft); border-radius:14px 0 0 14px; }
.app-collection-table tbody td:last-child { border-right:1px solid var(--border-soft); border-radius:0 14px 14px 0; }
.app-collection-table tbody tr:hover td { background:var(--bg-glass-hover); }
.app-collection-table thead th { color:var(--text-faint); font-size:10px; font-weight:600; padding:10px 12px; }
.app-collection-table input[type=checkbox] { width:20px; height:20px; accent-color:var(--accent); cursor:pointer; }
.app-collection-card { border:1px solid var(--border-soft)!important; border-radius:14px!important; background:var(--bg-card); box-shadow:none!important; }
@media(max-width:700px) { .app-filter-surface { padding:12px!important; } .app-filter-panel { gap:10px; padding:12px; } .app-filter-panel > div,.app-filter-panel > label { flex:1 1 calc(50% - 10px); } .app-filter-submit { padding:0 12px; } .app-collection-table tbody td { padding:12px 9px!important; } }
[data-date-filters]:not(.app-filter-surface) { display:flex; flex-wrap:wrap; align-items:end; gap:10px; padding:14px; border:1px solid var(--border-soft); border-radius:14px; background:var(--bg-card); }
[data-date-filters] label { display:flex; flex-direction:column; gap:5px; font-size:12px; color:var(--text-muted); }
[data-date-filters] input[type=date] { min-width:0; max-width:100%; padding:9px 12px; border:1px solid var(--border-soft); border-radius:10px; background:var(--bg-glass-input); color:var(--text-primary); }
@media(max-width:640px) { [data-date-filters] { width:100%; } [data-date-filters] label { flex:1 1 120px; min-width:0; } }
[data-date-filters].app-filter-surface { width:100%; margin-left:0; }
[data-date-filters] .app-filter-title { min-width:0; overflow-wrap:anywhere; }
[data-date-filters] .app-search-bar { flex-wrap:wrap; }
</style>
<script src="{{ asset('js/list-design.js') }}?v=3" defer></script>
