<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\AtRiskLearner;
use App\Models\HomeworkLog;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\LeadershipWeekReview;
use App\Models\Learner;
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
use App\Services\AtRiskCalculationService;
use App\Services\LeadershipReviewFeed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadershipWeekReviewTest extends TestCase
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
        foreach (['board', 'head_of_school', 'assistant_head_secondary', 'head_of_department', 'teacher', 'admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $token = Str::lower(Str::random(8));
        $school = School::create(['name' => 'Lead School', 'slug' => 'lead-school-'.$token, 'status' => 'active']);
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
        foreach (['board', 'head_of_school', 'assistant_head_secondary', 'head_of_department', 'teacher'] as $role) {
            $user = User::create([
                'school_id' => $school->id,
                'name' => $role,
                'email' => $role.'.'.$token.'@lead.test',
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
            'learning_objectives' => ['Convert bases'],
            'display_order' => 1,
            'status' => 'planned',
        ]);

        $pillar = Pillar::create([
            'school_id' => $school->id,
            'code' => 'AE-'.$token,
            'name' => 'Academic Excellence',
            'status' => 'active',
        ]);
        $kpi = Kpi::create([
            'pillar_id' => $pillar->id,
            'code' => 'AE-01-'.$token,
            'name' => 'Curriculum Coverage Rate',
            'default_target' => 1,
            'frequency' => 'fortnightly',
            'status' => 'active',
        ]);
        $row = KpiPeriodicData::create([
            'kpi_id' => $kpi->id,
            'measure_key' => null,
            'academic_session_id' => $session->id,
            'term_id' => $term->id,
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'target_value' => 1,
            'actual_value' => 0.55,
            'period_start' => $session->start_date,
            'period_end' => $session->end_date,
        ]);

        return compact('school', 'session', 'term', 'class', 'subject', 'users', 'scheme', 'topic', 'kpi', 'row');
    }

    protected function submittedPlan(array $world): LessonPlan
    {
        return LessonPlan::create([
            'topic_id' => $world['topic']->id,
            'teacher_id' => $world['users']['teacher']->id,
            'school_class_id' => $world['class']->id,
            'subject_id' => $world['subject']->id,
            'objectives' => 'Convert bases',
            'activities' => 'Teach',
            'assessment' => 'Quiz',
            'status' => 'submitted',
            'submitted_at' => now(),
            'due_at' => now()->addDay(),
            'on_time' => true,
        ]);
    }

    public function test_hos_dashboard_shows_pending_hod_work_and_week_review_form(): void
    {
        $world = $this->world();
        $this->submittedPlan($world);
        HomeworkLog::create([
            'teacher_id' => $world['users']['teacher']->id,
            'school_class_id' => $world['class']->id,
            'subject_id' => $world['subject']->id,
            'academic_session_id' => $world['session']->id,
            'term_id' => $world['term']->id,
            'title' => 'Exercise 1',
            'given_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'given_count' => 10,
            'completed_on_time_count' => 8,
            'status' => 'submitted',
        ]);

        $this->actingAs($world['users']['head_of_school'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Operational exceptions')
            ->assertSee('Academic period')
            ->assertSee('Waiting on HOD')
            ->assertSee('Lesson plans awaiting HOD')
            ->assertSee('Homework awaiting HOD')
            ->assertSee('Record week review')
            ->assertSee('does not change AE KPI formulas');
    }

    public function test_board_sees_exceptions_but_cannot_record_week_review(): void
    {
        $world = $this->world();
        $this->submittedPlan($world);

        $this->actingAs($world['users']['board'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Lesson plans awaiting HOD')
            ->assertDontSee('Record week review')
            ->assertDontSee('Update week review');

        $this->actingAs($world['users']['board'])
            ->from(route('dashboard'))
            ->post(route('leadership-week-reviews.store'), [
                'session_id' => $world['session']->id,
                'term_id' => $world['term']->id,
                'week_number' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($world['users']['teacher'])
            ->post(route('leadership-week-reviews.store'), [
                'session_id' => $world['session']->id,
                'term_id' => $world['term']->id,
                'week_number' => 1,
            ])
            ->assertForbidden();
    }

    public function test_hos_records_week_review_without_changing_ae_kpis_or_hod_queues(): void
    {
        $world = $this->world();
        $plan = $this->submittedPlan($world);
        $learner = Learner::create([
            'school_id' => $world['school']->id,
            'school_class_id' => $world['class']->id,
            'name' => 'Ben Two',
            'admission_no' => 'L-'.$world['subject']->code,
            'status' => 'enrolled',
        ]);
        AtRiskLearner::create([
            'learner_id' => $learner->id,
            'school_class_id' => $world['class']->id,
            'academic_session_id' => $world['session']->id,
            'term_id' => $world['term']->id,
            'identified_by' => $world['users']['head_of_department']->id,
            'identification_date' => now()->toDateString(),
            'risk_factors' => ['below_pass_mark'],
            'concern_note' => 'Failed Mathematics',
            'risk_level' => 'medium',
            'status' => 'active',
        ]);

        $beforeAe07 = app(AtRiskCalculationService::class)->monthSummary($world['session'], $world['term'], null, false);
        $beforeActual = (float) $world['row']->fresh()->actual_value;

        $this->actingAs($world['users']['head_of_school'])
            ->from(route('dashboard'))
            ->post(route('leadership-week-reviews.store'), [
                'session_id' => $world['session']->id,
                'term_id' => $world['term']->id,
                'week_number' => 99,
                'notes' => 'Seen HOD backlog.',
            ])
            ->assertRedirect();

        $review = LeadershipWeekReview::query()
            ->where('school_id', $world['school']->id)
            ->where('term_id', $world['term']->id)
            ->first();
        $this->assertNotNull($review);
        $this->assertSame($world['users']['head_of_school']->id, $review->reviewed_by);
        $this->assertSame('Seen HOD backlog.', $review->notes);
        $this->assertNotSame(99, $review->week_number);
        $this->assertSame(1, $review->snapshot['plans'] ?? null);
        $this->assertSame(1, $review->snapshot['at_risk_without_plan'] ?? null);

        $this->assertSame('submitted', $plan->fresh()->status);
        $this->assertEquals($beforeActual, (float) $world['row']->fresh()->actual_value);
        $afterAe07 = app(AtRiskCalculationService::class)->monthSummary($world['session'], $world['term'], null, false);
        $this->assertSame($beforeAe07['identified'], $afterAe07['identified']);
        $this->assertSame($beforeAe07['without_plan'], $afterAe07['without_plan']);
        $this->assertSame($beforeAe07['rate'], $afterAe07['rate']);

        $this->actingAs($world['users']['head_of_school'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Update week review')
            ->assertSee('Seen HOD backlog.')
            ->assertSee('Identified learners without a plan');
    }

    public function test_assistant_head_can_update_week_review(): void
    {
        $world = $this->world();
        $feed = app(LeadershipReviewFeed::class)->compose($world['users']['head_of_school'], $world['session'], $world['term']);

        $this->actingAs($world['users']['assistant_head_secondary'])
            ->from(route('dashboard'))
            ->post(route('leadership-week-reviews.store'), [
                'session_id' => $world['session']->id,
                'term_id' => $world['term']->id,
                'week_number' => $feed['week_number'],
                'notes' => 'AH sign-off.',
            ])
            ->assertRedirect();

        $review = LeadershipWeekReview::query()->where('school_id', $world['school']->id)->first();
        $this->assertSame(1, LeadershipWeekReview::query()->where('school_id', $world['school']->id)->count());
        $this->assertSame('AH sign-off.', $review->notes);
        $this->assertSame($world['users']['assistant_head_secondary']->id, $review->reviewed_by);
    }
}
