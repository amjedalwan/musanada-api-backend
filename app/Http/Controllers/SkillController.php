<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SkillController extends Controller
{
    /**
     * 1. عرض كل المهارات المتاحة في النظام
     * يُستخدم لتعبئة الـ Multi-Select في تطبيق Flutter
     */
    public function index(Request $request)
    {
        $query = Skill::query();

        // إمكانية البحث الفوري (Search-as-you-type)
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        return response()->json([
            'status' => true,
            'data'   => $query->orderBy('name', 'asc')->get()
        ]);
    }

    /**
     * 2. إضافة مهارة جديدة (للمسؤول أو النظام)
     */
    public function store(Request $request)
    {
        // التحقق من الصلاحيات (يُفضل أن تكون للمسؤول فقط)
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'غير مصرح لك بإضافة مهارات جديدة للنظام'], 403);
        }

        $request->validate([
            'name' => 'required|string|unique:skills,name|max:50'
        ]);

        $skill = Skill::create(['name' => $request->name]);

        return response()->json([
            'message' => 'تم إضافة المهارة للنظام بنجاح',
            'skill'   => $skill
        ], 201);
    }

    /**
     * 3. ربط مهارات بالمتطوع الحالي (UserProfile)
     */
    public function attachToProfile(Request $request)
    {
        // 1. التحقق من البيانات المرسلة من Flutter
        $request->validate([
            'skill_ids'   => 'required|array',
            'skill_ids.*' => 'exists:skills,id', // التأكد أن المهارة موجودة فعلاً في الجدول
        ]);

        $user = Auth::user();

        // 2. التحقق من أن المستخدم يملك ملف شخصي (متطوع) وليس مؤسسة
        $profile = UserProfile::where('user_id', $user->id)->first();

        if (!$profile) {
            return response()->json([
                'message' => 'عذراً، هذا الحساب ليس ملف متطوع (المؤسسات لا تملك مهارات شخصية)'
            ], 403);
        }

        /** * 3. ربط المهارات باستخدام الجدول الوسيط (Pivot Table)
         * استخدام syncWithoutDetaching يضمن إضافة المهارات الجديدة 
         * دون حذف المهارات القديمة التي اختارها المتطوع سابقاً.
         */
        $profile->skills()->syncWithoutDetaching($request->skill_ids);

        return response()->json([
            'status'  => true,
            'message' => 'تم تحديث قائمة مهاراتك بنجاح',
            'current_skills' => $profile->load('skills')->skills
        ]);
    }
}