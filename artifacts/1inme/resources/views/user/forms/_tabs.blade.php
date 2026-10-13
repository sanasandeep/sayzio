@php
    $tabs = [
        'show'         => ['icon' => 'fa-chart-line', 'label' => 'Overview',     'route' => 'user.forms.show'],
        'reports' => ['icon'=>'fa-chart-pie','label'=>'Reports','route'=>'user.forms.reports'],
        'builder'      => ['icon' => 'fa-pen-ruler',  'label' => 'Build',        'route' => 'user.forms.builder'],
        'design'       => ['icon' => 'fa-palette',    'label' => 'Design',       'route' => 'user.forms.design'],
        'notifications'=> ['icon' => 'fa-bell',       'label' => 'Notifications','route' => 'user.forms.notifications'],
        'payment'      => ['icon' => 'fa-credit-card','label' => 'Payments',     'route' => 'user.forms.payment'],
        'settings'     => ['icon' => 'fa-shield-halved','label' => 'Settings',   'route' => 'user.forms.settings'],
        'submissions'  => ['icon' => 'fa-inbox',      'label' => 'Submissions',  'route' => 'user.forms.submissions'],
        'embed'        => ['icon' => 'fa-code',       'label' => 'Share / Embed','route' => 'user.forms.embed'],
    ];
@endphp
<div class="card-premium p-1 mb-6 flex items-center gap-1 flex-wrap forms-tabs">
    @foreach($tabs as $key => $t)
        @php $active = request()->routeIs($t['route']); @endphp
        <a href="{{ ($key==='reports'&&!workspace_owner()?->getPlanFeature('form_analytics_advanced',false))?route('user.upgrade'):route($t['route'], $form) }}"
           class="flex-shrink-0 inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold transition-all whitespace-nowrap"
           style="{{ $active ? 'background: linear-gradient(135deg,#5c83ff,#2342c7); color:white; box-shadow:0 4px 14px -4px rgba(92,131,255,0.5);' : 'color: var(--text-muted);' }}">
            <i class="fas {{ $t['icon'] }} text-[10px]"></i> {{ $t['label'] }}
        </a>
    @endforeach
</div>

