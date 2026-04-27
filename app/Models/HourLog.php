<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HourLog extends Model
{
    use HasFactory;

    // تحديث الحقول لتشمل الوصف وحالة الاعتماد من قبل المؤسسة
    protected $fillable = [
        'user_id',         // ربط بالمستخدم (بدلاً من student_id لتوافق النظام الموحد)
        'opportunity_id',  // الفرصة التي تم العمل فيها
        'hours',           // عدد الساعات المنجزة
        'notes',           // وصف المهمة (مهم للتوثيق)
        'status',          // حالة السجل (pending, approved, rejected)
        'date_logged'      // تاريخ تنفيذ النشاط
    ];

    /**
     * العلاقة مع المستخدم (المتطوع)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * العلاقة مع الفرصة التطوعية
     */
    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }
    // داخل ملف HourLog.php
    protected $casts = [
        'date_logged' => 'date',
        'hours' => 'float', // لضمان دقة العمليات الحسابية إذا كان هناك أنصاف ساعات
    ];
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
    
}
