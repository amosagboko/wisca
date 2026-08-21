<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schemes_of_work', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('file_path')->nullable();
            $table->enum('status', ['draft', 'approved', 'active', 'archived'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheme_of_work_id')->constrained('schemes_of_work')->cascadeOnDelete();
            $table->integer('week_number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('learning_objectives')->nullable();
            $table->integer('expected_duration_minutes')->default(240);
            $table->integer('display_order')->default(0);
            $table->enum('status', ['planned', 'in_progress', 'covered', 'skipped'])->default('planned');
            $table->timestamps();
        });

        Schema::create('topic_coverage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users');
            $table->foreignId('school_class_id')->constrained();
            $table->foreignId('subject_id')->constrained();
            $table->date('coverage_date');
            $table->string('workbook_reference')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'submitted', 'verified', 'rejected'])->default('draft');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_coverage_logs');
        Schema::dropIfExists('topics');
        Schema::dropIfExists('schemes_of_work');
    }
};
