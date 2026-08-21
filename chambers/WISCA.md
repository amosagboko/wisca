# WISCA Strategy Monitor — Configuration-Driven Architecture Specification
## Laravel Backend (PHP 8.2+, Laravel 11+)

---

## 1. Architectural Philosophy

> **Every decision point is a database-backed configuration.**

Instead of hardcoding thresholds like "3 days for escalation" or "95% = On Track" in code, the system stores these as **configuration records** managed through an Admin UI. This allows the Board/Proprietor to adjust strategic rules without deploying new code.

### Configuration-Driven Examples
| Hardcoded (Bad) | Configuration-Driven (Good) |
|---|---|
| `if ($days > 3) escalate()` | `EscalationRule::for('verification')->delay_hours` |
| `if ($rate >= 0.95) status = 'green'` | `Kpi::find('AE-01')->config->thresholds->on_track` |
| `Observer must score >= 3` | `ObservationRubric::where('level', 3)->first()->config->pass_threshold` |

---

## 2. High-Level Entity Relationship

```
+---------------+     +----------------+     +---------------+
|   schools     |---->|academic_sessions|---->|    terms      |
+---------------+     +----------------+     +---------------+
       |                       |
       v                       v
+---------------+     +----------------+
| school_classes|     |class_subject_  |
+---------------+     |    teacher     |
       |              +----------------+
       |                       |
       +-----------+-----------+
                   v
          +---------------+
          |schemes_of_work|
          +---------------+
                   |
       +-----------+-----------+
       v           v           v
  +--------+  +----------+ +-----------------+
  | topics |  |lesson_plans| |topic_delivery_  |
  +--------+  +----------+ |     logs        |
                           +-----------------+
                                    |
                                    v
                           +---------------+
                           | verifications | (polymorphic)
                           +---------------+
                                    |
                                    v
                           +---------------+
                           |escalation_rules|
                           +---------------+

+---------------+     +----------------+     +---------------+
|   pillars     |---->|      kpis      |---->|kpi_periodic_  |
+---------------+     +----------------+     |    data       |
       |                       |             +---------------+
       v                       v
+---------------+     +----------------+
|   settings    |     |notification_   |
+---------------+     |   templates    |
                      +----------------+

+---------------+     +----------------+     +---------------+
|     users     |<----| model_has_roles|---->|    roles      |
+---------------+     +----------------+     +---------------+
       |
       v
+---------------+
|  audit_logs   |
+---------------+
```

---

## 3. Database Migrations

### 3.1 Core & Identity

```php
// 2025_01_10_000001_create_schools_table.php
Schema::create('schools', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('address')->nullable();
    $table->string('logo')->nullable();
    $table->json('settings')->nullable(); // timezone, grading_scale, etc.
    $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
    $table->timestamps();
    $table->softDeletes();
});

// 2025_01_10_000002_create_academic_sessions_table.php
Schema::create('academic_sessions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('school_id')->constrained()->cascadeOnDelete();
    $table->string('name'); // e.g., "2025/2026"
    $table->date('start_date');
    $table->date('end_date');
    $table->boolean('is_current')->default(false);
    $table->enum('status', ['upcoming', 'active', 'closed'])->default('upcoming');
    $table->timestamps();
});

// 2025_01_10_000003_create_terms_table.php
Schema::create('terms', function (Blueprint $table) {
    $table->id();
    $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
    $table->string('name'); // First Term, Second Term, Third Term
    $table->date('start_date');
    $table->date('end_date');
    $table->enum('status', ['upcoming', 'active', 'closed'])->default('upcoming');
    $table->timestamps();
});

// 2025_01_10_000004_create_school_classes_table.php
Schema::create('school_classes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('school_id')->constrained()->cascadeOnDelete();
    $table->string('name'); // e.g., "JSS 1A", "SS 2B"
    $table->string('level'); // Primary, JSS, SSS
    $table->integer('display_order')->default(0);
    $table->enum('status', ['active', 'inactive'])->default('active');
    $table->timestamps();
    $table->softDeletes();
});

// 2025_01_10_000005_create_subjects_table.php
Schema::create('subjects', function (Blueprint $table) {
    $table->id();
    $table->foreignId('school_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('code')->nullable(); // MTH, ENG
    $table->enum('status', ['active', 'inactive'])->default('active');
    $table->timestamps();
    $table->softDeletes();
});

// 2025_01_10_000006_create_class_subject_teacher_table.php
Schema::create('class_subject_teacher', function (Blueprint $table) {
    $table->id();
    $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
    $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
    $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
    $table->enum('status', ['active', 'inactive'])->default('active');
    $table->timestamps();

    $table->unique(['school_class_id', 'subject_id', 'academic_session_id'], 'unique_cst');
});
```

### 3.2 Users, Roles & Permissions

**Recommendation:** Use `spatie/laravel-permission` package. Extend roles with configuration.

```php
// 2025_01_10_000007_create_role_configs_table.php
Schema::create('role_configs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('role_id')->constrained()->cascadeOnDelete();
    $table->json('dashboard_widgets'); // which widgets this role sees
    $table->json('allowed_actions'); // create, read, update, delete, verify, approve
    $table->json('notification_preferences');
    $table->timestamps();
});
```

### 3.3 Strategic Framework (Configuration Engine)

```php
// 2025_01_10_000100_create_pillars_table.php
Schema::create('pillars', function (Blueprint $table) {
    $table->id();
    $table->foreignId('school_id')->constrained()->cascadeOnDelete();
    $table->string('name'); // Academic Excellence
    $table->string('code')->unique(); // academic_excellence
    $table->text('description')->nullable();
    $table->integer('display_order')->default(0);
    $table->json('config')->nullable(); // color_theme, icon, weight_in_overall_score
    $table->enum('status', ['active', 'inactive'])->default('active');
    $table->timestamps();
});

// 2025_01_10_000101_create_kpis_table.php
Schema::create('kpis', function (Blueprint $table) {
    $table->id();
    $table->foreignId('pillar_id')->constrained()->cascadeOnDelete();
    $table->string('code')->unique(); // AE-01, CE-03
    $table->string('name'); // Curriculum Coverage Rate
    $table->text('description')->nullable();
    $table->text('measurement_methodology')->nullable();
    $table->string('calculation_formula')->nullable(); // expression template
    $table->string('data_collection_instrument')->nullable();
    $table->decimal('default_target', 8, 4)->nullable(); // 0.95, 1.00
    $table->string('target_type')->default('percentage'); // percentage, count, hours, ratio
    $table->string('frequency'); // daily, weekly, fortnightly, monthly, termly
    $table->string('unit')->nullable(); // %, hours, count
    $table->integer('display_order')->default(0);

    // CONFIGURATION-DRIVEN CORE
    $table->json('config')->nullable();
    /* config structure:
    {
        "thresholds": {
            "on_track": { "min": 1.0, "color": "#22c55e", "label": "ON TRACK" },
            "needs_attention": { "min": 0.90, "max": 0.9999, "color": "#eab308", "label": "NEEDS ATTENTION" },
            "off_track": { "max": 0.8999, "color": "#dc2626", "label": "OFF TRACK" }
        },
        "escalation": {
            "enabled": true,
            "delay_hours": 72,
            "escalate_to_role": "head_of_school"
        },
        "automation": {
            "auto_calculate": true,
            "calculation_trigger": "on_data_change",
            "observation_trigger": { "enabled": true, "threshold": 0.95, "operator": "<" }
        },
        "evidence": {
            "required": true,
            "min_photos": 1,
            "max_photos": 5,
            "allowed_types": ["image", "pdf", "video"]
        },
        "verification": {
            "required": true,
            "verifier_role": "head_of_department",
            "auto_approve_after_hours": null
        }
    }
    */

    $table->enum('status', ['active', 'inactive'])->default('active');
    $table->timestamps();
    $table->softDeletes();
});

// 2025_01_10_000102_create_kpi_periodic_data_table.php
Schema::create('kpi_periodic_data', function (Blueprint $table) {
    $table->id();
    $table->foreignId('kpi_id')->constrained()->cascadeOnDelete();
    $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
    $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();

    $table->decimal('target_value', 12, 4)->nullable();
    $table->decimal('actual_value', 12, 4)->nullable();
    $table->decimal('achievement_rate', 8, 4)->nullable(); // 0.9887
    $table->string('status')->nullable(); // ON_TRACK, NEEDS_ATTENTION, OFF_TRACK
    $table->date('period_start')->nullable();
    $table->date('period_end')->nullable();
    $table->json('metadata')->nullable(); // breakdown data, student counts, etc.
    $table->timestamps();

    $table->index(['kpi_id', 'academic_session_id', 'term_id']);
});
```

### 3.4 Scheme of Work & Delivery (Core Module)

