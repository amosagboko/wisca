<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schemes_of_work', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('term_id');
            $table->foreignId('replaces_id')->nullable()->after('version')->constrained('schemes_of_work')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->after('uploaded_by')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            $table->foreignId('hos_approved_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('hos_approved_at')->nullable()->after('hos_approved_by');
            $table->foreignId('board_approved_by')->nullable()->after('hos_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('board_approved_at')->nullable()->after('board_approved_by');
            $table->foreignId('rejected_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
        });

        $approved = DB::table('schemes_of_work')
            ->whereIn('status', ['approved', 'active'])
            ->whereNotNull('approved_by')
            ->get(['id', 'approved_by', 'approved_at']);

        foreach ($approved as $row) {
            DB::table('schemes_of_work')->where('id', $row->id)->update([
                'version' => 1,
                'submitted_at' => $row->approved_at,
                'submitted_by' => $row->approved_by,
                'hos_approved_by' => $row->approved_by,
                'hos_approved_at' => $row->approved_at,
                'board_approved_by' => $row->approved_by,
                'board_approved_at' => $row->approved_at,
            ]);
        }

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement('CREATE UNIQUE INDEX schemes_of_work_one_active ON schemes_of_work (academic_session_id, term_id, school_class_id, subject_id) WHERE status = \'active\' AND deleted_at IS NULL');
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement('DROP INDEX IF EXISTS schemes_of_work_one_active');
        }

        Schema::table('schemes_of_work', function (Blueprint $table) {
            $table->dropForeign(['replaces_id']);
            $table->dropForeign(['submitted_by']);
            $table->dropForeign(['hos_approved_by']);
            $table->dropForeign(['board_approved_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropColumn([
                'version',
                'replaces_id',
                'submitted_by',
                'submitted_at',
                'hos_approved_by',
                'hos_approved_at',
                'board_approved_by',
                'board_approved_at',
                'rejected_by',
                'rejected_at',
                'rejection_reason',
            ]);
        });
    }
};
