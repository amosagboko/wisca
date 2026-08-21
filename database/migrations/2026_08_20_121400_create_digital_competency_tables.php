<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_competency_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('passing_level')->default(3);
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('digital_competency_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('digital_competency_area_id')->constrained('digital_competency_areas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('level');
            $table->date('assessed_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['user_id', 'digital_competency_area_id', 'academic_session_id', 'term_id'],
                'dig_comp_rating_unique'
            );
            $table->index(['academic_session_id', 'term_id'], 'dig_comp_session_term_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_competency_ratings');
        Schema::dropIfExists('digital_competency_areas');
    }
};
