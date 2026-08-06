<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\User;
use App\Models\OtpCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AuthController extends BaseController
{
    /**
     * Send OTP code to a specific phone number.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $code = rand(100000, 999999);
        $identifier = $request->phone;

        OtpCode::updateOrCreate(
            ['identifier' => $identifier],
            [
                'code' => $code,
                'expires_at' => now()->addMinutes(10),
                'is_used' => false,
                'type' => 'auth'
            ]
        );

        // In real app, send SMS here. For now returning in response for testing.
        return $this->sendResponse(['code' => $code], 'تم إرسال رمز التحقق بنجاح');
    }

    /**
     * Verify OTP code and login / return token.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $otp = OtpCode::where('identifier', $request->phone)
            ->where('code', $request->code)
            ->where('expires_at', '>', now())
            ->where('is_used', false)
            ->first();

        if (!$otp) {
            return $this->sendError('رمز التحقق غير صحيح أو منتهي الصلاحية', [], 401);
        }

        $otp->update(['is_used' => true]);

        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            return $this->sendError('المستخدم غير موجود، يرجى إنشاء حساب أولاً', [], 404);
        }

        // التأكد من إسناد دور العميل
        if (!$user->hasRole('customer')) {
            $user->assignRole('customer');
            $user->update(['role' => 'customer']);
        }

        // تسجيل نشاط الدخول في "بطاقة سجل أنشطة العملاء"
        \App\Models\CustomerActivity::create([
            'user_id' => $user->id,
            'activity_type' => 'login',
            'description' => 'تسجيل دخول من تطبيق الهاتف',
            'ip_address' => $request->ip(),
            'device_info' => $request->header('User-Agent'),
        ]);

        $success['token'] = $user->createToken('MatjariApp')->plainTextToken;
        $success['user'] = $this->userPayload($user);

        return $this->sendResponse($success, 'تم التحقق وتسجيل الدخول بنجاح');
    }

    /**
     * Change user password.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (!$user) {
            return $this->sendError('Unauthenticated.', [], 401);
        }

        $validator = Validator::make($request->all(), [
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        if (!Hash::check($request->old_password, $user->password)) {
            return $this->sendError('كلمة المرور القديمة غير صحيحة', [], 401);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return $this->sendResponse([], 'تم تغيير كلمة المرور بنجاح');
    }

    /**
     * Logout user and revoke token.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (!$user || !$user->currentAccessToken()) {
            return $this->sendError('Unauthenticated.', [], 401);
        }

        $user->currentAccessToken()->delete();

        return $this->sendResponse([], 'تم تسجيل الخروج بنجاح');
    }

    /**
     * Get the authenticated user's profile.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (!$user) {
            return $this->sendError('Unauthenticated.', [], 401);
        }

        return $this->sendResponse(
            $this->userPayload($user),
            'تم جلب بيانات الملف الشخصي بنجاح'
        );
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (!$user) {
            return $this->sendError('Unauthenticated.', [], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20|unique:users,phone,' . $user->id,
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $user->id,
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $resolvedName = trim((string) $request->input('name', ''));
        if ($resolvedName === '') {
            $resolvedName = trim(
                (string) $request->input('first_name', '') . ' ' . (string) $request->input('last_name', '')
            );
        }

        $user->fill([
            'name' => $resolvedName !== '' ? $resolvedName : $user->name,
            'phone' => $request->filled('phone') ? (string) $request->input('phone') : $user->phone,
            'email' => $request->filled('email') ? (string) $request->input('email') : $user->email,
        ]);
        $user->save();

        return $this->sendResponse(
            $this->userPayload($user),
            'تم تحديث الملف الشخصي بنجاح'
        );
    }

    public function uploadProfileImage(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (!$user) {
            return $this->sendError('Unauthenticated.', [], 401);
        }

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $path = $request->file('image')->store('profile-photos', 'public');
        $user->update(['profile_photo_path' => $path]);

        return $this->sendResponse(
            ['profile_photo_url' => asset('storage/' . $path)],
            'تم تحديث الصورة الشخصية بنجاح'
        );
    }

    public function me(Request $request): JsonResponse
    {
        return $this->profile($request);
    }

    /**
     * Login user via identifier (email/phone) and password.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $identifier = $request->identifier;
        $user = User::where('email', $identifier)->orWhere('phone', $identifier)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->sendError('بيانات الدخول غير صحيحة.', [], 401);
        }

        if (!$user->hasRole('customer')) {
            $user->assignRole('customer');
            $user->update(['role' => 'customer']);
        }

        $success['token'] = $user->createToken('MatjariAppAuth')->plainTextToken;
        $success['user'] = $this->userPayload($user);

        return $this->sendResponse($success, 'تم تسجيل الدخول بنجاح');
    }

    /**
     * Enable biometric login for the user.
     */
    public function enableBiometric(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (!$user) {
            return $this->sendError('Unauthenticated.', [], 401);
        }

        $validator = Validator::make($request->all(), [
            'biometric_token' => 'required|string|unique:users,biometric_token,' . $user->id,
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $user->update([
            'has_biometric' => true,
            'biometric_token' => $request->biometric_token,
        ]);

        return $this->sendResponse([], 'تم تفعيل الدخول بالبصمة بنجاح');
    }

    /**
     * Login via biometric token.
     */
    public function biometricLogin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'biometric_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $user = User::where('biometric_token', $request->biometric_token)
                    ->where('has_biometric', true)
                    ->first();

        if (!$user) {
            return $this->sendError('فشل الدخول بالبصمة. المعرف غير معترف به.', [], 401);
        }

        if ($user->status !== 'active') {
             return $this->sendError('الحساب غير نشط أو تم حظره.', [], 403);
        }

        $success['token'] = $user->createToken('MatjariAppBiometric')->plainTextToken;
        $success['user'] = $this->userPayload($user);

        return $this->sendResponse($success, 'تم تسجيل الدخول بالبصمة بنجاح');
    }

    private function authenticatedUser(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }

    private function userPayload(User $user): array
    {
        $nameParts = $this->splitName($user->name);

        return [
            'id' => $user->id,
            'name' => $user->name ?? '',
            'first_name' => $nameParts['first_name'],
            'last_name' => $nameParts['last_name'],
            'phone' => $user->phone ?? '',
            'email' => $user->email ?? '',
            'role' => $user->role ?? 'customer',
            'profile_photo_url' => $user->profile_photo_path
                ? asset('storage/' . ltrim($user->profile_photo_path, '/'))
                : null,
        ];
    }

    private function splitName(?string $name): array
    {
        $trimmed = trim((string) $name);
        if ($trimmed === '') {
            return ['first_name' => '', 'last_name' => ''];
        }

        $parts = preg_split('/\s+/', $trimmed) ?: [];
        $firstName = array_shift($parts) ?? '';

        return [
            'first_name' => $firstName,
            'last_name' => implode(' ', $parts),
        ];
    }
}
