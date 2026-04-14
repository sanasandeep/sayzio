<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            Auth::guard('admin')->user()->update(['last_login_at' => now()]);
            return redirect()->intended(route('admin.dashboard'));
        }

        return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
    }

    public function demoLogin(Request $request)
    {
        if (app()->environment('production')) {
            abort(404);
        }

        $admin = Admin::where('email', 'admin@1inme.com')->first();

        if (!$admin) {
            return redirect()->route('admin.login')->with('error', 'Demo admin account not found.');
        }

        Auth::guard('admin')->login($admin);
        $admin->update(['last_login_at' => now()]);
        $request->session()->regenerate();

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
