# Dashboard Analytics Consolidation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the separate Analytics page with one database-backed administrator Dashboard containing accurate archive KPIs, decision-useful breakdowns, a unified activity feed, and a matching PDF report.

**Architecture:** Move dashboard aggregation into a focused PHP library that accepts a PDO connection and an optional clock, expose its result through the existing analytics endpoint, and render the returned contract in the current server-rendered Dashboard. Preserve legacy analytics fields temporarily for staff pages, while retiring the administrator Analytics route through a redirect.

**Tech Stack:** PHP 8/PDO, MariaDB with SQLite test fixtures, vanilla JavaScript, CSS Grid, Node.js built-in test runner, jsPDF.

**Spec:** `docs/superpowers/specs/2026-09-22-dashboard-analytics-consolidation-design.md`

## Global Constraints

- Every KPI and chart value must come from the database response; no hard-coded metric values or decorative charts may remain.
- Archived-week calculations use `archived_at`, never `uploaded_at`.
- “Student archive coverage” means eligible non-deleted students with at least one archived document; it is not called total digitization completion.
- A college with no eligible students displays `No registered students` rather than `0%`.
- The Analytics URL redirects to the Dashboard, and Analytics links disappear from administrator navigation.
- Existing staff consumers of `api.php?resource=analytics` must continue to receive their legacy fields during this change.
- Do not add frontend or backend dependencies.

## Review Focus

- One student with multiple archived documents must count once in overall and college coverage; Task 1 pins this with a duplicate-document fixture.
- Week boundaries must remain correct on Monday and across month/year transitions; Task 1 injects a fixed Monday clock and records on both sides of the boundary.
- Inactive and soft-deleted students must not affect coverage denominators or numerators; Task 1 includes both states.
- Empty colleges and an entirely empty database must return renderable zero values instead of division errors; Tasks 1 and 3 cover both responses.
- Archived activity whose related document was removed must still render from its log description; Tasks 2 and 3 verify the left-join/fallback behavior.

---

### Task 1: Database-backed dashboard aggregation

**Files:**
- Create: `lib/dashboard-analytics.php`
- Create: `tests/php/dashboard_analytics_test.php`

**Interfaces:**
- Consumes: `PDO`, plus optional `DateTimeImmutable $now` for deterministic boundaries.
- Produces: `dats_get_dashboard_analytics(PDO $pdo, ?DateTimeImmutable $now = null): array` with the contract from the approved specification.

- [ ] **Step 1: Write the failing SQLite aggregation test**

Create tables for `students`, `documents`, `colleges`, `document_categories`, and `activity_logs`, then seed active, inactive, soft-deleted, duplicate-document, OCR, missing-file, and boundary-date cases. Assert exact counts and rates:

```php
$now = new DateTimeImmutable('2026-09-21 12:00:00'); // Monday
$metrics = dats_get_dashboard_analytics($pdo, $now);

assert($metrics['archived_documents'] === 5);
assert($metrics['eligible_students'] === 3);
assert($metrics['students_with_archived_documents'] === 2);
assert($metrics['student_archive_coverage_rate'] === 66.7);
assert($metrics['archived_this_week'] === 2);
assert($metrics['archived_last_week'] === 1);
assert($metrics['archived_week_change'] === 1);
assert($metrics['ocr_completed_archived'] === 2);
assert($metrics['ocr_pending_archived'] === 2);
assert($metrics['ocr_failed_archived'] === 1);
assert($metrics['search_ready_rate'] === 40.0);
assert($metrics['missing_file_path_count'] === 1);
assert($metrics['missing_file_size_count'] === 1);
assert(count($metrics['archive_by_week']) === 8);
assert($metrics['coverage_by_college'][1]['coverage_label'] === 'No registered students');
```

Add a second empty-database fixture and assert all scalar counts/rates are zero, the eight-week series is present, and colleges/categories return valid zero rows.

- [ ] **Step 2: Run the PHP test and verify it fails**

Run:

```powershell
C:\xampp\php\php.exe -d zend.assertions=1 -d assert.exception=1 tests\php\dashboard_analytics_test.php
```

