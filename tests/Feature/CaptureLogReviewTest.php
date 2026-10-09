<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\AttendanceLog;
use App\Models\HomeworkLog;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\HomeworkCalculationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CaptureLogReviewTest extends TestCase
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
        foreach (['board', 'head_of_school', 'head_of_department', 'teacher', 'admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $token = Str::lower(Str::random(8));
        $school = School::create(['name' => 'Capture School', 'slug' => 'capture-school-'.$token, 'status' => 'active']);
        $session = AcademicSession::create([
            'school_id' => $school->id,
            'name' => '2025/2026',
            'start_date' => now()->startOfWeek(Carbon::MONDAY)->toDateString(),
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

        $users = [];
        foreach (['head_of_department', 'teacher'] as $role) {
            $user = User::create([
                'school_id' => $school->id,
                'name' => $role,
                'email' => $role.'.'.$token.'@capture.test',
                'password' => 'password',
                'status' => 'active',
            ]);
            $user->assignRole($role);
            $users[$role] = $user;
        }

        TeacherAssignment::create([
            'teacher_id' => $users['teacher']->id,
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'academic_session_id' => $session->id,
            'status' => 'active',
        ]);

        return compact('school', 'session', 'term', 'class', 'subject', 'users');
    }

    protected function homeworkLog(array $world, array $overrides = []): HomeworkLog
    {
        return HomeworkLog::create(array_merge([
            'teacher_id' => $world['users']['teacher']->id,
            'school_class_id' => $world['class']->id,
            'subject_id' => $world['subject']->id,
            'academic_session_id' => $world['session']->id,
            'term_id' => $world['term']->id,
            'title' => 'Exercise 1',
            'given_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'given_count' => 10,
            'completed_on_time_count' => 8,
            'status' => 'submitted',
        ], $overrides));
    }

    public function test_teacher_cannot_verify_homework(): void
    {
        $world = $this->world();
        $log = $this->homeworkLog($world);

        $this->actingAs($world['users']['teacher'])
            ->post(route('homework.verify', $log))
            ->assertForbidden();

        $this->assertSame('submitted', $log->fresh()->status);
    }

    public function test_hod_verifies_homework_without_changing_ae03_counts(): void
    {
        $world = $this->world();
        $log = $this->homeworkLog($world);
        $before = app(HomeworkCalculationService::class)->rateForLogs(collect([$log]));

        $this->actingAs($world['users']['head_of_department'])
            ->post(route('homework.verify', $log))
            ->assertRedirect();

        $verified = $log->fresh();
        $this->assertSame('verified', $verified->status);
        $this->assertSame($world['users']['head_of_department']->id, $verified->verified_by);
        $this->assertEquals($before, app(HomeworkCalculationService::class)->rateForLogs(collect([$verified])));
    }

    public function test_teacher_cannot_edit_verified_homework(): void
    {
        $world = $this->world();
        $log = $this->homeworkLog($world, [
            'status' => 'verified',
            'verified_by' => $world['users']['head_of_department']->id,
            'verified_at' => now(),
        ]);

        $this->actingAs($world['users']['teacher'])
            ->get(route('homework.edit', $log))
            ->assertStatus(422);
    }

    public function test_rejected_homework_returns_to_teacher_feed(): void
    {
        $world = $this->world();
        $log = $this->homeworkLog($world);

        $this->actingAs($world['users']['head_of_department'])
            ->post(route('homework.reject', $log), ['rejection_reason' => 'Counts look off'])
            ->assertRedirect();

        $this->actingAs($world['users']['teacher'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Revise homework')
            ->assertSee('Counts look off')
            ->assertDontSee('Homework awaiting HOD verification.');
    }

    public function test_hod_verifies_attendance_and_teacher_cannot(): void
    {
        $world = $this->world();
        $log = AttendanceLog::create([
            'recorded_by' => $world['users']['teacher']->id,
            'school_class_id' => $world['class']->id,
            'academic_session_id' => $world['session']->id,
            'term_id' => $world['term']->id,
            'attendance_date' => now()->toDateString(),
            'enrolled_count' => 20,
            'present_count' => 19,
            'status' => 'submitted',
        ]);

        $this->actingAs($world['users']['teacher'])
            ->post(route('attendance.verify', $log))
            ->assertForbidden();

        $this->actingAs($world['users']['head_of_department'])
            ->from(route('dashboard'))
            ->post(route('attendance.verify', $log))
            ->assertRedirect();

        $this->assertSame('verified', $log->fresh()->status);

        $this->actingAs($world['users']['head_of_department'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Review register');
    }
}
