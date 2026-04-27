<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',        // المعرف الخاص بالمتطوع (المستهدف بالتقييم)
        'opportunity_id', // الفرصة التي تم إنجازها ويتم تقييم الأداء فيها
        'rating',         // الدرجة المستحقة (1-5)
        'comment'         // ملاحظات المؤسسة حول أداء المتطوع
    ];

    /**
     * العلاقة مع المتطوع (المستخدم المستهدف بالتقييم)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * العلاقة مع الفرصة المرتبطة بهذا التقييم
     */
    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    protected $casts = [
        'rating' => 'integer',
        'created_at' => 'datetime:Y-m-d',
    ];

    /**
     * Scope لجلب التقييمات الممتازة (تستخدم في لوحة التحكم أو ملف المتطوع)
     */
    public function scopeExcellent($query)
    {
        return $query->where('rating', '>=', 4);
    }
}