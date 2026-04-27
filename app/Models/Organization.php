<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'org_name',
        'org_type',       // (خيرية، مبادرة، حكومية، خاصة)
        'contact_person', // الشخص المسؤول عن التنسيق
        'license_file',     // رقم الترخيص للتوثيق
        'description',
        'website',
        'digital_signature',
        'lat',
        'lng',
        'is_verified'     // حالة تفعيل الحساب من الإدارة
    ];

    /**
     * تحويل الحقل إلى Boolean لسهولة التعامل معه في Flutter
     */
    protected $casts = [
        'is_verified' => 'boolean',
    ];

    /**
     * العلاقة مع الحساب الأساسي (User)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * علاقة المؤسسة بالفرص التي تنشرها
     * ملاحظة: الربط يتم عبر user_id لأن الفرص مرتبطة بالمستخدم الناشر
     */
    public function opportunities()
    {
        return $this->hasMany(Opportunity::class, 'user_id', 'user_id');
    }
}