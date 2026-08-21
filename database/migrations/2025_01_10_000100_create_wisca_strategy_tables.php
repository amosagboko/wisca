<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general');
            $table->string('type')->default('string');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('pillars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->integer('display_order')->default(0);
            $table->json('config')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('kpis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pillar_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('measurement_methodology')->nullable();
            $table->string('calculation_formula')->nullable();
            $table->string('data_collection_instrument')->nullable();
            $table->decimal('default_target', 12, 4)->nullable();
            $table->string('target_type')->default('percentage');
            $table->string('frequency');
            $table->string('unit')->nullable();
            $table->string('owner_role')->nullable();
            $table->integer('display_order')->default(0);
            $table->json('config')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('kpi_periodic_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('target_value', 12, 4)->nullable();
            $table->decimal('actual_value', 12, 4)->nullable();
            $table->decimal('achievement_rate', 12, 4)->nullable();
            $table->string('status')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['kpi_id', 'academic_session_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_periodic_data');
        Schema::dropIfExists('kpis');
        Schema::dropIfExists('pillars');
        Schema::dropIfExists('settings');
    }
};
