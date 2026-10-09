# WISCA PEMS — User Manual

**School:** Wisdom Christian Academy (WISCA)  
**Product:** WISCA PEMS  
**Audience:** Board, Head of School, Heads of Department, teachers, officers, coordinators, IT, and System Admin  
**Version:** aligned to the locked Excel-to-app rules (`chambers/WISCA-Excel-to-App-Rules.md`)

This manual walks you through the whole application: how to sign in, what each role sees, how to enter data for every KPI, how the Executive Dashboard works, and how administrators set the school up.

---

## Table of contents

1. [What WISCA is](#1-what-wisca-is)
2. [Getting started](#2-getting-started)
3. [Roles, home screens, and demo accounts](#3-roles-home-screens-and-demo-accounts)
4. [Common screens and habits](#4-common-screens-and-habits)
5. [How numbers and status labels work](#5-how-numbers-and-status-labels-work)
6. [Board & Head of School — Executive Dashboard](#6-board--head-of-school--executive-dashboard)
7. [Status Thresholds](#7-status-thresholds)
8. [System Admin — Control Panel & Admin Hub](#8-system-admin--control-panel--admin-hub)
9. [Academic Excellence (AE-01 … AE-08)](#9-academic-excellence-ae-01--ae-08)
10. [Christocentric Education (CE-01 … CE-07)](#10-christocentric-education-ce-01--ce-07)
11. [Digital Innovation (DI-01 … DI-06)](#11-digital-innovation-di-01--di-06)
12. [Role-by-role quick guides](#12-role-by-role-quick-guides)
13. [Academic sessions and terms](#13-academic-sessions-and-terms)
14. [My Profile](#14-my-profile)
15. [Troubleshooting & FAQ](#15-troubleshooting--faq)
16. [Appendix — KPI reference table](#16-appendix--kpi-reference-table)
17. [Appendix — Technical operations (optional)](#17-appendix--technical-operations-optional)

---

## 1. What WISCA is

WISCA PEMS is **not** a full school LMS. It is a **strategy and accountability portal** that measures whether the school is delivering its Board strategy across three pillars:

| Pillar | KPI codes | Count |
|---|---|---|
| Academic Excellence | AE-01 … AE-08 | 8 |
| Christocentric Education | CE-01 … CE-07 | 7 |
| Digital Innovation | DI-01 … DI-06 | 6 |
| **Total** | | **21** |

Staff enter operational evidence (attendance, lesson plans, chapel roll, LMS usage, and so on). The system calculates **Actual ÷ Target** for each KPI, labels status, and rolls results up for the Board on the Executive Dashboard.

**Golden rule:** If the app and the strategy Excel disagree on formulas or labels, the Excel wins — unless the Board/Proprietor deliberately changes thresholds in the app.

---

## 2. Getting started

### 2.1 Open the portal

1. Open the WISCA web address provided by your school (locally this is often something like `http://localhost` or your XAMPP/Herd URL).
2. You land on the **login** page.
3. Enter your **email** and **password**.
4. Optionally tick **Remember me**.
5. Click **Log in**.

After login you are taken to a **home screen that depends on your role** (see §3).

### 2.2 Sign out

Use **Sign out** at the bottom of the left sidebar.

### 2.3 Demo / training passwords

On a freshly seeded training database, every demo account uses:

| Field | Value |
|---|---|
| Password | `password` |

Change passwords in production. Demo emails are listed in §3.

### 2.4 First things to notice

- **Left sidebar** — three strategy pillars (Academic Excellence, Christcentric Education, Digital Innovation). Open a pillar, then click an **activity**. Measurable sub-activities assigned to your role appear as **tabs** on that activity page (not as extra sidebar lines). School name (and logo if uploaded) sit at the top.
- **Academic session chip** — shows the school’s current session (for example `2025/2026`).
- **Top area** — page title for where you are.
- **Account** — **My Profile** for your name, password, and passport photo.

---

## 3. Roles, home screens, and demo accounts

### 3.1 Who does what (simple map)

| Role | Main job in WISCA |
|---|---|
| **Board** | Read strategy health; optionally adjust status thresholds |
| **Head of School (HoS)** | Executive view + oversight of operations and several KPIs |
| **Head of Department (HoD)** | Verify coverage & lesson plans; exams; department operations |
| **Teacher** | Lesson plans, homework, coverage evidence, some marksheets |
| **Admin Officer** | Class roll, attendance, chapel assistance |
| **Learning Support Coordinator** | At-risk learners and intervention plans |
| **Literacy Coordinator** | Reading progress assessments |
| **Chaplain** | Chapel, character recognition, restorative follow-up, scripture, parent Christian culture |
| **Student Life Coordinator** | Character ratings, community service, and other Christcentric activities they already use |
| **Parent Relations Lead** | Parent partnership commitments and parent portal engagement |
| **ICT Coordinator** | LMS, STEM scheduling, digital competency, e-assessment, parent portal orientation |
| **Admin Manager** | Portal feedback and parent portal enrolment / follow-up |
| **STEM Coordinator** | STEM / coding project completion |
| **Assistant Head (Secondary)** | Oversight of the full three-pillar tree with Head of School |
| **Subject Lead** | Curriculum coverage |
| **System Admin** | School structure, staff, KPI metadata, control panel, look-up lists |

### 3.2 Where you land after login

| Role | Home screen |
|---|---|
| System Admin | `/admin` — Admin Hub |
| Parent Relations Lead | `/partnership` |
| STEM Coordinator | `/stem` |
| ICT Coordinator | `/lms` |
| Admin Manager | `/portal-engagement` |
| Chaplain | `/chapel` |
| Subject Lead | `/coverage-logs` |
| Everyone else with a dashboard | `/dashboard` (role-specific layout) |

### 3.3 Demo accounts (training seed)

| Email | Suggested use |
|---|---|
| `board@wisca.test` | Board / Proprietor view |
| `hos@wisca.test` | Head of School |
| `hod@wisca.test` | Head of Department |
| `teacher@wisca.test` | Class Teacher |
| `officer@wisca.test` | Admin Officer |
| `admin@wisca.test` | System Admin |
| `support@wisca.test` | Learning Support |
| `literacy@wisca.test` | Literacy Coordinator |
| `chaplain@wisca.test` | Chaplain |
| `slc@wisca.test` | Student Life Coordinator |
| `prl@wisca.test` | Parent Relations Lead |
| `it@wisca.test` | ICT Coordinator |
| `stem@wisca.test` | STEM Coordinator |

Password for all demo users: **`password`**.

---

## 4. Common screens and habits

### 4.1 Activity tabs

Each activity page shows **only the measurable sub-activities assigned to your role** (from the WISCA v2 Responsibility column). Head of School, Assistant Head, Board, and System Admin see every tab for that activity. Specialist roles attached to an activity (for example Subject Lead on Curriculum Coverage) also see every tab there. Open a tab to see target, frequency, responsibility, and evidence. Tabs marked **Your work** belong to your role. The register or form below the tabs is shared for the whole activity.

Bookmark a tab with `?sub=2` (or another tab number) on the activity URL.

### 4.2 Filters (session / term / class)

Most operational pages let you filter by:

- **Session** — academic year (for example 2025/2026)
- **Term** — First / Second / Third Term (where relevant)
- **Class / subject** — when the screen is about a marksheet or register

**Tip:** Always confirm the session and term before you enter data. Wrong period = wrong KPI week/term.

### 4.3 Lists, create, edit

Pattern used almost everywhere:

1. Open the module from the sidebar (for example **Homework**).
2. Review the list (often filtered by session/term).
3. Click **Create**, **Record**, **Log**, or **Add**.
4. Fill the form and **Save**.
5. Edit or delete later from the list when your role allows.

### 4.4 Verification / approval (important)

Only some workflows need a second person:

| Workflow | Who submits | Who verifies / approves |
|---|---|---|
| Curriculum coverage (AE-01) | Teacher | HoD or HoS |
| Lesson plans (AE-05) | Teacher | HoD or HoS |
| Community service hours (CE-03) | Staff / SLC | Admin, HoS, or SLC |

Until coverage or lesson plans are approved, related KPIs will not move as expected.

### 4.5 Marksheets

Several DI/CE modules use a **record / marksheet** page:

1. Open the module index.
2. Click **Record** (or equivalent).
3. Set session/term (and week/month if asked).
4. Tick or enter values for each learner/staff/subject row.
5. Save — the KPI recalculates for that period.

---

## 5. How numbers and status labels work

You do **not** need to memorise formulas to use the app, but understanding labels helps when speaking to the Board.

### 5.1 Achievement

For every KPI:

```text
Achievement = Actual ÷ Target
```

Examples:

- Target 95%, Actual 95% → achievement **1.00** (100% of target)
- Target 90%, Actual 92% → achievement **about 1.02** (above target)
- CE-03 is special: target is **10 hours** per learner (not a percentage)

### 5.2 Three independent label scales

Do not mix these up — they answer different questions.

**A. KPI row status** (each metric)

| Achievement | Label |
|---|---|
| ≥ 1.00 | **ON TRACK** |
| ≥ 0.90 | **NEEDS ATTENTION** |
| &lt; 0.90 | **OFF TRACK** |

Beating the target still shows **ON TRACK** at KPI level (not “Exceeding”).

**B. Pillar headline** (average of KPIs in that pillar)

| Average | Label |
|---|---|
| ≥ 1.00 | **EXCEEDING** |
| ≥ 0.90 | **ON TRACK** |
| &lt; 0.90 | **NEEDS ATTENTION** |

**C. Overall health** (pillar or school-wide average)

| Average | Label |
|---|---|
| ≥ 0.95 | **HEALTHY** |
| ≥ 0.90 | **SATISFACTORY** |
| &lt; 0.90 | **CRITICAL** |

Board/HoS/Admin can change these cut-offs under **Status Thresholds** (§7).

### 5.3 How the school total is calculated

- **Pillar score** = unweighted average of that pillar’s KPI achievements  
- **School-wide score** = unweighted average of **all 21** KPI achievements  

There is no “40% weighting” between pillars unless the Board later changes policy.

---

## 6. Board & Head of School — Executive Dashboard

**Path:** `/dashboard` (Board and HoS)

### 6.1 What you see

1. **Session switcher** — choose which academic session’s numbers to view.
2. **School hero strip**
   - Overall health (HEALTHY / SATISFACTORY / CRITICAL)
   - Average achievement %
   - Reported KPIs out of 21
   - Counts: On track / Needs attention / Off track
3. **Three pillar cards** — Academic Excellence, Christocentric Education, Digital Innovation  
   Click a card to drill into that pillar’s KPI table.
4. **KPI Detail table** — code, metric name, target, actual, achievement, status, owner role  
   Optional filter: **Show needs attention / off track only**.
5. **Full pillar summary table** (expandable) — Excel-style rollup with Headline + Health columns and a **TOTAL SCHOOL WIDE** row.

### 6.2 How Board should use it in a meeting

1. Sign in as Board.
2. Confirm the **session** at the top.
3. Read overall health and the three count tiles.
4. Click each pillar card; note any OFF TRACK or NEEDS ATTENTION rows.
5. Open the full summary table for minutes / pack printouts.
6. Assign follow-up to the KPI **owner** role shown in the table.

### 6.3 HoS extra menus

HoS sees the same Executive Dashboard **plus** an Operations menu for almost every register (learners, exams, attendance, chapel, partnership, LMS, and more). Use Operations when you need to inspect or enter data; use the Dashboard when you need the Board view.

---

## 7. Status Thresholds

**Path:** `/status-thresholds`  
**Who:** Board, Head of School, System Admin

### 7.1 Purpose

Change the cut-offs for the three status scales without a software deploy. Defaults match the locked Excel rules.

### 7.2 How to edit

1. Open **Status Thresholds** from the Strategy menu (Board/HoS) or Admin Strategy links.
2. Adjust the six minimums (decimals: `1.0` = 100% of target):
   - KPI: on track / needs attention
   - Pillar: exceeding / on track
   - Health: healthy / satisfactory
3. Click **Save thresholds**.
4. Return to the Executive Dashboard — labels refresh from the new scales.

### 7.3 Rules the form enforces

- On-track minimum must be ≥ needs-attention minimum (same idea for pillar and health pairs).
- Values are achievement-rate decimals between 0 and 2.

---

## 8. System Admin — Control Panel & Admin Hub

**Home:** `/admin`  
**Role:** System Admin (`admin@wisca.test` in demo)

### 8.1 Control Panel (school brand)

**Path:** Configuration → **Control Panel** → `/admin/control-panel`

1. Edit **School name** (appears in the sidebar and Admin Hub).
2. Upload a **logo** (JPG / PNG / WebP, max 2 MB) or remove the current logo.
3. **Save changes**.

The sidebar shows the logo when present; otherwise it shows initials from the school name.

### 8.2 Admin Hub cards

From `/admin` you can jump to:

- Control Panel  
- Academic Sessions & Terms  
- Classes, Subjects, Staff, Teacher Assignments  
- KPI Settings  
- Status Thresholds  
- Attendance (operational shortcut)

### 8.3 School structure (do this first for a new term)

Recommended order for a new school year:

1. **Academic Sessions** — create the year (e.g. 2025/2026); mark it **current**.
2. **Terms** — create First / Second / Third with start and end dates under that session.
3. **Classes** — e.g. JSS 1A.
4. **Subjects** — e.g. Mathematics.
5. **Staff & Teachers** — create users and assign Spatie roles.
6. **Teacher Assignments** — link teacher ↔ class ↔ subject ↔ session.
7. **Learners (Class Roll)** — enrol students into classes for the session.
8. Configure look-up lists for CE/DI modules (chapel types, character domains, service types, bullying types, scripture passages, partnership charters, guardians, STEM types, ethics types, competency areas).

### 8.4 KPI Settings

**Path:** `/admin/kpis`

For each KPI you can review/edit:

- Default target  
- Owner role  
- Reporting frequency  
- Active / inactive  
- Optional per-KPI threshold notes  
- For **AE-02**, pass mark (default 50%)

Prefer changing **Status Thresholds** for Board-wide label scales; use KPI Settings for targets and ownership metadata.

### 8.5 Look-up / type catalogues

Under Admin → Christocentric / Digital configuration:

| Admin path | Supports |
|---|---|
| Chapel activity types & chapel sessions | CE-01 |
| Character domains | CE-02 |
| Service activity types | CE-03 |
| Discipline incident types | CE-04 |
| Bullying case types | CE-05 |
| Scripture passages | CE-06 |
| Partnership charters & Parent registry | CE-07 |
| STEM project types | DI-02 |
| Digital ethics audit types | DI-03 |
| Digital competency areas | DI-04 |

Create these **before** staff try to log day-to-day data that depends on them.

---

## 9. Academic Excellence (AE-01 … AE-08)

### 9.1 AE-01 — Curriculum Coverage Rate

| | |
|---|---|
| **Target** | 100% |
| **Frequency** | Fortnightly |
| **Owner (metadata)** | Subject Lead |
| **Screens** | `/coverage-logs` |

**What it measures:** Topics evidenced as taught ÷ planned topics in the approved scheme of work.

**Teacher steps**

1. Ensure your **lesson plan** for the topic is **approved** (AE-05).
2. Go to **Coverage Logs** → create.
3. Select class, subject, topic, and note workbook evidence.
4. Submit.

**HoD / HoS steps**

1. Open **Verification Queue** on the HoD dashboard (or Coverage Logs list).
2. **Verify** accepted evidence (topic becomes covered; AE-01 recalculates) or **Reject** with a reason.

### 9.2 AE-02 — School-wide Examination Pass Rate

| | |
|---|---|
| **Target** | 90% |
| **Frequency** | Termly |
| **Owner** | Head of Department |
| **Screens** | `/exam-results`, marksheet `/exam-results/marksheet` |

**What it measures:** Learners scoring ≥ pass mark (default **50%**) ÷ **total enrolled** (not only those with a result row).

**Steps**

1. Open **Exam Results**.
2. Choose session, term, class, subject.
3. Open the **marksheet** and enter scores.
4. Save — school-wide pass rate updates for the term.

### 9.3 AE-03 — Homework Completion Rate

| | |
|---|---|
| **Target** | 95% |
| **Frequency** | Weekly |
| **Owner** | Teacher |
| **Screens** | `/homework` |

**Steps (Teacher)**

1. Open **Homework**.
2. Create a log for the class/subject/week: assignments **given** and completed **on time**.
3. Save.

### 9.4 AE-04 — Learner Attendance Rate

| | |
|---|---|
| **Target** | 95% |
| **Frequency** | Weekly |
| **Owner** | Admin Officer |
| **Screens** | `/attendance` |

**Steps (Admin Officer / authorised staff)**

1. Ensure the **class roll** is up to date under **Learners**.
2. Open **Attendance** → create a register for the class and date.
3. Mark present / absent (and related statuses as provided).
4. Save.

Officers also get an **Attendance Week** summary on their dashboard.

### 9.5 AE-05 — Lesson Plan Submission & Approval

| | |
|---|---|
| **Target** | 100% |
| **Frequency** | Weekly |
| **Owner** | Head of Department |
| **Screens** | `/lesson-plans` |

**Teacher steps**

1. Open **Lesson Plans** → create for the topic / week.
2. Complete objectives, activities, assessment, resources.
3. Submit **on time** (before the school planning-policy due day; default Thursday).

**HoD / HoS steps**

1. Open pending plans from the dashboard queue or Lesson Plans list.
2. Complete the **quality checklist** (alignment, quality, engagement, assessment).
3. **Approve** only when every item passes, or **Return** with a reason if any item fails.
4. Decide within **24 hours** of submission (AE-05.3).

Approved plans unlock coverage logging for AE-01.

### 9.6 AE-06 — Effective or Better Lesson Observations

| | |
|---|---|
| **Target** | 90% |
| **Frequency** | Termly |
| **Owner** | Head of School |
| **Screens** | `/observations` |

**What it measures:** Observed lessons scoring **Secure (3) or better** across the **12** instructional standards ÷ lessons observed.

**Steps (HoS / HoD / Admin)**

1. Open **Observations** → create.
2. Select teacher, class, subject, date.
3. Score each of the 12 standards.
4. Save.

### 9.7 AE-07 — At-Risk Learners with Active Plan

| | |
|---|---|
| **Target** | 100% |
| **Frequency** | Monthly |
| **Owner** | Learning Support Coordinator |
| **Screens** | `/at-risk`, intervention plans |

**Steps**

1. Open **At-Risk Learners**.
2. Identify a learner (manually or from exam-driven prompts where available).
3. Create an **Intervention Plan** (Tier 2/3) and keep it active.
4. KPI = learners with an active plan ÷ identified at-risk learners.

Learning Support dashboard highlights below-pass learners still unflagged.

### 9.8 AE-08 — Reading Progress (≥1 Year Growth)

| | |
|---|---|
| **Target** | 85% |
| **Frequency** | Termly |
| **Owner** | Literacy Coordinator |
| **Screens** | `/reading`, `/reading/record` |

**What it measures:** Learners achieving ≥ **1.0** grade-level increase ÷ learners assessed.

**Steps**

1. Open **Reading Progress**.
2. Record baseline and follow-up grade-level scores for learners.
3. Save — growth is calculated for the KPI.

---

## 10. Christocentric Education (CE-01 … CE-07)

### 10.1 CE-01 — Daily Devotion & Chapel Attendance

| | |
|---|---|
| **Target** | 95% |
| **Frequency** | Daily |
| **Owner** | Chaplain |
| **Screens** | `/chapel` |

**Admin setup first:** `/admin/chapel-activity-types` and `/admin/chapel-sessions`.

**Roll steps**

1. Open **Chapel & Assembly**.
2. Select the scheduled session.
3. Open the **roll** and mark participation against the school roll.
4. Save.

### 10.2 CE-02 — Christian Character Rating (Secure+)

| | |
|---|---|
| **Target** | 85% |
| **Frequency** | Termly |
| **Owner (metadata)** | Chaplain |
| **Primary UI users** | Student Life Coordinator, teachers, HoS, Admin |
| **Screens** | `/character`, `/character/rate` |

**Admin setup:** character domains at `/admin/character-domains` (seven biblical domains).

**Steps**

1. Open **Character Development**.
2. Rate learners on each active domain (Secure / Exemplary levels count toward the KPI).
3. Save for the term.

### 10.3 CE-03 — Community Service Hours per Learner

| | |
|---|---|
| **Target** | **10 hours** per learner |
| **Frequency** | Termly |
| **Owner** | Student Life Coordinator |
| **Screens** | `/service`, `/service/log` |

**Steps**

1. Admin configures **service activity types**.
2. Log service activity and hours for learners.
3. **Verify** the log (SLC / HoS / Admin).  
   Only **verified** hours count in the KPI (total verified hours ÷ student roll).

### 10.4 CE-04 — Resolved Restorative Discipline Cases

| | |
|---|---|
| **Target** | 90% |
| **Frequency** | Monthly |
| **Owner** | Head of School |
| **Screens** | `/discipline` |

**Steps**

1. Configure incident types under Admin if needed.
2. Log a behaviour incident.
3. Work the restorative process to **completed**.
4. KPI = completed restorative agreements ÷ incidents logged.

### 10.5 CE-05 — Bullying Case Resolution Rate

| | |
|---|---|
| **Target** | 100% |
| **Frequency** | Monthly |
| **Owner** | Chaplain |
| **Screens** | `/bullying` |

**Steps**

1. Log a bullying / cyberbullying case.
2. Investigate and attach a **safety plan**.
3. Close the case when resolved.
4. KPI = closed cases with safety plan ÷ reported cases.

### 10.6 CE-06 — Scripture Memory & Application Mastery

| | |
|---|---|
| **Target** | 85% |
| **Frequency** | Termly |
| **Owner** | Chaplain |
| **Screens** | `/scripture`, `/scripture/assess` |

**Admin setup:** passages at `/admin/scripture-passages`.

**Steps**

1. Open **Scripture Mastery**.
2. Assess learners on assigned passages (recitation + contextual application).
3. Save — mastery rate updates for the term.

### 10.7 CE-07 — Parent–School Christian Culture Alignment

| | |
|---|---|
| **Target** | 85% |
| **Frequency** | Termly |
| **Owner** | Parent Relations Lead |
| **Screens** | `/partnership`, `/partnership/record` |

**Admin setup**

1. Create / activate a **Partnership Charter** (`/admin/partnership-charters`).
2. Maintain the **Parent Registry** (`/admin/guardians`) and link parents to learners.

**PRL steps**

1. Open **Parent Partnership**.
2. Record signed commitments against the active charter and parent body.
3. KPI = signed commitments ÷ active parent body.

---

## 11. Digital Innovation (DI-01 … DI-06)

### 11.1 DI-01 — Digital Portal & LMS Adoption Rate

| | |
|---|---|
| **Target** | 90% |
| **Frequency** | Weekly |
| **Owner** | IT Consultant |
| **Screens** | `/lms`, `/lms/record` |

**Steps**

1. Open **LMS Adoption**.
2. Record the week’s active LMS users (staff + learners).
3. Save — rate = active users ÷ total staff + students.

### 11.2 DI-02 — Coding & STEM Project Completion

| | |
|---|---|
| **Target** | 85% |
| **Frequency** | Termly |
| **Owner** | STEM Coordinator |
| **Screens** | `/stem`, `/stem/record` |

**Admin setup:** STEM project types.

**Steps**

1. Open **STEM Projects**.
2. Mark learners who completed an approved project type for the term.
3. Only **completed** rows count.

### 11.3 DI-03 — AI & Digital Ethics Compliance

| | |
|---|---|
| **Target** | 95% |
| **Frequency** | Termly |
| **Owner** | IT Consultant |
| **Screens** | `/ethics` |

**Steps**

1. Configure audit types under Admin.
2. Log audits of assignments for integrity / AI disclosure / cyber guidelines.
3. KPI uses audited items free of uncredited violations ÷ total audited.

### 11.4 DI-04 — Staff Digital Competency Mastery

| | |
|---|---|
| **Target** | 85% |
| **Frequency** | Termly |
| **Owner** | Head of School |
| **Screens** | `/competency`, `/competency/record` |

**Admin setup:** competency areas (staff must reach Level **3+** on **all** active areas).

**Steps**

1. Open **Staff Digital Competency**.
2. Rate each staff member per area for the term.
3. KPI = staff at Level 3+ on every active area ÷ active staff.

### 11.5 DI-05 — E-Portfolio & Digital Assessment Usage

| | |
|---|---|
| **Target** | 80% |
| **Frequency** | Termly |
| **Owner** | Head of School |
| **Screens** | `/eassessment`, `/eassessment/record` |

**Steps**

1. Open **E-Assessment Usage**.
2. For each subject, flag e-assessment and/or e-portfolio use.
3. KPI = subjects using those tools ÷ active subjects offered.

### 11.6 DI-06 — Parent Portal Engagement Rate

| | |
|---|---|
| **Target** | 80% |
| **Frequency** | Monthly |
| **Owner** | IT Consultant |
| **Screens** | `/portal-engagement`, `/portal-engagement/record` |

**Steps**

1. Open **Parent Portal Engagement**.
2. Record unique active parent logins for the month.
3. KPI = unique active family logins ÷ enrolled families (guardians linked to enrolled learners).

---

## 12. Role-by-role quick guides

### 12.1 Board

1. Log in → Executive Dashboard.  
2. Check session, health, and off-track counts.  
3. Drill pillars; note owners for follow-up.  
4. Optionally adjust **Status Thresholds**.  
5. You do **not** enter classroom registers.

### 12.2 Head of School

1. Use Executive Dashboard for strategy reviews.  
2. Conduct / review **Observations** (AE-06).  
3. Oversee discipline (CE-04), competency (DI-04), e-assessment (DI-05).  
4. Help verify coverage and lesson plans when needed.  
5. Use Status Thresholds with Board agreement.

### 12.3 Head of Department

1. Start on the **Verification Queue** dashboard.  
2. Review lesson plans against the quality checklist, then approve or return; verify coverage logs.  
3. Drive exam marksheets (AE-02).  
4. Support homework, attendance visibility, observations as authorised.

### 12.4 Teacher

1. Submit **lesson plans** early each week.  
2. After approval, log **coverage** with evidence.  
3. Log **homework** completion.  
4. Help with attendance, exams, character, STEM when asked.

### 12.5 Admin Officer

1. Keep **class rolls** accurate.  
2. Enter daily **attendance**.  
3. Assist with **chapel** rolls when required.

### 12.6 Learning Support Coordinator

1. Monitor below-pass / flagged learners.  
2. Maintain at-risk records and **active intervention plans**.

### 12.7 Literacy Coordinator

1. Run reading assessments for the term.  
2. Ensure baseline and follow-up scores are complete.

### 12.8 Chaplain

1. Take **chapel** rolls for held sessions.  
2. Manage **bullying** cases to closed + safety plan.  
3. Run **scripture** assessments.  
4. Open **Christcentric Education** in the sidebar for the full v2 sub-activity list.

### 12.9 Student Life Coordinator

1. Rate **character** domains each term.  
2. Log and **verify** community service hours.

### 12.10 Parent Relations Lead

1. Work from **Parent Partnership** home.  
2. Record charter signatures.  
3. Coordinate with Admin on guardian registry completeness.

### 12.11 ICT Coordinator

1. Weekly **LMS** marksheet.  
2. Termly **ethics** audits.  
3. Support competency / e-assessment entry with HoS.  
4. Monthly **parent portal engagement**.

### 12.12 STEM Coordinator

1. Work from **STEM Projects** home.  
2. Record completed projects for the term.

### 12.13 System Admin

1. Control Panel (name/logo).  
2. Sessions, terms, classes, subjects, users, assignments.  
3. All look-up catalogues.  
4. KPI Settings metadata.  
5. Help others when permissions or master data are missing.

---

## 13. Academic sessions and terms

### 13.1 Current session (school-wide)

Admin marks one session as **current** under **Academic Sessions**. Day-to-day create forms usually default to that session.

### 13.2 Viewing another session

On the Executive Dashboard (and many lists), use the **Session** dropdown. This changes what you **view** without necessarily changing the school’s official current session.

### 13.3 Terms

Terms belong to a session and carry start/end dates used for weekly windows, lesson-plan dues, and termly KPIs. Keep dates accurate.

---

## 14. My Profile

**Path:** `/profile` (every signed-in user)

You can:

- Update your display name and contact details (as allowed)
- Change password (when enabled on the form)
- Upload or remove a **passport photograph** (JPG/PNG/WebP, max 2 MB)

Your photo appears in the sidebar avatar area so colleagues recognise you in queues and assignments.

---

## 15. Troubleshooting & FAQ

### “I logged in but don’t see the module I need”

- Confirm you are using the correct **role account**.  
- Some roles (e.g. Chaplain) may need to open modules by path if the sidebar group is limited — ask Admin.  
- Admin must create look-up data (types, domains, passages) before forms work.

### “KPI didn’t move after I saved”

- Check **session** and **term**.  
- For AE-01: coverage must be **verified**; lesson plan must be **approved**.  
- For CE-03: service hours must be **verified**.  
- For DI-02: status must be **completed**.  
- Wait a moment and refresh; or ask Admin to run recalculation (see Appendix §17).

### “Executive Dashboard looks worse than the Excel sample”

Operational demo data often overwrites illustrative Excel sample Actuals. That is expected in a live training DB. Board sample parity can be restored by technical staff with `php artisan wisca:excel-parity --apply` (training environments only).

### “Logo or school name wrong”

Admin → **Control Panel** → update name/logo → save. Refresh the page.

### “Cannot upload logo / photo”

- Max **2 MB**  
- Use JPG, PNG, or WebP  
- Ask Admin to confirm the public storage link is enabled on the server (`php artisan storage:link`)
- On some shared hosts, PHP’s **fileinfo** extension is disabled. WISCA falls back automatically, but enabling `extension=fileinfo` in the host’s PHP settings is still recommended for best MIME detection.

### “Wrong pass mark for exams”

Admin → **KPI Settings** → AE-02 → edit **pass mark** (percent).

### “Status labels seem too harsh / too soft”

Board/HoS/Admin → **Status Thresholds** → adjust the three scales carefully and document the Board decision.

### “Session filter shows nothing”

Create or activate an academic session and at least one term under Admin.

---

## 16. Appendix — KPI reference table

| Code | Metric | Target | Frequency | Typical UI path |
|---|---|---|---|---|
| AE-01 | Curriculum Coverage Rate | 100% | Fortnightly | `/coverage-logs` |
| AE-02 | School-wide Examination Pass Rate | 90% | Termly | `/exam-results` |
| AE-03 | Homework Completion Rate | 95% | Weekly | `/homework` |
| AE-04 | Learner Attendance Rate | 95% | Weekly | `/attendance` |
| AE-05 | Lesson Plan Submission & Approval | 100% | Weekly | `/lesson-plans` |
| AE-06 | Effective or Better Lesson Observations | 90% | Termly | `/observations` |
| AE-07 | At-Risk Learners with Active Plan | 100% | Monthly | `/at-risk` |
| AE-08 | Reading Progress (≥1 Year Growth) | 85% | Termly | `/reading` |
| CE-01 | Daily Devotion & Chapel Attendance | 95% | Daily | `/chapel` |
| CE-02 | Christian Character Rating (Secure+) | 85% | Termly | `/character` |
| CE-03 | Community Service Hours per Learner | 10 hrs | Termly | `/service` |
| CE-04 | Resolved Restorative Discipline Cases | 90% | Monthly | `/discipline` |
| CE-05 | Bullying Case Resolution Rate | 100% | Monthly | `/bullying` |
| CE-06 | Scripture Memory & Application Mastery | 85% | Termly | `/scripture` |
| CE-07 | Parent–School Christian Culture Alignment | 85% | Termly | `/partnership` |
| DI-01 | Digital Portal & LMS Adoption Rate | 90% | Weekly | `/lms` |
| DI-02 | Coding & STEM Project Completion | 85% | Termly | `/stem` |
| DI-03 | AI & Digital Ethics Compliance | 95% | Termly | `/ethics` |
| DI-04 | Staff Digital Competency Mastery | 85% | Termly | `/competency` |
| DI-05 | E-Portfolio & Digital Assessment Usage | 80% | Termly | `/eassessment` |
| DI-06 | Parent Portal Engagement Rate | 80% | Monthly | `/portal-engagement` |

---

## 17. Appendix — Technical operations (optional)

For System Admin / IT hosting the app (not required for everyday teaching staff).

### 17.1 Recalculate KPIs manually

```bash
php artisan wisca:recalculate-kpis all
php artisan wisca:recalculate-kpis weekly
php artisan wisca:recalculate-kpis termly --school=1
```

Frequencies: `daily`, `weekly`, `fortnightly`, `monthly`, `termly`, `all`, or a KPI code such as `AE-01`.

### 17.2 Board Excel sample parity (training)

```bash
php artisan wisca:excel-parity --apply
php artisan wisca:excel-parity
```

Use only when you intentionally want Excel sample Actuals for acceptance testing. Live operational data will diverge again after normal entry or scheduled recalculation.

### 17.3 Scheduled jobs

When the server cron runs Laravel’s scheduler (`php artisan schedule:work` or system cron calling `schedule:run`):

| Cadence | Recalculates |
|---|---|
| Daily 06:00 | Daily KPIs (CE-01) |
| Mondays 06:15 | Weekly KPIs |
| Mondays 06:30 | Fortnightly (AE-01) |
| 1st of month 06:45 | Monthly KPIs |
| Daily 07:00 | Termly KPIs |

### 17.4 File uploads on shared hosting

Logo, hero image, passport photo, and lesson-plan uploads use Laravel’s public disk. Ensure:

1. `php artisan storage:link` has been run so `public/storage` points at `storage/app/public`.
2. The web server can write to `storage/` and `bootstrap/cache/`.

If uploads fail with **Class "finfo" not found**, the host has disabled PHP’s **fileinfo** extension. WISCA ships a fallback (extension-based MIME detection). Deploy the latest code, then run `php artisan config:clear`. Ask the host to enable **fileinfo** when possible.

### 17.5 Related documents in this repo

| File | Purpose |
|---|---|
| `chambers/WISCA-Excel-to-App-Rules.md` | Locked Board rules (formulas, scales, denominators) |
| `chambers/WISCA.md` | Broader engineering architecture notes |
| `docs/WISCA-User-Manual.md` | **This user manual** |

---

## Quick start checklist (new term)

1. Admin marks the new **session** current and creates **terms**.  
2. Confirm classes, subjects, assignments, and learner rolls.  
3. Refresh CE/DI catalogues (chapel sessions, passages, charters, STEM types, etc.).  
4. Teachers submit week-1 **lesson plans**; HoD approves.  
5. Officers start **attendance**; Chaplain schedules **chapel**.  
6. Board opens Executive Dashboard at the first strategy review and confirms session + health labels.

---

*End of manual. For strategy definitions that must never drift, always cross-check `chambers/WISCA-Excel-to-App-Rules.md`.*