```php
// 2025_01_10_000200_create_schemes_of_work_table.php
Schema::create('schemes_of_work', function (Blueprint $table) {
    $table->id();
    $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
    $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
    $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
    $table->foreignId('term_id')->constrained()->cascadeOnDelete();
    $table->foreignId('uploaded_by')->constrained('users');
    $table->string('file_path')->nullable(); // PDF upload
    $table->enum('status', ['draft', 'approved', 'active', 'archived'])->default('draft');
    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('approved_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
});

// 2025_01_10_000201_create_topics_table.php
Schema::create('topics', function (Blueprint $table) {
    $table->id();
    $table->foreignId('scheme_of_work_id')->constrained()->cascadeOnDelete();
    $table->integer('week_number');
    $table->string('title');
    $table->text('description')->nullable();
    $table->json('learning_objectives')->nullable();
    $table->integer('expected_duration_minutes')->default(240); // 4 periods
    $table->integer('display_order')->default(0);
    $table->enum('status', ['planned', 'in_progress', 'covered', 'skipped'])->default('planned');
    $table->timestamps();
});

// 2025_01_10_000202_create_lesson_plans_table.php
Schema::create('lesson_plans', function (Blueprint $table) {
    $table->id();
    $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
    $table->foreignId('teacher_id')->constrained('users');
    $table->foreignId('school_class_id')->constrained();
    $table->foreignId('subject_id')->constrained();
    $table->json('objectives')->nullable();
    $table->json('activities')->nullable();
    $table->json('assessment')->nullable();
    $table->json('resources')->nullable();
    $table->string('file_path')->nullable();
    $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'needs_revision'])->default('draft');
    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('approved_at')->nullable();
    $table->text('rejection_reason')->nullable();
    $table->timestamps();
    $table->softDeletes();
});

// 2025_01_10_000203_create_topic_delivery_logs_table.php
Schema::create('topic_delivery_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
    $table->foreignId('teacher_id')->constrained('users');
    $table->foreignId('school_class_id')->constrained();
    $table->foreignId('subject_id')->constrained();
    $table->foreignId('lesson_plan_id')->nullable()->constrained()->nullOnDelete();
    $table->date('delivery_date');
    $table->text('notes')->nullable();
    $table->json('evidence_metadata')->nullable();
    $table->enum('status', ['draft', 'submitted', 'verified', 'rejected'])->default('draft');
    $table->timestamp('submitted_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
});

// 2025_01_10_000204_create_attachments_table.php (polymorphic)
Schema::create('attachments', function (Blueprint $table) {
    $table->id();
    $table->morphs('attachable'); // topic_delivery_logs, lesson_plans, observations, etc.
    $table->foreignId('uploaded_by')->constrained('users');
    $table->string('file_name');
    $table->string('file_path');
    $table->string('file_type'); // image, pdf, video
    $table->integer('file_size');
    $table->string('disk')->default('s3'); // or local
    $table->json('metadata')->nullable(); // dimensions, duration, thumbnail
    $table->timestamps();
});
```

### 3.5 Verification & Escalation Engine

```php
// 2025_01_10_000300_create_verifications_table.php
Schema::create('verifications', function (Blueprint $table) {
    $table->id();
    $table->morphs('verifiable'); // topic_delivery_logs, lesson_plans, etc.
    $table->foreignId('requested_by')->constrained('users');
    $table->foreignId('verifier_id')->constrained('users');
    $table->enum('status', ['pending', 'approved', 'rejected', 'escalated', 'overridden'])->default('pending');
    $table->text('comments')->nullable();
    $table->timestamp('due_date')->nullable();
    $table->timestamp('verified_at')->nullable();
    $table->foreignId('escalated_to')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('escalated_at')->nullable();
    $table->text('escalation_reason')->nullable();
    $table->json('config_snapshot')->nullable(); // snapshot of escalation rules at creation
    $table->timestamps();

    $table->index(['status', 'due_date']);
});

// 2025_01_10_000301_create_escalation_rules_table.php
Schema::create('escalation_rules', function (Blueprint $table) {
    $table->id();
    $table->string('name'); // "Topic Delivery Verification"
    $table->string('applies_to_type'); // topic_delivery_logs, lesson_plans, homework_assignments
    $table->string('trigger_event'); // status_changed, overdue, threshold_breach
    $table->integer('delay_hours')->default(72);
    $table->foreignId('escalate_from_role_id')->nullable()->constrained('roles')->nullOnDelete();
    $table->foreignId('escalate_to_role_id')->constrained('roles');
    $table->json('notification_channels')->nullable(); // ["in_app", "email", "whatsapp"]
    $table->foreignId('notification_template_id')->nullable()->constrained()->nullOnDelete();
    $table->boolean('is_active')->default(true);
    $table->json('config')->nullable(); // conditions, exceptions, business_hours_only
    $table->integer('display_order')->default(0);
    $table->timestamps();
});
```

### 3.6 Academic KPIs (Representative Tables)

```php
// 2025_01_10_000400_create_attendance_records_table.php
Schema::create('attendance_records', function (Blueprint $table) {
    $table->id();
    $table->foreignId('school_class_id')->constrained();
    $table->date('date');
    $table->foreignId('recorded_by')->constrained('users');
    $table->enum('status', ['draft', 'confirmed'])->default('draft');
    $table->timestamps();
    $table->unique(['school_class_id', 'date']);
});

Schema::create('attendance_details', function (Blueprint $table) {
    $table->id();
    $table->foreignId('attendance_record_id')->constrained()->cascadeOnDelete();
    $table->foreignId('student_id')->constrained('users');
    $table->enum('status', ['present', 'absent', 'late', 'excused'])->default('present');
    $table->text('remarks')->nullable();
    $table->timestamps();
});

// 2025_01_10_000401_create_homework_assignments_table.php
Schema::create('homework_assignments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('school_class_id')->constrained();
    $table->foreignId('subject_id')->constrained();
    $table->foreignId('teacher_id')->constrained('users');
    $table->string('title');
    $table->text('description')->nullable();
    $table->date('due_date');
    $table->json('config')->nullable(); // grading_rubric, allowed_formats, max_file_size
    $table->enum('status', ['draft', 'published', 'closed'])->default('draft');
    $table->timestamps();
    $table->softDeletes();
});

Schema::create('homework_submissions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('homework_assignment_id')->constrained()->cascadeOnDelete();
    $table->foreignId('student_id')->constrained('users');
    $table->timestamp('submitted_at')->nullable();
    $table->text('content')->nullable();
    $table->decimal('score', 5, 2)->nullable();
    $table->text('feedback')->nullable();
    $table->enum('status', ['pending', 'submitted', 'graded', 'late'])->default('pending');
    $table->timestamps();
});

// 2025_01_10_000402_create_observations_table.php
Schema::create('observations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('teacher_id')->constrained('users');
    $table->foreignId('observer_id')->constrained('users');
    $table->foreignId('school_class_id')->constrained();
    $table->foreignId('subject_id')->constrained();
    $table->date('observation_date');
    $table->json('rubric_scores')->nullable(); // {"standard_1": 3, "standard_2": 4, ...}
    $table->decimal('overall_score', 5, 2)->nullable();
    $table->text('strengths')->nullable();
    $table->text('areas_for_improvement')->nullable();
    $table->text('action_plan')->nullable();
    $table->enum('status', ['scheduled', 'completed', 'reviewed', 'follow_up_required'])->default('scheduled');
    $table->timestamps();
    $table->softDeletes();
});

// 2025_01_10_000403_create_at_risk_learners_table.php
Schema::create('at_risk_learners', function (Blueprint $table) {
    $table->id();
    $table->foreignId('student_id')->constrained('users');
    $table->foreignId('school_class_id')->constrained();
    $table->foreignId('identified_by')->constrained('users');
    $table->date('identification_date');
    $table->json('risk_factors')->nullable(); // ["below_50_percent", "attendance_concern"]
    $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('medium');
    $table->enum('status', ['active', 'resolved', 'monitoring'])->default('active');
    $table->timestamps();
    $table->softDeletes();
});

Schema::create('intervention_plans', function (Blueprint $table) {
    $table->id();
    $table->foreignId('at_risk_learner_id')->constrained()->cascadeOnDelete();
    $table->foreignId('coordinator_id')->constrained('users');
    $table->string('plan_type'); // Tier 2, Tier 3
    $table->text('objectives')->nullable();
    $table->text('strategies')->nullable();
    $table->date('start_date');
    $table->date('review_date');
    $table->enum('status', ['draft', 'active', 'completed', 'discontinued'])->default('draft');
    $table->timestamps();
});
```

### 3.7 Christocentric & Digital Innovation (Representative)

```php
// 2025_01_10_000500_create_chapel_sessions_table.php
Schema::create('chapel_sessions', function (Blueprint $table) {
    $table->id();
    $table->date('date');
    $table->string('theme')->nullable();
    $table->foreignId('chaplain_id')->constrained('users');
    $table->json('config')->nullable(); // scripture_reading, worship_songs
    $table->enum('status', ['scheduled', 'held', 'cancelled'])->default('scheduled');
    $table->timestamps();
});

Schema::create('chapel_attendances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('chapel_session_id')->constrained()->cascadeOnDelete();
    $table->foreignId('student_id')->constrained('users');
    $table->enum('status', ['present', 'absent'])->default('present');
    $table->enum('participation_level', ['passive', 'active', 'leading'])->default('active');
    $table->timestamps();
    $table->unique(['chapel_session_id', 'student_id']);
});

// 2025_01_10_000501_create_character_domains_table.php (Configuration)
Schema::create('character_domains', function (Blueprint $table) {
    $table->id();
    $table->foreignId('school_id')->constrained()->cascadeOnDelete();
    $table->string('name'); // Faith, Integrity, Excellence, etc.
    $table->string('code')->nullable();
    $table->text('description')->nullable();
    $table->json('rubric')->nullable(); // [{"level":1,"label":"Beginning","description":"..."},...]
    $table->integer('display_order')->default(0);
    $table->enum('status', ['active', 'inactive'])->default('active');
    $table->timestamps();
});

// 2025_01_10_000502_create_lms_usage_logs_table.php
Schema::create('lms_usage_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->string('user_type'); // student, staff
    $table->date('login_date');
    $table->string('activity_type')->nullable(); // assignment_submission, resource_access, forum_post
    $table->integer('duration_minutes')->default(0);
    $table->json('metadata')->nullable();
    $table->timestamps();
    $table->index(['user_id', 'login_date']);
});
```