Expected: failure because `lib/dashboard-analytics.php` or `dats_get_dashboard_analytics()` does not exist.

- [ ] **Step 3: Implement the aggregation library**

Implement small private helpers for rate calculation, week bounds, and storage-byte normalization, followed by parameterized, SQLite/MariaDB-compatible queries. Use `CASE WHEN ... THEN ... END`, `COUNT(DISTINCT ...)`, and prepared ISO timestamps rather than MariaDB-only date arithmetic.

```php
function dats_metric_rate(int $numerator, int $denominator): float
{
    return $denominator > 0 ? round(($numerator / $denominator) * 100, 1) : 0.0;
}

function dats_get_dashboard_analytics(PDO $pdo, ?DateTimeImmutable $now = null): array
{
    $now ??= new DateTimeImmutable('now');
    $weekStart = $now->modify('monday this week')->setTime(0, 0);
    $lastWeekStart = $weekStart->modify('-7 days');
    // Query scalar summary, college coverage, eight-week counts, and active-category counts.
    // Return counts as ints, rates as floats, and arrays in stable display order.
}
```

For each of eight weeks, pre-create a zero-valued bucket in PHP and merge grouped database results so missing weeks are retained.

- [ ] **Step 4: Run the aggregation test and verify it passes**

Run the command from Step 2. Expected: `dashboard_analytics_test: PASS`.

- [ ] **Step 5: Run existing PHP tests**

Run:

```powershell
C:\xampp\php\php.exe -d zend.assertions=1 -d assert.exception=1 tests\php\archive_workflow_test.php
C:\xampp\php\php.exe -d zend.assertions=1 -d assert.exception=1 tests\php\scan_intake_test.php
```

Expected: both existing tests pass.

### Task 2: API contract and enriched activity feed

**Files:**
- Modify: `api.php`
- Modify: `lib/dashboard-analytics.php`
- Extend: `tests/php/dashboard_analytics_test.php`
- Create: `tests/js/dashboard-consolidation.test.cjs`

**Interfaces:**
- Consumes: `dats_get_dashboard_analytics(PDO, ?DateTimeImmutable): array` from Task 1.
- Produces: the analytics JSON contract, legacy compatibility aliases, and activity rows containing `description`, `action`, `user_name`, `document_name`, `category_name`, `student_name`, `document_status`, and `created_at`.

- [ ] **Step 1: Add failing API-source contract tests**

The Node test reads `api.php` and asserts that it requires the new library, delegates `get_analytics()` to it, uses an enriched activity function, and no longer performs the incorrect `uploaded_at` weekly calculation:

```js
assert.match(api, /require_once __DIR__ \. '\/lib\/dashboard-analytics\.php'/);
assert.match(api, /dats_get_dashboard_analytics\(\$pdo\)/);
assert.match(api, /get_dashboard_activity\(\$pdo\)/);
assert.doesNotMatch(api, /processed_this_week[^;]+uploaded_at/s);
```

Extend the PHP fixture with an activity row referencing a missing document and assert that the activity query/helper returns the original description with nullable context fields.

- [ ] **Step 2: Run tests and verify the new assertions fail**

Run:

```powershell
node --test tests\js\dashboard-consolidation.test.cjs
C:\xampp\php\php.exe -d zend.assertions=1 -d assert.exception=1 tests\php\dashboard_analytics_test.php
```

Expected: failures for the missing include, delegation, and activity helper.

- [ ] **Step 3: Wire the library into the API**

Add the library include beside the existing workflow includes. Replace the body of `get_analytics()` with the new aggregator and add compatibility keys used by existing staff pages:

```php
$analytics = dats_get_dashboard_analytics($pdo);
$analytics['total_students'] = $analytics['eligible_students'];
$analytics['total_documents'] = $analytics['archived_documents'];
$analytics['completed_documents'] = $analytics['archived_documents'];
$analytics['processing_documents'] = 0;
$analytics['documents_needing_review'] = 0;
$analytics['processed_this_week'] = $analytics['archived_this_week'];
$analytics['processed_last_week'] = $analytics['archived_last_week'];
```

