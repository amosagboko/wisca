# WISCA PEMS — Functional & Business Logic Gap Analysis

**Status:** Analysis only. No application, database, route, or UI changes were made.

**Authority:** Harmonised Framework workbook  
`C:\Users\NCC\Desktop\projects\wisca\chambers\WISCA_v2.xlsx`  
(title: *WISDOM CHRISTIAN ACADEMY (WISCA) - INTEGRATED OPERATIONAL FRAMEWORK* / Harmonised Group 2 & Group 3)

**Compared against:** live Laravel app (controllers, models, migrations, calculation services, views) and the navigation encoding in `app/Support/WiscaOperationalCatalog.php`.

**Question this report answers:** Can WISCA PEMS execute each Harmonised Framework process end to end — not merely show a matching menu?

---

## 1. Framework verification

### 1.1 Baseline count

| Source | Pillars | Activities | Measurable sub-activities |
|--------|---------|------------|---------------------------|
| `WISCA_v2.xlsx` (data rows) | 3 | **23** | **72** (33 + 21 + 18) |
| `WiscaOperationalCatalog.php` | 3 | 23 | 72 (same per-activity counts) |

An earlier working figure of **67 was incorrect**. This analysis covers **all 72**.

Workbook sheets match the three pillars: Academic Excellence, Christcentric Education, Digital Innovation.

Activity names and sub-activity labels in the catalog match the workbook. The catalog is a **navigation encoding**, not the source of truth.

### 1.2 Column-level differences (xlsx wins)

| Topic | Harmonised Framework | Catalog / app | How this analysis treats it |
|-------|----------------------|---------------|-----------------------------|
| **KPI / Measure** | Full measurement sentence on every row | Catalog stores target, frequency, evidence — **not** the KPI sentence | Workbook KPI text is quoted below. App KPI services are a **different** formula layer (one executive KPI per activity, not 72 sub-measures). |
| AE-01.5 target | `0.95` | `95%` | **Resolved (owner):** treat as **95%**. Same measure; spreadsheet stored a decimal. |
| AE-04.2 Responsibility | **Blank** in the sheet | Catalog assigned `teacher` | **Resolved (owner):** Responsibility is **Teacher**. |
| Role strings | Teachers, Class Teacher, HODs / HODS, HoS/AHS/HODs, AHS/HOS, ICT Coordinator, Admin Manager, Chaplain | Spatie keys (`teacher`, `head_of_department`, …) | Mapped in analysis; extra catalog `extra_roles` (Subject Lead, Admin Officer, STEM Coordinator, etc.) are **not** in the workbook Responsibility column. |
| Frequency casing | e.g. `routine` | `Routine` | Immaterial. |
| Evidence | Workbook wording | Slightly normalised in catalog | Workbook wording used. |

### 1.3 Structural mismatch (must not be hidden)

The workbook defines **72 measurable sub-activities**, each with its own KPI/Measure.

The application defines **21 executive KPIs** (AE-01–AE-08, CE-01–CE-07, DI-01–DI-06) with **one numerator/denominator per activity**. AE-09 and AE-10 have no KPI row. Sub-activity tabs share one register and one executive KPI.

So even where capture exists, the **sub-activity KPI in the framework is usually not what the app calculates**.

---

## 2. Summary

### 2.1 A–D ratings (process completeness, not “page exists”)

| Rating | Meaning | Count |
|--------|---------|------:|
| **A** | Process can be executed end to end as the framework describes | **0** |
| **B** | Partial: some capture or a related workflow exists; stages, evidence, master data, or the stated KPI are incomplete | **37** |
| **C** | UI / placeholder only (coming-soon) | **6** |
| **D** | Framework process has no meaningful implementation | **29** |
| **Total** | | **72** |

| Pillar | A | B | C | D |
|--------|--:|--:|--:|--:|
| Academic Excellence (33) | 0 | 16 | 6 | 11 |
| Christcentric Education (21) | 0 | 14 | 0 | 7 |
| Digital Innovation (18) | 0 | 7 | 0 | 11 |

No sub-activity is **A** because none of the 72 processes can be run with the framework’s prerequisites, evidence, staged workflow, **and** stated KPI all present.

Closest to A: **AE-05** (lesson plan submit / approve / reject) and **CE-01** (scheduled chapel session + roll). Both still fail a strict reading (no SoW-controlled planning, no quality checklist / 24-hour SLA, no separate assembly vs class vs chapel processes, no file evidence).

### 2.2 Highest-impact gaps (blueprint priorities for discussion)

1. **No controlled Scheme of Work process** — tables exist (`schemes_of_work`, `topics`); no upload / approve / version UI. Blocks AE-01 planning and weakens AE-03/AE-05 alignment.
2. **One register per activity, many framework stages** — tabs are labels on a shared form, not process stages.
3. **Executive KPI ≠ sub-activity KPI** — 21 calculated activity KPIs vs 72 framework measures.
4. **AE-02 is a term marksheet**, not baseline → intervention → SIP → comparative evaluation.
5. **AE-09 / AE-10 are coming-soon** (6 subs, no data, no KPI).
6. **Homework and attendance are class aggregates**, not per-learner completion, punctuality, absentee lists, or make-up.
7. **Restorative discipline and bullying are single-record status fields**, not Incident → Investigation → Plan → Follow-up → Evidence.
8. **Digital Innovation is weekly/termly registers** (logins, project ticks, audit ticks, competency levels, subject flags, parent login counts). Training, inventory, helpdesk, e-portfolios, and LMS provisioning are not first-class processes.
9. **Evidence is usually text or optional one-file (lesson plans only).** Framework evidence (notebooks, photos, certificates, registers, repositories) is not capturable.
10. **Master data is uneven:** session/term/class/subject/learners/assignments are administered; SoW, SIP, spiritual syllabus-as-taught-log, STEM inventory, parent portal accounts, and helpdesk are not.
11. **AE-04.2 attendance monitoring** is a Teacher responsibility (owner confirmed; sheet cell was blank). The process is still missing.
12. **No notifications / SLA clocks** (Thursday plans, 24-hour lesson-plan turnaround, 24/48-hour safeguarding, 5-day ethics resolution, 10-day parent follow-up).

### 2.3 Cross-cutting patterns

- **Shared register vs staged workflow.** Almost every activity is one CRUD screen. Status enums (coverage, service, discipline, bullying) approximate stages without locking, artefacts, or role hand-offs per stage.
- **Seeded master data ≠ controlled master data.** Schemes, topics, and many lookup types are created in seeders or simple admin CRUDs without approval history.
- **Evidence-as-caption.** Catalog/tab cards print the Evidence column. Only lesson plans upload a file. Coverage uses a workbook *reference string*.
- **KPI layer is executive.** `*CalculationService` + `kpi_periodic_data` store one rate per activity. Sub-activity targets in the workbook are not computed.
- **Coming-soon is honest for numeracy and assimilation** and must not be treated as implemented.
- **Reuse first.** Many gaps can start from existing tables (schemes, lesson plans, at-risk plans, chapel types, guardians, observation rubric) rather than new modules.

---

## 3. Master data inventory

Items the framework implies must be **centrally configured and controlled** before staff can perform dependent work. A seeded row is not a controlled process.

| Master data | Who should control it (framework / current practice) | Exists in DB? | Controlled process? | Blocks |
|-------------|------------------------------------------------------|---------------|---------------------|--------|
| School, session, term, class, subject | Admin (current Admin screens) | Yes | Yes (admin CRUD) | Almost all capture |
| Teacher assignments | Admin | Yes `teacher_assignments` | Yes | Scoped teacher work |
| Learners (enrolment) | Admin | Yes | Yes | Exams, chapel roll, character, at-risk, STEM, reading |
| **Approved scheme of work + weekly topics / LOs** | Unspecified in sheet; implied curriculum authority | Yes `schemes_of_work`, `topics` | **No UI** (seeder only). Status enum unused in portal | AE-01, AE-03 alignment, AE-05 topic link |
| Lesson-plan quality checklist / standard format | HOD review | No | No | AE-05.2 |
| Observation rubric / schedule of planned observations | HoS/AHS/HOD | Rubric hardcoded `ObservationRubric`; no planned-observation calendar | Partial | AE-06.1 planned % |
| At-risk criteria / diagnostic profile | HOD / Learning Support | Criteria helper; no diagnostic form entity | Partial | AE-07.1 |
| Exam / baseline assessment types | HoS/AHS/HOD | `assessment_key` fixed `term_exam` | No | AE-02.1, AE-02.4 |
| SIP / catch-up programme catalogue | HOD | No | No | AE-01.4, AE-02.3 |
| Homework assignment catalogue vs “given” | Teacher | No (aggregate log only) | No | AE-03 |
| Attendance benchmark (≥95%) + punctuality codes | Teacher (AE-04.2; owner confirmed) | No per-learner rows | No | AE-04.2–.3 |
| Chapel activity types + term timetable | Chaplain / Admin | Yes types + sessions | Admin CRUD, no approval | CE-01 |
| Character domains / rubric | Chaplain / Admin | Yes | Admin CRUD | CE-02 |
| Community service project calendar | Chaplain | Types only | No calendar | CE-03.1 |
| Discipline / bullying type catalogues | Admin | Yes | Admin CRUD | CE-04, CE-05 |
| Restorative framework / action-plan template | Chaplain | Text fields only | No | CE-04.2–.3 |
| Termly spiritual syllabus / weekly verses | Chaplain | `scripture_passages` | Admin CRUD; no “taught this week” log | CE-06.1 |
| Partnership charter / handbook | Chaplain | `partnership_charters` | Admin CRUD | CE-07.1 |
| Parent / guardian registry | Admin Manager / Parent Relations | `parents`, `learner_parent` | Admin CRUD | CE-07, DI-06 |
| **Parent portal user accounts** | Admin Manager | Guardians are **not** login users | No | DI-06.1 |
| LMS / portal accounts and login source | ICT Coordinator | Manual `lms_usage_logs` | No provisioning | DI-01.2 |
| Training catalogues (LMS, ICT, AI ethics, parent orientation) | ICT / Teacher | No | No | DI-01.1, DI-03.1, DI-04.1, DI-06.2 |
| STEM kit inventory + licences | HoS | No | No | DI-02.1 |
| STEM practical timetable | ICT | No | No | DI-02.2 |
| STEM project types | ICT / HOD | Yes | Admin CRUD | DI-02.3 |
| Ethics audit types | HOD | Yes | Admin CRUD | DI-03.2 |
| Digital competency areas | ICT | Yes | Admin CRUD | DI-04 |
| Approved digital assessment tools | ICT | Text `primary_tool` on subject row | No catalogue | DI-05.1 |
| E-portfolio platform / learner portfolios | ICT | No | No | DI-05.2–.3 |
| Helpdesk / support tickets | ICT / Admin Manager | No | No | DI-01.3, DI-06.2–.3 |
| Status thresholds / executive KPI config | Admin | Yes `kpis.config` | Admin KPI settings | Executive dashboard only |

