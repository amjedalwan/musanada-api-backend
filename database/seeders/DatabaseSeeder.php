<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. تنظيف قاعدة البيانات
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        $tables = [
            'attachments',
            'certificates',
            'reviews',
            'hour_logs',
            'applications',
            'opportunity_skill',
            'opportunities',
            'user_skill',
            'skills',
            'organizations',
            'user_profiles',
            'users'
        ];
        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 2. المهارات المطلوبة في السوق السعودي
        $skillNames = [
            'برمجة (Laravel)', 'تصميم واجهات (UI/UX)', 'إدارة فعاليات', 'إسعافات أولية',
            'تصميم جرافيك', 'تسويق رقمي', 'كتابة محتوى', 'علاقات عامة', 'إدارة حشود',
            'تصوير فوتوغرافي', 'مونتاج فيديو', 'إدخال بيانات', 'تحليل بيانات',
            'لغة إشارة', 'إرشاد سياحي', 'خدمة عملاء', 'تطوير تطبيقات الجوال'
        ];
        $skillIds = [];
        foreach ($skillNames as $name) {
            $skillIds[] = DB::table('skills')->insertGetId(['name' => $name, 'created_at' => now()]);
        }

        // 3. حساب مدير النظام الأساسي (أنت)
        $adminId = DB::table('users')->insertGetId([
            'full_name' => 'Amjad Alwan',
            'email' => 'admin@musanada.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone' => '0500000000',
            'profile_image' => 'admin_profile.png',
            'location' => 'Riyadh',
            'is_active' => true,
            'created_at' => Carbon::now()->subMonths(12),
        ]);

        // 4. المؤسسات السعودية (10 مؤسسات)
        $orgData = [
            ['name' => 'مؤسسة محمد بن سلمان (مسك)', 'type' => 'غير ربحية', 'loc' => 'الرياض', 'email' => 'info@misk.org.sa'],
            ['name' => 'جمعية إطعام', 'type' => 'جمعية خيرية', 'loc' => 'الدمام', 'email' => 'contact@saudifoodbank.com'],
            ['name' => 'منصة إحسان', 'type' => 'حكومية', 'loc' => 'الرياض', 'email' => 'info@ehsan.sa'],
            ['name' => 'الهلال الأحمر السعودي', 'type' => 'حكومية', 'loc' => 'الرياض', 'email' => 'info@srca.org.sa'],
            ['name' => 'جمعية البر', 'type' => 'جمعية خيرية', 'loc' => 'جدة', 'email' => 'info@albir.sa'],
            ['name' => 'جمعية الأطفال ذوي الإعاقة', 'type' => 'جمعية خيرية', 'loc' => 'الرياض', 'email' => 'info@dca.org.sa'],
            ['name' => 'مركز الملك سلمان للإغاثة', 'type' => 'حكومية', 'loc' => 'الرياض', 'email' => 'info@ksrelief.org'],
            ['name' => 'جمعية رعاية الأيتام (إنسان)', 'type' => 'جمعية خيرية', 'loc' => 'الرياض', 'email' => 'ensan@ensan.org.sa'],
            ['name' => 'مبادرة السعودية الخضراء', 'type' => 'مبادرة وطنية', 'loc' => 'الرياض', 'email' => 'sg@green.sa'],
            ['name' => 'جمعية ماجد للتنمية', 'type' => 'جمعية تنموية', 'loc' => 'جدة', 'email' => 'info@majid.org.sa'],
        ];

        $orgUserIds = [];
        foreach ($orgData as $idx => $org) {
            $createdAt = Carbon::now()->subMonths(rand(1, 11))->subDays(rand(1, 28));
            
            $uId = DB::table('users')->insertGetId([
                'full_name' => $org['name'],
                'email' => $org['email'],
                'password' => Hash::make('password'),
                'role' => 'organization',
                'location' => $org['loc'],
                'phone' => '05500000' . sprintf('%02d', $idx),
                'profile_image' => null, // سيتم استخدام الأيقونة الافتراضية
                'created_at' => $createdAt,
            ]);
            $orgUserIds[] = $uId;

            DB::table('organizations')->insert([
                'user_id' => $uId,
                'org_name' => $org['name'],
                'org_type' => $org['type'],
                'contact_person' => 'مدير العلاقات العامة',
                'is_verified' => true,
                'description' => "مؤسسة وطنية رائدة تعمل في مجال " . $org['type'] . " مقرها في " . $org['loc'],
                'created_at' => $createdAt,
            ]);
        }
        
        // مؤسسة واحدة معلقة للاختبار
        $pendingOrgUId = DB::table('users')->insertGetId([
            'full_name' => 'مبادرة صناع الأمل',
            'email' => 'pending@hope.sa',
            'password' => Hash::make('password'),
            'role' => 'organization',
            'location' => 'مكة المكرمة',
            'created_at' => now(),
        ]);
        DB::table('organizations')->insert([
            'user_id' => $pendingOrgUId,
            'org_name' => 'مبادرة صناع الأمل',
            'org_type' => 'مبادرة شبابية',
            'contact_person' => 'خالد العتيبي',
            'is_verified' => false,
            'description' => 'مبادرة جديدة تسعى للانضمام للمنصة.',
            'created_at' => now(),
        ]);


        // 5. الطلاب / المتطوعين السعوديين (25 طالب)
        $maleNames = ['محمد', 'عبدالله', 'خالد', 'عبدالرحمن', 'سعود', 'فهد', 'تركي', 'فيصل', 'صالح', 'سلطان'];
        $femaleNames = ['نورة', 'سارة', 'ريم', 'شهد', 'مريم', 'الهنوف', 'لمى', 'ريما', 'روان', 'هيفاء'];
        $families = ['الدوسري', 'القحطاني', 'المطيري', 'العتيبي', 'الغامدي', 'الزهراني', 'الشهري', 'العنزي', 'الشمري', 'الجهني'];
        $universities = [
            'جامعة الملك سعود', 'جامعة الإمام محمد بن سعود', 'جامعة الملك عبدالعزيز',
            'جامعة أم القرى', 'جامعة الملك فهد للبترول والمعادن', 'جامعة الأميرة نورة',
            'جامعة الطائف', 'جامعة الملك خالد', 'جامعة جدة', 'جامعة الأمير سلطان'
        ];
        $majors = ['هندسة برمجيات', 'تقنية معلومات', 'إدارة أعمال', 'طب وجراحة', 'تصميم جرافيك', 'تسويق', 'إعلام', 'قانون', 'لغة إنجليزية', 'تمريض'];

        $studentIds = [];
        for ($i = 1; $i <= 25; $i++) {
            $isMale = rand(0, 1) == 1;
            $fName = $isMale ? $maleNames[array_rand($maleNames)] : $femaleNames[array_rand($femaleNames)];
            $lName = $families[array_rand($families)];
            $gender = $isMale ? 'male' : 'female';
            $uni = $universities[array_rand($universities)];
            $major = $majors[array_rand($majors)];
            $createdAt = Carbon::now()->subMonths(rand(1, 11))->subDays(rand(1, 28));

            $sId = DB::table('users')->insertGetId([
                'full_name' => "$fName $lName",
                'email' => "student{$i}@example.com",
                'password' => Hash::make('password'),
                'role' => 'student',
                'location' => ['الرياض', 'جدة', 'الدمام', 'مكة', 'المدينة'][array_rand(['الرياض', 'جدة', 'الدمام', 'مكة', 'المدينة'])],
                'phone' => '05' . rand(0, 9) . rand(1000000, 9999999),
                'created_at' => $createdAt,
            ]);
            $studentIds[] = $sId;

            DB::table('user_profiles')->insert([
                'user_id' => $sId,
                'university' => $uni,
                'major' => $major,
                'bio' => "طالب في $uni تخصص $major طموح ومحب للعمل التطوعي وخدمة المجتمع السعودي.",
                'gender' => $gender,
                'birth_date' => Carbon::now()->subYears(rand(19, 25))->subDays(rand(1, 365)),
                'created_at' => $createdAt,
            ]);

            // مهارات عشوائية (2 إلى 4 مهارات)
            $userSkills = (array) array_rand(array_flip($skillIds), rand(2, 4));
            foreach ($userSkills as $skId) {
                DB::table('user_skill')->insert(['user_id' => $sId, 'skill_id' => $skId]);
            }
        }

        // 6. إنشاء الفرص التطوعية المتنوعة (20 فرصة)
        $oppTemplates = [
            ['title' => 'تنظيم مؤتمر تقني', 'type' => 'voluntary', 'req' => 'مهارات في إدارة الحشود والتعامل مع الجمهور', 'reqSkills' => [3, 9, 16], 'hours' => 20],
            ['title' => 'دورة مكثفة في Laravel', 'type' => 'course', 'req' => 'معرفة بأساسيات البرمجة', 'reqSkills' => [1], 'hours' => 15],
            ['title' => 'حملة توزيع السلال الغذائية', 'type' => 'voluntary', 'req' => 'اللياقة البدنية للعمل الميداني', 'reqSkills' => [3], 'hours' => 10],
            ['title' => 'تدريب صيفي: مصمم واجهات', 'type' => 'training', 'req' => 'خبرة سابقة في Figma', 'reqSkills' => [2, 5], 'hours' => 60],
            ['title' => 'التوعية الصحية لضيوف الرحمن', 'type' => 'voluntary', 'req' => 'طالب في التخصصات الصحية', 'reqSkills' => [4, 8], 'hours' => 40],
            ['title' => 'تصوير فعاليات اليوم الوطني', 'type' => 'voluntary', 'req' => 'امتلاك كاميرا احترافية', 'reqSkills' => [10, 11], 'hours' => 12],
            ['title' => 'تطوير موقع الجمعية', 'type' => 'voluntary', 'req' => 'خبرة في Laravel و React', 'reqSkills' => [1, 2], 'hours' => 50],
            ['title' => 'إدارة حسابات التواصل الاجتماعي', 'type' => 'training', 'req' => 'شغف بالتسويق الرقمي', 'reqSkills' => [6, 7], 'hours' => 30],
            ['title' => 'فرز وتغليف التبرعات', 'type' => 'voluntary', 'req' => 'حب العمل الخيري', 'reqSkills' => [], 'hours' => 15],
            ['title' => 'مترجم لغة إشارة في ورشة عمل', 'type' => 'voluntary', 'req' => 'إتقان لغة الإشارة المعتمدة', 'reqSkills' => [14], 'hours' => 5],
            ['title' => 'إدخال بيانات المستفيدين', 'type' => 'voluntary', 'req' => 'سرعة الطباعة ودقة الملاحظة', 'reqSkills' => [12], 'hours' => 25],
            ['title' => 'إرشاد سياحي في الدرعية', 'type' => 'voluntary', 'req' => 'معرفة بتاريخ المملكة واللباقة', 'reqSkills' => [8, 15], 'hours' => 20],
        ];

        $oppIds = [];
        $opportunityList = []; // لحفظ التفاصيل للمرحلة القادمة
        
        for ($i = 0; $i < 20; $i++) {
            $template = $oppTemplates[array_rand($oppTemplates)];
            $statusList = ['open', 'open', 'closed', 'completed']; // أكثر الفرص مفتوحة
            $status = $statusList[array_rand($statusList)];
            
            // تاريخ إنشاء متناثر
            $createdAt = Carbon::now()->subMonths(rand(0, 8))->subDays(rand(1, 28));
            $deadline = (clone $createdAt)->addDays(rand(15, 60));

                $genderOptions = ['male', 'female', 'both', 'both', 'both'];
                $gender = $genderOptions[array_rand($genderOptions)];
                
                $oId = DB::table('opportunities')->insertGetId([
                    'user_id' => $orgUserIds[array_rand($orgUserIds)],
                    'title' => $template['title'] . ($i > 11 ? ' - النسخة ' . rand(2, 5) : ''),
                    'description' => "هذه الفرصة تتيح لك الانخراط في تجربة حقيقية لخدمة المجتمع، نسعى لاستقطاب المتميزين للمشاركة في {$template['title']}.",
                    'location' => ['الرياض', 'جدة', 'الدمام', 'مكة المكرمة', 'الخبر', 'أبها'][array_rand(['الرياض', 'جدة', 'الدمام', 'مكة المكرمة', 'الخبر', 'أبها'])],
                    'duration' => rand(1, 4) . ' أسابيع',
                    'requirements' => $template['req'],
                    'required_volunteers' => rand(5, 50),
                    'deadline' => $deadline,
                    'status' => $status,
                    'type' => $template['type'],
                    'gender' => $gender,
                    'created_at' => $createdAt,
                ]);
            $oppIds[] = $oId;
            $opportunityList[] = ['id' => $oId, 'status' => $status, 'hours' => $template['hours']];

            // ربط المهارات
            foreach ($template['reqSkills'] as $skId) {
                DB::table('opportunity_skill')->insert(['opportunity_id' => $oId, 'skill_id' => $skId]);
            }
        }

        // 7. تقديم الطلاب للفرص والعمليات (Applications, HourLogs, Reviews, Certificates)
        $usedCombinations = [];

        foreach ($opportunityList as $opp) {
            // لكل فرصة، هناك 5 إلى 15 متقدم
            $applicantsCount = rand(5, 15);
            $applicants = (array) array_rand(array_flip($studentIds), $applicantsCount);

            foreach ($applicants as $sId) {
                if (isset($usedCombinations["{$sId}-{$opp['id']}"])) continue;
                $usedCombinations["{$sId}-{$opp['id']}"] = true;

                $appStatus = 'pending';
                if ($opp['status'] == 'completed' || $opp['status'] == 'closed') {
                    $appStatus = (rand(1, 100) > 30) ? 'accepted' : 'rejected';
                } else {
                    $statusOptions = ['pending', 'accepted', 'accepted', 'rejected'];
                    $appStatus = $statusOptions[array_rand($statusOptions)];
                }

                $appCreatedAt = Carbon::now()->subMonths(rand(0, 5))->subDays(rand(1, 28));

                DB::table('applications')->insert([
                    'user_id' => $sId,
                    'opportunity_id' => $opp['id'],
                    'status' => $appStatus,
                    'rejection_reason' => $appStatus == 'rejected' ? 'اكتفاء العدد المطلوب' : null,
                    'created_at' => $appCreatedAt,
                ]);

                // إذا كان مقبولاً والفرصة انتهت، نوثق الساعات
                if ($appStatus == 'accepted' && ($opp['status'] == 'completed' || $opp['status'] == 'closed')) {
                    $hoursLogged = $opp['hours'];
                    
                    DB::table('hour_logs')->insert([
                        'user_id' => $sId,
                        'opportunity_id' => $opp['id'],
                        'hours' => $hoursLogged,
                        'notes' => 'إنجاز متميز وحضور كامل',
                        'status' => 'approved',
                        'date_logged' => (clone $appCreatedAt)->addDays(rand(10, 30)),
                        'created_at' => (clone $appCreatedAt)->addDays(rand(10, 30)),
                    ]);

                    // تحديث إجمالي ساعات الطالب في البروفايل
                    DB::table('user_profiles')->where('user_id', $sId)->increment('total_volunteer_hours', $hoursLogged);

                    // إصدار شهادة
                    DB::table('certificates')->insert([
                        'user_id' => $sId,
                        'opportunity_id' => $opp['id'],
                        'certificate_code' => 'KSA-' . date('Y') . '-' . strtoupper(Str::random(6)),
                        'issue_date' => (clone $appCreatedAt)->addDays(rand(31, 40)),
                        'created_at' => (clone $appCreatedAt)->addDays(rand(31, 40)),
                    ]);

                    // الطالب يكتب تقييم للفرصة
                    if (rand(1, 100) > 40) {
                        $reviews = ['تجربة رائعة وتطوع مثمر', 'التنظيم كان ممتاز جداً', 'استفدت كثيراً من هذه المبادرة', 'بيئة عمل محفزة ورائعة'];
                        DB::table('reviews')->insert([
                            'user_id' => $sId,
                            'opportunity_id' => $opp['id'],
                            'rating' => rand(4, 5),
                            'comment' => $reviews[array_rand($reviews)],
                            'created_at' => (clone $appCreatedAt)->addDays(rand(41, 50)),
                        ]);
                    }
                }
            }
        }
    }
}
