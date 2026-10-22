<?php

namespace Tests\Feature;

use App\Models\AcademicPeriodTransition;
use App\Models\AcademicSession;
use App\Models\School;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSessionActivationTest extends TestCase
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
        foreach (['admin', 'head_of_school', 'board'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $token = Str::lower(Str::random(8));
        $school = School::create([
            'name' => 'Session School',
            'slug' => 'session-school-'.$token,
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
        Term::create([
            'academic_session_id' => $session->id,
            'name' => 'First Term',
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-15',
            'sequence' => 1,
            'is_current' => true,
            'status' => 'active',
        ]);
        Term::create([
            'academic_session_id' => $session->id,
            'name' => 'Second Term',
            'start_date' => '2026-01-07',
            'end_date' => '2026-04-10',
            'sequence' => 2,
            'is_current' => false,
            'status' => 'upcoming',
        ]);

        $users = [];
        foreach (['admin', 'head_of_school', 'board'] as $role) {
            $user = User::create([
                'school_id' => $school->id,
                'name' => $role,
                'email' => $role.'.'.$token.'@session.test',
                'password' => 'password',
                'status' => 'active',
            ]);
            $user->assignRole($role);
            $users[$role] = $user;
        }

        return compact('school', 'session', 'users', 'token');
    }

    public function test_admin_hub_and_create_form_link_to_session_activation(): void
    {
        $world = $this->world();

        $this->actingAs($world['users']['admin'])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Academic Period')
            ->assertSee('Create / activate session');

        $this->actingAs($world['users']['admin'])
            ->get(route('admin.sessions.create'))
            ->assertOk()
            ->assertSee('Activate this session now')
            ->assertSee('Admin does not need approval');
    }

    public function test_admin_cannot_delete_a_closed_session(): void
    {
        $world = $this->world();
        $closed = AcademicSession::create([
            'school_id' => $world['school']->id,
            'name' => '2024/2025',
            'start_date' => '2024-09-01',
            'end_date' => '2025-07-31',
            'is_current' => false,
            'status' => 'closed',
        ]);

        $this->actingAs($world['users']['admin'])
            ->get(route('admin.sessions.index'))
            ->assertOk()
            ->assertSee('Edit')
            ->assertSee('Current and closed sessions cannot be deleted')
            ->assertDontSee('name="_method" value="DELETE"', false);

        $this->actingAs($world['users']['admin'])
            ->from(route('admin.sessions.index'))
            ->delete(route('admin.sessions.destroy', $closed))
            ->assertRedirect(route('admin.sessions.index'))
            ->assertSessionHasErrors('session');

        $this->assertNotNull($closed->fresh());
        $this->assertSame('closed', $closed->fresh()->status);
    }

    public function test_admin_can_create_and_activate_a_session_without_waiting_for_last_term(): void
    {
        $world = $this->world();

        $this->actingAs($world['users']['admin'])
            ->post(route('admin.sessions.store'), [
                'name' => '2026/2027',
                'start_date' => '2026-09-01',
                'end_date' => '2027-07-31',
                'first_term_start_date' => '2026-09-01',
                'first_term_end_date' => '2026-12-15',
                'activate' => '1',
            ])
            ->assertRedirect(route('academic-period.show'));

        $new = AcademicSession::where('school_id', $world['school']->id)->where('name', '2026/2027')->first();
        $this->assertNotNull($new);
        $this->assertTrue($new->is_current);
        $this->assertSame('active', $new->status);
        $this->assertFalse($world['session']->fresh()->is_current);
        $this->assertSame('closed', $world['session']->fresh()->status);
        $this->assertTrue($new->terms()->where('sequence', 1)->where('is_current', true)->exists());
    }

    public function test_head_of_school_can_transition_to_the_next_term(): void
    {
        $world = $this->world();

        $this->actingAs($world['users']['head_of_school'])
            ->get(route('academic-period.show'))
            ->assertOk()
            ->assertSee('Transition term')
            ->assertSee('Second Term')
            ->assertDontSee('Activate session')
            ->assertDontSee('Prepare future session')
            ->assertDontSee('Configure a term');

        $this->actingAs($world['users']['head_of_school'])
            ->post(route('academic-period.transition'), ['notes' => 'Move to second term'])
            ->assertRedirect(route('academic-period.show'));

        $first = Term::where('academic_session_id', $world['session']->id)->where('sequence', 1)->first();
        $second = Term::where('academic_session_id', $world['session']->id)->where('sequence', 2)->first();

        $this->assertSame('closed', $first->fresh()->status);
        $this->assertFalse($first->fresh()->is_current);
        $this->assertTrue($second->fresh()->is_current);
        $this->assertSame('active', $second->fresh()->status);
        $this->assertTrue($world['session']->fresh()->is_current);
    }

    public function test_head_of_school_cannot_create_or_activate_a_session(): void
    {
        $world = $this->world();

        $this->actingAs($world['users']['head_of_school'])
            ->post(route('academic-period.sessions.store'), [
                'name' => '2026/2027',
                'start_date' => '2026-09-01',
                'end_date' => '2027-07-31',
            ])
            ->assertForbidden();

        $this->actingAs($world['users']['head_of_school'])
            ->post(route('academic-period.rollover'), ['target_session_id' => $world['session']->id])
            ->assertForbidden();

        $this->assertTrue($world['session']->fresh()->is_current);
    }

    public function test_head_of_school_can_add_the_next_term_and_cannot_skip(): void
    {
        $world = $this->world();

        Term::where('academic_session_id', $world['session']->id)->where('sequence', 2)->delete();

        $this->actingAs($world['users']['head_of_school'])
            ->post(route('academic-period.terms.store'), [
                'academic_session_id' => $world['session']->id,
                'sequence' => 3,
                'start_date' => '2026-04-20',
                'end_date' => '2026-07-31',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('sequence');

        $this->actingAs($world['users']['head_of_school'])
            ->post(route('academic-period.terms.store'), [
                'academic_session_id' => $world['session']->id,
                'sequence' => 2,
                'start_date' => '2025-09-15',
                'end_date' => '2025-10-15',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('start_date');

        $this->actingAs($world['users']['head_of_school'])
            ->post(route('academic-period.terms.store'), [
                'academic_session_id' => $world['session']->id,
                'sequence' => 2,
                'start_date' => '2026-01-07',
                'end_date' => '2026-04-10',
            ])
            ->assertRedirect();

        $this->assertTrue(
            Term::where('academic_session_id', $world['session']->id)->where('sequence', 2)->where('name', 'Second Term')->exists()
        );
    }

    public function test_academic_period_paginates_closed_sessions_and_recent_transitions(): void
    {
        $world = $this->world();
        $recentClosed = AcademicSession::create([
            'school_id' => $world['school']->id,
            'name' => '2024/2025',
            'start_date' => '2024-09-01',
            'end_date' => '2025-07-31',
            'is_current' => false,
            'status' => 'closed',
        ]);
        $olderClosed = AcademicSession::create([
            'school_id' => $world['school']->id,
            'name' => '2023/2024',
            'start_date' => '2023-09-01',
            'end_date' => '2024-07-31',
            'is_current' => false,
            'status' => 'closed',
        ]);

        foreach (range(1, 4) as $i) {
            AcademicPeriodTransition::create([
                'school_id' => $world['school']->id,
                'action' => 'term_transition',
                'performed_by' => $world['users']['admin']->id,
                'performed_at' => now()->subDays($i),
                'previous_session_id' => $world['session']->id,
                'new_session_id' => $world['session']->id,
                'notes' => 'Period activity '.$i,
            ]);
        }

        $this->actingAs($world['users']['admin'])
            ->get(route('academic-period.show'))
            ->assertOk()
            ->assertSee($world['session']->name)
            ->assertSee($recentClosed->name)
            ->assertDontSee($olderClosed->name)
            ->assertViewHas('closedSessions', fn ($pages) => $pages->total() === 2 && $pages->currentPage() === 1)
            ->assertViewHas('transitions', fn ($pages) => $pages->total() === 4 && $pages->count() === 3);

        $this->actingAs($world['users']['admin'])
            ->get(route('academic-period.show', ['sessions_page' => 2]))
            ->assertOk()
            ->assertSee($world['session']->name)
            ->assertSee($olderClosed->name)
            ->assertDontSee($recentClosed->name);

        $this->actingAs($world['users']['admin'])
            ->get(route('academic-period.show', ['transitions_page' => 2]))
            ->assertOk()
            ->assertViewHas('transitions', fn ($pages) => $pages->currentPage() === 2 && $pages->count() === 1);
    }
}
