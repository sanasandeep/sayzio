<nav class="att-area-nav" aria-label="People management">
<a class="{{ $area === 'attendance' ? 'btn-primary' : 'btn-ghost' }}" href="{{ route($routePrefix.'.index') }}" @if($area === 'attendance') aria-current="page" @endif>Attendance</a>
<a class="{{ $area === 'leave' ? 'btn-primary' : 'btn-ghost' }}" href="{{ route($routePrefix.'.leave') }}" @if($area === 'leave') aria-current="page" @endif>Leave Management</a>
<a class="{{ $area === 'team' ? 'btn-primary' : 'btn-ghost' }}" href="{{ route($routePrefix.'.team') }}" @if($area === 'team') aria-current="page" @endif>{{ $isInternal ? 'Staff Management' : 'Team Management' }}</a>
</nav>
<nav class="att-tabs" aria-label="{{ $area === 'leave' ? 'Leave management' : 'Attendance' }} sections">
@foreach($tabs as $key => $label)
<a href="{{ route($routePrefix.($area === 'attendance' ? '.index' : '.'.$area), ['tab'=>$key]) }}" @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
@endforeach
</nav>
