<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class WiscaNavigation
{
    /**
     * @return array<int, array{label: string, items: array<int, array<string, mixed>}>}
     */
    public static function groups(?User $user = null): array
    {
        $user ??= Auth::user();
        if (! $user instanceof User) {
            return [static::accountGroup()];
        }

        $groups = match (true) {
            $user->isAdmin() => static::adminGroups(),
            $user->isAdminOfficer() => static::officerGroups(),
            $user->isLearningSupport() => static::supportGroups(),
            $user->isLiteracyCoordinator() => static::literacyGroups(),
            $user->isStudentLifeCoordinator() => static::studentLifeGroups(),
            $user->isParentRelationsLead() => static::parentRelationsGroups(),
            $user->isItConsultant() => static::itConsultantGroups(),
            $user->isStemCoordinator() => static::stemCoordinatorGroups(),
            $user->isBoard() => static::executiveGroups(),
            $user->isHoS() => static::hosGroups(),
            $user->isHoD() => static::hodGroups(),
            $user->isTeacher() => static::teacherGroups(),
            default => static::executiveGroups(),
        };

        $groups[] = static::accountGroup();

        return $groups;
    }

    public static function areaLabel(?User $user = null): string
    {
        $user ??= Auth::user();
        if (! $user instanceof User) {
            return 'WISCA';
        }

        if ($user->isAdmin()) {
            return 'Administration';
        }

        if ($user->isBoard()) {
            return 'Board';
        }

        if ($user->isHoS()) {
            return 'Head of School';
        }

        if ($user->isHoD()) {
            return 'Head of Department';
        }

        if ($user->isAdminOfficer()) {
            return 'Admin Officer';
        }

        if ($user->isLearningSupport()) {
            return 'Learning Support';
        }

        if ($user->isLiteracyCoordinator()) {
            return 'Literacy Coordinator';
        }

        if ($user->isStudentLifeCoordinator()) {
            return 'Student Life Coordinator';
        }

        if ($user->isParentRelationsLead()) {
            return 'Parent Relations Lead';
        }

        if ($user->isItConsultant()) {
            return 'IT Consultant';
        }

        if ($user->isStemCoordinator()) {
            return 'STEM Coordinator';
        }

        if ($user->isTeacher()) {
            return 'Teacher';
        }

        return 'WISCA';
    }

    public static function homeRoute(): string
    {
        $user = Auth::user();

        if ($user instanceof User && $user->isAdmin()) {
            return route('admin.dashboard');
        }

        if ($user instanceof User && $user->isParentRelationsLead()) {
            return route('partnership.index');
        }

        if ($user instanceof User && $user->isStemCoordinator()) {
            return route('stem.index');
        }

        if ($user instanceof User && $user->isItConsultant()) {
            return route('lms.index');
        }

        return route('dashboard');
    }

    public static function currentTitle(): string
    {
        $route = request()->route()?->getName();

        foreach (static::groups() as $group) {
            foreach ($group['items'] as $item) {
                $patterns = $item['active'] ?? [$item['route'] ?? null];
                $patterns = is_array($patterns) ? $patterns : [$patterns];

                foreach ($patterns as $pattern) {
                    if ($pattern && $route && (request()->routeIs($pattern) || $route === $pattern)) {
                        return $item['label'];
                    }
                }
            }
        }

        return match (true) {
            str_starts_with($route ?? '', 'admin.dashboard') => 'Admin Hub',
            str_starts_with($route ?? '', 'admin.control-panel.') => 'Control Panel',
            str_starts_with($route ?? '', 'admin.sessions.') => 'Academic Sessions',
            str_starts_with($route ?? '', 'admin.terms.') => 'Terms',
            str_starts_with($route ?? '', 'admin.classes.') => 'Classes',
            str_starts_with($route ?? '', 'admin.subjects.') => 'Subjects',
            str_starts_with($route ?? '', 'admin.users.') => 'Staff & Teachers',
            str_starts_with($route ?? '', 'admin.assignments.') => 'Teacher Assignments',
            str_starts_with($route ?? '', 'admin.kpis.') => 'KPI Settings',
            str_starts_with($route ?? '', 'status-thresholds.') => 'Status Thresholds',
            $route === 'coverage-logs.create' => 'Log Coverage',
            str_starts_with($route ?? '', 'lesson-plans.') => 'Lesson Plans',
            str_starts_with($route ?? '', 'homework.') => 'Homework',
            str_starts_with($route ?? '', 'attendance.') => 'Attendance',
            str_starts_with($route ?? '', 'observations.') => 'Observations',
            str_starts_with($route ?? '', 'learners.') => 'Class Roll',
            str_starts_with($route ?? '', 'exam-results.') => 'Examination Results',
            str_starts_with($route ?? '', 'at-risk.') => 'At-Risk Learners',
            str_starts_with($route ?? '', 'intervention-plans.') => 'Intervention Plan',
            str_starts_with($route ?? '', 'chapel.') => 'Chapel & Assembly',
            str_starts_with($route ?? '', 'admin.chapel-activity-types.') => 'Activity Types',
            str_starts_with($route ?? '', 'admin.chapel-sessions.') => 'Chapel Sessions',
            str_starts_with($route ?? '', 'admin.character-domains.') => 'Character Domains',
            str_starts_with($route ?? '', 'character.') => 'Character Development',
            str_starts_with($route ?? '', 'admin.service-activity-types.') => 'Service Activity Types',
            str_starts_with($route ?? '', 'service.') => 'Community Service',
            str_starts_with($route ?? '', 'admin.discipline-incident-types.') => 'Discipline Incident Types',
            str_starts_with($route ?? '', 'discipline.') => 'Restorative Discipline',
            str_starts_with($route ?? '', 'admin.bullying-case-types.') => 'Bullying Case Types',
            str_starts_with($route ?? '', 'bullying.') => 'Anti-Bullying Cases',
            str_starts_with($route ?? '', 'admin.scripture-passages.') => 'Scripture Passages',
            str_starts_with($route ?? '', 'scripture.') => 'Scripture Mastery',
            str_starts_with($route ?? '', 'admin.partnership-charters.') => 'Partnership Charters',
            str_starts_with($route ?? '', 'admin.guardians.') => 'Parent Registry',
            str_starts_with($route ?? '', 'partnership.') => 'Parent Partnership',
            str_starts_with($route ?? '', 'lms.') => 'LMS Adoption',
            str_starts_with($route ?? '', 'admin.stem-project-types.') => 'STEM Project Types',
            str_starts_with($route ?? '', 'stem.') => 'STEM Projects',
            str_starts_with($route ?? '', 'admin.digital-ethics-audit-types.') => 'Digital Ethics Audit Types',
            str_starts_with($route ?? '', 'ethics.') => 'Digital Ethics Audits',
            str_starts_with($route ?? '', 'admin.digital-competency-areas.') => 'Digital Competency Areas',
            str_starts_with($route ?? '', 'competency.') => 'Staff Digital Competency',
            str_starts_with($route ?? '', 'eassessment.') => 'E-Assessment Usage',
            str_starts_with($route ?? '', 'portal-engagement.') => 'Parent Portal Engagement',
            str_starts_with($route ?? '', 'profile.') => 'My Profile',
            default => static::areaLabel(),
        };
    }

    /**
     * @return array<int, array{label: string, items: array<int, array<string, mixed>}>}
     */
    protected static function adminGroups(): array
    {
        return [
            [
                'label' => 'Configuration',
                'items' => [
                    ['route' => 'admin.dashboard', 'active' => ['admin.dashboard'], 'label' => 'Admin Hub', 'icon' => 'dashboard'],
                    ['route' => 'admin.control-panel.edit', 'active' => ['admin.control-panel.*'], 'label' => 'Control Panel', 'icon' => 'building'],
                    ['route' => 'admin.sessions.index', 'active' => ['admin.sessions.*'], 'label' => 'Academic Sessions', 'icon' => 'calendar'],
                    ['route' => 'admin.terms.index', 'active' => ['admin.terms.*'], 'label' => 'Terms', 'icon' => 'calendar'],
                ],
            ],
            [
                'label' => 'School Structure',
                'items' => [
                    ['route' => 'admin.classes.index', 'active' => ['admin.classes.*'], 'label' => 'Classes', 'icon' => 'building'],
                    ['route' => 'admin.subjects.index', 'active' => ['admin.subjects.*'], 'label' => 'Subjects', 'icon' => 'book'],
                    ['route' => 'admin.users.index', 'active' => ['admin.users.*'], 'label' => 'Staff & Teachers', 'icon' => 'users'],
                    ['route' => 'admin.assignments.index', 'active' => ['admin.assignments.*'], 'label' => 'Teacher Assignments', 'icon' => 'list'],
                ],
            ],
            [
                'label' => 'Registers',
                'items' => [
                    ['route' => 'learners.index', 'active' => ['learners.*'], 'label' => 'Class Roll', 'icon' => 'users'],
                    ['route' => 'exam-results.index', 'active' => ['exam-results.*'], 'label' => 'Exam Results', 'icon' => 'chart'],
                    ['route' => 'at-risk.index', 'active' => ['at-risk.*', 'intervention-plans.*'], 'label' => 'At-Risk Plans', 'icon' => 'document'],
                    ['route' => 'attendance.index', 'active' => ['attendance.*'], 'label' => 'Attendance', 'icon' => 'check'],
                    ['route' => 'observations.index', 'active' => ['observations.*'], 'label' => 'Observations', 'icon' => 'document'],
                    ['route' => 'chapel.index', 'active' => ['chapel.*'], 'label' => 'Chapel & Assembly', 'icon' => 'check'],
                    ['route' => 'service.index', 'active' => ['service.*'], 'label' => 'Community Service', 'icon' => 'document'],
                    ['route' => 'discipline.index', 'active' => ['discipline.*'], 'label' => 'Restorative Discipline', 'icon' => 'document'],
                    ['route' => 'bullying.index', 'active' => ['bullying.*'], 'label' => 'Anti-Bullying Cases', 'icon' => 'document'],
                    ['route' => 'scripture.index', 'active' => ['scripture.*'], 'label' => 'Scripture Mastery', 'icon' => 'book'],
                    ['route' => 'partnership.index', 'active' => ['partnership.*'], 'label' => 'Parent Partnership', 'icon' => 'document'],
                    ['route' => 'lms.index', 'active' => ['lms.*'], 'label' => 'LMS Adoption', 'icon' => 'chart'],
                    ['route' => 'stem.index', 'active' => ['stem.*'], 'label' => 'STEM Projects', 'icon' => 'book'],
                    ['route' => 'ethics.index', 'active' => ['ethics.*'], 'label' => 'Digital Ethics Audits', 'icon' => 'document'],
                    ['route' => 'competency.index', 'active' => ['competency.*'], 'label' => 'Staff Digital Competency', 'icon' => 'users'],
                    ['route' => 'eassessment.index', 'active' => ['eassessment.*'], 'label' => 'E-Assessment Usage', 'icon' => 'chart'],
                    ['route' => 'portal-engagement.index', 'active' => ['portal-engagement.*'], 'label' => 'Parent Portal Engagement', 'icon' => 'users'],
                ],
            ],
            [
                'label' => 'Christocentric',
                'items' => [
                    ['route' => 'admin.chapel-activity-types.index', 'active' => ['admin.chapel-activity-types.*'], 'label' => 'Activity Types', 'icon' => 'list'],
                    ['route' => 'admin.chapel-sessions.index', 'active' => ['admin.chapel-sessions.*'], 'label' => 'Chapel Sessions', 'icon' => 'calendar'],
                    ['route' => 'admin.character-domains.index', 'active' => ['admin.character-domains.*'], 'label' => 'Character Domains', 'icon' => 'list'],
                    ['route' => 'admin.service-activity-types.index', 'active' => ['admin.service-activity-types.*'], 'label' => 'Service Activity Types', 'icon' => 'list'],
                    ['route' => 'admin.discipline-incident-types.index', 'active' => ['admin.discipline-incident-types.*'], 'label' => 'Discipline Incident Types', 'icon' => 'list'],
                    ['route' => 'admin.bullying-case-types.index', 'active' => ['admin.bullying-case-types.*'], 'label' => 'Bullying Case Types', 'icon' => 'list'],
                    ['route' => 'admin.scripture-passages.index', 'active' => ['admin.scripture-passages.*'], 'label' => 'Scripture Passages', 'icon' => 'book'],
                    ['route' => 'admin.partnership-charters.index', 'active' => ['admin.partnership-charters.*'], 'label' => 'Partnership Charters', 'icon' => 'document'],
                    ['route' => 'admin.guardians.index', 'active' => ['admin.guardians.*'], 'label' => 'Parent Registry', 'icon' => 'users'],
                    ['route' => 'admin.stem-project-types.index', 'active' => ['admin.stem-project-types.*'], 'label' => 'STEM Project Types', 'icon' => 'list'],
                    ['route' => 'admin.digital-ethics-audit-types.index', 'active' => ['admin.digital-ethics-audit-types.*'], 'label' => 'Digital Ethics Audit Types', 'icon' => 'list'],
                    ['route' => 'admin.digital-competency-areas.index', 'active' => ['admin.digital-competency-areas.*'], 'label' => 'Digital Competency Areas', 'icon' => 'list'],
                ],
            ],
            [
                'label' => 'Strategy',
                'items' => [
                    ['route' => 'admin.kpis.index', 'active' => ['admin.kpis.*'], 'label' => 'KPI Settings', 'icon' => 'chart'],
                    ['route' => 'status-thresholds.edit', 'active' => ['status-thresholds.*'], 'label' => 'Status Thresholds', 'icon' => 'chart'],
                    ['route' => 'dashboard', 'active' => ['dashboard'], 'label' => 'Executive Dashboard', 'icon' => 'chart'],
                ],
            ],
        ];
    }

    /**
     * @return array{label: string, items: array<int, array<string, mixed>>}
     */
    protected static function accountGroup(): array
    {
        return [
            'label' => 'Account',
            'items' => [
                ['route' => 'profile.edit', 'active' => ['profile.*'], 'label' => 'My Profile', 'icon' => 'users'],
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, items: array<int, array<string, mixed>}>}
     */
    protected static function executiveGroups(): array
    {
        return [
            [
                'label' => 'Strategy',
                'items' => [
                    ['route' => 'dashboard', 'active' => ['dashboard'], 'label' => 'Executive Dashboard', 'icon' => 'dashboard'],
                    ['route' => 'status-thresholds.edit', 'active' => ['status-thresholds.*'], 'label' => 'Status Thresholds', 'icon' => 'chart'],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, items: array<int, array<string, mixed>}>}
     */
    protected static function hosGroups(): array
    {
        return [
            ...static::executiveGroups(),
            [
                'label' => 'Operations',
                'items' => [
                    ['route' => 'learners.index', 'active' => ['learners.*'], 'label' => 'Class Roll', 'icon' => 'users'],
                    ['route' => 'exam-results.index', 'active' => ['exam-results.*'], 'label' => 'Exam Results', 'icon' => 'chart'],
                    ['route' => 'at-risk.index', 'active' => ['at-risk.*', 'intervention-plans.*'], 'label' => 'At-Risk Plans', 'icon' => 'document'],
                    ['route' => 'attendance.index', 'active' => ['attendance.*'], 'label' => 'Attendance', 'icon' => 'check'],
                    ['route' => 'homework.index', 'active' => ['homework.*'], 'label' => 'Homework', 'icon' => 'list'],
                    ['route' => 'observations.index', 'active' => ['observations.*'], 'label' => 'Observations', 'icon' => 'document'],
                    ['route' => 'chapel.index', 'active' => ['chapel.*'], 'label' => 'Chapel & Assembly', 'icon' => 'check'],
                    ['route' => 'character.index', 'active' => ['character.*'], 'label' => 'Character Development', 'icon' => 'document'],
                    ['route' => 'service.index', 'active' => ['service.*'], 'label' => 'Community Service', 'icon' => 'document'],
                    ['route' => 'discipline.index', 'active' => ['discipline.*'], 'label' => 'Restorative Discipline', 'icon' => 'document'],
                    ['route' => 'bullying.index', 'active' => ['bullying.*'], 'label' => 'Anti-Bullying Cases', 'icon' => 'document'],
                    ['route' => 'scripture.index', 'active' => ['scripture.*'], 'label' => 'Scripture Mastery', 'icon' => 'book'],
                    ['route' => 'partnership.index', 'active' => ['partnership.*'], 'label' => 'Parent Partnership', 'icon' => 'document'],
                    ['route' => 'lms.index', 'active' => ['lms.*'], 'label' => 'LMS Adoption', 'icon' => 'chart'],
                    ['route' => 'stem.index', 'active' => ['stem.*'], 'label' => 'STEM Projects', 'icon' => 'book'],
                    ['route' => 'ethics.index', 'active' => ['ethics.*'], 'label' => 'Digital Ethics Audits', 'icon' => 'document'],
                    ['route' => 'competency.index', 'active' => ['competency.*'], 'label' => 'Staff Digital Competency', 'icon' => 'users'],
                    ['route' => 'eassessment.index', 'active' => ['eassessment.*'], 'label' => 'E-Assessment Usage', 'icon' => 'chart'],
                    ['route' => 'portal-engagement.index', 'active' => ['portal-engagement.*'], 'label' => 'Parent Portal Engagement', 'icon' => 'users'],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, items: array<int, array<string, mixed>}>}
     */
    protected static function officerGroups(): array
    {
        return [
            [
                'label' => 'Registers',
                'items' => [
                    ['route' => 'dashboard', 'active' => ['dashboard'], 'label' => 'Attendance Week', 'icon' => 'dashboard'],
                    ['route' => 'learners.index', 'active' => ['learners.*'], 'label' => 'Class Roll', 'icon' => 'users'],
                    ['route' => 'attendance.index', 'active' => ['attendance.*'], 'label' => 'Attendance Log', 'icon' => 'check'],
                    ['route' => 'chapel.index', 'active' => ['chapel.*'], 'label' => 'Chapel & Assembly', 'icon' => 'check'],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, items: array<int, array<string, mixed>}>}
     */
    protected static function hodGroups(): array
    {
        return [
            [
                'label' => 'Operations',
                'items' => [
                    ['route' => 'dashboard', 'active' => ['dashboard'], 'label' => 'Verification Queue', 'icon' => 'check'],
                    ['route' => 'lesson-plans.index', 'active' => ['lesson-plans.*'], 'label' => 'Lesson Plans', 'icon' => 'document'],
                    ['route' => 'exam-results.index', 'active' => ['exam-results.*'], 'label' => 'Exam Results', 'icon' => 'chart'],
                    ['route' => 'at-risk.index', 'active' => ['at-risk.*', 'intervention-plans.*'], 'label' => 'At-Risk Plans', 'icon' => 'document'],
                    ['route' => 'reading.index', 'active' => ['reading.*'], 'label' => 'Reading Progress', 'icon' => 'book'],
                    ['route' => 'learners.index', 'active' => ['learners.*'], 'label' => 'Class Roll', 'icon' => 'users'],
                    ['route' => 'homework.index', 'active' => ['homework.*'], 'label' => 'Homework', 'icon' => 'list'],
                    ['route' => 'attendance.index', 'active' => ['attendance.*'], 'label' => 'Attendance', 'icon' => 'check'],
                    ['route' => 'observations.index', 'active' => ['observations.*'], 'label' => 'Observations', 'icon' => 'document'],
                    ['route' => 'coverage-logs.index', 'active' => ['coverage-logs.*'], 'label' => 'Coverage Logs', 'icon' => 'document'],
                    ['route' => 'chapel.index', 'active' => ['chapel.*'], 'label' => 'Chapel & Assembly', 'icon' => 'check'],
                    ['route' => 'character.index', 'active' => ['character.*'], 'label' => 'Character Development', 'icon' => 'document'],
                    ['route' => 'service.index', 'active' => ['service.*'], 'label' => 'Community Service', 'icon' => 'document'],
                    ['route' => 'discipline.index', 'active' => ['discipline.*'], 'label' => 'Restorative Discipline', 'icon' => 'document'],
                    ['route' => 'bullying.index', 'active' => ['bullying.*'], 'label' => 'Anti-Bullying Cases', 'icon' => 'document'],
                    ['route' => 'scripture.index', 'active' => ['scripture.*'], 'label' => 'Scripture Mastery', 'icon' => 'book'],
                    ['route' => 'stem.index', 'active' => ['stem.*'], 'label' => 'STEM Projects', 'icon' => 'book'],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, items: array<int, array<string, mixed>}>}
     */
    protected static function teacherGroups(): array
    {
        return [
            [
                'label' => 'Curriculum',
                'items' => [
                    ['route' => 'dashboard', 'active' => ['dashboard'], 'label' => 'My Week', 'icon' => 'dashboard'],
                    ['route' => 'lesson-plans.index', 'active' => ['lesson-plans.*'], 'label' => 'Lesson Plans', 'icon' => 'book'],
                    ['route' => 'homework.index', 'active' => ['homework.*'], 'label' => 'Homework', 'icon' => 'list'],
                    ['route' => 'attendance.index', 'active' => ['attendance.*'], 'label' => 'Attendance', 'icon' => 'check'],
                    ['route' => 'exam-results.index', 'active' => ['exam-results.*'], 'label' => 'Exam Results', 'icon' => 'chart'],
                    ['route' => 'at-risk.index', 'active' => ['at-risk.*', 'intervention-plans.*'], 'label' => 'At-Risk Plans', 'icon' => 'document'],
                    ['route' => 'reading.index', 'active' => ['reading.*'], 'label' => 'Reading Progress', 'icon' => 'book'],
                    ['route' => 'learners.index', 'active' => ['learners.*'], 'label' => 'Class Roll', 'icon' => 'users'],
                    ['route' => 'observations.index', 'active' => ['observations.*'], 'label' => 'Observations', 'icon' => 'book'],
                    ['route' => 'coverage-logs.index', 'active' => ['coverage-logs.*'], 'label' => 'Coverage Logs', 'icon' => 'document'],
                    ['route' => 'character.index', 'active' => ['character.*'], 'label' => 'Character Development', 'icon' => 'document'],
                    ['route' => 'service.index', 'active' => ['service.*'], 'label' => 'Community Service', 'icon' => 'document'],
                    ['route' => 'discipline.index', 'active' => ['discipline.*'], 'label' => 'Restorative Discipline', 'icon' => 'document'],
                    ['route' => 'bullying.index', 'active' => ['bullying.*'], 'label' => 'Anti-Bullying Cases', 'icon' => 'document'],
                    ['route' => 'scripture.index', 'active' => ['scripture.*'], 'label' => 'Scripture Mastery', 'icon' => 'book'],
                    ['route' => 'stem.index', 'active' => ['stem.*'], 'label' => 'STEM Projects', 'icon' => 'book'],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, items: array<int, array<string, mixed>}>}
     */
    protected static function literacyGroups(): array
    {
        return [
            [
                'label' => 'Literacy',
                'items' => [
                    ['route' => 'dashboard', 'active' => ['dashboard'], 'label' => 'Dashboard', 'icon' => 'dashboard'],
                    ['route' => 'reading.index', 'active' => ['reading.*'], 'label' => 'Reading Progress', 'icon' => 'book'],
                    ['route' => 'learners.index', 'active' => ['learners.*'], 'label' => 'Class Roll', 'icon' => 'users'],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, items: array<int, array<string, mixed>}>}
     */
    protected static function supportGroups(): array
    {
        return [
            [
                'label' => 'Learning Support',
                'items' => [
                    ['route' => 'dashboard', 'active' => ['dashboard'], 'label' => 'At-Risk Caseload', 'icon' => 'dashboard'],
                    ['route' => 'at-risk.index', 'active' => ['at-risk.*', 'intervention-plans.*'], 'label' => 'Intervention Plans', 'icon' => 'document'],
                    ['route' => 'reading.index', 'active' => ['reading.*'], 'label' => 'Reading Progress', 'icon' => 'book'],
                    ['route' => 'learners.index', 'active' => ['learners.*'], 'label' => 'Class Roll', 'icon' => 'users'],
                    ['route' => 'exam-results.index', 'active' => ['exam-results.*'], 'label' => 'Exam Results', 'icon' => 'chart'],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, items: array<int, array<string, mixed>}>}
     */
    protected static function studentLifeGroups(): array
    {
        return [
            [
                'label' => 'Christocentric',
                'items' => [
                    ['route' => 'dashboard', 'active' => ['dashboard'], 'label' => 'Dashboard', 'icon' => 'dashboard'],
                    ['route' => 'chapel.index', 'active' => ['chapel.*'], 'label' => 'Chapel & Assembly', 'icon' => 'check'],
                    ['route' => 'character.index', 'active' => ['character.*'], 'label' => 'Character Development', 'icon' => 'document'],
                    ['route' => 'service.index', 'active' => ['service.*'], 'label' => 'Community Service', 'icon' => 'document'],
                    ['route' => 'discipline.index', 'active' => ['discipline.*'], 'label' => 'Restorative Discipline', 'icon' => 'document'],
                    ['route' => 'bullying.index', 'active' => ['bullying.*'], 'label' => 'Anti-Bullying Cases', 'icon' => 'document'],
                    ['route' => 'scripture.index', 'active' => ['scripture.*'], 'label' => 'Scripture Mastery', 'icon' => 'book'],
                    ['route' => 'partnership.index', 'active' => ['partnership.*'], 'label' => 'Parent Partnership', 'icon' => 'document'],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, items: array<int, array<string, mixed>}>}
     */
    protected static function parentRelationsGroups(): array
    {
        return [
            [
                'label' => 'Parent Partnership',
                'items' => [
                    ['route' => 'partnership.index', 'active' => ['partnership.*'], 'label' => 'Partnership Commitments', 'icon' => 'document'],
                    ['route' => 'portal-engagement.index', 'active' => ['portal-engagement.*'], 'label' => 'Parent Portal Engagement', 'icon' => 'users'],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, items: array<int, array<string, mixed>}>}
     */
    protected static function itConsultantGroups(): array
    {
        return [
            [
                'label' => 'Digital Innovation',
                'items' => [
                    ['route' => 'lms.index', 'active' => ['lms.*'], 'label' => 'LMS Adoption', 'icon' => 'chart'],
                    ['route' => 'ethics.index', 'active' => ['ethics.*'], 'label' => 'Digital Ethics Audits', 'icon' => 'document'],
                    ['route' => 'competency.index', 'active' => ['competency.*'], 'label' => 'Staff Digital Competency', 'icon' => 'users'],
                    ['route' => 'eassessment.index', 'active' => ['eassessment.*'], 'label' => 'E-Assessment Usage', 'icon' => 'chart'],
                    ['route' => 'portal-engagement.index', 'active' => ['portal-engagement.*'], 'label' => 'Parent Portal Engagement', 'icon' => 'users'],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, items: array<int, array<string, mixed>}>}
     */
    protected static function stemCoordinatorGroups(): array
    {
        return [
            [
                'label' => 'Digital Innovation',
                'items' => [
                    ['route' => 'stem.index', 'active' => ['stem.*'], 'label' => 'STEM Projects', 'icon' => 'book'],
                ],
            ],
        ];
    }
}
