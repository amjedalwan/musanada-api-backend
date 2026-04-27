<?php

namespace App\Http\Controllers;

use App\Models\HourLog;
use App\Models\UserProfile;
use App\Models\Opportunity;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HourLogController extends Controller
{
    /**
     * 1. تسجيل ساعات عمل جديدة للمتطوع (للمؤسسة فقط)
     */
    public function store(Request $request)
    {
        // التحقق من المدخلات
        $data = $request->validate([
            'user_id'        => 'required|exists:users,id',
            'opportunity_id' => 'required|exists:opportunities,id',
            'hours'          => 'required|numeric|min:0.5|max:12',
            'date_logged'    => 'required|date|before_or_equal:today',
            'notes'          => 'nullable|string',
        ]);

        try {
            return DB::transaction(function () use ($data) {
                // التأكد من أن المؤسسة الحالية هي صاحبة الفرصة
                $opportunity = Opportunity::findOrFail($data['opportunity_id']);
                if ($opportunity->user_id !== Auth::id()) {
                    return response()->json(['message' => 'غير مصرح لك بتسجيل ساعات لهذه الفرصة'], 403);
                }

                // التأكد من أن المتطوع مقبول فعلياً في هذه الفرصة
                $isAccepted = Application::where('user_id', $data['user_id'])
                    ->where('opportunity_id', $data['opportunity_id'])
                    ->where('status', 'accepted')
                    ->exists();

                if (!$isAccepted) {
                    return response()->json(['message' => 'المتطوع غير مقبول في هذه الفرصة'], 400);
                }

                // إنشاء سجل الساعات
                $log = HourLog::create([
                    'user_id'        => $data['user_id'],
                    'opportunity_id' => $data['opportunity_id'],
                    'hours'          => $data['hours'],
                    'date_logged'    => $data['date_logged'],
                    'notes'          => $data['notes'] ?? null,
                    'status'         => 'approved', // يتم الاعتماد تلقائياً لأن المنشئ هو المؤسسة
                ]);

                // تحديث إجمالي الساعات في ملف المستخدم (UserProfile)
                // ملاحظة: تم استخدام total_volunteer_hours بناءً على الميجريشن الخاص بك
                $profile = UserProfile::firstOrCreate(
                    ['user_id' => $data['user_id']],
                    ['total_volunteer_hours' => 0]
                );

                $profile->increment('total_volunteer_hours', $data['hours']);

                

                return response()->json([
                    'success' => true,
                    'message' => 'تم تسجيل الساعات وتحديث الملف الشخصي بنجاح',
                    'log'     => $log->load('user:id,full_name')
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حفظ البيانات: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 2. عرض سجل الساعات لفرصة معينة (للمؤسسة)
     */
    public function index($opportunityId)
    {
        $opportunity = Opportunity::findOrFail($opportunityId);

        // التحقق من الصلاحية
        if ($opportunity->user_id !== Auth::id()) {
            return response()->json(['message' => 'غير مصرح لك بالاطلاع على سجلات هذه الفرصة'], 403);
        }

        $logs = HourLog::where('opportunity_id', $opportunityId)
            ->with('user:id,full_name')
            ->orderBy('date_logged', 'desc')
            ->get();

        return response()->json($logs);
    }
/**
 * تصدير سجلات الساعات الخاصة بالمتطوع الحالي
 */
public function exportLogs(Request $request)
{
    $user = Auth::user();
    // في HourLogController.php
$logs = HourLog::with(['opportunity.user.organization']) // تعمق أكثر في العلاقات
    ->where('user_id', $user->id)
    ->get();
    // جلب كافة الساعات المعتمدة للمتطوع

    if ($logs->isEmpty()) {
        return response()->json(['message' => 'لا توجد سجلات لتصديرها'], 404);
    }

    return response()->json([
        'status' => true,
        'user_name' => $user->full_name,
        'data' => $logs
    ]);
}
    /**
     * 3. جلب آخر سجلات الساعات للمؤسسة (للداشبورد)
     */
    public function getRecentLogs(Request $request)
    {
        $user = Auth::user();

        // جلب السجلات للفرص التي تملكها هذه المؤسسة فقط
        $logs = HourLog::whereHas('opportunity', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->with(['user:id,full_name', 'opportunity:id,title'])
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'logs'    => $logs
        ]);
    }

    /**
     * 4. عرض إجمالي الساعات والسجلات (للمتطوع الحالي)
     */
    public function myLogs()
    {
        $user = Auth::user();

        // جلب سجلات المستخدم مع بيانات الفرص المرتبطة
        $logs = HourLog::where('user_id', $user->id)
            ->with('opportunity:id,title')
            ->orderBy('date_logged', 'desc')
            ->get();

        // جلب إجمالي الساعات من البروفايل
        $totalHours = UserProfile::where('user_id', $user->id)->value('total_volunteer_hours') ?? 0;

        return response()->json([
            'total_verified_hours' => $totalHours,
            'logs'                 => $logs
        ]);
    }

    public function getTotalVolunteerHours($volunteerId)
    {
        // جلب مجموع الساعات المعتمدة فقط من جميع السجلات المرتبطة بهذا المستخدم
        $totalHours = \App\Models\HourLog::where('user_id', $volunteerId)
            ->where('status', 'approved')
            ->sum('hours');

        return response()->json([
            'volunteer_id' => $volunteerId,
            'total_hours'  => (float) $totalHours
        ]);
    }
    public function getVolunteerHoursInOrganization($volunteerId, $organizationId)
    {
        // نصل للساعات عن طريق الفرص التي تملكها هذه المؤسسة فقط
        $totalHours = \App\Models\HourLog::where('user_id', $volunteerId)
            ->where('status', 'approved')
            ->whereHas('opportunity', function ($query) use ($organizationId) {
                $query->where('user_id', $organizationId);
                // ملاحظة: هنا نفترض أن user_id في جدول الفرص هو ID المؤسسة
            })
            ->sum('hours');

        return response()->json([
            'volunteer_id'    => $volunteerId,
            'organization_id' => $organizationId,
            'total_hours'     => (float) $totalHours
        ]);
    }

    public function getTotalHoursInOpportunity($opportunityId)
    {
        $total = HourLog::where('opportunity_id', $opportunityId)
            ->where('status', 'verified')
            ->sum('hours');

        return response()->json(['total_hours' => $total]);
    }
    public function getStudentHoursInOpportunity($opportunityId, $userId)
{
    // حساب مجموع الساعات من جدول hour_logs 
    // بشرط أن تكون الحالة 'approved' (أو حسب الحالة التي تعتمدها للشهادة)
    $totalHours = \App\Models\HourLog::where('opportunity_id', $opportunityId)
        ->where('user_id', $userId)
        ->where('status', 'approved') // نجمع فقط الساعات التي وافقت عليها المؤسسة
        ->sum('hours');

    return response()->json([
        'opportunity_id' => (int)$opportunityId,
        'user_id'        => (int)$userId,
        'total_hours'    => (float)$totalHours
    ]);
}
}
