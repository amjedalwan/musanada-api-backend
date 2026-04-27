<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\HourLog;
use App\Models\Opportunity;
use App\Models\Review;
use App\Models\User;
use App\Models\UserProfile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OpportunityController extends Controller
{
    /**
     * 1. عرض كافة الفرص مع الفلترة المتقدمة
     */
    public function index(Request $request)
    {
        $opportunities = Opportunity::query()
            ->with(['user.organization', 'applications', 'skills'])
            ->active() // Scope مخصص
            ->filter($request?->all()) // Scope مخصص للفلترة
            ->latest()
            ->withCount([
                'applications as accepted_count' => fn($q) => $q->where('status', 'accepted')
            ])
            ->paginate(12);

        return response()->json($opportunities);
    }


    /**
     * استخراج إحصائيات المتقدمين لفرصة معينة بالـ ID
     * Accessible by: Everyone (Public/Authenticated)
     */
    public function getOpportunityApplicantsStats($id)
    {
        // 1. التحقق من وجود الفرصة أولاً
        $opportunity = Opportunity::find($id);

        if (!$opportunity) {
            return response()->json([
                'message' => 'عذراً، هذه الفرصة غير موجودة'
            ], 404);
        }

        // 2. حساب الإحصائيات من جدول الطلبات (Applications) المرتبط بهذه الفرصة
        // استخدام selectRaw لسرعة الأداء في استعلام واحد
        $stats = Application::where('opportunity_id', $id)
            ->selectRaw("
            count(*) as total_applicants,
            count(case when status = 'accepted' then 1 end) as accepted_count,
            count(case when status = 'rejected' then 1 end) as rejected_count,
            count(case when status = 'pending' then 1 end) as pending_count
        ")
            ->first();

        // 3. إرجاع البيانات بشكل منظم
        return response()->json([
            'opportunity_id' => (int) $id,
            'opportunity_title' => $opportunity->title,
            'stats' => [
                'total'    => (int) $stats->total_applicants,
                'accepted' => (int) $stats->accepted_count,
                'rejected' => (int) $stats->rejected_count,
                'pending'  => (int) $stats->pending_count,
                // إضافة نسبة القبول (اختياري)
                'fill_rate' => $opportunity->required_volunteers > 0
                    ? round(($stats->accepted_count / $opportunity->required_volunteers) * 100, 2) . '%'
                    : '0%'
            ]
        ]);
    }
    /**
     * عرض تفاصيل فرصة واحدة مع التحقق من حالة التقديم
     */
    public function myOpportunities(Request $request)
    {
        try {
            $user = $request->user();

            $opportunities = Opportunity::where('user_id', $user->id)
                ->withCount([
                    'applications as total_applicants',
                    'applications as accepted_count' => fn($q) => $q->where('status', 'accepted'),
                    'applications as completed_count' => fn($q) => $q->where('status', 'completed')
                ])
                // أضف هذا الجزء لجلب مجموع الساعات من جدول hour_logs
                ->withSum(['hourLogs as total_logged_hours' => function ($query) {
                    $query->where('status', 'approved');
                }], 'hours')
                ->withSum(['hourLogs as pending_hours' => function ($query) {
                    $query->where('status', 'pending');
                }], 'hours')
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return response()->json($opportunities);
        } catch (\Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء جلب بياناتك'], 500);
        }
    }
    /**
     * تحديث بيانات فرصة موجودة في جدول opportunities
     * المسار الفيزيائي للصورة: storage/app/public/opportunities
     */
    public function update(Request $request, $id)
    {
        $opportunity = Opportunity::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();
        // التأكد من أن الفرصة تخص المستخدم (المؤسسة) المسجل حالياً
        try {
            $opportunity = Opportunity::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            // التحقق من الحقول بناءً على هيكلية جدول opportunities في المايجريشن
            $validatedData = $request->validate([
                'title'               => 'sometimes|required|string|max:255',
                'description'         => 'sometimes|required|string',
                'location'            => 'sometimes|required|string',
                'lat'                 => 'nullable|numeric',
                'lng'                 => 'nullable|numeric',
                'required_volunteers' => 'sometimes|required|integer|min:1',
                'start_date'          => 'sometimes|required|date',
                'duration'            => 'sometimes|required|string',
                'deadline'            => 'sometimes|required|date',
                'type'                => 'sometimes|required|in:voluntary,training,course',
                // الجنس كما ورد في المايجريشن: male, female, both
                'gender'              => 'sometimes|required|in:male,female,both',
                // الحالة كما وردت في المايجريشن: open, closed, completed
                'status'              => 'sometimes|in:open,closed,completed,hidden',
                // الحقل المرسل من React باسم cover_image
                'cover_image'         => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
                'skill_ids'           => 'sometimes|array',
                'skill_ids.*'         => 'exists:skills,id',
            ]);

            // 1. معالجة تحديث الصورة (cover_image)
            if ($request->hasFile('cover_image')) {

                // حذف الصورة القديمة إذا كانت موجودة لتوفير مساحة السيرفر
                if ($opportunity->cover_image) {
                    // نأخذ القيمة الخام للمسار من قاعدة البيانات لتجنب تعارض الـ Accessor
                    $oldPath = $opportunity->getRawOriginal('cover_image');
                    if ($oldPath) {
                        \Storage::disk('public')->delete($oldPath);
                    }
                }

                $path = $request->file('cover_image')->store('opportunities', 'public');

                // تعيين المسار الجديد في مصفوفة البيانات المراد تحديثها
                $validatedData['cover_image'] = $path;
            }

            // 2. تحديث البيانات الأساسية في جدول opportunities
            $opportunity->update($validatedData);

            // 3. تحديث المهارات في الجدول الوسيط (opportunity_skill)
            // دالة sync تقوم بحذف الارتباطات القديمة وإضافة الجديدة تلقائياً
            if (isset($validatedData['skill_ids'])) {
                $opportunity->skills()->sync($validatedData['skill_ids']);
            }

            // إرجاع استجابة JSON مع تحميل المهارات المحدثة
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات الفرصة بنجاح',
                'data'    => $opportunity->load('skills')
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ فني أثناء تحديث إحصائيات لوحة التحكم' . config('app.debug') ? $e->getMessage() : 'Server Error',
                // لا تظهر الخطأ الحقيقي للمستخدم في بيئة الإنتاج (Production)
                'error'   => config('app.debug') ? $e->getMessage() : 'Server Error'
            ], 500);
        }
    }
    /**
     * إغلاق الفرصة يدوياً (للمؤسسة)
     */
    public function toggleStatus($id)
    {
        $opportunity = Opportunity::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // تبديل الحالة بين مفتوح ومغلق
        $opportunity->status = ($opportunity->status === 'open') ? 'closed' : 'open';
        $opportunity->save();

        return response()->json([
            'message' => 'تم تحديث حالة الفرصة بنجاح',
            'status' => $opportunity->status
        ]);
    }
    // App\Http\Controllers\OpportunityController.php


    public function getApplicants($id)
    {
        try {
            // 1. التأكد من وجود الفرصة وأنها تخص هذه المؤسسة
            $opportunity = Opportunity::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            // 2. جلب المتقدمين مع (مجموع الساعات المعتمدة) لكل مستخدم في هذه الفرصة
            $applicants = Application::where('opportunity_id', $id)
                ->with(['user' => function ($query) use ($id) {
                    // نستخدم withSum لجلب مجموع حقل hours من جدول hour_logs
                    // فقط للسجلات المعتمدة والمرتبطة بهذه الفرصة
                    $query->withSum(['hourLogs' => function ($q) use ($id) {
                        $q->where('opportunity_id', $id)
                            ->where('status', 'approved');
                    }], 'hours');
                }])
                ->latest()
                ->get();

            return response()->json([
                'success'     => true,
                'opportunity' => $opportunity,
                'applicants'  => $applicants
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب بيانات المتقدمين'
            ], 500);
        }
    }
    public function show($id)
    {
        // 1. جلب الفرصة مع العلاقات الأساسية + حساب عدد المقبولين فقط
        // استخدمنا withCount مع closure لتصفية الحالات المقبولة فقط
        $opportunity = Opportunity::with(['user.organization', 'skills'])
            ->withCount(['applications as accepted_count' => function ($query) {
                $query->where('status', 'accepted');
            }])
            ->find($id);

        if (!$opportunity) {
            return response()->json([
                'message' => 'عذراً، هذه الفرصة غير موجودة'
            ], 404);
        }

        // 2. جلب حالة الطلب بدقة للمستخدم المسجل (الطالب)
        $applicationStatus = null;
        if (auth('sanctum')->check() && auth('sanctum')->user()->role === 'student') {
            $application = Application::where('user_id', auth('sanctum')->id())
                ->where('opportunity_id', $id)
                ->first();

            $applicationStatus = $application ? $application->status : null;
        }

        // 3. دمج البيانات وإرسالها
        // سيحتوي الـ JSON الآن على حقل "accepted_count"
        return response()->json(array_merge($opportunity->toArray(), [
            'my_application_status' => $applicationStatus
        ]));
    }

    /**
     * إلغاء التقديم على فرصة (دالة الحذف)
     */
    public function destroyApplication($opportunityId)
    {
        $userId = Auth::id();

        // البحث عن الطلب الخاص بهذا المستخدم لهذه الفرصة
        $application = Application::where('user_id', $userId)
            ->where('opportunity_id', $opportunityId)
            ->first();

        if (!$application) {
            return response()->json([
                'message' => 'لم يتم العثور على طلب تقديم لإلغائه'
            ], 404);
        }

        // التحقق: هل يمكن إلغاء الطلب؟ (مثلاً إذا كان مازال قيد الانتظار)
        if ($application->status !== 'pending') {
            return response()->json([
                'message' => 'عذراً، لا يمكنك إلغاء الطلب بعد أن تم قبوله أو رفضه'
            ], 400);
        }

        $application->delete();

        return response()->json([
            'message' => 'تم إلغاء التقديم بنجاح'
        ]);
    }
    public function destroy($id)
    {
        $opportunity = Opportunity::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();
        $opportunity = Opportunity::findOrFail($id);

        // التأكد أن المؤسسة هي صاحبة الفرصة
        if ($opportunity->user_id !== Auth::id()) {
            return response()->json(['message' => 'غير مصرح لك بالحذف'], 403);
        }

        // التحقق: هل بدأت الفرصة؟ (مقارنة تاريخ اليوم مع تاريخ البدء)
        if ($opportunity->start_date <= now()) {
            return response()->json([
                'message' => 'لا يمكن حذف الفرصة لأنها بدأت بالفعل، يمكنك تغيير حالتها إلى (مغلقة) بدلاً من الحذف.'
            ], 422);
        }

        // جلب المتقدمين المقبولين فقط لإشعارهم
        $acceptedVolunteers = $opportunity->applications()
            ->where('status', 'accepted')
            ->with('user')
            ->get();

        foreach ($acceptedVolunteers as $application) {
            $application->user->sendNotify(
                $application,
                "إلغاء فرصة: {$opportunity->title} ⚠️",
                "نحيطكم علماً بأن المؤسسة قد قامت بإلغاء الفرصة التطوعية قبل موعد بدئها. نعتذر عن أي إزعاج."
            );
        }

        // حذف الفرصة (سيتم حذف الطلبات تلقائياً إذا كنت قد وضعت onDelete('cascade') في المايجريشن)
        $opportunity->delete();

        return response()->json(['message' => 'تم حذف الفرصة وإشعار المتطوعين المقبولين بنجاح']);
    }
    // في موديل Opportunity.php أو داخل الكنترولر

    public function updateApplicantStatus(Request $request, $applicationId)
    {
        $request->validate(['status' => 'required|in:accepted,rejected,pending']);

        $application = Application::where('id', $applicationId)
            ->whereHas('opportunity', function ($query) {
                $query->where('user_id', Auth::id());
            })->firstOrFail();

        $application->status = $request->status;
        $application->save();

        // 🔔 إرسال الإشعار باستخدام الكلاس الذي أنشأته
        $title = "تحديث في طلبك لفرصة: " . $application->opportunity->title;
        $message = $this->getStatusMessage($request->status);

        // استخدام notify لإرسال الإشعار وتخزينه في جدول notifications
        $application->user->notify(new \App\Notifications\ApplicationStatusChanged($application, $title, $message));

        return response()->json([
            'message' => 'تم تحديث حالة المتقدم بنجاح وإرسال الإشعار',
            'new_status' => $application->status
        ]);
    }

    /**
     * دالة مساعدة لتحديد نص الرسالة بناءً على الحالة الجديدة
     */
    private function getStatusMessage($status)
    {
        return match ($status) {
            'accepted' => 'تهانينا! تم قبول انضمامك للفرصة، يمكنك البدء الآن. 🎉',
            'rejected' => 'نعتذر منك، لم يتم قبول طلبك لهذه الفرصة. نتمنى لك التوفيق في فرص أخرى. ✨',
            'pending'  => 'تم إعادة طلبك إلى قائمة الانتظار للمراجعة مرة أخرى. ⏳',
            default    => 'تم تحديث حالة طلبك بنجاح.',
        };
    }
    public function updateStatus(Request $request, $id)
    {
        $opportunity = Opportunity::findOrFail($id);

        // التأكد من الصلاحية
        if ($opportunity->user_id !== Auth::id()) {
            return response()->json(['message' => 'غير مصرح لك'], 403);
        }

        $oldStatus = $opportunity->status;
        $newStatus = $request->status;
        $opportunity->status = $newStatus;
        $opportunity->save();

        // جلب المتطوعين المرتبطين بهذه الفرصة (المقبولين فقط أو الكل حسب رغبتك)
        $volunteers = $opportunity->applications()
            ->whereIn('status', ['accepted', 'pending'])
            ->with('user')
            ->get();

        $statusTranslations = [
            'open' => 'نشطة',
            'closed' => 'مغلقة مؤقتاً',
            'hidden' => 'مخفية',
            'completed' => 'مكتملة وموثقة'
        ];

        foreach ($volunteers as $application) {
            $application->user->sendNotify(
                $application, // تمرير كائن الـ application لكي يعمل الـ Notification
                "تحديث في فرصة: {$opportunity->title}",
                "تم تغيير حالة الفرصة التي شاركت بها إلى ({$statusTranslations[$newStatus]})"
            );
        }

        return response()->json(['message' => 'تم تحديث الحالة وإشعار المتطوعين']);
    }
    /**
     * 2. إضافة فرصة جديدة (للمؤسسة فقط)
     */
    public function getStudentStats()
    {
        $userId = Auth::id();
        // جلب المستخدم مع المهارات والبروفايل معاً
        $user = \App\Models\User::with(['profile', 'skills'])->findOrFail($userId);

        return response()->json([
            // 1. الساعات: نجلبها من علاقة الساعات المعتمدة مباشرة لضمان الدقة
            'total_hours' => $user->hourLogs()->where('status', 'approved')->sum('hours'),

            // 2. المهارات: نجلب عددها من علاقة belongsToMany المباشرة في موديل User
            'skills_count' => $user->skills->count(),

            // 3. الطلبات النشطة: المقبولة وقيد الانتظار
            'active_applications' => \App\Models\Application::where('user_id', $userId)
                ->whereIn('status', ['pending', 'accepted'])
                ->count(),

            // 4. النقاط: مخزنة في جدول البروفايل
            'points' => $user->profile ? $user->profile->points : 0,

            // 5. الفرص المقترحة: جلب مهارات المستخدم للبحث عن فرص مشابهة
            'recent_opportunities' => \App\Models\Opportunity::with(['user.organization', 'skills'])
                ->where('status', 'open')
                ->whereHas('skills', function ($query) use ($user) {
                    $query->whereIn('skills.id', $user->skills->pluck('id'));
                })
                ->latest()
                ->limit(3)
                ->get()
        ]);
    }
    public function store(Request $request)
    {
        if ($request->user()->role !== 'organization') {
            return response()->json(['message' => 'عذراً، إضافة الفرص محصورة للمؤسسات فقط'], 403);
        }

        $data = $request->validate([
            'title'               => 'required|string|max:255',
            'description'         => 'required|string',
            'location'            => 'required|string',
            'lat'                 => 'nullable|numeric', // إضافة التحقق للإحداثيات
            'lng'                 => 'nullable|numeric',
            'duration'            => 'required|string',
            'requirements'        => 'nullable|string',
            'required_volunteers' => 'required|integer|min:1', // إضافة التحقق للعدد
            'start_date'            => 'required|date|after:today',
            'deadline'            => 'required|date|after:today',
            'type'                => 'required|in:voluntary,training,course', // تأكد من مطابقة المايجريشن
            'gender'              => 'required|in:male,female,both',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'skill_ids'           => 'required|array',
            'skill_ids.*'         => 'exists:skills,id',
        ]);


        if ($request->hasFile('cover_image')) {
            $image = $request->file('cover_image');
            $imageName = 'opportunity_' . time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $path = $request->file('cover_image')->store('opportunities', 'public');

            // التأكد من وجود المجلد ومن ثم نقل الصورة
            $image->move(public_path('uploads/opportunities'), $path);

            // إضافة المسار للمصفوفة التي ستخزن في القاعدة
            $data['cover_image'] = $path;
        }
        $opportunity = Opportunity::create([
            'user_id'             => Auth::id(),
            'title'               => $data['title'],
            'description'         => $data['description'],
            'location'            => $data['location'],
            'lat'                 => $data['lat'] ?? null,
            'lng'                 => $data['lng'] ?? null,
            'duration'            => $data['duration'],
            'requirements'        => $data['requirements'],
            'required_volunteers' => $data['required_volunteers'],
            'start_date'            => $data['start_date'],
            'deadline'            => $data['deadline'],
            'type'                => $data['type'],
            'gender'              => $data['gender'],
            'cover_image'         => $data['cover_image'] ?? null,
            'status'              => 'open'
        ]);

        // ربط المهارات المطلوبة بالفرصة (Many-to-Many)
        $opportunity->skills()->sync($data['skill_ids']);

        return response()->json([
            'message'     => 'تم نشر الفرصة بنجاح',
            'opportunity' => $opportunity->load('skills')
        ], 201);
    }

    /**
     * 3. جلب أفضل المرشحين بناءً على مطابقة المهارات (للمؤسسة)
     */
    public function getTopCandidates($opportunityId)
    {
        $opportunity = Opportunity::with('skills')->findOrFail($opportunityId);
        $skillIds = $opportunity->skills->pluck('id');

        // جلب المستخدمين الذين لديهم ملف شخصي ومهارات مطابقة
        $candidates = UserProfile::whereHas('skills', function ($query) use ($skillIds) {
            $query->whereIn('skills.id', $skillIds);
        })
            ->with(['user:id,full_name,email,phone', 'skills'])
            ->get();

        return response()->json($candidates);
    }


    public function recommended(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // 1. جلب المستخدم مع المهارات مباشرة (العلاقة الجديدة)
        $userWithSkills = $user->load('skills');

        // 2. التحقق من وجود مهارات
        if ($userWithSkills->skills->isEmpty()) {
            return response()->json([
                'status' => 'info',
                'message' => 'أضف مهاراتك أولاً للحصول على توصيات دقيقة',
                'data' => []
            ]);
        }

        $userSkillIds = $userWithSkills->skills->pluck('id')->toArray();

        // 3. البحث عن الفرص التي تشترك في هذه المهارات
        $opportunities = \App\Models\Opportunity::where('status', 'open')
            ->whereHas('skills', function ($query) use ($userSkillIds) {
                $query->whereIn('skills.id', $userSkillIds);
            })
            ->with(['skills', 'user.organization']) // شحن بيانات المؤسسة للعرض في الواجهة
            ->get()
            ->map(function ($opp) use ($userSkillIds) {
                $oppSkillIds = $opp->skills->pluck('id')->toArray();

                // حساب عدد المهارات المشتركة
                $matched = array_intersect($userSkillIds, $oppSkillIds);
                $totalOppSkills = count($oppSkillIds);

                // حساب النسبة المئوية للمطابقة
                $opp->match_percent = $totalOppSkills > 0
                    ? round((count($matched) / $totalOppSkills) * 100)
                    : 0;

                return $opp;
            })
            ->sortByDesc('match_percent')
            ->values();

        return response()->json($opportunities);
    }
    /**
     * 5. إحصائيات لوحة التحكم للمؤسسة
     */
    public function dashboardStats(Request $request)
    {
        $userId = Auth::id();

        $stats = [
            'total_opportunities' => Opportunity::where('user_id', $userId)->count(),
            'total_applicants'    => Application::whereHas('opportunity', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->count(),
            'accepted_students'   => Application::where('status', 'accepted')
                ->whereHas('opportunity', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })->count(),
            'pending_applications' => Application::where('status', 'pending')
                ->whereHas('opportunity', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })->count(),
        ];

        // جلب الفرص النشطة لعرضها في الـ Sidebar أو الإحصائيات
        $activeOpportunities = Opportunity::where('user_id', $userId)
            ->where('status', 'open')
            ->latest()
            ->get();

        return response()->json([
            'stats' => $stats,
            'active_opportunities' => $activeOpportunities
        ]);
    }
    public function getStats()
    {
        $userId = Auth::id();

        // جلب الفرص الأخيرة مع تحديد الأعمدة المطلوبة فقط (id و status)
        $recentOpportunities = Application::where('user_id', $userId)
            ->latest()
            ->limit(5)
            ->get(['opportunity_id', 'status']); // <--- هذا السطر هو السر

        $stats = [
            'total_opportunities'  => Opportunity::where('user_id', $userId)->count(),
            'total_applications'   => Application::whereHas('opportunity', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->count(),
            'pending_applications' => Application::where('status', 'pending')
                ->whereHas('opportunity', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })->count(),
            'accepted_volunteers'  => Application::where('status', 'accepted')->count(),

            // إضافة القائمة المختصرة للنتائج
            'recent_opportunities' => $recentOpportunities,
        ];

        return response()->json($stats);
    }
    public function getLatestApplicants(Request $request)
    {
        $userId = Auth::id();

        // نستخدم user_id لأن الفرصة مرتبطة بالمستخدم الذي أنشأها (المؤسسة)
        $applicants = \App\Models\Application::whereHas('opportunity', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
            ->with(['user', 'opportunity'])
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'applicants' => $applicants
        ]);
    }
    public function getDashboardStats()
    {
        $currentUserId = Auth::id();

        try {
            // 1. حساب التقييم المتوسط مع التحقق من النوع لضمان دقة العشرية
            $rawAvg = Review::whereHas('opportunity', fn($q) => $q->where('user_id', $currentUserId))
                ->avg('rating');

            // تنسيق الرقم ليظهر دائماً بخانة عشرية واحدة (مثلاً 4.9 أو 5.0)
            $averageRating = $rawAvg ? number_format((float)$rawAvg, 1, '.', '') : "0.0";

            // 2. تجميع الإحصائيات (إضافة casting لضمان نوع البيانات في JSON)
            $stats = [
                'total_opportunities'  => (int) Opportunity::where('user_id', $currentUserId)->count(),
                'pending_applications' => (int) Application::whereHas('opportunity', fn($q) => $q->where('user_id', $currentUserId))
                    ->where('status', 'pending')->count(),
                'total_hours'          => (int) HourLog::whereHas('opportunity', fn($q) => $q->where('user_id', $currentUserId))
                    ->where('status', 'approved')->sum('hours'),
                'average_rating'       => $averageRating,
            ];

            // 3. تحسين استعلام الرسم البياني (بيانات المتطوعين)
            $monthlyVolunteers = Application::whereHas('opportunity', fn($q) => $q->where('user_id', $currentUserId))
                ->where('status', 'accepted')
                ->select(
                    DB::raw('COUNT(id) as count'),
                    DB::raw("DATE_FORMAT(created_at, '%M') as month_name"),
                    DB::raw('MONTH(created_at) as month_num')
                )
                ->where('created_at', '>=', Carbon::now()->subMonths(6))
                ->groupBy('month_num', 'month_name')
                ->orderBy('month_num')
                ->get()
                ->map(fn($item) => [
                    'name'  => $this->translateMonth($item->month_name),
                    'count' => (int) $item->count
                ]);

            // 4. جلب الفرص النشطة مع حساب نسبة الإنجاز برمجياً
            $activeOpportunities = Opportunity::where('user_id', $currentUserId)
                ->where('status', 'open')
                ->select('id', 'title', 'required_volunteers', 'created_at') // جلب الأعمدة المطلوبة فقط
                ->withCount(['applications as accepted_count' => fn($q) => $q->where('status', 'accepted')])
                ->latest()
                ->take(5)
                ->get()
                ->map(function ($opp) {
                    $opp->completion_rate = $opp->required_volunteers > 0
                        ? round(($opp->accepted_count / $opp->required_volunteers) * 100)
                        : 0;
                    return $opp;
                });

            return response()->json([
                'success'              => true,
                'stats'                => $stats,
                'monthly_volunteers'   => $monthlyVolunteers,
                'active_opportunities' => $activeOpportunities
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ فني أثناء جلب إحصائيات لوحة التحكم',
                // لا تظهر الخطأ الحقيقي للمستخدم في بيئة الإنتاج (Production)
                'error'   => config('app.debug') ? $e->getMessage() : 'Server Error'
            ], 500);
        }
    }
    // دالة مساعدة لترجمة الأشهر للعربية (اختياري)
    private function translateMonth($month)
    {
        $months = [
            'January' => 'يناير',
            'February' => 'فبراير',
            'March' => 'مارس',
            'April' => 'أبريل',
            'May' => 'مايو',
            'June' => 'يونيو',
            'July' => 'يوليو',
            'August' => 'أغسطس',
            'September' => 'سبتمبر',
            'October' => 'أكتوبر',
            'November' => 'نوفمبر',
            'December' => 'ديسمبر'
        ];
        return $months[$month] ?? $month;
    }
    // دالة مقترحة لإضافتها في OpportunityController
    public function getOpportunityTotalHours($id)
    {
        // التأكد من أن الفرصة تخص المؤسسة المسجلة حالياً
        $opportunity = Opportunity::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $totalHours = HourLog::where('opportunity_id', $id)
            ->where('status', 'approved')
            ->sum('hours');

        return response()->json([
            'success' => true,
            'opportunity_title' => $opportunity->title,
            'total_hours' => (float) $totalHours
        ]);
    }
    // دالة مقترحة لجلب ساعات متطوع معين في فرصة محددة
    public function getVolunteerHoursInOpportunity($id, $volunteerId)
    {
        // 1. التأكد من صلاحية المؤسسة للوصول لهذه الفرصة
        $opportunity = Opportunity::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // 2. جلب السجلات مرتبة حسب تاريخ التطوع الفعلي (date_logged)
        $logs = HourLog::where('opportunity_id', $id)
            ->where('user_id', $volunteerId)
            ->orderBy('date_logged', 'desc') // استخدم الحقل الموجود في الـ migration الخاص بك
            ->get();

        // 3. حساب الإجماليات
        return response()->json([
            'success' => true,
            'volunteer_id' => (int) $volunteerId,
            'opportunity_title' => $opportunity->title,

            // إجمالي الساعات التي تظهر في الواجهة الرئيسية (المعتمدة)
            'total_hours' => $logs->where('status', 'approved')->sum('hours'),

            // تفاصيل إضافية للحالات الأخرى

        ]);
    }
    // في OpportunityController.php
    public function getAcceptedVolunteers(Request $request)
    {
        $orgId = Auth::id();

        $applicants = User::whereHas('applications', function ($query) use ($orgId) {
            $query->whereHas('opportunity', function ($q) use ($orgId) {
                $q->where('user_id', $orgId);
            });
        })
            // ضروري جداً إضافة 'profile' هنا وإلا سيعطيك خطأ عند محاولة الوصول لبياناته بالأسفل
            ->with(['profile', 'skills'])
            ->latest()
            ->get()
            ->map(function ($user) {
                return [
                    'user' => [
                        'id'            => $user->id,
                        'full_name'     => $user->full_name,

                        'phone'         => $user->phone,
                        'profile_image' => $user->profile_image,
                        'location'      => $user->location,


                        'major'         => $user->profile->major ?? null,
                        'total_volunteer_hours' => $user->profile->total_volunteer_hours ?? 0,
                        'skills' => $user->skills->map(function ($skill) {
                            return [
                                'id'   => $skill->id,
                                'name' => $skill->name
                            ];
                        }),
                    ]
                ];
            });

        // نعيد المصفوفة كاملة، وكل عنصر بداخلها سيكون مغلفاً بـ "user"
        return response()->json($applicants);
    }

    public function getPendingOrganizations(Request $request)
    {
        try {
            $orgId = Auth::id();

            // جلب الطلبات المعلقة للفرص التابعة لهذه المنظمة
            $pending = Application::whereHas('opportunity', function ($q) use ($orgId) {
                $q->where('user_id', $orgId);
            })
                ->with(['opportunity', 'user.profile']) // هنا نجلب المستخدم مع ملفه الشخصي
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($pending);
        } catch (\Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء جلب البيانات'], 500);
        }
    }
}