---

## 4. How to read each sub-activity

Each entry uses:

- **KPI class:** directly calculable from existing data / calculable after missing work / requires clarification / currently impossible.
- **Problem type(s):** missing master data; missing data capture; missing workflow; missing approval/review; missing evidence/verification; missing KPI calculation; KPI methodology ambiguity; missing role/permission; missing dependency/integration; missing UI/navigation (or a combination).
- **Impact split:** (1) business gap (2) reusable capability (3) required enhancement (4) potential technical impact. A gap does **not** automatically mean a new table.
- **Options:** alternatives and implications. No option is selected.

---

## 5. Academic Excellence — full inventory

### AE-01 Curriculum Coverage Rate

**Module:** `coverage-logs.*` → `TopicCoverageLogController`. Related: `schemes_of_work`, `topics`, `lesson_plans`.  
**Executive KPI (app):** covered topics ÷ all topics on approved/active schemes (`CoverageCalculationService`).  
**Downstream:** AE-05 unlocks coverage (approved plan required). AE-01.4 should feed AE-02/AE-07 interventions.

#### AE-01.1 Plan curriculum delivery according to approved scheme of work

- **Role:** Teachers. **KPI:** % of termly schemes of work prepared with weekly broken-down learning objectives. **Target:** 100%. **Frequency:** Every Thursday. **Evidence:** Submission of lesson plan every Thursday.
- **Prerequisites:** Approved SoW for session/term/class/subject; weekly topics + LOs; teacher assignment; Thursday deadline.
- **Dependencies:** SoW approval (missing UI) → this plan → AE-05 submit → AE-01.2 delivery.
- **Current:** Teacher cannot upload or select a controlled SoW. Lesson-plan submit exists on **another** activity (AE-05). Coverage page is a verification register. SoW rows are seeded (`status` draft/approved/active/archived unused in UI).
- **Rating:** **B**. **Problem types:** missing master data + missing workflow + missing UI + KPI methodology mismatch + missing dependency.
- **KPI class:** Requires clarification (workbook % of *schemes prepared*; app does not measure schemes at all) / calculable after SoW+plan workflow exists.
- **Impact:** (1) Teachers cannot plan against an approved, versioned SoW. (2) Reuse `schemes_of_work`, `topics.learning_objectives`, `LessonPlan` + Thursday `Term::lessonPlanDueAt`. (3) SoW upload/approve/publish + bind weekly plan to topic. (4) **Extend existing tables + new UI + workflow/state + permissions.** Not necessarily a new table.
- **Options:** (A) Admin/HOD SoW upload + approve on existing table, teachers only see `approved`/`active`. (B) Treat AE-05 Thursday submit as this KPI and rename measurement (framework change). (C) Import topics from file without approval. Implications: A preserves control; B is faster but abandons SoW authority; C is unsafe for “approved” wording.

#### AE-01.2 Deliver scheduled curriculum content and learning objectives

- **Role:** Teachers. **KPI:** % of planned curriculum content taught within scheduled class time. **Target:** ≥95%. **Weekly.** Evidence: lesson plans, teacher logs, coverage checks.
- **Current:** Teacher submits `topic_coverage_logs` (workbook_reference, notes) after an approved lesson plan; topic → `in_progress`. No “scheduled class time” capture.
- **Rating:** **B**. **Problem types:** missing data capture (duration vs schedule) + missing evidence + KPI ambiguity.
- **KPI class:** Partial existing data (covered/planned topics) ≠ “taught within scheduled class time.” Requires clarification of time window.
- **Impact:** (1) Delivery is a log, not timed delivery vs timetable. (2) Reuse coverage logs + topic statuses. (3) Optional duration/timetable fields or keep topic-count proxy. (4) **Extend existing** or **config-only** if Board accepts topic-count proxy.
- **Options:** (A) Keep topic-count as proxy and document the methodology change. (B) Add planned vs actual minutes. (C) Link to a timetable master. Implications: A is cheapest; C needs timetable master data.

#### AE-01.3 Check curriculum coverage and track completed topics

- **Role:** HODs. **KPI:** % comparison of content taught vs approved SoW. **Target:** 100%. **Weekly / Mid-Term.** Evidence: learners’ notebooks and tracking register.
- **Current:** HOD/HoS verify or reject coverage logs; verified → topic `covered`. No notebook evidence.
- **Rating:** **B**. **Problem types:** missing evidence + KPI mismatch (app = executive AE-01, close to this row).
- **KPI class:** Directly calculable *as topic covered ÷ topics* after verify. Not calculable as “notebook-verified understanding.”
- **Impact:** (1) Check exists; notebook verification does not. (2) Reuse verify/reject. (3) Optional evidence upload or mid-term review record. (4) **Extend existing** (file_path on log) or leave as register.
- **Options:** (A) File upload on verify. (B) Separate mid-term HOD review object. (C) Keep verify as the check. Implications: C already supports a coverage register KPI.

#### AE-01.4 Identify curriculum gaps and implement catch-up plans

- **Role:** HODs. **KPI:** % of identified untaught topics addressed through catch-up / remedial. **Target:** 100%. **Monthly.** Evidence: SIP implementation.
- **Current:** Skipped/uncovered topics can be listed in dashboards; **no SIP / catch-up entity.**
- **Rating:** **D**. **Problem types:** missing workflow + missing capture + missing master data + missing KPI.
- **KPI class:** Currently impossible.
- **Impact:** (1) Gaps are not closed in-system. (2) Reuse `topics.status` (`skipped`/`planned`) as the gap list; AE-07 plans as a weak analogue. (3) Catch-up plan linked to topics + evidence. (4) **New relationship** (topic ↔ intervention) or **extend** intervention_plans — not automatically a new module.
- **Options:** (A) HOD creates catch-up from uncovered topics. (B) Reuse AE-07 IIP for curriculum gaps (blurs academic vs learner risk). (C) Offline SIP, report-only. Implications: A matches framework; B overloads at-risk.

#### AE-01.5 Review, verify, and update coverage vs understanding

- **Role:** HODs. **KPI:** % verification of learner understanding of covered curriculum. **Target:** 95% (`0.95` in the sheet; owner confirmed as 95%). **Random.** Evidence: evaluation tests.
- **Current:** Nothing. Coverage verify is about *taught*, not *understood*.
- **Rating:** **D**. **Problem types:** missing capture + missing workflow + missing KPI + missing dependency (tests).
- **KPI class:** Currently impossible. Methodology needs a defined test instrument.
- **Impact:** (1) No coverage-vs-understanding process. (2) Reuse exam_results or a light quiz log. (3) Link tests to topics. (4) **New relationship** or **extend exam_results** (`assessment_key`).
- **Options:** (A) Topic-linked spot tests. (B) Reuse term exams (wrong frequency). (C) Defer to AE-10. Implications: A is the only fit for “random evaluation tests.”

---

### AE-02 School-wide Examination Pass Rate

**Module:** `exam-results.*` marksheet. **Executive KPI:** scores ≥ pass_mark ÷ enrolled roll on assignments (`ExamCalculationService`, default pass 50%).  
**Downstream:** at-risk queue (`at-risk.from-exam`) is available but not an AE-02 workflow.

#### AE-02.1 Conduct baseline assessment for performance gaps

- **Role:** HoS/AHS/HODs. **KPI:** % of subjects/classes with gaps identified and mapped. **Target:** 100%. **1st week of term.** Evidence: previous-session analysis + baseline marksheets.
- **Current:** Only `assessment_key = term_exam`. No baseline, no gap map, no previous-session analysis object.
- **Rating:** **D**. **Problem types:** missing capture + missing workflow + missing master data (assessment types).
- **KPI class:** Currently impossible.
- **Impact:** (1) Week-1 baseline process missing. (2) Reuse marksheet UI + `exam_results` if `assessment_key` is extended. (3) Baseline key + gap flag per class/subject. (4) **Extend existing** — not a new table required.
- **Options:** (A) Add `baseline` assessment_key and week-1 window. (B) Import previous term as baseline. (C) Spreadsheet upload only. Implications: A/B reuse marksheet; C stays offline.

#### AE-02.2 Develop and implement targeted academic intervention plans for low-performing learners

- **Role:** Teachers. **KPI:** % of identified low performers enrolled in support. **Target:** ≥95%. **Termly.** Evidence: intervention registers, IIPs, lesson schedules.
- **Current:** AE-07 IIP exists (HOD/Learning Support manage plans; teachers can identify). Not bound to exam gaps or teacher-owned AE-02 workflow. No lesson schedule.
- **Rating:** **B**. **Problem types:** missing dependency/integration + missing role/permission (teacher as plan owner) + missing KPI (enrolment % from exam list).
- **KPI class:** Calculable after exam→at-risk link and an “enrolled in support” definition.
- **Impact:** (1) Intervention exists in another activity, not as AE-02. (2) Reuse `at_risk_learners`, `intervention_plans`, `from-exam`. (3) Auto-queue below-pass; teacher enrolment; exam-linked KPI. (4) **Extend existing + workflow**, not a new table.
- **Options:** (A) Wire AE-02 tab to at-risk-from-exam. (B) Separate AE-02 intervention register. (C) Leave AE-07 as the only IIP. Implications: A reuses most; B duplicates.

#### AE-02.3 Conduct targeted remedial lessons and revision sessions

- **Role:** Teachers. **KPI:** % of scheduled remedial/revision lessons delivered. **Target:** ≥90%. Evidence: SIP timetable.
- **Current:** No SIP timetable, no remedial session log.
- **Rating:** **D**. **Problem types:** missing master data + missing capture + missing workflow.
- **KPI class:** Currently impossible.
- **Impact:** (1) Remedial delivery not executed in-app. (2) Weak reuse: homework logs or coverage logs as proxies (poor fit). (3) Session register against a SIP timetable. (4) **New structures** for timetable *or* extend coverage logs with `type=remedial`.
- **Options:** (A) Remedial session log. (B) Tag coverage logs as SIP. (C) Offline timetable. Implications: B avoids a new table.

#### AE-02.4 Evaluate pass rate and progress of learners receiving academic intervention

