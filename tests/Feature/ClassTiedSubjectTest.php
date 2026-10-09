<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClassTiedSubjectTest extends TestCase
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
    protected function world(bool $withClass = true): array
    {
        Role::findOrCreate('admin', 'web');

        $token = Str::lower(Str::random(8));
        $school = School::create([
            'name' => 'Subject School',
            'slug' => 'subject-school-'.$token,
            'status' => 'active',
        ]);

        $admin = User::create([
            'school_id' => $school->id,
            'name' => 'Admin',
            'email' => 'admin.'.$token.'@class-subject.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $admin->assignRole('admin');

        $class = null;
        $otherClass = null;
        if ($withClass) {
            $class = SchoolClass::create([
                'school_id' => $school->id,
                'name' => 'JSS 1A',
                'level' => 'JSS',
                'display_order' => 1,
                'status' => 'active',
            ]);
            $otherClass = SchoolClass::create([
                'school_id' => $school->id,
                'name' => 'JSS 2A',
                'level' => 'JSS',
                'display_order' => 2,
                'status' => 'active',
            ]);
        }

        return compact('school', 'admin', 'class', 'otherClass', 'token');
    }

    public function test_create_page_asks_for_class_before_subject_details(): void
    {
        $world = $this->world();

        $this->actingAs($world['admin'])
            ->get(route('admin.subjects.create'))
            ->assertOk()
            ->assertSee('1. Select class')
            ->assertSee('Select a class first')
            ->assertSee('2. Subject details')
            ->assertSee('Select class...')
            ->assertSee($world['class']->name);
    }

    public function test_create_page_preselects_class_from_query_string(): void
    {
        $world = $this->world();

        $this->actingAs($world['admin'])
            ->get(route('admin.subjects.create', ['class_id' => $world['class']->id]))
            ->assertOk()
            ->assertSee('selected', false)
            ->assertSee($world['class']->name);
    }

    public function test_create_page_requires_a_class_when_none_exist(): void
    {
        $world = $this->world(false);

        $this->actingAs($world['admin'])
            ->get(route('admin.subjects.create'))
            ->assertOk()
            ->assertSee('Create a class first')
            ->assertDontSee('2. Subject details');
    }

    public function test_admin_cannot_store_a_subject_without_a_class(): void
    {
        $world = $this->world();

        $this->actingAs($world['admin'])
            ->from(route('admin.subjects.create'))
            ->post(route('admin.subjects.store'), [
                'name' => 'Mathematics',
                'code' => 'MTH',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.subjects.create'))
            ->assertSessionHasErrors('school_class_id');

        $this->assertSame(0, Subject::where('school_id', $world['school']->id)->count());
    }

    public function test_admin_creates_a_subject_tied_to_the_selected_class(): void
    {
        $world = $this->world();

        $this->actingAs($world['admin'])
            ->post(route('admin.subjects.store'), [
                'school_class_id' => $world['class']->id,
                'name' => 'Mathematics',
                'code' => 'MTH',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.subjects.index'));

        $subject = Subject::where('school_id', $world['school']->id)->where('name', 'Mathematics')->first();
        $this->assertNotNull($subject);
        $this->assertTrue($world['class']->fresh()->offeredSubjects()->whereKey($subject->id)->exists());
        $this->assertFalse($world['otherClass']->fresh()->offeredSubjects()->whereKey($subject->id)->exists());
    }

    public function test_same_subject_name_can_be_offered_in_another_class(): void
    {
        $world = $this->world();

        $payload = [
            'name' => 'Mathematics',
            'code' => 'MTH',
            'status' => 'active',
        ];

        $this->actingAs($world['admin'])
            ->post(route('admin.subjects.store'), $payload + ['school_class_id' => $world['class']->id])
            ->assertRedirect(route('admin.subjects.index'));

        $this->actingAs($world['admin'])
            ->post(route('admin.subjects.store'), $payload + ['school_class_id' => $world['otherClass']->id])
            ->assertRedirect(route('admin.subjects.index'));

        $this->assertSame(1, Subject::where('school_id', $world['school']->id)->count());
        $subject = Subject::where('school_id', $world['school']->id)->first();
        $this->assertTrue($world['class']->fresh()->offeredSubjects()->whereKey($subject->id)->exists());
        $this->assertTrue($world['otherClass']->fresh()->offeredSubjects()->whereKey($subject->id)->exists());
    }

    public function test_admin_cannot_offer_the_same_subject_twice_in_one_class(): void
    {
        $world = $this->world();

        $payload = [
            'school_class_id' => $world['class']->id,
            'name' => 'Mathematics',
            'code' => 'MTH',
            'status' => 'active',
        ];

        $this->actingAs($world['admin'])
            ->post(route('admin.subjects.store'), $payload)
            ->assertRedirect(route('admin.subjects.index'));

        $this->actingAs($world['admin'])
            ->from(route('admin.subjects.create'))
            ->post(route('admin.subjects.store'), $payload)
            ->assertRedirect(route('admin.subjects.create'))
            ->assertSessionHasErrors('name');
    }

    public function test_create_class_form_does_not_ask_for_display_order(): void
    {
        $world = $this->world();

        $this->actingAs($world['admin'])
            ->get(route('admin.classes.create'))
            ->assertOk()
            ->assertDontSee('Display order')
            ->assertSee('Class name');
    }

    public function test_new_class_receives_the_next_display_order_automatically(): void
    {
        $world = $this->world();

        $this->actingAs($world['admin'])
            ->post(route('admin.classes.store'), [
                'name' => 'JSS 3A',
                'level' => 'JSS',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.classes.index'));

        $created = SchoolClass::where('school_id', $world['school']->id)->where('name', 'JSS 3A')->first();
        $this->assertNotNull($created);
        $this->assertSame(3, $created->display_order);
    }

    public function test_first_class_in_a_school_starts_at_display_order_one(): void
    {
        $world = $this->world(false);

        $this->actingAs($world['admin'])
            ->post(route('admin.classes.store'), [
                'name' => 'Nursery 1',
                'level' => 'Nursery',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.classes.index'));

        $created = SchoolClass::where('school_id', $world['school']->id)->where('name', 'Nursery 1')->first();
        $this->assertNotNull($created);
        $this->assertSame(1, $created->display_order);
    }
}
