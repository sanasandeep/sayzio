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