- **Role:** Teachers. **KPI:** % of intervention learners improving baseline → post-assessment. **Target:** ≥80% pass (≥75% major subjects). **Half-termly / termly.**
- **Current:** Executive AE-02 is **all enrolled learners’ term exam**, not the intervention cohort, not baseline-delta, not major-subject split.
- **Rating:** **D**. **Problem types:** missing KPI calculation + missing dependency + KPI methodology ambiguity (pass vs improvement; major subjects).
- **KPI class:** Requires clarification (two targets in one cell) / currently impossible on current data.
- **Impact:** (1) Stated evaluation cannot run. (2) Reuse exam_results + intervention membership if baseline exists. (3) Cohort filter + two assessments. (4) **New calculation service** + **extend** assessment keys.
- **Options:** (A) Define one official formula with Board. (B) Two KPIs (pass vs growth). (C) Keep school-wide pass only and treat this row as reporting commentary. Implications: C is current product behaviour.

---

### AE-03 Homework / Class Work Completion Rate

**Module:** `homework_logs` — `title`, dates, `given_count`, `completed_on_time_count`.  
**Executive KPI:** Σ completed_on_time ÷ Σ given in current week.

#### AE-03.1 Assign classwork and homework aligned with approved curriculum

- **Role:** Teachers. **KPI:** % of planned assignments given. **Target:** ≥95%. **3× a week.** Evidence: home and classwork marks.
- **Current:** Teacher may log a title and counts. No “planned” denominator, no 3×/week rule, no SoW alignment, no marks files.
- **Rating:** **B**. **Problem types:** missing master data (planned set) + missing capture + missing KPI.
- **KPI class:** Requires clarification (what is “planned”?) / not calculable as specified.
- **Impact:** (1) Assignment-vs-plan process missing. (2) Reuse homework_logs. (3) Planned vs given fields or weekly quota. (4) **Extend existing** or **config** (expect 3 logs/week).
- **Options:** (A) Weekly quota check against 3. (B) Planned assignment list from SoW. (C) Keep free-text logs. Implications: A is small; B needs SoW.

#### AE-03.2 Monitor, mark, and record learner completion

- **Role:** Teachers. **KPI:** % completed, marked, recorded on time. **Target:** ≥90%. **Weekly.**
- **Current:** Aggregate counts only — not per learner, not marks.
- **Rating:** **B**. **Problem types:** missing data capture + missing evidence.
- **KPI class:** Directly calculable **as entered aggregates** (current executive KPI). Not calculable as per-learner marked work.
- **Impact:** (1) Cannot see who did the work. (2) Reuse log + learners. (3) Per-learner completion *or* accept class aggregates. (4) **New relationship** (log↔learners) vs **no schema change**.
- **Options:** (A) Keep aggregates (document methodology). (B) Per-learner ticks. Implications: A matches current KPI; B is a real register.

#### AE-03.3 Follow up on incomplete work and provide make-up

- **Role:** Teachers. **KPI:** % of incomplete followed up and completed. **Target:** 100%.
- **Current:** No make-up fields.
- **Rating:** **D**. **Problem types:** missing workflow + missing capture + missing KPI.
- **KPI class:** Currently impossible.
- **Impact:** (1) Follow-up process missing. (2) Reuse incomplete = given − completed if per-learner exists. (3) Make-up status/date. (4) **Extend** homework_logs or child rows.
- **Options:** (A) Follow-up fields on the same log. (B) Child make-up records. (C) Notes-only. Implications: A avoids a new table.

---

### AE-04 Learner Attendance Rate

**Module:** one row per class per date: `enrolled_count`, `present_count`.  
**Executive KPI:** Σ present ÷ Σ enrolled in current week.  
**Note:** AE-04.2 Responsibility was blank in the workbook; **owner confirmed it is Teacher.**

#### AE-04.1 Record daily attendance and punctuality at assembly/class

- **Role:** Teachers. **KPI:** % of school days with complete accurate attendance **and punctuality**. **Target:** 100%. **Daily.**
- **Current:** Class totals. No punctuality. No per-learner present/late. Admin officer can also record (`canRecordAttendance`).
- **Rating:** **B**. **Problem types:** missing data capture + KPI ambiguity (day-complete vs learner rate).
- **KPI class:** Directly calculable as “days with a row” or as present/enrolled — **not** both attendance and punctuality. Requires clarification.
- **Impact:** (1) Punctuality process missing. (2) Reuse `attendance_logs` + learners. (3) Per-learner status or late_count column. (4) **Extend existing** (late_count) vs **new per-learner rows**.
- **Options:** (A) Add `late_count` to the daily row. (B) Per-learner register. (C) Keep headcount only. Implications: A is small; B matches “punctuality sheets.”

#### AE-04.2 Monitor patterns and identify persistent absentees or frequent lateness

- **Role:** Teachers (owner confirmed; sheet cell was blank). **KPI:** % of learners below ≥95% benchmark identified. **Weekly.** Evidence: monitoring reports and absentee list.
- **Current:** No per-learner history, no benchmark report, no named absentee list. Officer dashboard only shows missing class registers. Catalog already scoped the tab to Teacher.
- **Rating:** **D**. **Problem types:** missing capture + missing KPI + missing UI. Role is settled (Teacher).
- **KPI class:** Currently impossible on aggregates.
- **Impact:** (1) Pattern monitoring cannot run. (2) Reuse daily logs **if** they become per-learner. (3) Weekly exception list for the class teacher. (4) **New calculation** after capture exists; **config** for 95% benchmark. No role-model change required.
- **Options:** (A) Build exception report after AE-04.1 granularity is decided. (B) Export-only. Implications: owner is Teacher; remaining work is capture and calculation.

#### AE-04.3 Follow up unexplained absences and communicate to Management

- **Role:** Teachers. **KPI:** % follow-up on all absentees. **Target:** 100%. **Daily.** Evidence: register vs reports to management.
- **Current:** No follow-up record, no management notification.
- **Rating:** **D**. **Problem types:** missing workflow + missing capture + missing KPI + missing dependency (notifications).
- **KPI class:** Currently impossible.
- **Impact:** (1) Escalation process missing. (2) No existing absence-case table. (3) Follow-up case or daily checklist. (4) **New lightweight table** or **extend** a future per-learner attendance row with `followed_up_at`.
- **Options:** (A) Tick follow-up on each absentee. (B) Daily summary email/report to HoS (notification — not present today). (C) Paper process. Implications: B needs a notification channel.

---

### AE-05 Lesson Plan / Note Submission & Approval

**Module:** `lesson_plans` statuses `draft|submitted|approved|rejected`; optional file; HOD/HoS approve/reject.  
**Executive KPI:** approved-on-time plans ÷ topics in current scheme week.

#### AE-05.1 Prepare and submit weekly plans/notes to standard format and timeline

- **Role:** Teachers. **KPI:** % submitted on or before weekly deadline. **Target:** ≥95%. **Weekly.**
- **Current:** Teacher submits against a topic; `on_time` vs Monday deadline. Optional PDF/DOC. No enforced standard-format checklist. Depends on seeded topics (SoW).
- **Rating:** **B**. **Problem types:** missing master data (format) + missing dependency (controlled SoW).
- **KPI class:** Directly calculable for on-time submit (executive AE-05 is close, but uses *approved on-time*, not *submitted on-time*).
- **Impact:** (1) Submit works; “standard format” is not enforced. (2) Reuse lesson_plans + file_path + due_at. (3) Optional template/checklist. (4) **Config/UI** or **extend** with checklist JSON.
- **Options:** (A) Keep file + deadline as the process. (B) Structured template fields. (C) Require SoW approval before submit. Implications: C is correct vs AE-01.1 but blocks teachers if SoW UI is absent.

#### AE-05.2 Review submitted plans for curriculum alignment, quality, and engagement

- **Role:** HODs. **KPI:** % reviewed against quality standards checklist. **Target:** 100%.
- **Current:** HOD opens the plan and approves or rejects. No checklist.
- **Rating:** **B**. **Problem types:** missing data capture (checklist) + missing evidence.
- **KPI class:** Calculable as “touched by HOD” after counting approve+reject. Not “reviewed against checklist.”
- **Impact:** (1) Review action exists; quality instrument does not. (2) Reuse approve/reject. (3) Checklist before decision. (4) **Extend** lesson_plans or **config** rubric.
- **Options:** (A) Add checklist scores. (B) Treat any decision as reviewed. Implications: B is current behaviour.

#### AE-05.3 Approve compliant plans and return non-compliant with feedback

- **Role:** HODs. **KPI:** % approved or returned with feedback within 24 hours. **Target:** ≥95% approved.
- **Current:** Approve + comment / reject + reason. No 24-hour clock. Rejection allows resubmit. Unlocks AE-01 coverage.
- **Rating:** **B**. **Problem types:** missing KPI calculation (SLA) + KPI methodology (approved % vs SLA %).
- **KPI class:** Decision rate is calculable. 24-hour SLA is calculable **after** using `submitted_at` vs decision time (fields exist — **service not written**). Dual target needs clarification.
- **Impact:** (1) Core workflow exists. (2) Reuse statuses and timestamps. (3) SLA report. (4) **New calculation only** — no new table.
- **Options:** (A) Add SLA to existing KPI service. (B) Two metrics. (C) Ignore 24h. Implications: A is the smallest enhancement on the strongest existing workflow.

---

### AE-06 Effective or Better Lesson Observations

**Module:** `observations` + 12-standard rubric (1–4). Status `scheduled|completed|follow_up_required`.  
**Executive KPI:** effective (avg ≥ config, default 3) ÷ observations in term.

#### AE-06.1 Conduct formal observations using standardized checklist

- **Role:** HoS/AHS/HODs. **KPI:** % of **planned** observations completed. **Target:** ≥95%. **Monthly.**
- **Current:** Observer can complete a rubric form. No planned-observation schedule / denominator.
- **Rating:** **B**. **Problem types:** missing master data (plan) + missing KPI (planned vs done).
- **KPI class:** Requires clarification of “planned.” Count of completed forms is available; % of plan is not.
- **Impact:** (1) Observation can be recorded, not planned. (2) Reuse Observation + rubric. (3) Schedule entity or monthly quota. (4) **Config** (N per teacher/month) vs **new schedule table**.
- **Options:** (A) Monthly quota without a plan table. (B) Schedule then complete. Implications: A avoids a new table.

#### AE-06.2 Assess methods, engagement, and provide immediate feedback

