<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    use HasFactory;

    // الحقول المحدثة لتشمل رابط الملف وكود التحقق الفريد
    protected $fillable = [
        'user_id',            // ربط بالمستخدم (المتطوع/الخريج)
        'opportunity_id',     // ربط بالفرصة التي تم إنجازها
        'certificate_code',   // كود التحقق الفريد (Unique Verification Code)
        'file_path',          // مسار ملف الـ PDF الخاص بالشهادة
        'issue_date'          // تاريخ الإصدار
    ];

    /**
     * العلاقة مع المستخدم (صاحب الشهادة)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * العلاقة مع الفرصة المرتبطة بالشهادة
     */
    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }
}