<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\LessonPlan;
use App\Models\Kpi;
use App\Models\KpiPeriodicData;
use App\Models\Pillar;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchemeOfWork;
use App\Models\Subject;
use App\Models\Term;
use App\Models\Topic;
use App\Models\User;
use App\Services\KpiStatusEvaluator;
use App\Services\LessonPlanCalculationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class WiscaSeeder extends Seeder
{
    public function run(): void
    {
        $evaluator = app(KpiStatusEvaluator::class);

        $roles = [
            'board', 'head_of_school', 'head_of_department', 'assistant_head_secondary',
            'teacher', 'admin', 'admin_officer', 'learning_support_coordinator',
            'literacy_coordinator', 'chaplain', 'student_life_coordinator',
            'parent_relations_lead', 'ict_coordinator', 'admin_manager', 'stem_coordinator', 'subject_lead',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $school = School::create([
            'name' => 'Wisdom Christian Academy, Makurdi',
            'slug' => 'wca-makurdi',
            'address' => 'Makurdi, Benue State',
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

        $this->call(AcademicPeriodDemoSeeder::class);

        $jss1a = SchoolClass::create([
            'school_id' => $school->id,
            'name' => 'JSS 1A',
            'level' => 'JSS',
            'display_order' => 1,
        ]);

        $mathsDept = Department::create([
            'school_id' => $school->id,
            'name' => 'Mathematics',
            'status' => 'active',
        ]);

        $math = Subject::create([
            'school_id' => $school->id,
            'department_id' => $mathsDept->id,
            'name' => 'Mathematics',
            'code' => 'MTH',
        ]);

        $users = [
            ['name' => 'Board Member', 'email' => 'board@wisca.test', 'role' => 'board'],
            ['name' => 'Head of School', 'email' => 'hos@wisca.test', 'role' => 'head_of_school'],
            ['name' => 'Head of Department', 'email' => 'hod@wisca.test', 'role' => 'head_of_department'],
            ['name' => 'Class Teacher', 'email' => 'teacher@wisca.test', 'role' => 'teacher'],
            ['name' => 'Admin Officer', 'email' => 'officer@wisca.test', 'role' => 'admin_officer'],
            ['name' => 'System Admin', 'email' => 'admin@wisca.test', 'role' => 'admin'],
        ];

        $createdUsers = [];
        foreach ($users as $data) {
            $user = User::create([
                'school_id' => $school->id,
                'department_id' => $data['role'] === 'head_of_department' ? $mathsDept->id : null,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make('password'),
                'status' => 'active',
            ]);
            $user->assignRole($data['role']);
            $createdUsers[$data['role']] = $user;
        }

        $teacher = $createdUsers['teacher'];
        $hod = $createdUsers['head_of_department'];

        $jss1a->offeredSubjects()->syncWithoutDetaching([$math->id]);
        $jss1a->subjects()->attach($math->id, [
            'teacher_id' => $teacher->id,
            'academic_session_id' => $session->id,
            'status' => 'active',
        ]);

        $pillars = $this->pillarDefinitions();
        $kpiMap = [];

        foreach ($pillars as $pillarData) {
            $kpis = $pillarData['kpis'];
            unset($pillarData['kpis']);
            $pillarData['school_id'] = $school->id;

            $pillar = Pillar::create($pillarData);

            foreach ($kpis as $kpiData) {
                $sample = $kpiData['sample'] ?? null;
                unset($kpiData['sample']);
                $kpiData['pillar_id'] = $pillar->id;

                $kpi = Kpi::create($kpiData);
                $kpiMap[$kpi->code] = $kpi;

                if ($sample) {
                    KpiPeriodicData::create([
                        'kpi_id' => $kpi->id,
                        'academic_session_id' => $session->id,
                        'target_value' => $sample['target'],
                        'actual_value' => $sample['actual'],
                        'achievement_rate' => $evaluator->achievementRate($sample['actual'], $sample['target']),
                        'status' => $evaluator->kpiStatus($evaluator->achievementRate($sample['actual'], $sample['target']) ?? 0),
                    ]);
                }
            }
        }

        $approvedAt = now();
        $scheme = SchemeOfWork::create([
            'subject_id' => $math->id,
            'school_class_id' => $jss1a->id,
            'academic_session_id' => $session->id,
            'term_id' => $term->id,
            'version' => 1,
            'uploaded_by' => $hod->id,
            'status' => 'active',
            'submitted_by' => $hod->id,
            'submitted_at' => $approvedAt,
            'hos_approved_by' => $createdUsers['head_of_school']->id,
            'hos_approved_at' => $approvedAt,
            'board_approved_by' => $createdUsers['board']->id,
            'board_approved_at' => $approvedAt,
            'approved_by' => $createdUsers['board']->id,
            'approved_at' => $approvedAt,
        ]);

        $topics = [
            ['week' => 1, 'title' => 'Number Bases'],
            ['week' => 2, 'title' => 'Basic Operations'],
            ['week' => 3, 'title' => 'Fractions'],
            ['week' => 4, 'title' => 'Decimals'],
            ['week' => 5, 'title' => 'Approximation'],
            ['week' => 6, 'title' => 'Ratio and Proportion'],
            ['week' => 7, 'title' => 'Percentages'],
            ['week' => 8, 'title' => 'Simple Interest'],
            ['week' => 9, 'title' => 'Profit and Loss'],
            ['week' => 10, 'title' => 'Revision'],
        ];

        foreach ($topics as $index => $topicData) {
            $topic = Topic::create([
                'scheme_of_work_id' => $scheme->id,
                'week_number' => $topicData['week'],
                'title' => $topicData['title'],
                'learning_objectives' => ['Learners will demonstrate understanding of '.$topicData['title'].'.'],
                'display_order' => $index + 1,
                'status' => $index < 9 ? 'covered' : 'planned',
            ]);

            if ($index < 9) {
                $due = app(LessonPlanCalculationService::class)->dueAtForTopic($topic);
                LessonPlan::create([
                    'topic_id' => $topic->id,
                    'teacher_id' => $teacher->id,
                    'school_class_id' => $jss1a->id,
                    'subject_id' => $math->id,
                    'objectives' => 'Learners will demonstrate understanding of '.$topic->title.'.',
                    'activities' => 'Teacher modelling, guided practice, and workbook exercises.',
                    'assessment' => 'Oral questions and workbook check during the lesson.',
                    'resources' => 'JSS 1 Mathematics workbook',
                    'status' => 'approved',
                    'submitted_at' => $due->copy()->subDay()->setTime(18, 0),
                    'due_at' => $due,
                    'on_time' => true,
                    'approved_by' => $hod->id,
                    'approved_at' => $due->copy()->subDay()->setTime(20, 0),
                ]);
            }
        }

        app(\App\Services\CoverageCalculationService::class)->recalculateForScheme($scheme);
    }

    protected function pillarDefinitions(): array
    {
        return [
            [
                'name' => 'Academic Excellence',
                'code' => 'academic_excellence',
                'description' => 'Delivering rigorous, engaging, and future-ready learning experiences that foster critical thinking.',
                'display_order' => 1,
                'config' => ['color_theme' => '#1e3a5f'],
                'kpis' => [
                    $this->kpi('AE-01', 'Curriculum Coverage Rate', 'Fortnightly audit comparing taught topics in student workbooks against approved termly Scheme of Work.', '(Topics Completed ÷ Planned Topics in Scheme) × 100', 'Appendix C: Curriculum Coverage Tracker', 1.0, 'percentage', 'fortnightly', '%', 'subject_lead', 1, ['target' => 1.0, 'actual' => 0.96]),
                    $this->kpi('AE-02', 'School-wide Examination Pass Rate', 'Consolidated termly grading ledger across all subjects and phases (Primary & Secondary).', '(Students scoring ≥50% in subject ÷ Total Enrolled) × 100', 'Termly Broad Sheet & Examination Result Ledger', 0.9, 'percentage', 'termly', '%', 'head_of_department', 2, ['target' => 0.9, 'actual' => 0.92]),
                    $this->kpi('AE-03', 'Homework Completion Rate', 'Weekly class register check tracking homework submission and correctness.', '(Assignments Completed On-Time ÷ Total Assignments Given) × 100', 'Classroom Homework Log & Portal Submission Log', 0.95, 'percentage', 'weekly', '%', 'teacher', 3, ['target' => 0.95, 'actual' => 0.94]),
                    $this->kpi('AE-04', 'Learner Attendance Rate', 'Daily morning roll call recorded on the digital portal register.', '(Days Present ÷ Total Instructional School Days) × 100', 'Digital Attendance Register System', 0.95, 'percentage', 'weekly', '%', 'admin_officer', 4, ['target' => 0.95, 'actual' => 0.965]),
                    $this->kpi('AE-05', 'Lesson Plan Submission & Approval', 'Weekly digital portal upload audit against the school planning-policy due day.', '(Approved Lesson Plans Submitted On-Time ÷ Total Required) × 100', 'Appendix A: Weekly Lesson Plan Tracker', 1.0, 'percentage', 'weekly', '%', 'head_of_department', 5, ['target' => 1.0, 'actual' => 0.98]),
                    $this->kpi('AE-06', 'Effective or Better Lesson Observations', 'Structured classroom observation scoring across 12 instructional standards.', '(Lessons Scored ≥3 Secure on Rubric ÷ Total Observed) × 100', 'Appendix B: Classroom Observation Checklist', 0.9, 'percentage', 'termly', '%', 'head_of_school', 6, ['target' => 0.9, 'actual' => 0.88]),
                    $this->kpi('AE-07', 'At-Risk Learners with Active Plan', 'Monthly audit of students performing below 50% or marked Concern against active Tier 2/3 plans.', '(Identified Students with Active Support Plans ÷ Total Identified At-Risk) × 100', 'Appendix F: Learner Academic Intervention Plan', 1.0, 'percentage', 'monthly', '%', 'learning_support_coordinator', 7, ['target' => 1.0, 'actual' => 1.0]),
                    $this->kpi('AE-08', 'Reading Progress (≥1 Year Growth)', 'Standardized diagnostic reading tests administered at start and end of academic session.', '(Students achieving ≥1.0 Grade-Level Increase ÷ Total Assessed) × 100', 'Literacy Assessment Battery & Reading Logs', 0.85, 'percentage', 'termly', '%', 'literacy_coordinator', 8, ['target' => 0.85, 'actual' => 0.82]),
                ],
            ],
            [
                'name' => 'Christocentric Education',
                'code' => 'christocentric_education',
                'description' => "Rooting every aspect of school life in God's Word, nurturing spiritual growth and Christian character.",
                'display_order' => 2,
                'config' => ['color_theme' => '#4a1942'],
                'kpis' => [
                    $this->kpi('CE-01', 'Daily Devotion & Chapel Attendance', 'Daily attendance and active participation check during morning devotion and chapel services.', '(Students Present & Participating ÷ Total School Roll) × 100', 'Chapel Attendance Log & Devotion Register', 0.95, 'percentage', 'daily', '%', 'chaplain', 1, ['target' => 0.95, 'actual' => 0.97]),
                    $this->kpi('CE-02', 'Christian Character Rating (Secure+)', 'Termly holistic evaluation across 7 biblical character domains (Faith, Integrity, Excellence, etc.).', '(Students scoring Rating 3 Secure or 4 Exemplary ÷ Total Enrolled) × 100', 'Appendix I: Character Development Rubric', 0.85, 'percentage', 'termly', '%', 'chaplain', 2, ['target' => 0.85, 'actual' => 0.88]),
                    $this->kpi('CE-03', 'Community Service Hours per Learner', 'Tracking student participation in school-organized outreach, peer tutoring, and campus service.', 'Total Verified Service Hours ÷ Total Student Roll', 'Student Leadership & Service Activity Log', 10, 'hours', 'termly', 'hours', 'student_life_coordinator', 3, ['target' => 10, 'actual' => 9.5]),
                    $this->kpi('CE-04', 'Resolved Restorative Discipline Cases', 'Tracking disciplinary incidents managed through restorative reflection vs purely punitive measures.', '(Resolved Cases with Completed Restorative Agreement ÷ Total Incidents Logged) × 100', 'Appendix G: Behaviour Incident & Restorative Record', 0.9, 'percentage', 'monthly', '%', 'head_of_school', 4, ['target' => 0.9, 'actual' => 0.93]),
                    $this->kpi('CE-05', 'Bullying Case Resolution Rate', 'Formal tracking of reported bullying/cyberbullying incidents from report to verified follow-up closure.', '(Bullying Cases Fully Investigated & Closed with Safety Plan ÷ Total Reported) × 100', 'Appendix H: Anti-Bullying Case Management Form', 1.0, 'percentage', 'monthly', '%', 'chaplain', 5, ['target' => 1.0, 'actual' => 1.0]),
                    $this->kpi('CE-06', 'Scripture Memory & Application Mastery', 'Bi-weekly assessment of assigned termly Bible verses and contextual application reflection.', '(Students Reciting and Contextually Explaining Verses ÷ Total Assessed) × 100', 'Scripture Memory Progress Log', 0.85, 'percentage', 'termly', '%', 'chaplain', 6, ['target' => 0.85, 'actual' => 0.8]),
                    $this->kpi('CE-07', 'Parent-School Christian Culture Alignment', 'Tracking parent endorsement and submission of the Christian Culture Partnership Commitment.', '(Signed Partnership Commitments Received ÷ Total Parent Body) × 100', 'Appendix K: Parent-School Partnership Charter', 0.85, 'percentage', 'termly', '%', 'parent_relations_lead', 7, ['target' => 0.85, 'actual' => 0.89]),
                ],
            ],
            [
                'name' => 'Digital Innovation',
                'code' => 'digital_innovation',
                'description' => 'Leveraging technology and innovative practices to create personalized, flexible digital learning.',
                'display_order' => 3,
                'config' => ['color_theme' => '#0f4c75'],
                'kpis' => [
                    $this->kpi('DI-01', 'Digital Portal & LMS Adoption Rate', 'Automated LMS activity logs tracking staff and student daily login, assignment submission, and resource access.', '(Active Weekly LMS Users ÷ Total Staff & Students) × 100', 'LMS System Usage Analytics Report', 0.9, 'percentage', 'weekly', '%', 'it_consultant', 1, ['target' => 0.9, 'actual' => 0.94]),
                    $this->kpi('DI-02', 'Coding & STEM Project Completion', 'Assessment of student practical project submissions in computer coding, robotics, or design challenges.', '(Students Completing Approved STEM Project ÷ Total Enrolled) × 100', 'STEM & Coding Project Assessment Rubric', 0.85, 'percentage', 'termly', '%', 'stem_coordinator', 2, ['target' => 0.85, 'actual' => 0.87]),
                    $this->kpi('DI-03', 'AI & Digital Ethics Compliance', 'Audit of student digital submissions for academic integrity, AI disclosure, and cybersecurity guidelines.', '(Audited Assignments Free of Uncredited AI / Tech Violations ÷ Total Audited) × 100', 'Digital Integrity Audit Log & AI Detector Check', 0.95, 'percentage', 'termly', '%', 'it_consultant', 3, ['target' => 0.95, 'actual' => 0.96]),
                    $this->kpi('DI-04', 'Staff Digital Competency Mastery', 'Termly practical evaluation of teaching staff in digital tool integration, e-assessment, and smart board use.', '(Staff Demonstrating Level 3+ Digital Competence ÷ Total Staff) × 100', 'Teacher Digital Skills Proficiency Matrix', 0.85, 'percentage', 'termly', '%', 'head_of_school', 4, ['target' => 0.85, 'actual' => 0.78]),
                    $this->kpi('DI-05', 'E-Portfolio & Digital Assessment Usage', 'Verification of digital continuous assessment tasks and student e-portfolio updates.', '(Subjects Utilizing E-Assessment Tools ÷ Total Subjects Offered) × 100', 'Academic Portal Assessment Audit', 0.8, 'percentage', 'termly', '%', 'head_of_school', 5, ['target' => 0.8, 'actual' => 0.83]),
                    $this->kpi('DI-06', 'Parent Portal Engagement Rate', 'System log tracking unique parent logins to inspect report cards, attendance, and fee status.', '(Unique Active Parent Portal Logins ÷ Total Enrolled Families) × 100', 'Parent Portal System Analytics', 0.8, 'percentage', 'monthly', '%', 'it_consultant', 6, ['target' => 0.8, 'actual' => 0.82]),
                ],
            ],
        ];
    }

    protected function kpi(
        string $code,
        string $name,
        string $methodology,
        string $formula,
        string $instrument,
        float $target,
        string $targetType,
        string $frequency,
        string $unit,
        string $ownerRole,
        int $order,
        ?array $sample = null,
    ): array {
        return [
            'code' => $code,
            'name' => $name,
            'measurement_methodology' => $methodology,
            'calculation_formula' => $formula,
            'data_collection_instrument' => $instrument,
            'default_target' => $target,
            'target_type' => $targetType,
            'frequency' => $frequency,
            'unit' => $unit,
            'owner_role' => $ownerRole,
            'display_order' => $order,
            'sample' => $sample,
        ];
    }
}
