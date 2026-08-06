<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate(); // <-- هذا يتحقق من الإيميل وكلمة المرور

        // ##### بداية الكود المضاف #####
        $user = Auth::getProvider()->retrieveByCredentials($request->only('email'));
        if ($user && $user->status !== 'active') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'هذا الحساب غير نشط أو تم حظره.',
            ]);
        }
        // ##### نهاية الكود المضاف #####

        // منع العملاء من دخول لوحة الويب
        if ($request->user()->hasRole('customer')) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->withErrors([
                'email' => 'هذا الحساب مخصص لتطبيق الهاتف فقط. يرجى تسجيل الدخول من خلال التطبيق.',
            ]);
        }

        $request->session()->regenerate();
        $user = $request->user();

        // توجيه المستخدم حسب الصلاحية - تم التعديل لتجنب الروابط العالقة
        if ($user->hasRole('super-admin')) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->hasRole('vendor')) {
            return redirect()->route('vendor.dashboard');
        }

        return redirect()->intended('/');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}