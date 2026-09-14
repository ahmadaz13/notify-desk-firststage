<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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

        // 1. Resolve user by email (case-insensitive)
        $user = User::whereRaw('LOWER(email) = ?', [$lowerIdentifier])->first();

        // 2. If not found, resolve user by associated partner's company email
        if (!$user) {
            $user = User::whereHas('partner', function ($q) use ($lowerIdentifier) {
                $q->whereRaw('LOWER(email) = ?', [$lowerIdentifier]);
            })->first();
        }

        // 3. If not found, resolve user by associated partner's phone number
        if (!$user) {
            $digits = preg_replace('/[^0-9]/', '', $rawIdentifier);
            if (!empty($digits) && strlen($digits) >= 7) {
                $user = User::whereHas('partner', function ($q) use ($rawIdentifier, $digits) {
                    $q->where('phone', $rawIdentifier)
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$digits}%"]);
                })->first();
            }
        }

        // 4. Validate credentials if user was matched
        if ($user && Hash::check($password, $user->password)) {
            // Check if partner account is suspended
            if ($user->isPartner() && $user->partner && $user->partner->isSuspended()) {
                return back()->withErrors(['email' => 'هذا الحساب معلق حالياً. يرجى التواصل مع الإدارة.'])->withInput();
            }

            // Check if temporary password reset has expired
            if ($user->reset_expires_at && $user->reset_expires_at->isPast()) {
                return back()->withErrors(['email' => 'انتهت صلاحية كلمة المرور المؤقتة، يرجى طلب إعادة تعيين من الإدارة.'])->withInput();
            }

            Auth::login($user, $request->boolean('remember'));
        } elseif (!Auth::attempt(['email' => $lowerIdentifier, 'password' => $password], $request->boolean('remember')) &&
                  !Auth::attempt(['email' => $rawIdentifier, 'password' => $password], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'بيانات الدخول غير صحيحة.'])->withInput();
        }

        $request->session()->regenerate();

        $loggedInUser = Auth::user();
        if ($loggedInUser && $loggedInUser->isPartner()) {
            $isFirstLogin = is_null($loggedInUser->first_login_at);
            if ($isFirstLogin) {
                $loggedInUser->update([
                    'first_login_at' => now(),
                    'reset_expires_at' => null,
                ]);
                if ($loggedInUser->partner && is_null($loggedInUser->partner->onboarded_at)) {
                    $loggedInUser->partner->update(['onboarded_at' => now()]);
                }

                return redirect()->intended(route('partner.dashboard'))
                    ->with('success', 'أهلاً بك في شبكة شركاء Notify! تم تفعيل حسابك بنجاح.')
                    ->with('is_first_login', true);
            }

            return redirect()->intended(route('partner.dashboard'))->with('success', 'مرحباً بك في بوابة الشركاء.');
        }

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
