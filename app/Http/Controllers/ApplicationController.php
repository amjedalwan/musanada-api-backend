<?php

namespace App\Http\Controllers;

use App\Notifications\ApplicationStatusChanged;
use App\Models\Application;
use App\Models\Opportunity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class ApplicationController extends Controller
{
    /**
     * 1. تقديم طلب جديد (للمتطوعين/الطلاب)
     */
    public function store(Request $request)
    {
        // التحقق من أن الدور هو مستخدم (user) وليس مؤسسة
        if ($request->user()->role !== 'student') {
            return response()->json(['message' => 'عذراً، التقديم متاح للمتطوعين فقط'], 403);
        }

        $data = $request->validate([
            'opportunity_id' => 'required|exists:opportunities,id',
        ]);

        // نستخدم Auth::id مباشرة لأننا وحدنا المعرفات في جدول applications
        $alreadyApplied = Application::where('user_id', Auth::id())
            ->where('opportunity_id', $data['opportunity_id'])
            ->exists();

        if ($alreadyApplied) {
            return response()->json(['message' => 'لقد قمت بالتقديم مسبقاً على هذه الفرصة'], 400);
        }

        $application = Application::create([
            'user_id'        => Auth::id(),
            'opportunity_id' => $data['opportunity_id'],
            'status'         => 'pending',
        ]);

        return response()->json([
            'message'     => 'تم تقديم طلبك بنجاح، بالتوفيق!',
            'application' => $application
        ], 201);
    }

    /**
     * 2. عرض طلبات التقديم الخاصة بالمتطوع الحالي
     */
    /**
     * 2. عرض طلبات التقديم الخاصة بالمتطوع الحالي مع إحصائيات الفرص
     */
    /**
     * 2. عرض طلبات التقديم الخاصة بالمتطوع الحالي مع إحصائيات الفرص
     */
    public function myApplications()
    {
        $user = Auth::user();

        $applications = Application::with([
            'opportunity.user.organization',
            'certificate' // تأكد من وجود علاقة certificate في مودل Application
        ])
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(function ($app) {
                if ($app->opportunity) {
                    // إضافة الإحصائيات (الكود القديم الخاص بك)
                    $app->opportunity->stats = [
                        'accepted' => (int) $app->opportunity->applications()->where('status', 'accepted')->count(),
                        'pending'  => (int) $app->opportunity->applications()->where('status', 'pending')->count(),
                        'rejected' => (int) $app->opportunity->applications()->where('status', 'rejected')->count(),
                    ];
                }

                // إضافة التحقق: هل توجد شهادة صادرة؟
                // سيقوم هذا السطر بإضافة حقل is_certified بناءً على وجود بيانات في جدول الشهادات
                $app->is_certified = $app->certificate ? true : false;

                // إذا كنت تريد رابط الشهادة مباشرة
                $app->certificate_url = $app->certificate ? asset($app->certificate->file_path) : null;

                return $app;
            });

        return response()->json($applications);
    }
    public function allApplicationsForOrg(Request $request)
    {
        // 1. التأكد من أن المستخدم المسجل هو مؤسسة (حماية إضافية داخل الدالة)
        if ($request->user()->role !== 'organization') {
            return response()->json(['message' => 'عذراً، هذا القسم مخصص للمؤسسات فقط'], 403);
        }

        $user = Auth::user();

        // 2. الاستعلام: جلب الطلبات المرتبطة بفرص هذه المؤسسة حصراً
        $applications = Application::whereHas('opportunity', function ($query) use ($user) {
            $query->where('user_id', $user->id); // هذا السطر يمنع اختلاط بيانات المؤسسات
        })
            ->with([
                'user:id,full_name,email,phone', // جلب بيانات المتطوع المحددة فقط للأمان
                'opportunity:id,title,type,status' // جلب بيانات الفرصة الأساسية
            ])
            ->latest()
            ->paginate(15);

        return response()->json($applications);
    }
    public function completeApplication($id)
    {
        // 1. البحث عن الطلب (Application) وليس الفرصة
        $application = Application::findOrFail($id);

        // 2. التحقق من أن المؤسسة الحالية هي صاحبة الفرصة المرتبطة بهذا الطلب
        if ($application->opportunity->user_id !== Auth::id()) {
            return response()->json(['message' => 'غير مصرح لك'], 403);
        }

        // 3. تحديث حالة الطلب إلى مكتمل
        $application->update(['status' => 'completed']);

        return response()->json(['message' => 'تم إتمام مهمة المتطوع بنجاح!']);
    }
    /**
     * 3. تحديث حالة الطلب (قبول/رفض) - للمؤسسة فقط
     */
    /**
     * تحديث حالة الطلب وإرسال إشعار
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status'           => 'required|in:accepted,rejected,pending,completed',
            'rejection_reason' => 'required_if:status,rejected|string|nullable'
        ]);

        $application = Application::with(['opportunity', 'user'])->findOrFail($id);

        // التحقق من الصلاحية: هل المستخدم هو صاحب الفرصة؟
        if (Auth::id() !== $application->opportunity->user_id) {
            return response()->json(['message' => 'غير مصرح لك بتعديل هذا الطلب'], 403);
        }

        // تجهيز محتوى الإشعار بناءً على الحالة
        switch ($request->status) {
            case 'accepted':
                $title = "تهانينا! تم قبول طلبك";
                $message = "تم قبول انضمامك لفرصة: " . $application->opportunity->title;
                break;
            case 'rejected':
                $title = "تحديث بشأن طلب التطوع";
                $message = "نعتذر منك، تم رفض طلبك لفرصة: " . $application->opportunity->title;
                break;
            case 'completed':
                $title = "إنجاز جديد! اكتملت الفرصة";
                $message = "لقد أكملت تطوعك في " . $application->opportunity->title . ". يمكنك الآن تحميل شهادتك.";
                break;
            default:
                $title = "تحديث في حالة الطلب";
                $message = "تم تغيير حالة طلبك لفرصة " . $application->opportunity->title;
        }

        // تحديث قاعدة البيانات
        $application->update([
            'status' => $request->status,
            'rejection_reason' => $request->status === 'rejected' ? $request->rejection_reason : null
        ]);

        // إرسال الإشعار
        $application->user->notify(new ApplicationStatusChanged($application, $title, $message));

        return response()->json([
            'message' => 'تم تحديث الحالة بنجاح وإرسال الإشعار',
            'status' => $request->status
        ]);
    }

    /**
     * 4. عرض المتقدمين لفرصة معينة (للمؤسسة)
     */
    public function opportunityApplications($opportunityId)
    {
        $opportunity = Opportunity::findOrFail($opportunityId);

        if (Auth::id() !== $opportunity->user_id) {
            return response()->json(['message' => 'غير مصرح لك بالاطلاع على المتقدمين'], 403);
        }

        $applications = Application::with(['user' => function ($query) {
            $query->select('id', 'full_name', 'email', 'phone');
        }, 'user.profile']) // جلب بيانات الملف الشخصي (الجامعة والتخصص)
            ->where('opportunity_id', $opportunityId)
            ->latest()
            ->get();

        return response()->json([
            'opportunity_title' => $opportunity->title,
            'applicants'        => $applications
        ]);
    }

    public function issueCertificate($applicationId)
    {


        $application = Application::with(['opportunity.user', 'user'])->findOrFail($applicationId);
        if ($application->status !== 'accepted') {
            return response()->json(['message' => 'يجب قبول الطلب أولاً'], 400);
        }

        $certificateCode = 'MSND-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $fileName = $certificateCode . '.pdf';
        $directory = public_path('certificates');
        $filePath = 'certificates/' . $fileName;

        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }

        // --- معالجة اللغة العربية ---
        $arabic = new \I18N_Arabic('Glyphs');

        // تحويل النصوص لتظهر بشكل صحيح (غير مقلوبة وغير مقطعة)
        $fullName = $arabic->utf8Glyphs($application->user->full_name);
        $opportunityTitle = $arabic->utf8Glyphs($application->opportunity->title);
        $orgName = $arabic->utf8Glyphs($application->opportunity->user->full_name);
        $platformName = $arabic->utf8Glyphs("منصة مساندة");
        $certTitle = $arabic->utf8Glyphs("شهادة تطوع وتدريب");

        $data = [
            'full_name'         => $fullName,
            'opportunity_title' => $opportunityTitle,
            'organization_name' => $orgName,
            'platform_name'     => $platformName,
            'cert_title'        => $certTitle,
            'certificate_code'  => $certificateCode,
            'issue_date'        => now()->format('Y-m-d'),
        ];

        try {
            $pdf = Pdf::loadView('pdf.certificate', $data)
                ->setPaper('a4', 'landscape')
                ->setOptions([
                    'tempDir' => public_path(),
                    'chroot'  => public_path(),
                    'isRemoteEnabled' => true,
                    'fontHeightRatio' => 1.1,
                ]);
            if ($application->certificate) {
                return response()->json(['message' => 'الشهادة صادرة بالفعل', 'url' => asset($application->certificate->file_path)]);
            }
            $pdf->save($directory . '/' . $fileName);

            $certificate = \App\Models\Certificate::create([
                'user_id'          => $application->user_id,
                'opportunity_id'   => $application->opportunity_id,
                'certificate_code' => $certificateCode,
                'issue_date'       => now(),
                'file_path'        => $filePath,
            ]);

            $application->update(['status' => 'completed']);

            return response()->json([
                'message'      => 'تم توليد ملف PDF بنصوص عربية صحيحة',
                'download_url' => asset($filePath),
                'data'         => $certificate
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'حدث خطأ أثناء إصدار الشهادة: ' . $e->getMessage()
            ], 500);
        }
    }
    public function verifyCertificate($code)
    {
        $certificate = \App\Models\Certificate::with(['user', 'opportunity.user'])
            ->where('certificate_code', $code)
            ->first();

        if (!$certificate) {
            return response()->json(['message' => 'عذراً، هذه الشهادة غير مسجلة في نظامنا'], 404);
        }

        return response()->json([
            'valid' => true,
            'student_name' => $certificate->user->full_name,
            'opportunity' => $certificate->opportunity->title,
            'organization' => $certificate->opportunity->user->full_name,
            'issue_date' => $certificate->issue_date->format('Y-m-d'),
        ]);
    }
}
