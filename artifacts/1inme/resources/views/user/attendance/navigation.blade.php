
<nav class="att-tabs" aria-label="{{ $area === 'leave' ? 'Leave management' : ($area === 'team' ? 'Workspace team' : 'Attendance') }} sections">
@if($area === 'team' && !$isInternal && $directoryAccess)<a href="{{ route('user.team.index') }}">Members &amp; invitations</a>@endif
@foreach($tabs as $key => $label)
<a href="{{ ($area === 'team' && !$isInternal ? route('user.team.people', ['tab'=>$key]) : route($routePrefix.($area === 'attendance' ? '.index' : '.'.$area), ['tab'=>$key])) }}" @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
@endforeach
</nav>