- **Role:** HODs. **KPI:** % of observed lessons with documented feedback and coaching. **Target:** 100%.
- **Current:** Text `strengths`, `areas_for_improvement`, `action_plan`. No teacher sign-off.
- **Rating:** **B**. **Problem types:** missing approval/review (sign-off) + missing evidence.
- **KPI class:** Calculable if “non-empty feedback fields” is accepted. Sign-off is not.
- **Impact:** (1) Feedback text exists; coaching acknowledgement does not. (2) Reuse text fields. (3) Teacher acknowledge. (4) **Extend** observations (`acknowledged_at`).
- **Options:** (A) Require text fields. (B) Teacher sign-off. (C) Separate debrief form. Implications: A/B extend one table.

#### AE-06.3 Monitor implementation of feedback and track teaching improvement

- **Role:** HODs. **KPI:** % of observed teachers rated Effective or Better **in a subsequent observation**. **Target:** ≥90%.
- **Current:** `follow_up_required` is a label. No linked follow-up visit. Executive KPI is **same-visit** effective rate, not subsequent.
- **Rating:** **D**. **Problem types:** missing workflow + missing KPI calculation + missing dependency.
- **KPI class:** Currently impossible (no pair of observations).
- **Impact:** (1) Improvement cycle missing. (2) Reuse Observation rows. (3) `follows_observation_id` + compare scores. (4) **New relationship** on existing table.
- **Options:** (A) Link follow-up observation. (B) Keep single-visit effective rate and change the framework metric. Implications: A matches the sheet; B matches today’s KPI.

---

### AE-07 At-Risk Learners with Active Intervention Plan

**Module:** identify + IIP (`draft|active|completed|discontinued`).  
**Executive KPI:** active identifications with an active plan ÷ identified (`AtRiskCalculationService`).

#### AE-07.1 Identify and document struggling/at-risk learners

- **Role:** HODs. **KPI:** % of identified at-risk documented in central register. **Target:** 100%.
- **Current:** Register exists; exam queue helper; teachers can also identify. No diagnostic profile form. KPI tautology if “identified” = “on register.”
- **Rating:** **B**. **Problem types:** KPI methodology ambiguity + missing evidence (diagnostic forms) + role (sheet says HOD; app also allows teachers).
- **KPI class:** Requires clarification (denominator: who *should* have been identified?).
- **Impact:** (1) Register works; “completeness of identification” is undefined. (2) Reuse `at_risk_learners`. (3) Criteria/diagnostic completeness rules. (4) **Extend** or **config**.
- **Options:** (A) Keep register as the process. (B) Require diagnostic fields. (C) Auto-identify from exams/attendance. Implications: C needs AE-02/AE-04 data quality.

#### AE-07.2 Develop and implement individualized intervention plans

- **Role:** HODs. **KPI:** % of identified with active customized plans. **Target:** 100%. Evidence: IIP and remedial attendance logs.
- **Current:** Plan text + tier + dates. No remedial attendance log. KPI **is** this row (with-plan ÷ identified).
- **Rating:** **B**. **Problem types:** missing evidence + missing capture (remedial attendance).
- **KPI class:** Directly calculable as current executive AE-07.
- **Impact:** (1) Plan coverage works; implementation evidence does not. (2) Reuse intervention_plans. (3) Optional remedial attendance. (4) **Extend** or reuse attendance/homework.
- **Options:** (A) Accept IIP as sufficient. (B) Add remedial sessions. Implications: A matches today’s KPI.

#### AE-07.3 Monitor progress and evaluate academic outcome improvements

- **Role:** HoS/AHS/HODs. **KPI:** % demonstrating measurable academic improvement on re-assessment. **Target:** ≥80%.
- **Current:** Resolve identification only. No post-intervention scores.
- **Rating:** **D**. **Problem types:** missing capture + missing KPI + missing dependency (re-assessment).
- **KPI class:** Currently impossible.
- **Impact:** (1) Outcome evaluation missing. (2) Reuse exam_results / reading levels. (3) Before/after scores on the case. (4) **Extend** at_risk_learners or **integrate** exams.
- **Options:** (A) Attach re-assessment scores. (B) Reuse term exam delta. (C) Manual resolve without metric. Implications: C is current.

---

### AE-08 Literacy Progress (≥1 Year Growth)

**Module:** `reading_assessments` — one row per learner per session: baseline + follow-up levels.  
**Executive KPI:** (followup − baseline) ≥ `growth_min` (default 1.0) ÷ assessed.

#### AE-08.1 Conduct baseline literacy assessments

- **Role:** HoS/AHS/HODs. **KPI:** % of learners evaluated for baseline. **Target:** 100%. **Beginning of term.**
- **Current:** Baseline fields exist; checkpoints are session-start/end, not termly. App allows literacy coordinator and teachers. No fluency/comprehension components.
- **Rating:** **B**. **Problem types:** missing capture (instrument detail) + KPI (assessed ÷ enrolled not stored as the executive KPI) + frequency mismatch (term vs session).
- **KPI class:** Calculable after using enrolled denominator (data exists). Executive KPI is growth, not coverage of baseline.
- **Impact:** (1) Baseline can be typed; instrument and coverage KPI are weak. (2) Reuse reading_assessments + learners. (3) Coverage report + richer scores. (4) **New calculation** and/or **extend** columns.
- **Options:** (A) Coverage KPI from existing rows. (B) Multi-score instrument. Implications: A is configuration/reporting.

#### AE-08.2 Deliver targeted literacy/writing instruction with periodic monitoring

- **Role:** HODs. **KPI:** % meeting monthly reading/writing benchmarks. **Target:** ≥90%.
- **Current:** No reading logs, portfolios, or monthly checks.
- **Rating:** **D**. **Problem types:** missing workflow + missing capture + missing KPI.
- **KPI class:** Currently impossible.
- **Impact:** (1) Instruction monitoring missing. (2) Little to reuse (not homework). (3) Monthly checkpoint or logs. (4) **Extend** reading_assessments with monthly scores **or new log table**.
- **Options:** (A) Monthly extra fields. (B) New reading-log module. (C) Stay with two-point session model. Implications: C is current product.

#### AE-08.3 Specialized support for struggling readers and termly growth

- **Role:** Teachers. **KPI:** % achieving ≥1 year growth. **Target:** ≥90%.
- **Current:** Follow-up level on the same form; executive KPI matches **this row’s idea**. No specialized-support programme, no standardized instrument metadata.
- **Rating:** **B**. **Problem types:** missing workflow (support programme) + KPI methodology (what is “1 year” on a 1–n level scale?).
- **KPI class:** Directly calculable **as configured** (`growth_min`). Methodology of “≥1 year” vs level points needs Board confirmation.
- **Impact:** (1) Growth number can be produced; support process cannot. (2) Reuse reading_assessments + AE-07 for struggling readers. (3) Link support + clarify scale. (4) **Config** + optional **integration**.
- **Options:** (A) Keep two-point growth KPI. (B) Define a standardized test. Implications: A is live today.

---

### AE-09 Numeracy Progress

**Module:** `ComingSoonActivityController` + `activities/coming-soon`. **No tables. No KPI row.**

#### AE-09.1 / AE-09.2 / AE-09.3

- **Roles:** Teachers (baseline); HODs (monthly growth; termly vs baseline).
- **KPIs:** % receiving baseline; % monthly growth; % ≥1 year growth. Targets 100% / ≥85% / ≥85%.
- **Current:** Tabs and intro text only.
- **Rating:** **C** × 3. **Problem types:** missing UI (beyond placeholder) + missing capture + missing workflow + missing KPI + missing master data (instrument).
- **KPI class:** Currently impossible.
- **Impact:** (1) Entire numeracy process missing. (2) **Reuse pattern** from AE-08 reading_assessments (clone), not a blank design. (3) Baseline / monthly / follow-up capture + service. (4) **New tables modelled on reading** + new service + KPI seed + UI. Not “new invention,” but new structures.
- **Options:** (A) Clone AE-08 for numeracy. (B) Shared “progress assessment” module with a domain flag. (C) Leave coming-soon. Implications: A/B are implementation choices for later; C is current.

---

### AE-10 Learner Assimilation Rate

**Module:** coming-soon. **No tables. No KPI row.**

#### AE-10.1 / AE-10.2 / AE-10.3

- **Roles:** Teachers (formative checks; termly strategy review); HODs (gap identification → reinforcement).
- **KPIs:** % demonstrating understanding in lessons; % with gaps enrolled in reinforcement; % of gaps resolved. Targets ≥90% / ≥95% / ≥90%.
- **Current:** Placeholder.
- **Rating:** **C** × 3. **Problem types:** missing capture + workflow + KPI + methodology (how is “understanding” scored daily?).
- **KPI class:** Requires clarification (instrument) / currently impossible.
- **Impact:** (1) Assimilation process missing. (2) Weak reuse: homework aggregates, observations, AE-01.5 tests. (3) Formative instrument + gap register. (4) Likely **new structures** after methodology is agreed — do not invent a formula now.
- **Options:** (A) Weekly class % understanding field (coarse). (B) Per-learner formative scores. (C) Defer until instrument is defined. Implications: C is honest; A would be a proxy.

---

## 6. Christcentric Education — full inventory

### CE-01 Daily Devotion & Chapel Participation

**Module:** admin types + sessions; operational roll.  
**Executive KPI:** participating (non-absent ∩ counting_levels) ÷ (enrolled × held sessions).

#### CE-01.1 General morning assembly devotions 3–5 times weekly

- **Role:** Chaplain. **KPI:** % of scheduled assembly devotions conducted. **Target:** 100%.
- **Current:** Any session type can be scheduled and marked held via roll. No assembly-specific schedule compliance (3–5/week). `school_wide` flag unused in roll.
- **Rating:** **B**. **Problem types:** missing master data (assembly series) + missing KPI (scheduled vs held for that type).
- **KPI class:** Calculable after filtering activity type + planned vs held. Not the current blended participation KPI.
- **Impact:** (1) Assembly cadence not enforced. (2) Reuse chapel_activity_types + chapel_sessions. (3) Type filter + weekly planned count. (4) **Config/types + calculation** — no new table required.
- **Options:** (A) Dedicated “assembly” type + weekly quota. (B) Keep generic sessions. Implications: A matches the sheet.

#### CE-01.2 Age-appropriate classroom devotions, scripture, memory verse

