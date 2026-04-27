<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * 1. دالة التسجيل المصلحة
     */
    public function register(Request $request)
    {
        // التحقق من البيانات مع إضافة nullable لتجنب أخطاء النوع (String Error)
        $data = $request->validate([
            'full_name'      => 'required|string|max:255',
            'email'          => 'required|email|unique:users',
            'password'       => 'required|min:6|confirmed',
            'role'           => 'required|in:student,organization',
            'profile_image'  => 'nullable|file',
            'university'     => 'nullable|required_if:role,student|string',
            'phone'     => 'nullable|string',
            'gender'         => 'nullable|in:male,female',
            'birth_date'     => 'nullable|date',
            // حقول المؤسسة: nullable تعني "مسموح أن يكون فارغاً إذا لم يكن الدور مؤسسة"
            'org_name'       => 'nullable|required_if:role,organization|string',
            'org_type'       => 'nullable|required_if:role,organization|string',
            'contact_person' => 'nullable|required_if:role,organization|string',
            'license_file'   => 'nullable|required_if:role,organization|file|mimes:pdf|max:5120',
            'website'        => 'nullable|url',
            // حقول الطالب
            'university'     => 'nullable|required_if:role,student|string',
            'major'          => 'nullable|required_if:role,student|string',
        ]);
        $licensePath = null;
        if ($request->hasFile('license_file')) {
            $licensePath = $request->file('license_file')->store('licenses', 'public');
        }
        if ($request->hasFile('profile_image')) {
            $profileImage = $request->file('profile_image')->store('profiles', 'public');
        }
        // إنشاء الحساب الأساسي
        $user = User::create([
            'full_name' => $data['full_name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'role'      => $data['role'],
            'profile_image' =>   $profileImage,
            'phone' => $data['phone'],
            'is_active' => true,
        ]);

        // توزيع البيانات بناءً على الدور
        if ($user->role === 'student') {
            $user->profile()->create([
                'university' => $data['university'],
                'major'      => $data['major'],
                'gender'      => $data['gender'],
                'birth_date'  => $data['birth_date'],

            ]);
            $relation = 'profile';
        } else {
            // معالجة رفع ملف الترخيص PDF

            $user->organization()->create([
                'org_name'       => $data['org_name'],
                'org_type'       => $data['org_type'],
                'contact_person' => $data['contact_person'],
                'license_file'   => $licensePath, // تخزين مسار الملف
                'website'  => $data['website'],
                'is_verified'    => false,
            ]);
            $relation = 'organization';
        }

        $token = $user->createToken('musanada_token')->plainTextToken;

        return response()->json([
            'status'       => true,
            'message'      => 'تم إنشاء الحساب بنجاح',
            'access_token' => $token,
            'user'         => $user->load($relation)
        ], 201);
    }

    /**
     * 2. دالة تسجيل الدخول المحدثة (تصحيح مسمى الدور)
     */
    public function login(Request $request)
    {
        $fields = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string'
        ]);

        $user = User::where('email', $fields['email'])->first();

        if (!$user || !Hash::check($fields['password'], $user->password)) {
            return response()->json(['message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'هذا الحساب معطل، يرجى التواصل مع الإدارة'], 403);
        }
        // 3. التحقق من قبول الإدمن للمؤسسة (المنطق الجديد)
        if ($user->role === 'organization') {
            // نتحقق من حقل is_verified في علاقة المؤسسة
            if (!$user->organization || !$user->organization->is_verified) {
                return response()->json([
                    'status' => false,
                    'message' => 'حساب المؤسسة قيد المراجعة من قبل الإدارة. سيتم تفعيل الدخول فور التحقق من البيانات والوثائق المرفقة.'
                ], 403);
            }
        }
        $relations = $user->role === 'organization' ? 'organization' : 'profile';

        $token = $user->createToken('musanada_token')->plainTextToken;

        return response()->json([
            'status'  => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'token'   => $token,
            'user'    => $user->load($relations),
            'role'    => $user->role,
        ]);
    }

    public function logout(Request $request)
    {
        // حذف التوكن الحالي الذي تم استخدامه في الطلب
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'status' => true,
            'message' => 'تم تسجيل الخروج بنجاح وحذف التوكن من قاعدة البيانات'
        ]);
    }
}
