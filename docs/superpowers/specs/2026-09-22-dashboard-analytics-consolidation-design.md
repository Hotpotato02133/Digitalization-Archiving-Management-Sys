# Dashboard Analytics Consolidation Design

**Date:** 2026-09-22  
**Status:** Awaiting written-spec review  
**Scope:** Administrator dashboard, analytics API, report export, and Analytics page retirement

## Objective

Replace the separate Analytics page with one administrator Dashboard that combines archive operations and decision-useful analytics. Every displayed value must come from the database or a clearly identified database-derived calculation. Duplicate, hard-coded, and misleading indicators will be removed.

The dashboard should let an administrator answer four questions immediately:

1. How many records are safely archived?
2. How much of the active student population has at least one archived record?
3. How much archiving work was completed this week?
4. How many archived records are searchable through completed OCR?

## Approved Primary KPIs

### Archived records

- Definition: count of `documents` rows where `status = 'Archived'`.
- Current verified value: 5.
- This replaces both “Total records” and “Total Documents.”

### Student archive coverage

- Numerator: distinct active, non-deleted students with at least one archived document.
- Denominator: all active or graduated, non-deleted students; students whose status is `Inactive` are excluded.
- Display: `covered / eligible` plus the calculated percentage, for example `3 of 7 · 42.9%`.
- Label must say “Students with an archived record,” not “digitization completion.”

### Archived this week

- Definition: archived documents whose `archived_at` falls between the start of the current calendar week and the present.
- Comparison: absolute change from the preceding calendar week.
- When the preceding week is zero, show an absolute comparison such as `+2 vs last week`; do not display an undefined percentage.
- The calculation must use `archived_at`, not `uploaded_at`.

### Search-ready records

- Numerator: archived documents where `ocr_status = 'Completed'`.
- Denominator: all archived documents.
- Display: `completed / archived` plus the calculated percentage.
- Show pending and failed OCR counts as supporting text when either is non-zero.

## Secondary Information

Archive storage will be shown as a secondary fact rather than a primary KPI. It will use `SUM(file_size_bytes)` for archived documents. The existing hard-coded 2.8 TB capacity and derived percentage will be removed because that capacity is not stored or verified by the application.

Active college, course, category, and user counts are configuration inventory. They will not occupy the primary KPI row. They may appear in the report or a compact system-inventory area if space permits, but they must not be presented as performance outcomes.

## Dashboard Layout

### Header and actions

Keep the greeting, live date/time, notifications, user menu, and Export Report action. The report action will export the same consolidated data shown on the Dashboard.

### Primary KPI row

Display the four approved cards in this order:

1. Archived records
2. Student archive coverage
3. Archived this week
4. Search-ready records

Each card has one primary value and at most one short supporting statement. Decorative mini charts that do not encode real values will be removed.

### Analytics row

The first panel is **Archive coverage by college**. For each active college, display:

- active/non-deleted student count;
- students with at least one archived document;
- coverage percentage.

A college with no eligible students displays `No registered students`, not `0%`. This panel replaces the current “Archive by college” document-share visualization.

The second panel is **Archiving throughput**. It displays archived document counts grouped by week for the most recent eight calendar weeks. Empty weeks are included as zero so the trend is honest and continuous.

### Records and operations row

The **Archive composition** panel displays archived document counts by active document category. Categories with zero archived documents remain visible so administrators can identify missing archive categories.

The **Records requiring attention** panel displays database-backed operational counts:

- archived documents with pending OCR;
- archived documents with failed OCR;
- archived documents without a file path;
- archived documents without a recorded file size.

Zero counts remain visible in a healthy/clear state. This panel replaces the hard-coded system-health percentage.

### Recent activity

Merge “Recently Archived” and “Activity” into one chronological feed. Each row shows:

- action;
- document or category context when available;
- student context when available;
- actor;
- timestamp;
- resulting status.

The feed is sourced from `activity_logs`, enriched with related document, student, and category data where the referenced entity still exists. Missing related entities must not prevent an activity row from rendering.

### Quick actions

Keep:

- Upload records
- Manage users
- Add document category