<style>
.forms-workspace{width:100%;max-width:1440px;margin-inline:auto;min-width:0;container-type:inline-size}
.forms-workspace .forms-tabs{gap:6px;border-radius:18px}.forms-tabs a:hover{background:var(--bg-glass-input)}
.forms-workspace .theme-input{min-width:0;max-width:100%}.forms-workspace aside{min-width:0}
.forms-preview{position:sticky;top:100px;align-self:start;min-width:0}.forms-preview iframe{display:block;width:100%;border:0;border-radius:18px;background:white;height:min(650px,calc(100vh - 285px))}
.forms-workspace .forms-expanded{position:fixed!important;top:96px;bottom:16px;left:16px;right:16px;max-width:1000px;margin:auto;z-index:100;overflow:auto;box-shadow:0 0 0 100vmax rgba(15,23,42,.4);border-radius:20px}.forms-expanded iframe{height:calc(100vh - 285px)!important;min-height:260px}.forms-preview.is-phone iframe{max-width:390px;margin:auto}.forms-preview header{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-bottom:12px}
.forms-workspace pre{white-space:pre-wrap;overflow-wrap:anywhere}.forms-workspace .form-chip{border-radius:999px;padding:8px 12px;background:var(--bg-glass-input);color:var(--text-primary);border:1px solid var(--border-glass)}
.forms-workspace .form-chip.active{background:#3155eb;color:#fff}.forms-workspace .form-insert{display:flex;justify-content:center;padding:6px;position:relative}.form-insert button{border:1px solid var(--border-glass);border-radius:999px;padding:4px 12px;color:#3155eb;background:var(--bg-card)}
@media(max-width:1023px){.forms-preview{position:static}.forms-preview iframe{height:560px}.forms-workspace [style*="margin-left: 3.25rem"]{margin-left:0!important}}
@media(max-width:640px){.forms-tabs a{flex:1 1 110px;justify-content:center}.forms-workspace .p-6{padding:16px}.forms-workspace .ml-13{margin-left:0}.forms-workspace .grid-cols-2{grid-template-columns:1fr}.forms-workspace .field-card{padding:14px}}
@container(max-width:800px){.forms-workspace .form-studio-grid{display:flex;flex-direction:column}.forms-workspace .form-studio-grid>aside,.forms-workspace .form-studio-grid>main{width:100%}}
</style>

<style>
.forms-selection-bar{position:fixed;z-index:60;bottom:24px;left:50%;transform:translateX(-50%);display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;width:max-content;max-width:calc(100vw - 24px);padding:12px 16px;background:var(--bg-card,#fff);color:var(--text-primary);border:1px solid #8a9fff;border-radius:18px;box-shadow:0 16px 40px #17264b33}
.forms-selection-more{position:absolute;bottom:70px;left:0;display:flex;gap:8px;flex-wrap:wrap;background:var(--bg-card,#fff);padding:16px;border:1px solid var(--border-glass);border-radius:16px;width:min(600px,calc(100vw - 30px))}
.forms-field-selected{border:2px solid #5573ff;border-radius:20px;padding:8px;background:#5573ff08}.forms-selection-bar~#fieldsList{padding-bottom:100px}
.forms-rich-stat{position:relative;overflow:hidden;border-radius:24px;padding:24px;min-height:178px;color:#fff;border:1px solid #ffffff40;box-shadow:inset 0 2px 0 #ffffff40,0 16px 24px -18px #14275b99}
.forms-rich-stat .stat-art{position:absolute;right:20px;top:38px;width:80px;height:80px;filter:drop-shadow(0 6px 0 #0002);animation:form-stat-float 5s ease-in-out infinite}
.forms-rich-stat .stat-value{font-size:44px;font-weight:800;line-height:1.1;margin-top:38px;position:relative}.forms-rich-stat .stat-label{font-size:14px;font-weight:600;position:relative}.forms-rich-stat .stat-caption{font-size:12px;opacity:.85;margin-top:10px}
@keyframes form-stat-float{50%{transform:translateY(-5px) rotate(3deg)}}@media(prefers-reduced-motion:reduce){.forms-rich-stat .stat-art{animation:none}}
@media(max-width:640px){.forms-rich-stat{padding:18px;min-height:155px}.forms-rich-stat .stat-art{width:54px;height:54px;right:12px;top:42px}.forms-rich-stat .stat-value{font-size:32px}.forms-selection-bar{bottom:10px;gap:5px;padding:10px}}
</style>

<style>
.forms-field-unit{position:relative;min-width:0;padding-bottom:20px}.forms-workspace .forms-field-unit.forms-field-selected{padding:0 0 20px;background:transparent;border:0}.forms-field-unit.forms-field-selected>.field-card{border:2px solid #5573ff!important;box-shadow:0 0 0 1px #5573ff18!important}
.forms-card-select{position:absolute;top:22px;left:14px;z-index:3}.forms-card-select input{appearance:none;width:18px;height:18px;border:2px solid var(--border-glass,#cbd5e1);border-radius:6px;background:var(--bg-card,#fff);cursor:pointer}.forms-card-select input:checked{background:#4567ff;border-color:#4567ff}.forms-card-select input:checked:after{content:'✓';display:block;text-align:center;color:#fff;font-size:12px;line-height:14px}
.forms-field-unit>.field-card{padding-left:44px!important;border-radius:18px 18px 0 0!important;cursor:pointer}.forms-field-detail{margin-top:12px}.forms-card-actions{flex-wrap:wrap;justify-content:flex-end}.forms-card-actions button{color:var(--text-muted)}
.forms-width-strip{display:flex;align-items:center;gap:5px;flex-wrap:wrap;padding:9px 12px;border:1px solid var(--border-glass);border-top:0;border-radius:0 0 18px 18px;background:var(--bg-card,#fff);font-size:10px;color:var(--text-muted)}.forms-width-strip span{margin-right:8px}.forms-width-strip button{padding:3px 7px;border:1px solid transparent;border-radius:5px}.forms-width-strip button[aria-pressed=true]{background:#4567ff16;color:#3155eb;border-color:#4567ff55}.forms-field-selected>.forms-width-strip{border:2px solid #5573ff;border-top:0}
.forms-between-insert{display:flex;align-items:center;gap:10px;position:absolute;bottom:-7px;left:0;right:0;z-index:2}.forms-between-insert:before,.forms-between-insert:after{content:'';height:1px;background:var(--border-glass);flex:1}.forms-between-insert button{width:24px;height:24px;border:1px solid #10b98155;border-radius:50%;color:#10b981;background:var(--bg-card,#fff);font-size:10px}.forms-between-insert button:hover,.forms-between-insert button.active{background:#10b981;color:white}.forms-builder-grid #fieldsList{gap:24px 12px!important;align-items:start}.forms-builder-grid main{min-width:0}
@container(min-width:1100px){.forms-workspace .forms-builder-grid{display:grid;grid-template-columns:minmax(210px,1fr) minmax(360px,1.6fr) minmax(330px,1.4fr);gap:24px}.forms-builder-grid>aside,.forms-builder-grid>main{grid-column:auto!important}.forms-builder-grid>aside:first-child{position:sticky;top:100px;align-self:start}}
@container(max-width:1099px){.forms-workspace .forms-builder-grid{display:flex;flex-direction:column}.forms-builder-grid>aside,.forms-builder-grid>main{width:100%}.forms-builder-grid .forms-field-unit{grid-column:span 12!important}.forms-builder-grid>aside:first-child .space-y-1{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:6px}.forms-builder-grid>aside:first-child .lg\:sticky{position:static;max-height:320px;overflow:auto}}
@media(max-width:640px){.forms-card-actions{gap:0}.forms-builder-grid .forms-field-unit{grid-column:span 12!important}.forms-selection-bar{bottom:12px;padding:8px;gap:5px}.forms-selection-bar .form-chip{padding:6px 9px;font-size:11px}}
</style>

<style>@container(min-width:1100px){.forms-builder-grid>aside:last-child{max-height:calc(100vh - 120px);overflow-y:auto;padding-right:4px}.forms-builder-grid>aside:last-child [x-ref=fieldPanel]{position:static;max-height:none;overflow:visible}.forms-builder-grid>aside:first-child>div{max-height:calc(100vh - 120px)}}@container(max-width:1099px){.forms-builder-grid>aside:last-child{position:static;max-height:none;overflow:visible}}.forms-builder-grid .forms-card-actions button{flex-shrink:0}</style>
