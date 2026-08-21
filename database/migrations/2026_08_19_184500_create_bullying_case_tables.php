<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bullying_case_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->text('description')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bullying_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bullying_case_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('target_learner_id')->nullable()->constrained('learners')->nullOnDelete();
            $table->foreignId('reported_by_learner_id')->nullable()->constrained('learners')->nullOnDelete();
            $table->date('reported_on');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('severity', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['reported', 'investigating', 'closed'])->default('reported');
            $table->boolean('safety_plan_created')->default(false);
            $table->text('safety_plan')->nullable();
            $table->timestamp('safety_plan_created_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['academic_session_id', 'term_id']);
            $table->index(['school_id', 'status']);
            $table->index(['bullying_case_type_id', 'reported_on'], 'bullying_case_type_date_idx');
            $table->index(['safety_plan_created', 'closed_at'], 'bullying_safety_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bullying_cases');
        Schema::dropIfExists('bullying_case_types');
    }
};
