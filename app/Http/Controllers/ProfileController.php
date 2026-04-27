<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * 1. عرض بيانات البروفايل بالكامل
     */
    /**
     * 1. عرض بيانات البروفايل بالكامل مع الإحصائيات الحقيقية
     */
    public function show()
    {
        $userId = Auth::id();

        // تحميل المستخدم مع علاقاته الأساسية
        $user = User::with(['profile', 'skills', 'organization'])->findOrFail($userId);


        if ($user->profile_image) {
            $user->profile_image_url = asset('storage/' . $user->profile_image);
        } else {
            // المسار الافتراضي الذي طلبته
            $user->profile_image_url = asset('storage/profiles/default.jpg');
        }
        // --- حساب الإحصائيات الحقيقية للمتطوع (Student/User) ---
        if ($user->role !== 'organization') {
            $stats = [
                // 1. إجمالي الساعات المعتمدة فقط (approved)
                'total_hours' => $user->hourLogs()->where('status', 'approved')->sum('hours'),

                // 2. عدد الفرص التي انتهى العمل بها (مقبولة في الطلبات)
                'completed_opportunities_count' => \App\Models\Application::where('user_id', $userId)
                    ->where('status', 'accepted')
                    ->count(),

                // 3. متوسط التقييم من جدول Reviews
                'average_rating' => $user->average_rating,
                // 4. عدد التقييمات الكلي
                'total_reviews' => $user->reviews()->count(),
            ];

            // دمج الإحصائيات داخل كائن الـ profile لسهولة الوصول إليها في الـ Frontend
            if ($user->profile) {
                $user->profile->stats = $stats;
            }
        }

        return response()->json($user);
    }

    /**
     * 2. تحديث البيانات الأساسية (الاسم، الهاتف، الصورة، والبيانات التكميلية)
     */
    public function update(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // استخدمنا sometimes بدلاً من required للحقول التي لا نريد إجبار المستخدم على إرسالها كل مرة
        $validator = Validator::make($request->all(), [
            'full_name'      => 'sometimes|string|max:255',
            'phone'          => 'nullable|string|max:20',
            'location'       => 'nullable|string',
            'lat'            => 'nullable|numeric',
            'lng'            => 'nullable|numeric',
            'profile_image'  => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'email'          => 'sometimes|email|unique:users,email,' . $user->id,
            'password'       => 'sometimes|nullable|min:8|confirmed',

            // حقول المتطوع
            'university'     => 'nullable|string',
            'major'          => 'nullable|string',
            'bio'            => 'nullable|string',
            'gender'         => 'nullable|in:male,female',
            'birth_date'     => 'nullable|date',
            'skill_ids'      => 'nullable|array',

            // حقول المؤسسة
            'org_name'       => 'sometimes|string|max:255',
            'website'        => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $userData = $request->only(['full_name', 'phone', 'email', 'location', 'lat', 'lng']);

        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
        }

        $user->update($userData);

        // --- تحديث الصورة الشخصية ---
        if ($request->hasFile('profile_image')) {
            // حذف الصورة القديمة (تجنب حذف الصورة الافتراضية)
            if ($user->profile_image && !str_contains($user->profile_image, 'default.jpg')) {
                Storage::disk('public')->delete($user->profile_image);
            }
            $user->profile_image = $request->file('profile_image')->store('profiles', 'public');
            $user->save();
        }

        // --- تحديث الجداول المرتبطة بناءً على الدور ---
        if ($user->role === 'organization' && $user->organization) {
            $user->organization->update($request->only([
                'org_name',
                'org_type',
                'contact_person',
                'description',
                'website'
            ]));
            if ($request->hasFile('digital_signature')) {
                if ($user->organization->digital_signature) Storage::disk('public')->delete($user->organization->digital_signature);
                $user->organization->update(['digital_signature' => $request->file('digital_signature')->store('signatures', 'public')]);
            }
            // معالجة الملفات الخاصة بالمؤسسة بنفس منطق الحذف القديم
            if ($request->hasFile('license_file')) {
                if ($user->organization->license_file) Storage::disk('public')->delete($user->organization->license_file);
                $user->organization->update(['license_file' => $request->file('license_file')->store('licenses', 'public')]);
            }
        } elseif ($user->profile) {
            $user->profile->update($request->only([
                'university',
                'major',
                'bio',
                'gender',
                'birth_date'
            ]));

            if ($request->has('skill_ids')) {
                $user->skills()->sync($request->skill_ids);
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'تم تحديث بياناتك بنجاح',
            'data'    => $user->load($user->role === 'organization' ? 'organization' : ['profile', 'skills'])
        ]);
    }
    /**
     * Update the user's password specifically.
     */
    public function updatePassword(Request $request)
    {
        // 1. التحقق من البيانات المرسلة من React
        // استخدمنا new_password لتطابق الـ Request القادم من الواجهة الأمامية
        $request->validate([
            'current_password' => 'required|current_password',
            'new_password'     => 'required|string|min:8|confirmed',
        ], [
            'current_password.current_password' => 'كلمة المرور الحالية غير صحيحة.',
            'new_password.confirmed' => 'تأكيد كلمة المرور الجديدة غير متطابق.',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // 2. التحديث مباشرة على كائن المستخدم (جدول users)
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'تم تحديث كلمة المرور بنجاح'
        ]);
    }
    /**
     * 3. تحديث المهارات (للمتطوعين)
     */
    public function updateSkills(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();


        $request->validate([
            'skill_ids'   => 'required|array',
            'skill_ids.*' => 'exists:skills,id',
        ]);

        // التعديل: المزامنة مع مهارات المستخدم مباشرة
        $user->skills()->sync($request->skill_ids);

        return response()->json([
            'status'  => 'success',
            'message' => 'تم تحديث مهاراتك التقنية بنجاح.',
            'skills'  => $user->load('skills')->skills
        ]);
    }
    /**
     * تحديث الإحداثيات الجغرافية للمستخدم (Latitude & Longitude)
     * لغرض عرض الفرص الأقرب مكانياً.
     */
    public function updateLocation(Request $request)
    {
        // 1. التحقق من وصول الإحداثيات بشكل صحيح
        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'الإحداثيات المرسلة غير صالحة',
                'errors' => $validator->errors()
            ], 422);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // 2. تحديث الحقول في جدول users مباشرة
        // تأكد أن أعمدة lat و lng موجودة في جدول users في قاعدة البيانات
        $user->update([
            'lat' => $request->lat,
            'lng' => $request->lng,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'تم تحديث موقعك الجغرافي بنجاح، ستظهر لك النتائج الأقرب الآن.',
            'coords'  => [
                'lat' => $user->lat,
                'lng' => $user->lng
            ]
        ]);
    }
}
