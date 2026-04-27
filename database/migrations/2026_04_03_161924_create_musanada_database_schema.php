<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. المستخدمين (الحسابات الأساسية)
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['student', 'organization', 'admin']);
            $table->string('phone')->nullable();
            $table->string('profile_image')->nullable();
            $table->string('location')->nullable();
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 11, 8)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. ملفات الأفراد (بناء البورتفوليو المهني)
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('university')->nullable();
            $table->string('major')->nullable();
            $table->text('bio')->nullable(); 
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->integer('total_volunteer_hours')->default(0);
            $table->integer('total_training_hours')->default(0);
            $table->timestamps();
        });

        // 3. المؤسسات (مطابق تماماً لبيانات التواصل والنوع في التحليل)
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('org_name');
            $table->string('org_type');
            $table->string('contact_person');
            $table->string('license_file')->nullable();
            $table->string('digital_signature')->nullable();
            $table->text('description')->nullable();
            $table->string('website')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
        });

        // 4. المهارات
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // 5. الربط بين المستخدمين والمهارات
        Schema::create('user_skill', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('skill_id')->constrained()->onDelete('cascade');
        });

        // 6. الفرص (تطوع، تدريب، دورات)
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->string('cover_image')->nullable();
            $table->string('location');
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 11, 8)->nullable();
            $table->string('duration');
            $table->text('requirements')->nullable();
            $table->integer('required_volunteers')->default(1);
            $table->date('deadline');
            $table->enum('status', ['open', 'closed', 'completed', 'hidden'])->default('open');
            $table->enum('type', ['voluntary', 'training', 'course'])->default('voluntary');
            $table->enum('gender', ['male', 'female', 'both'])->default('both');
            $table->timestamps();
        });

        // 7. مهارات الفرصة
        Schema::create('opportunity_skill', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->onDelete('cascade');
            $table->foreignId('skill_id')->constrained()->onDelete('cascade');
        });

        // 8. طلبات التقديم (مع حالة القبول والرفض)
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('opportunity_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['pending', 'accepted', 'rejected', 'completed'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        // 9. سجل الساعات (مع نظام الاعتماد والموافقة)
        Schema::create('hour_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('opportunity_id')->constrained()->onDelete('cascade');
            $table->integer('hours');
            $table->text('notes')->nullable(); 
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->date('date_logged');
            $table->timestamps();
        });

        // 10. التقييمات والشهادات والإشعارات
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('opportunity_id')->constrained()->onDelete('cascade');
            $table->integer('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
            // حماية لمنع تكرار التقييم لنفس الطالب في نفس الفرصة
            $table->unique(['user_id', 'opportunity_id']);
        });



        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('opportunity_id')->constrained()->onDelete('cascade');
            $table->string('certificate_code')->unique();
            $table->string('file_path')->nullable();
            $table->date('issue_date');
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('file_path');
            $table->string('file_type')->nullable(); 
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('hour_logs');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('opportunity_skill');
        Schema::dropIfExists('opportunities');
        Schema::dropIfExists('user_skill');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('users');
    }
};
