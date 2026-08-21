<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users');
            $table->foreignId('school_class_id')->constrained();
            $table->foreignId('subject_id')->constrained();
            $table->text('objectives')->nullable();
            $table->text('activities')->nullable();
            $table->text('assessment')->nullable();
            $table->text('resources')->nullable();
            $table->string('file_path')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->boolean('on_time')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('topic_coverage_logs', function (Blueprint $table) {
            $table->foreignId('lesson_plan_id')->nullable()->after('subject_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('topic_coverage_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lesson_plan_id');
        });

        Schema::dropIfExists('lesson_plans');
    }
};
