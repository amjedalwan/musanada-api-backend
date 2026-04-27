<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Application extends Model
{
    use HasFactory;

    // تحديث الحقول لتشمل سبب الرفض (مهم جداً للمناقشة)
    protected $fillable = [
        'user_id',
        'opportunity_id',
        'status',
        'rejection_reason'
    ];

    /**
     * العلاقة مع المستخدم (المتقدم)
     * ملاحظة: نربط بـ User مباشرة أو بـ UserProfile حسب تفضيلك في الاستعلام
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function hourLogs()
    {
        // نربط الساعات بالمستخدم والفرصة معاً لضمان الدقة
        return $this->hasMany(HourLog::class, 'user_id', 'user_id')
            ->whereColumn('opportunity_id', 'opportunity_id');
    }

    /**
     * العلاقة مع الفرصة التطوعية
     */
    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }
    public function certificate()
    {
        return $this->hasOne(Certificate::class, 'opportunity_id', 'opportunity_id')
            ->where('user_id', $this->user_id);
    }
}