### 3.8 System & Configuration

```php
// 2025_01_10_000600_create_settings_table.php
Schema::create('settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->text('value')->nullable();
    $table->string('group')->default('general'); // general, academic, notification, security
    $table->string('type')->default('string'); // string, integer, boolean, json, file
    $table->text('description')->nullable();
    $table->boolean('is_public')->default(false);
    $table->timestamps();
});

// 2025_01_10_000601_create_notification_templates_table.php
Schema::create('notification_templates', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->string('channel'); // in_app, email, sms, whatsapp
    $table->string('subject')->nullable();
    $table->text('body');
    $table->json('variables')->nullable(); // ["teacher_name", "topic_title", "due_date"]
    $table->json('config')->nullable(); // delay, conditions, attachments
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

// 2025_01_10_000602_create_audit_logs_table.php
Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->string('action'); // created, updated, deleted, verified, approved
    $table->morphs('auditable');
    $table->json('old_values')->nullable();
    $table->json('new_values')->nullable();
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();
    $table->timestamps();
    $table->index(['auditable_type', 'auditable_id']);
    $table->index('created_at');
});
```


---

## 4. Eloquent Models

### 4.1 Core Models

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class School extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'slug', 'address', 'logo', 'settings', 'status'];

    protected $casts = ['settings' => 'array'];

    public function academicSessions(): HasMany
    {
        return $this->hasMany(AcademicSession::class);
    }

    public function pillars(): HasMany
    {
        return $this->hasMany(Pillar::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }
}

class AcademicSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id', 'name', 'start_date', 'end_date', 'is_current', 'status'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class);
    }

    public function kpiData(): HasMany
    {
        return $this->hasMany(KpiPeriodicData::class);
    }
}

class Term extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_session_id', 'name', 'start_date', 'end_date', 'status'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }
}

class SchoolClass extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'school_classes';

    protected $fillable = [
        'school_id', 'name', 'level', 'display_order', 'status'
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'class_subject_teacher')
            ->withPivot('teacher_id', 'academic_session_id', 'status')
            ->withTimestamps();
    }

    public function teachers()
    {
        return $this->belongsToMany(User::class, 'class_subject_teacher')
            ->withPivot('subject_id', 'academic_session_id', 'status')
            ->withTimestamps();
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}

class Subject extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['school_id', 'name', 'code', 'status'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function classes()
    {
        return $this->belongsToMany(SchoolClass::class, 'class_subject_teacher')
            ->withPivot('teacher_id', 'academic_session_id', 'status')
            ->withTimestamps();
    }
}
```

### 4.2 Strategic Framework Models

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pillar extends Model
{
    protected $fillable = [
        'school_id', 'name', 'code', 'description', 'display_order', 'config', 'status'
    ];

    protected $casts = ['config' => 'array'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function kpis(): HasMany
    {
        return $this->hasMany(Kpi::class)->orderBy('display_order');
    }

    public function activeKpis(): HasMany
    {
        return $this->kpis()->where('status', 'active');
    }

    public function calculateAchievement(int $academicSessionId, ?int $termId = null): array
    {
        $query = KpiPeriodicData::whereIn('kpi_id', $this->kpis()->pluck('id'))
            ->where('academic_session_id', $academicSessionId);

        if ($termId) {
            $query->where('term_id', $termId);
        }

        $data = $query->get();

        $totalKpis = $this->activeKpis()->count();
        $onTrack = $data->where('status', 'ON_TRACK')->count();
        $needsAttention = $data->where('status', 'NEEDS_ATTENTION')->count();
        $offTrack = $data->where('status', 'OFF_TRACK')->count();
        $avgAchievement = $data->avg('achievement_rate') ?? 0;

        return [
            'total_kpis' => $totalKpis,
            'on_track' => $onTrack,
            'needs_attention' => $needsAttention,
            'off_track' => $offTrack,
            'avg_achievement' => round($avgAchievement, 4),
            'overall_status' => $this->determineOverallStatus($avgAchievement),
        ];
    }

    protected function determineOverallStatus(float $rate): string
    {
        $thresholds = $this->config['thresholds'] ?? [
            'on_track' => ['min' => 1.0],
            'needs_attention' => ['min' => 0.90, 'max' => 0.9999],
        ];

        if ($rate >= ($thresholds['on_track']['min'] ?? 1.0)) {
            return 'ON_TRACK';
        }
        if ($rate >= ($thresholds['needs_attention']['min'] ?? 0.90)) {
            return 'NEEDS_ATTENTION';
        }
        return 'OFF_TRACK';
    }
}

class Kpi extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pillar_id', 'code', 'name', 'description', 'measurement_methodology',
        'calculation_formula', 'data_collection_instrument', 'default_target',
        'target_type', 'frequency', 'unit', 'display_order', 'config', 'status'
    ];

    protected $casts = [
        'default_target' => 'decimal:4',
        'config' => 'array',
    ];

    public function pillar(): BelongsTo
    {
        return $this->belongsTo(Pillar::class);
    }

    public function periodicData(): HasMany
    {
        return $this->hasMany(KpiPeriodicData::class);
    }

    public function evaluateStatus(float $achievementRate): string
    {
        $thresholds = $this->config['thresholds'] ?? $this->getDefaultThresholds();

        if ($achievementRate >= ($thresholds['on_track']['min'] ?? 1.0)) {
            return 'ON_TRACK';
        }
        if ($achievementRate >= ($thresholds['needs_attention']['min'] ?? 0.90)) {
            return 'NEEDS_ATTENTION';
        }
        return 'OFF_TRACK';
    }

    protected function getDefaultThresholds(): array
    {
        return [
            'on_track' => ['min' => 1.0, 'color' => '#22c55e', 'label' => 'ON TRACK'],
            'needs_attention' => ['min' => 0.90, 'max' => 0.9999, 'color' => '#eab308', 'label' => 'NEEDS ATTENTION'],
            'off_track' => ['max' => 0.8999, 'color' => '#dc2626', 'label' => 'OFF TRACK'],
        ];
    }

    public function shouldTriggerObservation(float $actualValue): bool
    {
        $trigger = $this->config['automation']['observation_trigger'] ?? null;
        if (!($trigger['enabled'] ?? false)) {
            return false;
        }

        $threshold = $trigger['threshold'] ?? 0.95;
        $operator = $trigger['operator'] ?? '<';

        return match ($operator) {
            '<' => $actualValue < $threshold,
            '<=' => $actualValue <= $threshold,
            '>' => $actualValue > $threshold,
            '>=' => $actualValue >= $threshold,
            default => false,
        };
    }
}

class KpiPeriodicData extends Model
{
    protected $table = 'kpi_periodic_data';

    protected $fillable = [
        'kpi_id', 'academic_session_id', 'term_id', 'school_class_id',
        'subject_id', 'teacher_id', 'target_value', 'actual_value',
        'achievement_rate', 'status', 'period_start', 'period_end', 'metadata'
    ];

    protected $casts = [
        'target_value' => 'decimal:4',
        'actual_value' => 'decimal:4',
        'achievement_rate' => 'decimal:4',
        'period_start' => 'date',
        'period_end' => 'date',
        'metadata' => 'array',
    ];

    public function kpi(): BelongsTo
    {
        return $this->belongsTo(Kpi::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    protected static function booted(): void
    {
        static::saving(function ($model) {
            if ($model->target_value && $model->actual_value !== null) {
                $model->achievement_rate = $model->actual_value / $model->target_value;
                $model->status = $model->kpi->evaluateStatus($model->achievement_rate);
            }
        });
    }
}
```

### 4.3 Scheme Delivery Models

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class SchemeOfWork extends Model
{
    use SoftDeletes;

    protected $table = 'schemes_of_work';

    protected $fillable = [
        'subject_id', 'school_class_id', 'academic_session_id', 'term_id',
        'uploaded_by', 'file_path', 'status', 'approved_by', 'approved_at'
    ];

    protected $casts = ['approved_at' => 'datetime'];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class)->orderBy('week_number')->orderBy('display_order');
    }

    public function uncoveredTopics(): HasMany
    {
        return $this->topics()->whereIn('status', ['planned', 'in_progress']);
    }

    public function coverageRate(): float
    {
        $total = $this->topics()->count();
        if ($total === 0) return 0;

        $covered = $this->topics()->where('status', 'covered')->count();
        return round($covered / $total, 4);
    }
}

