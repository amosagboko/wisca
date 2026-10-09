<?php

use App\Models\TeacherAssignment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['school_class_id', 'subject_id']);
        });

        $pairs = TeacherAssignment::query()
            ->select('school_class_id', 'subject_id')
            ->distinct()
            ->get();

        $now = now();
        foreach ($pairs as $pair) {
            DB::table('class_subjects')->insertOrIgnore([
                'school_class_id' => $pair->school_class_id,
                'subject_id' => $pair->subject_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('class_subjects');
    }
};
