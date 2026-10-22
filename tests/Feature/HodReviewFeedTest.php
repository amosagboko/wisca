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
            ->assertSee('Action Queue')
            ->assertSee('Weekly Pulse')
            ->assertSee('Flags & Follow-ups')
            ->assertSee('Termly Drawer')
            ->assertSee('Lesson plan')
            ->assertSee('Review lesson plan')
            ->assertSee('Number Bases')
            ->assertSee('Learner Understanding')
            ->assertSee('Awaiting data')
            ->assertSee('Examination pass rate')
            ->assertDontSee('Due this week')
            ->assertDontSee('Approve homework')
            ->assertDontSee('Homework not logged')
            ->assertDontSee('Review homework')
            ->assertDontSee('Awaiting exam portal sync')
            ->assertDontSee('Department coverage');
    }

    public function test_reviews_due_type_filter_hides_other_activity_groups(): void
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
            ->assertSee('Number Bases')
            ->assertSee('Review lesson plan')
            ->assertDontSee('Homework not logged');
    }

    public function test_reviews_due_paginates_large_queues(): void
    {
        $world = $this->world();

        for ($i = 1; $i <= 12; $i++) {
            $topic = Topic::create([
                'scheme_of_work_id' => $world['scheme']->id,
                'week_number' => 1,
                'title' => 'Queued topic '.$i,
                'learning_objectives' => ['Convert bases'],
                'display_order' => $i + 1,
                'status' => 'planned',
            ]);
            LessonPlan::create([
                'topic_id' => $topic->id,
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

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Queued topic')
            ->assertSee('Action Queue')
            ->assertSee('Lesson plans')
            ->assertSee('Review lesson plan')
            ->assertSee('Showing 3 of 12')
            ->assertDontSee('inbox_page=2');

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('hod.reviews.lesson-plans', ['session_id' => $world['session']->id, 'term_id' => $world['term']->id]))
            ->assertOk()
            ->assertSee('Queued topic 1')
            ->assertSee('Queued topic 12')
            ->assertSee('Review lesson plan');
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
            ->assertSee('Action Queue')
            ->assertSee('Homework/Classwork')
            ->assertSee('80.0%')
            ->assertDontSee('Homework not logged')
            ->assertDontSee('Review homework')
            ->assertDontSee('Approve homework')
            ->assertDontSee('Exercise 1');
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
            ->assertSee('24-hour HOD review SLA has passed');
    }

    public function test_submitted_scheme_appears_in_action_queue_and_homework_does_not(): void
    {
        $world = $this->world();

        $submitted = SchemeOfWork::create([
            'subject_id' => $world['subject']->id,
            'school_class_id' => $world['class']->id,
            'academic_session_id' => $world['session']->id,
            'term_id' => $world['term']->id,
            'version' => 2,
            'uploaded_by' => $world['users']['teacher']->id,
            'submitted_by' => $world['users']['teacher']->id,
            'submitted_at' => now()->subHours(21),
            'status' => 'draft',
        ]);

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Scheme of Work')
            ->assertSee('Review scheme')
            ->assertSee('20-hour warning')
            ->assertSee($world['subject']->name)
            ->assertDontSee('Review homework');

        $this->assertSame('draft', $submitted->fresh()->status);
    }

    public function test_action_queue_empty_state_when_nothing_is_pending(): void
    {
        $world = $this->world();

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('All caught up')
            ->assertDontSee('Review plan')
            ->assertSee('QuickLog');
    }

    public function test_action_queue_preview_keeps_three_items_and_review_list_is_paginated(): void
    {
        $world = $this->world();

        for ($i = 1; $i <= 16; $i++) {
            $topic = Topic::create([
                'scheme_of_work_id' => $world['scheme']->id,
                'week_number' => 1,
                'title' => 'Preview topic '.$i,
                'learning_objectives' => ['Convert bases'],
                'display_order' => $i + 1,
                'status' => 'planned',
            ]);
            LessonPlan::create([
                'topic_id' => $topic->id,
                'teacher_id' => $world['users']['teacher']->id,
                'school_class_id' => $world['class']->id,
                'subject_id' => $world['subject']->id,
                'objectives' => 'Convert bases',
                'activities' => 'Teach',
                'assessment' => 'Quiz',
                'status' => 'submitted',
                'submitted_at' => now()->subHours(16 - $i),
                'due_at' => now()->addDay(),
                'on_time' => true,
            ]);
        }

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Preview topic 1')
            ->assertSee('Preview topic 2')
            ->assertSee('Preview topic 3')
            ->assertDontSee('Preview topic 4')
            ->assertSee('Review lesson plan')
            ->assertSee('13 more on the review list');

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('hod.reviews.lesson-plans', ['session_id' => $world['session']->id, 'term_id' => $world['term']->id]))
            ->assertOk()
            ->assertSee('Preview topic 1')
            ->assertSee('Preview topic 15')
            ->assertDontSee('Preview topic 16');

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('hod.reviews.lesson-plans', [
                'session_id' => $world['session']->id,
                'term_id' => $world['term']->id,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertSee('Preview topic 16');
    }

    public function test_hod_scheme_review_list_shows_submitted_scheme_and_teacher_is_forbidden(): void
    {
        $world = $this->world();

        SchemeOfWork::create([
            'subject_id' => $world['subject']->id,
            'school_class_id' => $world['class']->id,
            'academic_session_id' => $world['session']->id,
            'term_id' => $world['term']->id,
            'version' => 2,
            'uploaded_by' => $world['users']['teacher']->id,
            'submitted_by' => $world['users']['teacher']->id,
            'submitted_at' => now()->subHours(2),
            'status' => 'draft',
        ]);

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('hod.reviews.schemes', ['session_id' => $world['session']->id, 'term_id' => $world['term']->id]))
            ->assertOk()
            ->assertSee('Schemes of Work for review')
            ->assertSee($world['subject']->name)
            ->assertSee('Review scheme');

        $this->actingAs($world['users']['teacher'])
            ->get(route('hod.reviews.schemes'))
            ->assertForbidden();

        $this->actingAs($world['users']['teacher'])
            ->get(route('hod.reviews.lesson-plans'))
            ->assertForbidden();
    }
}
