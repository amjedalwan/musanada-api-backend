<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Certificate;
use App\Models\HourLog;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class StudentController extends Controller
{
    public function index(Request $request)
    {
       $user = Auth::user();
      

        // إمكانية البحث الفوري (Search-as-you-type)
    
        return response()->json(['data'=>$user ,'gender'=>$user->profile->gender]);
    }

    /**
     * 1. البحث عن المتطوعين حسب المهارة (للمؤسسات)
     * مثال: جلب كل من لديه مهارة "Graphic Design"
     */
    public function searchBySkill(Request $request)
    {
        $skillName = $request->query('skill');

        if (!$skillName) {
            return response()->json(['message' => 'يرجى إدخال اسم المهارة للبحث'], 400);
        }

        $volunteers = UserProfile::whereHas('skills', function ($query) use ($skillName) {
            $query->where('name', 'like', '%' . $skillName . '%');
        })
            ->with(['user:id,full_name,profile_image', 'skills:id,name'])
            ->get();

        if ($volunteers->isEmpty()) {
            return response()->json(['message' => 'عذراً، لا يوجد متطوعون يمتلكون هذه المهارة حالياً'], 404);
        }

        return response()->json($volunteers);
    }

    /**
     * 2. جلب الملف التعريفي الشامل (Full Portfolio)
     * هذا هو الكود الذي يغذي واجهة "عرض البروفايل" في Flutter للمؤسسة
     */
    public function getFullPortfolio($id)
    {
        try {
            // 1. نبحث عن المستخدم أولاً مع جلب علاقة البروفايل والمهارات
            $user = \App\Models\User::find($id);

            // 2. إذا لم نجد المستخدم أو لم يكن له بروفايل
            if (!$user || !$user->profile) {
                return response()->json(['message' => 'عذراً، ملف المتطوع هذا غير مكتمل أو غير موجود'], 404);
            }

            $profile = $user->profile;

            return response()->json([
                'personal_info' => [
                    'name'  => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'location' => $user->location,
                    'lng' => $user->lng,
                    'lat' => $user->lat,
                    'is_active' => $user->is_active,

                    'image' => $user->profile_image ? asset('storage/' . $user->profile_image) : null,


                ],
                'academic_info' => [
                    'university' => $profile->university,
                    'major'      => $profile->major,
                    'birth_date' => $profile->birth_date,
                    'bio'        => $profile->bio,
                ],

                'skills'          => $user->skills,
                'total_hours'     => $profile->total_volunteer_hours ?? 0,
                'training_hours'  => $profile->total_training_hours ?? 0,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطأ في الخادم الداخلي',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    private function calculateCompletionRate($userId)
    {
        $totalApps = Application::where('user_id', $userId)->count();
        if ($totalApps === 0) return 0;

        $completedApps = Application::where('user_id', $userId)
            ->where('status', 'accepted') // افترضنا أن المقبول هو المكتمل
            ->count();

        return round(($completedApps / $totalApps) * 100);
    }

   public function getDashboardData()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user) return response()->json(['message' => 'غير مصرح'], 401);

        $userId = $user->id;

        // 1. حساب الإحصائيات مع إضافة "معدل الإنجاز"
        $stats = [
            'total_hours' => (int) HourLog::where('user_id', $userId)->where('status', 'approved')->sum('hours'),
            'skills_count' => $user->skills()->count(),
            'active_applications' => Application::where('user_id', $userId)->whereIn('status', ['pending', 'accepted'])->count(),
            'certificates_count' => Certificate::where('user_id', $userId)->count(),
            'completion_rate' => $this->calculateCompletionRate($userId), // استدعاء الدالة المساعدة
        ];

        // 2. تحضير بيانات الرسم البياني للسنة الحالية
        $monthlyHours = HourLog::where('user_id', $userId)
            ->where('status', 'approved')
            ->whereYear('created_at', date('Y'))
            ->selectRaw('MONTH(created_at) as month, SUM(hours) as total')
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();

        $monthlyApps = Application::where('user_id', $userId)
            ->whereYear('created_at', date('Y'))
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->groupBy('month')
            ->pluck('count', 'month')
            ->toArray();

        $chartData = [];
        $monthsAr = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل', 
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس', 
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
        ];

        foreach ($monthsAr as $num => $name) {
            $chartData[] = [
                'name' => $name,
                'hours' => $monthlyHours[$num] ?? 0,
                'apps' => $monthlyApps[$num] ?? 0,
            ];
        }

        // 3. جلب المهارات
        $userSkills = $user->skills->map(function ($skill) {
            return [
                'id'    => $skill->id,
                'name'  => $skill->name,
                'level' => rand(75, 98), 
                'color' => '#6366f1'
            ];
        });

        // 4. التوصيات بناءً على مهارات المستخدم
        $userSkillIds = $user->skills->pluck('id');
        $recommendations = Opportunity::where('status', 'open')
            ->whereHas('skills', function ($query) use ($userSkillIds) {
                $query->whereIn('skills.id', $userSkillIds);
            })
            ->with(['user.organization', 'skills'])
            ->latest()
            ->take(3)
            ->get();

        // 5. النشاط الأخير
        $recentActivity = Application::where('user_id', $userId)
            ->with(['opportunity.user.organization'])
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'stats'           => $stats,
            'chart_data'      => $chartData,
            'recommendations' => $recommendations,
            'user_skills'     => $userSkills,
            'recent_activity' => $recentActivity,
        ]);
    }

}
