<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CustomerController extends BaseController
{
    /**
     * Register a new customer.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        // التحقق من وجود المستخدم مسبقاً (تسجيل دخول تلقائي)
        $existingCustomer = \App\Models\Customer::where('phone', $request->phone)->first();
        if ($existingCustomer) {
            $user = $existingCustomer->user;
            $success['token'] =  $user->createToken('MatjariApp')->plainTextToken;
            $success['user']  = [
                'id'    => $user->id,
                'name'  => $user->name,
                'first_name' => $this->firstNameFrom($user->name),
                'last_name' => $this->lastNameFrom($user->name),
                'phone' => $existingCustomer->phone,
                'email' => $user->email ?? "",
            ];
            return $this->sendResponse($success, 'تم تسجيل الدخول بنجاح');
        }

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20|unique:customers',
            'biometric_token' => 'nullable|string|unique:customers',
            'name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users',
            'password' => 'nullable|string|min:6',
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

        // 1. إنشاء الحساب الأساسي في جدول المستخدمين
        $user = User::create([
            'name' => $resolvedName !== '' ? $resolvedName : ('User_' . substr($request->phone, -4)),
            'email' => $request->email,
            'phone' => $request->phone, // للمطابقة
            'password' => $request->password ? Hash::make($request->password) : null,
            'role' => 'customer',
        ]);

        // 2. إنشاء بيانات العميل في الجدول الجديد
        \App\Models\Customer::create([
            'user_id' => $user->id,
            'phone' => $request->phone,
            'biometric_token' => $request->biometric_token,
        ]);

        $user->assignRole('customer');

        $success['token'] =  $user->createToken('MatjariApp')->plainTextToken;
        $success['user']  = [
            'id'    => $user->id,
            'name'  => $user->name,
            'first_name' => $this->firstNameFrom($user->name),
            'last_name' => $this->lastNameFrom($user->name),
            'phone' => $request->phone,
            'email' => $user->email ?? "",
        ];

        return $this->sendResponse($success, 'تم تسجيل الحساب والعميل بنجاح', 201);
    }

    /**
     * جلب بيانات العميل المخصصة (رقم الهاتف والبصمة)
     */
    public function fetchCustomer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('رقم الهاتف مطلوب', $validator->errors(), 422);
        }

        $customer = \App\Models\Customer::where('phone', $request->phone)->first();

        if (!$customer) {
            return $this->sendError('العميل غير موجود', [], 404);
        }

        return $this->sendResponse([
            'phone' => $customer->phone,
            'biometric_token' => $customer->biometric_token,
            'user_details' => [
                'id' => $customer->user_id,
                'name' => $customer->user->name,
            ]
        ], 'تم جلب بيانات العميل من قاعدة البيانات بنجاح');
    }

    private function firstNameFrom(?string $name): string
    {
        $trimmed = trim((string) $name);
        if ($trimmed === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $trimmed) ?: [];

        return $parts[0] ?? '';
    }

    private function lastNameFrom(?string $name): string
    {
        $trimmed = trim((string) $name);
        if ($trimmed === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $trimmed) ?: [];
        array_shift($parts);

        return implode(' ', $parts);
    }
}
