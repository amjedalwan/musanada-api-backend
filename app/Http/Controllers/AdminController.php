<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Organization;
use App\Models\Opportunity;
use App\Models\HourLog;
use App\Models\Application;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Notifications\ApplicationStatusChanged; // يُستخدم للإشعارات التلقائية المطلوبة
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Notifications\AdminSystemNotification; // تأكد من استيراد الكلاس الجديد

class AdminController extends Controller
{
   
    // ==========================================
    // ب) إدارة المستخدمين (FR-A02 - FR-A05)
    // ==========================================

    /** * عرض قائمة المستخدمين والبحث (FR-A02) 
     * إجراءات الأمان المضافة: التحقق من الصلاحيات، الحماية من الثغرات، والتحقق من نوع البيانات
     */
    public function getUsersDirectory(Request $request)
    {
        // الاحتياط 1: التحقق من أن الطلب آتٍ من "أدمن" فقط 
        // يفضل استخدام Middleware، ولكن كزيادة أمان نتحقق هنا أيضاً
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'غير مصرح لك بالوصول لهذه البيانات'], 403);
        }

        // البدء بالاستعلام مع العلاقات
        $query = User::with(['profile', 'organization']);

        // الاحتياط 2: منع ظهور بيانات الأدمن أنفسهم في القائمة (اختياري حسب حاجتك)
        // لكي لا يقوم أدمن بحذف نفسه أو تعديل بياناته من هنا بالخطأ
        $query->where('role', '!=', 'admin');


        // 1. فلترة الدور
        if ($request->filled('role') && $request->role !== 'all') {
            // احتياط: التحقق من أن القيمة المدخلة هي فعلاً قيم مقبولة فقط
            $allowedRoles = ['student', 'organization'];
            if (in_array($request->role, $allowedRoles)) {
                $query->where('role', $request->role);
            }
        }

        // 2. فلترة الحالة
        if ($request->filled('status') && $request->status !== 'all') {
            $status = ($request->status === 'active' || $request->status === '1') ? 1 : 0;
            $query->where('is_active', $status);
        }

        // 3. البحث (مع تنظيف مدخلات البحث للحماية من ثغرات XSS أو SQL Injection)
        if ($request->filled('search')) {
            // تنظيف النص من أي وسوم HTML أو رموز غريبة
            $searchTerm = strip_tags($request->search);

            $query->where(function ($q) use ($searchTerm) {
                $q->where('full_name', 'like', "%{$searchTerm}%")
                    ->orWhere('email', 'like', "%{$searchTerm}%")
                    // احتياط: البحث أيضاً برقم الهاتف إذا كان موجوداً
                    ->orWhere('id', 'like', "%{$searchTerm}%");
            });
        }

        // 4. الاحتياط 4: تحديد الأعمدة المطلوبة فقط لتقليل حجم البيانات (Performance & Privacy)
        // لا ترسل كلمة المرور (Password) أو التوكنات حتى لو كانت مشفرة
        $query->select(['id', 'profile_image', 'full_name', 'email', 'role', 'is_active', 'created_at']);

        // 5. الترتيب وجلب الصفحات
        $users = $query->latest()->paginate(10);

        return response()->json($users);
    }


    public function toggleUserStatus(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            // إذا أرسل الفرونت إند حالة معينة نستخدمها، وإذا لم يرسل نقوم بتبديل الحالة الحالية تلقائياً
            if ($request->has('status')) {
                $user->is_active = ($request->status === 'active' || $request->status == 1);
            } else {
                $user->is_active = !$user->is_active;
            }

            $user->save();

            // إشعار تلقائي
            $statusText = $user->is_active ? "تم تفعيل حسابك بنجاح." : "تم تعطيل حسابك مؤقتاً.";

            // استخدام try-catch للإشعارات لضمان عدم توقف العملية إذا فشل إرسال الإيميل
            try {
                $user->notify(new \App\Notifications\AdminSystemNotification(
                    "تحديث حالة الحساب",
                    $statusText,
                    $user->is_active ? "success" : "warning"
                ));
            } catch (\Exception $e) {
                Log::error("Notification Error: " . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة الحساب بنجاح',
                'is_active' => (bool)$user->is_active
            ]);
        } catch (\Exception $e) {
            Log::error("Toggle Status Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في الخادم: ' . $e->getMessage()
            ], 500);
        }
    }

    /** حذف حساب مستخدم نهائياً (FR-A05) */
    public function destroyUser($id)
    {
        $user = User::findOrFail($id);
        // ملاحظة: الحذف نهائي وغير قابل للتراجع كما في المتطلبات
        $user->delete();
        return response()->json(['message' => 'تم حذف الحساب وجميع بياناته المرتبطة نهائياً']);
    }

    // ==========================================
    // ج) إدارة المؤسسات والتحقق (FR-A06 - FR-A08)
    // ==========================================

    public function getPendingOrganizations(Request $request)
    {
        try {
            // تصحيح: إضافة السهم -> قبل where، والتأكد من استدعاء query() لبدء بناء الاستعلام
            $query = Organization::with('user')->where('is_verified', false);

            // إضافة ميزة البحث إذا كنت ترغب في توفيرها للـ Frontend لاحقاً
            if ($request->has('search')) {
                $searchTerm = $request->search;
                $query->where('org_name', 'LIKE', "%{$searchTerm}%");
            }

            // تنفيذ الترقيم وجلب الأحدث
            $organizations = $query->latest()->paginate(10);

            return response()->json($organizations);
        } catch (\Exception $e) {
            // تسجيل الخطأ في الـ Log لغرض الديباجينج
            \Log::error("Error in getPendingOrganizations: " . $e->getMessage());

            return response()->json([
                'error' => 'حدث خطأ أثناء جلب البيانات',
                'message' => $e->getMessage() // احذف هذا السطر في مرحلة الإنتاج (Production) للأمان
            ], 500);
        }
    }
    /** الموافقة على تسجيل مؤسسة (FR-A07) */
    public function approveOrganization($id)
    {
        return DB::transaction(function () use ($id) {
            try {
                $org = Organization::findOrFail($id);
                $org->is_verified = true;
                $org->save();

                $org->user->notify(new AdminSystemNotification(
                    "تم قبول التسجيل",
                    "نهنئكم، تم قبول طلب انضمام مؤسستكم ({$org->org_name}) بنجاح.",
                    "success"
                ));

                return response()->json(['message' => 'تم تفعيل حساب المؤسسة بنجاح']);
            } catch (\Exception $e) {
                return response()->json(['error' => 'فشل في تحديث الحالة: ' . $e->getMessage()], 500);
            }
        });
    }

    /** رفض تسجيل مؤسسة مع ذكر السبب (FR-A08) */
    public function rejectOrganization(Request $request, $id)
    {
        
        $org = Organization::findOrFail($id);

      
        $org->delete();

        return response()->json(['message' => 'تم رفض الطلب وإبلاغ المؤسسة']);
    }

    // ==========================================
    // د) إدارة المحتوى (FR-A09 - FR-A10)
    // ==========================================

    public function getAllOpportunities(Request $request)
    {
        try {
            // 1. الإحصائيات (استخدام استعلامات بسيطة لضمان عدم الخطأ)
            $globalStats = [
                'total'   => \App\Models\Opportunity::count(),
                'open'    => \App\Models\Opportunity::where('status', 'open')->count(),
                'closed'  => \App\Models\Opportunity::where('status', 'closed')->count(),
                // إذا كان اسم العمود عندك مختلف عن deadline، قم بتغييره هنا
                'expired' => \App\Models\Opportunity::whereDate('deadline', '<', now())->count(),
            ];

            // 2. بناء الاستعلام الأساسي
            // تأكد من وجود علاقة organization في مودل User وعلاقة user في مودل Opportunity
            $query = \App\Models\Opportunity::with(['user.organization']);

            // حساب المقبولين (تأكد أن جدول applications يحتوي على status)
            $query->withCount(['applications as accepted_count' => function ($q) {
                $q->where('status', 'accepted');
            }]);

            // 3. البحث
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            }

            // 4. الفلترة
            if ($request->filled('status') && $request->status !== 'all') {
                if ($request->status === 'expired') {
                    $query->whereDate('deadline', '<', now());
                } else {
                    $query->where('status', $request->status);
                }
            }

            $opportunities = $query->latest()->paginate(12);

            return response()->json([
                'success' => true,
                'data' => $opportunities,
                'header_stats' => $globalStats
            ]);
        } catch (\Exception $e) {
            // في حال حدوث خطأ، سيظهر لك في Response الخاص بـ Inspect
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في السيرفر: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    /** إخفاء أو حذف فرصة مخالفة (FR-A10) */
    public function moderateOpportunity(Request $request, $id)
    {
        try {
            // 1. جلب الفرصة مع المستخدم (المؤسسة)
            $opportunity = \App\Models\Opportunity::with('user')->findOrFail($id);

            // 2. الحصول على السبب من الطلب
            $reason = $request->input('reason', 'محتوى غير مناسب لمعايير المنصة');

            // 3. إرسال الإشعار باستخدام الكلاس الجديد AdminSystemNotification
            if ($opportunity->user) {
                $opportunity->user->notify(new \App\Notifications\AdminSystemNotification(
                    "إجراء إداري: حذف فرصة", // العنوان
                    "تم حذف الفرصة ({$opportunity->title}) للسبب التالي: " . $reason, // الرسالة
                    "warning" // النوع
                ));
            }

            // 4. تنفيذ الحذف
            $opportunity->delete();

            return response()->json(['message' => 'تم حذف الفرصة بنجاح وإبلاغ المؤسسة']);
        } catch (\Exception $e) {
            // تسجيل الخطأ في Log لمعرفته لاحقاً
            Log::error("Moderation Error: " . $e->getMessage());

            return response()->json([
                'message' => 'حدث خطأ في السيرفر أثناء الحذف',
                'debug' => $e->getMessage() // احذفه في مرحلة الإنتاج
            ], 500);
        }
    }
    // في ملف AdminController.php

    // ==========================================
    // إغلاق فرصة تطوعية مع إرسال إشعار للمؤسسة
    // ==========================================
    public function closeOpportunity(Request $request, $id)
    {
        try {
            $opportunity = \App\Models\Opportunity::with('user')->findOrFail($id);

            // الحصول على السبب من الطلب أو وضع سبب افتراضي
            $reason = $request->input('reason', 'تم إغلاق الفرصة لمراجعة إدارية');

            // تحديث الحالة إلى مغلقة (closed)
            $opportunity->update(['status' => 'closed']);

            // إرسال الإشعار للمؤسسة (صاحب الفرصة)
            if ($opportunity->user) {
                $opportunity->user->notify(new \App\Notifications\AdminSystemNotification(
                    "تنبيه إداري: إغلاق فرصة", // العنوان
                    "نحيطكم علماً بأنه تم إغلاق الفرصة ({$opportunity->title}) من قبل الإدارة. السبب: " . $reason, // الرسالة
                    "warning" // نوع الإشعار (يظهر باللون الأصفر أو البرتقالي في الواجهة)
                ));
            }

            return response()->json(['message' => 'تم إغلاق الفرصة وإخطار المؤسسة بنجاح']);
        } catch (\Exception $e) {
            Log::error("Close Opportunity Error: " . $e->getMessage());
            return response()->json(['message' => 'حدث خطأ أثناء إغلاق الفرصة'], 500);
        }
    }

    // ==========================================
    // إعادة فتح فرصة تطوعية مع إرسال إشعار للمؤسسة
    // ==========================================
    public function openOpportunity(Request $request, $id)
    {
        try {
            $opportunity = \App\Models\Opportunity::with('user')->findOrFail($id);

            $reason = $request->input('reason', 'تمت الموافقة على إعادة تفعيل الفرصة');

            // تحديث الحالة إلى مفتوحة (open)
            $opportunity->update(['status' => 'open']);

            // إرسال الإشعار للمؤسسة
            if ($opportunity->user) {
                $opportunity->user->notify(new \App\Notifications\AdminSystemNotification(
                    "تنبيه إداري: تفعيل فرصة",
                    "تمت الموافقة على إعادة فتح الفرصة الخاصة بكم ({$opportunity->title}). ملاحظة الإدارة: " . $reason,
                    "success" // نوع الإشعار (يظهر باللون الأخضر)
                ));
            }

            return response()->json(['message' => 'تم فتح الفرصة وإخطار المؤسسة بنجاح']);
        } catch (\Exception $e) {
            Log::error("Open Opportunity Error: " . $e->getMessage());
            return response()->json(['message' => 'حدث خطأ أثناء فتح الفرصة'], 500);
        }
    }

    // ==========================================
    // هـ) التقارير والإحصائيات (FR-A11 - FR-A12)
    // ==========================================

    /** لوحة الإحصائيات الرقمية (FR-A11) */
    public function getDashboardStats()
    {
        $now = now();
        $lastMonth = now()->subMonth();

        $studentsCurrent = User::where('role', 'student')->whereMonth('created_at', $now->month)->count();
        $studentsLast = User::where('role', 'student')->whereMonth('created_at', $lastMonth->month)->count();
        $studentGrowth = $studentsLast > 0 ? (($studentsCurrent - $studentsLast) / $studentsLast) * 100 : ($studentsCurrent > 0 ? 100 : 0);

        $orgCurrent = Organization::where('is_verified', true)->whereMonth('created_at', $now->month)->count();
        $orgLast = Organization::where('is_verified', true)->whereMonth('created_at', $lastMonth->month)->count();
        $orgGrowth = $orgLast > 0 ? (($orgCurrent - $orgLast) / $orgLast) * 100 : ($orgCurrent > 0 ? 100 : 0);

        $oppCurrent = Opportunity::where('status', 'open')->whereMonth('created_at', $now->month)->count();
        $oppLast = Opportunity::where('status', 'open')->whereMonth('created_at', $lastMonth->month)->count();
        $oppGrowth = $oppLast > 0 ? (($oppCurrent - $oppLast) / $oppLast) * 100 : ($oppCurrent > 0 ? 100 : 0);

        $hoursCurrent = HourLog::where('status', 'approved')->whereMonth('created_at', $now->month)->sum('hours');
        $hoursLast = HourLog::where('status', 'approved')->whereMonth('created_at', $lastMonth->month)->sum('hours');
        $hoursGrowth = $hoursLast > 0 ? (($hoursCurrent - $hoursLast) / $hoursLast) * 100 : ($hoursCurrent > 0 ? 100 : 0);

        return response()->json([
            'total_students' => User::where('role', 'student')->count(),
            'verified_organizations' => Organization::where('is_verified', true)->count(),
            'active_opportunities' => Opportunity::where('status', 'open')->count(),
            'finished_opportunities' => Opportunity::where('status', 'closed')->count(),
            'total_applications' => Application::count(),
            'total_documented_hours' => HourLog::where('status', 'approved')->sum('hours'),
            'student_growth' => round($studentGrowth, 1),
            'org_growth' => round($orgGrowth, 1),
            'opp_growth' => round($oppGrowth, 1),
            'hours_growth' => round($hoursGrowth, 1)
        ]);
    }

    /** تصدير التقارير (FR-A12) */
    public function exportGeneralReport()
    {
        // تجهيز بيانات شاملة للتصدير (JSON جاهز للتحويل لـ PDF/Excel في الواجهة)
        $data = Organization::withCount('opportunities')
            ->get()
            ->map(function ($org) {
                return [
                    'المؤسسة' => $org->org_name,
                    'الفرص المنشورة' => $org->opportunities_count,
                    'ساعات الأثر' => HourLog::whereHas('opportunity', fn($q) => $q->where('user_id', $org->user_id))->sum('hours'),
                ];
            });
        return response()->json($data);
    }
    // ==========================================
    // إحصائيات الرسوم البيانية (Visual Charts Data)
    // ==========================================

    /**
     * 1. رسم بياني لنمو المستخدمين (Monthly Growth)
     * يعرض عدد المسجلين الجدد لكل شهر خلال السنة الحالية
     */
    public function getUserGrowthChart()
    {
        $year = date('Y');

        $students = User::where('role', 'student')->whereYear('created_at', $year)
            ->selectRaw("MONTH(created_at) as month, count(*) as count")
            ->groupBy('month')->pluck('count', 'month')->toArray();

        $orgs = Organization::where('is_verified', true)->whereYear('created_at', $year)
            ->selectRaw("MONTH(created_at) as month, count(*) as count")
            ->groupBy('month')->pluck('count', 'month')->toArray();

        $opportunities = Opportunity::whereYear('created_at', $year)
            ->selectRaw("MONTH(created_at) as month, count(*) as count")
            ->groupBy('month')->pluck('count', 'month')->toArray();

        $currentMonth = date('n');
        $data = [];

        for ($i = 1; $i <= $currentMonth; $i++) {
            $data[] = [
                'label' => Carbon::create()->month($i)->format('M'),
                'students' => $students[$i] ?? 0,
                'organizations' => $orgs[$i] ?? 0,
                'opportunities' => $opportunities[$i] ?? 0,
            ];
        }

        return response()->json($data);
    }

    /**
     * 2. رسم بياني دائري لتوزيع المتطوعين حسب الجنس (Gender Pie Chart)
     */
    public function getGenderDistributionChart()
    {
        $stats = DB::table('user_profiles')
            ->selectRaw('gender, count(*) as count')
            ->groupBy('gender')
            ->get();

        return response()->json($stats);
    }

    /**
     * 3. رسم بياني للأداء: الساعات الموثقة شهرياً (Impact Chart)
     */
    public function getMonthlyHoursChart()
    {
        $hours = HourLog::where('status', 'approved')
            ->whereYear('created_at', date('Y'))
            ->selectRaw("MONTH(created_at) as month, SUM(hours) as total_hours")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => Carbon::create()->month($item->month)->format('M'),
                    'hours' => $item->total_hours
                ];
            });

        return response()->json($hours);
    }
    // ==========================================
    // 6. إدارة المهارات (Skill CRUD & Charts)
    // ==========================================

    // جلب كافة المهارات
    public function getSkillsList()
    {
        // جلب المهارات مع عد المستخدمين المرتبطين بكل مهارة من جدول user_skill
        $skills = \App\Models\Skill::withCount('users')->get();
        return response()->json($skills);
    }

    /** إنشاء مهارة جديدة */
    public function storeSkill(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:skills,name|max:255'
        ]);

        $skill = \App\Models\Skill::create([
            'name' => $request->name
        ]);

        return response()->json(['message' => 'تم إضافة المهارة بنجاح', 'data' => $skill], 201);
    }

    /** تعديل مهارة موجودة */
    public function updateSkill(Request $request, $id)
    {
        $skill = \App\Models\Skill::findOrFail($id);
        $request->validate(['name' => 'required|unique:skills,name,' . $id]);

        $skill->update(['name' => $request->name]);
        return response()->json(['message' => 'تم تعديل المهارة بنجاح', 'data' => $skill]);
    }
    /** تعديل مهارة موجودة */


    /** حذف مهارة */
    public function deleteSkill($id)
    {
        $skill = Skill::findOrFail($id);
        $skill->delete();
        return response()->json(['message' => 'تم حذف المهارة بنجاح']);
    }

    /** رسم إحصائي: المهارات الأكثر طلباً (Top Skills Chart Data) */
    public function getSkillsChartData()
    {
        // نجلب المهارات مع عدد الفرص المرتبطة بها
        $data = Skill::withCount('opportunities')
            ->orderBy('opportunities_count', 'desc')
            ->take(10) // أفضل 10 مهارات
            ->get()
            ->map(function ($skill) {
                return [
                    'skill' => $skill->name,
                    'count' => $skill->opportunities_count
                ];
            });

        return response()->json($data);
    }
    /**
     * 4. ميزان المهارات: الفجوة بين الطلب والعرض (Skill Gap Chart)
     * يعرض مقارنة بين المهارات التي تملكها الطلاب والمهارات التي تطلبها المؤسسات
     */
    public function getSkillComparisonChart()
    {
        $skills = Skill::withCount(['opportunities', 'users'])
            ->orderBy('opportunities_count', 'desc')
            ->take(6)
            ->get()
            ->map(function ($skill) {
                return [
                    'skill_name' => $skill->name,
                    'demanded' => $skill->opportunities_count,
                    'available' => $skill->users_count
                ];
            });

        return response()->json($skills);
    }
    /** جلب آخر النشاطات أو السجلات في النظام */
    public function getLatestLogs()
    {
        $logs = Application::with(['user', 'opportunity'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'type' => 'user',
                    'message' => "طلب تقديم جديد لفرصة {$log->opportunity->title}",
                    'user_affected' => $log->user->full_name ?? 'مستخدم',
                    'timestamp' => $log->created_at->diffForHumans(),
                ];
            });

        return response()->json($logs);
    }
    /**
     * جلب الإحصائيات التحليلية المتقدمة لصفحة Analytics
     */

    public function getAdvancedAnalytics()
    {
        // 1. إحصائيات الفرص
        $opportunityStats = Opportunity::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')->get();

        // 2. تحليل الساعات الموثقة
        $monthlyHours = HourLog::where('status', 'approved')
            ->where('created_at', '>=', now()->subMonths(6))
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(hours) as total_hours")
            ->groupBy('month')->orderBy('month')->get();

        // 3. المهارات الأكثر طلباً
        // 3. المهارات الأكثر طلباً مع عدد المستخدمين الذين يمتلكونها
        $topSkills = Skill::withCount(['opportunities', 'users']) // أضفنا users هنا
            ->orderBy('opportunities_count', 'desc')
            ->take(5)
            ->get();

        // --- إضافات لإصلاح أخطاء الـ Frontend ---

        // 4. جلب المؤسسات (للـ Export)
        $organizations = Organization::withCount('opportunities')->get();

        // 5. جلب المتطوعين (للـ Export)
        $volunteers = User::where('role', 'student')
            ->with('profile')
            ->get();

        // 6. جلب الفرص كاملة (للـ Export)
        $opportunities = Opportunity::with(['user.organization'])->get();

        return response()->json([
            'opportunityStats' => $opportunityStats,
            'monthlyHours'     => $monthlyHours,
            'topSkills'        => $topSkills,
            'organizations'    => $organizations, // تم الإضافة
            'volunteers'       => $volunteers,    // تم الإضافة
            'opportunities'    => $opportunities, // تم الإضافة

        ]);
    }
}
