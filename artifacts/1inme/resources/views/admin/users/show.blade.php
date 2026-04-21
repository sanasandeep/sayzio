@extends('admin.layouts.app')
@section('title', 'User Details')
@section('page-title', 'User: ' . $user->name)

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1">
        <div class="glass rounded-2xl border border-white/10  p-6">
            <div class="text-center">
                <div class="w-20 h-20 rounded-full bg-violet-500/10 text-violet-300 flex items-center justify-center text-2xl font-bold mx-auto">
                    {{ substr($user->name, 0, 1) }}
                </div>
                <h2 class="mt-4 text-lg font-semibold text-white">{{ $user->name }}</h2>
                <p class="text-sm text-white/40">{{ $user->email }}</p>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-2
                    {{ $user->status === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' }}">
                    {{ ucfirst($user->status) }}
                </span>
            </div>

            <div class="mt-6 space-y-3 text-sm">
                <div class="flex justify-between"><span class="text-white/40">Phone</span><span class="text-white">{{ $user->phone ?? 'N/A' }}</span></div>
                <div class="flex justify-between"><span class="text-white/40">Plan</span><span class="text-white">{{ $user->plan->name ?? 'Free' }}</span></div>
                <div class="flex justify-between"><span class="text-white/40">Joined</span><span class="text-white">{{ $user->created_at->format('M d, Y') }}</span></div>
                <div class="flex justify-between"><span class="text-white/40">Last Login</span><span class="text-white">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</span></div>
                <div class="flex justify-between"><span class="text-white/40">Timezone</span><span class="text-white">{{ $user->timezone }}</span></div>
            </div>

            <div class="mt-6 flex gap-2">
                <form action="{{ route('admin.users.impersonate', $user) }}" method="POST" class="flex-1">
                    @csrf
                    <button type="submit" class="w-full px-4 py-2 bg-amber-500/10 text-amber-400 rounded-xl text-sm font-medium hover:bg-amber-500/10 transition">
                        <i class="fas fa-user-secret mr-1"></i> Login as User
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="glass rounded-2xl border border-white/10  p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Edit User</h3>
            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @csrf @method('PUT')
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Name</label>
                        <input type="text" name="name" value="{{ $user->name }}"
                               class="w-full px-4 py-2.5 border border-white/10 rounded-xl focus:ring-2 focus:ring-violet-500/40 outline-none">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-white/80 mb-1">Status</label>
                            <select name="status" class="w-full px-4 py-2.5 border border-white/10 rounded-xl focus:ring-2 focus:ring-violet-500/40 outline-none">
                                <option value="active" {{ $user->status == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ $user->status == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                <option value="banned" {{ $user->status == 'banned' ? 'selected' : '' }}>Banned</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-white/80 mb-1">Plan</label>
                            <select name="plan_id" class="w-full px-4 py-2.5 border border-white/10 rounded-xl focus:ring-2 focus:ring-violet-500/40 outline-none">
                                <option value="">No Plan</option>
                                @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}" {{ $user->plan_id == $plan->id ? 'selected' : '' }}>{{ $plan->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Plan Expires At</label>
                        <input type="datetime-local" name="plan_expires_at" value="{{ $user->plan_expires_at?->format('Y-m-d\TH:i') }}"
                               class="w-full px-4 py-2.5 border border-white/10 rounded-xl focus:ring-2 focus:ring-violet-500/40 outline-none">
                    </div>
                    <button type="submit" class="px-6 py-2.5 bg-violet-600 text-white rounded-xl font-medium hover:bg-violet-700 transition">
                        Update User
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if($walletEnabled)
    <div class="glass rounded-2xl border border-white/10 p-6 mt-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-white font-semibold">Wallet</h3>
                <p class="text-xs text-white/40">Coin balance and recent transactions.</p>
            </div>
            <div class="text-2xl font-bold text-amber-300">{{ number_format($wallet->balance) }} 🪙</div>
        </div>

        <form method="POST" action="{{ route('admin.users.wallet.adjust', $user) }}" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6">
            @csrf
            <input type="number" name="delta" placeholder="Δ coins (use - to debit)" required
                   class="px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm">
            <input type="text" name="reason" placeholder="Reason (required)" required
                   class="px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm md:col-span-1">
            <button class="px-4 py-2 bg-violet-600 text-white rounded-xl text-sm font-medium hover:bg-violet-700">Apply adjustment</button>
        </form>

        @if($walletTransactions->isEmpty())
            <p class="text-sm text-white/40">No transactions yet.</p>
        @else
        <table class="w-full text-sm">
            <thead><tr class="text-white/40 text-xs uppercase tracking-wider">
                <th class="text-left py-2">When</th><th class="text-left">Type</th>
                <th class="text-right">Δ Coins</th><th class="text-right">Balance</th>
                <th class="text-left pl-3">Reason</th>
            </tr></thead>
            <tbody>
            @foreach($walletTransactions as $tx)
                <tr class="border-t border-white/5">
                    <td class="py-2 text-white/60">{{ $tx->created_at->diffForHumans() }}</td>
                    <td><span class="px-2 py-0.5 rounded-full text-xs bg-white/10 text-white/70">{{ $tx->type }}</span></td>
                    <td class="text-right font-semibold {{ $tx->delta_coins >= 0 ? 'text-emerald-300' : 'text-red-300' }}">
                        {{ $tx->delta_coins >= 0 ? '+' : '' }}{{ number_format($tx->delta_coins) }}
                    </td>
                    <td class="text-right text-white/80">{{ number_format($tx->balance_after) }}</td>
                    <td class="pl-3 text-white/50">{{ $tx->reason ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
    @endif
</div>
@endsection
