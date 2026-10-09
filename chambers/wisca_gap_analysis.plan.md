---
name: WISCA gap analysis
overview: Read-only Functional and Business Logic Gap Analysis of the Harmonised Framework (WISCA_v2.xlsx) against the live app. Authoritative count is 23 activities and 72 sub-activities. Stop after the report. No app code changes.
todos:
  - id: verify-framework
    content: Verify WISCA_v2.xlsx (Harmonised Framework) against WiscaOperationalCatalog.php; list any column-level differences; use the xlsx as authority
    status: pending
  - id: inventory-modules
    content: Inspect each activity module against all 72 Harmonised Framework sub-activities (process, not page-exists)
    status: pending
  - id: write-gap-report
    content: Write only chambers/WISCA-Functional-Gap-Analysis.md as the v2 functional blueprint (gaps, reuse, enhancements, impact, problem types, options)
    status: pending
  - id: stop-after-report
    content: Stop. Do not implement or auto-fix any gap.
    status: pending
---

# Functional gap analysis (all three pillars)

This is the project copy of the analysis plan. It incorporates the 23 Sep 2026 clarifications, including implementation-impact discipline.

This file is the **plan**, not the finished gap report. The report (when written) will be [WISCA-Functional-Gap-Analysis.md](WISCA-Functional-Gap-Analysis.md).

## Constraints (unchanged)

- **Read-only.** The only file that may be created or updated during execution is [WISCA-Functional-Gap-Analysis.md](WISCA-Functional-Gap-Analysis.md).
- Do not modify code, database, migrations, routes, controllers, models, views, UI, permissions, or configuration.
- Present **options and implications**, not a selected solution.
- **Stop after the report.** No implementation.

## 1. Authoritative reference

The Harmonised Framework is:

`C:\Users\NCC\Desktop\projects\wisca\chambers\WISCA_v2.xlsx`

(Confirmed: there is no other `WISCA_v2` file in `chambers`. The workbook title is “WISDOM CHRISTIAN ACADEMY (WISCA) - INTEGRATED OPERATIONAL FRAMEWORK” / “Harmonised Group 2 & Group 3 Integrated Activities…”. Sheets: Academic Excellence, Christcentric Education, Digital Innovation.)

[app/Support/WiscaOperationalCatalog.php](../app/Support/WiscaOperationalCatalog.php) is a **navigation encoding**, not the source of truth.

**First execution step:** compare catalog vs workbook on every column:

- Pillars
- Activities
- Measurable Sub-Activities
- KPI / Measure (full workbook wording — the catalog often stores only target/frequency/evidence, not the KPI sentence)
- Targets
- Frequency
- Evidence / Verification
- Responsible roles

Do not silently substitute the catalog for the workbook. If they differ, **state the difference in the report** and analyse against the workbook.

## 2. Baseline count (verified against the xlsx)

Workbook data rows (excluding titles/headers):

- Academic Excellence — 10 activities, **33** sub-activities
- Christcentric Education — 7 activities, **21** sub-activities
- Digital Innovation — 6 activities, **18** sub-activities
- **Total: 23 activities, 72 measurable sub-activities**

The earlier figure of **67 was wrong**. Activity names and per-activity sub counts in the catalog match the workbook (5+4+3×8 AE; 3×7 CE; 3×6 DI). The analysis will cover **all 72**.

Known items to confirm in the verification section (not assumed resolved):

- Workbook **KPI / Measure** text is richer than catalog fields
- AE-01 last target is `0.95` in the sheet vs `95%` in the catalog — **owner: treat as 95%**
- AE-04 “Monitor attendance patterns…” has a **blank Responsibility** in the sheet — **owner: Teacher**
- Role strings in the sheet (Teachers, Class Teacher, HODs, HODS, HoS/AHS/HODs, AHS/HOS, ICT Coordinator, Admin Manager) vs Spatie keys in the catalog
- Catalog `extra_roles` (Subject Lead, Admin Officer, etc.) are **not** in the workbook Responsibility column

## 3. What each sub-activity entry must contain

Rate whether the **intended business process can be executed end to end**. A table or page is not enough.

For **every** measurable sub-activity:

- Prerequisites
- Required master data (and who should own/configure it centrally)
- Required documents/records
- Role that creates each prerequisite
- Dependencies on other activities
- Approval/review dependencies
- Downstream processes that depend on the outcome
- Responsible role (from the workbook)
- Required workflow
- Evidence / verification (as a real capture path, not a caption)
- KPI / target (workbook wording)
- **KPI classification** (exactly one):
  - Directly calculable from existing data
  - Calculable after missing data/workflows are implemented
  - Requires clarification of measurement methodology
  - Currently impossible to calculate
- Current implementation (what the app actually does)
- **A–D rating** (process completeness, not UI existence)
- Gap (what the framework requires vs what exists; why it matters)
- **Problem type(s)** — one or more of: missing master data; missing data capture; missing workflow; missing approval/review; missing evidence/verification; missing KPI calculation; KPI methodology ambiguity; missing role/permission; missing dependency/integration; missing UI/navigation
- **Implementation-impact split** (see section 3a)
- Affected roles
- Affected UI/modules
- Potential technical impact (extend existing structures / new structures / new relationships / workflow-state / new services / new permissions / new UI / configuration-or-master-data only)
- Implementation options (two or more where a gap exists, with implications — no chosen solution)

Do not invent KPI formulas when the framework or code is insufficient. Classify those as “requires clarification.”

## 3a. Implementation-impact discipline

The report is the **functional blueprint for WISCA v2**, not a defect list. For every identified gap, distinguish clearly:

1. **Business/functional gap** — what the Harmonised Framework requires that the application cannot currently execute.
2. **Existing capability that can be reused** — tables, models, workflows, services, evidence/document mechanisms, approvals, or UI that already support part of the requirement.
3. **Required functional enhancement** — what capability must be added or changed.
4. **Potential technical impact** — whether that enhancement appears to need:
   - existing database structures extended,
   - new database structures,
   - new relationships,
   - workflow/state changes,
   - new services/calculations,
   - new permissions,
   - new UI/forms,
   - or only configuration/master-data changes.

Do not assume every functional gap needs a new database table.

Also classify the primary problem type(s) listed in section 3. A gap may be a combination.

## 4. Master data (explicit section)

The report will include a dedicated **Master Data** inventory: items that must be centrally configured and controlled before dependent work can happen (examples to verify, not a closed list): session/term/class/subject; approved scheme of work; teacher assignments; learners; incident types; STEM inventory; portal/LMS accounts; parent accounts; scripture syllabus; character rubric; observation checklist; training catalogues.

A seeded row is not a controlled master-data process.

## 5. A–D ratings

- **A** Fully implemented — the process can be executed end to end
- **B** Partially implemented — some capture exists; workflow, evidence, approval, master data, or KPI is incomplete
- **C** UI-only / placeholder — menu, tab, or coming-soon without an adequate process
- **D** Completely missing

## 6. Report structure

File: [WISCA-Functional-Gap-Analysis.md](WISCA-Functional-Gap-Analysis.md)

1. Framework verification (xlsx vs catalog) and the 23 / 72 count
2. Summary — A–D counts by pillar; highest-impact gaps
3. Master data inventory
4. Cross-cutting patterns
5. Full inventory — all 72 sub-activities with the fields in sections 3 and 3a
6. Menu/tab impact
7. Options register (no selected solution; reuse vs enhance vs new structure called out)

Then stop.
