<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    // إخفاء حقل pivot عند إرجاع البيانات لـ Flutter لجعل الـ JSON نظيفاً
    protected $hidden = ['pivot', 'created_at', 'updated_at'];

    /**
     * العلاقة مع المستخدمين (المتطوعين/الطلاب)
     * ملاحظة: تم التغيير لترتبط بـ User مباشرة أو UserProfile حسب المايجريشن
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_skill');
    }

    /**
     * العلاقة مع الفرص التطوعية
     * لكي نعرف ما هي المهارات المطلوبة لكل فرصة
     */
    public function opportunities()
    {
        return $this->belongsToMany(Opportunity::class, 'opportunity_skill');
    }
}