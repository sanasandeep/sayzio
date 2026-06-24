<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\ProtectedAccount;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Services\AdminActionLogger;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $query = Admin::with('role');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role_id', $request->role);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $staff = $query->latest()->paginate(15)->withQueryString();
        $roles = Role::all();

        // Lowercased set of protected emails on this page so the view can
        // hide the delete control for protected staff accounts.
        $staffEmails = $staff->getCollection()
            ->pluck('email')
            ->filter()
            ->map(fn ($e) => strtolower(trim((string) $e)))
            ->unique()
            ->values()
            ->all();

        $protectedEmails = ProtectedAccount::query()
            ->whereIn('email', $staffEmails)
            ->pluck('email')
            ->map(fn ($e) => strtolower(trim((string) $e)))
            ->flip();

        // Admin-guard roles power the inline "Promote existing user" control
        // so an operator can pick a back-office role without leaving this page.
        $adminRoles = Role::query()
            ->where('guard', 'admin')
            ->orderBy('name')
            ->get();

        return view('admin.staff.index', compact('staff', 'roles', 'adminRoles', 'protectedEmails'));
    }

    /**
     * Typeahead search for existing user accounts, used by the inline
     * "Promote existing user" control on the Staff page. Returns a small
     * JSON list of matching users (mirrors the name/email ilike pattern
     * in UserManagementController::index) flagged with whether they
     * already have back-office admin access. Gated by `staff.create` at
     * the route layer, the same permission as the grant endpoint.
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $term = trim((string) $request->get('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $users = User::query()
            ->where(function ($q) use ($term) {
                $q->where('name', 'ilike', "%{$term}%")
                  ->orWhere('email', 'ilike', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'email']);

        $data = $users->map(fn (User $user) => [
            'id'       => $user->id,
            'name'     => $user->name,
            'email'    => $user->email,
            'is_admin' => $user->adminAccount() !== null,
        ])->all();

        return response()->json(['data' => $data]);
    }

    public function create()
    {
        $roles = Role::all();
        return view('admin.staff.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
            'status' => 'required|in:active,inactive',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        Admin::create($validated);

        return redirect()->route('admin.staff.index')->with('success', 'Staff member created successfully.');
    }

    public function show(Admin $staff)
    {
        $staff->load('role');
        return view('admin.staff.show', compact('staff'));
    }

    public function edit(Admin $staff)
    {
        $roles = Role::all();
        $isProtected = ProtectedAccount::isProtected($staff);
        return view('admin.staff.edit', compact('staff', 'roles', 'isProtected'));
    }

    public function update(Request $request, Admin $staff, AdminActionLogger $audit)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('admins')->ignore($staff->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
            'status' => 'required|in:active,inactive',
        ]);

        // Deactivating a protected staff account is a suspend — block it.
        if ($validated['status'] === 'inactive'
            && $staff->status !== 'inactive'
            && ProtectedAccount::isProtected($staff)) {
            $audit->log(AdminActionLogger::SUSPEND_BLOCKED, $staff->userAccount(), [
                'email'  => $staff->email,
                'reason' => 'Staff account is protected and cannot be deactivated.',
            ]);
            return back()->with('error', 'This account is protected and cannot be deactivated.');
        }

        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password'] = Hash::make($validated['password']);
        }

        $staff->update($validated);

        return redirect()->route('admin.staff.index')->with('success', 'Staff member updated successfully.');
    }

    public function destroy(Admin $staff, AdminActionLogger $audit)
    {
        if ($staff->id === Auth::guard('admin')->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if (ProtectedAccount::isProtected($staff)) {
            $audit->log(AdminActionLogger::DELETE_BLOCKED, $staff->userAccount(), [
                'email'  => $staff->email,
                'reason' => 'Staff account is protected and cannot be deleted.',
            ]);
            return back()->with('error', 'This account is protected and cannot be deleted.');
        }

        $staff->delete();
        return redirect()->route('admin.staff.index')->with('success', 'Staff member deleted successfully.');
    }
}
