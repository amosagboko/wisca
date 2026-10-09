# WISCA session handoff

Saved so a later chat can resume without re-deriving this thread. Local work is **not committed**. Remote: `https://github.com/amosagboko/wisca.git` (`main`). Do not create a second backup copy.

## Where we stopped

Teacher–HOD Academic Excellence stage is complete through **HOD plan-review checklist (AE-05.2)**. Next product slices were **not** started: auto-IIP, AE-01.5, SIP timetable, DMS, CE/DI, generic case engine.

## Locked architecture (do not reopen)

- Teachers capture; HODs verify. Leadership consumes KPI output.
- Composed inboxes (`TeacherTaskFeed`, `HodReviewFeed`) — **no** generic `work_items` / case engine.
- Planning chain: **Active SoW** → lesson plan → HOD LP approval → delivery → coverage log → HOD verify.
- Catch-up is topic-linked (AE-01.4), not IIP. Executive AE-01 uses `measure_key` null; operational coverage uses `measure_key`.
- `lesson_plans.topic_id` stays. No sub_topics. `Term::lessonPlanDueAt()` stays Monday (legacy helper). New due dates and AE-05 period windows use `PlanningPolicy` via `LessonPlanCalculationService`.
- Tests: sqlite `migrate` **or** MySQL `DatabaseTransactions` from `.env`. **Never** `RefreshDatabase` on live `wisca`. PowerShell: `;` not `&&`.

## What shipped in this thread (high level)

1. P2 Active SoW as planning baseline; Round 1 catch-up plans.
2. Teacher My Week task feed; HOD Reviews due inbox.
3. Same-row review status on homework/attendance logs and exam sittings (verify/reject).
4. Auto-flag below-pass from **verified** marksheets onto the existing at-risk register (no IIP). AE-02/03/04/07 formulas unchanged by HOD status.
5. **HOD queue scale:**
   - `departments` + `subjects.department_id` + `users.department_id`
   - `HodScope` on dashboard, `/lesson-plans`, approve/reject, coverage/homework/attendance/exams
   - Pending plans/coverage: selected session + term (current term default), optional week
   - Paginated plan/coverage cards; Reviews due capped at 25; group by teacher/class
   - Teacher/class/subject filters in SQL; term dropdown wired
   - Dashboard exams use `sittingOverviews` / aggregated `termSummary` (no per-assignment class roll)
   - Existing school backfilled to **General**; split later via Admin → Departments
6. **Manual IIP queue (not auto-create):** HOD dashboard “Plans needed”; IIP form prefills from verified below-pass; at-risk index paginated and HOD-scoped to department classes. AE-07 still active Tier 2/3 ÷ identified.
7. **Leadership verify (not a third stamp):** HoS/Assistant Head see school-wide exception counts (submitted plans/coverage/homework/registers/exams + identified without IIP). They record `leadership_week_reviews` with a snapshot. Board is read-only. Week sign-off does **not** approve plans, verify logs, or change AE-01/02/03/04/07 formulas.
8. **Planning policy in the calculator:** `dueAtForTopic` / `dueAtForWeek` and AE-05 `period_start`/`period_end` use the school due weekday (default Thursday). `Term::lessonPlanDueAt()` remains Monday. AE-05 rate is still approved on-time ÷ required. Historical `due_at` values are not rewritten.
9. **HOD catch-up queue:** Reviews due + dashboard panel list Active SoW topics **behind** the instructional week without verified coverage. HOD opens catch-up from the dashboard (same `TopicCatchUpService`). Current-week uncovered topics are not listed. AE-01.4 still identified → addressed after verify; executive AE-01 unchanged. Leadership sees a school-wide behind-topic count.
10. **24-hour HOD plan SLA (AE-05.3 clock):** Submitted plans use `submitted_at + 24h`. Overdue items badge **Overdue** on Reviews due and the plan card. Leadership sees a past-SLA count. Executive AE-05 is still approved on-time ÷ required (teacher planning-policy due day). Historical rows are not rewritten.
11. **HOD plan-review checklist (AE-05.2):** `lesson_plans.review_checklist` JSON + `LessonPlanReview` (alignment, quality, engagement, assessment). Approve requires every item pass. Return requires a complete checklist plus a reason. Teacher resubmit clears the snapshot. Operational AE-05.2 = checklist-complete approved/rejected ÷ required. Operational AE-05.3 counts approve **or** return within 24h. Executive AE-05 (`measure_key` null) is unchanged. Historical approved plans without a checklist drop from AE-05.2. Dashboard and `/lesson-plans` share the review form.

Demo: `hod@wisca.test` / `hos@wisca.test` / `password`. HOD currently sees **General** until admin splits departments. Instructional week in demo can differ from calendar today.

## Key files

- `app/Services/HodScope.php`
- `app/Services/CurriculumDashboardService.php` (`hodOperations`)
- `app/Http/Controllers/DashboardController.php` (HOD branch)
- `app/Http/Controllers/LessonPlanController.php`
- `app/Http/Controllers/Admin/DepartmentController.php`
- `database/migrations/2026_10_07_171500_create_departments_and_hod_scope.php`
- `tests/Feature/HodQueueScaleTest.php`
- `app/Services/LeadershipReviewFeed.php`
- `app/Http/Controllers/LeadershipWeekReviewController.php`
- `database/migrations/2026_10_08_110500_create_leadership_week_reviews_table.php`
- `tests/Feature/LeadershipWeekReviewTest.php`
- `app/Services/LessonPlanReview.php`
- `database/migrations/2026_10_09_104800_add_review_checklist_to_lesson_plans.php`
- `resources/views/lesson-plans/partials/review-form.blade.php`
- `tests/Feature/LessonPlanReviewTest.php`

## Explicitly do not start next

Auto-IIP, attendance/homework follow-up beyond current verify, observations engine, baseline exams, SIP timetable, AE-01.5, DMS, CE/DI rewrite.

## How to reopen this chat

Cursor keeps this thread in chat history. Search for “HOD queue” or “HodScope”. Ask: “Continue from chambers/WISCA-Session-Handoff.md”.
