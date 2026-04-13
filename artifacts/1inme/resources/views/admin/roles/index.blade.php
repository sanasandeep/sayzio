@extends('admin.layouts.app')
@section('title', 'Roles')
@section('page-title', 'Roles & Permissions')

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-dark-500">Manage staff roles and their permissions</p>
    <a href="{{ route('admin.roles.create') }}" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition">
        <i class="fas fa-plus mr-2"></i>Add Role
    </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach($roles as $role)
    <div class="bg-white rounded-xl border border-dark-200 shadow-sm p-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-dark-800">{{ $role->name }}</h3>
            @if($role->slug === 'super-admin')
                <span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full">System</span>
            @endif
        </div>
        <p class="text-sm text-dark-500 mb-4">{{ $role->description ?? 'No description' }}</p>
        <div class="flex items-center justify-between text-sm">
            <div class="flex items-center gap-4 text-dark-400">
                <span><i class="fas fa-users mr-1"></i>{{ $role->admins_count }} staff</span>
                <span><i class="fas fa-key mr-1"></i>{{ $role->permissions_count }} permissions</span>
            </div>
            @if($role->slug !== 'super-admin')
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.roles.edit', $role) }}" class="text-dark-400 hover:text-primary-600"><i class="fas fa-edit"></i></a>
                <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-dark-400 hover:text-red-600"><i class="fas fa-trash"></i></button>
                </form>
            </div>
            @endif
        </div>
    </div>
    @endforeach
</div>
@endsection