class Topic extends Model
{
    protected $fillable = [
        'scheme_of_work_id', 'week_number', 'title', 'description',
        'learning_objectives', 'expected_duration_minutes', 'display_order', 'status'
    ];

    protected $casts = ['learning_objectives' => 'array'];

    public function schemeOfWork(): BelongsTo
    {
        return $this->belongsTo(SchemeOfWork::class);
    }

    public function deliveryLogs(): HasMany
    {
        return $this->hasMany(TopicDeliveryLog::class);
    }

    public function latestDeliveryLog(): ?TopicDeliveryLog
    {
        return $this->deliveryLogs()->latest()->first();
    }
}

class LessonPlan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'topic_id', 'teacher_id', 'school_class_id', 'subject_id',
        'objectives', 'activities', 'assessment', 'resources', 'file_path',
        'status', 'approved_by', 'approved_at', 'rejection_reason'
    ];

    protected $casts = [
        'objectives' => 'array',
        'activities' => 'array',
        'assessment' => 'array',
        'resources' => 'array',
        'approved_at' => 'datetime',
    ];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function verification(): MorphOne
    {
        return $this->morphOne(Verification::class, 'verifiable');
    }
}

class TopicDeliveryLog extends Model
{
    use SoftDeletes;

    protected $table = 'topic_delivery_logs';

    protected $fillable = [
        'topic_id', 'teacher_id', 'school_class_id', 'subject_id',
        'lesson_plan_id', 'delivery_date', 'notes', 'evidence_metadata', 'status', 'submitted_at'
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'evidence_metadata' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function lessonPlan(): BelongsTo
    {
        return $this->belongsTo(LessonPlan::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function verification(): MorphOne
    {
        return $this->morphOne(Verification::class, 'verifiable');
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    protected static function booted(): void
    {
        static::created(function ($log) {
            $kpi = Kpi::where('code', 'AE-01')->first();
            if ($kpi && ($kpi->config['verification']['required'] ?? false)) {
                $verifierRole = $kpi->config['verification']['verifier_role'] ?? 'head_of_department';

                $verifier = User::role($verifierRole)
                    ->whereHas('subjects', fn($q) => $q->where('subjects.id', $log->subject_id))
                    ->first();

                if ($verifier) {
                    $delay = EscalationRule::for(self::class)->first()?->delay_hours ?? 72;
                    Verification::create([
                        'verifiable_type' => self::class,
                        'verifiable_id' => $log->id,
                        'requested_by' => $log->teacher_id,
                        'verifier_id' => $verifier->id,
                        'due_date' => now()->addHours($delay),
                        'config_snapshot' => $kpi->config['escalation'] ?? null,
                    ]);
                }
            }
        });
    }
}

class Attachment extends Model
{
    protected $fillable = [
        'attachable_type', 'attachable_id', 'uploaded_by',
        'file_name', 'file_path', 'file_type', 'file_size', 'disk', 'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'file_size' => 'integer',
    ];

    public function attachable()
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return \Storage::disk($this->disk)->url($this->file_path);
    }
}
```

### 4.4 Verification & Escalation Models

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Verification extends Model
{
    protected $fillable = [
        'verifiable_type', 'verifiable_id', 'requested_by', 'verifier_id',
        'status', 'comments', 'due_date', 'verified_at',
        'escalated_to', 'escalated_at', 'escalation_reason', 'config_snapshot'
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'verified_at' => 'datetime',
        'escalated_at' => 'datetime',
        'config_snapshot' => 'array',
    ];

    public function verifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifier_id');
    }

    public function escalatedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to');
    }

    public function isOverdue(): bool
    {
        return $this->status === 'pending' 
            && $this->due_date 
            && $this->due_date->isPast();
    }

    public function approve(string $comments = null): void
    {
        $this->update([
            'status' => 'approved',
            'comments' => $comments,
            'verified_at' => now(),
        ]);

        $this->verifiable->update(['status' => 'verified']);
        event(new \App\Events\VerificationApproved($this));
    }

    public function reject(string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'comments' => $reason,
            'verified_at' => now(),
        ]);

        $this->verifiable->update(['status' => 'rejected']);
    }

    public function escalate(string $reason = null): void
    {
        $rule = EscalationRule::for($this->verifiable_type)->first();
        $escalationRole = $rule?->escalateToRole;

        if ($escalationRole) {
            $nextVerifier = User::role($escalationRole->name)->first();

            $this->update([
                'status' => 'escalated',
                'escalated_to' => $nextVerifier?->id,
                'escalated_at' => now(),
                'escalation_reason' => $reason ?? 'Overdue verification',
            ]);
        }
    }
}

class EscalationRule extends Model
{
    protected $fillable = [
        'name', 'applies_to_type', 'trigger_event', 'delay_hours',
        'escalate_from_role_id', 'escalate_to_role_id',
        'notification_channels', 'notification_template_id',
        'is_active', 'config', 'display_order'
    ];

    protected $casts = [
        'delay_hours' => 'integer',
        'notification_channels' => 'array',
        'config' => 'array',
        'is_active' => 'boolean',
    ];

    public function escalateFromRole(): BelongsTo
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class, 'escalate_from_role_id');
    }

    public function escalateToRole(): BelongsTo
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class, 'escalate_to_role_id');
    }

    public function notificationTemplate(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class);
    }

    public function scopeFor($query, string $modelType)
    {
        return $query->where('applies_to_type', $modelType)->where('is_active', true);
    }
}
```

### 4.5 User Model (Extended)

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'avatar',
        'school_id', 'status', 'email_verified_at'
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subject_teacher')
            ->withPivot('school_class_id', 'academic_session_id', 'status')
            ->withTimestamps();
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'class_subject_teacher')
            ->withPivot('subject_id', 'academic_session_id', 'status')
            ->withTimestamps();
    }

    public function deliveryLogs(): HasMany
    {
        return $this->hasMany(TopicDeliveryLog::class, 'teacher_id');
    }

    public function pendingVerifications(): HasMany
    {
        return $this->hasMany(Verification::class, 'verifier_id')
            ->where('status', 'pending');
    }

    public function isTeacher(): bool
    {
        return $this->hasRole('teacher');
    }

    public function isHoD(): bool
    {
        return $this->hasRole('head_of_department');
    }

    public function isHoS(): bool
    {
        return $this->hasRole('head_of_school');
    }

    public function isBoard(): bool
    {
        return $this->hasRole('board');
    }
}
```


---

## 5. Service Layer (Configuration-Driven Business Logic)

### 5.1 KpiCalculationService

```php
<?php

namespace App\Services;

use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\AcademicSession;
use App\Models\Term;

class KpiCalculationService
{
    /**
     * Calculate and store KPI achievement based on configured formula
     */
    public function calculate(string $kpiCode, int $academicSessionId, ?int $termId = null, array $filters = []): KpiPeriodicData
    {
        $kpi = Kpi::where('code', $kpiCode)->firstOrFail();

        $formula = $kpi->calculation_formula;
        $actualValue = $this->resolveFormula($formula, $kpi, $academicSessionId, $termId, $filters);

        $targetValue = $filters['target_value'] ?? $kpi->default_target;
        $achievementRate = $targetValue > 0 ? ($actualValue / $targetValue) : 0;
        $status = $kpi->evaluateStatus($achievementRate);

        $data = KpiPeriodicData::updateOrCreate(
            [
                'kpi_id' => $kpi->id,
                'academic_session_id' => $academicSessionId,
                'term_id' => $termId,
                'school_class_id' => $filters['school_class_id'] ?? null,
                'subject_id' => $filters['subject_id'] ?? null,
                'teacher_id' => $filters['teacher_id'] ?? null,
            ],
            [
                'target_value' => $targetValue,
                'actual_value' => $actualValue,
                'achievement_rate' => $achievementRate,
                'status' => $status,
                'period_start' => $filters['period_start'] ?? now()->startOfWeek(),
                'period_end' => $filters['period_end'] ?? now()->endOfWeek(),
                'metadata' => $filters['metadata'] ?? null,
            ]
        );

        // Trigger observation if configured
        if ($kpi->shouldTriggerObservation($actualValue)) {
            event(new \App\Events\KpiThresholdBreached($kpi, $data));
        }

        return $data;
    }

    /**
     * Resolve formula expression against actual database data
     */
    protected function resolveFormula(?string $formula, Kpi $kpi, int $sessionId, ?int $termId, array $filters): float
    {
        if (!$formula) {
            return $filters['actual_value'] ?? 0;
        }

        return match ($kpi->code) {
            'AE-01' => $this->calculateCurriculumCoverage($filters),
            'AE-02' => $this->calculateExamPassRate($filters),
            'AE-03' => $this->calculateHomeworkCompletion($filters),
            'AE-04' => $this->calculateAttendanceRate($filters),
            'AE-05' => $this->calculateLessonPlanSubmission($filters),
            'AE-06' => $this->calculateObservationRate($filters),
            'AE-07' => $this->calculateAtRiskIntervention($filters),
            'AE-08' => $this->calculateReadingProgress($filters),
            default => $filters['actual_value'] ?? 0,
        };
    }

