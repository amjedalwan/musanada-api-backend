<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    // 1. جلب كافة إشعارات المستخدم (الأحدث أولاً)
    public function index()
    {
        // نستخدم العلاقة الجاهزة notifications التي يوفرها لارافل تلقائياً
        $notifications = Auth::user()->notifications;

        return response()->json($notifications);
    }

    // 2. تحديد إشعار معين كمقروء
    public function markAsRead($id)
    {
        // نبحث في إشعارات المستخدم الحالي فقط لضمان الأمان
        $notification = Auth::user()->notifications->findOrFail($id);

        // مارافل يستخدم دالة markAsRead() لتحديث عمود read_at تلقائياً
        $notification->markAsRead();

        return response()->json(['message' => 'تم تحديد الإشعار كمقروء']);
    }

    // 3. حذف إشعار
    public function destroy($id)
    {
        $notification = Auth::user()->notifications->findOrFail($id);
        $notification->delete();

        return response()->json(['message' => 'تم حذف الإشعار']);
    }

    // 4. جلب عدد الإشعارات غير المقروءة
    public function unreadCount()
    {
        // نستخدم خاصية unreadNotifications الجاهزة
        $count = Auth::user()->unreadNotifications->count();

        return response()->json(['unread_count' => $count]);
    }

    // 5. تحديد الكل كمقروء
    public function markAllAsRead()
    {
        // سطر واحد يقوم بتحديث كل الإشعارات غير المقروءة دفعة واحدة
        Auth::user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'تم تحديد جميع الإشعارات كمقروءة']);
    }
}