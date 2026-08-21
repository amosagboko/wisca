<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->date('week_start_date');
            $table->enum('actor_type', ['staff', 'learner']);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('learner_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('login_count')->default(0);
            $table->unsignedInteger('activity_count')->default(0);
            $table->boolean('is_active_weekly')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academic_session_id', 'week_start_date', 'user_id'], 'lms_week_user_unique');
            $table->unique(['academic_session_id', 'week_start_date', 'learner_id'], 'lms_week_learner_unique');
            $table->index(['school_id', 'week_start_date'], 'lms_school_week_idx');
            $table->index(['actor_type', 'is_active_weekly'], 'lms_actor_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_usage_logs');
    }
};
