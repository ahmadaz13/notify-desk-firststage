<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $rawIdentifier = trim((string) $credentials['email']);
        $password = (string) $credentials['password'];
        $lowerIdentifier = strtolower($rawIdentifier);

        $user = User::whereRaw('LOWER(email) = ?', [$lowerIdentifier])->first();

        if ($user && $user->isActiveApplicationUser() && Hash::check($password, $user->password)) {
            if ($user->reset_expires_at && $user->reset_expires_at->isPast()) {
                return back()->withErrors(['email' => 'انتهت صلاحية كلمة المرور المؤقتة، يرجى طلب إعادة تعيين من الإدارة.'])->withInput();
            }

            Auth::login($user, $request->boolean('remember'));
        } else {
            return back()->withErrors(['email' => 'بيانات الدخول غير صحيحة.'])->withInput();
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))->with('success', 'مرحباً بك في Notify.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
