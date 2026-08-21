# WISCA — Excel-to-App Rules (Locked)

**School:** Wisdom Christian Academy, Makurdi  
**Source of truth:** `chambers/WISCA-Strategy.xlsx` → `Master KPI Data`  
**Purpose:** One-page contract so the Laravel app reproduces Board numbers and labels exactly.

---

## 1. What the Excel defines vs what the app adds

| From Excel (must match) | App addition (optional layer) |
|---|---|
| 21 KPIs across 3 pillars | Auth, roles, dashboards, audit trail |
| Targets, formulas, frequencies, owners | Data entry screens / imports |
| Achievement = **Actual ÷ Target** | Scheduled recalculation jobs |
| Three status scales (below) | Verification / escalation for **AE-01 & AE-05 only** (workflow, not in Excel) |

**Rule:** If app output disagrees with the Excel, the Excel wins unless the Proprietor/Board explicitly changes a rule in the Admin UI.

---

## 2. Pillars & KPI count

| Pillar | Code prefix | KPIs |
|---|---|---|
| Academic Excellence | AE-01 … AE-08 | 8 |
| Christocentric Education | CE-01 … CE-07 | 7 |
| Digital Innovation | DI-01 … DI-06 | 6 |
| **Total** | | **21** |

**Pillar aggregation:** Unweighted average of member KPI achievement rates (same as Excel `AVERAGE`). No 40% pillar weight unless Board approves a change.

**School-wide score:** Unweighted average of all 21 KPI achievement rates.

---

## 3. Three status scales (do not merge)

### A. KPI status (`kpi_periodic_data.status`)

Applied per KPI row after `achievement_rate = actual_value / target_value`.

| Condition | Label |
|---|---|
| achievement ≥ 1.0 | **ON TRACK** |
| achievement ≥ 0.90 | **NEEDS ATTENTION** |
| achievement < 0.90 | **OFF TRACK** |

> Note: Beating target still shows **ON TRACK**, not “Exceeding”, at KPI level.

### B. Pillar headline (Executive / pillar summary cards)

Uses **average achievement** of KPIs in that pillar.

| Condition | Label |
|---|---|
| avg ≥ 1.0 | **EXCEEDING** |
| avg ≥ 0.90 | **ON TRACK** |
| avg < 0.90 | **NEEDS ATTENTION** |

### C. Overall health (Executive summary table)

Uses **average achievement** (pillar row or school-wide total).

| Condition | Label |
|---|---|
| avg ≥ 0.95 | **HEALTHY** |
| avg ≥ 0.90 | **SATISFACTORY** |
| avg < 0.90 | **CRITICAL** |

**Implementation:** Store thresholds in `settings` or pillar `config` JSON. All three scales must remain independently configurable.

---

## 4. Achievement & target types

```
achievement_rate = actual_value / target_value   (if target_value > 0)
```

| KPI | `target_type` | Target | Actual meaning |
|---|---|---|---|
| AE-01 … AE-08 (except where noted) | percentage | 0–1 decimal | 0–1 decimal |
| CE-03 | **hours** | 10 | Total verified service hours **÷ student roll** |
| All others default | percentage | per Excel | per Excel |

Display percentages in UI; store as decimals (e.g. `0.95` not `95`).

---

## 5. Formula denominators (locked — overrides markdown spec)

| Code | Numerator | Denominator |
|---|---|---|
| **AE-01** | Topics completed (evidenced vs scheme) | Planned topics in approved termly scheme |
| **AE-02** | Students scoring ≥ **50%** in subject | **Total enrolled** (not result-row count) |
| **AE-03** | Assignments completed **on time** | Total assignments **given** |
| **AE-04** | Days present | Total **instructional** school days |
| **AE-05** | Approved lesson plans submitted **on time** (before Monday) | Total **required** for the week |
| **AE-06** | Lessons scored ≥ **3** Secure on **12** standards | Total lessons observed |
| **AE-07** | At-risk students with **active** Tier 2/3 plan | Total identified at-risk (below 50% or “Concern”) |
| **AE-08** | Students with ≥ **1.0** grade-level reading growth | Total assessed |
| **CE-01** | Students present **and participating** | Total school roll |
| **CE-02** | Students rated 3 Secure or 4 Exemplary | Total enrolled (7 character domains) |
| **CE-03** | Total verified service hours | Total student roll |
| **CE-04** | Incidents with completed restorative agreement | Total incidents logged |
| **CE-05** | Bullying cases closed with safety plan | Total reported |
| **CE-06** | Students reciting **and** contextually explaining verses | Total assessed |
| **CE-07** | Signed parent partnership commitments | Total parent body |
| **DI-01** | Active **weekly** LMS users (staff + students) | Total staff + students |
| **DI-02** | Students completing approved STEM project | Total enrolled |
| **DI-03** | Audited assignments free of AI/tech violations | Total audited |
| **DI-04** | Staff at digital competence **Level 3+** | Total staff |
| **DI-05** | Subjects using e-assessment / e-portfolio | Total subjects offered |
| **DI-06** | Unique active parent portal logins | Total enrolled families |

