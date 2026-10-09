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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchemeOfWorkWorkflowTest extends TestCase
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
        foreach (['board', 'head_of_school', 'head_of_department', 'teacher', 'admin', 'assistant_head_secondary'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $school = School::create([
            'name' => 'WISCA Test',
            'slug' => 'wisca-test',
            'status' => 'active',
        ]);
        $otherSchool = School::create([
            'name' => 'Other School',
            'slug' => 'other-school',
            'status' => 'active',
        ]);

        $session = AcademicSession::create([
            'school_id' => $school->id,
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-07-31',
            'is_current' => true,
            'status' => 'active',
        ]);
        $term = Term::create([
            'academic_session_id' => $session->id,
            'name' => 'First Term',
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-15',
            'sequence' => 1,
            'status' => 'active',
            'is_current' => true,
        ]);
        $class = SchoolClass::create([
            'school_id' => $school->id,
            'name' => 'JSS 1A',
            'level' => 'JSS',
            'display_order' => 1,
            'status' => 'active',
        ]);
        $math = Subject::create([
            'school_id' => $school->id,
            'name' => 'Mathematics',
            'code' => 'MTH',
            'status' => 'active',
        ]);
        $outside = Subject::create([
            'school_id' => $otherSchool->id,
            'name' => 'English',
            'code' => 'ENG',
            'status' => 'active',
        ]);

        $users = [];
        foreach ([
            'board' => 'board@test.com',
            'head_of_school' => 'hos@test.com',
            'head_of_department' => 'hod@test.com',
            'teacher' => 'teacher@test.com',
        ] as $role => $email) {
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

        TeacherAssignment::create([
            'teacher_id' => $users['teacher']->id,
            'school_class_id' => $class->id,
            'subject_id' => $math->id,
            'academic_session_id' => $session->id,
            'status' => 'active',
        ]);

        return compact('school', 'otherSchool', 'session', 'term', 'class', 'math', 'outside', 'users');
    }

    /**
     * @param  array<string, mixed>  $world
     * @return array<string, mixed>
     */
    protected function draftPayload(array $world, ?int $subjectId = null): array
    {
        return [
            'academic_session_id' => $world['session']->id,
            'term_id' => $world['term']->id,
            'school_class_id' => $world['class']->id,
            'subject_id' => $subjectId ?? $world['math']->id,
            'topics' => [
                [
                    'week_number' => 1,
                    'title' => 'Number Bases',
                    'learning_objectives' => "Convert between bases\nApply place value",
                ],
            ],
            '_action' => 'save',
        ];
    }

    public function test_hod_can_create_clone_and_submit_but_cannot_approve_or_activate(): void
    {
        $world = $this->world();
        $hod = $world['users']['head_of_department'];

        $this->actingAs($hod)
            ->post(route('schemes.store'), $this->draftPayload($world))
            ->assertRedirect();

        $draft = SchemeOfWork::first();
        $this->assertSame('draft', $draft->status);
        $this->assertNull($draft->submitted_at);
        $this->assertSame(1, $draft->topics()->count());

        $this->actingAs($hod)->post(route('schemes.submit', $draft))->assertRedirect();
        $this->assertNotNull($draft->fresh()->submitted_at);
        $this->assertSame('draft', $draft->fresh()->status);

        $this->actingAs($hod)->post(route('schemes.approve', $draft))->assertForbidden();
        $this->actingAs($hod)->post(route('schemes.activate', $draft))->assertForbidden();
    }

    public function test_hod_cannot_create_outside_department_subjects(): void
    {
        $world = $this->world();

        $this->actingAs($world['users']['head_of_department'])
            ->from(route('schemes.create'))
            ->post(route('schemes.store'), $this->draftPayload($world, $world['outside']->id))
            ->assertRedirect(route('schemes.create'))
            ->assertSessionHasErrors('subject_id');

        $this->assertSame(0, SchemeOfWork::count());
    }

    public function test_board_cannot_approve_before_hos_and_hos_alone_does_not_approve(): void
    {
        $world = $this->world();
        $hod = $world['users']['head_of_department'];
        $hos = $world['users']['head_of_school'];
        $board = $world['users']['board'];

        $this->actingAs($hod)->post(route('schemes.store'), $this->draftPayload($world) + ['_action' => 'submit']);
        $scheme = SchemeOfWork::first();

        $this->actingAs($board)->post(route('schemes.approve', $scheme))->assertStatus(422);
        $this->assertSame('draft', $scheme->fresh()->status);

        $this->actingAs($hos)->post(route('schemes.approve', $scheme))->assertRedirect();
        $afterHos = $scheme->fresh();
        $this->assertSame('draft', $afterHos->status);
        $this->assertNotNull($afterHos->hos_approved_at);
        $this->assertNull($afterHos->board_approved_at);

        $this->actingAs($board)->post(route('schemes.approve', $scheme))->assertRedirect();
        $approved = $scheme->fresh();
        $this->assertSame('approved', $approved->status);
        $this->assertNotNull($approved->board_approved_at);
        $this->assertTrue($approved->approved_at->equalTo($approved->board_approved_at));
    }

    public function test_reject_from_submitted_or_approved_returns_draft_and_hod_can_resubmit(): void
    {
        $world = $this->world();
        $hod = $world['users']['head_of_department'];
        $hos = $world['users']['head_of_school'];
        $board = $world['users']['board'];

        $this->actingAs($hod)->post(route('schemes.store'), $this->draftPayload($world) + ['_action' => 'submit']);
        $scheme = SchemeOfWork::first();

        $this->actingAs($hos)
            ->from(route('schemes.show', $scheme))
            ->post(route('schemes.reject', $scheme), ['rejection_reason' => 'Add week 2'])
            ->assertRedirect();

        $rejected = $scheme->fresh();
        $this->assertSame('draft', $rejected->status);
        $this->assertNull($rejected->submitted_at);
        $this->assertSame('Add week 2', $rejected->rejection_reason);
        $this->assertNotNull($rejected->rejected_at);

        $this->actingAs($hod)->post(route('schemes.submit', $rejected))->assertRedirect();
        $this->actingAs($hos)->post(route('schemes.approve', $rejected))->assertRedirect();
        $this->actingAs($board)->post(route('schemes.approve', $rejected))->assertRedirect();

        $this->actingAs($board)
            ->post(route('schemes.reject', $rejected), ['rejection_reason' => 'Board wants another week'])
            ->assertRedirect();

        $again = $rejected->fresh();
        $this->assertSame('draft', $again->status);
        $this->assertNull($again->hos_approved_by);
        $this->assertNull($again->board_approved_by);
        $this->assertSame('Board wants another week', $again->rejection_reason);

        $this->actingAs($hod)->post(route('schemes.submit', $again))->assertRedirect();
        $this->assertNotNull($again->fresh()->submitted_at);
    }

    public function test_board_activate_archives_previous_and_clone_does_not_reactivate(): void
    {
        $world = $this->world();
        $first = $this->activateScheme($world, 'Version one');

        $this->actingAs($world['users']['head_of_department'])
            ->post(route('schemes.clone', $first))
            ->assertRedirect();

        $clone = SchemeOfWork::query()->where('id', '!=', $first->id)->first();
        $this->assertSame('draft', $clone->status);
        $this->assertSame($first->id, $clone->replaces_id);
        $this->assertSame(2, $clone->version);
        $this->assertSame('active', $first->fresh()->status);
        $this->assertNotSame($first->topics()->first()->id, $clone->topics()->first()->id);

        $this->actingAs($world['users']['head_of_department'])->post(route('schemes.submit', $clone));
        $this->actingAs($world['users']['head_of_school'])->post(route('schemes.approve', $clone));
        $this->actingAs($world['users']['board'])->post(route('schemes.approve', $clone));
        $this->actingAs($world['users']['board'])->post(route('schemes.activate', $clone))->assertRedirect();

        $this->assertSame('archived', $first->fresh()->status);
        $this->assertSame('active', $clone->fresh()->status);
        $this->assertSame(1, SchemeOfWork::where('status', 'active')->count());
        $this->assertTrue(Topic::where('scheme_of_work_id', $first->id)->exists());
    }

    public function test_teacher_can_view_active_but_cannot_update(): void
    {
        $world = $this->world();
        $scheme = $this->activateScheme($world, 'Active maths');
        $teacher = $world['users']['teacher'];

        $this->actingAs($teacher)->get(route('schemes.show', $scheme))->assertOk();
        $this->actingAs($teacher)->get(route('schemes.edit', $scheme))->assertForbidden();
        $this->actingAs($teacher)
            ->put(route('schemes.update', $scheme), $this->draftPayload($world))
            ->assertForbidden();
        $this->actingAs($teacher)->post(route('schemes.approve', $scheme))->assertForbidden();
        $this->actingAs($teacher)->delete(route('schemes.destroy', $scheme))->assertForbidden();
    }

    public function test_hod_can_permanently_delete_erroneous_draft_but_not_active(): void
    {
        $world = $this->world();
        $hod = $world['users']['head_of_department'];

        $this->actingAs($hod)->post(route('schemes.store'), $this->draftPayload($world));
        $draft = SchemeOfWork::first();
        $topicId = $draft->topics()->first()->id;

        $this->actingAs($world['users']['teacher'])
            ->delete(route('schemes.destroy', $draft))
            ->assertForbidden();
        $this->assertNotNull($draft->fresh());

        $this->actingAs($hod)
            ->from(route('schemes.index'))
            ->delete(route('schemes.destroy', $draft))
            ->assertRedirect(route('schemes.index'));

        $this->assertNull(SchemeOfWork::withTrashed()->find($draft->id));
        $this->assertFalse(Topic::where('id', $topicId)->exists());

        $active = $this->activateScheme($world, 'Keep this baseline');
        $this->actingAs($hod)->delete(route('schemes.destroy', $active))->assertStatus(422);
        $this->assertSame('active', $active->fresh()->status);
    }

    public function test_lesson_plan_create_requires_active_scheme_topic(): void
    {
        $world = $this->world();
        $approved = $this->makeApprovedScheme($world, 'Approved only');
        $topic = $approved->topics()->first();
        $teacher = $world['users']['teacher'];

        $this->actingAs($teacher)
            ->from(route('lesson-plans.create'))
            ->post(route('lesson-plans.store'), [
                'topic_id' => $topic->id,
                'objectives' => 'Know number bases',
                'activities' => 'Worked examples',
                'assessment' => 'Exit ticket',
            ])
            ->assertStatus(422);
    }

    /**
     * @param  array<string, mixed>  $world
     */
    protected function makeApprovedScheme(array $world, string $title): SchemeOfWork
    {
        $payload = $this->draftPayload($world);
        $payload['topics'][0]['title'] = $title;
        $payload['_action'] = 'submit';

        $this->actingAs($world['users']['head_of_department'])->post(route('schemes.store'), $payload);
        $scheme = SchemeOfWork::query()->latest('id')->first();
        $this->actingAs($world['users']['head_of_school'])->post(route('schemes.approve', $scheme));
        $this->actingAs($world['users']['board'])->post(route('schemes.approve', $scheme));

        return $scheme->fresh(['topics']);
    }

    /**
     * @param  array<string, mixed>  $world
     */
    protected function activateScheme(array $world, string $title): SchemeOfWork
    {
        $scheme = $this->makeApprovedScheme($world, $title);
        $this->actingAs($world['users']['board'])->post(route('schemes.activate', $scheme));

        return $scheme->fresh(['topics']);
    }
}
