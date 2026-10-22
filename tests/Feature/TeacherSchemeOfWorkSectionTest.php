<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\LessonPlan;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchemeOfWork;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherSchemeOfWorkSectionTest extends TestCase
{
    protected bool $mysqlTransaction = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (extension_loaded('pdo_sqlite') && config('database.default') === 'sqlite') {
            $this->artisan('migrate');

            return;
        }

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

    /**
     * @return array<string, mixed>
     */
    protected function world(): array
    {
        foreach (['board', 'head_of_school', 'head_of_department', 'teacher', 'admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $token = Str::lower(Str::random(8));
        $school = School::create(['name' => 'Section School', 'slug' => 'sec-'.$token, 'status' => 'active']);
        $session = AcademicSession::create([
            'school_id' => $school->id,
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
        $eyfsClass = SchoolClass::create(['school_id' => $school->id, 'name' => 'Nursery', 'level' => 'EYFS', 'status' => 'active']);
        $basicClass = SchoolClass::create(['school_id' => $school->id, 'name' => 'Basic 1', 'level' => 'Basic', 'status' => 'active']);
        $unassigned = SchoolClass::create(['school_id' => $school->id, 'name' => 'JSS 1A', 'level' => 'JSS', 'status' => 'active']);
        $math = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics', 'code' => 'MTH'.$token, 'status' => 'active']);
        $english = Subject::create(['school_id' => $school->id, 'name' => 'English', 'code' => 'ENG'.$token, 'status' => 'active']);
        $eyfsClass->offeredSubjects()->syncWithoutDetaching([$math->id]);
        $basicClass->offeredSubjects()->syncWithoutDetaching([$english->id]);
        $unassigned->offeredSubjects()->syncWithoutDetaching([$math->id]);

        $users = [];
        foreach (['board', 'head_of_school', 'head_of_department', 'teacher', 'admin'] as $role) {
            $user = User::create([
                'school_id' => $school->id,
                'name' => $role,
                'email' => $role.'.'.$token.'@sec.test',
                'password' => 'password',
                'status' => 'active',
            ]);
            $user->assignRole($role);
            $users[$role] = $user;
        }

        $eyfsHod = User::create([
            'school_id' => $school->id,
            'name' => 'EYFS HOD',
            'email' => 'eyfs.'.$token.'@sec.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $eyfsHod->assignRole('head_of_department');
        $basicHod = User::create([
            'school_id' => $school->id,
            'name' => 'Basic HOD',
            'email' => 'basic.'.$token.'@sec.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $basicHod->assignRole('head_of_department');

        $this->assignSectionHod($school, $eyfsHod, $eyfsClass, 'EYFS '.$token, [$math->id]);
        $this->assignSectionHod($school, $basicHod, $basicClass, 'Basic School '.$token, [$english->id]);

        $otherTeacher = User::create([
            'school_id' => $school->id,
            'name' => 'Other Teacher',
            'email' => 'other.'.$token.'@sec.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $otherTeacher->assignRole('teacher');

        TeacherAssignment::create([
            'teacher_id' => $users['teacher']->id,
            'school_class_id' => $eyfsClass->id,
            'subject_id' => $math->id,
            'academic_session_id' => $session->id,
            'status' => 'active',
        ]);
        TeacherAssignment::create([
            'teacher_id' => $otherTeacher->id,
            'school_class_id' => $basicClass->id,
            'subject_id' => $english->id,
            'academic_session_id' => $session->id,
            'status' => 'active',
        ]);
        TeacherAssignment::create([
            'teacher_id' => $users['teacher']->id,
            'school_class_id' => $unassigned->id,
            'subject_id' => $math->id,
            'academic_session_id' => $session->id,
            'status' => 'active',
        ]);

        return compact(
            'school', 'session', 'term', 'eyfsClass', 'basicClass', 'unassigned',
            'math', 'english', 'users', 'eyfsHod', 'basicHod', 'otherTeacher',
        );
    }

    /**
     * @param  array<string, mixed>  $world
     * @return array<string, mixed>
     */
    protected function draftPayload(array $world, ?int $classId = null, ?int $subjectId = null): array
    {
        return [
            'academic_session_id' => $world['session']->id,
            'term_id' => $world['term']->id,
            'school_class_id' => $classId ?? $world['eyfsClass']->id,
            'subject_id' => $subjectId ?? $world['math']->id,
            'topics' => [[
                'week_number' => 1,
                'title' => 'Number counting',
                'learning_objectives' => "Count to 20\nUse objects",
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $world
     */
    protected function schemeFor(array $world, ?int $classId = null, ?int $subjectId = null): ?SchemeOfWork
    {
        return SchemeOfWork::query()
            ->where('academic_session_id', $world['session']->id)
            ->where('school_class_id', $classId ?? $world['eyfsClass']->id)
            ->where('subject_id', $subjectId ?? $world['math']->id)
            ->latest('id')
            ->first();
    }

    public function test_teacher_can_create_and_submit_only_for_assigned_class_subject(): void
    {
        $world = $this->world();
        $teacher = $world['users']['teacher'];

        $this->actingAs($teacher)
            ->post(route('schemes.store'), array_merge($this->draftPayload($world), ['_action' => 'submit']))
            ->assertRedirect();

        $scheme = $this->schemeFor($world);
        $this->assertNotNull($scheme);
        $this->assertSame('draft', $scheme->status);
        $this->assertNotNull($scheme->submitted_at);
        $this->assertSame($teacher->id, $scheme->uploaded_by);
        $this->assertNotNull($scheme->submittedOnTime());

        $this->actingAs($teacher)
            ->get(route('schemes.index'))
            ->assertOk()
            ->assertSee('Timing')
            ->assertSee($scheme->submittedOnTime() ? 'On time' : 'Late');
    }

    public function test_teacher_cannot_upload_another_sections_class(): void
    {
        $world = $this->world();

        $this->actingAs($world['users']['teacher'])
            ->from(route('schemes.create'))
            ->post(route('schemes.store'), $this->draftPayload($world, $world['basicClass']->id, $world['english']->id))
            ->assertRedirect(route('schemes.create'))
            ->assertSessionHasErrors('subject_id');

        $this->assertSame(0, SchemeOfWork::query()->where('academic_session_id', $world['session']->id)->count());
    }

    public function test_submit_fails_when_class_has_no_section_hod(): void
    {
        $world = $this->world();
        $teacher = $world['users']['teacher'];

        $this->actingAs($teacher)
            ->post(route('schemes.store'), $this->draftPayload($world, $world['unassigned']->id, $world['math']->id))
            ->assertRedirect();

        $draft = $this->schemeFor($world, $world['unassigned']->id, $world['math']->id);
        $this->assertNotNull($draft);
        $this->assertNull($draft->submitted_at);

        $this->actingAs($teacher)
            ->from(route('schemes.show', $draft))
            ->post(route('schemes.submit', $draft))
            ->assertRedirect()
            ->assertSessionHasErrors('school_class_id');

        $this->assertNull($draft->fresh()->submitted_at);
    }

    public function test_section_hods_cannot_approve_the_other_section(): void
    {
        $world = $this->world();

        $this->actingAs($world['users']['teacher'])
            ->post(route('schemes.store'), array_merge($this->draftPayload($world), ['_action' => 'submit']));
        $eyfsScheme = $this->schemeFor($world);

        $this->actingAs($world['otherTeacher'])
            ->post(route('schemes.store'), array_merge($this->draftPayload($world, $world['basicClass']->id, $world['english']->id), ['_action' => 'submit']));
        $basicScheme = $this->schemeFor($world, $world['basicClass']->id, $world['english']->id);

        $this->actingAs($world['basicHod'])->post(route('schemes.approve', $eyfsScheme))->assertForbidden();
        $this->actingAs($world['eyfsHod'])->post(route('schemes.approve', $basicScheme))->assertForbidden();
        $this->assertSame('draft', $eyfsScheme->fresh()->status);
        $this->assertSame('draft', $basicScheme->fresh()->status);

        $this->actingAs($world['eyfsHod'])->post(route('schemes.approve', $eyfsScheme))->assertRedirect();
        $this->assertSame('active', $eyfsScheme->fresh()->status);
    }

    public function test_assigned_teacher_can_plan_lessons_from_approved_sow_only(): void
    {
        $world = $this->world();
        $teacher = $world['users']['teacher'];

        $this->actingAs($teacher)
            ->post(route('schemes.store'), array_merge($this->draftPayload($world), ['_action' => 'submit']));
        $scheme = $this->schemeFor($world);
        $this->assertNotNull($scheme);

        $this->actingAs($teacher)
            ->get(route('schemes.show', $scheme))
            ->assertOk()
            ->assertDontSee('Plan Lessons from Approved SoW');

        $this->actingAs($teacher)
            ->get(route('lesson-plans.create', ['scheme' => $scheme->id]))
            ->assertForbidden();

        $this->actingAs($world['eyfsHod'])->post(route('schemes.approve', $scheme))->assertRedirect();
        $scheme = $scheme->fresh();
        $this->assertSame('active', $scheme->status);

        $this->actingAs($teacher)
            ->get(route('schemes.show', $scheme))
            ->assertOk()
            ->assertSee('Plan Lessons from Approved SoW')
            ->assertSee(route('lesson-plans.create', ['scheme' => $scheme->id]), false);

        $this->actingAs($world['eyfsHod'])
            ->get(route('schemes.show', $scheme))
            ->assertOk()
            ->assertDontSee('Plan Lessons from Approved SoW');

        $this->actingAs($world['otherTeacher'])
            ->get(route('lesson-plans.create', ['scheme' => $scheme->id]))
            ->assertForbidden();

        $this->actingAs($teacher)
            ->get(route('lesson-plans.create', ['scheme' => $scheme->id]))
            ->assertOk()
            ->assertSee('Plan lessons from approved SoW')
            ->assertSee($world['session']->name)
            ->assertSee('First Term')
            ->assertSee($world['eyfsClass']->name)
            ->assertSee($world['math']->name)
            ->assertSee('Number counting')
            ->assertSee('Count to 20');

        $topic = $scheme->fresh('topics')->topics->first();
        $this->actingAs($teacher)
            ->post(route('lesson-plans.store'), [
                'topic_id' => $topic->id,
                'objectives' => ['Count to 20', 'Use objects'],
                'activities' => 'Counting games',
                'assessment' => 'Exit ticket',
            ])
            ->assertRedirect();

        $plan = LessonPlan::query()->where('topic_id', $topic->id)->first();
        $this->assertNotNull($plan);
        $this->assertSame($teacher->id, $plan->teacher_id);
        $this->assertSame($world['eyfsClass']->id, $plan->school_class_id);
        $this->assertSame($world['math']->id, $plan->subject_id);
        $this->assertSame('submitted', $plan->status);

        $this->actingAs($teacher)
            ->get(route('lesson-plans.show', $plan))
            ->assertOk()
            ->assertSee('Counting games')
            ->assertSee('Exit ticket')
            ->assertSee('Count to 20');

        $this->actingAs($world['eyfsHod'])
            ->get(route('lesson-plans.show', $plan))
            ->assertOk()
            ->assertSee('Counting games')
            ->assertSee('Approve plan');

        $this->actingAs($world['otherTeacher'])
            ->get(route('lesson-plans.show', $plan))
            ->assertForbidden();

        $this->actingAs($world['basicHod'])
            ->get(route('lesson-plans.show', $plan))
            ->assertForbidden();
    }

    public function test_hos_and_board_cannot_approve_sow_or_lesson_plan(): void
    {
        $world = $this->world();

        $this->actingAs($world['users']['teacher'])
            ->post(route('schemes.store'), array_merge($this->draftPayload($world), ['_action' => 'submit']));
        $scheme = $this->schemeFor($world);

        $this->actingAs($world['users']['head_of_school'])->post(route('schemes.approve', $scheme))->assertForbidden();
        $this->actingAs($world['users']['board'])->post(route('schemes.approve', $scheme))->assertForbidden();

        $this->actingAs($world['eyfsHod'])->post(route('schemes.approve', $scheme))->assertRedirect();
        $topic = $scheme->fresh('topics')->topics->first();

        $plan = LessonPlan::create([
            'topic_id' => $topic->id,
            'teacher_id' => $world['users']['teacher']->id,
            'school_class_id' => $world['eyfsClass']->id,
            'subject_id' => $world['math']->id,
            'objectives' => 'Count',
            'activities' => 'Teach',
            'assessment' => 'Quiz',
            'status' => 'submitted',
            'submitted_at' => now(),
            'due_at' => now()->addDay(),
            'on_time' => true,
        ]);

        $this->actingAs($world['users']['head_of_school'])->post(route('lesson-plans.approve', $plan))->assertForbidden();
        $this->actingAs($world['users']['board'])->post(route('lesson-plans.approve', $plan))->assertForbidden();
        $this->assertSame('submitted', $plan->fresh()->status);
    }

    public function test_hod_approve_archives_previous_active_and_teacher_can_plan(): void
    {
        $world = $this->world();
        $teacher = $world['users']['teacher'];

        $previous = SchemeOfWork::create([
            'subject_id' => $world['math']->id,
            'school_class_id' => $world['eyfsClass']->id,
            'academic_session_id' => $world['session']->id,
            'term_id' => $world['term']->id,
            'version' => 1,
            'uploaded_by' => $teacher->id,
            'status' => 'active',
        ]);
        Topic::create([
            'scheme_of_work_id' => $previous->id,
            'week_number' => 1,
            'title' => 'Old counting',
            'learning_objectives' => ['Old'],
            'display_order' => 1,
            'status' => 'planned',
        ]);

        $this->actingAs($teacher)
            ->post(route('schemes.store'), array_merge($this->draftPayload($world), ['_action' => 'submit']));
        $replacement = SchemeOfWork::query()
            ->where('academic_session_id', $world['session']->id)
            ->where('school_class_id', $world['eyfsClass']->id)
            ->where('subject_id', $world['math']->id)
            ->where('id', '!=', $previous->id)
            ->first();

        $this->actingAs($world['eyfsHod'])->post(route('schemes.approve', $replacement))->assertRedirect();

        $this->assertSame('archived', $previous->fresh()->status);
        $this->assertSame('active', $replacement->fresh()->status);
        $this->assertSame($world['eyfsHod']->id, $replacement->fresh()->approved_by);

        $this->actingAs($teacher)
            ->get(route('schemes.index'))
            ->assertOk()
            ->assertSee('Active')
            ->assertSee('Timing')
            ->assertSee($replacement->fresh()->submittedOnTime() ? 'On time' : 'Late');
        $this->assertNull($replacement->fresh()->hos_approved_by);
        $this->assertNull($replacement->fresh()->board_approved_by);

        $topic = $replacement->fresh('topics')->topics->first();
        $this->actingAs($teacher)
            ->post(route('lesson-plans.store'), [
                'topic_id' => $topic->id,
                'objectives' => "Count to 20\nUse objects",
                'activities' => 'Counting games',
                'assessment' => 'Exit ticket',
            ])
            ->assertRedirect();

        $this->assertSame(1, LessonPlan::query()->where('topic_id', $topic->id)->count());
    }

    public function test_reject_returns_editable_draft_to_the_teacher(): void
    {
        $world = $this->world();
        $teacher = $world['users']['teacher'];

        $this->actingAs($teacher)
            ->post(route('schemes.store'), array_merge($this->draftPayload($world), ['_action' => 'submit']));
        $scheme = $this->schemeFor($world);

        $this->actingAs($world['eyfsHod'])
            ->from(route('schemes.show', $scheme))
            ->post(route('schemes.reject', $scheme), ['rejection_reason' => 'Add week 2'])
            ->assertRedirect();

        $returned = $scheme->fresh();
        $this->assertSame('draft', $returned->status);
        $this->assertNull($returned->submitted_at);
        $this->assertSame('Add week 2', $returned->rejection_reason);
        $this->assertTrue($returned->isEditableDraft());

        $this->actingAs($teacher)->get(route('schemes.edit', $returned))->assertOk();
        $this->actingAs($teacher)->post(route('schemes.submit', $returned))->assertRedirect();
        $this->assertNotNull($returned->fresh()->submitted_at);
    }

    public function test_clone_keeps_term_matching_and_does_not_copy_approval(): void
    {
        $world = $this->world();
        $teacher = $world['users']['teacher'];

        $this->actingAs($teacher)
            ->post(route('schemes.store'), array_merge($this->draftPayload($world), ['_action' => 'submit']));
        $source = $this->schemeFor($world);
        $this->actingAs($world['eyfsHod'])->post(route('schemes.approve', $source));

        $destSession = AcademicSession::create([
            'school_id' => $world['school']->id,
            'name' => '2026/2027',
            'start_date' => now()->addYear()->toDateString(),
            'end_date' => now()->addMonths(21)->toDateString(),
            'is_current' => false,
            'status' => 'upcoming',
        ]);
        $destFirst = Term::create([
            'academic_session_id' => $destSession->id,
            'name' => 'First Term',
            'start_date' => $destSession->start_date,
            'end_date' => now()->addMonths(15)->toDateString(),
            'sequence' => 1,
            'is_current' => false,
            'status' => 'upcoming',
        ]);
        $destSecond = Term::create([
            'academic_session_id' => $destSession->id,
            'name' => 'Second Term',
            'start_date' => now()->addMonths(16)->toDateString(),
            'end_date' => $destSession->end_date,
            'sequence' => 2,
            'is_current' => false,
            'status' => 'upcoming',
        ]);
        TeacherAssignment::create([
            'teacher_id' => $teacher->id,
            'school_class_id' => $world['eyfsClass']->id,
            'subject_id' => $world['math']->id,
            'academic_session_id' => $destSession->id,
            'status' => 'active',
        ]);

        $this->actingAs($teacher)
            ->from(route('schemes.clone-session', $source))
            ->post(route('schemes.clone-session.store', $source), [
                'academic_session_id' => $destSession->id,
                'term_id' => $destSecond->id,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('term_id');

        $this->actingAs($teacher)
            ->post(route('schemes.clone-session.store', $source), [
                'academic_session_id' => $destSession->id,
                'term_id' => $destFirst->id,
            ])
            ->assertRedirect();

        $clone = SchemeOfWork::query()
            ->where('academic_session_id', $destSession->id)
            ->where('term_id', $destFirst->id)
            ->first();
        $this->assertNotNull($clone);
        $this->assertSame('draft', $clone->status);
        $this->assertNull($clone->submitted_at);
        $this->assertNull($clone->approved_by);
        $this->assertNull($clone->hos_approved_by);
        $this->assertSame($source->id, $clone->replaces_id);
        $this->assertSame('active', $source->fresh()->status);
    }

    public function test_teacher_sees_schemes_of_work_in_home_nav(): void
    {
        $world = $this->world();

        $this->actingAs($world['users']['teacher'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Schemes of Work')
            ->assertSee(route('schemes.index'), false);
    }
}
