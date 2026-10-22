<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('active');
            $table->foreignId('hod_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::table('school_classes', function (Blueprint $table) {
            $table->foreignId('school_section_id')->nullable()->after('school_id')->constrained('school_sections')->nullOnDelete();
        });

        $this->seedKnownSections();
    }

    public function down(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_section_id');
        });

        Schema::dropIfExists('school_sections');
    }

    protected function seedKnownSections(): void
    {
        $now = now();
        $schoolIds = DB::table('schools')->pluck('id');

        foreach ($schoolIds as $schoolId) {
            $eyfsId = $this->firstOrCreateSection((int) $schoolId, 'EYFS', $now);
            $basicId = $this->firstOrCreateSection((int) $schoolId, 'Basic School', $now);

            $classes = DB::table('school_classes')
                ->where('school_id', $schoolId)
                ->whereNull('school_section_id')
                ->get(['id', 'name']);

            foreach ($classes as $class) {
                $sectionId = $this->sectionIdForClassName((string) $class->name, $eyfsId, $basicId);
                if ($sectionId === null) {
                    continue;
                }

                DB::table('school_classes')
                    ->where('id', $class->id)
                    ->update(['school_section_id' => $sectionId]);
            }
        }
    }

    protected function firstOrCreateSection(int $schoolId, string $name, $now): int
    {
        $existing = DB::table('school_sections')
            ->where('school_id', $schoolId)
            ->where('name', $name)
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('school_sections')->insertGetId([
            'school_id' => $schoolId,
            'name' => $name,
            'status' => 'active',
            'hod_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function sectionIdForClassName(string $name, int $eyfsId, int $basicId): ?int
    {
        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? $name));

        $eyfs = [
            'pre-nursery', 'pre nursery', 'prenursery',
            'nursery',
            'kindergarten', 'reception',
            'kindergarten/reception', 'kindergarten / reception',
            'kindergarten reception',
        ];

        if (in_array($normalized, $eyfs, true)) {
            return $eyfsId;
        }

        if (in_array($normalized, ['basic 1', 'basic 2', 'basic1', 'basic2'], true)) {
            return $basicId;
        }

        return null;
    }
};
