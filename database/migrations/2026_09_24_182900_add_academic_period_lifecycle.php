<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->unsignedInteger('sequence')->default(1)->after('academic_session_id');
            $table->boolean('is_current')->default(false)->after('status');
        });

        $sessions = DB::table('academic_sessions')->orderBy('id')->get();
        foreach ($sessions as $session) {
            $terms = DB::table('terms')
                ->where('academic_session_id', $session->id)
                ->orderBy('start_date')
                ->orderBy('id')
                ->get();

            $currentAssigned = false;
            foreach ($terms as $index => $term) {
                $isCurrent = false;
                if ($session->is_current && ! $currentAssigned && $term->status === 'active') {
                    $isCurrent = true;
                    $currentAssigned = true;
                }

                DB::table('terms')->where('id', $term->id)->update([
                    'sequence' => $index + 1,
                    'is_current' => $isCurrent,
                ]);
            }
        }

        Schema::create('academic_period_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('action', 32);
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at');
            $table->foreignId('previous_session_id')->nullable()->constrained('academic_sessions')->nullOnDelete();
            $table->foreignId('previous_term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignId('new_session_id')->nullable()->constrained('academic_sessions')->nullOnDelete();
            $table->foreignId('new_term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $this->addCurrentPeriodIndexes();
    }

    public function down(): void
    {
        $this->dropCurrentPeriodIndexes();

        Schema::dropIfExists('academic_period_transitions');

        Schema::table('terms', function (Blueprint $table) {
            $table->dropColumn(['sequence', 'is_current']);
        });
    }

    protected function addCurrentPeriodIndexes(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement('CREATE UNIQUE INDEX academic_sessions_one_current ON academic_sessions (school_id) WHERE is_current = 1');
            DB::statement('CREATE UNIQUE INDEX terms_one_current ON terms (academic_session_id) WHERE is_current = 1');

            return;
        }

        if ($driver === 'mysql') {
            DB::statement('CREATE UNIQUE INDEX academic_sessions_one_current ON academic_sessions ((CASE WHEN is_current = 1 THEN school_id END))');
            DB::statement('CREATE UNIQUE INDEX terms_one_current ON terms ((CASE WHEN is_current = 1 THEN academic_session_id END))');
        }
    }

    protected function dropCurrentPeriodIndexes(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement('DROP INDEX IF EXISTS academic_sessions_one_current');
            DB::statement('DROP INDEX IF EXISTS terms_one_current');

            return;
        }

        if ($driver === 'mysql') {
            DB::statement('DROP INDEX academic_sessions_one_current ON academic_sessions');
            DB::statement('DROP INDEX terms_one_current ON terms');
        }
    }
};