- **Role:** Class Teacher. **KPI:** % of learners actively participating in class devotions. **Target:** ≥95%. **Twice weekly.**
- **Current:** Same roll UI for all types; participation_level exists. No class-devotion series, no memory-verse sheet (that lives conceptually in CE-06).
- **Rating:** **B**. **Problem types:** missing workflow (class vs assembly) + missing evidence.
- **KPI class:** Calculable if class-devotion sessions are distinguished. Today’s KPI mixes all held sessions.
- **Impact:** (1) Class devotion is not a separate process. (2) Reuse roll + participation_level. (3) Type + teacher-led sessions. (4) **Config + UI filter.**
- **Options:** (A) Class-devotion type, teacher records own class. (B) Continue school-wide roll only. Implications: A needs class-scoped sessions (`school_wide` already on type).

#### CE-01.3 Weekly chapel with active student participation

- **Role:** Chaplain. **KPI:** 100% attendance (≥90% active participation). **Weekly.** Evidence includes photos/video.
- **Current:** Roll can record present/active. Dual target in one cell. No media evidence.
- **Rating:** **B**. **Problem types:** missing evidence + KPI methodology (two numbers) + missing capture (photos).
- **KPI class:** Attendance and active-participation rates are calculable from roll **if** chapel type is filtered. Dual target needs clarification.
- **Impact:** (1) Weekly chapel can be recorded; evidence/media and dual KPI are incomplete. (2) Reuse sessions/roll. (3) Type + optional attachments + two metrics. (4) **Calculation + optional file** on session.
- **Options:** (A) Filter KPI to chapel type. (B) Add media upload. (C) Keep blended CE-01. Implications: A reuses data.

---

### CE-02 Christian Character Rating (Secure +)

**Module:** domain marksheet, levels 1–4, pass default 3.  
**Executive KPI:** learners passing **all** active domains ÷ enrolled.

#### CE-02.1 Deliver planned lessons and activities on Christian core values

- **Role:** Teachers. **KPI:** number of value-based modules/activities. **Target:** 2 activities per month (100% coverage).
- **Current:** No character-lesson or activity log.
- **Rating:** **D**. **Problem types:** missing capture + missing workflow + missing KPI.
- **KPI class:** Currently impossible.
- **Impact:** (1) Values delivery not logged. (2) Weak reuse: lesson_plans or a simple activity log. (3) Monthly activity records. (4) **Extend** lesson_plans (`character` flag) **or small new log**.
- **Options:** (A) Character activity log. (B) Tag lesson plans. (C) Ignore delivery; keep ratings only. Implications: C is current.

#### CE-02.2 Monitor and record daily application of Christian conduct

- **Role:** Teachers. **KPI:** % consistently demonstrating Christ-like conduct. **Target:** ≥90%. **Ongoing / monthly.**
- **Current:** Termly level per domain + notes. Not daily.
- **Rating:** **B**. **Problem types:** missing capture (daily) + frequency mismatch.
- **KPI class:** Directly calculable as term Secure+ (executive CE-02) — **not** daily consistency.
- **Impact:** (1) Daily conduct process missing. (2) Reuse character_ratings. (3) Daily/weekly ticks or keep termly. (4) **New daily table** vs **no change** if termly is accepted.
- **Options:** (A) Keep termly ratings as the measure. (B) Conduct log. Implications: A matches the live KPI, not the “daily” wording.

#### CE-02.3 Monthly secret/peer reviews and recognition

- **Role:** Chaplain. **KPI:** % evaluated and recognized; ≥80% positive; min 1 recognition/term.
- **Current:** No peer/secret review, no certificates/awards.
- **Rating:** **D**. **Problem types:** missing workflow + missing capture + missing evidence + KPI methodology (secret/peer vs teacher rating).
- **KPI class:** Currently impossible / requires clarification of “secret/peer.”
- **Impact:** (1) Recognition process missing. (2) Reuse ratings as input. (3) Review event + award record. (4) **New small records** or **extend** ratings with `recognized`.
- **Options:** (A) Chaplain recognition flag. (B) Full peer-review workflow. (C) Offline certificates. Implications: A is the smallest.

---

### CE-03 Community Service Hours Per Learner

**Module:** event logs `draft|submitted|verified|rejected`; **aggregate** `verified_hours` + `participant_count`.  
**Executive KPI:** verified hours ÷ enrolled (seed target **10 hours**, workbook target **≥2 hours/learner/term**).

#### CE-03.1 Plan, schedule, and organize outreach projects

- **Role:** Chaplain. **KPI:** number of approved projects aligned with calendar. **Target:** 100% aligned. Evidence: master schedule and guidelines.
- **Current:** Activity types CRUD; no project calendar, no approval of a project before the event log.
- **Rating:** **D**. **Problem types:** missing master data + missing approval + missing workflow.
- **KPI class:** Currently impossible (no planned calendar).
- **Impact:** (1) Planning process missing. (2) Reuse service_activity_types. (3) Scheduled project + approve. (4) **Extend types** with dates **or new project table**.
- **Options:** (A) Project calendar. (B) Treat types as the plan. Implications: B is too thin for “approved projects.”

#### CE-03.2 Track and log hours per learner

- **Role:** HODS. **KPI:** verified hours **per learner**. **Target:** ≥2 hours/learner/term.
- **Current:** Event-level hours, not individual. Verify/reject exists (admin/leadership/SLC — not HOD as verifier).
- **Rating:** **B**. **Problem types:** missing data capture (per learner) + role mismatch + KPI mismatch (app uses 10h school-wide average).
- **KPI class:** Current average is calculable. Per-learner ≥2h is **not**.
- **Impact:** (1) Individual hour ledger missing. (2) Reuse logs + verify + learners. (3) Participant list with hours. (4) **New relationship** log↔learners — existing table is insufficient alone.
- **Options:** (A) Per-learner hours. (B) Keep event aggregates and change the target story. Implications: seed target 10 vs sheet 2 must be reconciled.

#### CE-03.3 Acknowledge, reward, and report completion

- **Role:** HODS. **KPI:** % of participants meeting hour target recognized. **Target:** ≥90%.
- **Current:** No certificates, assembly recognition, or completion awards.
- **Rating:** **D**. **Problem types:** missing workflow + missing evidence + missing KPI.
- **KPI class:** Currently impossible until per-learner hours exist.
- **Impact:** (1) Recognition process missing. (2) Reuse verified hours if individualized. (3) Certificate/recognition flag. (4) **Extend** after 03.2.
- **Options:** (A) Recognition flag + report. (B) Offline certificates. Implications: A is enough for the KPI.

---

### CE-04 Resolved Restorative Discipline Cases

**Module:** single form: `status` open/in_review/resolved + `restorative_status` not_required/pending/in_progress/completed + two textareas.  
**Executive KPI:** restorative completed (status + timestamp) ÷ incidents **in current calendar month**.

#### CE-04.1 Promptly log and report cases using approved restorative framework

- **Role:** Teachers. **KPI:** % of reported cases accurately documented. **Target:** ≥95%.
- **Current:** Teacher can create an incident. “Accurately documented” and “approved framework” are not validated. Type.restorative_required is not auto-applied.
- **Rating:** **B**. **Problem types:** missing approval (framework completeness) + KPI ambiguity (reported vs accurately documented).
- **KPI class:** Count of logged cases is available. % “accurate” requires a definition.
- **Impact:** (1) Logging works. (2) Reuse discipline_incidents + types. (3) Required fields / framework template. (4) **Extend validation / UI**.
- **Options:** (A) Required restorative fields when type says so. (B) Keep free-text log. Implications: A is a workflow change, not a new table.

#### CE-04.2 Conduct structured restorative conversations

- **Role:** AHS/HOS. **KPI:** % of eligible cases with guided restorative conversation. **Target:** 100%. Evidence: meeting notes and counsellor logs.
- **Current:** Text `restorative_agreement` on the same record. No conversation event, no counsellor role hand-off, no eligibility rule.
- **Rating:** **B**. **Problem types:** missing workflow + missing evidence + missing role hand-off.
- **KPI class:** Requires clarification of “eligible.” Not separately calculated.
- **Impact:** (1) Conversation is a textarea, not a stage. (2) Reuse restorative_* fields. (3) Stage lock + meeting notes artefact. (4) **Workflow/state** on existing row **or child events**.
- **Options:** (A) Child “conversation” records. (B) Enforce fields + role when status moves. (C) Keep one form. Implications: C is current; A matches your Incident→… chain.

#### CE-04.3 Develop, execute, and monitor restoration action plans

- **Role:** Chaplain. **KPI:** 100% plan completion (≥90% no recurrence). Evidence: agreements and follow-up reports.
- **Current:** `restorative_actions` text; completed timestamp. No plan tasks, no follow-up visits, no recurrence link. Dual target.
- **Rating:** **B**. **Problem types:** missing workflow + missing KPI (recurrence) + methodology (two targets).
- **KPI class:** Completion flag is the executive KPI. Recurrence is currently impossible. Dual target needs clarification.
- **Impact:** (1) Action-plan lifecycle missing. (2) Reuse text + completed_at. (3) Plan items + follow-up + recurrence. (4) **Extend** or **child table**; recurrence needs learner+date query on existing incidents (**no new table strictly required**).
- **Options:** (A) Recurrence = new incident same learner within N days. (B) Full plan tasks. (C) Keep completed flag. Implications: A reuses incidents.

---

### CE-05 Bullying Incident Resolution Rate

**Module:** `reported|investigating|closed` + safety_plan text.  
**Executive KPI:** closed **and** safety_plan_created ÷ reported (month).

#### CE-05.1 Report, log, and acknowledge within 24 hours

- **Role:** AHS/HOS. **KPI:** % logged and acknowledged within 24 hours. **Target:** 100%.
- **Current:** Case can be created (`reported_on`). No acknowledgement event, no 24h SLA. Teachers can also create (wider than sheet).
- **Rating:** **B**. **Problem types:** missing workflow (ack) + missing KPI (SLA) + role breadth.
- **KPI class:** SLA calculable after `acknowledged_at` exists. Today only “row created.”
- **Impact:** (1) Immediate safeguarding ack not modeled. (2) Reuse bullying_cases. (3) Ack timestamp + SLA service. (4) **Extend existing**.
- **Options:** (A) Add acknowledged_at. (B) Treat create as ack. Implications: B is weaker safeguarding.

#### CE-05.2 Investigate using anti-bullying procedures within 48 hours

