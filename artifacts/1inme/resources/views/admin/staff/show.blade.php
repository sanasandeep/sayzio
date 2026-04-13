@extends('admin.layouts.app')
@section('title', $staff->name)

@section('content')
<div class="flex items-center gap-4 mb-6">
    <a href="{{ route('admin.staff.index') }}" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-2xl font-bold text-gray-900">{{ $staff->name }}</h1>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <dl class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
        <div>
            <dt class="text-gray-500">Email</dt>
            <dd class="text-gray-900 mt-1">{{ $staff->email }}</dd>
        </div>
        <div>
            <dt class="text-gray-500">Role</dt>
            <dd class="text-gray-900 mt-1">{{ $staff->role->name ?? 'None' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500">Status</dt>
            <dd class="mt-1"><span class="px-2 py-1 rounded text-xs {{ $staff->status === 'active' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">{{ ucfirst($staff->status) }}</span></dd>
        </div>
        <div>
            <dt class="text-gray-500">Last Login</dt>
            <dd class="text-gray-900 mt-1">{{ $staff->last_login_at?->format('M d, Y H:i') ?? 'Never' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500">Created</dt>
            <dd class="text-gray-900 mt-1">{{ $staff->created_at->format('M d, Y H:i') }}</dd>
        </div>
    </dl>
    <div class="mt-6 flex gap-3">
        <a href="{{ route('admin.staff.edit', $staff) }}" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Edit</a>
        <a href="{{ route('admin.staff.index') }}" class="text-gray-600 hover:text-gray-800 px-4 py-2 text-sm">Back to list</a>
    </div>
</div>
@endsection
