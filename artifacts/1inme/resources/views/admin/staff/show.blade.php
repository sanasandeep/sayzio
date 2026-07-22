@extends('admin.layouts.app')
@section('title', $staff->name)

@section('content')
<div class="flex items-center gap-4 mb-6">
    <a href="{{ route('admin.staff.index') }}" class="text-white/30 hover:text-white/50 ak-note"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-2xl font-bold text-white ak-strong">{{ $staff->name }}</h1>
</div>

<div class="glass rounded-2xl p-6">
    <dl class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
        <div>
            <dt class="text-white/40 ak-note">Email</dt>
            <dd class="text-white mt-1 ak-strong">{{ $staff->email }}</dd>
        </div>
        <div>
            <dt class="text-white/40 ak-note">Role</dt>
            <dd class="text-white mt-1 ak-strong">{{ $staff->role->name ?? 'None' }}</dd>
        </div>
        <div>
            <dt class="text-white/40 ak-note">Status</dt>
            <dd class="mt-1"><span class="px-2 py-1 rounded text-xs {{ $staff->status === 'active' ? 'bg-emerald-500/10 text-emerald-400 ak-green' : 'bg-red-500/10 text-red-400 ak-red' }}">{{ ucfirst($staff->status) }}</span></dd>
        </div>
        <div>
            <dt class="text-white/40 ak-note">Last Login</dt>
            <dd class="text-white mt-1 ak-strong">{{ $staff->last_login_at?->format('M d, Y H:i') ?? 'Never' }}</dd>
        </div>
        <div>
            <dt class="text-white/40 ak-note">Created</dt>
            <dd class="text-white mt-1 ak-strong">{{ $staff->created_at->format('M d, Y H:i') }}</dd>
        </div>
    </dl>
    <div class="mt-6 flex gap-3">
        <a href="{{ route('admin.staff.edit', $staff) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-sm font-medium">Edit</a>
        <a href="{{ route('admin.staff.index') }}" class="text-white/50 hover:text-white px-4 py-2 text-sm ak-muted">Back to list</a>
    </div>
</div>
@endsection
