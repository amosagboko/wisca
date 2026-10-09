<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topic_catch_up_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('opened_at');
            $table->text('notes')->nullable();
            $table->unsignedInteger('target_week_number')->nullable();
            $table->string('status', 16)->default('open');
            $table->timestamp('addressed_at')->nullable();
            $table->foreignId('addressed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('coverage_log_id')->nullable()->constrained('topic_coverage_logs')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_catch_up_plans');
    }
};
