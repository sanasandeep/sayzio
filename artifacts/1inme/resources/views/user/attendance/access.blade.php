<section class="card">
<h2>{{ $isInternal ? 'Internal staff roles & permissions' : 'Workspace team roles & permissions' }}</h2>
<p>{{ $isInternal ? 'These permissions apply to Sayzio internal staff only.' : 'These permissions apply to members of this workspace only.' }}</p>
<div class="space">
@foreach(['create'=>'Record attendance and submit personal requests', 'review'=>'Review team attendance and requests', 'manage'=>'Manage attendance policies, rosters and leave types', 'export'=>'Export attendance reports'] as $ability=>$label)
<div class="att-access-list"><span>{{ $label }}</span><span class="badge">{{ $accessPermissions[$ability] ? 'Allowed' : 'Not granted' }}</span></div>
@endforeach
</div>
@if($mayManageRoles)<a class="btn-primary space" href="{{ route($isInternal ? 'admin.roles.index' : 'user.team.roles.index') }}">{{ $isInternal ? 'Manage staff roles' : 'Manage team roles' }}</a>@endif
@if($directoryAccess)<a class="btn-ghost space" href="{{ route($isInternal ? 'admin.staff.index' : 'user.team.index') }}">{{ $isInternal ? 'Manage internal staff' : 'Manage workspace team' }}</a>@endif
</section>
