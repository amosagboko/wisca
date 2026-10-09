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
use App\Models\TopicCoverageLog;
use App\Models\User;
use App\Services\CurriculumCoverageKpiService;
use App\Services\LessonPlanCalculationService;
use App\Services\PlanningPolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CurriculumCoverageP2Test extends TestCase
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
        $school = School::create(['name' => 'P2 School', 'slug' => 'p2-school-'.$token, 'status' => 'active']);
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
                'email' => $role.'.'.$token.'@p2.test',
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

    public function test_teacher_cannot_submit_unapproved_learning_objectives(): void
    {
        $world = $this->world();

        $this->actingAs($world['users']['teacher'])
            ->from(route('lesson-plans.create', ['topic' => $world['topic']->id]))
            ->post(route('lesson-plans.store'), [
                'topic_id' => $world['topic']->id,
                'objectives' => ['Invented curriculum'],
                'activities' => 'Worked examples',
                'assessment' => 'Exit ticket',
            ])
            ->assertRedirect(route('lesson-plans.create', ['topic' => $world['topic']->id]))
            ->assertSessionHasErrors('objectives');
    }

    public function test_hos_cannot_verify_coverage_and_hod_verification_feeds_proxy(): void
    {
        $world = $this->world();
        $topic = $world['topic'];

        $plan = LessonPlan::create([
            'topic_id' => $topic->id,
            'teacher_id' => $world['users']['teacher']->id,
            'school_class_id' => $world['class']->id,
            'subject_id' => $world['subject']->id,
            'objectives' => 'Convert bases',
            'activities' => 'Teach',
            'assessment' => 'Quiz',
            'status' => 'approved',
            'submitted_at' => now(),
            'due_at' => now()->addDay(),
            'on_time' => true,
            'approved_by' => $world['users']['head_of_department']->id,
            'approved_at' => now(),
        ]);

        $log = TopicCoverageLog::create([
            'topic_id' => $topic->id,
            'teacher_id' => $world['users']['teacher']->id,
            'school_class_id' => $world['class']->id,
            'subject_id' => $world['subject']->id,
            'lesson_plan_id' => $plan->id,
            'coverage_date' => now()->toDateString(),
            'workbook_reference' => 'p.1-2',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->actingAs($world['users']['head_of_school'])
            ->post(route('coverage-logs.verify', $log))
            ->assertForbidden();

        $this->actingAs($world['users']['head_of_department'])
            ->post(route('coverage-logs.verify', $log))
            ->assertRedirect();

        $this->assertSame('verified', $log->fresh()->status);
        $this->assertSame('covered', $topic->fresh()->status);

        $proxy = KpiPeriodicData::query()
            ->where('measure_key', CurriculumCoverageKpiService::AE01_2_PROXY)
            ->where('school_class_id', $world['class']->id)
            ->where('subject_id', $world['subject']->id)
            ->first();
        $this->assertNotNull($proxy);
        $this->assertSame('proxy', $proxy->metadata['calculation_method']);
        $this->assertEquals(1.0, (float) $proxy->actual_value);

        $weekly = KpiPeriodicData::query()
            ->where('measure_key', CurriculumCoverageKpiService::AE01_3_WEEKLY)
            ->where('school_class_id', $world['class']->id)
            ->first();
        $this->assertEquals(1.0, (float) $weekly->actual_value);

        $executive = KpiPeriodicData::query()
            ->whereNull('measure_key')
            ->where('school_class_id', $world['class']->id)
            ->where('subject_id', $world['subject']->id)
            ->first();
        $this->assertNotNull($executive);
        $this->assertEquals(1.0, (float) $executive->actual_value);
        $this->assertNotEquals($executive->id, $proxy->id);
    }

    public function test_closed_term_rejects_new_lesson_plans(): void
    {
        $world = $this->world();
        $world['term']->update(['status' => 'closed']);

        $this->actingAs($world['users']['teacher'])
            ->from(route('lesson-plans.create', ['topic' => $world['topic']->id]))
            ->post(route('lesson-plans.store'), [
                'topic_id' => $world['topic']->id,
                'objectives' => ['Convert bases'],
                'activities' => 'Worked examples',
                'assessment' => 'Exit ticket',
            ])
            ->assertSessionHasErrors('term_id');

        $this->assertSame(0, LessonPlan::where('topic_id', $world['topic']->id)->count());
    }

    public function test_admin_can_set_thursday_policy_and_teacher_is_forbidden(): void
    {
        $world = $this->world();

        $this->actingAs($world['users']['teacher'])
            ->put(route('planning-policy.update'), ['lesson_plan_due_weekday' => Carbon::MONDAY])
            ->assertForbidden();

        $this->actingAs($world['users']['admin'])
            ->put(route('planning-policy.update'), ['lesson_plan_due_weekday' => Carbon::THURSDAY])
            ->assertRedirect();

        $this->assertSame(Carbon::THURSDAY, app(PlanningPolicy::class)->lessonPlanDueWeekday($world['school']->fresh()));
    }

    public function test_ae05_uses_planning_policy_weekday_not_monday_helper(): void
    {
        $world = $this->world();
        $calculator = app(LessonPlanCalculationService::class);
        $due = $calculator->dueAtForTopic($world['topic']);

        $this->assertTrue($due->isThursday());
        $this->assertTrue($world['term']->lessonPlanDueAt(1)->isMonday());

        LessonPlan::create([
            'topic_id' => $world['topic']->id,
            'teacher_id' => $world['users']['teacher']->id,
            'school_class_id' => $world['class']->id,
            'subject_id' => $world['subject']->id,
            'objectives' => 'Convert bases',
            'activities' => 'Teach',
            'assessment' => 'Quiz',
            'status' => 'approved',
            'submitted_at' => $due->copy()->subDay(),
            'due_at' => $due,
            'on_time' => true,
            'approved_by' => $world['users']['head_of_department']->id,
            'approved_at' => $due->copy()->subDay(),
        ]);

        $row = $calculator->recalculateForSession($world['session'], $world['term']);
        $this->assertNotNull($row);
        $this->assertSame($world['term']->instructionalWeekStart(1)->toDateString(), $row->period_start->toDateString());
        $this->assertSame($world['term']->instructionalWeekEnd(1)->toDateString(), $row->period_end->toDateString());
        $this->assertSame('Thursday', $row->metadata['due_weekday']);
        $this->assertEquals(1.0, (float) $row->actual_value);

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('lesson-plans.index'))
            ->assertOk()
            ->assertSee('before Thursday')
            ->assertDontSee('before Monday');

        app(PlanningPolicy::class)->putLessonPlanDueWeekday($world['school']->fresh(), Carbon::FRIDAY);
        $this->assertTrue($calculator->dueAtForWeek($world['term'], 1, $world['school']->fresh())->isFriday());

        $after = $calculator->recalculateForSession($world['session']->fresh(), $world['term']->fresh());
        $this->assertSame('Friday', $after->metadata['due_weekday']);
        $this->assertEquals(1.0, (float) $after->actual_value);
        $this->assertTrue($world['term']->fresh()->lessonPlanDueAt(1)->isMonday());
    }

    public function test_hod_plan_card_uses_stored_due_day_not_monday_copy(): void
    {
        $world = $this->world();
        $due = app(LessonPlanCalculationService::class)->dueAtForTopic($world['topic']);

        LessonPlan::create([
            'topic_id' => $world['topic']->id,
            'teacher_id' => $world['users']['teacher']->id,
            'school_class_id' => $world['class']->id,
            'subject_id' => $world['subject']->id,
            'objectives' => 'Convert bases',
            'activities' => 'Teach',
            'assessment' => 'Quiz',
            'status' => 'submitted',
            'submitted_at' => $due->copy()->addDay(),
            'due_at' => $due,
            'on_time' => false,
        ]);

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Submitted after the Thursday deadline')
            ->assertDontSee('Monday deadline');
    }

    public function test_approving_sla_overdue_plan_still_uses_teacher_on_time_for_ae05(): void
    {
        $world = $this->world();
        $calculator = app(LessonPlanCalculationService::class);
        $due = $calculator->dueAtForTopic($world['topic']);

        $plan = LessonPlan::create([
            'topic_id' => $world['topic']->id,
            'teacher_id' => $world['users']['teacher']->id,
            'school_class_id' => $world['class']->id,
            'subject_id' => $world['subject']->id,
            'objectives' => 'Convert bases',
            'activities' => 'Teach',
            'assessment' => 'Quiz',
            'status' => 'submitted',
            'submitted_at' => now()->subHours(25),
            'due_at' => $due,
            'on_time' => true,
        ]);

        $before = $calculator->recalculateForSession($world['session'], $world['term']);
        $this->assertEquals(0.0, (float) $before->actual_value);

        $this->actingAs($world['users']['head_of_department'])
            ->from(route('dashboard'))
            ->post(route('lesson-plans.approve', $plan), [
                'checklist' => [
                    'alignment' => '1',
                    'quality' => '1',
                    'engagement' => '1',
                    'assessment' => '1',
                ],
            ])
            ->assertRedirect();

        $after = $calculator->recalculateForSession($world['session']->fresh(), $world['term']->fresh());
        $this->assertEquals(1.0, (float) $after->actual_value);
        $this->assertTrue($plan->fresh()->on_time);
        $this->assertSame('approved', $plan->fresh()->status);
    }
}
