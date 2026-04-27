<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Opportunity extends Model
{
    use HasFactory;

    // تحديث الحقول لتشمل الإحداثيات والعدد المطلوب كما في المايجريشن والتحليل
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'location',
        'lat',          // أضف هذا
        'lng',          // أضف هذا
        'duration',
        'requirements',
        'required_volunteers',
        'start_date',
        'deadline',
        'type',
        'gender',
        'status',
        'cover_image'
    ];

    // جلب بيانات المؤسسة تلقائياً لتحسين أداء واجهة Flutter
    protected $with = ['user'];
    protected $appends = ['fill_percentage', 'total_logged_hours', 'is_expired','accepted_count']; // أضفنا الحقل هنا
    /**
     * علاقة الفرصة بالمؤسسة الناشرة
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }


    public function getIsExpiredAttribute()
    {
        // نتحقق مما إذا كان تاريخ اليوم قد تجاوز التاريخ النهائي (deadline)
        // نستخدم مكتبة Carbon المدمجة في لارافل
        if (!$this->deadline) {
            return false;
        }

        return \Carbon\Carbon::now()->greaterThan($this->deadline);
    }
    /**
     * علاقة الفرصة بالمهارات المطلوبة (جدول وسيط: opportunity_skill)
     */
    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'opportunity_skill');
    }

    /**
     * علاقة الفرصة بطلبات التقديم (Applications)
     */
    public function applications()
    {
        return $this->hasMany(Application::class);
    }
    // داخل class Opportunity extends Model
    public function applicants()
    {
        // افترضنا أن لديك جدول اسمه applications يربط الطالب بالفرصة
        return $this->hasMany(Application::class, 'opportunity_id');
    }
    /**
     * علاقة الفرصة بسجلات الساعات المنجزة (HourLogs)
     */
    // 1. تعريف العلاقة أولاً
    public function hourLogs()
    {
        return $this->hasMany(HourLog::class, 'opportunity_id');
    }

    // 2. السمة المحسوبة تأتي بعدها
    public function getTotalLoggedHoursAttribute()
    {
        // نستخدم querybuilder مباشرة لضمان الدقة
        return (float) $this->hourLogs()->where('status', 'approved')->sum('hours');
    }
    public function acceptedVolunteers()
    {
        return $this->belongsToMany(User::class, 'applications')
            ->wherePivot('status', 'accepted');
    }
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
    // حساب عدد الطلبات التي حالتها 'accepted' فقط
    public function acceptedApplications()
    {
        return $this->hasMany(Application::class)->where('status', 'accepted');
    }
    public function getCoverImageAttribute($value)
    {
        // إذا لم تكن هناك صورة في قاعدة البيانات، نرجع null
        // لكي يتعامل الفرونت إند معها ويظهر الأيقونة
        if (!$value) {
            return null;
        }

        // إذا كانت القيمة مخزنة كمسار كامل (رابط) نرجعها كما هي
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        // غير ذلك نرجع المسار داخل التخزين
        return asset('storage/' . $value);
    }
    public function getFillPercentageAttribute()
    {
        if ($this->required_volunteers <= 0) return 0;

        $acceptedCount = $this->applications()->where('status', 'accepted')->count();

        return round(($acceptedCount / $this->required_volunteers) * 100, 1);
    }
    public function scopeActive($query)
    {
        return $query->where('status', 'open' || 'closed');
    }

    /**
     * منطق الفلترة والبحث
     */
    public function scopeFilter($query, array $filters)
    {
        // البحث بالكلمة المفتاحية (العنوان أو الوصف)
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        });

        // الفلترة حسب النوع (فرصة ميدانية، عن بعد، إلخ)
        $query->when($filters['type'] ?? null, function ($query, $type) {
            $query->where('type', $type);
        });

        // الفلترة حسب الجنس المطلوب
        $query->when($filters['gender'] ?? null, function ($query, $gender) {
            $query->where('gender', $gender);
        });

        return $query;
    }
   

    public function getAcceptedCountAttribute()
    {
        // نفترض أن لديك علاقة اسمها applications
        return $this->applications()->where('status', 'accepted')->count();
    }
}