- **Role:** Teachers. **KPI:** % investigated/assessed by panel/head within 48h. **Target:** ≥95%.
- **Current:** Status can be set to `investigating`. No investigation notes entity, witness statements, panel, or 48h clock. Role (Teacher vs leadership) conflicts with “panel/head.”
- **Rating:** **B**. **Problem types:** missing capture + missing workflow + missing role clarity + missing KPI.
- **KPI class:** Requires clarification (who investigates) / not calculable as specified.
- **Impact:** (1) Investigation artefacts missing. (2) Reuse status enum. (3) Investigation pack + SLA. (4) **Extend** (JSON/notes) or **child table**.
- **Options:** (A) Structured investigation fields. (B) Status-only. Implications: B is current.

#### CE-05.3 Execute resolution and post-resolution follow-up (10–14 days)

- **Role:** HODS. **KPI:** % of resolved cases followed up for safety and zero recurrence. **Target:** 100% follow-up.
- **Current:** Close + safety plan satisfies executive KPI. No follow-up visit, no recurrence check.
- **Rating:** **D**. **Problem types:** missing workflow + missing KPI + missing evidence.
- **KPI class:** Follow-up % currently impossible. Executive KPI is close-with-plan, not follow-up.
- **Impact:** (1) Follow-up stage missing. (2) Reuse closed cases. (3) Follow-up record 10–14 days later. (4) **Extend** (`followed_up_at`) — no new table required.
- **Options:** (A) Follow-up fields. (B) Keep close+plan as “resolved.” Implications: B matches today’s KPI, not this row.

---

### CE-06 Scripture Memory & Application Mastery

**Module:** passages + term marksheet (`recites_correctly`, `explains_contextually`).  
**Executive KPI:** both-true ÷ assessed.

#### CE-06.1 Teach approved weekly verses per termly spiritual syllabus

- **Role:** Chaplain. **KPI:** % of planned verses taught and explained across all classes. **Target:** 100%. **Weekly.** Evidence: “test of knowledge of verse of the week.”
- **Current:** Passages can be stored. No “taught this week” log; assessment is not a weekly teach record.
- **Rating:** **B**. **Problem types:** missing capture (taught) + missing workflow + KPI (planned vs taught).
- **KPI class:** Calculable after a taught-log. Not the current mastery KPI.
- **Impact:** (1) Syllabus delivery not tracked. (2) Reuse scripture_passages. (3) Weekly taught flag per class. (4) **Extend** or **small teach-log**.
- **Options:** (A) Taught checkbox per passage/week/class. (B) Infer teach from any assessment. Implications: B is a poor proxy.

#### CE-06.2 Guide practical application of memorized scriptures

- **Role:** Chaplain. **KPI:** % demonstrating understanding and practical reflection. **Target:** ≥90%. **Monthly.**
- **Current:** Boolean `explains_contextually`. No classwork sheets.
- **Rating:** **B**. **Problem types:** missing evidence + coarse capture.
- **KPI class:** Directly calculable as explains-true ÷ assessed (part of CE-06). Not monthly-only.
- **Impact:** (1) Application is a yes/no. (2) Reuse assessment booleans. (3) Richer rubric or artefacts. (4) **Extend** or accept boolean.
- **Options:** (A) Keep boolean. (B) Scored application. Implications: A is live.

#### CE-06.3 Assess recitation accuracy and celebrate mastery

- **Role:** AHS/HOS. **KPI:** % achieving ≥80% accuracy in memory and application. **Target:** ≥90% meeting target. Evidence includes certificates.
- **Current:** Two booleans, not an 80% score. No certificates. Role in app includes chaplain/teachers via class forms.
- **Rating:** **B**. **Problem types:** missing capture (score) + missing evidence + KPI methodology (boolean vs 80%).
- **KPI class:** Requires clarification. Current both-true ÷ assessed is a different definition.
- **Impact:** (1) Celebration/certificate process missing; scoring is binary. (2) Reuse assessments. (3) Numeric score + recognition. (4) **Extend** columns.
- **Options:** (A) Add score 0–100. (B) Keep booleans and rewrite the KPI. Implications: B is current.

---

### CE-07 Parent-School Christian Culture Alignment

**Module:** charters + guardian registry + signature `pending|signed|declined`.  
**Executive KPI:** signed ÷ active parents.

#### CE-07.1 Communicate values and obtain signed Christian alignment commitment

- **Role:** Chaplain. **KPI:** % of parents provided handbook and signed commitment. **Target:** 100%. **Beginning of term.**
- **Current:** Staff record signature status. Charter content exists. No “handbook provided” flag, no orientation attendance.
- **Rating:** **B**. **Problem types:** missing capture (provision/orientation) + missing evidence (actual signed image).
- **KPI class:** Directly calculable as signed ÷ parents (executive CE-07). Dual action (provided + signed) is not split.
- **Impact:** (1) Commitment register works; handbook issuance is implicit. (2) Reuse signatures + charters + parents. (3) Provided-on + optional scan. (4) **Extend** signatures.
- **Options:** (A) Keep signed %. (B) Track handbook issued vs signed. Implications: A is live.

#### CE-07.2 Engage parents in spiritual activities (chapel, prayer, chatroom)

- **Role:** Chaplain. **KPI:** % participating in spiritual programmes (min 2 events/term; ≥90% positive).
- **Current:** No parent event attendance, chatroom, or survey. DI-06 is login counts, not spiritual engagement.
- **Rating:** **D**. **Problem types:** missing capture + missing workflow + missing KPI + missing integration.
- **KPI class:** Currently impossible. Dual target needs clarification.
- **Impact:** (1) Spiritual parent engagement process missing. (2) Reuse parents; chapel is learner-roll today. (3) Parent event register. (4) **New attendance rows** (parent, not learner) or extend chapel_attendances to parents.
- **Options:** (A) Parent event roll. (B) Reuse DI-06 (wrong construct). (C) Offline. Implications: B would mis-measure.

#### CE-07.3 Collaborate on character formation and discipline follow-up

- **Role:** Chaplain. **KPI:** % of character/behaviour cases with documented parent collaboration. **Target:** ≥90%.
- **Current:** No joint action plan, consultation minutes, or link from CE-02/CE-04 to a parent.
- **Rating:** **D**. **Problem types:** missing dependency/integration + missing capture + missing workflow.
- **KPI class:** Currently impossible.
- **Impact:** (1) Home-school collaboration process missing. (2) Reuse incidents + character + guardians. (3) Collaboration record on a case. (4) **New relationship** (incident/character ↔ parent contact).
- **Options:** (A) Parent-contact log on discipline/character. (B) Standalone consultation module. Implications: A reuses cases.

---

## 7. Digital Innovation — full inventory

### DI-01 Digital Portal and LMS Adoption

**Module:** weekly marksheet `lms_usage_logs` (staff + learners; login_count + activity_count).  
**Executive KPI:** actors with any login/activity that week ÷ (active staff + enrolled learners). **Parents are not in the denominator.**

#### DI-01.1 Train staff, learners, and parents on LMS/portal

- **Role:** ICT Coordinator. **KPI:** % trained. **Target:** ≥95% staff & parents. **Pre-term / mid-term.**
- **Current:** No training register, manuals store, or photos.
- **Rating:** **D**. **Problem types:** missing capture + missing evidence + missing KPI + missing master data (training events).
- **KPI class:** Currently impossible.
- **Impact:** (1) Training process missing. (2) Reuse users + parents as the audience list. (3) Training event + attendance. (4) **New training records** (shared with DI-04/DI-06) or a **generic training table**.
- **Options:** (A) Shared training module. (B) Per-pillar training logs. (C) Offline. Implications: A avoids three new modules.

#### DI-01.2 Provision accounts, verify credentials, track weekly logins

- **Role:** ICT Coordinator. **KPI:** % of staff and learners with active accounts and verified weekly logins. **Target:** ≥95%.
- **Current:** Manual weekly counts. No account provisioning, no LMS API, no credential verify. Parents excluded.
- **Rating:** **B**. **Problem types:** missing master data (accounts) + missing integration + missing capture (real logs).
- **KPI class:** Directly calculable **as manually entered weekly activity**. Not calculable as provisioned-account audit.
- **Impact:** (1) True provisioning missing; a proxy register exists. (2) Reuse users/learners + lms_usage_logs. (3) Account status + optional import. (4) **Extend users** (`lms_provisioned`) and/or **integration** — not required to add a new fact table if import updates the existing log.
- **Options:** (A) Keep manual weekly register (document as proxy). (B) CSV import from LMS. (C) Live LMS API. Implications: A is current; C is a large integration.

#### DI-01.3 Collect feedback and implement enhancements

- **Role:** Admin Manager. **KPI:** % of digital feedback issues reviewed and acted on in time. **Target:** ≥90%. **Monthly.**
- **Current:** No feedback, tickets, or change-log.
- **Rating:** **D**. **Problem types:** missing capture + missing workflow + missing KPI + missing UI.
- **KPI class:** Currently impossible. “Specified timeframe” needs clarification.
- **Impact:** (1) Feedback loop missing. (2) No helpdesk to reuse. (3) Feedback/ticket + resolution. (4) **New small ticket table** or external helpdesk + status import.
- **Options:** (A) In-app tickets. (B) External helpdesk, store monthly %. (C) Notes on LMS form (too weak). Implications: B may avoid a new product surface.

---

### DI-02 Learner Coding & STEM Practical Completion

**Module:** `stem_project_completions` per learner/type; admin types.  
**Executive KPI:** learners with ≥1 completed active-type project ÷ enrolled.

#### DI-02.1 Procure and verify STEM inventory and licences

- **Role:** HOS. **KPI:** % of required equipment/assets available. **Target:** 100%. **Beginning of term.**
- **Current:** No inventory, licences, or readiness report.
- **Rating:** **D**. **Problem types:** missing master data + missing capture + missing KPI.
- **KPI class:** Currently impossible (no required-asset list).
- **Impact:** (1) Readiness process missing. (2) Nothing to reuse. (3) Asset catalogue + term checklist. (4) **New inventory structures** (this is one of the few gaps that likely needs new tables).
- **Options:** (A) Simple term checklist (item, required, available). (B) Full inventory system. (C) Offline HoS sign-off. Implications: A is enough for the KPI.

#### DI-02.2 Schedule and deliver practicals twice weekly

- **Role:** ICT Coordinator. **KPI:** % of planned practicals delivered. **Target:** 100%. **Weekly.**
- **Current:** No timetable, lab log, or delivery record.
- **Rating:** **D**. **Problem types:** missing master data + missing capture + missing workflow + missing KPI.
- **KPI class:** Currently impossible.
- **Impact:** (1) Delivery process missing. (2) Weak reuse: chapel-like session+roll pattern. (3) Practical sessions. (4) **New session table** or reuse a generic “session” pattern — not STEM project completions.
- **Options:** (A) STEM session register. (B) Reuse coverage logs with STEM subject. Implications: B only works if STEM is a subject with SoW.