    protected function calculateCurriculumCoverage(array $filters): float
    {
        $scheme = \App\Models\SchemeOfWork::where([
            'subject_id' => $filters['subject_id'],
            'school_class_id' => $filters['school_class_id'],
            'academic_session_id' => $filters['academic_session_id'],
        ])->first();

        return $scheme ? $scheme->coverageRate() : 0;
    }

    protected function calculateExamPassRate(array $filters): float
    {
        $results = \App\Models\ExamResult::where([
            'school_class_id' => $filters['school_class_id'],
            'subject_id' => $filters['subject_id'],
        ])->get();

        $total = $results->count();
        if ($total === 0) return 0;

        $passed = $results->where('score', '>=', 50)->count();
        return round($passed / $total, 4);
    }

    protected function calculateHomeworkCompletion(array $filters): float
    {
        $assignments = \App\Models\HomeworkAssignment::where([
            'school_class_id' => $filters['school_class_id'],
            'subject_id' => $filters['subject_id'],
        ])->pluck('id');

        $total = \App\Models\HomeworkSubmission::whereIn('homework_assignment_id', $assignments)->count();
        $completed = \App\Models\HomeworkSubmission::whereIn('homework_assignment_id', $assignments)
            ->where('status', 'submitted')->count();

        return $total > 0 ? round($completed / $total, 4) : 0;
    }

    protected function calculateAttendanceRate(array $filters): float
    {
        $records = \App\Models\AttendanceRecord::where('school_class_id', $filters['school_class_id'])->pluck('id');
        $total = \App\Models\AttendanceDetail::whereIn('attendance_record_id', $records)->count();
        $present = \App\Models\AttendanceDetail::whereIn('attendance_record_id', $records)
            ->where('status', 'present')->count();

        return $total > 0 ? round($present / $total, 4) : 0;
    }

    protected function calculateLessonPlanSubmission(array $filters): float
    {
        $total = \App\Models\LessonPlan::where([
            'teacher_id' => $filters['teacher_id'],
            'subject_id' => $filters['subject_id'],
        ])->count();

        $approved = \App\Models\LessonPlan::where([
            'teacher_id' => $filters['teacher_id'],
            'subject_id' => $filters['subject_id'],
        ])->where('status', 'approved')->count();

        return $total > 0 ? round($approved / $total, 4) : 0;
    }

    protected function calculateObservationRate(array $filters): float
    {
        $observations = \App\Models\Observation::where('teacher_id', $filters['teacher_id'])->get();
        $total = $observations->count();
        if ($total === 0) return 0;

        $effective = $observations->filter(fn($o) => $o->overall_score >= 3)->count();
        return round($effective / $total, 4);
    }

    protected function calculateAtRiskIntervention(array $filters): float
    {
        $atRisk = \App\Models\AtRiskLearner::where('school_class_id', $filters['school_class_id'])->get();
        $total = $atRisk->count();
        if ($total === 0) return 1; // 100% if no at-risk learners

        $withPlan = $atRisk->filter(fn($a) => $a->interventionPlans()->where('status', 'active')->exists())->count();
        return round($withPlan / $total, 4);
    }

    protected function calculateReadingProgress(array $filters): float
    {
        // Implementation depends on standardized test integration
        return $filters['actual_value'] ?? 0;
    }
}
```

### 5.2 CoverageCalculationService

```php
<?php

namespace App\Services;

use App\Models\SchemeOfWork;
use App\Models\Topic;
use App\Models\KpiPeriodicData;

class CoverageCalculationService
{
    public function recalculateForScheme(SchemeOfWork $scheme): void
    {
        $rate = $scheme->coverageRate();
        $totalTopics = $scheme->topics()->count();
        $coveredTopics = $scheme->topics()->where('status', 'covered')->count();

        $kpi = \App\Models\Kpi::where('code', 'AE-01')->first();
        if (!$kpi) return;

        // Store at scheme level
        KpiPeriodicData::updateOrCreate(
            [
                'kpi_id' => $kpi->id,
                'academic_session_id' => $scheme->academic_session_id,
                'term_id' => $scheme->term_id,
                'school_class_id' => $scheme->school_class_id,
                'subject_id' => $scheme->subject_id,
            ],
            [
                'target_value' => 1.0,
                'actual_value' => $rate,
                'achievement_rate' => $rate,
                'status' => $kpi->evaluateStatus($rate),
                'metadata' => [
                    'total_topics' => $totalTopics,
                    'covered_topics' => $coveredTopics,
                    'scheme_id' => $scheme->id,
                ],
            ]
        );
    }

    public function recalculateForTeacher(int $teacherId, int $sessionId): void
    {
        $schemes = SchemeOfWork::whereHas('topics.deliveryLogs', function ($q) use ($teacherId) {
            $q->where('teacher_id', $teacherId);
        })->where('academic_session_id', $sessionId)->get();

        foreach ($schemes as $scheme) {
            $this->recalculateForScheme($scheme);
        }
    }
}
```

### 5.3 NotificationService

```php
<?php

namespace App\Services;

use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    public function send(string $templateSlug, User $recipient, array $variables = []): void
    {
        $template = NotificationTemplate::where('slug', $templateSlug)
            ->where('is_active', true)
            ->first();

        if (!$template) return;

        $subject = $this->interpolate($template->subject, $variables);
        $body = $this->interpolate($template->body, $variables);

        match ($template->channel) {
            'in_app' => $this->sendInApp($recipient, $subject, $body),
            'email' => $this->sendEmail($recipient, $subject, $body),
            'sms' => $this->sendSms($recipient, $body),
            'whatsapp' => $this->sendWhatsApp($recipient, $body),
            default => null,
        };
    }

    protected function interpolate(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace("{{{$key}}}", $value, $text);
        }
        return $text;
    }

    protected function sendInApp(User $user, string $subject, string $body): void
    {
        $user->notifications()->create([
            'type' => 'in_app',
            'subject' => $subject,
            'body' => $body,
            'read_at' => null,
        ]);
    }

    protected function sendEmail(User $user, string $subject, string $body): void
    {
        \Mail::to($user->email)->send(new \App\Mail\GenericNotification($subject, $body));
    }

    protected function sendSms(User $user, string $body): void
    {
        // Integrate with Termii, Twilio, or local SMS gateway
    }

    protected function sendWhatsApp(User $user, string $body): void
    {
        // Integrate with WhatsApp Business API
    }
}
```

---

## 6. Event & Listener System

### 6.1 Events

```php
<?php

namespace App\Events;

use App\Models\Verification;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VerificationApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(public Verification $verification) {}
}

class VerificationOverdue
{
    use Dispatchable, SerializesModels;

    public function __construct(public Verification $verification) {}
}

class KpiThresholdBreached
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Kpi $kpi,
        public KpiPeriodicData $data
    ) {}
}

class TopicDelivered
{
    use Dispatchable, SerializesModels;

    public function __construct(public \App\Models\TopicDeliveryLog $log) {}
}
```

### 6.2 Listeners

```php
<?php

namespace App\Listeners;

use App\Events\VerificationApproved;
use App\Events\VerificationOverdue;
use App\Events\KpiThresholdBreached;
use App\Services\KpiCalculationService;
use App\Services\NotificationService;

class RecalculateKpiOnVerification
{
    public function handle(VerificationApproved $event): void
    {
        $verifiable = $event->verification->verifiable;

        if ($verifiable instanceof \App\Models\TopicDeliveryLog) {
            $service = new KpiCalculationService();
            $service->calculate('AE-01', 
                $verifiable->topic->schemeOfWork->academic_session_id,
                $verifiable->topic->schemeOfWork->term_id,
                [
                    'school_class_id' => $verifiable->school_class_id,
                    'subject_id' => $verifiable->subject_id,
                ]
            );
        }
    }
}

class EscalateOverdueVerification
{
    public function handle(VerificationOverdue $event): void
    {
        $verification = $event->verification;
        $verification->escalate('Auto-escalated: verification overdue');

        $notificationService = new NotificationService();
        $notificationService->send('verification_escalated', 
            $verification->escalatedToUser,
            [
                'verifier_name' => $verification->verifier->name,
                'teacher_name' => $verification->requester->name,
                'topic_title' => $verification->verifiable->topic->title ?? 'N/A',
            ]
        );
    }
}

class TriggerObservationOnKpiBreach
{
    public function handle(KpiThresholdBreached $event): void
    {
        $kpi = $event->kpi;
        $data = $event->data;

        if ($kpi->code === 'AE-01' && $data->teacher_id) {
            // Auto-schedule classroom observation
            \App\Models\Observation::create([
                'teacher_id' => $data->teacher_id,
                'observer_id' => $this->getDefaultObserver(),
                'school_class_id' => $data->school_class_id,
                'subject_id' => $data->subject_id,
                'observation_date' => now()->addDays(3),
                'status' => 'scheduled',
            ]);

            $notificationService = new NotificationService();
            $notificationService->send('observation_scheduled',
                $data->teacher,
                [
                    'teacher_name' => $data->teacher->name,
                    'kpi_name' => $kpi->name,
                    'achievement_rate' => $data->achievement_rate,
                ]
            );
        }
    }

