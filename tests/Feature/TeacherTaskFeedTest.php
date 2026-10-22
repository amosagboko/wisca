<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\HomeworkLog;
use App\Models\LessonPlan;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchemeOfWork;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\Topic;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherTaskFeedTest extends TestCase
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
        $school = School::create(['name' => 'Feed School', 'slug' => 'feed-school-'.$token, 'status' => 'active']);
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
                'email' => $role.'.'.$token.'@feed.test',
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
        $this->assignSectionHod($school, $users['head_of_department'], $class, 'Basic School '.$token, [$subject->id]);

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

        return compact('school', 'session', 'term', 'class', 'subject', 'users', 'scheme', 'topic', 'token');
    }

    public function test_teacher_sees_four_segments_and_actionable_plan_homework(): void
    {
        $world = $this->world();

        $this->actingAs($world['users']['teacher'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('My Actions')
            ->assertSee('My Teaching Week')
            ->assertSee('My Performance & Learner Follow-up')
            ->assertSee('Other Required Activities')
            ->assertSee('Submit lesson plan')
            ->assertSee('Number Bases')
            ->assertSee('No homework logged this instructional week.')
            ->assertSee('Log homework')
            ->assertSee('Plan Lessons')
            ->assertSee('Curriculum Coverage')
            ->assertSee('Lesson plans')
            ->assertSee('Homework/Classwork')
            ->assertSee('Learner Attendance')
            ->assertSee('Examination pass rate')
            ->assertSee('AE-02')
            ->assertSee('AE-09')
            ->assertDontSee('Due this week')
            ->assertDontSee('Learner Understanding')
            ->assertDontSee('AE-10')
            ->assertDontSee('Record Delivery')
            ->assertDontSee('Waiting on HOD');
    }

    public function test_homework_log_stays_out_of_actions_while_waiting_on_hod(): void
    {
        $world = $this->world();

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
        ]);

        $this->actingAs($world['users']['teacher'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('My Actions')
            ->assertSee('Submit lesson plan')
            ->assertDontSee('Log homework')
            ->assertDontSee('No homework logged this instructional week.')
            ->assertDontSee('Homework awaiting HOD verification.')
            ->assertDontSee('Waiting on HOD')
            ->assertDontSee('Due this week');
    }

    public function test_submitted_lesson_plan_is_teaching_week_status_not_an_action(): void
    {
        $world = $this->world();

        LessonPlan::create([
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

        $this->actingAs($world['users']['teacher'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('My Teaching Week')
            ->assertSee('Awaiting HOD')
            ->assertDontSee('Submit lesson plan')
            ->assertDontSee('Waiting on HOD')
            ->assertSee('Log homework');
    }

    public function test_missing_scheme_appears_in_actions_and_does_not_fabricate_zero_coverage(): void
    {
        $world = $this->world();
        $world['scheme']->update(['status' => 'draft', 'submitted_at' => null]);

        $this->actingAs($world['users']['teacher'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Continue draft')
            ->assertSee('Submit scheme of work')
            ->assertSee('No approved scheme')
            ->assertSee('Awaiting data')
            ->assertDontSee('Submit lesson plan')
            ->assertDontSee('0.0%');
    }

    public function test_submitted_scheme_is_not_an_action(): void
    {
        $world = $this->world();
        $world['scheme']->update([
            'status' => 'draft',
            'submitted_at' => now(),
            'submitted_by' => $world['users']['teacher']->id,
        ]);

        $this->actingAs($world['users']['teacher'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Awaiting HOD')
            ->assertDontSee('Continue draft')
            ->assertDontSee('Submit scheme')
            ->assertDontSee('Revise scheme');
    }

    public function test_record_delivery_requires_an_approved_lesson_plan(): void
    {
        $world = $this->world();

        LessonPlan::create([
            'topic_id' => $world['topic']->id,
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

        $this->actingAs($world['users']['teacher'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Record Delivery')
            ->assertDontSee('Plan Lessons');
    }

    public function test_multiple_assignments_render_two_teaching_week_rows(): void
    {
        $world = $this->world();
        $classB = SchoolClass::create([
            'school_id' => $world['school']->id,
            'name' => 'JSS 2B',
            'level' => 'JSS',
            'status' => 'active',
        ]);
        $subjectB = Subject::create([
            'school_id' => $world['school']->id,
            'name' => 'English',
            'code' => 'ENG'.$world['token'],
            'status' => 'active',
        ]);
        TeacherAssignment::create([
            'teacher_id' => $world['users']['teacher']->id,
            'school_class_id' => $classB->id,
            'subject_id' => $subjectB->id,
            'academic_session_id' => $world['session']->id,
            'status' => 'active',
        ]);

        $this->actingAs($world['users']['teacher'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('JSS 1A · Mathematics')
            ->assertSee('JSS 2B · English')
            ->assertSee('Submit scheme of work');
    }

    public function test_teacher_without_assignments_is_all_caught_up(): void
    {
        $world = $this->world();
        $spare = User::create([
            'school_id' => $world['school']->id,
            'name' => 'Spare teacher',
            'email' => 'spare.'.$world['token'].'@feed.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $spare->assignRole('teacher');

        $this->actingAs($spare)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('You are all caught up.')
            ->assertSee('My Actions')
            ->assertDontSee('Due this week');
    }

    public function test_hod_verification_queue_does_not_show_teacher_task_feed(): void
    {
        $world = $this->world();

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('School Section Overview')
            ->assertDontSee('Due this week')
            ->assertDontSee('Submit lesson plan');
    }
}
