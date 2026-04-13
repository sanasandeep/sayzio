<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\User;
use App\Modules\Admin\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showRegister()
    {
        if (Auth::check()) return redirect()->route('user.dashboard');
        return view('user.auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $freePlan = Plan::where('slug', 'free')->first();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'plan_id' => $freePlan?->id,
            'status' => 'active',
        ]);

        Auth::login($user);

        return redirect()->route('user.dashboard');
    }

    public function showLogin()
    {
        if (Auth::check()) return redirect()->route('user.dashboard');
        return view('user.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            Auth::user()->update(['last_login_at' => now()]);
            return redirect()->intended(route('user.dashboard'));
        }

        return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        if (session()->has('admin_id')) {
            $adminId = session('admin_id');
            session()->forget(['impersonate_user_id', 'admin_id']);
            Auth::logout();
            Auth::guard('admin')->loginUsingId($adminId);
            return redirect()->route('admin.users.index')->with('success', 'Impersonation stopped.');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('user.login');
    }
}
