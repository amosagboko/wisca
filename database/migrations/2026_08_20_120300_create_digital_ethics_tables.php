<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_ethics_audit_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->text('description')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('digital_ethics_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('digital_ethics_audit_type_id')->constrained('digital_ethics_audit_types')->cascadeOnDelete();
            $table->foreignId('learner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->string('assignment_title');
            $table->date('audited_on');
            $table->boolean('free_of_violations')->default(true);
            $table->string('violation_category', 100)->nullable();
            $table->string('detector_tool', 100)->nullable();
            $table->text('findings')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('audited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['academic_session_id', 'term_id'], 'ethics_session_term_idx');
            $table->index(['free_of_violations'], 'ethics_free_idx');
            $table->index(['audited_on'], 'ethics_audited_on_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_ethics_audits');
        Schema::dropIfExists('digital_ethics_audit_types');
    }
};
