@php
    $tabs = [
        'show'         => ['icon' => 'fa-chart-line', 'label' => 'Overview',     'route' => 'user.forms.show'],
        'builder'      => ['icon' => 'fa-pen-ruler',  'label' => 'Build',        'route' => 'user.forms.builder'],
        'design'       => ['icon' => 'fa-palette',    'label' => 'Design',       'route' => 'user.forms.design'],
        'notifications'=> ['icon' => 'fa-bell',       'label' => 'Notifications','route' => 'user.forms.notifications'],
        'submissions'  => ['icon' => 'fa-inbox',      'label' => 'Submissions',  'route' => 'user.forms.submissions'],
        'embed'        => ['icon' => 'fa-code',       'label' => 'Share / Embed','route' => 'user.forms.embed'],
    ];
@endphp
<div class="card-premium p-1 mb-6 flex items-center gap-1 overflow-x-auto">
    @foreach($tabs as $key => $t)
        @php $active = request()->routeIs($t['route']); @endphp
        <a href="{{ route($t['route'], $form) }}"
           class="flex-shrink-0 inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold transition-all whitespace-nowrap"
           style="{{ $active ? 'background: linear-gradient(135deg,#8b5cf6,#6d28d9); color:white; box-shadow:0 4px 14px -4px rgba(139,92,246,0.5);' : 'color: var(--text-muted);' }}">
            <i class="fas {{ $t['icon'] }} text-[10px]"></i> {{ $t['label'] }}
        </a>
    @endforeach
</div>
