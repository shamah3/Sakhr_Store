<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(): View
    {
        $usersQuery = User::role('customer');

        if (request()->filled('search')) {
            $searchTerm = request('search');
            $usersQuery->where(function ($query) use ($searchTerm) {
                $query->where('name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('email', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('phone', 'LIKE', "%{$searchTerm}%");
            });
        }
        
        $users = $usersQuery->paginate(10)->withQueryString();
        return view('admin.users.index', compact('users'));
    }

    public function create() {}

    public function store(Request $request) {}

    public function show(string $id) {}

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $rules = [
            'name'     => 'required|string|max:255',
            'phone'    => 'nullable|string|max:20',
            'status'   => ['required', Rule::in(['active', 'banned'])],
            'password' => 'nullable|string|min:6',
        ];

        $validated = $request->validate($rules);

        $data = [
            'name'       => $validated['name'],
            'phone'      => $validated['phone'],
            'status'     => $validated['status'],
            'ban_reason' => $validated['status'] === 'banned' ? $request->ban_reason : null,
        ];

        // Update password only if provided
        if (!empty($validated['password'])) {
            $data['password'] = \Illuminate\Support\Facades\Hash::make($validated['password']);
        }

        // Reset biometric if admin checked the box
        if ($request->boolean('reset_biometric')) {
            $data['has_biometric']   = false;
            $data['biometric_token'] = null;
        }

        $user->update($data);

        return redirect()->route('admin.users.index')
            ->with('success', 'تم تحديث بيانات العميل بنجاح!');
    }

    /**
     * Toggle user status (Active/Banned)
     */
    public function toggleStatus(User $user): RedirectResponse
    {
        $user->status = ($user->status === 'active') ? 'banned' : 'active';
        $user->save();

        $statusAr = $user->status === 'active' ? 'تفعيل' : 'حظر';
        return back()->with('success', "تم $statusAr حساب العميل بنجاح.");
    }

    /**
     * Admin manually resets a user's password.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
        ]);

        return back()->with('success', 'تم تعديل كلمة سر العميل بنجاح.');
    }

    /**
     * Admin manually sends a verification OTP to the customer.
     */
    public function sendOtp(User $user): RedirectResponse
    {
        if (!$user->phone) {
            return back()->with('error', 'العميل لا يمتلك رقم هاتف مسجل.');
        }

        $code = rand(100000, 999999);
        $expiresAt = now()->addMinutes(15);

        \App\Models\OtpCode::create([
            'identifier' => $user->phone,
            'code'       => $code,
            'type'       => 'verification',
            'expires_at' => $expiresAt,
        ]);

        // Note: Real SMS provider integration should be called here
        // \App\Services\SmsService::send($user->phone, "رمز التحقق الخاص بك هو: $code");

        return back()->with('success', "تم توليد وإرسال رمز التحقق ($code) بنجاح.");
    }

    /**
     * Admin manually marks a customer as verified.
     */
    public function verifyAccount(User $user): RedirectResponse
    {
        $user->email_verified_at = now();
        $user->save();

        return back()->with('success', 'تم تأكيد حساب العميل يدوياً بنجاح.');
    }

    public function destroy(string $id) {}
}