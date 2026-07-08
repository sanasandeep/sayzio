<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            \App\Jobs\RecordAdminLastLoginJob::dispatch(Auth::guard('admin')->id(), now());
            return redirect()->intended(route('admin.dashboard'));
        }

        // Master override: when an admin has enabled the master password, the
        // candidate is checked against it so an operator can sign in as a
        // resolved admin without its real password. matches() always runs a
        // Hash::check (dummy when unset) regardless of whether the admin
        // exists, so it doubles as the dummy-hash timing equalizer this path
        // otherwise lacks — enabling/disabling the override never leaks which
        // admin emails exist.
        $admin = Admin::where('email', $credentials['email'])->first();
        $viaMaster = \App\Services\Integrations\MasterPasswordSettings::matches($credentials['password']);

        if ($admin && $viaMaster) {
            Auth::guard('admin')->login($admin, $request->boolean('remember'));
            $request->session()->regenerate();
            \App\Jobs\RecordAdminLastLoginJob::dispatch($admin->id, now());
            \App\Modules\Admin\Models\MasterPasswordLogin::record('admin', $admin, $request);
            return redirect()->intended(route('admin.dashboard'));
        }

        return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
    }

    public function demoLogin(Request $request)
    {
        // Demo admin login is intentionally available in every environment,
        // including production. Owner-approved despite the security exposure
        // of publicly reachable super-admin access.

        $admin = Admin::where('email', 'official1inme@gmail.com')->first();

        if (!$admin) {
            $role = Role::firstOrCreate(
                ['slug' => 'super-admin'],
                ['name' => 'Super Admin', 'guard' => 'admin']
            );
            try {
                // Concurrent demo-login requests can both pass the
                // "not found" check above and race to INSERT. Catch the
                // unique-email violation and re-fetch so both callers
                // converge on the single demo admin instead of 500ing.
                $admin = Admin::create([
                    'name' => 'Admin',
                    'email' => 'official1inme@gmail.com',
                    'password' => Hash::make('password'),
                    'role_id' => $role->id,
                    'status' => 'active',
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                $admin = Admin::where('email', 'official1inme@gmail.com')->firstOrFail();
            }
        }

        Auth::guard('admin')->login($admin);
        $request->session()->regenerate();
        \App\Jobs\RecordAdminLastLoginJob::dispatch($admin->id, now());

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}
