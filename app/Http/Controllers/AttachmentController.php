<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    /**
     * 1. رفع مرفق جديد
     */
    public function store(Request $request)
    {
        $request->validate([
            'file'      => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', 
            'file_type' => 'required|string|in:id_card,license,academic_transcript,other',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($request->hasFile('file')) {
            $fileName = time() . '_' . $request->file('file')->getClientOriginalName();
            $path = $request->file('file')->storeAs('attachments/user_' . $user->id, $fileName, 'public');

            // استخدام العلاقة مباشرة لإنشاء المرفق لضمان دقة البيانات (MorphMany)
            $attachment = $user->attachments()->create([
                'file_path'           => $path,
                'file_type'           => $request->file_type,
                'verification_status' => 'pending', 
            ]);

            return response()->json([
                'message'    => 'تم رفع المستند بنجاح وهو قيد المراجعة',
                'attachment' => $attachment,
                'url'        => asset('storage/' . $path)
            ], 201);
        }

        return response()->json(['message' => 'حدث خطأ أثناء رفع الملف'], 400);
    }

    /**
     * 2. عرض مرفقات المستخدم الحالي
     */
    public function myAttachments()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // جلب المرفقات وإضافة رابط URL لكل ملف لتسهيل العرض في Flutter
        $attachments = $user->attachments()->latest()->get()->map(function($attachment) {
            $attachment->full_url = asset('storage/' . $attachment->file_path);
            return $attachment;
        });

        return response()->json($attachments);
    }

    /**
     * 3. حذف مرفق
     */
    public function destroy($id)
    {
        $attachment = Attachment::findOrFail($id);

        // حماية: التأكد أن المرفق يخص المستخدم الحالي
        if ($attachment->attachable_id !== Auth::id()) {
            return response()->json(['message' => 'غير مصرح لك بحذف هذا الملف'], 403);
        }

        if ($attachment->verification_status === 'verified') {
            return response()->json(['message' => 'لا يمكن حذف مستند تم التحقق منه مسبقاً'], 422);
        }

        // حذف الملف من التخزين الفيزيائي
        Storage::disk('public')->delete($attachment->file_path);
        
        $attachment->delete();

        return response()->json(['message' => 'تم حذف المستند بنجاح']);
    }
}