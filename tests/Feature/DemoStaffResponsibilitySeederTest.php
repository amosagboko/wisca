<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\School;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\DemoStaffResponsibilitySeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DemoStaffResponsibilitySeederTest extends TestCase
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

    protected function seedStaff(): void
    {
        foreach (['board', 'head_of_school', 'head_of_department', 'teacher', 'admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $school = School::firstOrCreate(
            ['slug' => 'wca-makurdi'],
            [
                'name' => 'Wisdom Christian Academy, Makurdi',
                'status' => 'active',
            ],
        );

        $session = AcademicSession::currentForSchool($school->id);
        if (! $session) {
            $session = AcademicSession::create([
                'school_id' => $school->id,
                'name' => '2026/2027',
                'start_date' => now()->startOfWeek()->toDateString(),
                'end_date' => now()->addMonths(9)->toDateString(),
                'is_current' => true,
                'status' => 'active',
            ]);
        }

        if (! Term::currentForSession($session->id)) {
            Term::create([
                'academic_session_id' => $session->id,
                'name' => 'First Term',
                'start_date' => $session->start_date,
                'end_date' => $session->end_date,
                'sequence' => 1,
                'is_current' => true,
                'status' => 'active',
            ]);
        }

        $this->seed(DemoStaffResponsibilitySeeder::class);
    }

    public function test_current_teacher_is_caught_up_and_sow_gap_teacher_has_a_cta(): void
    {
        $this->seedStaff();

        $ready = User::where('email', 'teacher.ready.ada@wisca.test')->first();
        $gap = User::where('email', 'teacher.gap.sow@wisca.test')->first();

        $this->assertNotNull($ready);
        $this->assertNotNull($gap);

        $this->actingAs($ready)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('You are all caught up.')
            ->assertDontSee('Submit lesson plan')
            ->assertDontSee('Continue draft');

        $this->actingAs($gap)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Continue draft')
            ->assertSee('Submit scheme of work');
    }

    public function test_ready_hod_queue_is_clear_and_behind_hod_has_pending_reviews(): void
    {
        $this->seedStaff();

        $ready = User::where('email', 'hod.ready@wisca.test')->first();
        $behind = User::where('email', 'hod.behind@wisca.test')->first();

        $this->assertNotNull($ready);
        $this->assertNotNull($behind);

        $this->actingAs($ready)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('All caught up')
            ->assertSeeInOrder(['Schemes of Work', '(0)', 'Lesson plans', '(0)']);

        $this->actingAs($behind)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Schemes of Work', '(1)', 'Lesson plans', '(2)'])
            ->assertSee('Review scheme')
            ->assertSee('Review lesson plan');
    }

    public function test_hos_sees_named_lagging_staff_and_board_cannot_submit_week_review(): void
    {
        $this->seedStaff();

        $school = School::where('slug', 'wca-makurdi')->first();
        $this->assertNotNull($school);

        $hos = $this->leadershipUser($school, 'Head of School', 'hos@wisca.test', 'head_of_school');
        $board = $this->leadershipUser($school, 'Board Member', 'board@wisca.test', 'board');

        $this->actingAs($hos)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Who is behind')
            ->assertSee('HODs')
            ->assertSee('Teachers')
            ->assertSee('Mr Musa Behind')
            ->assertDontSee('Eze Sow')
            ->assertDontSee('Ada Ready')
            ->assertSee('Record week review');

        $this->actingAs($hos)
            ->get(route('dashboard', ['behind' => 'teachers']))
            ->assertOk()
            ->assertSee('Eze Sow')
            ->assertDontSee('Ada Ready')
            ->assertDontSee('Mr Musa Behind');

        $this->actingAs($board)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Who is behind')
            ->assertSee('Mr Musa Behind')
            ->assertDontSee('Eze Sow')
            ->assertDontSee('Ada Ready')
            ->assertDontSee('Record week review')
            ->assertDontSee('Update week review');

        $this->actingAs($board)
            ->get(route('dashboard', ['behind' => 'teachers']))
            ->assertOk()
            ->assertSee('Eze Sow')
            ->assertDontSee('Ada Ready');
    }

    protected function leadershipUser(School $school, string $name, string $email, string $role): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'school_id' => $school->id,
                'name' => $name,
                'password' => 'password',
                'status' => 'active',
            ],
        );
        $user->update([
            'school_id' => $school->id,
            'name' => $name,
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }
}
