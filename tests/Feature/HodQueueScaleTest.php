<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\AtRiskLearner;
use App\Models\Department;
use App\Models\Learner;
use App\Models\LessonPlan;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchemeOfWork;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\Topic;
use App\Models\User;
use App\Services\CurriculumDashboardService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HodQueueScaleTest extends TestCase
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
        $school = School::create(['name' => 'Scale School', 'slug' => 'scale-school-'.$token, 'status' => 'active']);
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
            'end_date' => now()->addMonths(3)->toDateString(),
            'sequence' => 1,
            'is_current' => true,
            'status' => 'active',
        ]);
        $termTwo = Term::create([
            'academic_session_id' => $session->id,
            'name' => 'Second Term',
            'start_date' => now()->addMonths(4)->toDateString(),
            'end_date' => $session->end_date,
            'sequence' => 2,
            'is_current' => false,
            'status' => 'active',
        ]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'JSS 1A', 'level' => 'JSS', 'status' => 'active']);

        $sciences = Department::create(['school_id' => $school->id, 'name' => 'Sciences '.$token, 'status' => 'active']);
        $humanities = Department::create(['school_id' => $school->id, 'name' => 'Humanities '.$token, 'status' => 'active']);

        $physics = Subject::create(['school_id' => $school->id, 'department_id' => $sciences->id, 'name' => 'Physics', 'code' => 'PHY'.$token, 'status' => 'active']);
        $english = Subject::create(['school_id' => $school->id, 'department_id' => $humanities->id, 'name' => 'English', 'code' => 'ENG'.$token, 'status' => 'active']);
        $class->offeredSubjects()->syncWithoutDetaching([$physics->id, $english->id]);

        $scienceHod = User::create([
            'school_id' => $school->id,
            'department_id' => $sciences->id,
            'name' => 'Science HOD',
            'email' => 'sci.hod.'.$token.'@scale.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $scienceHod->assignRole('head_of_department');

        $humanitiesHod = User::create([
            'school_id' => $school->id,
            'department_id' => $humanities->id,
            'name' => 'Humanities HOD',
            'email' => 'hum.hod.'.$token.'@scale.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $humanitiesHod->assignRole('head_of_department');

        $hos = User::create([
            'school_id' => $school->id,
            'name' => 'Head of School',
            'email' => 'hos.'.$token.'@scale.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $hos->assignRole('head_of_school');

        $teacher = User::create([
            'school_id' => $school->id,
            'name' => 'Teacher',
            'email' => 'teacher.'.$token.'@scale.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');

        TeacherAssignment::create([
            'teacher_id' => $teacher->id,
            'school_class_id' => $class->id,
            'subject_id' => $physics->id,
            'academic_session_id' => $session->id,
            'status' => 'active',
        ]);
        TeacherAssignment::create([
            'teacher_id' => $teacher->id,
            'school_class_id' => $class->id,
            'subject_id' => $english->id,
            'academic_session_id' => $session->id,
            'status' => 'active',
        ]);

        $physicsScheme = $this->scheme($physics, $class, $session, $term, $scienceHod, 'Forces');
        $englishScheme = $this->scheme($english, $class, $session, $term, $humanitiesHod, 'Comprehension');
        $termTwoScheme = $this->scheme($physics, $class, $session, $termTwo, $scienceHod, 'Waves next term');

        $physicsPlan = $this->plan($physicsScheme['topic'], $teacher, $class, $physics);
        $englishPlan = $this->plan($englishScheme['topic'], $teacher, $class, $english);
        $termTwoPlan = $this->plan($termTwoScheme['topic'], $teacher, $class, $physics);

        return compact(
            'school', 'session', 'term', 'termTwo', 'class', 'sciences', 'humanities',
            'physics', 'english', 'scienceHod', 'humanitiesHod', 'hos', 'teacher',
            'physicsPlan', 'englishPlan', 'termTwoPlan'
        );
    }

    /**
     * @return array{scheme: SchemeOfWork, topic: Topic}
     */
    protected function scheme(Subject $subject, SchoolClass $class, AcademicSession $session, Term $term, User $hod, string $title): array
    {
        $scheme = SchemeOfWork::create([
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'academic_session_id' => $session->id,
            'term_id' => $term->id,
            'version' => 1,
            'uploaded_by' => $hod->id,
            'status' => 'active',
        ]);
        $topic = Topic::create([
            'scheme_of_work_id' => $scheme->id,
            'week_number' => 1,
            'title' => $title,
            'learning_objectives' => ['Learn'],
            'display_order' => 1,
            'status' => 'planned',
        ]);

        return compact('scheme', 'topic');
    }

    /**
     * @return array{checklist: array<string, string>}
     */
    protected function passingChecklist(): array
    {
        return [
            'checklist' => [
                'alignment' => '1',
                'quality' => '1',
                'engagement' => '1',
                'assessment' => '1',
            ],
        ];
    }

    protected function plan(Topic $topic, User $teacher, SchoolClass $class, Subject $subject): LessonPlan
    {
        return LessonPlan::create([
            'topic_id' => $topic->id,
            'teacher_id' => $teacher->id,
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'objectives' => 'Learn',
            'activities' => 'Teach',
            'assessment' => 'Quiz',
            'status' => 'submitted',
            'submitted_at' => now(),
            'due_at' => now()->addDay(),
            'on_time' => true,
        ]);
    }

    public function test_science_hod_cannot_approve_humanities_plan(): void
    {
        $world = $this->world();

        $this->actingAs($world['scienceHod'])
            ->post(route('lesson-plans.approve', $world['englishPlan']))
            ->assertForbidden();

        $this->actingAs($world['scienceHod'])
            ->post(route('lesson-plans.approve', $world['physicsPlan']), $this->passingChecklist())
            ->assertRedirect();

        $this->assertSame('approved', $world['physicsPlan']->fresh()->status);
        $this->assertSame('submitted', $world['englishPlan']->fresh()->status);
    }

    public function test_hod_cannot_approve_plan_from_another_school(): void
    {
        $world = $this->world();
        $token = Str::lower(Str::random(8));
        $other = School::create(['name' => 'Other', 'slug' => 'other-'.$token, 'status' => 'active']);
        $session = AcademicSession::create([
            'school_id' => $other->id,
            'name' => '2025/2026',
            'start_date' => now()->toDateString(),
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
        $class = SchoolClass::create(['school_id' => $other->id, 'name' => 'SS1', 'level' => 'SS', 'status' => 'active']);
        $subject = Subject::create(['school_id' => $other->id, 'name' => 'Biology', 'code' => 'BIO'.$token, 'status' => 'active']);
        $scheme = $this->scheme($subject, $class, $session, $term, $world['scienceHod'], 'Cells');
        $plan = $this->plan($scheme['topic'], $world['teacher'], $class, $subject);

        $this->actingAs($world['scienceHod'])
            ->post(route('lesson-plans.approve', $plan))
            ->assertForbidden();
    }

    public function test_hos_can_approve_any_plan_in_school(): void
    {
        $world = $this->world();

        $this->actingAs($world['hos'])
            ->post(route('lesson-plans.approve', $world['englishPlan']), $this->passingChecklist())
            ->assertRedirect();

        $this->assertSame('approved', $world['englishPlan']->fresh()->status);
    }

    public function test_dashboard_scopes_department_session_and_term(): void
    {
        $world = $this->world();

        $this->actingAs($world['scienceHod'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Forces')
            ->assertDontSee('Comprehension')
            ->assertDontSee('Waves next term')
            ->assertSee('Group by');

        $this->actingAs($world['scienceHod'])
            ->get(route('dashboard', ['term_id' => $world['termTwo']->id]))
            ->assertOk()
            ->assertSee('Waves next term')
            ->assertDontSee('Forces');

        $this->actingAs($world['humanitiesHod'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Comprehension')
            ->assertDontSee('Forces');
    }

    public function test_pending_plans_are_paginated_and_exam_sittings_are_lightweight(): void
    {
        $world = $this->world();

        $data = app(CurriculumDashboardService::class)->hodOperations($world['scienceHod'], $world['session'], [
            'term_id' => $world['term']->id,
        ]);

        $this->assertInstanceOf(LengthAwarePaginator::class, $data['pending_plans']);
        $this->assertInstanceOf(LengthAwarePaginator::class, $data['pending']);
        $this->assertSame(1, $data['pending_plan_total']);
        $this->assertTrue($data['exam_sittings']->isNotEmpty());
        $this->assertArrayNotHasKey('learners', $data['exam_sittings']->first());
        $this->assertArrayHasKey('review_status', $data['exam_sittings']->first());
    }

    public function test_science_hod_does_not_see_humanities_class_at_risk(): void
    {
        $world = $this->world();
        $ss2 = SchoolClass::create([
            'school_id' => $world['school']->id,
            'name' => 'SS 2A',
            'level' => 'SS',
            'status' => 'active',
        ]);
        $ss2->offeredSubjects()->syncWithoutDetaching([$world['english']->id]);
        $learner = Learner::create([
            'school_id' => $world['school']->id,
            'school_class_id' => $ss2->id,
            'name' => 'Ngozi English',
            'admission_no' => 'SC-'.$world['english']->code,
            'status' => 'enrolled',
        ]);
        AtRiskLearner::create([
            'learner_id' => $learner->id,
            'school_class_id' => $ss2->id,
            'academic_session_id' => $world['session']->id,
            'term_id' => $world['term']->id,
            'identified_by' => $world['humanitiesHod']->id,
            'identification_date' => now()->toDateString(),
            'risk_factors' => ['below_pass_mark'],
            'concern_note' => 'Failed English',
            'risk_level' => 'medium',
            'status' => 'active',
        ]);

        $this->actingAs($world['scienceHod'])
            ->get(route('at-risk.index'))
            ->assertOk()
            ->assertDontSee('Ngozi English');

        $this->actingAs($world['humanitiesHod'])
            ->get(route('at-risk.index'))
            ->assertOk()
            ->assertSee('Ngozi English');
    }

    public function test_lesson_plans_index_hides_other_department(): void
    {
        $world = $this->world();

        $this->actingAs($world['scienceHod'])
            ->get(route('lesson-plans.index'))
            ->assertOk()
            ->assertSee('Forces')
            ->assertDontSee('Comprehension');
    }
}
