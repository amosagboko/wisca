<?php

namespace Tests\Feature;

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

    public function test_admin_can_create_and_activate_a_session_without_waiting_for_last_term(): void
    {
        $world = $this->world();

        $this->actingAs($world['users']['admin'])
            ->post(route('admin.sessions.store'), [
                'name' => '2026/2027',
                'start_date' => '2026-09-01',
                'end_date' => '2027-07-31',
                'activate' => '1',
                'first_term_name' => 'First Term',
            ])
            ->assertRedirect(route('admin.sessions.index'));

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
            ->assertSee('Second Term');

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

    public function test_head_of_school_and_board_can_prepare_and_activate(): void
    {
        $world = $this->world();

        $this->actingAs($world['users']['head_of_school'])
            ->post(route('academic-period.sessions.store'), [
                'name' => '2026/2027',
                'start_date' => '2026-09-01',
                'end_date' => '2027-07-31',
            ])
            ->assertRedirect();

        $target = AcademicSession::where('school_id', $world['school']->id)->where('name', '2026/2027')->first();
        $this->assertNotNull($target);
        $this->assertFalse($target->is_current);

        $this->actingAs($world['users']['head_of_school'])
            ->post(route('academic-period.terms.store'), [
                'academic_session_id' => $target->id,
                'name' => 'First Term',
                'start_date' => '2026-09-01',
                'end_date' => '2026-12-15',
                'sequence' => 1,
            ])
            ->assertRedirect();

        $this->actingAs($world['users']['head_of_school'])
            ->get(route('academic-period.show'))
            ->assertOk()
            ->assertSee('Transition term')
            ->assertSee('Activate session');

        $this->actingAs($world['users']['board'])
            ->get(route('academic-period.show'))
            ->assertOk()
            ->assertSee('Transition term')
            ->assertSee('Activate session');

        $this->actingAs($world['users']['head_of_school'])
            ->post(route('academic-period.rollover'), ['target_session_id' => $target->id])
            ->assertRedirect(route('academic-period.show'));

        $this->assertTrue($target->fresh()->is_current);
        $this->assertFalse($world['session']->fresh()->is_current);
        $this->assertSame('closed', $world['session']->fresh()->status);
    }
}
