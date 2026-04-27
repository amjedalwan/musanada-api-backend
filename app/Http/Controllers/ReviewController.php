<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Application;
use App\Models\Opportunity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    /**
     * إضافة تقييم جديد للمتطوع (متوافق مع تحليل الفصل الثالث)
     */
 public function store(Request $request)
{
    // 1. التحقق من البيانات
    $data = $request->validate([
        'user_id'         => 'required|exists:users,id',
        'opportunity_id' => 'required|exists:opportunities,id',
        'rating'         => 'required|integer|min:1|max:5',
        'comment'        => 'nullable|string|max:500',
    ]);

    // 2. جلب الكائنات (Objects) بشكل صريح
    $opportunity = Opportunity::findOrFail($data['opportunity_id']);
    $student = \App\Models\User::findOrFail($data['user_id']);

    // 3. التحققات الأمنية (قبل الحفظ)
    if ($opportunity->user_id !== Auth::id()) {
        return response()->json(['message' => 'غير مصرح لك بتقييم هذه الفرصة'], 403);
    }

    $alreadyReviewed = Review::where('user_id', $data['user_id'])
        ->where('opportunity_id', $data['opportunity_id'])
        ->exists();

    if ($alreadyReviewed) {
        return response()->json(['message' => 'تم تقييم هذا المتطوع مسبقاً'], 400);
    }

    // 4. الحفظ في قاعدة البيانات (مرة واحدة فقط)
    $review = Review::create([
        'user_id'         => $data['user_id'],
        'opportunity_id' => $data['opportunity_id'],
        'rating'         => $data['rating'],
        'comment'        => $data['comment'],
    ]);

    // 5. إرسال الإشعار (استخدام كائن الطالب لضمان عدم وجود خطأ String)
    try {
        $orgName = Auth::user()->organization->org_name ?? 'المؤسسة';
        
        // تأكد أن دالة sendNotify تستقبل 3 معاملات كما في ملف User.php لديك
        $student->sendNotify(
            'تقييم جديد! ⭐',
            "قامت {$orgName} بتقييم أدائك في: {$opportunity->title}",
            '/student/profile' 
        );
    } catch (\Exception $e) {
        // إذا فشل الإشعار لا نريد تعطيل استجابة النجاح للمؤسسة
        \Log::error("فشل إرسال إشعار التقييم: " . $e->getMessage());
    }

    // 6. استجابة النجاح النهائية (تُرجع 201 ليفهمها Vite/React)
    return response()->json([
        'message' => 'تم تسجيل التقييم في السجل الرقمي بنجاح',
        'review'  => $review
    ], 201);
}

    /**
     * عرض التقييمات (تحسين الـ Eager Loading)
     */
    public function userReviews($userId)
    {
        // نربط الفرصة مباشرة لمعرفة من قيم (صاحب الفرصة هو المقيم)
        $reviews = Review::with(['opportunity.user.organization'])
            ->where('user_id', $userId)
            ->latest()
            ->get();

        $averageRating = Review::where('user_id', $userId)->avg('rating');

        return response()->json([
            'average_rating' => round($averageRating, 1),
            'total_reviews'  => $reviews->count(),
            'reviews'        => $reviews
        ]);
    }
    
}