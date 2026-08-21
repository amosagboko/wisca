<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-configurable activity types (chapel, assembly, devotion, prayer, etc.)
        Schema::create('chapel_activity_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');                         // e.g. "Chapel Service", "Morning Assembly"
            $table->string('code', 20)->nullable();         // e.g. CHAPEL, ASSEMBLY
            $table->text('description')->nullable();
            // Which participation level(s) count for CE-01 numerator
            // passive|active|leading — stored as comma-separated or JSON
            $table->json('counting_levels')->nullable();
            $table->boolean('school_wide')->default(true);  // affects whole school roll or class-specific
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // One record per scheduled/held session
        Schema::create('chapel_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapel_activity_type_id')->constrained('chapel_activity_types')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->date('session_date');
            $table->string('theme')->nullable();
            $table->string('scripture_reference')->nullable();
            $table->foreignId('led_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['scheduled', 'held', 'cancelled'])->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['academic_session_id', 'session_date']);
            $table->index(['term_id', 'status']);
        });

        // Per-learner attendance record for each session
        Schema::create('chapel_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapel_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['present', 'absent', 'late'])->default('present');
            $table->enum('participation_level', ['passive', 'active', 'leading'])->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['chapel_session_id', 'learner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapel_attendances');
        Schema::dropIfExists('chapel_sessions');
        Schema::dropIfExists('chapel_activity_types');
    }
};