    protected function getDefaultObserver(): int
    {
        return \App\Models\User::role('head_of_school')->first()?->id ?? 1;
    }
}
```

### 6.3 EventServiceProvider Registration

```php
<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        \App\Events\VerificationApproved::class => [
            \App\Listeners\RecalculateKpiOnVerification::class,
        ],
        \App\Events\VerificationOverdue::class => [
            \App\Listeners\EscalateOverdueVerification::class,
        ],
        \App\Events\KpiThresholdBreached::class => [
            \App\Listeners\TriggerObservationOnKpiBreach::class,
        ],
        \App\Events\TopicDelivered::class => [
            \App\Listeners\UpdateTopicStatus::class,
            \App\Listeners\NotifyHoDOfDelivery::class,
        ],
    ];
}
```

---

## 7. Authorization (Policies)

### 7.1 TopicDeliveryLogPolicy

```php
<?php

namespace App\Policies;

use App\Models\TopicDeliveryLog;
use App\Models\User;

class TopicDeliveryLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['teacher', 'head_of_department', 'head_of_school', 'board']);
    }

    public function view(User $user, TopicDeliveryLog $log): bool
    {
        return $user->id === $log->teacher_id
            || $user->isHoD()
            || $user->isHoS()
            || $user->isBoard();
    }

    public function create(User $user): bool
    {
        return $user->isTeacher();
    }

    public function update(User $user, TopicDeliveryLog $log): bool
    {
        return $user->id === $log->teacher_id && $log->status === 'draft';
    }

    public function delete(User $user, TopicDeliveryLog $log): bool
    {
        return $user->id === $log->teacher_id && $log->status === 'draft';
    }

    public function verify(User $user, TopicDeliveryLog $log): bool
    {
        if (!$user->isHoD()) return false;

        // HoD can only verify for subjects they oversee
        return $user->subjects()->where('subjects.id', $log->subject_id)->exists();
    }
}
```

### 7.2 KpiPolicy

```php
<?php

namespace App\Policies;

use App\Models\Kpi;
use App\Models\User;

class KpiPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['head_of_school', 'board', 'admin']);
    }

    public function view(User $user, Kpi $kpi): bool
    {
        return true; // All authenticated users can view KPI definitions
    }

    public function create(User $user): bool
    {
        return $user->isHoS() || $user->hasRole('admin');
    }

    public function update(User $user, Kpi $kpi): bool
    {
        return $user->isHoS() || $user->hasRole('admin');
    }

    public function delete(User $user, Kpi $kpi): bool
    {
        return $user->hasRole('admin');
    }

    public function configure(User $user, Kpi $kpi): bool
    {
        // Only HoS and Board can change thresholds and rules
        return $user->isHoS() || $user->isBoard();
    }
}
```

### 7.3 AuthServiceProvider Registration

```php
<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        \App\Models\TopicDeliveryLog::class => \App\Policies\TopicDeliveryLogPolicy::class,
        \App\Models\Kpi::class => \App\Policies\KpiPolicy::class,
        \App\Models\LessonPlan::class => \App\Policies\LessonPlanPolicy::class,
        \App\Models\SchemeOfWork::class => \App\Policies\SchemeOfWorkPolicy::class,
        \App\Models\Observation::class => \App\Policies\ObservationPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Define a Gate for Board-level read-only access
        \Gate::define('view-board-dashboard', function (User $user) {
            return $user->isBoard() || $user->isHoS() || $user->hasRole('admin');
        });

        // Define a Gate for configuration management
        \Gate::define('manage-configuration', function (User $user) {
            return $user->isHoS() || $user->isBoard();
        });
    }
}
```

---

## 8. Scheduled Commands

### 8.1 EscalationCheckCommand

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Verification;
use App\Events\VerificationOverdue;

class EscalationCheckCommand extends Command
{
    protected $signature = 'wisca:check-escalations';
    protected $description = 'Check for overdue verifications and trigger escalations';

    public function handle(): int
    {
        $overdue = Verification::where('status', 'pending')
            ->where('due_date', '<', now())
            ->whereNull('escalated_at')
            ->get();

        foreach ($overdue as $verification) {
            event(new VerificationOverdue($verification));
            $this->info("Escalated verification ID: {$verification->id}");
        }

        $this->info("Processed {$overdue->count()} overdue verifications.");
        return self::SUCCESS;
    }
}
```

### 8.2 KpiRecalculationCommand

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Kpi;
use App\Models\AcademicSession;
use App\Services\KpiCalculationService;

class KpiRecalculationCommand extends Command
{
    protected $signature = 'wisca:recalculate-kpis {--session=} {--kpi=}';
    protected $description = 'Recalculate all KPIs for the current or specified session';

    public function handle(KpiCalculationService $service): int
    {
        $sessionId = $this->option('session') 
            ?? AcademicSession::where('is_current', true)->first()?->id;

        if (!$sessionId) {
            $this->error('No active academic session found.');
            return self::FAILURE;
        }

        $query = Kpi::where('status', 'active');
        if ($this->option('kpi')) {
            $query->where('code', $this->option('kpi'));
        }

        $kpis = $query->get();

        foreach ($kpis as $kpi) {
            $service->calculate($kpi->code, $sessionId);
            $this->info("Recalculated: {$kpi->code} - {$kpi->name}");
        }

        $this->info("Recalculated {$kpis->count()} KPIs.");
        return self::SUCCESS;
    }
}
```

### 8.3 Schedule Registration (routes/console.php or Kernel)

```php
<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('wisca:check-escalations')->everyThirtyMinutes();
Schedule::command('wisca:recalculate-kpis')->dailyAt('02:00');
Schedule::command('wisca:recalculate-kpis --kpi=AE-01')->weeklyOn(1, '06:00'); // Monday 6 AM
```

---

## 9. API Resources (DTOs for Frontend)

### 9.1 DashboardResource

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'pillar' => [
                'name' => $this['name'],
                'code' => $this['code'],
                'avg_achievement' => $this['avg_achievement'],
                'overall_status' => $this['overall_status'],
                'health_color' => $this['health_color'],
            ],
            'kpis' => KpiResource::collection($this['kpis']),
            'summary' => [
                'total' => $this['total_kpis'],
                'on_track' => $this['on_track'],
                'needs_attention' => $this['needs_attention'],
                'off_track' => $this['off_track'],
            ],
        ];
    }
}
```

### 9.2 KpiResource

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class KpiResource extends JsonResource
{
    public function toArray($request): array
    {
        $thresholds = $this->config['thresholds'] ?? [];

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'target' => $this->default_target,
            'actual' => $this->actual_value,
            'achievement_rate' => $this->achievement_rate,
            'status' => $this->status,
            'status_config' => $thresholds[$this->status] ?? null,
            'frequency' => $this->frequency,
            'owner' => $this->owner,
            'can_configure' => $request->user()?->can('configure', $this->resource),
        ];
    }
}
```

### 9.3 TopicDeliveryLogResource

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TopicDeliveryLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'topic' => [
                'id' => $this->topic->id,
                'title' => $this->topic->title,
                'week_number' => $this->topic->week_number,
            ],
            'teacher' => [
                'id' => $this->teacher->id,
                'name' => $this->teacher->name,
            ],
            'delivery_date' => $this->delivery_date->toDateString(),
            'notes' => $this->notes,
            'evidence' => AttachmentResource::collection($this->attachments),
            'status' => $this->status,
            'verification' => $this->when($this->verification, new VerificationResource($this->verification)),
            'can_verify' => $request->user()?->can('verify', $this->resource),
            'can_edit' => $request->user()?->can('update', $this->resource),
        ];
    }
}
```

---

## 10. Form Requests

### 10.1 StoreTopicDeliveryLogRequest

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTopicDeliveryLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\TopicDeliveryLog::class);
    }

    public function rules(): array
    {
        $kpi = \App\Models\Kpi::where('code', 'AE-01')->first();
        $evidenceConfig = $kpi?->config['evidence'] ?? ['required' => true, 'max_photos' => 5];

        return [
            'topic_id' => ['required', 'exists:topics,id'],
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'lesson_plan_id' => ['nullable', 'exists:lesson_plans,id'],
            'delivery_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'evidence' => [
                Rule::requiredIf($evidenceConfig['required'] ?? true),
                'array',
                'max:' . ($evidenceConfig['max_photos'] ?? 5),
            ],
            'evidence.*' => ['image', 'max:5120'], // 5MB max per image
        ];
    }

    public function messages(): array
    {
        return [
            'evidence.required' => 'Photo evidence is required for curriculum coverage logging.',
            'evidence.max' => 'You may upload a maximum of :max evidence photos.',
            'delivery_date.before_or_equal' => 'Delivery date cannot be in the future.',
        ];
    }
}
```

### 10.2 VerifyDeliveryLogRequest

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyDeliveryLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $log = $this->route('deliveryLog');
        return $this->user()->can('verify', $log);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:approved,rejected'],
            'comments' => ['required_if:status,rejected', 'nullable', 'string', 'max:2000'],
        ];
    }
}
```

---

## 11. Observers

### 11.1 AuditLogObserver

