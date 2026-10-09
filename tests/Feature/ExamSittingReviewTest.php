<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\AtRiskLearner;
use App\Models\ExamResult;
use App\Models\InterventionPlan;
use App\Models\Learner;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\AtRiskCalculationService;
use App\Services\ExamCalculationService;
use App\Support\AtRiskCriteria;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExamSittingReviewTest extends TestCase
{
    protected bool $mysqlTransaction = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (extension_loaded('pdo_sqlite') && config('database.default') === 'sqlite') {
            $this->artisan('migrate');

            return;
        }

        $this->switchToMysqlFromDotEnv();
        DB::beginTransaction();
        $this->mysqlTransaction = true;
    }

    protected function tearDown(): void
    {
        if ($this->mysqlTransaction && DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    protected function switchToMysqlFromDotEnv(): void
    {
        $path = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'.env';
        $vars = \Dotenv\Dotenv::parse((string) file_get_contents($path));

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => $vars['DB_HOST'] ?? '127.0.0.1',
            'database.connections.mysql.port' => $vars['DB_PORT'] ?? '3306',
            'database.connections.mysql.database' => $vars['DB_DATABASE'] ?? 'wisca',
            'database.connections.mysql.username' => $vars['DB_USERNAME'] ?? 'root',
            'database.connections.mysql.password' => $vars['DB_PASSWORD'] ?? '',
        ]);

        DB::purge();
        DB::reconnect();
        DB::setDefaultConnection('mysql');
    }

    /**
     * @return array<string, mixed>
     */
    protected function world(): array
    {
        foreach (['board', 'head_of_school', 'head_of_department', 'teacher', 'admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $token = Str::lower(Str::random(8));
        $school = School::create(['name' => 'Exam School', 'slug' => 'exam-school-'.$token, 'status' => 'active']);
        $session = AcademicSession::create([
            'school_id' => $school->id,
            'name' => '2025/2026',
            'start_date' => now()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'end_date' => now()->addMonths(9)->toDateString(),
            'is_current' => true,
            'status' => 'active',
        ]);
        $term = Term::create([
            'academic_session_id' => $session->id,
            'name' => 'First Term',
            'start_date' => $session->start_date,
            'end_date' => $session->end_date,
            'sequence' => 1,
            'is_current' => true,
            'status' => 'active',
        ]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'JSS 1A', 'level' => 'JSS', 'status' => 'active']);
        $subject = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics', 'code' => 'MTH'.$token, 'status' => 'active']);

        $users = [];
        foreach (['head_of_department', 'teacher'] as $role) {
            $user = User::create([
                'school_id' => $school->id,
                'name' => $role,
                'email' => $role.'.'.$token.'@exam.test',
                'password' => 'password',
                'status' => 'active',
            ]);
            $user->assignRole($role);
            $users[$role] = $user;
        }

        TeacherAssignment::create([
            'teacher_id' => $users['teacher']->id,
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'academic_session_id' => $session->id,
            'status' => 'active',
        ]);

        $learners = collect([
            Learner::create([
                'school_id' => $school->id,
                'school_class_id' => $class->id,
                'name' => 'Ada One',
                'admission_no' => 'EX-'.$token.'-1',
                'status' => 'enrolled',
            ]),
            Learner::create([
                'school_id' => $school->id,
                'school_class_id' => $class->id,
                'name' => 'Ben Two',
                'admission_no' => 'EX-'.$token.'-2',
                'status' => 'enrolled',
            ]),
        ]);

        return compact('school', 'session', 'term', 'class', 'subject', 'users', 'learners');
    }

    protected function scoreAll(array $world, array $scores = [80, 40]): void
    {
        foreach ($world['learners'] as $index => $learner) {
            ExamResult::create([
                'learner_id' => $learner->id,
                'subject_id' => $world['subject']->id,
                'school_class_id' => $world['class']->id,
                'academic_session_id' => $world['session']->id,
                'term_id' => $world['term']->id,
                'recorded_by' => $world['users']['teacher']->id,
                'assessment_key' => ExamCalculationService::ASSESSMENT_KEY,
                'assessment_name' => 'First Term examination',
                'score' => $scores[$index],
                'status' => 'submitted',
            ]);
        }
    }

    protected function assignmentKey(array $world): string
    {
        return $world['class']->id.':'.$world['subject']->id;
    }

    public function test_incomplete_marksheet_is_a_teacher_task_and_hod_gap(): void
    {
        $world = $this->world();
        ExamResult::create([
            'learner_id' => $world['learners'][0]->id,
            'subject_id' => $world['subject']->id,
            'school_class_id' => $world['class']->id,
            'academic_session_id' => $world['session']->id,
            'term_id' => $world['term']->id,
            'recorded_by' => $world['users']['teacher']->id,
            'assessment_key' => ExamCalculationService::ASSESSMENT_KEY,
            'assessment_name' => 'First Term examination',
            'score' => 80,
            'status' => 'submitted',
        ]);

        $this->actingAs($world['users']['teacher'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Complete marksheet')
            ->assertSee('1 of 2 learners without a score.');

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Marksheet incomplete')
            ->assertSee('View marksheet')
            ->assertDontSee('Review marksheet')
            ->assertDontSee('Approve marksheet');
    }

    public function test_teacher_cannot_verify_complete_sitting(): void
    {
        $world = $this->world();
        $this->scoreAll($world);

        $this->actingAs($world['users']['teacher'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Marksheet awaiting HOD verification.')
            ->assertSee('Waiting on HOD');

        $this->actingAs($world['users']['teacher'])
            ->post(route('exam-results.verify'), ['assignment' => $this->assignmentKey($world)])
            ->assertForbidden();
    }

    public function test_hod_verifies_complete_sitting_without_changing_ae02_counts(): void
    {
        $world = $this->world();
        $this->scoreAll($world);
        $before = app(ExamCalculationService::class)->termSummary($world['session'], $world['term']);

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Review marksheet')
            ->assertSee('Approve marksheet');

        $this->actingAs($world['users']['head_of_department'])
            ->post(route('exam-results.verify'), ['assignment' => $this->assignmentKey($world)])
            ->assertRedirect();

        $this->assertTrue(ExamResult::query()->where('subject_id', $world['subject']->id)->get()->every->isVerified());
        $this->assertEquals($before, app(ExamCalculationService::class)->termSummary($world['session'], $world['term']));

        $flagged = AtRiskLearner::query()
            ->where('academic_session_id', $world['session']->id)
            ->where('status', 'active')
            ->get();
        $this->assertCount(1, $flagged);
        $this->assertSame($world['learners'][1]->id, $flagged->first()->learner_id);
        $this->assertContains('below_pass_mark', $flagged->first()->risk_factors);
        $this->assertSame(0, InterventionPlan::query()->where('at_risk_learner_id', $flagged->first()->id)->count());

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Plans needed')
            ->assertSee('Add intervention plan')
            ->assertSee('Ben Two')
            ->assertDontSee('Review marksheet');

        $this->actingAs($world['users']['teacher'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ben Two')
            ->assertSee('No plan')
            ->assertDontSee('Add intervention plan');

        $this->actingAs($world['users']['teacher'])
            ->put(route('exam-results.update'), [
                'assignment' => $this->assignmentKey($world),
                'scores' => [
                    $world['learners'][0]->id => 90,
                    $world['learners'][1]->id => 40,
                ],
            ])
            ->assertSessionHasErrors('assignment');
    }

    public function test_hod_writes_iip_from_exam_flag_and_ae07_counts_the_plan(): void
    {
        $world = $this->world();
        $this->scoreAll($world);

        $this->actingAs($world['users']['head_of_department'])
            ->post(route('exam-results.verify'), ['assignment' => $this->assignmentKey($world)])
            ->assertRedirect();

        $record = AtRiskLearner::query()
            ->where('academic_session_id', $world['session']->id)
            ->where('status', 'active')
            ->first();
        $this->assertNotNull($record);
        $this->assertSame(0, InterventionPlan::query()->where('at_risk_learner_id', $record->id)->count());

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('at-risk.plans.create', $record))
            ->assertOk()
            ->assertSee('Raise Ben Two above the pass mark')
            ->assertSee('Auto-flagged from a verified marksheet');

        $this->actingAs($world['users']['head_of_department'])
            ->post(route('at-risk.plans.store', $record), [
                'plan_type' => AtRiskCriteria::TIER_2,
                'status' => 'active',
                'objectives' => 'Raise Ben Two above the pass mark in Mathematics.',
                'strategies' => 'Small-group practice twice a week.',
                'start_date' => now()->toDateString(),
                'review_date' => now()->addWeeks(4)->toDateString(),
            ])
            ->assertRedirect(route('at-risk.show', $record));

        $this->assertSame(1, InterventionPlan::query()->where('at_risk_learner_id', $record->id)->count());
        $summary = app(AtRiskCalculationService::class)->monthSummary($world['session'], $world['term']);
        $this->assertSame(1, $summary['identified']);
        $this->assertSame(1, $summary['with_plan']);
        $this->assertSame(0, $summary['without_plan']);
        $this->assertSame(1.0, $summary['rate']);
    }

    public function test_unverified_below_pass_does_not_enter_at_risk_register(): void
    {
        $world = $this->world();
        $this->scoreAll($world);

        $this->assertSame(0, AtRiskLearner::query()->where('academic_session_id', $world['session']->id)->count());

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Add intervention plan')
            ->assertDontSee('Not flagged');
    }

    public function test_rejected_marksheet_returns_to_teacher_feed(): void
    {
        $world = $this->world();
        $this->scoreAll($world);

        $this->actingAs($world['users']['head_of_department'])
            ->post(route('exam-results.reject'), [
                'assignment' => $this->assignmentKey($world),
                'rejection_reason' => 'Check Joy\'s script',
            ])
            ->assertRedirect();

        $this->actingAs($world['users']['teacher'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Revise marksheet')
            ->assertSee('Check Joy\'s script')
            ->assertDontSee('Marksheet awaiting HOD verification.');
    }
}
