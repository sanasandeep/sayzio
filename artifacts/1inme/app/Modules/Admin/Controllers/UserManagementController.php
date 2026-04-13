<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\User;
use App\Modules\Admin\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('plan');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('plan')) {
            $query->where('plan_id', $request->plan);
        }

        $users = $query->latest()->paginate(15)->withQueryString();
        $plans = Plan::active()->ordered()->get();

        return view('admin.users.index', compact('users', 'plans'));
    }

    public function show(User $user)
    {
        $user->load('plan');
        $plans = Plan::active()->ordered()->get();
        return view('admin.users.show', compact('user', 'plans'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:active,inactive,banned',
            'plan_id' => 'sometimes|nullable|exists:plans,id',
            'plan_expires_at' => 'sometimes|nullable|date',
        ]);

        $user->update($validated);

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    public function impersonate(User $user)
    {
        $admin = Auth::guard('admin')->user();
        session(['impersonate_user_id' => $user->id, 'admin_id' => $admin->id]);
        Auth::guard('web')->login($user);

        return redirect()->route('user.dashboard')->with('info', 'You are now impersonating ' . $user->name);
    }

    public function stopImpersonation()
    {
        $adminId = session('admin_id');
        session()->forget(['impersonate_user_id', 'admin_id']);
        Auth::guard('web')->logout();

        return redirect()->route('admin.users.index')->with('success', 'Impersonation stopped.');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }
}
