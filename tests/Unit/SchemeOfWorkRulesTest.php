<?php

namespace Tests\Unit;

use App\Models\SchemeOfWork;
use App\Models\Term;
use App\Services\CoverageCalculationService;
use App\Services\SchemeOfWorkBulkUpload;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SchemeOfWorkRulesTest extends TestCase
{
    public function test_submitted_draft_is_identified_by_submitted_at(): void
    {
        $draft = new SchemeOfWork(['status' => 'draft', 'submitted_at' => null]);
        $submitted = new SchemeOfWork(['status' => 'draft', 'submitted_at' => now()]);

        $this->assertTrue($draft->isEditableDraft());
        $this->assertFalse($draft->isSubmitted());
        $this->assertTrue($submitted->isSubmitted());
        $this->assertFalse($submitted->isEditableDraft());
        $this->assertSame('Submitted', $submitted->processLabel());
    }

    public function test_board_approve_and_activate_require_prior_slots(): void
    {
        $submitted = new SchemeOfWork([
            'status' => 'draft',
            'submitted_at' => now(),
        ]);
        $this->assertTrue($submitted->canHosApprove());
        $this->assertFalse($submitted->canBoardApprove());
        $this->assertFalse($submitted->canActivate());

        $hosDone = new SchemeOfWork([
            'status' => 'draft',
            'submitted_at' => now(),
            'hos_approved_by' => 1,
            'hos_approved_at' => now(),
        ]);
        $this->assertTrue($hosDone->canBoardApprove());
        $this->assertFalse($hosDone->canActivate());

        $approved = new SchemeOfWork([
            'status' => 'approved',
            'hos_approved_by' => 1,
            'hos_approved_at' => now(),
            'board_approved_by' => 2,
            'board_approved_at' => now(),
        ]);
        $this->assertTrue($approved->canActivate());
        $this->assertTrue($approved->canReject());
    }

    public function test_archived_cannot_be_reactivated_by_status_helpers(): void
    {
        $archived = new SchemeOfWork(['status' => 'archived']);

        $this->assertTrue($archived->isArchived());
        $this->assertFalse($archived->canActivate());
        $this->assertFalse($archived->canBeSubmitted());
        $this->assertFalse($archived->canReject());
        $this->assertFalse($archived->canBePermanentlyDeleted());
    }

    public function test_hod_may_permanently_delete_drafts_not_active_or_archived(): void
    {
        $draft = new SchemeOfWork(['status' => 'draft', 'submitted_at' => null]);
        $submitted = new SchemeOfWork(['status' => 'draft', 'submitted_at' => now()]);
        $approved = new SchemeOfWork(['status' => 'approved']);
        $active = new SchemeOfWork(['status' => 'active']);
        $archived = new SchemeOfWork(['status' => 'archived']);

        $this->assertTrue($draft->canBePermanentlyDeleted());
        $this->assertTrue($submitted->canBePermanentlyDeleted());
        $this->assertFalse($approved->canBePermanentlyDeleted());
        $this->assertFalse($active->canBePermanentlyDeleted());
        $this->assertFalse($archived->canBePermanentlyDeleted());
    }

    public function test_current_term_uses_is_current_not_latest_date(): void
    {
        $source = file_get_contents((new \ReflectionClass(\App\Models\Term::class))->getFileName());
        $this->assertStringContainsString("where('is_current', true)", $source);
        $this->assertStringNotContainsString("orderByDesc('start_date')", $source);
    }

    public function test_lesson_plan_due_at_is_still_monday_of_instructional_week(): void
    {
        $term = new Term(['start_date' => '2025-09-01']);

        $this->assertTrue($term->lessonPlanDueAt(1)->isMonday());
        $this->assertSame(
            $term->instructionalWeekStart(1)->startOfWeek(Carbon::MONDAY)->toDateTimeString(),
            $term->lessonPlanDueAt(1)->toDateTimeString(),
        );
    }

    public function test_ae01_calculation_still_includes_approved_and_active(): void
    {
        $source = file_get_contents((new \ReflectionClass(CoverageCalculationService::class))->getFileName());

        $this->assertStringContainsString("whereIn('status', ['active', 'approved'])", $source);
        $this->assertStringContainsString('unweighted average of scheme coverage rates', $source);
    }

    public function test_bulk_upload_template_parses_weeks_and_pipe_separated_objectives(): void
    {
        $service = new SchemeOfWorkBulkUpload;
        $path = tempnam(sys_get_temp_dir(), 'sow');
        file_put_contents($path, $service->templateCsv());
        $file = new UploadedFile($path, SchemeOfWorkBulkUpload::FILENAME, 'text/csv', null, true);

        $topics = $service->parse($file);

        $this->assertCount(3, $topics);
        $this->assertSame(1, $topics[0]['week_number']);
        $this->assertSame('Number counting', $topics[0]['title']);
        $this->assertSame(
            "Count numbers 1 to 20\nUnderstand place value\nUse counting in daily activities",
            $topics[0]['learning_objectives']
        );
        $this->assertSame(
            "Add 2-digit numbers without regrouping\nAdd 2-digit numbers with regrouping\nSolve simple word problems",
            $topics[1]['learning_objectives']
        );

        @unlink($path);
    }

    public function test_bulk_upload_rejects_non_csv_and_empty_rows(): void
    {
        $service = new SchemeOfWorkBulkUpload;
        $path = tempnam(sys_get_temp_dir(), 'sow');
        file_put_contents($path, 'not a csv of topics');
        $xlsx = new UploadedFile($path, 'scheme.xlsx', 'application/vnd.ms-excel', null, true);

        try {
            $service->parse($xlsx);
            $this->fail('Expected validation exception for xlsx.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('bulk_template', $e->errors());
        }

        file_put_contents($path, "week_number,title,learning_objectives\n");
        $empty = new UploadedFile($path, 'template.csv', 'text/csv', null, true);

        try {
            $service->parse($empty);
            $this->fail('Expected validation exception for empty csv.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('bulk_template', $e->errors());
        }

        @unlink($path);
    }
}
