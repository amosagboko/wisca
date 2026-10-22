<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\LessonPlan;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\SchemeOfWork;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\HodScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HodUserSectionTest extends TestCase
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
        foreach (['admin', 'teacher', 'head_of_department'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $token = Str::lower(Str::random(8));
        $school = School::create(['name' => 'Hod User School', 'slug' => 'hus-'.$token, 'status' => 'active']);
        $session = AcademicSession::create([
            'school_id' => $school->id,
            'name' => '2025/2026 '.$token,
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

        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Basic 1 '.$token, 'level' => 'Basic', 'status' => 'active']);
        $otherClass = SchoolClass::create(['school_id' => $school->id, 'name' => 'Nursery '.$token, 'level' => 'EYFS', 'status' => 'active']);
        $math = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics '.$token, 'code' => 'MTH'.$token, 'status' => 'active']);
        $class->offeredSubjects()->syncWithoutDetaching([$math->id]);
        $otherClass->offeredSubjects()->syncWithoutDetaching([$math->id]);

        $basicSection = SchoolSection::create([
            'school_id' => $school->id,
            'name' => 'Basic School '.$token,
            'status' => 'active',
            'hod_id' => null,
        ]);
        $eyfsSection = SchoolSection::create([
            'school_id' => $school->id,
            'name' => 'EYFS '.$token,
            'status' => 'active',
            'hod_id' => null,
        ]);
        $class->update(['school_section_id' => $basicSection->id]);
        $otherClass->update(['school_section_id' => $eyfsSection->id]);

        $admin = User::create([
            'school_id' => $school->id,
            'name' => 'Admin '.$token,
            'email' => 'admin.'.$token.'@hus.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $admin->assignRole('admin');

        $teacher = User::create([
            'school_id' => $school->id,
            'name' => 'Teacher '.$token,
            'email' => 'teacher.'.$token.'@hus.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');

        TeacherAssignment::create([
            'teacher_id' => $teacher->id,
            'school_class_id' => $class->id,
            'subject_id' => $math->id,
            'academic_session_id' => $session->id,
            'status' => 'active',
        ]);

        return compact(
            'token', 'school', 'session', 'term', 'class', 'otherClass', 'math',
            'basicSection', 'eyfsSection', 'admin', 'teacher',
        );
    }

    /**
     * @param  array<string, mixed>  $world
     * @return array<string, mixed>
     */
    protected function hodPayload(array $world, array $overrides = []): array
    {
        $token = $world['token'];

        return array_merge([
            'name' => 'New HOD '.$token,
            'email' => 'newhod.'.$token.'@hus.test',
            'password' => 'password1',
            'password_confirmation' => 'password1',
            'status' => 'active',
            'role' => 'head_of_department',
            'school_section_id' => $world['basicSection']->id,
        ], $overrides);
    }

    public function test_create_form_shows_school_section_not_department(): void
    {
        $world = $this->world();

        $this->actingAs($world['admin'])
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('Select School Section')
            ->assertDontSee('Select department', false)
            ->assertSee($world['basicSection']->name)
            ->assertSee($world['eyfsSection']->name);
    }

    public function test_hod_cannot_be_created_without_a_school_section(): void
    {
        $world = $this->world();

        $this->actingAs($world['admin'])
            ->from(route('admin.users.create'))
            ->post(route('admin.users.store'), $this->hodPayload($world, [
                'school_section_id' => null,
            ]))
            ->assertRedirect(route('admin.users.create'))
            ->assertSessionHasErrors('school_section_id');

        $this->assertSame(0, User::query()->where('email', 'newhod.'.$world['token'].'@hus.test')->count());
        $this->assertNull($world['basicSection']->fresh()->hod_id);
    }

    public function test_creating_hod_assigns_selected_school_section(): void
    {
        $world = $this->world();

        $this->actingAs($world['admin'])
            ->post(route('admin.users.store'), $this->hodPayload($world))
            ->assertRedirect(route('admin.users.index'));

        $hod = User::where('email', 'newhod.'.$world['token'].'@hus.test')->first();
        $this->assertNotNull($hod);
        $this->assertTrue($hod->hasRole('head_of_department'));
        $this->assertSame($hod->id, $world['basicSection']->fresh()->hod_id);
        $this->assertNull($world['eyfsSection']->fresh()->hod_id);
        $this->assertNull($hod->department_id);
    }

    public function test_teacher_can_be_created_without_a_school_section(): void
    {
        $world = $this->world();
        $token = $world['token'];

        $this->actingAs($world['admin'])
            ->post(route('admin.users.store'), [
                'name' => 'Other Teacher '.$token,
                'email' => 'other.'.$token.'@hus.test',
                'password' => 'password1',
                'password_confirmation' => 'password1',
                'status' => 'active',
                'role' => 'teacher',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertTrue(User::where('email', 'other.'.$token.'@hus.test')->first()?->hasRole('teacher'));
    }

    public function test_edit_reflects_assigned_section_and_can_move_hod(): void
    {
        $world = $this->world();

        $this->actingAs($world['admin'])
            ->post(route('admin.users.store'), $this->hodPayload($world));

        $hod = User::where('email', 'newhod.'.$world['token'].'@hus.test')->first();

        $this->actingAs($world['admin'])
            ->get(route('admin.users.edit', $hod))
            ->assertOk()
            ->assertSee('Select School Section')
            ->assertSee('selected', false)
            ->assertSee($world['basicSection']->name);

        $this->actingAs($world['admin'])
            ->put(route('admin.users.update', $hod), [
                'name' => $hod->name,
                'email' => $hod->email,
                'status' => 'active',
                'role' => 'head_of_department',
                'school_section_id' => $world['eyfsSection']->id,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertNull($world['basicSection']->fresh()->hod_id);
        $this->assertSame($hod->id, $world['eyfsSection']->fresh()->hod_id);
    }

    public function test_assigned_hod_reviews_schemes_and_lesson_plans_for_that_section_only(): void
    {
        $world = $this->world();

        $this->actingAs($world['admin'])
            ->post(route('admin.users.store'), $this->hodPayload($world));

        $hod = User::where('email', 'newhod.'.$world['token'].'@hus.test')->first();

        $this->actingAs($world['teacher'])
            ->post(route('schemes.store'), [
                'academic_session_id' => $world['session']->id,
                'term_id' => $world['term']->id,
                'school_class_id' => $world['class']->id,
                'subject_id' => $world['math']->id,
                'topics' => [[
                    'week_number' => 1,
                    'title' => 'Counting',
                    'learning_objectives' => "Count\nWrite",
                ]],
                '_action' => 'submit',
            ])
            ->assertRedirect();

        $scheme = SchemeOfWork::query()
            ->where('academic_session_id', $world['session']->id)
            ->where('school_class_id', $world['class']->id)
            ->where('subject_id', $world['math']->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($scheme);
        $this->actingAs($hod)->post(route('schemes.approve', $scheme))->assertRedirect();
        $this->assertSame('active', $scheme->fresh()->status);

        $topic = $scheme->fresh('topics')->topics->first();
        $plan = LessonPlan::create([
            'topic_id' => $topic->id,
            'teacher_id' => $world['teacher']->id,
            'school_class_id' => $world['class']->id,
            'subject_id' => $world['math']->id,
            'objectives' => 'Count',
            'activities' => 'Teach',
            'assessment' => 'Quiz',
            'status' => 'submitted',
            'submitted_at' => now(),
            'due_at' => now()->addDay(),
            'on_time' => true,
        ]);

        $scope = app(HodScope::class);
        $this->assertTrue($scope->canReviewLessonPlan($hod, $plan));
        $this->actingAs($hod)
            ->from(route('dashboard'))
            ->post(route('lesson-plans.approve', $plan))
            ->assertRedirect()
            ->assertSessionHasErrors('checklist.alignment');

        $otherPlan = LessonPlan::create([
            'topic_id' => $topic->id,
            'teacher_id' => $world['teacher']->id,
            'school_class_id' => $world['otherClass']->id,
            'subject_id' => $world['math']->id,
            'objectives' => 'Count',
            'activities' => 'Teach',
            'assessment' => 'Quiz',
            'status' => 'submitted',
            'submitted_at' => now(),
            'due_at' => now()->addDay(),
            'on_time' => true,
        ]);

        $this->assertFalse($scope->canReviewLessonPlan($hod, $otherPlan));
        $this->actingAs($hod)->post(route('lesson-plans.approve', $otherPlan))->assertForbidden();
        $this->assertSame('submitted', $otherPlan->fresh()->status);
    }
}
