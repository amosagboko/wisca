<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchemeOfWork;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\Topic;
use App\Models\User;
use App\Services\SchemeOfWorkBulkUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchemeOfWorkDeleteTest extends TestCase
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

    public function test_teacher_deletes_draft_from_versions_table_and_hod_cannot(): void
    {
        foreach (['board', 'head_of_school', 'head_of_department', 'teacher'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $token = Str::lower(Str::random(8));
        $school = School::create(['name' => 'Delete School', 'slug' => 'del-'.$token, 'status' => 'active']);
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
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'JSS 1A', 'level' => 'JSS', 'status' => 'active']);
        $subject = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics', 'code' => 'MTH'.$token, 'status' => 'active']);

        $hod = User::create([
            'school_id' => $school->id,
            'name' => 'HOD',
            'email' => 'hod.'.$token.'@del.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $hod->assignRole('head_of_department');
        $teacher = User::create([
            'school_id' => $school->id,
            'name' => 'Teacher',
            'email' => 'teacher.'.$token.'@del.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');
        TeacherAssignment::create([
            'teacher_id' => $teacher->id,
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
            'uploaded_by' => $teacher->id,
            'status' => 'draft',
        ]);
        $topic = Topic::create([
            'scheme_of_work_id' => $scheme->id,
            'week_number' => 1,
            'title' => 'Created in error',
            'learning_objectives' => ['One'],
            'display_order' => 1,
            'status' => 'planned',
        ]);

        $this->actingAs($teacher)->get(route('schemes.index'))
            ->assertOk()
            ->assertSee('View')
            ->assertSee('Delete')
            ->assertSee('This action is permanent');

        $this->actingAs($hod)->delete(route('schemes.destroy', $scheme))->assertForbidden();

        $active = $scheme->replicate();
        $active->status = 'active';
        $active->version = 2;
        $active->save();

        $this->actingAs($teacher)->delete(route('schemes.destroy', $active))->assertStatus(422);

        $this->actingAs($teacher)
            ->from(route('schemes.index'))
            ->delete(route('schemes.destroy', $scheme))
            ->assertRedirect(route('schemes.index'));

        $this->assertNull(SchemeOfWork::withTrashed()->find($scheme->id));
        $this->assertFalse(Topic::where('id', $topic->id)->exists());
        $this->assertSame('active', $active->fresh()->status);
    }

    public function test_teacher_can_download_template_and_bulk_create_from_csv(): void
    {
        foreach (['head_of_department', 'teacher'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $token = Str::lower(Str::random(8));
        $school = School::create(['name' => 'Bulk School', 'slug' => 'bulk-'.$token, 'status' => 'active']);
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
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'JSS 1A', 'level' => 'JSS', 'status' => 'active']);
        $subject = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics', 'code' => 'BLK'.$token, 'status' => 'active']);
        $hod = User::create([
            'school_id' => $school->id,
            'name' => 'HOD',
            'email' => 'hod.'.$token.'@bulk.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $hod->assignRole('head_of_department');
        $teacher = User::create([
            'school_id' => $school->id,
            'name' => 'Teacher',
            'email' => 'teacher.'.$token.'@bulk.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');
        TeacherAssignment::create([
            'teacher_id' => $teacher->id,
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'academic_session_id' => $session->id,
            'status' => 'active',
        ]);

        $this->actingAs($hod)->get(route('schemes.template'))->assertForbidden();

        $download = $this->actingAs($teacher)->get(route('schemes.template'));
        $download->assertOk();
        $download->assertHeader('content-disposition');
        $this->assertStringContainsString('week_number,title,learning_objectives', $download->streamedContent());

        $csv = UploadedFile::fake()->createWithContent(
            SchemeOfWorkBulkUpload::FILENAME,
            app(SchemeOfWorkBulkUpload::class)->templateCsv()
        );

        $this->actingAs($teacher)
            ->post(route('schemes.store'), [
                'academic_session_id' => $session->id,
                'term_id' => $term->id,
                'school_class_id' => $class->id,
                'subject_id' => $subject->id,
                'bulk_template' => $csv,
                '_action' => 'save',
            ])
            ->assertRedirect();

        $scheme = SchemeOfWork::query()->where('subject_id', $subject->id)->first();
        $this->assertNotNull($scheme);
        $this->assertSame(3, $scheme->topics()->count());
        $this->assertSame('Number counting', $scheme->topics()->orderBy('week_number')->first()->title);
    }

    public function test_create_form_pairs_terms_to_the_selected_session(): void
    {
        foreach (['head_of_department', 'teacher'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $token = Str::lower(Str::random(8));
        $school = School::create(['name' => 'Term School', 'slug' => 'term-'.$token, 'status' => 'active']);
        $session = AcademicSession::create([
            'school_id' => $school->id,
            'name' => '2025/2026',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(9)->toDateString(),
            'is_current' => true,
            'status' => 'active',
        ]);
        $otherSession = AcademicSession::create([
            'school_id' => $school->id,
            'name' => '2026/2027',
            'start_date' => now()->addYear()->toDateString(),
            'end_date' => now()->addMonths(21)->toDateString(),
            'is_current' => false,
            'status' => 'upcoming',
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
        $otherTerm = Term::create([
            'academic_session_id' => $otherSession->id,
            'name' => 'First Term',
            'start_date' => $otherSession->start_date,
            'end_date' => $otherSession->end_date,
            'sequence' => 1,
            'is_current' => true,
            'status' => 'upcoming',
        ]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'JSS 1A', 'level' => 'JSS', 'status' => 'active']);
        $subject = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics', 'code' => 'TRM'.$token, 'status' => 'active']);
        $teacher = User::create([
            'school_id' => $school->id,
            'name' => 'Teacher',
            'email' => 'teacher.'.$token.'@term.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');
        TeacherAssignment::create([
            'teacher_id' => $teacher->id,
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'academic_session_id' => $session->id,
            'status' => 'active',
        ]);

        $page = $this->actingAs($teacher)->get(route('schemes.create'));
        $page->assertOk();
        $page->assertSee('Terms shown are only those that belong to the session above.');
        $page->assertSee('value="'.$term->id.'"', false);

        $this->actingAs($teacher)
            ->from(route('schemes.create'))
            ->post(route('schemes.store'), [
                'academic_session_id' => $session->id,
                'term_id' => $otherTerm->id,
                'school_class_id' => $class->id,
                'subject_id' => $subject->id,
                'topics' => [[
                    'week_number' => 1,
                    'title' => 'Number Bases',
                    'learning_objectives' => "Convert bases",
                ]],
                '_action' => 'save',
            ])
            ->assertRedirect(route('schemes.create'))
            ->assertSessionHasErrors('term_id');
    }

    public function test_index_shows_session_column_returned_label_and_locks_submitted_delete(): void
    {
        foreach (['head_of_department', 'teacher'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $token = Str::lower(Str::random(8));
        $school = School::create(['name' => 'List School', 'slug' => 'list-'.$token, 'status' => 'active']);
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
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'JSS 1A', 'level' => 'JSS', 'status' => 'active']);
        $subject = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics', 'code' => 'LST'.$token, 'status' => 'active']);
        $hod = User::create([
            'school_id' => $school->id,
            'name' => 'HOD',
            'email' => 'hod.'.$token.'@list.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $hod->assignRole('head_of_department');
        $teacher = User::create([
            'school_id' => $school->id,
            'name' => 'Teacher',
            'email' => 'teacher.'.$token.'@list.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');
        TeacherAssignment::create([
            'teacher_id' => $teacher->id,
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'academic_session_id' => $session->id,
            'status' => 'active',
        ]);
        $this->assignSectionHod($school, $hod, $class, 'Basic School '.$token, [$subject->id]);

        $returned = SchemeOfWork::create([
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'academic_session_id' => $session->id,
            'term_id' => $term->id,
            'version' => 1,
            'uploaded_by' => $teacher->id,
            'status' => 'draft',
            'rejection_reason' => 'Add week 3',
        ]);
        $submitted = SchemeOfWork::create([
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'academic_session_id' => $session->id,
            'term_id' => $term->id,
            'version' => 2,
            'uploaded_by' => $teacher->id,
            'status' => 'draft',
            'submitted_at' => now(),
            'submitted_by' => $teacher->id,
        ]);

        $this->actingAs($hod)
            ->get(route('schemes.index'))
            ->assertOk()
            ->assertSee('2025/2026')
            ->assertSee('Returned')
            ->assertSee('View')
            ->assertViewHas('schemes', fn ($pages) => $pages instanceof \Illuminate\Pagination\LengthAwarePaginator);

        $this->actingAs($teacher)
            ->from(route('schemes.index'))
            ->delete(route('schemes.destroy', $submitted))
            ->assertStatus(422);
        $this->assertNotNull($submitted->fresh());

        $this->actingAs($teacher)
            ->from(route('schemes.index'))
            ->delete(route('schemes.destroy', $returned))
            ->assertRedirect(route('schemes.index'));
        $this->assertNull(SchemeOfWork::withTrashed()->find($returned->id));
    }

    public function test_teacher_can_clone_to_matching_term_in_another_session_only(): void
    {
        foreach (['head_of_department', 'teacher'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $token = Str::lower(Str::random(8));
        $school = School::create(['name' => 'Clone School', 'slug' => 'clone-'.$token, 'status' => 'active']);
        $sourceSession = AcademicSession::create([
            'school_id' => $school->id,
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-07-31',
            'is_current' => false,
            'status' => 'closed',
        ]);
        $destSession = AcademicSession::create([
            'school_id' => $school->id,
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'is_current' => true,
            'status' => 'active',
        ]);
        $sourceFirst = Term::create([
            'academic_session_id' => $sourceSession->id,
            'name' => 'First Term',
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-15',
            'sequence' => 1,
            'is_current' => false,
            'status' => 'closed',
        ]);
        $destFirst = Term::create([
            'academic_session_id' => $destSession->id,
            'name' => 'First Term',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-15',
            'sequence' => 1,
            'is_current' => true,
            'status' => 'active',
        ]);
        $destSecond = Term::create([
            'academic_session_id' => $destSession->id,
            'name' => 'Second Term',
            'start_date' => '2027-01-07',
            'end_date' => '2027-04-10',
            'sequence' => 2,
            'is_current' => false,
            'status' => 'upcoming',
        ]);
        $closedDest = AcademicSession::create([
            'school_id' => $school->id,
            'name' => '2024/2025',
            'start_date' => '2024-09-01',
            'end_date' => '2025-07-31',
            'is_current' => false,
            'status' => 'closed',
        ]);
        $closedFirst = Term::create([
            'academic_session_id' => $closedDest->id,
            'name' => 'First Term',
            'start_date' => '2024-09-01',
            'end_date' => '2024-12-15',
            'sequence' => 1,
            'is_current' => false,
            'status' => 'closed',
        ]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'JSS 1A', 'level' => 'JSS', 'status' => 'active']);
        $subject = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics', 'code' => 'CLN'.$token, 'status' => 'active']);
        $teacher = User::create([
            'school_id' => $school->id,
            'name' => 'Teacher',
            'email' => 'teacher.'.$token.'@clone.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');
        TeacherAssignment::create([
            'teacher_id' => $teacher->id,
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'academic_session_id' => $sourceSession->id,
            'status' => 'active',
        ]);
        TeacherAssignment::create([
            'teacher_id' => $teacher->id,
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'academic_session_id' => $destSession->id,
            'status' => 'active',
        ]);

        $source = SchemeOfWork::create([
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'academic_session_id' => $sourceSession->id,
            'term_id' => $sourceFirst->id,
            'version' => 1,
            'uploaded_by' => $teacher->id,
            'status' => 'active',
        ]);
        Topic::create([
            'scheme_of_work_id' => $source->id,
            'week_number' => 1,
            'title' => 'Number counting',
            'learning_objectives' => ['Count to 20'],
            'display_order' => 1,
            'status' => 'covered',
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
            ->from(route('schemes.clone-session', $source))
            ->post(route('schemes.clone-session.store', $source), [
                'academic_session_id' => $closedDest->id,
                'term_id' => $closedFirst->id,
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
        $this->assertNull($clone->hos_approved_by);
        $this->assertSame($source->id, $clone->replaces_id);
        $this->assertSame('Number counting', $clone->topics()->first()->title);
        $this->assertSame('planned', $clone->topics()->first()->status);
        $this->assertSame('active', $source->fresh()->status);
        $this->assertSame(1, $source->topics()->count());

        $this->actingAs($teacher)
            ->from(route('schemes.clone-session', $source))
            ->post(route('schemes.clone-session.store', $source), [
                'academic_session_id' => $destSession->id,
                'term_id' => $destFirst->id,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('subject_id');
    }

    public function test_teacher_cannot_log_coverage_against_approved_scheme(): void
    {
        foreach (['head_of_department', 'teacher'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $token = Str::lower(Str::random(8));
        $school = School::create(['name' => 'Cover School', 'slug' => 'cov-'.$token, 'status' => 'active']);
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
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'JSS 1A', 'level' => 'JSS', 'status' => 'active']);
        $subject = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics', 'code' => 'COV'.$token, 'status' => 'active']);
        $hod = User::create([
            'school_id' => $school->id,
            'name' => 'HOD',
            'email' => 'hod.'.$token.'@cov.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $hod->assignRole('head_of_department');
        $teacher = User::create([
            'school_id' => $school->id,
            'name' => 'Teacher',
            'email' => 'teacher.'.$token.'@cov.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');
        \App\Models\TeacherAssignment::create([
            'teacher_id' => $teacher->id,
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
            'uploaded_by' => $hod->id,
            'status' => 'approved',
        ]);
        $topic = Topic::create([
            'scheme_of_work_id' => $scheme->id,
            'week_number' => 1,
            'title' => 'Approved only',
            'learning_objectives' => ['One'],
            'display_order' => 1,
            'status' => 'planned',
        ]);

        $this->actingAs($teacher)
            ->from(route('coverage-logs.create'))
            ->post(route('coverage-logs.store'), [
                'topic_id' => $topic->id,
                'workbook_reference' => 'WB 1',
                'coverage_date' => now()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('topic_id');
    }
}
