<?php

namespace App\Support;

/**
 * WISCA_v2 operational menu catalog: pillar → activity → sub-activity.
 *
 * Source: chambers/WISCA_v2.xlsx
 */
class WiscaOperationalCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function pillars(): array
    {
        return [
            [
                'key' => 'academic_excellence',
                'label' => 'Academic Excellence',
                'icon' => 'book',
                'activities' => [
                    static::activity('ae-01', 'Curriculum Coverage Rate', 'coverage-logs.index', ['coverage-logs.*', 'schemes.*', 'curriculum-coverage.*', 'lesson-plans.*', 'planning-policy.*', 'catch-ups.*'], 'document', ['subject_lead'], [
                        static::sub('Plan curriculum delivery according to approved scheme of work', ['teacher'], 'schemes.index', '100%', 'Every Thursday of the week', 'Submission of lesson plan every Thursday'),
                        static::sub('Deliver scheduled curriculum content and learning objectives', ['teacher'], 'coverage-logs.index', '≥ 95%', 'Weekly', 'Lesson plans, teacher logs, and curriculum coverage checks'),
                        static::sub('Check curriculum coverage and track completed topics', ['head_of_department'], 'coverage-logs.index', '100%', 'Weekly / Mid-Term', 'Submitted learners\' notebooks and curriculum tracking register'),
                        static::sub('Identify curriculum gaps and implement catch-up plans', ['head_of_department'], 'curriculum-coverage.report', '100%', 'Monthly', 'HOD catch-up from uncovered Active SoW topics (not an IIP)'),
                        static::sub('Review, verify, and update coverage vs understanding', ['head_of_department'], 'coverage-logs.index', '95%', 'Random', 'Evaluation tests'),
                    ]),
                    static::activity('ae-02', 'School-wide Examination Pass Rate', 'exam-results.index', ['exam-results.*'], 'chart', [], [
                        static::sub('Conduct baseline assessment for performance gaps', ['head_of_school', 'assistant_head_secondary', 'head_of_department'], 'exam-results.index', '100%', '1st week of every term', 'Previous session examination analysis report and baseline mark sheets'),
                        static::sub('Develop and implement targeted academic intervention plans for low-performing learners', ['teacher'], 'exam-results.index', '≥ 95%', 'Termly', 'Intervention registers, individualized support plans, and lesson schedules'),
                        static::sub('Conduct targeted remedial lessons and revision sessions', ['teacher'], 'exam-results.index', '≥ 90%', 'Weekly / Termly', 'SIP timetable implementation'),
                        static::sub('Evaluate pass rate and progress of learners receiving academic intervention', ['teacher'], 'exam-results.index', '≥ 80% pass rate (≥75% in major subjects)', 'Half-Termly / Termly', 'Comparative baseline vs. termly assessment results and mark sheets'),
                    ]),
                    static::activity('ae-03', 'Homework/Class Work Completion Rate', 'homework.index', ['homework.*'], 'list', [], [
                        static::sub('Assign classwork and homework aligned with approved curriculum', ['teacher'], 'homework.index', '≥ 95%', '3x a week', 'Home and classwork marks'),
                        static::sub('Monitor, mark, and record learner completion of assigned work', ['teacher'], 'homework.index', '≥ 90%', 'Weekly', 'Homework register, teacher marking records, and exercise books'),
                        static::sub('Follow up on incomplete work and provide make-up opportunities', ['teacher'], 'homework.index', '100%', 'Weekly', 'Teacher follow-up records, make-up registers, and completed work'),
                    ]),
                    static::activity('ae-04', 'Learner Attendance Rate', 'attendance.index', ['attendance.*'], 'check', ['admin_officer'], [
                        static::sub('Record daily learner attendance and punctuality accurately at assembly/class', ['teacher'], 'attendance.index', '100%', 'Daily', 'Daily class attendance registers and punctuality sheets'),
                        static::sub('Monitor attendance patterns and identify persistent absentees or frequent lateness', ['teacher'], 'attendance.index', '100%', 'Weekly', 'Weekly attendance monitoring reports and absentee list'),
                        static::sub('Follow up on unexplained absences and communicate concerns to Management', ['teacher'], 'attendance.index', '100%', 'Daily', 'Register reviews vs reports to management'),
                    ]),
                    static::activity('ae-05', 'Lesson Plan/Note Submission & Approval', 'lesson-plans.index', ['lesson-plans.*'], 'document', [], [
                        static::sub('Prepare and submit weekly lesson plans/notes according to standard format and timeline', ['teacher'], 'lesson-plans.index', '≥ 95%', 'Weekly', 'Lesson plan files and submission tracking register'),
                        static::sub('Review submitted lesson plans for curriculum alignment, quality, and engagement', ['head_of_department'], 'lesson-plans.index', '100%', 'Weekly', 'Review checklists and supervisor review records'),
                        static::sub('Approve compliant plans and return non-compliant plans with feedback for correction', ['head_of_department'], 'lesson-plans.index', '≥ 95% approved', 'Weekly', 'Approved lesson plan files, signature logs, and feedback notes'),
                    ]),
                    static::activity('ae-06', 'Effective or Better Lesson Observations', 'observations.index', ['observations.*'], 'document', [], [
                        static::sub('Conduct formal classroom lesson observations using standardized observation checklist', ['head_of_school', 'assistant_head_secondary', 'head_of_department'], 'observations.index', '≥ 95%', 'Monthly', 'Completed lesson observation forms and supervisor schedules'),
                        static::sub('Assess teaching methods, learner engagement, and provide immediate post-observation feedback', ['head_of_department'], 'observations.index', '100%', 'Monthly', 'Feedback forms, observation debrief notes, and teacher feedback sign-offs'),
                        static::sub('Monitor implementation of observation feedback and track teaching improvement', ['head_of_department'], 'observations.index', '≥ 90%', 'Termly / Follow-up', 'Follow-up observation reports and teacher development tracking matrix'),
                    ]),
                    static::activity('ae-07', 'At-Risk Learners with Active Intervention Plan', 'at-risk.index', ['at-risk.*', 'intervention-plans.*'], 'document', ['learning_support_coordinator'], [
                        static::sub('Identify and document struggling/at-risk learners based on academic and behavioral criteria', ['head_of_department'], 'at-risk.index', '100%', 'Monthly / Termly', 'At-risk learner register and learner profile diagnostic forms'),
                        static::sub('Develop and implement individualized learning intervention plans for each at-risk learner', ['head_of_department'], 'at-risk.index', '100%', 'Weekly / Termly', 'Individual Intervention Plans (IIP) and remedial attendance logs'),
                        static::sub('Monitor intervention progress and evaluate academic outcome improvements', ['head_of_school', 'assistant_head_secondary', 'head_of_department'], 'at-risk.index', '≥ 80%', 'Monthly / Termly', 'Progress tracking charts, post-intervention test scores, and evaluation reports'),
                    ]),
                    static::activity('ae-08', 'Literacy Progress (≥1 Year Growth)', 'reading.index', ['reading.*'], 'book', ['literacy_coordinator'], [
                        static::sub('Conduct baseline literacy, reading fluency, and comprehension assessments', ['head_of_school', 'assistant_head_secondary', 'head_of_department'], 'reading.index', '100%', 'Beginning of Term', 'Baseline reading assessment scores and diagnostic literacy records'),
                        static::sub('Deliver targeted literacy and writing instruction with periodic progress monitoring', ['head_of_department'], 'reading.index', '≥ 90%', 'Weekly / Monthly', 'Reading logs, writing portfolio samples, and monthly literacy progress checklists'),
                        static::sub('Provide specialized reading support for struggling readers and evaluate termly growth', ['teacher'], 'reading.index', '≥ 90%', 'Termly', 'Standardized literacy re-assessment results and progress growth reports'),
                    ]),
                    static::activity('ae-09', 'Numeracy Progress', 'activities.coming-soon', ['activities.coming-soon'], 'chart', [], [
                        static::sub('Establish baseline numeracy, calculation, and mathematical reasoning levels', ['teacher'], 'activities.coming-soon', '100%', 'Beginning of Term', 'Baseline numeracy assessment results and diagnostic scorecards', ['numeracy-progress']),
                        static::sub('Conduct regular numeracy assessments and deliver targeted problem-solving support', ['head_of_department'], 'activities.coming-soon', '≥ 85%', 'Monthly', 'Monthly numeracy quiz scores, exercise book checks, and remedial logs', ['numeracy-progress']),
                        static::sub('Compare termly/end-of-year numeracy performance against baseline data', ['head_of_department'], 'activities.coming-soon', '≥ 85%', 'Termly / End-of-Year', 'Comparative numeracy growth charts and final assessment reports', ['numeracy-progress']),
                    ], ['activity' => 'numeracy-progress']),
                    static::activity('ae-10', 'Learner Assimilation Rate', 'activities.coming-soon', ['activities.coming-soon'], 'check', [], [
                        static::sub('Conduct regular formative assessments, questioning, and interactive checks during lessons', ['teacher'], 'activities.coming-soon', '≥ 90%', 'Daily / Weekly', 'Lesson questioning records, classwork performance scores, and observation notes', ['learner-assimilation-rate']),
                        static::sub('Identify assimilation gaps early through weekly quizzes and formative checks', ['head_of_department'], 'activities.coming-soon', '≥ 95%', 'Weekly / Monthly', 'Quiz result analysis, gap identification logs, and correction records', ['learner-assimilation-rate']),
                        static::sub('Review assimilation trends and adjust instructional strategies to achieve concept mastery', ['teacher'], 'activities.coming-soon', '≥ 90%', 'Termly', 'Formative-to-summative progress reports and learning outcome review sheets', ['learner-assimilation-rate']),
                    ], ['activity' => 'learner-assimilation-rate']),
                ],
            ],
            [
                'key' => 'christcentric_education',
                'label' => 'Christcentric Education',
                'icon' => 'check',
                'activities' => [
                    static::activity('ce-01', 'Daily Devotion & Chapel Participation', 'chapel.index', ['chapel.*'], 'check', ['admin_officer', 'student_life_coordinator'], [
                        static::sub('Conduct general morning assembly devotions 3 to 5 times weekly', ['chaplain'], 'chapel.index', '100%', 'Thrice Weekly / Daily', 'Devotional timetable, assembly logs, and attendance records'),
                        static::sub('Conduct age-appropriate classroom devotions, scripture readings, and memory verse activities', ['teacher'], 'chapel.index', '≥ 95%', 'Twice Weekly / Daily', 'Class teacher devotion logs and memory verse assessment sheets'),
                        static::sub('Organize and conduct weekly chapel services with active student participation', ['chaplain'], 'chapel.index', '100% attendance (≥90% active participation)', 'Weekly', 'Chaplaincy program roster, attendance registers, photos, and video logs'),
                    ]),
                    static::activity('ce-02', 'Christian Character Rating (Secure +)', 'character.index', ['character.*'], 'document', ['student_life_coordinator'], [
                        static::sub('Deliver planned lessons and activities on Christian core values', ['teacher'], 'character.index', '2 activities per month (100% coverage)', 'Monthly', 'Character lesson plans, activity sheets, and classroom photos'),
                        static::sub('Monitor, evaluate, and record learners\' daily application of Christian conduct and peer relationships', ['teacher'], 'character.index', '≥ 90%', 'Ongoing / Monthly', 'Learner conduct logs, behavioral tracking forms, and incident registers'),
                        static::sub('Conduct monthly secret/peer character reviews and recognize learners demonstrating exceptional character growth', ['chaplain'], 'character.index', '≥ 80% positive rating (min 1 recognition per term)', 'Monthly / Termly', 'Character assessment forms, certificates of exemplary conduct, and awards list'),
                    ]),
                    static::activity('ce-03', 'Community Service Hours Per Learner', 'service.index', ['service.*'], 'document', ['student_life_coordinator'], [
                        static::sub('Plan, schedule, and organize age-appropriate voluntary school and community outreach projects', ['chaplain'], 'service.index', '100% aligned with calendar', 'Ongoing / Termly', 'Community service master schedule and outreach activity guidelines'),
                        static::sub('Track, calculate, and log community service hours completed by each learner', ['head_of_department'], 'service.index', '≥ 2 hours per learner per term', 'Monthly / Termly', 'Individual learner service logs and class teacher verification reports'),
                        static::sub('Acknowledge, reward, and report learner participation and completion of community service targets', ['head_of_department'], 'service.index', '≥ 90%', 'Termly', 'Community service certificates, summary reports, and assembly recognition'),
                    ]),
                    static::activity('ce-04', 'Resolved Restorative Discipline Cases', 'discipline.index', ['discipline.*'], 'document', ['student_life_coordinator'], [
                        static::sub('Promptly log and report all discipline cases using approved restorative framework', ['teacher'], 'discipline.index', '≥ 95%', 'Ongoing / As cases occur', 'Disciplinary incident register and incident reporting forms'),
                        static::sub('Conduct structured restorative conversations with involved learners to foster understanding and responsibility', ['assistant_head_secondary', 'head_of_school'], 'discipline.index', '100%', 'Ongoing', 'Restorative meeting notes and counselor/supervisor case logs'),
                        static::sub('Develop, execute, and monitor agreed restoration action plans', ['chaplain'], 'discipline.index', '100% plan completion (≥90% no recurrence)', 'Ongoing / Termly', 'Restoration action agreements and follow-up monitoring reports'),
                    ]),
                    static::activity('ce-05', 'Bullying Incident Resolution Rate', 'bullying.index', ['bullying.*'], 'document', ['student_life_coordinator'], [
                        static::sub('Promptly report, log, and acknowledge all bullying complaints via safeguarding process', ['assistant_head_secondary', 'head_of_school'], 'bullying.index', '100%', 'Immediate / As cases occur', 'Safeguarding register, incident logs'),
                        static::sub('Investigate reported bullying incidents thoroughly using established anti-bullying procedures', ['teacher'], 'bullying.index', '≥ 95%', 'Routine', 'Investigation notes, witness statements, and assessment forms'),
                        static::sub('Execute resolution measures and conduct post-resolution follow-up checks with affected learners', ['head_of_department'], 'bullying.index', '100% follow-up', 'Routine', 'Follow-up monitoring forms, counseling notes, and safety audit logs'),
                    ]),
                    static::activity('ce-06', 'Scripture Memory & Application Mastery', 'scripture.index', ['scripture.*'], 'book', ['student_life_coordinator'], [
                        static::sub('Teach approved weekly scripture memory verses according to termly spiritual syllabus', ['chaplain'], 'scripture.index', '100%', 'Weekly', 'Test of knowledge of verse of the week'),
                        static::sub('Guide learners to comprehend and state real-life practical applications of memorized scriptures', ['chaplain'], 'scripture.index', '≥ 90%', 'Monthly', 'Oral assessment records and classwork sheets'),
                        static::sub('Assess scripture recitation accuracy and celebrate scripture mastery and spiritual growth', ['assistant_head_secondary', 'head_of_school'], 'scripture.index', '≥ 90% meeting target', 'Termly', 'Scripture evaluation mark sheets, certificates, and recognition records'),
                    ]),
                    static::activity('ce-07', 'Parent-School Christian Culture Alignment', 'partnership.index', ['partnership.*'], 'document', ['parent_relations_lead'], [
                        static::sub('Communicate school\'s Christian values, conduct standards, and policies to new and returning parents', ['chaplain'], 'partnership.index', '100%', 'Beginning of Term', 'Signed parent commitment forms and orientation attendance list'),
                        static::sub('Engage parents in spiritual activities (chapel services, joint prayer meetings, chatroom reflections)', ['chaplain'], 'partnership.index', '≥ 90% positive engagement (min 2 events/term)', 'Weekly / Termly', 'Event attendance registers, parent chatroom records, and feedback surveys'),
                        static::sub('Collaborate with parents on learner character formation, home-school reinforcement, and discipline follow-up', ['chaplain'], 'partnership.index', '≥ 90%', 'Termly / As needed', 'Parent consultation records, joint action plans, and counseling meeting minutes'),
                    ]),
                ],
            ],
            [
                'key' => 'digital_innovation',
                'label' => 'Digital Innovation',
                'icon' => 'chart',
                'activities' => [
                    static::activity('di-01', 'Digital Portal and LMS Adoption', 'lms.index', ['lms.*'], 'chart', [], [
                        static::sub('Conduct comprehensive training for staff, learners, and parents on LMS and digital portal apps', ['ict_coordinator'], 'lms.index', '≥ 95% staff & parents', 'Pre-Term / Mid-Term refresher', 'Training attendance registers, instruction manuals, and session photos'),
                        static::sub('Provision active user accounts, verify credentials, and track regular portal/LMS logins', ['ict_coordinator'], 'lms.index', '≥ 95%', 'Beginning of Term / Weekly', 'User account audit reports and LMS portal login logs'),
                        static::sub('Collect user feedback regarding digital portal experience and implement functional enhancements', ['admin_manager'], 'lms.index', '≥ 90%', 'Monthly', 'User feedback survey results, resolution reports, and system update logs'),
                    ]),
                    static::activity('di-02', 'Learner Coding & STEM Practical Completion', 'stem.index', ['stem.*'], 'book', ['stem_coordinator'], [
                        static::sub('Procure and verify complete inventory of STEM kits, coding software, and practical materials', ['head_of_school'], 'stem.index', '100%', 'Beginning of Term', 'STEM inventory checklist, software license records, and readiness report'),
                        static::sub('Schedule and deliver practical coding and STEM classes twice weekly per curriculum plan', ['ict_coordinator'], 'stem.index', '100%', 'Weekly', 'Practical timetable, lab utilization logs, and lesson delivery records'),
                        static::sub('Ensure completion of weekly coding projects, practical STEM assignments, and termly exhibitions', ['head_of_department'], 'stem.index', '≥ 95% project completion (1–2 exhibitions/session)', 'Weekly / Monthly', 'Learner project files, coding repositories, project rubrics, and exhibition photos'),
                    ]),
                    static::activity('di-03', 'AI & Technology Ethics Compliance', 'ethics.index', ['ethics.*'], 'document', [], [
                        static::sub('Educate staff and learners on responsible AI usage, digital ethics, and academic integrity guidelines', ['teacher'], 'ethics.index', '≥ 90% participation (2 sessions/term)', 'Termly', 'Workshop attendance registers, ethics presentation slides, and training reports'),
                        static::sub('Monitor and audit how AI tools are integrated into lesson preparation and learner assignments', ['head_of_department'], 'ethics.index', '100% compliant sample audits', 'Monthly', 'AI monitoring checklists, sample audit reports, and usage guidelines'),
                        static::sub('Enforce academic honesty standards, discourage AI misuse/plagiarism, and resolve violations', ['head_of_school'], 'ethics.index', '≥ 90%', 'Ongoing', 'Incident log, investigation reports, and corrective action records'),
                    ]),
                    static::activity('di-04', 'Staff Digital Competency Mastery', 'competency.index', ['competency.*'], 'users', [], [
                        static::sub('Organize monthly practical ICT and digital teaching competency training workshops for teaching staff', ['ict_coordinator'], 'competency.index', 'Min 1 session per month (100% participation)', 'Monthly / Termly', 'Staff training registers, competency assessments, and workshop materials'),
                        static::sub('Provide peer coaching and technical support pairing digitally competent staff with developing staff', ['ict_coordinator'], 'competency.index', '100%', 'Termly / Ongoing', 'Coaching schedule, peer support logs, and improvement tracking forms'),
                        static::sub('Observe and evaluate teachers\' integration of digital tools in daily instruction and resource creation', ['head_of_department'], 'competency.index', '≥ 90% competency rating', 'Weekly / Termly', 'Digital teaching observation checklists and ICT integration reports'),
                    ]),
                    static::activity('di-05', 'Digital Assessment & E-Portfolio Usage', 'eassessment.index', ['eassessment.*'], 'chart', [], [
                        static::sub('Administer periodic digital assessments using approved online evaluation tools', ['ict_coordinator'], 'eassessment.index', 'Min 2 digital tests per term (≥90% completion)', 'Termly', 'Online test portal records, digital submission logs, and score summaries'),
                        static::sub('Create, update, and maintain individual digital learner E-Portfolios with learning artifacts', ['ict_coordinator'], 'eassessment.index', '100%', 'Beginning of Term / Weekly update', 'E-Portfolio platform links, digital artifact registers, and portfolio review logs'),
                        static::sub('Analyze E-Portfolio assessment data to monitor individual learning progress and target support', ['ict_coordinator'], 'eassessment.index', '100%', 'Weekly / Monthly', 'Platform progress feedback reports, assessment analytics, and support plans'),
                    ]),
                    static::activity('di-06', 'Parent Portal Engagement Rate', 'portal-engagement.index', ['portal-engagement.*'], 'users', ['parent_relations_lead'], [
                        static::sub('Enroll and activate parent accounts on the school portal for academic/attendance tracking', ['admin_manager'], 'portal-engagement.index', '≥ 95%', 'Beginning of Term / Termly', 'Parent portal registration database and active user reports'),
                        static::sub('Provide parent orientation sessions and technical guidance on utilizing portal features effectively', ['ict_coordinator'], 'portal-engagement.index', 'Min 1 orientation per term (≥90% reach)', 'Termly', 'Orientation attendance logs, user guides distributed, and helpdesk records'),
                        static::sub('Monitor parent portal access trends, identify inactive accounts, and conduct proactive follow-ups', ['admin_manager'], 'portal-engagement.index', '100% follow-up', 'Monthly / Termly', 'Portal login activity reports, parent outreach call logs, and support tickets'),
                    ]),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findActivity(string $slug): ?array
    {
        foreach (static::pillars() as $pillar) {
            foreach ($pillar['activities'] as $activity) {
                if (($activity['params']['activity'] ?? $activity['key']) === $slug || $activity['key'] === $slug) {
                    return $activity + ['pillar' => $pillar['label']];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @param  array<int, string>  $extraRoles
     * @param  array<string, string>  $params
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>|null
     */
    public static function findActivityForRequest(): ?array
    {
        $route = request()->route()?->getName();

        if ($route === 'activities.coming-soon') {
            return static::findActivity((string) request()->route('activity'));
        }

        foreach (static::pillars() as $pillar) {
            foreach ($pillar['activities'] as $activity) {
                foreach ($activity['active'] as $pattern) {
                    if (! $pattern || ! request()->routeIs($pattern)) {
                        continue;
                    }

                    return $activity + ['pillar' => $pillar['label']];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $userRoles
     */
    public static function userOwnsSub(array $userRoles, array $sub): bool
    {
        return array_intersect($sub['roles'] ?? [], $userRoles) !== [];
    }

    /**
     * Sub-activities visible for these roles. Oversight ($seeAll) and extra_roles see every child.
     *
     * @param  array<int, string>  $userRoles
     * @return array<int, array<string, mixed>>
     */
    public static function visibleChildren(array $activity, array $userRoles, bool $seeAll = false): array
    {
        $includeAll = $seeAll || array_intersect($activity['extra_roles'] ?? [], $userRoles) !== [];

        return array_values(array_filter(
            $activity['children'] ?? [],
            fn (array $child) => $includeAll || static::userOwnsSub($userRoles, $child)
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @param  array<int, string>  $extraRoles
     * @param  array<string, string>  $params
     * @return array<string, mixed>
     */
    protected static function activity(
        string $key,
        string $label,
        string $route,
        array $active,
        string $icon,
        array $extraRoles,
        array $children,
        array $params = [],
    ): array {
        foreach ($children as $index => $child) {
            $children[$index]['key'] = (string) ($index + 1);
        }

        return [
            'key' => $key,
            'label' => $label,
            'route' => $route,
            'active' => $active,
            'icon' => $icon,
            'extra_roles' => $extraRoles,
            'params' => $params,
            'children' => $children,
        ];
    }

    /**
     * @param  array<int, string>  $roles
     * @return array<string, mixed>
     */
    protected static function sub(
        string $label,
        array $roles,
        string $route,
        string $target = '',
        string $frequency = '',
        string $evidence = '',
        ?array $activityParam = null,
    ): array {
        $item = [
            'label' => $label,
            'roles' => $roles,
            'route' => $route,
            'target' => $target,
            'frequency' => $frequency,
            'evidence' => $evidence,
            'active' => [$route === 'activities.coming-soon' ? 'activities.coming-soon' : str_replace('.index', '.*', $route)],
        ];

        if ($activityParam) {
            $item['params'] = ['activity' => $activityParam[0]];
        }

        return $item;
    }
}
