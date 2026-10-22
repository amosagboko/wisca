<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\SchemeOfWork;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use App\Support\WiscaNavigation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherAssignmentSectionTest extends TestCase
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
        foreach (['admin', 'teacher', 'head_of_department', 'head_of_school'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $token = Str::lower(Str::random(8));
        $school = School::create(['name' => 'Assign School', 'slug' => 'asg-'.$token, 'status' => 'active']);
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

        $basicClass = SchoolClass::create(['school_id' => $school->id, 'name' => 'Basic 1 '.$token, 'level' => 'Basic', 'status' => 'active']);
        $eyfsClass = SchoolClass::create(['school_id' => $school->id, 'name' => 'Nursery '.$token, 'level' => 'EYFS', 'status' => 'active']);
        $unassigned = SchoolClass::create(['school_id' => $school->id, 'name' => 'JSS 1A '.$token, 'level' => 'JSS', 'status' => 'active']);
        $noHodClass = SchoolClass::create(['school_id' => $school->id, 'name' => 'Orphan '.$token, 'level' => 'JSS', 'status' => 'active']);

        $math = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics '.$token, 'code' => 'MTH'.$token, 'status' => 'active']);
        $english = Subject::create(['school_id' => $school->id, 'name' => 'English '.$token, 'code' => 'ENG'.$token, 'status' => 'active']);
        $science = Subject::create(['school_id' => $school->id, 'name' => 'Basic Science '.$token, 'code' => 'BSC'.$token, 'status' => 'active']);
        $civic = Subject::create(['school_id' => $school->id, 'name' => 'Civic '.$token, 'code' => 'CIV'.$token, 'status' => 'active']);

        $basicClass->offeredSubjects()->syncWithoutDetaching([$math->id, $english->id, $science->id, $civic->id]);
        $eyfsClass->offeredSubjects()->syncWithoutDetaching([$math->id]);
        $unassigned->offeredSubjects()->syncWithoutDetaching([$math->id]);
        $noHodClass->offeredSubjects()->syncWithoutDetaching([$math->id]);

        $admin = User::create([
            'school_id' => $school->id,
            'name' => 'Admin '.$token,
            'email' => 'admin.'.$token.'@asg.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $admin->assignRole('admin');

        $teacher = User::create([
            'school_id' => $school->id,
            'name' => 'Ada Teacher '.$token,
            'email' => 'teacher.'.$token.'@asg.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');

        $hos = User::create([
            'school_id' => $school->id,
            'name' => 'HoS '.$token,
            'email' => 'hos.'.$token.'@asg.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $hos->assignRole('head_of_school');

        $basicHod = User::create([
            'school_id' => $school->id,
            'name' => 'Basic HOD '.$token,
            'email' => 'basic.'.$token.'@asg.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $basicHod->assignRole('head_of_department');

        $eyfsHod = User::create([
            'school_id' => $school->id,
            'name' => 'EYFS HOD '.$token,
            'email' => 'eyfs.'.$token.'@asg.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $eyfsHod->assignRole('head_of_department');

        $basicSection = $this->assignSectionHod($school, $basicHod, $basicClass, 'Basic School '.$token, [$math->id, $english->id, $science->id, $civic->id]);
        $eyfsSection = $this->assignSectionHod($school, $eyfsHod, $eyfsClass, 'EYFS '.$token, [$math->id]);

        $noHodSection = SchoolSection::create([
            'school_id' => $school->id,
            'name' => 'No HOD '.$token,
            'status' => 'active',
            'hod_id' => null,
        ]);
        $noHodClass->update(['school_section_id' => $noHodSection->id]);

        return compact(
            'token', 'school', 'session', 'term',
            'basicClass', 'eyfsClass', 'unassigned', 'noHodClass',
            'math', 'english', 'science', 'civic',
            'admin', 'teacher', 'hos', 'basicHod', 'eyfsHod',
            'basicSection', 'eyfsSection', 'noHodSection',
        );
    }

    /**
     * @param  array<string, mixed>  $world
     * @return array<string, mixed>
     */
    protected function assignmentPayload(array $world, array $overrides = []): array
    {
        return array_merge([
            'teacher_id' => $world['teacher']->id,
            'school_section_id' => $world['basicSection']->id,
            'school_class_id' => $world['basicClass']->id,
            'subject_ids' => [$world['math']->id, $world['english']->id, $world['science']->id],
            'academic_session_id' => $world['session']->id,
            'status' => 'active',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $world
     * @return array<string, mixed>
     */
    protected function draftPayload(array $world, int $classId, int $subjectId): array
    {
        return [
            'academic_session_id' => $world['session']->id,
            'term_id' => $world['term']->id,
            'school_class_id' => $classId,
            'subject_id' => $subjectId,
            'topics' => [[
                'week_number' => 1,
                'title' => 'Topic '.$subjectId,
                'learning_objectives' => "Learn\nPractise",
            ]],
        ];
    }

    public function test_hos_sees_teacher_assignments_menu_and_can_open_it(): void
    {
        $world = $this->world();

        $labels = collect(WiscaNavigation::groups($world['hos']))
            ->flatMap(fn (array $group) => collect($group['items'] ?? [])->pluck('label'))
            ->all();
        $this->assertContains('Teacher Assignments', $labels);

        $teacherLabels = collect(WiscaNavigation::groups($world['teacher']))
            ->flatMap(fn (array $group) => collect($group['items'] ?? [])->pluck('label'))
            ->all();
        $this->assertNotContains('Teacher Assignments', $teacherLabels);

        $this->actingAs($world['hos'])
            ->get(route('admin.assignments.index'))
            ->assertOk()
            ->assertSee('Teacher Assignments');

        $this->actingAs($world['teacher'])
            ->get(route('admin.assignments.index'))
            ->assertForbidden();
    }

    public function test_create_form_shows_section_and_read_only_hod(): void
    {
        $world = $this->world();

        $this->actingAs($world['admin'])
            ->get(route('admin.assignments.create'))
            ->assertOk()
            ->assertSee('School section')
            ->assertSee('Head of Department')
            ->assertSee('Subjects')
            ->assertSee($world['basicSection']->name)
            ->assertSee($world['eyfsSection']->name);
    }

    public function test_admin_can_assign_one_teacher_to_multiple_subjects_in_one_class(): void
    {
        $world = $this->world();

        $this->actingAs($world['admin'])
            ->post(route('admin.assignments.store'), $this->assignmentPayload($world))
            ->assertRedirect(route('admin.assignments.index'))
            ->assertSessionHas('success');

        $rows = TeacherAssignment::query()
            ->where('teacher_id', $world['teacher']->id)
            ->where('school_class_id', $world['basicClass']->id)
            ->where('academic_session_id', $world['session']->id)
            ->get();

        $this->assertCount(3, $rows);
        $this->assertEqualsCanonicalizing(
            [$world['math']->id, $world['english']->id, $world['science']->id],
            $rows->pluck('subject_id')->all()
        );
    }

    public function test_same_teacher_can_be_assigned_across_classes_and_sections(): void
    {
        $world = $this->world();

        $this->actingAs($world['admin'])
            ->post(route('admin.assignments.store'), $this->assignmentPayload($world))
            ->assertRedirect(route('admin.assignments.index'));

        $this->actingAs($world['admin'])
            ->post(route('admin.assignments.store'), $this->assignmentPayload($world, [
                'school_section_id' => $world['eyfsSection']->id,
                'school_class_id' => $world['eyfsClass']->id,
                'subject_ids' => [$world['math']->id],
            ]))
            ->assertRedirect(route('admin.assignments.index'));

        $this->assertSame(4, TeacherAssignment::query()
            ->where('teacher_id', $world['teacher']->id)
            ->where('academic_session_id', $world['session']->id)
            ->count());
    }

    public function test_duplicate_class_subject_session_is_rejected(): void
    {
        $world = $this->world();

        $this->actingAs($world['admin'])
            ->post(route('admin.assignments.store'), $this->assignmentPayload($world, [
                'subject_ids' => [$world['math']->id],
            ]))
            ->assertRedirect(route('admin.assignments.index'));

        $this->actingAs($world['admin'])
            ->from(route('admin.assignments.create'))
            ->post(route('admin.assignments.store'), $this->assignmentPayload($world, [
                'subject_ids' => [$world['math']->id],
            ]))
            ->assertRedirect(route('admin.assignments.create'))
            ->assertSessionHasErrors('subject_ids');

        $this->assertSame(1, TeacherAssignment::query()
            ->where('school_class_id', $world['basicClass']->id)
            ->where('subject_id', $world['math']->id)
            ->where('academic_session_id', $world['session']->id)
            ->count());
    }

    public function test_new_assignment_requires_class_in_section_with_hod(): void
    {
        $world = $this->world();

        $this->actingAs($world['admin'])
            ->from(route('admin.assignments.create'))
            ->post(route('admin.assignments.store'), $this->assignmentPayload($world, [
                'school_section_id' => $world['basicSection']->id,
                'school_class_id' => $world['unassigned']->id,
                'subject_ids' => [$world['math']->id],
            ]))
            ->assertRedirect(route('admin.assignments.create'))
            ->assertSessionHasErrors('school_class_id');

        $this->actingAs($world['admin'])
            ->from(route('admin.assignments.create'))
            ->post(route('admin.assignments.store'), $this->assignmentPayload($world, [
                'school_section_id' => $world['noHodSection']->id,
                'school_class_id' => $world['noHodClass']->id,
                'subject_ids' => [$world['math']->id],
            ]))
            ->assertRedirect(route('admin.assignments.create'))
            ->assertSessionHasErrors('school_section_id');

        $this->assertSame(0, TeacherAssignment::query()->where('academic_session_id', $world['session']->id)->count());
    }

    public function test_index_shows_section_and_hod_and_filters(): void
    {
        $world = $this->world();

        TeacherAssignment::create([
            'teacher_id' => $world['teacher']->id,
            'school_class_id' => $world['basicClass']->id,
            'subject_id' => $world['math']->id,
            'academic_session_id' => $world['session']->id,
            'status' => 'active',
        ]);
        TeacherAssignment::create([
            'teacher_id' => $world['teacher']->id,
            'school_class_id' => $world['eyfsClass']->id,
            'subject_id' => $world['math']->id,
            'academic_session_id' => $world['session']->id,
            'status' => 'active',
        ]);
        TeacherAssignment::create([
            'teacher_id' => $world['teacher']->id,
            'school_class_id' => $world['unassigned']->id,
            'subject_id' => $world['math']->id,
            'academic_session_id' => $world['session']->id,
            'status' => 'active',
        ]);

        $this->actingAs($world['admin'])
            ->get(route('admin.assignments.index', ['teacher_id' => $world['teacher']->id]))
            ->assertOk()
            ->assertSee($world['basicSection']->name)
            ->assertSee($world['basicHod']->name)
            ->assertSee($world['eyfsSection']->name)
            ->assertSee($world['eyfsHod']->name)
            ->assertSee('Unassigned')
            ->assertSee($world['basicClass']->name)
            ->assertSee('All assignments (3)');

        $this->actingAs($world['admin'])
            ->get(route('admin.assignments.index', [
                'teacher_id' => $world['teacher']->id,
                'section_id' => $world['basicSection']->id,
            ]))
            ->assertOk()
            ->assertSee($world['basicClass']->name)
            ->assertSee('All assignments (1)');

        $this->actingAs($world['admin'])
            ->get(route('admin.assignments.index', [
                'teacher_id' => $world['teacher']->id,
                'hod_id' => $world['eyfsHod']->id,
            ]))
            ->assertOk()
            ->assertSee($world['eyfsClass']->name)
            ->assertSee('All assignments (1)');
    }

    public function test_edit_stays_single_subject_and_can_update_unassigned_row(): void
    {
        $world = $this->world();

        $row = TeacherAssignment::create([
            'teacher_id' => $world['teacher']->id,
            'school_class_id' => $world['unassigned']->id,
            'subject_id' => $world['math']->id,
            'academic_session_id' => $world['session']->id,
            'status' => 'active',
        ]);

        $this->actingAs($world['admin'])
            ->get(route('admin.assignments.edit', $row))
            ->assertOk()
            ->assertSee('name="subject_id"', false)
            ->assertDontSee('name="subject_ids[]"', false);

        $this->actingAs($world['admin'])
            ->put(route('admin.assignments.update', $row), [
                'teacher_id' => $world['teacher']->id,
                'school_class_id' => $world['unassigned']->id,
                'subject_id' => $world['math']->id,
                'academic_session_id' => $world['session']->id,
                'status' => 'inactive',
            ])
            ->assertRedirect(route('admin.assignments.index'));

        $this->assertSame('inactive', $row->fresh()->status);
    }

    public function test_teacher_can_prepare_sow_for_each_assigned_subject_only(): void
    {
        $world = $this->world();

        $this->actingAs($world['admin'])
            ->post(route('admin.assignments.store'), $this->assignmentPayload($world))
            ->assertRedirect(route('admin.assignments.index'));

        foreach ([$world['math'], $world['english'], $world['science']] as $subject) {
            $this->actingAs($world['teacher'])
                ->post(route('schemes.store'), $this->draftPayload($world, $world['basicClass']->id, $subject->id))
                ->assertRedirect();

            $this->assertNotNull(SchemeOfWork::query()
                ->where('academic_session_id', $world['session']->id)
                ->where('school_class_id', $world['basicClass']->id)
                ->where('subject_id', $subject->id)
                ->where('uploaded_by', $world['teacher']->id)
                ->first());
        }

        $this->actingAs($world['teacher'])
            ->from(route('schemes.create'))
            ->post(route('schemes.store'), $this->draftPayload($world, $world['basicClass']->id, $world['civic']->id))
            ->assertRedirect(route('schemes.create'))
            ->assertSessionHasErrors('subject_id');

        $this->assertSame(0, SchemeOfWork::query()
            ->where('academic_session_id', $world['session']->id)
            ->where('subject_id', $world['civic']->id)
            ->count());
    }
}
