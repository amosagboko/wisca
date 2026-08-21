<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recorded_by')->constrained('users');
            $table->foreignId('school_class_id')->constrained();
            $table->foreignId('academic_session_id')->constrained();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->date('attendance_date');
            $table->unsignedInteger('enrolled_count');
            $table->unsignedInteger('present_count');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['school_class_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
