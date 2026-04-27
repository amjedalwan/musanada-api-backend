<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserProfile extends Model
{
    use HasFactory;

    // تم تحديث الحقول لتطابق المايجريشن الشامل والتحليل (صفحة 9)
    protected $fillable = [
        'user_id',
        'university',
        'major',
        'bio',
        'birth_date',
        'gender',
        'total_volunteer_hours', // الاسم المحدث لـ total_hours
        'total_training_hours'   // حقل إضافي للتدريب التعاوني
    ];



    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
