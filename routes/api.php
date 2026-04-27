<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// استيراد كافة المتحكمات (Controllers)
use App\Http\Controllers\{
    AuthController,
    ProfileController,
    OpportunityController,
    ApplicationController,
    AttachmentController,
    HourLogController,
    ReviewController,
    SkillController,
    StudentController,
    CertificateController,
    NotificationController,
    AdminController
};

/*
|--------------------------------------------------------------------------
| API Routes - منصة مساندة (Musanada Platform)
|--------------------------------------------------------------------------
*/

// --- 1. المسارات العامة (Public Routes) ---
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/opportunities', [OpportunityController::class, 'index']);
    Route::get('/opportunities/{id}', [OpportunityController::class, 'show']);
});
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/skills', [SkillController::class, 'index']);

// --- 2. المسارات المحمية (Protected Routes) ---
Route::middleware('auth:sanctum')->group(function () {

    // --- أ. الحساب والهوية ---
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user()->load(['profile.skills', 'organization']);
    });

    // --- ب. الملف الشخصي والمرفقات ---
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::post('/update', [ProfileController::class, 'update']);
        Route::post('/skills', [ProfileController::class, 'updateSkills']);
        Route::post('/update-password', [ProfileController::class, 'updatePassword']);
        Route::get('/stats', [OpportunityController::class, 'getStudentStats']); // إحصائيات الطالب
    });

    Route::post('/user/update-location', [ProfileController::class, 'updateLocation']);

    Route::prefix('attachments')->group(function () {
        Route::get('/my', [AttachmentController::class, 'myAttachments']);
        Route::post('/upload', [AttachmentController::class, 'store']);
        Route::delete('/{id}', [AttachmentController::class, 'destroy']);
    });

    // --- ج. الفرص التطوعية (Opportunities) ---
    Route::prefix('opportunities')->group(function () {
        Route::post('/', [OpportunityController::class, 'store']); // إنشاء فرصة
        Route::get('/latest-applicants', [OpportunityController::class, 'getLatestApplicants']); // للمؤسسة (الرئيسية)
        Route::get('/{id}/applicants', [OpportunityController::class, 'getApplicants']);
        Route::get('/{id}/stats', [OpportunityController::class, 'getOpportunityApplicantsStats']);
        Route::post('/{id}/toggle-status', [OpportunityController::class, 'toggleStatus']);
        Route::get('/{id}/top-candidates', [OpportunityController::class, 'getTopCandidates']);
        Route::patch('/{id}/status', [OpportunityController::class, 'updateStatus']);
    });

    // --- د. مسارات خاصة بالمؤسسة (Org Section) ---
    Route::prefix('org')->group(function () {
        Route::get('/dashboard-stats', [OpportunityController::class, 'getDashboardStats']); // الإحصائيات مع الرسم البياني
        Route::get('/my-opportunities', [OpportunityController::class, 'myOpportunities']);
        Route::get('/latest-applicants', [OpportunityController::class, 'getLatestApplicants']);
        Route::get('/recent-hour-logs', [HourLogController::class, 'getRecentLogs']); // لم ننشئها بعد ولكنها موجودة في الـ UI // عرض فرصه فقط
        Route::post('/opportunities', [OpportunityController::class, 'store']); // إضافة فرصة
        Route::post('/opportunities/{id}', [OpportunityController::class, 'update']); // تحديث (استخدم Post مع _method لرفع الصور)
        Route::delete('/opportunities/{id}', [OpportunityController::class, 'destroy']); // حذف
        Route::patch('/opportunities/{id}/toggle', [OpportunityController::class, 'toggleStatus']); // تبديل الحالة
        Route::post('/reviews', [ReviewController::class, 'store']);
        Route::post('/log-hours', [HourLogController::class, 'store']);
        Route::patch('/applications/{id}/complete', [ApplicationController::class, 'completeApplication']);
        // إدارة المتقدمين للفرصة
        Route::get('/opportunities/{id}/applicants', [OpportunityController::class, 'getApplicants']); // جلب قائمة المتقدمين
        Route::get('/opportunities/{id}/stats', [OpportunityController::class, 'getOpportunityApplicantsStats']); // إحصائيات المتقدمين لفرصة محددة
        Route::patch('/applications/{id}/status', [OpportunityController::class, 'updateApplicantStatus']); // قبول أو رفض متقدم
        Route::get('/opportunities/{id}/total-hours', [OpportunityController::class, 'getOpportunityTotalHours']);
        // --- مسارات إدارة الشهادات ---
        Route::get('/certificates/verify/{code}', [CertificateController::class, 'verify']);
        Route::get('/get-accepted-volunteers', [OpportunityController::class, 'getAcceptedVolunteers']);
        Route::get('/all-applications', [ApplicationController::class, 'allApplicationsForOrg']);
        // 1. جلب المتطوعين المؤهلين (بانتظار الإصدار)
        Route::get('/certificates/eligible', [CertificateController::class, 'getEligibleVolunteers']);
        Route::get('/all-pending-applications', [OpportunityController::class, 'getPendingOrganizations']);

        // 2. جلب الشهادات التي أصدرتها المؤسسة (الجاهزة)
        // ملاحظة: غيرنا الدالة من myCertificates إلى issuedByMe لأنها الأنسب للمؤسسة
        Route::get('/certificates/issued', [CertificateController::class, 'issuedByMe']);

        // 3. إصدار شهادة جديدة
        Route::post('/certificates/issue', [CertificateController::class, 'issue']);

        // 4. تحديث بيانات الشهادة (التي طلبتها للتعديل)
        Route::put('/certificates/{id}', [CertificateController::class, 'update']);

        // 5. حذف/إبطال شهادة
        Route::delete('/certificates/{id}', [CertificateController::class, 'destroy']);
        // 2. جلب ساعات متطوع محدد في فرصة معينة
        Route::get('/opportunities/{id}/volunteers/{volunteer_id}/hours', [OpportunityController::class, 'getVolunteerHoursInOpportunity']);
    });

    // --- هـ. مسارات خاصة بالطالب (Student Section) ---
    Route::prefix('student')->group(function () {
          Route::get('/', [StudentController::class, 'index']);
        Route::get('/recommendations', [OpportunityController::class, 'recommended']);
        Route::get('/stats', [OpportunityController::class, 'getStats']);
        Route::get('/all-stats', [OpportunityController::class, 'getStudentStats']);
        Route::get('/my-logs', [HourLogController::class, 'myLogs']);
        Route::get('/certificates', [CertificateController::class, 'myCertificates']);
        Route::get('/dashboard', [StudentController::class, 'getDashboardData']);
        // أضفه ضمن مجموعة middleware('auth:sanctum')
        Route::get('/export-logs', [HourLogController::class, 'exportLogs']);
        Route::delete('/applications/{opportunityId}', [OpportunityController::class, 'destroyApplication']);
    });
    Route::prefix('logs')->group(function () {
        // دوال الإحصائيات الجديدة (يجب إضافتها لتعمل)
        Route::get('/volunteer/{volunteerId}/total', [HourLogController::class, 'getTotalVolunteerHours']);
        Route::get('/volunteer/{volunteerId}/org/{orgId}', [HourLogController::class, 'getVolunteerHoursInOrganization']);
        Route::get('/opportunity/{opportunityId}/total', [HourLogController::class, 'getTotalHoursInOpportunity']);

        // المراجعة والاعتماد
        Route::get('/opportunity/{opportunityId}', [HourLogController::class, 'index']);
        Route::patch('/{id}/verify', [HourLogController::class, 'verify']); // تأكد من بناء دالة verify في الكنترولر
    });

    // --- و. طلبات التقديم (Applications) ---
    Route::get('/my-applications', [ApplicationController::class, 'myApplications']);
    Route::post('/applications', [ApplicationController::class, 'store']);
    Route::put('/applications/{id}/status', [ApplicationController::class, 'updateStatus']); // قبول/رفض
    Route::patch('/applications/{id}/status', function (Request $request, $id) { // تحديث سريع للحالة
        $application = \App\Models\Application::findOrFail($id);
        $application->update(['status' => $request->status]);
        return response()->json(['message' => 'تم تحديث الحالة بنجاح']);
    });
    Route::delete('/applications/{opportunity_id}', [OpportunityController::class, 'destroyApplication']);

    // --- ز. سجل الساعات والعمل (Work Logs) ---
    Route::post('/logs/work', [HourLogController::class, 'store']); // الطالب يسجل
    Route::get('/logs/opportunity/{opportunityId}', [HourLogController::class, 'index']); // المؤسسة تراجع
    Route::patch('/logs/{id}/verify', [HourLogController::class, 'verify']); // المؤسسة تعتمد الساعات
    Route::get('/opportunities/{opportunityId}/users/{userId}/hours', [HourLogController::class, 'getStudentHoursInOpportunity']);
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::get('/student/{id}/reviews', [ReviewController::class, 'studentReviews']);

    // --- ط. البحث والبروفايلات الشاملة ---
    Route::get('/students/search', [StudentController::class, 'searchBySkill']);
    Route::get('/students/{id}/portfolio', [StudentController::class, 'getFullPortfolio']);
    // --- ي. الإشعارات ---
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });


    // جميع مسارات مدير النظام محمية بـ Middleware للتأكد من الهوية والصلاحية
    Route::prefix('admin')->middleware(['auth:sanctum', 'isAdmin'])->group(function () {

        // --- 1. إدارة المستخدمين (الطلاب والمؤسسات) ---
        // جلب قائمة بجميع المستخدمين مع إمكانية البحث والفلترة (FR-A02)
        Route::get('/users', [AdminController::class, 'getUsersDirectory']);
        Route::patch('opportunities/{id}/status', [OpportunityController::class, 'toggleStatus']);
        // تفعيل أو تعطيل حساب مستخدم (إرسال إشعار تلقائي) (FR-A03, FR-A04)
        Route::post('/users/{id}/toggle-status', [AdminController::class, 'toggleUserStatus']);

        // حذف حساب مستخدم نهائياً من قاعدة البيانات (FR-A05)
        Route::delete('/users/{id}', [AdminController::class, 'destroyUser']);


        // --- 2. إدارة المؤسسات والتحقق من الهوية ---
        // عرض طلبات التسجيل الجديدة للمؤسسات التي لم يتم التحقق منها بعد (FR-A06)
        Route::get('/organizations/pending', [AdminController::class, 'getPendingOrganizations']);

        Route::post('/organizations/{id}/approve', [AdminController::class, 'approveOrganization']);
        Route::post('/organizations/{id}/reject', [AdminController::class, 'rejectOrganization']);

        // --- 3. إدارة المحتوى والرقابة ---
        // مراجعة كافة الفرص التطوعية المنشورة على المنصة (FR-A09)
        Route::get('/opportunities', [AdminController::class, 'getAllOpportunities']);

        // حذف فرصة مخالفة للسياسات مع إبلاغ المؤسسة بالسبب (FR-A10)
        Route::delete('/opportunities/{id}', [AdminController::class, 'moderateOpportunity']);
        Route::patch('/opportunities/{id}/close', [AdminController::class, 'closeOpportunity']);
        Route::patch('/opportunities/{id}/open', [AdminController::class, 'openOpportunity']);

        // --- 4. الإحصائيات والتقارير ---
        // جلب الأرقام الرئيسية للوحة التحكم (عدد الطلاب، المؤسسات، الساعات...) (FR-A11)
        Route::get('/dashboard/stats', [AdminController::class, 'getDashboardStats']);

        // تصدير تقرير شامل عن أداء المنصة والمؤسسات (JSON جاهز للتصدير) (FR-A12)
        Route::get('/reports/export', [AdminController::class, 'exportGeneralReport']);


        // --- 5. مسارات الرسوم البيانية (Charts) ---
        // بيانات رسم بياني لنمو المستخدمين شهرياً خلال السنة الحالية
        Route::get('/charts/user-growth', [AdminController::class, 'getUserGrowthChart']);

        // بيانات رسم بياني دائري لتوزيع المتطوعين حسب الجنس
        Route::get('/charts/gender-distribution', [AdminController::class, 'getGenderDistributionChart']);

        // بيانات رسم بياني لتطور عدد الساعات الموثقة شهرياً
        Route::get('/charts/monthly-hours', [AdminController::class, 'getMonthlyHoursChart']);


        // --- 6. إدارة المهارات (Skill Management) ---
        // إضافة مهارة جديدة إلى النظام
        Route::post('/skills', [AdminController::class, 'storeSkill']);
        Route::get('/skills', [AdminController::class, 'getSkillsList']);
        // تحديث اسم مهارة موجودة
        Route::put('/skills/{id}', [AdminController::class, 'updateSkill']);

        // حذف مهارة من النظام
        Route::delete('/skills/{id}', [AdminController::class, 'deleteSkill']);

        // جلب بيانات أكثر 10 مهارات مطلوبة في الفرص (للرسوم البيانية)
        Route::get('/skills/top-chart', [AdminController::class, 'getSkillsChartData']);

        // جلب بيانات تحليل الفجوة بين المهارات المطلوبة والمتوفرة لدى الطلاب
        // داخل مجموعة الـ admin في ملف routes/api.php
        Route::get('/charts/skills-comparison', [AdminController::class, 'getSkillComparisonChart']);

        Route::get('/system/logs/latest', [AdminController::class, 'getLatestLogs']);

        Route::get('/advanced-analytics', [AdminController::class, 'getAdvancedAnalytics']);
    });
});
