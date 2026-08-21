<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_digital_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('uses_e_assessment')->default(false);
            $table->boolean('uses_e_portfolio')->default(false);
            $table->string('primary_tool', 100)->nullable();
            $table->text('evidence_notes')->nullable();
            $table->date('verified_on')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['subject_id', 'academic_session_id', 'term_id'],
                'subj_digital_assess_unique'
            );
            $table->index(['academic_session_id', 'term_id'], 'subj_digital_session_term_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_digital_assessments');
    }
};