```php
<?php

namespace App\Observers;

use App\Models\AuditLog;

class AuditLogObserver
{
    public function created($model): void
    {
        $this->log($model, 'created');
    }

    public function updated($model): void
    {
        $this->log($model, 'updated', $model->getOriginal(), $model->getChanges());
    }

    public function deleted($model): void
    {
        $this->log($model, 'deleted');
    }

    protected function log($model, string $action, array $old = null, array $new = null): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
```

### 11.2 Observer Registration

```php
<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        \App\Models\TopicDeliveryLog::observe(\App\Observers\AuditLogObserver::class);
        \App\Models\LessonPlan::observe(\App\Observers\AuditLogObserver::class);
        \App\Models\Kpi::observe(\App\Observers\AuditLogObserver::class);
        \App\Models\Verification::observe(\App\Observers\AuditLogObserver::class);
    }
}
```

---

## 12. Middleware

### 12.1 EnsureActiveAcademicSession

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAcademicSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!session()->has('active_academic_session_id')) {
            $session = \App\Models\AcademicSession::where('is_current', true)->first();

            if (!$session) {
                return response()->json([
                    'message' => 'No active academic session configured. Please contact the administrator.'
                ], 403);
            }

            session()->put('active_academic_session_id', $session->id);
        }

        return $next($request);
    }
}
```

### 12.2 RoleBasedDashboardRedirect

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleBasedDashboardRedirect
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('dashboard') && $request->user()) {
            $user = $request->user();

            if ($user->isBoard()) {
                return redirect()->route('dashboard.board');
            }
            if ($user->isHoS()) {
                return redirect()->route('dashboard.hos');
            }
            if ($user->isHoD()) {
                return redirect()->route('dashboard.hod');
            }
            if ($user->isTeacher()) {
                return redirect()->route('dashboard.teacher');
            }
        }

        return $next($request);
    }
}
```

---

## 13. Seeders (Configuration Bootstrap)

### 13.1 KpiSeeder

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Pillar;
use App\Models\Kpi;

class KpiSeeder extends Seeder
{
    public function run(): void
    {
        $pillar1 = Pillar::create([
            'school_id' => 1,
            'name' => 'Academic Excellence',
            'code' => 'academic_excellence',
            'description' => 'Delivering rigorous, engaging, and future-ready learning experiences.',
            'display_order' => 1,
            'config' => [
                'color_theme' => '#1e3a5f',
                'icon' => 'academic-cap',
                'weight_in_overall_score' => 0.4,
                'thresholds' => [
                    'on_track' => ['min' => 1.0, 'color' => '#22c55e', 'label' => 'ON TRACK'],
                    'needs_attention' => ['min' => 0.90, 'max' => 0.9999, 'color' => '#eab308', 'label' => 'NEEDS ATTENTION'],
                    'off_track' => ['max' => 0.8999, 'color' => '#dc2626', 'label' => 'OFF TRACK'],
                ],
            ],
        ]);

        Kpi::create([
            'pillar_id' => $pillar1->id,
            'code' => 'AE-01',
            'name' => 'Curriculum Coverage Rate',
            'description' => 'Fortnightly audit comparing taught topics against approved Scheme of Work.',
            'measurement_methodology' => 'Compare topics completed vs planned topics in Scheme of Work.',
            'calculation_formula' => '(Topics Completed / Planned Topics) * 100',
            'data_collection_instrument' => 'Appendix C: Curriculum Coverage Tracker',
            'default_target' => 1.0,
            'target_type' => 'percentage',
            'frequency' => 'fortnightly',
            'unit' => '%',
            'display_order' => 1,
            'config' => [
                'thresholds' => [
                    'on_track' => ['min' => 1.0, 'color' => '#22c55e', 'label' => 'ON TRACK'],
                    'needs_attention' => ['min' => 0.90, 'max' => 0.9999, 'color' => '#eab308', 'label' => 'NEEDS ATTENTION'],
                    'off_track' => ['max' => 0.8999, 'color' => '#dc2626', 'label' => 'OFF TRACK'],
                ],
                'escalation' => [
                    'enabled' => true,
                    'delay_hours' => 72,
                    'escalate_to_role' => 'head_of_school',
                ],
                'automation' => [
                    'auto_calculate' => true,
                    'calculation_trigger' => 'on_verification_approved',
                    'observation_trigger' => ['enabled' => true, 'threshold' => 0.95, 'operator' => '<'],
                ],
                'evidence' => [
                    'required' => true,
                    'min_photos' => 1,
                    'max_photos' => 5,
                    'allowed_types' => ['image', 'pdf'],
                ],
                'verification' => [
                    'required' => true,
                    'verifier_role' => 'head_of_department',
                    'auto_approve_after_hours' => null,
                ],
            ],
        ]);

        // ... create remaining 20 KPIs following same pattern
    }
}
```

### 13.2 EscalationRuleSeeder

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EscalationRule;
use Spatie\Permission\Models\Role;

class EscalationRuleSeeder extends Seeder
{
    public function run(): void
    {
        $hodRole = Role::firstOrCreate(['name' => 'head_of_department', 'guard_name' => 'web']);
        $hosRole = Role::firstOrCreate(['name' => 'head_of_school', 'guard_name' => 'web']);

        EscalationRule::create([
            'name' => 'Topic Delivery Verification',
            'applies_to_type' => 'App\Models\TopicDeliveryLog',
            'trigger_event' => 'overdue',
            'delay_hours' => 72,
            'escalate_from_role_id' => $hodRole->id,
            'escalate_to_role_id' => $hosRole->id,
            'notification_channels' => ['in_app', 'email'],
            'is_active' => true,
            'config' => [
                'business_hours_only' => false,
                'weekend_excluded' => false,
                'max_escalations' => 2,
            ],
        ]);

        EscalationRule::create([
            'name' => 'Lesson Plan Approval',
            'applies_to_type' => 'App\Models\LessonPlan',
            'trigger_event' => 'overdue',
            'delay_hours' => 48,
            'escalate_from_role_id' => $hodRole->id,
            'escalate_to_role_id' => $hosRole->id,
            'notification_channels' => ['in_app'],
            'is_active' => true,
        ]);
    }
}
```

### 13.3 NotificationTemplateSeeder

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NotificationTemplate;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        NotificationTemplate::create([
            'name' => 'Verification Pending',
            'slug' => 'verification_pending',
            'channel' => 'in_app',
            'subject' => 'New Topic Delivery Pending Verification',
            'body' => 'Teacher {{teacher_name}} has logged topic "{{topic_title}}" for {{class_name}}. Please review and verify.',
            'variables' => ['teacher_name', 'topic_title', 'class_name'],
        ]);

        NotificationTemplate::create([
            'name' => 'Verification Escalated',
            'slug' => 'verification_escalated',
            'channel' => 'email',
            'subject' => 'Overdue Verification Escalated',
            'body' => 'A verification for {{topic_title}} by {{teacher_name}} has been escalated due to non-response from {{verifier_name}}.',
            'variables' => ['topic_title', 'teacher_name', 'verifier_name'],
        ]);

        NotificationTemplate::create([
            'name' => 'Observation Scheduled',
            'slug' => 'observation_scheduled',
            'channel' => 'in_app',
            'subject' => 'Classroom Observation Scheduled',
            'body' => 'Your classroom has been flagged for observation on {{observation_date}} due to {{kpi_name}} falling below target ({{achievement_rate}}%).',
            'variables' => ['observation_date', 'kpi_name', 'achievement_rate'],
        ]);
    }
}
```

---

## 14. File Storage Strategy

```php
<?php

// config/filesystems.php — add a dedicated disk
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'af-south-1'),
    'bucket' => env('AWS_BUCKET'),
    'url' => env('AWS_URL'),
    'endpoint' => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
    'throw' => false,
],

'evidence' => [
    'driver' => 's3',
    'bucket' => env('AWS_EVIDENCE_BUCKET', env('AWS_BUCKET')),
    'root' => 'evidence',
],
```

**Image Processing Pipeline:**
```php
// In AttachmentController or Service
use Intervention\Image\Laravel\Facades\Image;

public function storeEvidence($file, $attachable): Attachment
{
    // Compress image before upload
    $image = Image::read($file->getRealPath());
    $image->scaleDown(width: 1920); // Max width 1920px

    $path = 'evidence/' . date('Y/m') . '/' . uniqid() . '.jpg';
    $compressed = $image->encode(new \Intervention\Image\Encoders\JpegEncoder(quality: 75));

    Storage::disk('evidence')->put($path, $compressed);

    return Attachment::create([
        'attachable_type' => get_class($attachable),
        'attachable_id' => $attachable->id,
        'uploaded_by' => auth()->id(),
        'file_name' => $file->getClientOriginalName(),
        'file_path' => $path,
        'file_type' => 'image',
        'file_size' => strlen($compressed),
        'disk' => 'evidence',
        'metadata' => [
            'original_width' => $image->width(),
            'original_height' => $image->height(),
            'compressed' => true,
        ],
    ]);
}
```

---

## 15. Caching Strategy

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class DashboardCacheService
{
    public function getPillarSummary(int $pillarId, int $sessionId): array
    {
        $cacheKey = "pillar:{$pillarId}:session:{$sessionId}:summary";

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($pillarId, $sessionId) {
            $pillar = \App\Models\Pillar::find($pillarId);
            return $pillar?->calculateAchievement($sessionId) ?? [];
        });
    }

    public function invalidatePillar(int $pillarId, int $sessionId): void
    {
        Cache::forget("pillar:{$pillarId}:session:{$sessionId}:summary");
    }

    public function getKpiData(string $kpiCode, int $sessionId, ?int $termId = null): ?\App\Models\KpiPeriodicData
    {
        $cacheKey = "kpi:{$kpiCode}:session:{$sessionId}:term:" . ($termId ?? 'all');

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($kpiCode, $sessionId, $termId) {
            $kpi = \App\Models\Kpi::where('code', $kpiCode)->first();
            if (!$kpi) return null;

            $query = $kpi->periodicData()->where('academic_session_id', $sessionId);
            if ($termId) $query->where('term_id', $termId);

            return $query->first();
        });
    }
}
```