Remove the “Generate report / View archive analytics” link because Export Report remains in the page header.

## Metrics Removed or Redefined

- Remove duplicate “Total Documents.”
- Remove hard-coded “System health 99.98%.”
- Remove hard-coded “Digitization progress 92.4%.”
- Remove hard-coded “+8.2% since January 2026.”
- Remove “100% fully digitized,” which currently divides archived-document count by the same archived-document count.
- Remove primary cards for Total Colleges and Total Courses.
- Remove “new courses this year”; `courses.created_at` records when a course was added to DATS, not necessarily when the academic program was launched.
- Replace document-share “Archive by college” with student archive coverage by college.
- Replace hard-coded storage capacity percentage with archived file-size data.

## Backend and Data Contract

The analytics endpoint remains `api.php?resource=analytics`, but `get_analytics()` will return a consolidated, explicit contract:

```text
archived_documents
eligible_students
students_with_archived_documents
student_archive_coverage_rate
archived_this_week
archived_last_week
archived_week_change
ocr_completed_archived
ocr_pending_archived
ocr_failed_archived
search_ready_rate
archived_storage_bytes
missing_file_path_count
missing_file_size_count
coverage_by_college[]
archive_by_week[]
archive_by_category[]
```

Rates will be returned as numeric values or calculated consistently by one presentation layer; the implementation must not mix conflicting server and client formulas. Counts remain the source of truth so the UI and report can reproduce every percentage.

The existing fields needed by staff-facing pages will remain temporarily available if those pages consume the same endpoint. Removing legacy fields is allowed only after repository-wide usage confirms that no remaining page depends on them.

## Analytics Page Retirement

- Remove the Analytics item from all administrator navigation menus.
- Remove Dashboard links that point to the Analytics page.
- Keep `admin/analytics.php` as a lightweight server-side redirect to `admin/index.php` so bookmarks and old links do not break.
- Do not duplicate the new Dashboard markup in `analytics.php`.
- Update page descriptions and footer text to describe the consolidated archive Dashboard.

## Report Export

The Dashboard report will contain:

- report generation timestamp;
- the four primary KPI counts and rates;
- college coverage values;
- eight-week archive throughput;
- category distribution;
- attention counts;
- archive storage size.

The report must use the same API response as the visible Dashboard. No report-only hard-coded values are permitted.

## Empty, Error, and Loading States

- A database count of zero is a valid result and must not be shown as an error.
- Percentage denominators of zero produce `0%` except college rows with no eligible students, which show `No registered students`.
- Panels show a concise loading state while data is requested.
- If analytics loading fails, the page retains navigation and quick actions, shows an inline error message, and does not substitute placeholder numbers.
- A failed optional panel must not erase successfully loaded KPI data.

## Accessibility and Responsive Behavior

- KPI values and charts must remain understandable without color.
- Progress bars include visible text values and accessible labels.
- The weekly trend and category distribution receive text equivalents in exported reports.
- At tablet widths, the four KPI cards wrap to two columns; at mobile widths they use one column.
- Panels stack vertically without horizontal page scrolling.

## Verification

Automated tests will verify the aggregation rules against controlled fixtures, including:

- no students and no documents;
- students without documents;
- multiple documents for one student without double-counting coverage;
- inactive and soft-deleted students;
- current-week and preceding-week boundaries;
- OCR completed, pending, and failed states restricted to archived records;
- active colleges with zero students;
- missing path and missing size counts;
- activity rows with missing related entities.

Implementation verification will also include:

- direct SQL comparisons for every KPI;
- confirmation that no hard-coded KPI values remain in Dashboard markup or JavaScript;
- confirmation that all administrator navigation omits Analytics;
- confirmation that `admin/analytics.php` redirects to the Dashboard;
- report export comparison against the API response;
- desktop and responsive browser checks.

## Out of Scope

A true “all required student documents digitized” percentage is not part of this change. The current schema does not define which document categories are required for each student, program, year level, or academic year. That metric requires a separate requirements model and should not be inferred from existing documents.

No uptime percentage will be displayed until the application records real availability checks or consumes a monitoring service with retained observations.
