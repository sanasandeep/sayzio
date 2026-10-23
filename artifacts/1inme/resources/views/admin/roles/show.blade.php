@extends('admin.layouts.app')
@section('title', $role->name)

@section('content')
<div class="flex items-center gap-4 mb-6">
    <a href="{{ route('admin.roles.index') }}" class="text-white/30 hover:text-white/50 ak-note"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-2xl font-bold text-white ak-strong">{{ $role->name }}</h1>
</div>

<div class="glass rounded-2xl p-6 mb-6">
    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div>
            <dt class="text-white/40 ak-note">Slug</dt>
            <dd class="text-white mt-1 font-mono ak-strong">{{ $role->slug }}</dd>
        </div>
        <div>
            <dt class="text-white/40 ak-note">Guard</dt>
            <dd class="text-white mt-1 ak-strong">{{ $role->guard ?? 'admin' }}</dd>
        </div>
        @if($role->description)
        <div class="md:col-span-2">
            <dt class="text-white/40 ak-note">Description</dt>
            <dd class="text-white mt-1 ak-strong">{{ $role->description }}</dd>
        </div>
        @endif
    </dl>
</div>

<div class="glass rounded-2xl p-6">
    <h2 class="text-lg font-semibold text-white mb-4 ak-strong">Permissions {{ $role->slug === 'super-admin' ? '(unrestricted)' : '('.$role->permissions->count().')' }}</h2>
    @if($role->slug === 'super-admin')
        <p class="text-red-400 ak-red font-semibold">Critical · Unrestricted access to all current and future permissions.</p>
    @elseif($role->permissions->isEmpty())
        <p class="text-white/40 text-sm ak-note">No permissions assigned.</p>
    @else
        <div class="flex flex-wrap gap-2">
            @foreach($role->permissions->groupBy('group') as $group => $perms)
                @foreach($perms as $perm)
                    <span class="bg-white/10 px-2.5 py-1 rounded text-xs {{ \App\Modules\Admin\Support\StaffRoleTools::risk($perm->slug) === 4 ? 'text-red-400 ak-red font-semibold' : 'text-white/60 ak-muted' }}">{{ $perm->name }} · {{ \App\Modules\Admin\Support\StaffRoleTools::labels()[\App\Modules\Admin\Support\StaffRoleTools::risk($perm->slug)] }}</span>
                @endforeach
            @endforeach
        </div>
    @endif
    <div class="mt-6 flex flex-wrap gap-3">
        @if(\App\Modules\Admin\Support\StaffRoleTools::canManage(auth('admin')->user(), $role))
        <a href="{{ route('admin.roles.edit', $role) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-sm font-medium">Edit</a>
        <a href="{{ route('admin.roles.duplicate', $role) }}" class="ak-blue px-4 py-2">Copy / duplicate</a>
        @endif
        <a href="{{ route('admin.roles.index') }}" class="text-white/50 hover:text-white px-4 py-2 text-sm ak-muted">Back to list</a>
    </div>
</div>
@endsection