---

## 16. Recommended Packages

| Package | Purpose | Installation |
|---|---|---|
| `spatie/laravel-permission` | Role-based access control | `composer require spatie/laravel-permission` |
| `spatie/laravel-activitylog` | Audit logging | `composer require spatie/laravel-activitylog` |
| `intervention/image-laravel` | Image compression | `composer require intervention/image-laravel` |
| `laravel/sanctum` | API authentication | `composer require laravel/sanctum` |
| `maatwebsite/excel` | Excel import/export | `composer require maatwebsite/excel` |
| `barryvdh/laravel-dompdf` | PDF report generation | `composer require barryvdh/laravel-dompdf` |
| `predis/predis` | Redis caching | `composer require predis/predis` |
| `pusher/pusher-php-server` | Real-time notifications | `composer require pusher/pusher-php-server` |

---

## 17. Environment Configuration

```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wisca_monitor
DB_USERNAME=root
DB_PASSWORD=

# File Storage (AWS S3 or MinIO)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=af-south-1
AWS_BUCKET=wisca-evidence
AWS_URL=

# Cache & Queue
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Broadcasting (for real-time dashboard updates)
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https

# WhatsApp Business API (optional)
WHATSAPP_API_URL=
WHATSAPP_API_TOKEN=
WHATSAPP_BUSINESS_ID=

# Academic Session Default
DEFAULT_ACADEMIC_SESSION=
```

---

## 18. API Route Structure

```php
<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'ensure.active.session'])->group(function () {

    // Dashboard
    Route::get('/dashboard/executive', [\App\Http\Controllers\Api\DashboardController::class, 'executive']);
    Route::get('/dashboard/teacher', [\App\Http\Controllers\Api\DashboardController::class, 'teacher']);
    Route::get('/dashboard/hod', [\App\Http\Controllers\Api\DashboardController::class, 'hod']);

    // KPIs
    Route::get('/kpis', [\App\Http\Controllers\Api\KpiController::class, 'index']);
    Route::get('/kpis/{kpi}', [\App\Http\Controllers\Api\KpiController::class, 'show']);
    Route::put('/kpis/{kpi}/config', [\App\Http\Controllers\Api\KpiController::class, 'updateConfig'])
        ->middleware('can:configure,kpi');

    // Scheme of Work & Topics
    Route::apiResource('schemes', \App\Http\Controllers\Api\SchemeOfWorkController::class);
    Route::apiResource('schemes.topics', \App\Http\Controllers\Api\TopicController::class)->shallow();

    // Topic Delivery
    Route::apiResource('delivery-logs', \App\Http\Controllers\Api\TopicDeliveryLogController::class);
    Route::post('/delivery-logs/{deliveryLog}/verify', 
        [\App\Http\Controllers\Api\TopicDeliveryLogController::class, 'verify'])
        ->middleware('can:verify,deliveryLog');

    // Lesson Plans
    Route::apiResource('lesson-plans', \App\Http\Controllers\Api\LessonPlanController::class);
    Route::post('/lesson-plans/{lessonPlan}/approve', 
        [\App\Http\Controllers\Api\LessonPlanController::class, 'approve']);

    // Verifications
    Route::get('/verifications/pending', [\App\Http\Controllers\Api\VerificationController::class, 'pending']);
    Route::post('/verifications/{verification}/approve', [\App\Http\Controllers\Api\VerificationController::class, 'approve']);
    Route::post('/verifications/{verification}/reject', [\App\Http\Controllers\Api\VerificationController::class, 'reject']);

    // Observations
    Route::apiResource('observations', \App\Http\Controllers\Api\ObservationController::class);

    // Attendance
    Route::apiResource('attendance', \App\Http\Controllers\Api\AttendanceController::class);

    // Admin Configuration
    Route::middleware('can:manage-configuration')->group(function () {
        Route::apiResource('escalation-rules', \App\Http\Controllers\Api\EscalationRuleController::class);
        Route::apiResource('notification-templates', \App\Http\Controllers\Api\NotificationTemplateController::class);
        Route::apiResource('settings', \App\Http\Controllers\Api\SettingController::class)->only(['index', 'update']);
    });

    // Reports
    Route::get('/reports/kpi-summary', [\App\Http\Controllers\Api\ReportController::class, 'kpiSummary']);
    Route::get('/reports/coverage-detail', [\App\Http\Controllers\Api\ReportController::class, 'coverageDetail']);
    Route::get('/reports/export/{type}', [\App\Http\Controllers\Api\ReportController::class, 'export']);
});
```

---

## 19. Frontend Architecture Notes

### Recommended Stack
- **Framework:** Vue 3 + Vite (or React 18 + Vite)
- **State Management:** Pinia (Vue) or Zustand (React)
- **UI Library:** Tailwind CSS + Headless UI
- **Charts:** Chart.js or ApexCharts
- **PWA:** Vite PWA Plugin
- **Offline Storage:** IndexedDB (via Dexie.js) for teacher mobile app
- **Real-time:** Laravel Echo + Pusher (or Laravel Reverb)

### Key Frontend Modules
1. **DashboardModule** — Executive, HoS, HoD, Teacher views
2. **SchemeDeliveryModule** — Topic logging, evidence upload, coverage tracking
3. **VerificationModule** — Approval queues, escalation alerts
4. **KpiConfigurationModule** — Admin-only threshold and rule editing
5. **NotificationModule** — Real-time alerts, WhatsApp integration
6. **OfflineSyncModule** — Queue operations for offline-first teacher app

---

## 20. Security Checklist

- [ ] **Authentication:** Sanctum token-based auth for API, session-based for web
- [ ] **Authorization:** Spatie roles + Laravel Policies on every controller action
- [ ] **Input Validation:** Form Requests on all endpoints
- [ ] **SQL Injection:** Eloquent ORM only — no raw queries with user input
- [ ] **XSS Prevention:** Laravel's automatic escaping + validated file uploads
- [ ] **CSRF Protection:** Enabled on web routes; not needed for stateless API
- [ ] **File Uploads:** Validate MIME types, compress images, scan with ClamAV if possible
- [ ] **Rate Limiting:** Apply to auth endpoints and public APIs
- [ ] **Audit Logging:** Every create/update/delete logged with user context
- [ ] **Data Encryption:** Sensitive fields encrypted at rest if required by policy
- [ ] **HTTPS Only:** Force SSL in production
- [ ] **CORS:** Restrict to known frontend origins

---

## 21. Deployment Architecture

```
┌─────────────────────────────────────────────┐
│              Cloudflare / CDN               │
└─────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────┐
│              Nginx / Load Balancer          │
│         (SSL termination, rate limiting)    │
└─────────────────────────────────────────────┘
                    │
        ┌───────────┴───────────┐
        ▼                       ▼
┌───────────────┐       ┌───────────────┐
│  App Server 1 │       │  App Server 2 │
│   (PHP-FPM)   │       │   (PHP-FPM)   │
└───────────────┘       └───────────────┘
        │                       │
        └───────────┬───────────┘
                    ▼
┌─────────────────────────────────────────────┐
│              Redis Cluster                  │
│   (Cache, Sessions, Queues, Broadcasting)   │
└─────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────┐
│              MySQL 8.0 / RDS                │
│         (Primary + Read Replica)            │
└─────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────┐
│              S3 / MinIO                     │
│         (Evidence files, exports)           │
└─────────────────────────────────────────────┘
```

---

## 22. Testing Strategy

### Unit Tests
- `KpiCalculationServiceTest` — Test all 21 KPI formulas
- `CoverageCalculationServiceTest` — Test scheme coverage math
- `NotificationServiceTest` — Test template interpolation

### Feature Tests
- `TopicDeliveryLogTest` — CRUD, verification, escalation flow
- `DashboardApiTest` — Role-based data visibility
- `KpiConfigurationTest` — Admin can change thresholds; teachers cannot

### Integration Tests
- `OfflineSyncTest` — Teacher logs offline, syncs on reconnect
- `EscalationPipelineTest` — Overdue verification → HoS notification
- `ImageUploadTest` — Compression, storage, retrieval

### Load Tests
- 200 concurrent teachers submitting evidence simultaneously
- Board dashboard rendering 21 KPIs with 500+ data points

---

*Document Version: 1.0 | Generated for WISCA Strategy Monitor | Configuration-Driven Architecture*
