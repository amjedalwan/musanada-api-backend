<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Application;
use App\Notifications\ApplicationStatusChanged;
use Illuminate\Database\Eloquent\Casts\Attribute;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    protected $fillable = [
        'full_name',
        'email',
        'password',
        'role',
        'phone',
        'profile_image',
        'location',
        'lat',
        'lng',
        'is_active',
    ];
    protected $hidden = [
        'password',
        'remember_token',
    ];
    protected function profileImage(): Attribute
    {
        return Attribute::make(get: fn($value) => $value ?? 'profiles/default.jpg');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    // علاقة الملف الشخصي (للطلاب/الخريجين/المتطوعين)
    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }
    public function hourLogs()
    {
        return $this->hasMany(HourLog::class);
    }
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
    public function applications()
    {
        return $this->hasMany(Application::class);
    }
    // علاقة المؤسسة
    public function organization()
    {
        return $this->hasOne(Organization::class);
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'user_skill', 'user_id', 'skill_id');
    }


    public function sendNotify($application, $title, $message)
    {
        // نستخدم notify() بدلاً من notifications()->create()
        return $this->notify(new ApplicationStatusChanged($application, $title, $message));
    }
    // أضف هذا داخل كلاس User في ملف User.php
    public function getAverageRatingAttribute()
    {
        return round($this->reviews()->avg('rating') ?? 5.0, 1);
    }
    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }
    public function scopeAdmin($query)
    {
        return $query->where('role', 'admin');
    }
}
