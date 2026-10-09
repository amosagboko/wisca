<?php

namespace Tests\Feature;

use App\Models\AcademicPeriodTransition;
use App\Models\AcademicSession;
use App\Models\School;
use App\Models\SchemeOfWork;
use App\Models\Subject;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Models\Topic;
use App\Models\User;
use App\Services\AcademicPeriodService;
use App\Services\CoverageCalculationService;
use App\Services\SchemeOfWorkService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicPeriodLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not available in this PHP build.');
        }

        parent::setUp();
    }

    /**
     * @return array<string, mixed>
     */
    protected function world(): array
    {
        foreach (['board', 'head_of_school', 'assistant_head_secondary', 'head_of_department', 'teacher', 'admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $school = School::create(['name' => 'Period School', 'slug' => 'period-school', 'status' => 'active']);
        $session = AcademicSession::create([
            'school_id' => $school->id,
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-07-31',
            'is_current' => true,
            'status' => 'active',
        ]);

        $terms = [];
        foreach ([
            [1, 'First Term', '2025-09-01', '2025-12-15', true, 'active'],
            [2, 'Second Term', '2026-01-07', '2026-04-10', false, 'upcoming'],
            [3, 'Third Term', '2026-04-20', '2026-07-31', false, 'upcoming'],
        ] as [$sequence, $name, $start, $end, $current, $status]) {
            $terms[$sequence] = Term::create([
                'academic_session_id' => $session->id,
                'name' => $name,
                'start_date' => $start,
                'end_date' => $end,
                'sequence' => $sequence,
                'is_current' => $current,
                'status' => $status,
            ]);
        }

        $users = [];
        foreach (['admin' => 'admin@p.test', 'head_of_school' => 'hos@p.test', 'assistant_head_secondary' => 'ah@p.test', 'board' => 'board@p.test', 'teacher' => 'teacher@p.test', 'head_of_department' => 'hod@p.test'] as $role => $email) {
            $user = User::create([
                'school_id' => $school->id,
                'name' => $role,
                'email' => $email,
                'password' => 'password',
                'status' => 'active',
            ]);
            $user->assignRole($role);
            $users[$role] = $user;
        }

        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'JSS 1A', 'level' => 'JSS', 'status' => 'active']);
        $subject = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics', 'code' => 'MTH', 'status' => 'active']);
        $scheme = SchemeOfWork::create([
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'academic_session_id' => $session->id,
            'term_id' => $terms[1]->id,
            'version' => 1,
            'uploaded_by' => $users['head_of_department']->id,
            'status' => 'active',
        ]);
        Topic::create([
            'scheme_of_work_id' => $scheme->id,
            'week_number' => 1,
            'title' => 'Number Bases',
            'learning_objectives' => ['Convert bases'],
            'display_order' => 1,
        ]);

        return compact('school', 'session', 'terms', 'users', 'scheme');
    }

    public function test_authorized_roles_can_create_future_session_and_terms(): void
    {
        $world = $this->world();

        $this->actingAs($world['users']['head_of_school'])
            ->post(route('academic-period.sessions.store'), [
                'name' => '2026/2027',
                'start_date' => '2026-09-01',
                'end_date' => '2027-07-31',
            ])
            ->assertRedirect();

        $future = AcademicSession::where('name', '2026/2027')->first();
        $this->assertFalse($future->is_current);
        $this->assertSame('upcoming', $future->status);
        $this->assertTrue($world['session']->fresh()->is_current);

        $this->actingAs($world['users']['board'])
            ->post(route('academic-period.terms.store'), [
                'academic_session_id' => $future->id,
                'name' => 'First Term',
                'start_date' => '2026-09-01',
                'end_date' => '2026-12-15',
                'sequence' => 1,
            ])
            ->assertRedirect();

        $this->assertSame(1, $future->terms()->count());
        $this->assertSame(1, AcademicSession::where('school_id', $world['school']->id)->where('is_current', true)->count());
    }

    public function test_head_of_school_and_board_can_transition_via_http(): void
    {
        $world = $this->world();

        $this->actingAs($world['users']['head_of_school'])
            ->post(route('academic-period.transition'), ['notes' => 'Move to second term'])
            ->assertRedirect(route('academic-period.show'));

        $this->assertTrue($world['terms'][2]->fresh()->is_current);
        $this->assertSame('closed', $world['terms'][1]->fresh()->status);
        $this->assertTrue($world['session']->fresh()->is_current);

        $this->actingAs($world['users']['board'])
            ->post(route('academic-period.transition'))
            ->assertRedirect(route('academic-period.show'));

        $this->assertTrue($world['terms'][3]->fresh()->is_current);
        $this->assertSame('closed', $world['terms'][2]->fresh()->status);
    }

    public function test_term_transitions_are_sequential_and_audited(): void
    {
        $world = $this->world();
        $service = app(AcademicPeriodService::class);
        $hos = $world['users']['head_of_school'];

        $service->transitionToNextTerm($hos, 'T1 to T2');
        $this->assertTrue($world['terms'][2]->fresh()->is_current);
        $this->assertSame('closed', $world['terms'][1]->fresh()->status);
        $this->assertTrue($world['session']->fresh()->is_current);

        $service->transitionToNextTerm($hos, 'T2 to T3');
        $this->assertTrue($world['terms'][3]->fresh()->is_current);
        $this->assertSame('closed', $world['terms'][2]->fresh()->status);

        $this->assertSame(2, AcademicPeriodTransition::where('action', 'term_transition')->count());
        $this->assertSame($world['terms'][3]->id, AcademicPeriodTransition::latest('id')->first()->new_term_id);

        $this->expectException(ValidationException::class);
        $service->transitionToNextTerm($hos);
    }

    public function test_term_skip_and_cross_session_transition_are_rejected(): void
    {
        $world = $this->world();
        $other = AcademicSession::create([
            'school_id' => $world['school']->id,
            'name' => 'Other',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'status' => 'upcoming',
            'is_current' => false,
        ]);
        $foreign = Term::create([
            'academic_session_id' => $other->id,
            'name' => 'First Term',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-15',
            'sequence' => 2,
            'status' => 'upcoming',
            'is_current' => false,
        ]);

        $service = app(AcademicPeriodService::class);
        $this->assertNotSame((int) $world['session']->id, (int) $foreign->academic_session_id);
        $next = $service->nextTerm($world['terms'][1]);
        $this->assertSame(2, $next->sequence);
        $this->assertSame((int) $world['session']->id, (int) $next->academic_session_id);

        $world['terms'][2]->delete();
        $this->assertNull($service->nextTerm($world['terms'][1]->fresh()));

        try {
            $service->transitionToNextTerm($world['users']['admin']);
            $this->fail('Skipping Term 1 to Term 3 must be rejected.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertTrue($world['terms'][1]->fresh()->is_current);
        $this->assertFalse($world['terms'][3]->fresh()->is_current);
        $this->assertTrue($world['session']->fresh()->is_current);
    }

    public function test_database_allows_only_one_current_session_and_term(): void
    {
        $world = $this->world();

        try {
            AcademicSession::create([
                'school_id' => $world['school']->id,
                'name' => 'Second Current',
                'start_date' => '2026-09-01',
                'end_date' => '2027-07-31',
                'status' => 'active',
                'is_current' => true,
            ]);
            $this->fail('A second current session for the same school must be rejected.');
        } catch (QueryException) {
            // expected
        }

        try {
            Term::create([
                'academic_session_id' => $world['session']->id,
                'name' => 'Also Current',
                'start_date' => '2026-01-01',
                'end_date' => '2026-03-01',
                'sequence' => 9,
                'status' => 'active',
                'is_current' => true,
            ]);
            $this->fail('A second current term for the same session must be rejected.');
        } catch (QueryException) {
            // expected
        }

        $this->assertSame(1, AcademicSession::where('school_id', $world['school']->id)->where('is_current', true)->count());
        $this->assertSame(1, Term::where('academic_session_id', $world['session']->id)->where('is_current', true)->count());
    }

    public function test_session_rollover_preserves_historical_foreign_keys(): void
    {
        $world = $this->world();
        $service = app(AcademicPeriodService::class);
        $admin = $world['users']['admin'];

        $service->transitionToNextTerm($admin);
        $service->transitionToNextTerm($admin);

        $target = AcademicSession::create([
            'school_id' => $world['school']->id,
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'status' => 'upcoming',
            'is_current' => false,
        ]);
        $start = Term::create([
            'academic_session_id' => $target->id,
            'name' => 'First Term',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-15',
            'sequence' => 1,
            'status' => 'upcoming',
            'is_current' => false,
        ]);

        $schemeId = $world['scheme']->id;
        $oldSessionId = $world['session']->id;
        $oldTermId = $world['terms'][1]->id;

        $service->rolloverToSession($admin, $target, $start, 'Year end');

        $this->assertFalse($world['session']->fresh()->is_current);
        $this->assertSame('closed', $world['session']->fresh()->status);
        $this->assertTrue($target->fresh()->is_current);
        $this->assertTrue($start->fresh()->is_current);
        $this->assertSame(1, AcademicSession::where('school_id', $world['school']->id)->where('is_current', true)->count());
        $this->assertSame(1, Term::where('academic_session_id', $target->id)->where('is_current', true)->count());

        $scheme = SchemeOfWork::find($schemeId);
        $this->assertSame($oldSessionId, $scheme->academic_session_id);
        $this->assertSame($oldTermId, $scheme->term_id);
        $this->assertSame($schemeId, $scheme->topics()->first()->scheme_of_work_id);

        $audit = AcademicPeriodTransition::latest('id')->first();
        $this->assertSame('session_rollover', $audit->action);
        $this->assertSame($admin->id, $audit->performed_by);
        $this->assertSame($oldSessionId, $audit->previous_session_id);
        $this->assertSame($target->id, $audit->new_session_id);
    }

    public function test_double_submit_and_teacher_are_rejected(): void
    {
        $world = $this->world();
        $service = app(AcademicPeriodService::class);
        $service->transitionToNextTerm($world['users']['board']);

        $this->actingAs($world['users']['admin'])->get(route('academic-period.show'))->assertOk();
        $this->actingAs($world['users']['head_of_school'])->get(route('academic-period.show'))->assertOk()->assertSee('Transition term');
        $this->actingAs($world['users']['board'])->get(route('academic-period.show'))->assertOk();
        $this->actingAs($world['users']['assistant_head_secondary'])->get(route('academic-period.show'))->assertOk();

        $this->actingAs($world['users']['teacher'])
            ->get(route('academic-period.show'))
            ->assertForbidden();

        $this->actingAs($world['users']['teacher'])
            ->post(route('academic-period.transition'))
            ->assertForbidden();

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('academic-period.show'))
            ->assertForbidden();

        $this->expectException(ValidationException::class);
        $service->rolloverToSession($world['users']['admin'], $world['session']);
    }

    public function test_closed_period_blocks_new_scheme_and_failed_rollover_rolls_back(): void
    {
        $world = $this->world();
        $service = app(AcademicPeriodService::class);
        $service->transitionToNextTerm($world['users']['admin']);
        $service->transitionToNextTerm($world['users']['admin']);

        $this->assertFalse($service->acceptsNewActivity($world['session']->fresh(), $world['terms'][1]->fresh()));

        try {
            app(SchemeOfWorkService::class)->createDraft($world['users']['head_of_department'], [
                'academic_session_id' => $world['session']->id,
                'term_id' => $world['terms'][1]->id,
                'school_class_id' => $world['scheme']->school_class_id,
                'subject_id' => $world['scheme']->subject_id,
            ], [[
                'week_number' => 1,
                'title' => 'Closed period',
                'learning_objectives' => ['Should not save'],
            ]]);
            $this->fail('Creating a scheme against a closed term must fail.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(1, SchemeOfWork::count());

        $empty = AcademicSession::create([
            'school_id' => $world['school']->id,
            'name' => 'Empty',
            'start_date' => '2027-09-01',
            'end_date' => '2028-07-31',
            'status' => 'upcoming',
            'is_current' => false,
        ]);

        try {
            $service->rolloverToSession($world['users']['admin'], $empty);
            $this->fail('Rollover into a session with no terms must fail.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertTrue($world['session']->fresh()->is_current);
        $this->assertTrue($world['terms'][3]->fresh()->is_current);
        $this->assertFalse($empty->fresh()->is_current);
    }

    public function test_ae01_and_due_date_helpers_are_unchanged(): void
    {
        $source = file_get_contents((new \ReflectionClass(CoverageCalculationService::class))->getFileName());
        $this->assertStringContainsString("whereIn('status', ['active', 'approved'])", $source);

        $term = new Term(['start_date' => '2025-09-01']);
        $this->assertTrue($term->lessonPlanDueAt(1)->isMonday());
    }
}
