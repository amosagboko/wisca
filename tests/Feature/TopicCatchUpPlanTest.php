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
use App\Models\TopicCatchUpPlan;
use App\Models\TopicCoverageLog;
use App\Models\User;
use App\Services\CurriculumCoverageKpiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TopicCatchUpPlanTest extends TestCase
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
        $school = School::create(['name' => 'Catch-up School', 'slug' => 'catch-up-'.$token, 'status' => 'active']);
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
                'email' => $role.'.'.$token.'@catchup.test',
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

        return compact('school', 'session', 'term', 'class', 'subject', 'users', 'scheme', 'topic');
    }

    public function test_teacher_cannot_open_catch_up(): void
    {
        $world = $this->world();

        $this->actingAs($world['users']['teacher'])
            ->from(route('curriculum-coverage.report'))
            ->post(route('catch-ups.store'), ['topic_id' => $world['topic']->id])
            ->assertForbidden();

        $this->assertSame(0, TopicCatchUpPlan::query()->count());
    }

    public function test_hod_opens_catch_up_and_verify_addresses_it_without_changing_executive_ae01(): void
    {
        $world = $this->world();
        $topic = $world['topic'];
        $hod = $world['users']['head_of_department'];

        $this->actingAs($hod)
            ->from(route('curriculum-coverage.report'))
            ->post(route('catch-ups.store'), ['topic_id' => $topic->id])
            ->assertRedirect();

        $plan = TopicCatchUpPlan::query()->where('topic_id', $topic->id)->first();
        $this->assertNotNull($plan);
        $this->assertSame(TopicCatchUpPlan::STATUS_OPEN, $plan->status);

        $measure = KpiPeriodicData::query()
            ->where('measure_key', CurriculumCoverageKpiService::AE01_4)
            ->where('school_class_id', $world['class']->id)
            ->where('subject_id', $world['subject']->id)
            ->first();
        $this->assertNotNull($measure);
        $this->assertEquals(0.0, (float) $measure->actual_value);
        $this->assertSame(1, $measure->metadata['identified']);
        $this->assertSame(0, $measure->metadata['addressed']);

        $this->assertSame(0, KpiPeriodicData::query()->whereNull('measure_key')->where('school_class_id', $world['class']->id)->count());

        $this->actingAs($hod)
            ->from(route('curriculum-coverage.report'))
            ->post(route('catch-ups.store'), ['topic_id' => $topic->id])
            ->assertRedirect()
            ->assertSessionHasErrors('topic_id');

        $approvedPlan = LessonPlan::create([
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
            'approved_by' => $hod->id,
            'approved_at' => now(),
        ]);

        $log = TopicCoverageLog::create([
            'topic_id' => $topic->id,
            'teacher_id' => $world['users']['teacher']->id,
            'school_class_id' => $world['class']->id,
            'subject_id' => $world['subject']->id,
            'lesson_plan_id' => $approvedPlan->id,
            'coverage_date' => now()->toDateString(),
            'workbook_reference' => 'p.1-2',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->actingAs($hod)
            ->post(route('coverage-logs.verify', $log))
            ->assertRedirect();

        $this->assertSame(TopicCatchUpPlan::STATUS_ADDRESSED, $plan->fresh()->status);
        $this->assertSame($log->id, $plan->fresh()->coverage_log_id);

        $measure = $measure->fresh();
        $this->assertEquals(1.0, (float) $measure->actual_value);
        $this->assertSame(1, $measure->metadata['addressed']);

        $executive = KpiPeriodicData::query()
            ->whereNull('measure_key')
            ->where('school_class_id', $world['class']->id)
            ->where('subject_id', $world['subject']->id)
            ->first();
        $this->assertNotNull($executive);
        $this->assertEquals(1.0, (float) $executive->actual_value);
        $this->assertNotEquals($executive->id, $measure->id);
    }

    public function test_hod_cannot_open_catch_up_for_already_verified_topic(): void
    {
        $world = $this->world();
        $topic = $world['topic'];
        $hod = $world['users']['head_of_department'];

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
            'approved_by' => $hod->id,
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
            'status' => 'verified',
            'verified_by' => $hod->id,
            'verified_at' => now(),
            'submitted_at' => now(),
        ]);
        $topic->update(['status' => 'covered']);

        $this->actingAs($hod)
            ->from(route('curriculum-coverage.report'))
            ->post(route('catch-ups.store'), ['topic_id' => $topic->id])
            ->assertRedirect()
            ->assertSessionHasErrors('topic_id');

        $this->assertSame(0, TopicCatchUpPlan::query()->count());
        $this->assertSame('verified', $log->fresh()->status);
    }

    public function test_cancelled_catch_up_is_excluded_from_ae01_4_and_can_be_reopened(): void
    {
        $world = $this->world();
        $topic = $world['topic'];
        $hod = $world['users']['head_of_department'];

        $this->actingAs($hod)
            ->post(route('catch-ups.store'), ['topic_id' => $topic->id])
            ->assertRedirect();

        $plan = TopicCatchUpPlan::query()->where('topic_id', $topic->id)->first();

        $this->actingAs($hod)
            ->from(route('curriculum-coverage.report'))
            ->post(route('catch-ups.cancel', $plan))
            ->assertRedirect();

        $this->assertSame(TopicCatchUpPlan::STATUS_CANCELLED, $plan->fresh()->status);
        $this->assertSame(0, KpiPeriodicData::query()
            ->where('measure_key', CurriculumCoverageKpiService::AE01_4)
            ->where('school_class_id', $world['class']->id)
            ->count());

        $this->actingAs($hod)
            ->post(route('catch-ups.store'), ['topic_id' => $topic->id])
            ->assertRedirect();

        $this->assertSame(TopicCatchUpPlan::STATUS_OPEN, $plan->fresh()->status);
        $this->assertSame(1, TopicCatchUpPlan::query()->where('topic_id', $topic->id)->count());
    }

    public function test_hod_dashboard_lists_behind_topic_and_opening_does_not_change_executive_ae01(): void
    {
        $world = $this->shiftIntoWeekTwo($this->world());
        Topic::create([
            'scheme_of_work_id' => $world['scheme']->id,
            'week_number' => 2,
            'title' => 'This Week Algebra',
            'learning_objectives' => ['Solve linear equations'],
            'display_order' => 2,
            'status' => 'planned',
        ]);

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Catch-up needed')
            ->assertSee('Number Bases')
            ->assertSee('Open catch-up')
            ->assertDontSee('This Week Algebra');

        $this->actingAs($world['users']['head_of_school'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Behind topics without catch-up');

        $this->actingAs($world['users']['head_of_department'])
            ->from(route('dashboard'))
            ->post(route('catch-ups.store'), ['topic_id' => $world['topic']->id])
            ->assertRedirect();

        $this->assertSame(TopicCatchUpPlan::STATUS_OPEN, TopicCatchUpPlan::query()->where('topic_id', $world['topic']->id)->value('status'));
        $this->assertSame(0, KpiPeriodicData::query()->whereNull('measure_key')->where('school_class_id', $world['class']->id)->count());

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('id="catch-up-needed-'.$world['topic']->id.'"', false);
    }

    /**
     * @param  array<string, mixed>  $world
     * @return array<string, mixed>
     */
    protected function shiftIntoWeekTwo(array $world): array
    {
        $start = now()->startOfWeek(Carbon::MONDAY)->subWeek()->toDateString();
        $world['session']->update(['start_date' => $start]);
        $world['term']->update(['start_date' => $start]);
        $world['session']->refresh();
        $world['term']->refresh();

        return $world;
    }
}