#### DI-02.3 Weekly projects, assignments, and termly exhibitions

- **Role:** HODs. **KPI:** % completing projects and participating in exhibitions. **Target:** ≥95% (1–2 exhibitions/session).
- **Current:** Completion status + optional score. No files, repos, rubrics, or exhibitions.
- **Rating:** **B**. **Problem types:** missing evidence + missing capture (exhibition) + dual KPI.
- **KPI class:** Project completion % is the executive KPI. Exhibition participation is currently impossible.
- **Impact:** (1) Exhibition/evidence missing. (2) Reuse completions + types. (3) Exhibition event + file links. (4) **Extend** completions (`exhibition`) + optional files.
- **Options:** (A) Exhibition flag. (B) Separate exhibition register. (C) Keep completion-only KPI. Implications: C is current.

---

### DI-03 AI & Technology Ethics Compliance

**Module:** audit register + types; `free_of_violations`.  
**Executive KPI:** compliant audits ÷ audits (0 audits → no recalc).

#### DI-03.1 Educate staff and learners (2 sessions/term)

- **Role:** Teachers. **KPI:** % attending ethics workshops. **Target:** ≥90% participation.
- **Current:** No workshop attendance.
- **Rating:** **D**. **Problem types:** missing capture + missing evidence + missing KPI.
- **KPI class:** Currently impossible.
- **Impact:** (1) Education process missing. (2) Reuse shared training idea (DI-01.1). (3) Two sessions + attendance. (4) **Shared training table**, not ethics-specific schema.
- **Options:** (A) Shared training. (B) Ethics-only attendance. Implications: A reduces duplication.

#### DI-03.2 Monitor and audit AI use in lessons and assignments (monthly samples)

- **Role:** HoDs. **KPI:** **number** of monthly sample audits / 100% compliant samples. Mixed “number” vs “% compliant.”
- **Current:** HOD/teacher can log audits. No sample plan, no monthly quota enforcement. Executive KPI is % compliant of logged audits (selection bias).
- **Rating:** **B**. **Problem types:** missing workflow (sample plan) + KPI methodology ambiguity.
- **KPI class:** Requires clarification (count vs % compliant). % of logged audits is directly calculable.
- **Impact:** (1) Ad-hoc audits exist; planned sampling does not. (2) Reuse digital_ethics_audits. (3) Monthly sample plan. (4) **Config/quota + existing table**.
- **Options:** (A) Keep ad-hoc % compliant. (B) Require N audits/month. Implications: A is current; biased.

#### DI-03.3 Enforce honesty, resolve misuse/plagiarism within 5 days

- **Role:** HoS. **KPI:** % of reported incidents investigated and resolved within 5 days. **Target:** ≥90%.
- **Current:** Violation fields on the audit form. No investigation workflow, 5-day SLA, or link to discipline.
- **Rating:** **B**. **Problem types:** missing workflow + missing KPI (SLA) + missing integration (CE-04).
- **KPI class:** Calculable after opened_at/resolved_at. Not calculated today.
- **Impact:** (1) Enforcement SLA missing. (2) Reuse audit row or discipline incident. (3) Case SLA. (4) **Extend timestamps** or **integrate CE-04**.
- **Options:** (A) SLA on ethics audit. (B) File as discipline incident. Implications: B reuses restorative process.

---

### DI-04 Staff Digital Competency Mastery

**Module:** area marksheet, levels 1–4, passing_level default 3.  
**Executive KPI:** staff proficient on **all** active areas ÷ active staff.  
**Controller** is ICT/leadership/admin — **not HOD**, though the sheet gives DI-04.3 to HODs. Observation module is separate.

#### DI-04.1 Monthly ICT / digital teaching workshops

- **Role:** ICT Coordinator. **KPI:** number of hands-on sessions; 100% participation; min 1/month.
- **Current:** No workshop register.
- **Rating:** **D**. **Problem types:** missing capture + missing KPI + missing evidence.
- **KPI class:** Currently impossible. Dual “count” vs “100% participation.”
- **Impact:** (1) Training process missing. (2) Shared training module. (3) Session + staff attendance. (4) **Reuse DI-01.1 design**.
- **Options:** Same as DI-01.1 shared training.

#### DI-04.2 Peer coaching for staff needing improvement

- **Role:** ICT Coordinator. **KPI:** % of staff needing improvement enrolled in peer coaching. **Target:** 100%.
- **Current:** Ratings can show who is below passing_level. No pairing, schedule, or coaching log.
- **Rating:** **D**. **Problem types:** missing workflow + missing capture + missing KPI.
- **KPI class:** Denominator (needs improvement) is calculable from ratings. Enrolment is currently impossible.
- **Impact:** (1) Coaching process missing. (2) Reuse competency ratings to list candidates. (3) Pairing records. (4) **New small pairing table** or **extend** ratings (`coach_id`).
- **Options:** (A) coach_id on the rating. (B) Pairing table. Implications: A avoids a new table.

#### DI-04.3 Observe digital tool use in daily instruction

- **Role:** HODs. **KPI:** % of teaching staff effective in digital tool use during observations. **Target:** ≥90%.
- **Current:** General AE-06 observations exist (not digital-specific). DI-04 ratings are a marksheet, not an observation. HOD cannot access competency controller.
- **Rating:** **B**. **Problem types:** missing integration + missing role/permission + missing capture (digital checklist).
- **KPI class:** Calculable if AE-06 gains a digital standard or DI-04 ratings are accepted as the proxy. Not calculated as specified.
- **Impact:** (1) Digital observation process not wired. (2) Reuse Observation rubric **or** competency ratings. (3) Digital standard on AE-06 and/or HOD access. (4) **Extend rubric + permissions** — no new table required.
- **Options:** (A) Add digital standards to ObservationRubric and report. (B) Let HOD complete DI-04 ratings after walk-throughs. (C) Keep ICT marksheet only. Implications: A reuses AE-06.

---

### DI-05 Digital Assessment & E-Portfolio Usage

**Module:** one row per subject/term: flags `uses_e_assessment`, `uses_e_portfolio`, `primary_tool`, `evidence_notes`, self `verified_by`.  
**Executive KPI:** subjects with either flag ÷ active subjects.

#### DI-05.1 Administer periodic digital assessments (min 2/term, ≥90% completion)

- **Role:** ICT Coordinator. **KPI:** % of planned termly assessments administered digitally.
- **Current:** Subject flag only. No planned tests, no learner completions, no portal records.
- **Rating:** **B**. **Problem types:** missing capture + missing integration + KPI mismatch (subject adoption ≠ test completion).
- **KPI class:** Subject-flag rate is directly calculable. Test completion is currently impossible.
- **Impact:** (1) Digital assessment delivery not executed. (2) Reuse subjects + flags. (3) Assessment events + completion. (4) **New assessment-event table** *or* reuse exam_results with `assessment_key=digital`.
- **Options:** (A) Keep subject adoption KPI. (B) Log digital tests. (C) Import from Google/LMS. Implications: A is current; C is integration.

#### DI-05.2 Create and maintain individual learner e-portfolios

- **Role:** ICT Coordinator. **KPI:** % of learners with active updated portfolios. **Target:** 100%.
- **Current:** Subject-level “uses e-portfolio” flag. No learner portfolios or artefacts.
- **Rating:** **D**. **Problem types:** missing master data + missing capture + missing evidence + missing KPI.
- **KPI class:** Currently impossible.
- **Impact:** (1) E-portfolio process missing. (2) No table to reuse. (3) Portfolio URL/artefacts per learner. (4) **New structures** (or external platform + URL register — **small new table**, not a full LMS).
- **Options:** (A) URL register per learner. (B) File vault in-app. (C) External only, no KPI. Implications: A is enough to start measuring.

#### DI-05.3 Analyse e-portfolio data to target support

- **Role:** ICT Coordinator. **KPI:** % of portfolio reviews conducted to assist struggling learners. **Target:** 100%.
- **Current:** None.
- **Rating:** **D**. **Problem types:** missing dependency + missing workflow + missing KPI.
- **KPI class:** Currently impossible until 05.2 exists.
- **Impact:** (1) Review process missing. (2) Reuse AE-07 once reviews exist. (3) Review log + support link. (4) **Extend** portfolio rows.
- **Options:** (A) Review checklist on the URL register. (B) Defer until 05.2. Implications: B is sequential.

---

### DI-06 Parent Portal Engagement Rate

**Module:** monthly `login_count` per guardian; parents are **not** authenticated portal users.  
**Executive KPI:** families with login_count>0 ÷ families with an enrolled learner.

#### DI-06.1 Enroll and activate parent accounts for academic/attendance tracking

- **Role:** Admin Manager. **KPI:** % of parents registered with **active login access**. **Target:** ≥95%.
- **Current:** Parent registry + optional monthly login ticks. No parent login, no academic/attendance parent views beyond staff recording.
- **Rating:** **B**. **Problem types:** missing master data (accounts) + missing integration + KPI (registry ≠ login access).
- **KPI class:** Registry coverage is calculable. “Active login access” is currently impossible as real auth.
- **Impact:** (1) True parent portal missing. (2) Reuse `parents` + learner_parent. (3) User accounts or accept registry as proxy. (4) **New users/roles** (large) vs **config** (treat registry as enrolment).
- **Options:** (A) Create parent users. (B) Keep CRM registry + manual activity. (C) External portal, import logins. Implications: A is a product decision, not a small table tweak.

#### DI-06.2 Parent orientation and technical guidance

- **Role:** ICT Coordinator. **KPI:** % attending or receiving materials; min 1 orientation/term; ≥90% reach. Evidence: attendance, guides, **helpdesk**.
- **Current:** No orientation, guides, or helpdesk.
- **Rating:** **D**. **Problem types:** missing capture + missing workflow + missing KPI.
- **KPI class:** Currently impossible.
- **Impact:** (1) Orientation process missing. (2) Shared training (DI-01.1) + parents list. (3) Event + reach. (4) **Shared training**, not a new product line.
- **Options:** Same shared training as DI-01.1 / DI-04.1.

#### DI-06.3 Monitor inactive accounts and follow up within 10 working days

