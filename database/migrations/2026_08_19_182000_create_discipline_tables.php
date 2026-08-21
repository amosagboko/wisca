<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_incident_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->text('description')->nullable();
            $table->boolean('restorative_required')->default(true);
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('discipline_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discipline_incident_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('learner_id')->nullable()->constrained()->nullOnDelete();
            $table->date('incident_date');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('severity', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['open', 'in_review', 'resolved'])->default('open');
            $table->enum('restorative_status', ['not_required', 'pending', 'in_progress', 'completed'])->default('pending');
            $table->text('restorative_agreement')->nullable();
            $table->text('restorative_actions')->nullable();
            $table->timestamp('restorative_completed_at')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['academic_session_id', 'term_id']);
            $table->index(['school_id', 'status']);
            $table->index(['discipline_incident_type_id', 'incident_date'], 'disc_inc_type_date_idx');
            $table->index(['restorative_status', 'restorative_completed_at'], 'disc_restorative_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_incidents');
        Schema::dropIfExists('discipline_incident_types');
    }
};
