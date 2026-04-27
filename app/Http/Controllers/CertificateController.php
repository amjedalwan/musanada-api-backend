<?php

namespace App\Http\Controllers;


use App\Models\Application;
use App\Models\Certificate;
use App\Models\HourLog;
use App\Models\Opportunity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class CertificateController extends Controller
{
    /**
     * 1. إصدار شهادة جديدة للمتطوع (للمؤسسة فقط)
     */
    public function issue(Request $request)
    {
        $data = $request->validate([
            'user_id'        => 'required|exists:users,id',
            'opportunity_id' => 'required|exists:opportunities,id',
        ]);

        $opportunity = Opportunity::findOrFail($data['opportunity_id']);

        // 1. التحقق من الصلاحية (المؤسسة هي صاحبة الفرصة)
        if ($opportunity->user_id !== Auth::id()) {
            return response()->json(['message' => 'غير مصرح لك بإصدار شهادات لهذه الفرصة'], 403);
        }

        // 2. التحقق من عدم وجود شهادة سابقة
        $alreadyIssued = Certificate::where('user_id', $data['user_id'])
            ->where('opportunity_id', $data['opportunity_id'])
            ->exists();

        if ($alreadyIssued) {
            return response()->json(['message' => 'لقد تم إصدار شهادة لهذا المتطوع بالفعل'], 400);
        }

        // 3. حساب الساعات (لغرض التنبيه فقط وليس المنع)
        $totalHours = HourLog::where('user_id', $data['user_id'])
            ->where('opportunity_id', $data['opportunity_id'])
            ->where('status', 'approved')
            ->sum('hours');

        // الساعات الواجبة (نفترض أنها مخزنة في الحقل duration أو قيمة ثابتة)
        $requiredHours = $opportunity->duration ?? 10;

        // 4. توليد الشهادة (تم إزالة شرط if ($totalHours < 1))
        $certificate = Certificate::create([
            'user_id'          => $data['user_id'],
            'opportunity_id'   => $data['opportunity_id'],
            'certificate_code' => 'MSND-' . strtoupper(Str::random(8)) . '-' . date('Y'),
            'issue_date'       => now(),
        ]);

        // إرسال الإشعار
        $certificate->user->sendNotify(
            $certificate, // تمرير كائن الشهادة للإشعار
            'مبارك لك! صدرت شهادتك 🎓',
            "تم إصدار شهادة لفرصة: {$opportunity->title}. الساعات المنجزة: {$totalHours}"
        );

        // داخل دالة issue، استبدل سطر الـ return بهذا لضمان وصول كافة البيانات للفرونت إند
        return response()->json([
            'message' => 'تم إصدار الشهادة بنجاح',
            'hours_stats' => [
                'completed' => $totalHours,
                'required'  => $requiredHours,
                'status'    => $totalHours >= $requiredHours ? 'مكتمل' : 'ناقص'
            ],
            'certificate' => $certificate->load([
                'user:id,full_name',
                'opportunity:id,title,user_id',
                'opportunity.user.organization:user_id,org_name'
            ])
        ], 201);
    }

    /**
     * 3. التحقق من صحة الشهادة عبر الرمز (للجهات الخارجية مثل الجامعات وأصحاب العمل)
     * ميزة أساسية لضمان عدم التزوير
     */
    public function verify($code)
    {
        // 1. جلب الشهادة مع كافة العلاقات المتشعبة المطلوبة
        $certificate = Certificate::with([
            'user.profile', // لجلب الجنس من ملف المستخدم
            'opportunity.skills',
            'opportunity.user.organization',
            // جلب طلب التقديم الخاص بهذا المتطوع لهذه الفرصة لمعرفة تاريخ القبول
            'opportunity.applications' => function ($query) use ($code) {
                $query->whereHas('user.certificates', function ($q) use ($code) {
                    $q->where('certificate_code', $code);
                })->where('status', 'accepted');
            }
        ])->where('certificate_code', $code)->firstOrFail();

        $opportunity = $certificate->opportunity;
        $organization = $opportunity->user->organization;
        $user = $certificate->user;

        // 2. حساب إجمالي الساعات المعتمدة
        $totalHours = \App\Models\HourLog::where('user_id', $certificate->user_id)
            ->where('opportunity_id', $certificate->opportunity_id)
            ->where('status', 'approved')
            ->sum('hours');

        // 3. معالجة المهارات
        $skills = $opportunity->skills->pluck('name')->toArray();
        if (empty($skills)) {
            $skills = array_map('trim', explode('و', $opportunity->requirements));
        }

        // 4. الحصول على تاريخ القبول (أول تاريخ تم فيه قبول الطلب)
        $applicationDate = $opportunity->applicants->first();
        $joinDate =  $applicationDate->updated_at ? $applicationDate->updated_at : $applicationDate->created_at;
 $joinDate=date_format($joinDate,'Y-m-d');
        return response()->json([
            'success'         => true,
            'data' => [
                // بيانات المتطوع
                'volunteerName'   => $user->full_name,
                'volunteerGender' => $user->profile->gender ?? 'male', // مهم لتحديد (متطوع/ة)

                // بيانات الفرصة
                'opportunityTitle' => $opportunity->title,
                'opportunityType' => $opportunity->type, 
                'location'        => $opportunity->location,
                'duration'        => $opportunity->duration, // مدة الفرصة
                'joinDate'        => $joinDate, // تاريخ القبول في الفرصة

                // بيانات المؤسسة والتواصل
                'orgName'         => $organization->org_name,
                'orgType'         => $organization->org_type,
                'orgLogo'         => $opportunity->user->profile_image, // أو شعار المؤسسة من جدول الملحقات
                'orgPhone'        => $opportunity->user->phone,
                'orgWebsite'      => $organization->website,

                'digital_signature' => $organization->digital_signature,

                // بيانات الشهادة والتوثيق
                'hours'           => (string)$totalHours,
                'issueDate'       => $certificate->issue_date,
                'certificateCode' => $certificate->certificate_code,
                'skills'          => implode(', ', array_slice($skills, 0, 5)),
                'managerName'     => $opportunity->user->full_name,
                'managerTitle'    => "إدارة المؤسسة"
            ]
        ]);
    }

    /**
     * جلب المتطوعين الذين أتموا الفرصة ولم يحصلوا على شهادة بعد (للمؤسسة)
     */
    public function getEligibleVolunteers()
    {
        $orgId = Auth::id();

        // 💡 نستخدم withSum لجلب مجموع الساعات المعتمدة في نفس الاستعلام الأساسي
        $eligible = \App\Models\Application::with([
            'user:id,full_name,profile_image',
            'opportunity:id,title'
        ])
            ->withSum(['hourLogs as approved_hours' => function ($query) {
                $query->where('status', 'approved');
            }], 'hours')
            ->whereHas('opportunity', function ($query) use ($orgId) {
                $query->where('user_id', $orgId);
            })
            ->where('status', 'completed')
            ->whereNotExists(function ($query) {
                $query->select(\DB::raw(1))
                    ->from('certificates')
                    ->whereColumn('certificates.user_id', 'applications.user_id')
                    ->whereColumn('certificates.opportunity_id', 'applications.opportunity_id');
            })
            ->get();

        return response()->json($eligible);
    }
    public function issuedByMe()
    {
        $orgId = Auth::id();

        $certificates = Certificate::with(['user:id,full_name,profile_image', 'opportunity:id,title'])
            ->whereHas('opportunity', function ($q) use ($orgId) {
                $q->where('user_id', $orgId);
            })
            ->latest()
            ->get()
            ->map(function ($certificate) {
                // حساب الساعات المعتمدة لهذا المتطوع في هذه الفرصة المحددة
                $certificate->approved_hours = \App\Models\HourLog::where('user_id', $certificate->user_id)
                    ->where('opportunity_id', $certificate->opportunity_id)
                    ->where('status', 'approved')
                    ->sum('hours');
                return $certificate;
            });

        return response()->json($certificates);
    }
    // في ملف CertificateController.php


    public function update(Request $request, $id)
    {
        $certificate = Certificate::findOrFail($id);

        // التحقق من أن المؤسسة هي من أصدرت الشهادة
        $opportunity = Opportunity::findOrFail($certificate->opportunity_id);
        if ($opportunity->user_id !== Auth::id()) {
            return response()->json(['message' => 'غير مصرح لك بتعديل هذه الشهادة'], 403);
        }

        $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'total_hours' => 'sometimes|numeric'
        ]);

        // ملاحظة: قد تحتاج لتحديث جدول المستخدم أو جدول منفصل لبيانات الشهادة المخصصة
        // سنناقش أفضل طريقة لتخزين "الاسم المعدل" للشهادة لاحقاً

        return response()->json(['message' => 'تم تحديث بيانات الشهادة بنجاح']);
    }
    public function destroy($id)
    {
        $certificate = Certificate::findOrFail($id);

        // التحقق من الصلاحية: هل المؤسسة هي من أصدر الشهادة؟
        if ($certificate->opportunity->user_id !== Auth::id()) {
            return response()->json(['message' => 'غير مصرح لك بحذف هذه الشهادة'], 403);
        }

        $certificate->delete();

        return response()->json(['message' => 'تم حذف الشهادة بنجاح']);
    }
    /**
     * 2. جلب شهادات المتطوع الحالي (خاص بالمتطوع)
     * تستخدم لعرض "سجل الإنجازات" في لوحة تحكم المتطوع
     */
    public function myCertificates()
    {
        $userId = Auth::id();

        // جلب الشهادات مع العلاقات اللازمة لعرضها في الواجهة الأمامية
        $certificates = Certificate::with([
            'opportunity:id,title,type,duration,user_id',
            'opportunity.user.organization:user_id,org_name,org_type'
        ])
            ->where('user_id', $userId)
            ->latest() // ترتيب من الأحدث إلى الأقدم
            ->get()
            ->map(function ($certificate) use ($userId) {
                // حساب الساعات المعتمدة (Approved Hours) التي حققها المتطوع في هذه الفرصة
                // لكي تظهر في بطاقة الشهادة بالفرونت إند
                $certificate->approved_hours = \App\Models\HourLog::where('user_id', $userId)
                    ->where('opportunity_id', $certificate->opportunity_id)
                    ->where('status', 'approved')
                    ->sum('hours');

                // إضافة اسم المؤسسة بشكل مباشر لتسهيل الوصول إليه في React
                $certificate->organization_name = $certificate->opportunity->user->organization->org_name ?? 'جهة غير معروفة';

                return $certificate;
            });
        $totalHours = \App\Models\HourLog::where('user_id', $userId)
            ->where('status', 'approved')
            ->sum('hours');
        // إرجاع البيانات في كائن موحد
        return response()->json([
            'success' => true,
            'count'   => $certificates->count(),
            'data'    => $certificates,
            'total_logged_hours' => $totalHours,
        ]);
    }
}