- **Role:** Admin Manager. **KPI:** % of inactive accounts contacted and supported. **Target:** 100% follow-up.
- **Current:** Inactivity can be inferred from login_count=0. No outreach, tickets, or 10-day SLA.
- **Rating:** **D**. **Problem types:** missing workflow + missing capture + missing KPI + missing UI.
- **KPI class:** Inactive list is calculable from current monthly rows. Follow-up % is currently impossible.
- **Impact:** (1) Outreach process missing. (2) Reuse monthly engagements to list inactives. (3) Contact log + SLA. (4) **Extend** parent_portal_engagements (`followed_up_at`) — **no new table required**.
- **Options:** (A) Follow-up fields on the monthly row. (B) Tickets (with DI-01.3). Implications: A is the smallest.

---

## 8. Menu / tab impact

Agreed navigation (**Pillar → Activity → role-filtered tabs**) is kept. Tabs today show framework text and share one register.

| Pattern | Activities | Implication |
|---------|------------|-------------|
| Tab is documentation on a shared register | Most of the 23 | Tabs can stay; process work is still the same form unless a stage is built. |
| Tab should become a **stage** (different form, status lock, role) | AE-01 (plan vs deliver vs check vs SIP vs tests); AE-02; AE-05 (already closest); AE-06 follow-up; CE-04; CE-05 | May need extra routes or query `?stage=` **after** you choose an option — not done now. |
| Tab should open a **different existing module** | AE-01.1 → lesson plans / future SoW; AE-02.2 → at-risk; DI-04.3 → observations | Integration, not a new menu tree. |
| Coming-soon tabs | AE-09, AE-10 | Honest placeholders until methodology + tables exist. |
| Role filter already hides tabs | Staff vs oversight | Do not confuse “tab hidden” with “process complete.” |

No navigation rewrite is recommended in this analysis.

---

## 9. Options register (no selected solution)

Cross-cutting choices that affect many rows. Discuss before any build.

| Theme | Option | Implication |
|-------|--------|-------------|
| **Sub-KPIs vs executive KPIs** | Keep 21 activity KPIs as Board scorecard; treat 72 rows as operational SOPs | Fastest; many rows stay “methodology = activity proxy.” |
| | Compute a subset of sub-KPIs where data already exists (AE-05 SLA, CE-01 by type, AE-04 day-complete) | New calculations only; no new tables. |
| | Build all 72 measures | Large; many need new capture first. |
| **Scheme of Work** | Activate existing `schemes_of_work` with upload/approve UI | Unblocks AE-01.1; extends existing schema. |
| | Continue seeder-only SoW | Planning process stays broken. |
| **Registers vs ledgers** | Keep class/event aggregates (homework, attendance, service, LMS) | Current KPIs keep working; per-person framework KPIs stay impossible. |
| | Move selected registers to per-learner/per-parent | New relationships; heavier UI. |
| **Case workflows** | Status fields on one record (today) | Simple; not the Incident → Investigation → Plan → Follow-up → Evidence chain. |
| | Stage locks + artefacts on the **same** row | Extends existing tables; no new modules. |
| | Child records per stage (discipline, bullying, ethics) | New relationships; closer to the framework. |
| **Evidence** | Text / optional lesson-plan file (today) | Most Evidence column items stay captions. |
| | Reuse `Storage` pattern from lesson plans on selected records | Extends existing rows (`file_path`). |
| | Full evidence vault | New structures; only if Board wants a DMS. |
| **Training (DI-01.1, DI-03.1, DI-04.1, DI-06.2)** | One shared training-and-attendance module | Avoids four parallel builds. |
| | Separate log per activity | More tables, same idea repeated. |
| **Parent digital access** | Keep `parents` as a staff-maintained registry | DI-06 stays a proxy. |
| | Real parent user accounts + portal views | New auth/permissions/UI — a product, not a column. |
| **Numeracy / assimilation** | Clone AE-08 for AE-09; defer AE-10 until an instrument exists | Honest sequencing. |
| | Design both before any table | Slower; avoids a second migration later. |
| **AE-04.2 Responsibility** | **Teacher** (owner confirmed 24 Sep 2026) | Sheet cell was blank; no longer an open question. |
| **AE-02.4 and other dual targets** | Ask Board which number is official | Do not invent a combined formula. |

### 9.1 Gaps that likely need **new** structures (if built)

These are the exceptions. Most other gaps can extend what exists.

- STEM inventory / licences (DI-02.1)
- Shared training attendance (if not overloaded onto an existing log)
- Per-learner service hours (CE-03.2) and per-learner attendance (AE-04) if ledgers are chosen
- Learner e-portfolio register (DI-05.2) unless it is only a URL column on learners
- Parent spiritual event attendance (CE-07.2)
- Numeracy (AE-09) and assimilation (AE-10) — no tables today
- Helpdesk/tickets (DI-01.3) if kept in-app

### 9.2 Gaps that do **not** require a new table (if built)

- Scheme of Work approval UI (`schemes_of_work` already exists)
- AE-05 24-hour SLA (timestamps exist)
- AE-06 follow-up link (`follows_observation_id`)
- CE-05 acknowledgement / follow-up timestamps
- CE-04 recurrence query on existing incidents
- DI-04 digital items on the existing observation rubric
- DI-06 inactive follow-up field on `parent_portal_engagements`
- Baseline exam key on `exam_results`
- Wiring AE-02 low performers to existing at-risk / IIP

---

## 10. Rating checklist (all 72)

| ID | Sub-activity (short) | Rating | KPI class |
|----|----------------------|--------|-----------|
| AE-01.1 | Plan vs approved SoW | B | Clarification / after SoW+plan |
| AE-01.2 | Deliver scheduled content | B | Clarification (time) |
| AE-01.3 | HOD check coverage | B | Direct as topic-cover % |
| AE-01.4 | Gaps / SIP catch-up | D | Impossible now |
| AE-01.5 | Coverage vs understanding | D | Impossible now |
| AE-02.1 | Baseline / gap map | D | Impossible now |
| AE-02.2 | Intervention enrolment | B | After exam↔IIP link |
| AE-02.3 | Remedial / SIP lessons | D | Impossible now |
| AE-02.4 | Intervention progress | D | Clarification + impossible |
| AE-03.1 | Assign vs planned | B | Clarification |
| AE-03.2 | Mark / record completion | B | Direct as aggregates |
| AE-03.3 | Make-up follow-up | D | Impossible now |
| AE-04.1 | Daily attendance + punctuality | B | Clarification |
| AE-04.2 | Patterns / absentee list | D | Impossible now (role = Teacher) |
| AE-04.3 | Follow-up to management | D | Impossible now |
| AE-05.1 | Submit plans on time | B | Direct (submit); exec uses approved |
| AE-05.2 | Review vs checklist | B | After checklist or proxy |
| AE-05.3 | Approve / return in 24h | B | SLA calculable from existing times |
| AE-06.1 | Planned observations done | B | Clarification of “planned” |
| AE-06.2 | Feedback / coaching | B | Direct if text required |
| AE-06.3 | Subsequent improvement | D | Impossible now |
| AE-07.1 | Identify at-risk | B | Clarification of denominator |
| AE-07.2 | Active IIP | B | Direct (exec AE-07) |
| AE-07.3 | Outcome improvement | D | Impossible now |
| AE-08.1 | Literacy baseline | B | Coverage calculable; exec is growth |
| AE-08.2 | Monthly literacy instruction | D | Impossible now |
| AE-08.3 | ≥1 year growth | B | Direct as configured; scale unclear |
| AE-09.1–.3 | Numeracy (all) | C | Impossible now |
| AE-10.1–.3 | Assimilation (all) | C | Clarification + impossible |
| CE-01.1 | Assembly cadence | B | After type filter |
| CE-01.2 | Class devotion | B | After type filter |
| CE-01.3 | Weekly chapel | B | Clarification (dual target) |
| CE-02.1 | Values lessons | D | Impossible now |
| CE-02.2 | Daily conduct | B | Direct as term Secure+ |
| CE-02.3 | Peer review / recognition | D | Impossible / clarification |
| CE-03.1 | Service calendar | D | Impossible now |
| CE-03.2 | Hours per learner | B | Average only |
| CE-03.3 | Recognition | D | Impossible now |
| CE-04.1 | Log incidents | B | Clarification of “accurate” |
| CE-04.2 | Restorative conversation | B | Clarification of eligible |
| CE-04.3 | Action plan / no recurrence | B | Completion direct; recurrence no |
| CE-05.1 | Log / ack in 24h | B | After ack timestamp |
| CE-05.2 | Investigate in 48h | B | Clarification |
| CE-05.3 | Follow-up 10–14 days | D | Impossible now |
| CE-06.1 | Teach weekly verse | B | After taught-log |
| CE-06.2 | Application | B | Direct as boolean |
| CE-06.3 | Recitation ≥80% + celebrate | B | Clarification (boolean vs score) |
| CE-07.1 | Handbook + signed commitment | B | Direct as signed % |
| CE-07.2 | Parent spiritual events | D | Impossible now |
| CE-07.3 | Parent collaboration on cases | D | Impossible now |
| DI-01.1 | LMS/portal training | D | Impossible now |
| DI-01.2 | Provision + weekly logins | B | Direct as manual proxy |
| DI-01.3 | Feedback / enhancements | D | Impossible now |
| DI-02.1 | STEM inventory | D | Impossible now |
| DI-02.2 | Practical timetable | D | Impossible now |
| DI-02.3 | Projects / exhibitions | B | Completion direct; exhibition no |
| DI-03.1 | Ethics workshops | D | Impossible now |
| DI-03.2 | Monthly AI audits | B | Clarification (count vs %) |
| DI-03.3 | Resolve misuse in 5 days | B | After SLA fields |
| DI-04.1 | Monthly ICT workshops | D | Impossible now |
| DI-04.2 | Peer coaching | D | Enrolment impossible |
| DI-04.3 | Digital teaching observation | B | After AE-06 link |
| DI-05.1 | Digital tests | B | Subject flags only |
| DI-05.2 | Learner e-portfolios | D | Impossible now |
| DI-05.3 | Portfolio reviews | D | Impossible now |
| DI-06.1 | Parent account activation | B | Registry proxy only |
| DI-06.2 | Parent orientation | D | Impossible now |
| DI-06.3 | Inactive follow-up | D | Follow-up impossible |

---

## 11. Stop

This file is the **functional blueprint for WISCA v2** for discussion. It is not a build list and no option above is selected.

**Not done in this exercise:** no code, migrations, routes, controllers, models, views, permissions, or configuration were changed.

**Next (human):** review remaining open items — dual-target rows and whether the Board scorecard stays at 21 executive KPIs. AE-04.2 is **Teacher**; AE-01.5 target is **95%**. Then choose which gaps to design. Implementation starts only after that confirmation.
