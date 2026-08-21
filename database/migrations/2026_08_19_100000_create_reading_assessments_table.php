<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained();
            $table->foreignId('academic_session_id')->constrained();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->constrained('users');

            // grade-level score at each checkpoint (e.g. 3.5 = mid-Year-3 reading level)
            $table->decimal('baseline_level', 5, 2)->nullable();
            $table->decimal('followup_level', 5, 2)->nullable();

            // checkpoint labels ('start_of_session', 'mid_term', 'end_of_session', etc.)
            $table->string('baseline_checkpoint')->default('start_of_session');
            $table->string('followup_checkpoint')->nullable();

            $table->date('baseline_date')->nullable();
            $table->date('followup_date')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            // one row per learner per session
            $table->unique(['learner_id', 'academic_session_id'], 'reading_assessments_learner_session_unique');
            $table->index(['academic_session_id', 'school_class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_assessments');
    }
};
