@extends('admin.layouts.app')
@section('title', 'User Details')
@section('page-title', 'User: ' . $user->name)

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl border border-dark-200 shadow-sm p-6">
            <div class="text-center">
                <div class="w-20 h-20 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-2xl font-bold mx-auto">
                    {{ substr($user->name, 0, 1) }}
                </div>
                <h2 class="mt-4 text-lg font-semibold text-dark-800">{{ $user->name }}</h2>
                <p class="text-sm text-dark-500">{{ $user->email }}</p>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-2
                    {{ $user->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    {{ ucfirst($user->status) }}
                </span>
            </div>

            <div class="mt-6 space-y-3 text-sm">
                <div class="flex justify-between"><span class="text-dark-500">Phone</span><span class="text-dark-800">{{ $user->phone ?? 'N/A' }}</span></div>
                <div class="flex justify-between"><span class="text-dark-500">Plan</span><span class="text-dark-800">{{ $user->plan->name ?? 'Free' }}</span></div>
                <div class="flex justify-between"><span class="text-dark-500">Joined</span><span class="text-dark-800">{{ $user->created_at->format('M d, Y') }}</span></div>
                <div class="flex justify-between"><span class="text-dark-500">Last Login</span><span class="text-dark-800">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</span></div>
                <div class="flex justify-between"><span class="text-dark-500">Timezone</span><span class="text-dark-800">{{ $user->timezone }}</span></div>
            </div>

            <div class="mt-6 flex gap-2">
                <form action="{{ route('admin.users.impersonate', $user) }}" method="POST" class="flex-1">
                    @csrf
                    <button type="submit" class="w-full px-4 py-2 bg-orange-50 text-orange-700 rounded-lg text-sm font-medium hover:bg-orange-100 transition">
                        <i class="fas fa-user-secret mr-1"></i> Login as User
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl border border-dark-200 shadow-sm p-6">
            <h3 class="text-lg font-semibold text-dark-800 mb-4">Edit User</h3>
            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @csrf @method('PUT')
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-dark-700 mb-1">Name</label>
                        <input type="text" name="name" value="{{ $user->name }}"
                               class="w-full px-4 py-2.5 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-dark-700 mb-1">Status</label>
                            <select name="status" class="w-full px-4 py-2.5 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                                <option value="active" {{ $user->status == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ $user->status == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                <option value="banned" {{ $user->status == 'banned' ? 'selected' : '' }}>Banned</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-dark-700 mb-1">Plan</label>
                            <select name="plan_id" class="w-full px-4 py-2.5 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                                <option value="">No Plan</option>
                                @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}" {{ $user->plan_id == $plan->id ? 'selected' : '' }}>{{ $plan->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-dark-700 mb-1">Plan Expires At</label>
                        <input type="datetime-local" name="plan_expires_at" value="{{ $user->plan_expires_at?->format('Y-m-d\TH:i') }}"
                               class="w-full px-4 py-2.5 border border-dark-300 rounded-lg focus:ring-2 focus:ring-primary-500 outline-none">
                    </div>
                    <button type="submit" class="px-6 py-2.5 bg-primary-600 text-white rounded-lg font-medium hover:bg-primary-700 transition">
                        Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
