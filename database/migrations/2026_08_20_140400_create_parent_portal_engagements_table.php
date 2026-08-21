<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_portal_engagements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained('parents')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->date('month_start_date');
            $table->unsignedInteger('login_count')->default(0);
            $table->boolean('is_active_monthly')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['parent_id', 'month_start_date'], 'parent_portal_month_unique');
            $table->index(['school_id', 'month_start_date'], 'parent_portal_school_month_idx');
            $table->index(['academic_session_id', 'term_id'], 'parent_portal_session_term_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_portal_engagements');
    }
};
