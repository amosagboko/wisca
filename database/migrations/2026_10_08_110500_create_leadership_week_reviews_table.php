<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leadership_week_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('week_number');
            $table->foreignId('reviewed_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('reviewed_at');
            $table->text('notes')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->unique(
                ['school_id', 'academic_session_id', 'term_id', 'week_number'],
                'leadership_week_reviews_period_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leadership_week_reviews');
    }
};
