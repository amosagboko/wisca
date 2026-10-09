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

class HodReviewFeedTest extends TestCase
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
        $school = School::create(['name' => 'Review School', 'slug' => 'review-school-'.$token, 'status' => 'active']);
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
                'email' => $role.'.'.$token.'@review.test',
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

        return compact('school', 'session', 'term', 'class', 'subject', 'users', 'scheme', 'topic');
    }

    public function test_hod_reviews_due_includes_pending_plan_and_missing_homework(): void
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

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Reviews due')
            ->assertSee('Review plan')
            ->assertSee('Number Bases')
            ->assertSee('Homework not logged')
            ->assertSee('View homework')
            ->assertSee('View only')
            ->assertDontSee('Due this week')
            ->assertDontSee('Approve homework')
            ->assertDontSee('SLA overdue');
    }

    public function test_logged_homework_drops_gap_from_hod_feed(): void
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

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Reviews due')
            ->assertDontSee('Homework not logged')
            ->assertSee('Review homework')
            ->assertSee('Exercise 1')
            ->assertSee('Approve homework');
    }

    public function test_plan_past_24_hours_is_overdue_on_hod_inbox(): void
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
            'submitted_at' => now()->subHours(25),
            'due_at' => now()->subDay(),
            'on_time' => true,
        ]);

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Number Bases')
            ->assertSee('Overdue')
            ->assertSee('24-hour HOD review SLA has passed')
            ->assertSee('24-hour review SLA overdue');
    }
}
