<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('at_risk_learners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained();
            $table->foreignId('academic_session_id')->constrained();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('identified_by')->constrained('users');
            $table->date('identification_date');
            $table->json('risk_factors')->nullable();
            $table->text('concern_note')->nullable();
            $table->string('risk_level')->default('medium');
            $table->string('status')->default('active');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['learner_id', 'academic_session_id'], 'at_risk_learner_session_unique');
            $table->index(['academic_session_id', 'status']);
        });

        Schema::create('intervention_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('at_risk_learner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coordinator_id')->constrained('users');
            $table->string('plan_type');
            $table->text('objectives')->nullable();
            $table->text('strategies')->nullable();
            $table->date('start_date');
            $table->date('review_date');
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['at_risk_learner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intervention_plans');
        Schema::dropIfExists('at_risk_learners');
    }
};
