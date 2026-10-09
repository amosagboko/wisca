<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('school_id')->constrained()->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('school_id')->constrained()->nullOnDelete();
        });

        Schema::table('lesson_plans', function (Blueprint $table) {
            $table->index(['status', 'subject_id']);
            $table->index(['status', 'school_class_id']);
        });

        Schema::table('topic_coverage_logs', function (Blueprint $table) {
            $table->index(['status', 'subject_id']);
            $table->index(['status', 'school_class_id']);
        });

        $this->backfillGeneralDepartments();
    }

    public function down(): void
    {
        Schema::table('topic_coverage_logs', function (Blueprint $table) {
            $table->dropIndex(['status', 'subject_id']);
            $table->dropIndex(['status', 'school_class_id']);
        });

        Schema::table('lesson_plans', function (Blueprint $table) {
            $table->dropIndex(['status', 'subject_id']);
            $table->dropIndex(['status', 'school_class_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::dropIfExists('departments');
    }

    protected function backfillGeneralDepartments(): void
    {
        $now = now();
        $schoolIds = DB::table('schools')->pluck('id');

        foreach ($schoolIds as $schoolId) {
            $departmentId = DB::table('departments')->insertGetId([
                'school_id' => $schoolId,
                'name' => 'General',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('subjects')
                ->where('school_id', $schoolId)
                ->whereNull('department_id')
                ->update(['department_id' => $departmentId]);

            $hodIds = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('roles.name', 'head_of_department')
                ->where('model_has_roles.model_type', 'App\\Models\\User')
                ->pluck('model_has_roles.model_id');

            if ($hodIds->isNotEmpty()) {
                DB::table('users')
                    ->where('school_id', $schoolId)
                    ->whereIn('id', $hodIds->all())
                    ->whereNull('department_id')
                    ->update(['department_id' => $departmentId]);
            }
        }
    }
};