Pass mark **50%**, observation pass **≥3**, reading growth **≥1.0 grade level** — store in KPI `config`, not hardcoded.

---

## 6. AE-01 evidence rule (Excel-aligned)

**Excel says:** Fortnightly audit of taught topics in **student workbooks** against approved scheme (Appendix C).

**App rule:** A topic counts as “completed” only when coverage is evidenced against learner work / Appendix C tracker — not by teacher photo alone. Optional photos are supplementary; workbook/topic evidence is primary.

---

## 7. Roles & owners (from Excel)

Minimum app roles must allow data entry by KPI owner. Map Excel owners → app roles:

| Excel owner | App role (proposed) |
|---|---|
| Subject Leads / HoS | `subject_lead`, `head_of_school` |
| HoDs / AHS | `head_of_department`, `assistant_head_secondary` |
| Class Teachers | `teacher` |
| Admin Officer | `admin_officer` |
| Learning Support Coordinator | `learning_support_coordinator` |
| Literacy Coordinator | `literacy_coordinator` |
| Chaplain | `chaplain` |
| Student Life Coordinator | `student_life_coordinator` |
| Parent Relations Lead | `parent_relations_lead` |
| IT Consultant | `it_consultant` |
| STEM Coordinator | `stem_coordinator` |
| Board / Proprietor | `board` (read + configure) |

**Rule:** Each KPI stores `owner_role` (and optional `verifier_role`). Dashboards filter by role; Board sees Executive view only.

---

## 8. Frequencies (recalculation schedule)

| Frequency | KPIs |
|---|---|
| Daily | CE-01 |
| Weekly | AE-03, AE-04, AE-05, DI-01 |
| Fortnightly | AE-01 |
| Monthly | AE-07, CE-04, CE-05, DI-06 |
| Termly | All others |

Cron jobs recalculate on this cadence; manual override allowed for admins.

---

## 9. Data collection instruments → app modules

| Appendix / instrument | Module |
|---|---|
| Appendix A — Weekly Lesson Plan Tracker | Lesson plans (AE-05) |
| Appendix B — Classroom Observation Checklist | Observations, 12 standards (AE-06) |
| Appendix C — Curriculum Coverage Tracker | Schemes, topics, workbook evidence (AE-01) |
| Appendix F — Learner Academic Intervention Plan | At-risk + intervention plans (AE-07) |
| Appendix G — Behaviour Incident & Restorative Record | Restorative discipline (CE-04) |
| Appendix H — Anti-Bullying Case Management | Bullying cases (CE-05) |
| Appendix I — Character Development Rubric | Character domains, 7 areas (CE-02) |
| Appendix K — Parent-School Partnership Charter | Parent commitments (CE-07) |
| Digital Attendance Register | Attendance (AE-04) |
| LMS / Portal analytics | LMS usage logs (DI-01, DI-05, DI-06) |
| Termly Broad Sheet | Exam results (AE-02) |
| Literacy Assessment Battery | Reading assessments (AE-08) |

---

## 10. Build order (aligned to Excel)

1. **Seed** all 21 KPIs + 3 pillars from `Master KPI Data` (targets, formulas text, owners, frequencies).
2. **Implement** three status scales + unweighted aggregation.
3. **Phase 1:** AE-01 end-to-end (scheme → topics → workbook evidence → fortnightly AE-01).
4. **Phase 2:** AE-03, AE-04, AE-05 (weekly operational KPIs).
5. **Phase 3:** Remaining AE, then CE, then DI — one KPI at a time with correct denominator.
6. **Executive Dashboard** last: must match Excel Executive sheet formulas exactly.

---

## 11. Acceptance test (Board parity)

For the sample period in the Excel file, after seeding the same Actual values:

- Each KPI `achievement_rate` and **KPI status** matches `Master KPI Data` column I & J.
- Pillar averages and **EXCEEDING / ON TRACK** labels match pillar summary rows.
- Executive **HEALTHY / SATISFACTORY / CRITICAL** and On Track / Needs Attention counts match row 11–14.

If parity fails, fix the app — not the Excel.

---

*Locked: Aug 2026 | Derived from WISCA-Strategy.xlsx | Supersedes informal assumptions in WISCA.md where they conflict.*
