<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\LessonPlan;
use App\Models\Pillar;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchemeOfWork;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\Topic;
use App\Models\User;
use App\Services\CurriculumCoverageKpiService;
use App\Services\LessonPlanCalculationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LessonPlanReviewTest extends TestCase
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
        $school = School::create(['name' => 'Checklist School', 'slug' => 'checklist-school-'.$token, 'status' => 'active']);
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
        foreach (['admin', 'head_of_school', 'head_of_department', 'teacher'] as $role) {
            $user = User::create([
                'school_id' => $school->id,
                'name' => $role,
                'email' => $role.'.'.$token.'@checklist.test',
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

        $scheme = SchemeOfWork::create([
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'academic_session_id' => $session->id,
            'term_id' => $term->id,
            'version' => 1,
            'uploaded_by' => $users['head_of_department']->id,
            'status' => 'active',
        ]);
        $topic = Topic::create([
            'scheme_of_work_id' => $scheme->id,
            'week_number' => 1,
            'title' => 'Number Bases',
            'learning_objectives' => ['Convert bases', 'Apply place value'],
            'display_order' => 1,
            'status' => 'planned',
        ]);

        $pillar = Pillar::firstOrCreate(
            ['code' => 'AE'],
            ['school_id' => $school->id, 'name' => 'Academic Excellence', 'status' => 'active']
        );
        Kpi::firstOrCreate(
            ['code' => 'AE-01'],
            [
                'pillar_id' => $pillar->id,
                'name' => 'Curriculum Coverage Rate',
                'default_target' => 1,
                'frequency' => 'fortnightly',
                'status' => 'active',
            ]
        );
        Kpi::firstOrCreate(
            ['code' => 'AE-05'],
            [
                'pillar_id' => $pillar->id,
                'name' => 'Lesson Plan Submission',
                'default_target' => 0.95,
                'frequency' => 'weekly',
                'status' => 'active',
            ]
        );

        return compact('school', 'session', 'term', 'class', 'subject', 'users', 'scheme', 'topic');
    }

    /**
     * @return array{checklist: array<string, string>}
     */
    protected function checklist(bool $allPass = true, bool $engagementPass = true): array
    {
        return [
            'checklist' => [
                'alignment' => '1',
                'quality' => '1',
                'engagement' => $engagementPass ? '1' : '0',
                'assessment' => $allPass ? '1' : '0',
            ],
        ];
    }

    protected function submittedPlan(array $world, array $overrides = []): LessonPlan
    {
        return LessonPlan::create(array_merge([
            'topic_id' => $world['topic']->id,
            'teacher_id' => $world['users']['teacher']->id,
            'school_class_id' => $world['class']->id,
            'subject_id' => $world['subject']->id,
            'objectives' => 'Convert bases',
            'activities' => 'Teach',
            'assessment' => 'Quiz',
            'status' => 'submitted',
            'submitted_at' => now()->subHour(),
            'due_at' => now()->addDay(),
            'on_time' => true,
        ], $overrides));
    }

    public function test_hod_cannot_approve_without_complete_pass_checklist(): void
    {
        $world = $this->world();
        $plan = $this->submittedPlan($world);

        $this->actingAs($world['users']['head_of_department'])
            ->from(route('dashboard'))
            ->post(route('lesson-plans.approve', $plan))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('checklist.alignment');

        $this->actingAs($world['users']['head_of_department'])
            ->from(route('dashboard'))
            ->post(route('lesson-plans.approve', $plan), $this->checklist(engagementPass: false))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('checklist');

        $this->assertSame('submitted', $plan->fresh()->status);
        $this->assertNull($plan->fresh()->review_checklist);
    }

    public function test_hod_approve_stores_complete_checklist(): void
    {
        $world = $this->world();
        $plan = $this->submittedPlan($world);

        $this->actingAs($world['users']['head_of_department'])
            ->from(route('dashboard'))
            ->post(route('lesson-plans.approve', $plan), $this->checklist())
            ->assertRedirect(route('dashboard'));

        $plan = $plan->fresh();
        $this->assertSame('approved', $plan->status);
        $this->assertTrue($plan->review_checklist['items']['alignment']['passed']);
        $this->assertTrue($plan->review_checklist['items']['quality']['passed']);
        $this->assertTrue($plan->review_checklist['items']['engagement']['passed']);
        $this->assertTrue($plan->review_checklist['items']['assessment']['passed']);
        $this->assertSame($world['users']['head_of_department']->id, $plan->review_checklist['reviewed_by']);

        $reviewed = KpiPeriodicData::query()
            ->where('measure_key', CurriculumCoverageKpiService::AE05_2)
            ->where('academic_session_id', $world['session']->id)
            ->where('school_class_id', $world['class']->id)
            ->first();
        $this->assertNotNull($reviewed);
        $this->assertEquals(1.0, (float) $reviewed->actual_value);
    }

    public function test_hod_reject_requires_checklist_and_reason(): void
    {
        $world = $this->world();
        $plan = $this->submittedPlan($world);

        $this->actingAs($world['users']['head_of_department'])
            ->from(route('dashboard'))
            ->post(route('lesson-plans.reject', $plan), $this->checklist(engagementPass: false))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('rejection_reason');

        $this->actingAs($world['users']['head_of_department'])
            ->from(route('dashboard'))
            ->post(route('lesson-plans.reject', $plan), array_merge($this->checklist(engagementPass: false), [
                'rejection_reason' => 'Add learner tasks.',
            ]))
            ->assertRedirect(route('dashboard'));

        $plan = $plan->fresh();
        $this->assertSame('rejected', $plan->status);
        $this->assertSame('Add learner tasks.', $plan->rejection_reason);
        $this->assertFalse($plan->review_checklist['items']['engagement']['passed']);
    }

    public function test_teacher_resubmit_clears_checklist(): void
    {
        $world = $this->world();
        $plan = $this->submittedPlan($world, [
            'status' => 'rejected',
            'rejection_reason' => 'Add learner tasks.',
            'approved_by' => $world['users']['head_of_department']->id,
            'approved_at' => now(),
            'review_checklist' => [
                'items' => [
                    'alignment' => ['label' => 'a', 'passed' => true],
                    'quality' => ['label' => 'q', 'passed' => true],
                    'engagement' => ['label' => 'e', 'passed' => false],
                    'assessment' => ['label' => 's', 'passed' => true],
                ],
                'reviewed_by' => $world['users']['head_of_department']->id,
                'reviewed_at' => now()->toIso8601String(),
            ],
        ]);

        $this->actingAs($world['users']['teacher'])
            ->put(route('lesson-plans.update', $plan), [
                'topic_id' => $world['topic']->id,
                'objectives' => ['Convert bases'],
                'activities' => 'Pair work',
                'assessment' => 'Exit ticket',
            ])
            ->assertRedirect(route('dashboard'));

        $plan = $plan->fresh();
        $this->assertSame('submitted', $plan->status);
        $this->assertNull($plan->review_checklist);
        $this->assertNull($plan->rejection_reason);
        $this->assertNull($plan->approved_by);
    }

    public function test_ae052_counts_only_checklist_complete_decisions(): void
    {
        $world = $this->world();
        $this->submittedPlan($world, [
            'status' => 'approved',
            'approved_by' => $world['users']['head_of_department']->id,
            'approved_at' => now(),
            'review_checklist' => null,
        ]);

        app(CurriculumCoverageKpiService::class)->recalculateLessonPlanSubmeasures($world['session'], $world['term'], 1);

        $row = KpiPeriodicData::query()
            ->where('measure_key', CurriculumCoverageKpiService::AE05_2)
            ->where('academic_session_id', $world['session']->id)
            ->where('school_class_id', $world['class']->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertEquals(0.0, (float) $row->actual_value);
    }

    public function test_ae053_counts_return_within_24h_and_executive_ae05_stays_approved_on_time(): void
    {
        $world = $this->world();
        $plan = $this->submittedPlan($world, [
            'submitted_at' => now()->subHours(2),
        ]);

        $this->actingAs($world['users']['head_of_department'])
            ->from(route('dashboard'))
            ->post(route('lesson-plans.reject', $plan), array_merge($this->checklist(engagementPass: false), [
                'rejection_reason' => 'Add learner tasks.',
            ]))
            ->assertRedirect(route('dashboard'));

        $kpiRow = KpiPeriodicData::query()
            ->where('measure_key', CurriculumCoverageKpiService::AE05_3)
            ->where('academic_session_id', $world['session']->id)
            ->where('school_class_id', $world['class']->id)
            ->first();
        $this->assertNotNull($kpiRow);
        $this->assertEquals(1.0, (float) $kpiRow->actual_value);

        $executive = app(LessonPlanCalculationService::class)->recalculateForSession($world['session']->fresh(), $world['term']->fresh());
        $this->assertNotNull($executive);
        $this->assertNull($executive->measure_key);
        $this->assertEquals(0.0, (float) $executive->actual_value);
    }

    public function test_index_and_dashboard_show_quality_checklist(): void
    {
        $world = $this->world();
        $plan = $this->submittedPlan($world);

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Quality checklist (AE-05.2)')
            ->assertSee('Curriculum is aligned to the Active Scheme of Work topic');

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('lesson-plans.index'))
            ->assertOk()
            ->assertSee('Quality checklist (AE-05.2)')
            ->assertSee(route('lesson-plans.approve', $plan), false);
    }
}
