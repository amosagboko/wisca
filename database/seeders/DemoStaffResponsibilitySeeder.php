<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\ExamResult;
use App\Models\HomeworkLog;
use App\Models\Learner;
use App\Models\LessonPlan;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\SchemeOfWork;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\Topic;
use App\Models\TopicCoverageLog;
use App\Models\User;
use App\Services\ExamCalculationService;
use App\Services\LessonPlanCalculationService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DemoStaffResponsibilitySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['head_of_department', 'teacher', 'head_of_school', 'board'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $school = $this->school();
        $session = $school ? AcademicSession::currentForSchool($school->id) : null;
        $term = $session ? Term::currentForSession($session->id) : null;

        if (! $school || ! $session || ! $term) {
            return;
        }

        $readyHod = $this->user($school, 'Mrs Amina Ready', 'hod.ready@wisca.test', 'head_of_department');
        $behindHod = $this->user($school, 'Mr Musa Behind', 'hod.behind@wisca.test', 'head_of_department');

        $junior = $this->section($school, 'Junior Secondary', $readyHod);
        $upper = $this->section($school, 'Upper Basic', $behindHod);

        $english = $this->subject($school, 'Languages', 'English', 'ENG');
        $science = $this->subject($school, 'Science', 'Basic Science', 'BSC');
        $social = $this->subject($school, 'Humanities', 'Social Studies', 'SOS');
        $civic = $this->subject($school, 'Humanities', 'Civic Education', 'CIV');
        $crs = $this->subject($school, 'Humanities', 'Christian Religious Studies', 'CRS');

        $readyHod->update(['department_id' => $english->department_id]);
        $behindHod->update(['department_id' => $science->department_id]);

        $weekNumber = $term->schemeWeekNumber(10);
        $registerDate = now()->isWeekday()
            ? now()->toDateString()
            : $term->instructionalWeekStart($weekNumber)->toDateString();
        $homeworkDate = $term->instructionalWeekStart($weekNumber)->copy()->addDay()->toDateString();
        if ($homeworkDate > $term->instructionalWeekEnd($weekNumber)->toDateString()) {
            $homeworkDate = $term->instructionalWeekStart($weekNumber)->toDateString();
        }

        $ready = [
            ['name' => 'Ada Ready', 'email' => 'teacher.ready.ada@wisca.test', 'class' => 'JSS 1B', 'subject' => $english, 'order' => 11],
            ['name' => 'Chidi Ready', 'email' => 'teacher.ready.chidi@wisca.test', 'class' => 'JSS 1C', 'subject' => $science, 'order' => 12],
            ['name' => 'Funmi Ready', 'email' => 'teacher.ready.funmi@wisca.test', 'class' => 'JSS 2A', 'subject' => $social, 'order' => 13],
        ];

        foreach ($ready as $row) {
            $teacher = $this->user($school, $row['name'], $row['email'], 'teacher');
            $class = $this->class($school, $junior, $row['class'], $row['order']);
            $this->assign($session, $class, $row['subject'], $teacher);
            $this->learners($school, $class);
            $scheme = $this->activeScheme($session, $term, $class, $row['subject'], $teacher, $readyHod);
            $this->seedCurrentWeek($scheme, $teacher, $readyHod, $weekNumber, [
                'plan' => 'approved',
                'coverage' => 'verified',
            ]);
            $this->homework($teacher, $class, $row['subject'], $session, $term, $homeworkDate, $readyHod, verified: true);
            $this->register($teacher, $class, $session, $term, $registerDate, $readyHod, verified: true);
            $this->marks($teacher, $class, $row['subject'], $session, $term, $readyHod);
        }

        $lagging = [
            ['name' => 'Eze Sow', 'email' => 'teacher.gap.sow@wisca.test', 'class' => 'JSS 2B', 'subject' => $crs, 'order' => 14, 'gap' => 'sow'],
            ['name' => 'Bello Plan', 'email' => 'teacher.gap.plan@wisca.test', 'class' => 'JSS 2C', 'subject' => $civic, 'order' => 15, 'gap' => 'plan'],
            ['name' => 'Okoh Coverage', 'email' => 'teacher.gap.coverage@wisca.test', 'class' => 'JSS 3A', 'subject' => $english, 'order' => 16, 'gap' => 'coverage'],
            ['name' => 'Sani Homework', 'email' => 'teacher.gap.homework@wisca.test', 'class' => 'JSS 3B', 'subject' => $science, 'order' => 17, 'gap' => 'homework'],
            ['name' => 'Torkwase Register', 'email' => 'teacher.gap.register@wisca.test', 'class' => 'JSS 3C', 'subject' => $social, 'order' => 18, 'gap' => 'register'],
        ];

        $teachers = [];
        foreach ($lagging as $row) {
            $teacher = $this->user($school, $row['name'], $row['email'], 'teacher');
            $class = $this->class($school, $upper, $row['class'], $row['order']);
            $this->assign($session, $class, $row['subject'], $teacher);
            $this->learners($school, $class);
            $teachers[$row['gap']] = compact('teacher', 'class') + ['subject' => $row['subject']];

            if ($row['gap'] === 'sow') {
                $this->draftScheme($session, $term, $class, $row['subject'], $teacher);
            } else {
                $scheme = $this->activeScheme($session, $term, $class, $row['subject'], $teacher, $behindHod);
                $this->seedCurrentWeek($scheme, $teacher, $behindHod, $weekNumber, match ($row['gap']) {
                    'plan' => ['plan' => 'none', 'coverage' => 'none'],
                    'coverage' => ['plan' => 'approved', 'coverage' => 'none'],
                    default => ['plan' => 'approved', 'coverage' => 'verified'],
                });
            }

            if ($row['gap'] !== 'homework') {
                $this->homework($teacher, $class, $row['subject'], $session, $term, $homeworkDate, $behindHod, verified: true);
            }
            if ($row['gap'] !== 'register') {
                $this->register($teacher, $class, $session, $term, $registerDate, $behindHod, verified: true);
            }
            $this->marks($teacher, $class, $row['subject'], $session, $term, $behindHod);
        }

        $this->hodBacklog($session, $term, $upper, $civic, $teachers, $weekNumber, $behindHod, $homeworkDate, $registerDate);
    }

    protected function school(): ?School
    {
        $named = School::where('slug', 'wca-makurdi')->first();
        if ($named && AcademicSession::currentForSchool($named->id)) {
            return $named;
        }

        return School::query()
            ->whereHas('academicSessions', fn ($query) => $query->where('is_current', true))
            ->first();
    }

    protected function user(School $school, string $name, string $email, string $role): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'school_id' => $school->id,
                'name' => $name,
                'password' => 'password',
                'status' => 'active',
            ]
        );

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        return $user;
    }

    protected function section(School $school, string $name, User $hod): SchoolSection
    {
        return SchoolSection::updateOrCreate(
            ['school_id' => $school->id, 'name' => $name],
            ['status' => 'active', 'hod_id' => $hod->id],
        );
    }

    protected function subject(School $school, string $departmentName, string $name, string $code): Subject
    {
        $department = Department::firstOrCreate(
            ['school_id' => $school->id, 'name' => $departmentName],
            ['status' => 'active'],
        );

        return Subject::firstOrCreate(
            ['school_id' => $school->id, 'code' => $code],
            [
                'department_id' => $department->id,
                'name' => $name,
                'status' => 'active',
            ],
        );
    }

    protected function class(School $school, SchoolSection $section, string $name, int $order): SchoolClass
    {
        $class = SchoolClass::firstOrCreate(
            ['school_id' => $school->id, 'name' => $name],
            [
                'level' => 'JSS',
                'status' => 'active',
                'display_order' => $order,
            ],
        );
        $class->update(['school_section_id' => $section->id, 'status' => 'active']);

        return $class;
    }

    protected function assign(AcademicSession $session, SchoolClass $class, Subject $subject, User $teacher): void
    {
        $class->offeredSubjects()->syncWithoutDetaching([$subject->id]);
        TeacherAssignment::firstOrCreate(
            [
                'school_class_id' => $class->id,
                'subject_id' => $subject->id,
                'academic_session_id' => $session->id,
            ],
            [
                'teacher_id' => $teacher->id,
                'status' => 'active',
            ],
        );
    }

    protected function learners(School $school, SchoolClass $class): void
    {
        $names = [
            ['Ada', 'female'], ['Bola', 'female'], ['Chika', 'female'], ['Dele', 'male'],
            ['Efe', 'male'], ['Fola', 'female'], ['Gidado', 'male'], ['Halima', 'female'],
        ];

        foreach ($names as $index => [$first, $gender]) {
            $admission = 'WCA/RESP/'.$class->name.'/'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            Learner::firstOrCreate(
                ['school_id' => $school->id, 'admission_no' => $admission],
                [
                    'school_class_id' => $class->id,
                    'name' => $first.' '.$class->name,
                    'gender' => $gender,
                    'status' => 'enrolled',
                ],
            );
        }
    }

    protected function activeScheme(
        AcademicSession $session,
        Term $term,
        SchoolClass $class,
        Subject $subject,
        User $teacher,
        User $hod,
    ): SchemeOfWork {
        $scheme = SchemeOfWork::firstOrCreate(
            [
                'school_class_id' => $class->id,
                'subject_id' => $subject->id,
                'academic_session_id' => $session->id,
                'term_id' => $term->id,
                'version' => 1,
            ],
            [
                'uploaded_by' => $teacher->id,
                'status' => 'active',
                'submitted_by' => $teacher->id,
                'submitted_at' => now()->subDays(10),
                'approved_by' => $hod->id,
                'approved_at' => now()->subDays(9),
            ],
        );

        if ($scheme->status !== 'active') {
            $scheme->update([
                'status' => 'active',
                'submitted_at' => $scheme->submitted_at ?? now()->subDays(10),
                'approved_by' => $hod->id,
                'approved_at' => $scheme->approved_at ?? now()->subDays(9),
                'rejection_reason' => null,
            ]);
        }

        $this->ensureTopics($scheme);

        return $scheme->fresh(['topics', 'subject']);
    }

    protected function draftScheme(
        AcademicSession $session,
        Term $term,
        SchoolClass $class,
        Subject $subject,
        User $teacher,
    ): SchemeOfWork {
        $scheme = SchemeOfWork::firstOrCreate(
            [
                'school_class_id' => $class->id,
                'subject_id' => $subject->id,
                'academic_session_id' => $session->id,
                'term_id' => $term->id,
                'version' => 1,
            ],
            [
                'uploaded_by' => $teacher->id,
                'status' => 'draft',
                'submitted_at' => null,
                'submitted_by' => null,
            ],
        );

        if (! $scheme->isEditableDraft()) {
            $scheme->update([
                'status' => 'draft',
                'submitted_at' => null,
                'submitted_by' => null,
                'approved_by' => null,
                'approved_at' => null,
                'rejection_reason' => null,
            ]);
        }

        $this->ensureTopics($scheme);

        return $scheme;
    }

    protected function ensureTopics(SchemeOfWork $scheme): void
    {
        if ($scheme->topics()->exists()) {
            return;
        }

        $titles = [
            1 => 'Settling In',
            2 => 'Core Ideas',
            3 => 'Guided Practice',
            4 => 'Application',
            5 => 'Problem Solving',
            6 => 'Mid-term Review',
            7 => 'Extension',
            8 => 'Project Work',
            9 => 'Assessment Prep',
            10 => 'Revision',
        ];

        foreach ($titles as $week => $title) {
            Topic::create([
                'scheme_of_work_id' => $scheme->id,
                'week_number' => $week,
                'title' => $title,
                'learning_objectives' => ['Learners will demonstrate understanding of '.$title.'.'],
                'display_order' => $week,
                'status' => 'planned',
            ]);
        }
    }

    /**
     * @param  array{plan: string, coverage: string}  $thisWeek
     */
    protected function seedCurrentWeek(
        SchemeOfWork $scheme,
        User $teacher,
        User $hod,
        int $weekNumber,
        array $thisWeek,
    ): void {
        $scheme->loadMissing(['topics', 'subject']);
        $planner = app(LessonPlanCalculationService::class);

        foreach ($scheme->topics as $topic) {
            $isThisWeek = (int) $topic->week_number === $weekNumber;
            $planMode = $isThisWeek ? $thisWeek['plan'] : 'approved';
            $coverageMode = $isThisWeek ? $thisWeek['coverage'] : 'verified';

            $plan = null;
            if ($planMode !== 'none') {
                $due = $planner->dueAtForTopic($topic);
                $submittedAt = $due->copy()->subDay()->setTime(18, 0);
                $plan = LessonPlan::updateOrCreate(
                    ['topic_id' => $topic->id, 'teacher_id' => $teacher->id],
                    [
                        'school_class_id' => $scheme->school_class_id,
                        'subject_id' => $scheme->subject_id,
                        'objectives' => 'Learners will demonstrate understanding of '.$topic->title.'.',
                        'activities' => 'Teacher modelling, guided practice, and workbook exercises.',
                        'assessment' => 'Oral questions and workbook check during the lesson.',
                        'resources' => $scheme->subject->name.' workbook',
                        'status' => $planMode === 'submitted' ? 'submitted' : 'approved',
                        'submitted_at' => $submittedAt,
                        'due_at' => $due,
                        'on_time' => true,
                        'approved_by' => $planMode === 'approved' ? $hod->id : null,
                        'approved_at' => $planMode === 'approved' ? $due->copy()->subDay()->setTime(20, 0) : null,
                    ],
                );
            }

            if ($coverageMode === 'none' || ! $plan || $plan->status !== 'approved') {
                if ($coverageMode === 'none') {
                    $topic->update(['status' => 'planned']);
                }

                continue;
            }

            $log = TopicCoverageLog::updateOrCreate(
                ['topic_id' => $topic->id, 'teacher_id' => $teacher->id],
                [
                    'school_class_id' => $scheme->school_class_id,
                    'subject_id' => $scheme->subject_id,
                    'lesson_plan_id' => $plan->id,
                    'coverage_date' => now()->toDateString(),
                    'workbook_reference' => 'Wk '.$topic->week_number.' p.1-2',
                    'status' => $coverageMode === 'verified' ? 'verified' : 'submitted',
                    'submitted_at' => now()->subDays(2),
                    'verified_by' => $coverageMode === 'verified' ? $hod->id : null,
                    'verified_at' => $coverageMode === 'verified' ? now()->subDay() : null,
                ],
            );

            $topic->update(['status' => $log->status === 'verified' ? 'covered' : 'in_progress']);
        }
    }

    protected function homework(
        User $teacher,
        SchoolClass $class,
        Subject $subject,
        AcademicSession $session,
        Term $term,
        string $givenDate,
        User $hod,
        bool $verified,
    ): void {
        HomeworkLog::updateOrCreate(
            [
                'teacher_id' => $teacher->id,
                'school_class_id' => $class->id,
                'subject_id' => $subject->id,
                'academic_session_id' => $session->id,
                'term_id' => $term->id,
                'given_date' => $givenDate,
            ],
            [
                'title' => $subject->name.' practice',
                'due_date' => $givenDate,
                'given_count' => 8,
                'completed_on_time_count' => 7,
                'status' => $verified ? 'verified' : 'submitted',
                'verified_by' => $verified ? $hod->id : null,
                'verified_at' => $verified ? now()->subHours(6) : null,
            ],
        );
    }

    protected function register(
        User $teacher,
        SchoolClass $class,
        AcademicSession $session,
        Term $term,
        string $date,
        User $hod,
        bool $verified,
    ): void {
        AttendanceLog::updateOrCreate(
            [
                'school_class_id' => $class->id,
                'attendance_date' => $date,
            ],
            [
                'recorded_by' => $teacher->id,
                'academic_session_id' => $session->id,
                'term_id' => $term->id,
                'enrolled_count' => 8,
                'present_count' => 8,
                'status' => $verified ? 'verified' : 'submitted',
                'verified_by' => $verified ? $hod->id : null,
                'verified_at' => $verified ? now()->subHours(3) : null,
            ],
        );
    }

    protected function marks(
        User $teacher,
        SchoolClass $class,
        Subject $subject,
        AcademicSession $session,
        Term $term,
        User $hod,
    ): void {
        $learners = Learner::where('school_class_id', $class->id)->where('status', 'enrolled')->get();
        foreach ($learners as $index => $learner) {
            ExamResult::updateOrCreate(
                [
                    'learner_id' => $learner->id,
                    'subject_id' => $subject->id,
                    'academic_session_id' => $session->id,
                    'term_id' => $term->id,
                    'assessment_key' => ExamCalculationService::ASSESSMENT_KEY,
                ],
                [
                    'school_class_id' => $class->id,
                    'recorded_by' => $teacher->id,
                    'assessment_name' => $term->name.' examination',
                    'score' => 58 + ($index * 4),
                    'status' => 'verified',
                    'verified_by' => $hod->id,
                    'verified_at' => now()->subDays(3),
                ],
            );
        }
    }

    /**
     * @param  array<string, array{teacher: User, class: SchoolClass, subject: Subject}>  $teachers
     */
    protected function hodBacklog(
        AcademicSession $session,
        Term $term,
        SchoolSection $section,
        Subject $civic,
        array $teachers,
        int $weekNumber,
        User $hod,
        string $homeworkDate,
        string $registerDate,
    ): void {
        $sowTeacher = $teachers['sow']['teacher'];
        $planTeacher = $teachers['plan']['teacher'];
        $coverage = $teachers['coverage'];

        $queueClass = $this->class($session->school, $section, 'JSS 1D', 19);
        $this->assign($session, $queueClass, $civic, $sowTeacher);
        $this->learners($session->school, $queueClass);
        $this->homework($sowTeacher, $queueClass, $civic, $session, $term, $homeworkDate, $hod, verified: true);
        $this->register($sowTeacher, $queueClass, $session, $term, $registerDate, $hod, verified: true);
        $this->marks($sowTeacher, $queueClass, $civic, $session, $term, $hod);

        $submittedScheme = SchemeOfWork::firstOrCreate(
            [
                'school_class_id' => $queueClass->id,
                'subject_id' => $civic->id,
                'academic_session_id' => $session->id,
                'term_id' => $term->id,
                'version' => 1,
            ],
            [
                'uploaded_by' => $sowTeacher->id,
                'status' => 'draft',
                'submitted_by' => $sowTeacher->id,
                'submitted_at' => now()->subHours(30),
            ],
        );
        $submittedScheme->update([
            'status' => 'draft',
            'submitted_by' => $sowTeacher->id,
            'submitted_at' => now()->subHours(30),
            'approved_by' => null,
            'approved_at' => null,
        ]);
        $this->ensureTopics($submittedScheme);

        $planScheme = SchemeOfWork::query()
            ->where('school_class_id', $teachers['plan']['class']->id)
            ->where('subject_id', $teachers['plan']['subject']->id)
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->first();

        if ($planScheme) {
            $extraWeeks = collect([(int) max(1, $weekNumber - 1), (int) min(10, $weekNumber + 1)])
                ->unique()
                ->reject(fn ($week) => $week === $weekNumber)
                ->take(2)
                ->values();
            if ($extraWeeks->count() < 2) {
                $extraWeeks = collect([max(1, $weekNumber === 1 ? 2 : 1), min(10, $weekNumber === 10 ? 9 : 10)])
                    ->unique()
                    ->reject(fn ($week) => $week === $weekNumber)
                    ->values();
            }

            $planner = app(LessonPlanCalculationService::class);
            foreach ($extraWeeks as $week) {
                $topic = $planScheme->topics()->where('week_number', $week)->first();
                if (! $topic) {
                    continue;
                }
                $due = $planner->dueAtForTopic($topic);
                LessonPlan::updateOrCreate(
                    ['topic_id' => $topic->id, 'teacher_id' => $planTeacher->id],
                    [
                        'school_class_id' => $planScheme->school_class_id,
                        'subject_id' => $planScheme->subject_id,
                        'objectives' => 'Awaiting HOD review for '.$topic->title.'.',
                        'activities' => 'Guided practice.',
                        'assessment' => 'Workbook check.',
                        'status' => 'submitted',
                        'submitted_at' => now()->subHours(30),
                        'due_at' => $due,
                        'on_time' => true,
                        'approved_by' => null,
                        'approved_at' => null,
                    ],
                );
            }
        }

        $coverageScheme = SchemeOfWork::query()
            ->where('school_class_id', $coverage['class']->id)
            ->where('subject_id', $coverage['subject']->id)
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->first();
        $otherWeek = $weekNumber === 1 ? 2 : 1;
        $otherTopic = $coverageScheme?->topics()->where('week_number', $otherWeek)->first();
        if ($otherTopic) {
            $plan = LessonPlan::where('topic_id', $otherTopic->id)->where('teacher_id', $coverage['teacher']->id)->first();
            TopicCoverageLog::updateOrCreate(
                ['topic_id' => $otherTopic->id, 'teacher_id' => $coverage['teacher']->id],
                [
                    'school_class_id' => $coverage['class']->id,
                    'subject_id' => $coverage['subject']->id,
                    'lesson_plan_id' => $plan?->id,
                    'coverage_date' => now()->subDays(3)->toDateString(),
                    'workbook_reference' => 'Awaiting verification',
                    'status' => 'submitted',
                    'submitted_at' => now()->subHours(30),
                    'verified_by' => null,
                    'verified_at' => null,
                ],
            );
            $otherTopic->update(['status' => 'in_progress']);
        }
    }
}