Retain real `total_colleges` and `total_courses` aliases for any staff markup that still displays them.

- [ ] **Step 4: Implement enriched activity retrieval**

Add `dats_get_dashboard_activity(PDO $pdo, int $limit = 12): array` using left joins from `activity_logs` to `documents`, `students`, and `document_categories`. Keep the log description as the guaranteed fallback, validate/clamp the integer limit before interpolating it, and route the `activity` resource through this function.

- [ ] **Step 5: Run PHP and Node contract tests**

Run the commands from Step 2. Expected: all pass.

### Task 3: Consolidated Dashboard markup, rendering, and responsive styles

**Files:**
- Modify: `admin/index.php`
- Modify: `data-sync.js`
- Modify: `admin/styles.css`
- Extend: `tests/js/dashboard-consolidation.test.cjs`

**Interfaces:**
- Consumes: the analytics and activity endpoint contracts from Task 2.
- Produces: data hooks for four KPIs, college coverage, eight-week throughput, category composition, attention counts, storage size, an inline load error, and a unified activity feed.

- [ ] **Step 1: Add failing dashboard structure tests**

Assert that `admin/index.php` contains hooks for all approved components and none of the rejected metric copy:

```js
for (const hook of [
  'archived_documents', 'student_archive_coverage_rate', 'archived_this_week',
  'search_ready_rate', 'coverage_by_college', 'archive_by_week',
  'archive_by_category', 'records_requiring_attention', 'dashboard_activity'
]) assert.match(dashboard, new RegExp(`data-dashboard="${hook}"`));

assert.doesNotMatch(dashboard, /99\.98%|92\.4%|2\.8 TB|System health|fully digitized/i);
assert.doesNotMatch(dashboard, /href="analytics\.php"/);
```

Also assert that `data-sync.js` renders `No registered students`, creates zero-width-safe percentages, emits an inline analytics error, and never inserts fixed KPI percentages.

- [ ] **Step 2: Run the Node test and verify it fails**

Run `node --test tests\js\dashboard-consolidation.test.cjs`. Expected: missing hooks and rejected legacy content still present.

- [ ] **Step 3: Replace Dashboard content markup**

In `admin/index.php`, remove filesystem capacity scanning and the two separate recent panels. Add:

- four primary KPI articles with loading text and exact `data-dashboard` hooks;
- college coverage and eight-week throughput panels;
- archive composition and records-requiring-attention panels;
- one unified activity panel;
- an inline error region with `role="alert"`;
- the three approved quick actions.

Keep server-rendered navigation, header, profile, notification, modal, and footer infrastructure intact.

- [ ] **Step 4: Implement defensive Dashboard rendering**

Replace the existing generic analytics rendering block in `data-sync.js` with named helpers:

```js
const dashboardPercent = (value) => Math.min(100, Math.max(0, Number(value) || 0));
const formatStorageBytes = (bytes) => { /* B, KB, MB, GB, TB */ };
const renderDashboardAnalytics = (analytics) => { /* KPIs and panels */ };
const renderDashboardActivity = (activities) => { /* unified feed */ };
const renderDashboardError = (message) => { /* inline alert, no fake zeros */ };
```

Use DOM node creation plus `textContent` for database strings. Set visible percentage text and `aria-label` on progress indicators. Render college rows with no denominator as `No registered students`. Render empty categories/weeks rather than removing them.

- [ ] **Step 5: Add consolidated responsive CSS**

Reuse the existing palette and typography. Add focused classes for KPI supporting values, coverage rows, bar charts, attention tiles, activity rows, loading/error states, and accessible progress labels. At `850px`, use two KPI columns and stacked content panels; at `640px`, use one KPI column and prevent horizontal scrolling.

Remove obsolete `.analytics-*`, `.storage-bar`, separate request/timeline, fake progress, and decorative bar rules only after confirming no remaining page uses them.

- [ ] **Step 6: Run Dashboard structure tests**

