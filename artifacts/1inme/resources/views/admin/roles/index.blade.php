@extends('admin.layouts.app')
@section('title', 'Roles')
@section('page-title', 'Roles & Permissions')

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-white/40">Manage staff roles and their permissions</p>
    <a href="{{ route('admin.roles.create') }}" class="px-4 py-2 bg-purple-600 text-white rounded-xl text-sm font-medium hover:bg-purple-700 transition">
        <i class="fas fa-plus mr-2"></i>Add Role
    </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach($roles as $role)
    <div class="glass rounded-2xl border border-white/10  p-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-white">{{ $role->name }}</h3>
            @if($role->slug === 'super-admin')
                <span class="text-xs bg-purple-500/10 text-purple-400 px-2 py-0.5 rounded-full">System</span>
            @endif
        </div>
        <p class="text-sm text-white/40 mb-4">{{ $role->description ?? 'No description' }}</p>
        <div class="flex items-center justify-between text-sm">
            <div class="flex items-center gap-4 text-white/30">
                <span><i class="fas fa-users mr-1"></i>{{ $role->admins_count }} staff</span>
                <span><i class="fas fa-key mr-1"></i>{{ $role->permissions_count }} permissions</span>
            </div>
            @if($role->slug !== 'super-admin')
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.roles.edit', $role) }}" class="text-white/30 hover:text-purple-400"><i class="fas fa-edit"></i></a>
                <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-white/30 hover:text-red-400"><i class="fas fa-trash"></i></button>
                </form>
            </div>
            @endif
        </div>
    </div>
    @endforeach
</div>
@endsection
