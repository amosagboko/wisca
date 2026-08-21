<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained();
            $table->string('name');
            $table->string('admission_no')->nullable();
            $table->string('gender')->nullable();
            $table->string('status')->default('enrolled');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'school_class_id', 'status']);
        });

        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained();
            $table->foreignId('school_class_id')->constrained();
            $table->foreignId('academic_session_id')->constrained();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->constrained('users');
            $table->string('assessment_key')->default('term_exam');
            $table->string('assessment_name');
            $table->decimal('score', 5, 2);
            $table->timestamps();

            $table->unique(
                ['learner_id', 'subject_id', 'academic_session_id', 'term_id', 'assessment_key'],
                'exam_results_sitting_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_results');
        Schema::dropIfExists('learners');
    }
};
