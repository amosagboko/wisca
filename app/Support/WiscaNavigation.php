<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class WiscaNavigation
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function groups(?User $user = null): array
    {
        $user ??= Auth::user();
        if (! $user instanceof User) {
            return [static::accountGroup()];
        }

        $groups = [];

        if ($user->isAdmin()) {
            $groups = array_merge($groups, static::adminLeadGroups());
        } else {
            $home = static::homeGroup($user);
            if ($home) {
                $groups[] = $home;
            }
        }

        $groups = array_merge($groups, static::pillarGroups($user));

        $supporting = static::supportingGroup($user);
        if ($supporting) {
            $groups[] = $supporting;
        }

        if ($user->isAdmin()) {
            $groups = array_merge($groups, static::adminLookupsAndStrategy());
        }

        $groups[] = static::accountGroup();

        return $groups;
    }

    public static function areaLabel(?User $user = null): string
    {
        $user ??= Auth::user();
        if (! $user instanceof User) {
            return 'WISCA PEMS';
        }

        $label = RoleLabels::label($user->getRoleNames()->first());

        return $label === '—' ? 'WISCA PEMS' : $label;
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

        if ($user instanceof User && $user->isIctCoordinator()) {
            return route('lms.index');
        }

        if ($user instanceof User && $user->isAdminManager()) {
            return route('portal-engagement.index');
        }

        if ($user instanceof User && $user->isChaplain()) {
            return route('chapel.index');
        }

        if ($user instanceof User && $user->isSubjectLead()) {
            return route('coverage-logs.index');
        }

        return route('dashboard');
    }

    public static function currentTitle(): string
    {
        $route = request()->route()?->getName();

        if ($route === 'activities.coming-soon') {
            $slug = (string) request()->route('activity');
            $activity = WiscaOperationalCatalog::findActivity($slug);

            return $activity['label'] ?? 'Coming later';
        }

        foreach (static::walkItems(static::groups()) as $item) {
            if (static::itemIsActive($item)) {
                return $item['label'];
            }
        }

        return match (true) {
            str_starts_with($route ?? '', 'admin.dashboard') => 'Admin Hub',
            str_starts_with($route ?? '', 'admin.control-panel.') => 'Control Panel',
            str_starts_with($route ?? '', 'academic-period.') => 'Academic Period',
            $route === 'admin.sessions.create' => 'Create / activate session',
            str_starts_with($route ?? '', 'admin.sessions.') => 'Academic Sessions',
            str_starts_with($route ?? '', 'admin.terms.') => 'Terms',
            str_starts_with($route ?? '', 'admin.classes.') => 'Classes',
            str_starts_with($route ?? '', 'admin.departments.') => 'Departments',
            str_starts_with($route ?? '', 'admin.subjects.') => 'Subjects',
            str_starts_with($route ?? '', 'admin.users.') => 'Staff & Teachers',
            str_starts_with($route ?? '', 'admin.assignments.') => 'Teacher Assignments',
            str_starts_with($route ?? '', 'admin.kpis.') => 'KPI Settings',
            str_starts_with($route ?? '', 'status-thresholds.') => 'Status Thresholds',
            str_starts_with($route ?? '', 'planning-policy.') => 'Planning Policy',
            str_starts_with($route ?? '', 'curriculum-coverage.') => 'Curriculum Coverage',
            str_starts_with($route ?? '', 'catch-ups.') => 'Curriculum Coverage',
            $route === 'coverage-logs.create' => 'Log Coverage',
            str_starts_with($route ?? '', 'lesson-plans.') => 'Lesson Plan/Note Submission & Approval',
            str_starts_with($route ?? '', 'homework.') => 'Homework/Class Work Completion Rate',
            str_starts_with($route ?? '', 'attendance.') => 'Learner Attendance Rate',
            str_starts_with($route ?? '', 'observations.') => 'Effective or Better Lesson Observations',
            str_starts_with($route ?? '', 'learners.') => 'Class Roll',
            str_starts_with($route ?? '', 'exam-results.') => 'School-wide Examination Pass Rate',
            str_starts_with($route ?? '', 'at-risk.') => 'At-Risk Learners with Active Intervention Plan',
            str_starts_with($route ?? '', 'intervention-plans.') => 'Intervention Plan',
            str_starts_with($route ?? '', 'chapel.') => 'Daily Devotion & Chapel Participation',
            str_starts_with($route ?? '', 'admin.chapel-activity-types.') => 'Activity Types',
            str_starts_with($route ?? '', 'admin.chapel-sessions.') => 'Chapel Sessions',
            str_starts_with($route ?? '', 'admin.character-domains.') => 'Character Domains',
            str_starts_with($route ?? '', 'character.') => 'Christian Character Rating (Secure +)',
            str_starts_with($route ?? '', 'admin.service-activity-types.') => 'Service Activity Types',
            str_starts_with($route ?? '', 'service.') => 'Community Service Hours Per Learner',
            str_starts_with($route ?? '', 'admin.discipline-incident-types.') => 'Discipline Incident Types',
            str_starts_with($route ?? '', 'discipline.') => 'Resolved Restorative Discipline Cases',
            str_starts_with($route ?? '', 'admin.bullying-case-types.') => 'Bullying Case Types',
            str_starts_with($route ?? '', 'bullying.') => 'Bullying Incident Resolution Rate',
            str_starts_with($route ?? '', 'admin.scripture-passages.') => 'Scripture Passages',
            str_starts_with($route ?? '', 'scripture.') => 'Scripture Memory & Application Mastery',
            str_starts_with($route ?? '', 'admin.partnership-charters.') => 'Partnership Charters',
            str_starts_with($route ?? '', 'admin.guardians.') => 'Parent Registry',
            str_starts_with($route ?? '', 'partnership.') => 'Parent-School Christian Culture Alignment',
            str_starts_with($route ?? '', 'lms.') => 'Digital Portal and LMS Adoption',
            str_starts_with($route ?? '', 'admin.stem-project-types.') => 'STEM Project Types',
            str_starts_with($route ?? '', 'stem.') => 'Learner Coding & STEM Practical Completion',
            str_starts_with($route ?? '', 'admin.digital-ethics-audit-types.') => 'Digital Ethics Audit Types',
            str_starts_with($route ?? '', 'ethics.') => 'AI & Technology Ethics Compliance',
            str_starts_with($route ?? '', 'admin.digital-competency-areas.') => 'Digital Competency Areas',
            str_starts_with($route ?? '', 'competency.') => 'Staff Digital Competency Mastery',
            str_starts_with($route ?? '', 'eassessment.') => 'Digital Assessment & E-Portfolio Usage',
            str_starts_with($route ?? '', 'portal-engagement.') => 'Parent Portal Engagement Rate',
            str_starts_with($route ?? '', 'reading.') => 'Literacy Progress (≥1 Year Growth)',
            str_starts_with($route ?? '', 'schemes.') => 'Curriculum Coverage Rate',
            str_starts_with($route ?? '', 'coverage-logs.') => 'Curriculum Coverage Rate',
            str_starts_with($route ?? '', 'profile.') => 'My Profile',
            default => static::areaLabel(),
        };
    }

    public static function itemHref(array $item): ?string
    {
        $route = $item['route'] ?? null;
        if (! $route || ! Route::has($route)) {
            return null;
        }

        return route($route, $item['params'] ?? []);
    }

    public static function itemIsActive(array $item): bool
    {
        $patterns = $item['active'] ?? [$item['route'] ?? null];
        $patterns = is_array($patterns) ? $patterns : [$patterns];

        foreach ($patterns as $pattern) {
            if (! $pattern || ! request()->routeIs($pattern)) {
                continue;
            }

            $expected = $item['params']['activity'] ?? null;
            if ($expected && request()->route('activity') !== $expected) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function navRoleNames(User $user): array
    {
        $roles = $user->getRoleNames()->all();

        if (in_array('it_consultant', $roles, true) && ! in_array('ict_coordinator', $roles, true)) {
            $roles[] = 'ict_coordinator';
        }

        return $roles;
    }

    public static function seesFullTree(User $user): bool
    {
        return $user->isAdmin()
            || $user->isHoS()
            || $user->isAssistantHead()
            || $user->isBoard();
    }

    /**
     * Sub-activities this user may see (v2 Responsibility), including oversight and extra_roles.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function visibleSubs(array $activity, User $user): array
    {
        return WiscaOperationalCatalog::visibleChildren(
            $activity,
            static::navRoleNames($user),
            static::seesFullTree($user)
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function pillarGroups(User $user): array
    {
        $groups = [];

        foreach (WiscaOperationalCatalog::pillars() as $pillar) {
            $activities = [];

            foreach ($pillar['activities'] as $activity) {
                $children = static::visibleSubs($activity, $user);

                if ($children === []) {
                    continue;
                }

                $activities[] = [
                    'label' => $activity['label'],
                    'icon' => $activity['icon'],
                    'route' => $activity['route'],
                    'active' => $activity['active'],
                    'params' => $activity['params'] ?? [],
                    'children' => $children,
                ];
            }

            if ($activities === []) {
                continue;
            }

            $groups[] = [
                'type' => 'pillar',
                'label' => $pillar['label'],
                'icon' => $pillar['icon'],
                'items' => $activities,
            ];
        }

        return $groups;
    }

    /**
     * @return array{label: string, items: array<int, array<string, mixed>>}|null
     */
    protected static function homeGroup(User $user): ?array
    {
        if ($user->isParentRelationsLead() || $user->isStemCoordinator() || $user->isIctCoordinator() || $user->isAdminManager()) {
            return null;
        }

        if ($user->isBoard() || $user->isHoS() || $user->isAssistantHead()) {
            $items = [
                ['route' => 'dashboard', 'active' => ['dashboard'], 'label' => 'Executive Dashboard', 'icon' => 'dashboard'],
                ['route' => 'status-thresholds.edit', 'active' => ['status-thresholds.*'], 'label' => 'Status Thresholds', 'icon' => 'chart'],
                ['route' => 'schemes.index', 'active' => ['schemes.*'], 'label' => 'Schemes of Work', 'icon' => 'document'],
            ];

            if ($user->canManageAcademicPeriod()) {
                $items[] = ['route' => 'academic-period.show', 'active' => ['academic-period.*'], 'label' => 'Academic Period', 'icon' => 'calendar'];
            }
            $items[] = ['route' => 'curriculum-coverage.report', 'active' => ['curriculum-coverage.*', 'catch-ups.*'], 'label' => 'Curriculum Coverage', 'icon' => 'chart'];
            if ($user->isHoS() || $user->isAdmin()) {
                $items[] = ['route' => 'planning-policy.edit', 'active' => ['planning-policy.*'], 'label' => 'Planning Policy', 'icon' => 'chart'];
            }

            return [
                'label' => 'Strategy',
                'items' => $items,
            ];
        }

        $label = match (true) {
            $user->isHoD() => 'Verification Queue',
            $user->isTeacher() => 'My Week',
            $user->isAdminOfficer() => 'Attendance Week',
            $user->isLearningSupport() => 'At-Risk Caseload',
            default => 'Dashboard',
        };

        $items = [
            ['route' => 'dashboard', 'active' => ['dashboard'], 'label' => $label, 'icon' => 'dashboard'],
        ];

        if ($user->isHoD()) {
            $items[] = ['route' => 'schemes.index', 'active' => ['schemes.*'], 'label' => 'Schemes of Work', 'icon' => 'document'];
            $items[] = ['route' => 'curriculum-coverage.report', 'active' => ['curriculum-coverage.*', 'catch-ups.*'], 'label' => 'Curriculum Coverage', 'icon' => 'chart'];
        }

        if ($user->isTeacher() && ! $user->isHoD()) {
            $items[] = ['route' => 'curriculum-coverage.report', 'active' => ['curriculum-coverage.*'], 'label' => 'My Coverage', 'icon' => 'chart'];
        }

        return [
            'label' => 'Home',
            'items' => $items,
        ];
    }

    /**
     * @return array{label: string, items: array<int, array<string, mixed>>}|null
     */
    protected static function supportingGroup(User $user): ?array
    {
        if ($user->isAdmin() || ! $user->canViewLearners()) {
            return null;
        }

        return [
            'label' => 'School',
            'items' => [
                ['route' => 'learners.index', 'active' => ['learners.*'], 'label' => 'Class Roll', 'icon' => 'users'],
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, items: array<int, array<string, mixed>}>}
     */
    protected static function adminLeadGroups(): array
    {
        return [
            [
                'label' => 'Configuration',
                'items' => [
                    ['route' => 'admin.dashboard', 'active' => ['admin.dashboard'], 'label' => 'Admin Hub', 'icon' => 'dashboard'],
                    ['route' => 'admin.control-panel.edit', 'active' => ['admin.control-panel.*'], 'label' => 'Control Panel', 'icon' => 'building'],
                    ['route' => 'academic-period.show', 'active' => ['academic-period.*'], 'label' => 'Academic Period', 'icon' => 'calendar'],
                    ['route' => 'admin.sessions.create', 'active' => ['admin.sessions.create'], 'label' => 'Create / activate session', 'icon' => 'calendar'],
                    ['route' => 'planning-policy.edit', 'active' => ['planning-policy.*'], 'label' => 'Planning Policy', 'icon' => 'chart'],
                    ['route' => 'curriculum-coverage.report', 'active' => ['curriculum-coverage.*', 'catch-ups.*'], 'label' => 'Curriculum Coverage', 'icon' => 'chart'],
                    ['route' => 'admin.sessions.index', 'active' => ['admin.sessions.*'], 'label' => 'Academic Sessions', 'icon' => 'calendar'],
                    ['route' => 'admin.terms.index', 'active' => ['admin.terms.*'], 'label' => 'Terms', 'icon' => 'calendar'],
                ],
            ],
            [
                'label' => 'School Structure',
                'items' => [
                    ['route' => 'admin.classes.index', 'active' => ['admin.classes.*'], 'label' => 'Classes', 'icon' => 'building'],
                    ['route' => 'admin.departments.index', 'active' => ['admin.departments.*'], 'label' => 'Departments', 'icon' => 'building'],
                    ['route' => 'admin.subjects.index', 'active' => ['admin.subjects.*'], 'label' => 'Subjects', 'icon' => 'book'],
                    ['route' => 'admin.users.index', 'active' => ['admin.users.*'], 'label' => 'Staff & Teachers', 'icon' => 'users'],
                    ['route' => 'admin.assignments.index', 'active' => ['admin.assignments.*'], 'label' => 'Teacher Assignments', 'icon' => 'list'],
                    ['route' => 'learners.index', 'active' => ['learners.*'], 'label' => 'Class Roll', 'icon' => 'users'],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, items: array<int, array<string, mixed>}>}
     */
    protected static function adminLookupsAndStrategy(): array
    {
        return [
            [
                'label' => 'Look-up lists',
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
                    ['route' => 'schemes.index', 'active' => ['schemes.*'], 'label' => 'Schemes of Work', 'icon' => 'document'],
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
     * @param  array<int, array<string, mixed>>  $groups
     * @return \Generator<int, array<string, mixed>>
     */
    protected static function walkItems(array $groups): \Generator
    {
        foreach ($groups as $group) {
            foreach ($group['items'] ?? [] as $item) {
                yield $item;
                foreach ($item['children'] ?? [] as $child) {
                    yield $child;
                }
            }
        }
    }
}