Run `node --test tests\js\dashboard-consolidation.test.cjs`. Expected: pass.

### Task 4: Matching report export and Analytics retirement

**Files:**
- Modify: `admin/script.js`
- Replace: `admin/analytics.php`
- Modify: administrator PHP pages containing Analytics navigation links
- Extend: `tests/js/dashboard-consolidation.test.cjs`

**Interfaces:**
- Consumes: `api.php?resource=analytics` and the approved Dashboard contract.
- Produces: a consolidated PDF preview/download and a backward-compatible Analytics redirect.

- [ ] **Step 1: Add failing retirement and report tests**

Recursively inspect administrator PHP pages and assert no navigation or action links target `analytics.php`, except the redirect file itself. Assert `admin/analytics.php` contains a server-side `Location: index.php` redirect and no page markup. Assert the export handler fetches `resource=analytics`, prints all four KPI names, and includes college coverage, weekly throughput, category distribution, attention counts, and storage.

- [ ] **Step 2: Run the Node test and verify it fails**

Run `node --test tests\js\dashboard-consolidation.test.cjs`. Expected: Analytics links and the old annual-report export remain.

- [ ] **Step 3: Update the PDF report**

In `admin/script.js`, fetch `../api.php?resource=analytics`. Build sections from the exact response counts used by the Dashboard. Use a page-break helper before every row and format rates from their numerator/denominator fields. Name the file `zppsu-archive-dashboard-report-YYYY-MM-DD.pdf`.

- [ ] **Step 4: Retire the route and links**

Replace `admin/analytics.php` with authenticated server-side redirection:

```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth.php';
dats_require_login();
header('Location: index.php', true, 302);
exit;
```

Remove the Analytics navigation item from every administrator page and remove any remaining quick-action links to it.

- [ ] **Step 5: Run the retirement/report test**

Run `node --test tests\js\dashboard-consolidation.test.cjs`. Expected: pass.

### Task 5: Full verification against tests and live data

**Files:**
- Verify only; fix the owning file if a check fails.

**Interfaces:**
- Consumes: completed Tasks 1–4.
- Produces: evidence that the consolidated Dashboard matches the approved specification and live database.

- [ ] **Step 1: Run all PHP tests**

Run each file under `tests/php` with assertions enabled. Expected: every file prints `PASS` and exits zero.

- [ ] **Step 2: Run all Node tests**

Run:

```powershell
node --test tests\js\*.test.cjs tests\*.test.js
```

Expected: all tests pass.

- [ ] **Step 3: Run PHP syntax validation**

Run `C:\xampp\php\php.exe -l` on `api.php`, `lib/dashboard-analytics.php`, and every modified administrator PHP page. Expected: no syntax errors.

- [ ] **Step 4: Compare the live aggregator response to direct SQL**

Using the configured PDO connection, print only non-sensitive aggregate values and verify:

- archived records = 5;
- covered students = 3 of 7 = 42.9%;
- archived this week = 2;
- search-ready records = 2 of 5 = 40%;
- archived OCR pending = 3;
- archived missing path/size = 0/0;
- archived storage = 14,120,828 bytes.

If live data changes during implementation, compare formulas and query results rather than forcing these snapshot values into code.

- [ ] **Step 5: Browser verification**

Open the authenticated Dashboard at desktop and mobile widths. Confirm the four KPI values, college no-student label, zero weeks/categories, attention states, unified activity, export preview, navigation removal, redirect behavior, absence of console errors, and no horizontal overflow.

- [ ] **Step 6: Final hard-coded metric scan**

Run:

```powershell
rg -n "99\.98|92\.4|8\.2%|2\.8 TB|fully digitized|System health" admin data-sync.js api.php
```

Expected: no matches in active Dashboard/analytics implementation.

## Commit Note

The workspace currently has no discoverable Git repository, so the per-task commit steps normally required by the workflow cannot run. If Git is initialized or the repository metadata becomes available before execution, commit after each task with scoped messages such as `feat: add dashboard analytics aggregation` and `feat: consolidate admin dashboard`.
