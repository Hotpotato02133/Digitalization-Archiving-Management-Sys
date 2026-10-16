const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const read = (relativePath) => fs.readFileSync(path.join(root, relativePath), 'utf8');

test('analytics API delegates to dashboard aggregation and enriches activity', () => {
  const api = read('api.php');
  assert.match(api, /require_once __DIR__ \. '\/lib\/dashboard-analytics\.php'/);
  assert.match(api, /dats_get_dashboard_analytics\(\$pdo\)/);
  assert.match(api, /dats_get_dashboard_activity\(\$pdo\)/);
  assert.doesNotMatch(api, /processed_this_week[^;]+uploaded_at/s);
});

test('dashboard exposes approved data-backed KPI and panel hooks', () => {
  const dashboard = read('admin/index.php');
  for (const hook of [
    'archived_documents',
    'student_archive_coverage_rate',
    'archived_this_week',
    'search_ready_rate',
    'coverage_by_college',
    'archive_by_week',
    'archive_by_category',
    'records_requiring_attention',
    'dashboard_activity',
  ]) {
    assert.match(dashboard, new RegExp(`data-dashboard=["']${hook}["']`), hook);
  }
  assert.match(dashboard, /role="alert"[^>]+data-dashboard-error/);
  assert.doesNotMatch(dashboard, /99\.98%|92\.4%|2\.8 TB|System health|fully digitized/i);
  assert.doesNotMatch(dashboard, /href="analytics\.php"/);
});

test('dashboard renderer handles zero denominators and loading failures honestly', () => {
  const sync = read('data-sync.js');
  assert.match(sync, /const request = async \(resource\) => \{\s*try \{/);
  assert.match(sync, /catch \([^)]*\) \{\s*return null;\s*\}/);
  assert.match(sync, /const renderDashboardAnalytics\s*=/);
  assert.match(sync, /const renderDashboardActivity\s*=/);
  assert.match(sync, /const renderDashboardError\s*=/);
  assert.match(sync, /No registered students/);
  assert.match(sync, /Math\.min\(100, Math\.max\(0,/);
  assert.match(sync, /archived_week_change/);
  assert.match(sync, /archived_storage_bytes/);
  assert.doesNotMatch(sync, /92\.4|99\.98/);
});

test('recently archived shares the dashboard scroll limit and renders all returned uploads', () => {
  const sync = read('data-sync.js');
  const styles = read('admin/styles.css');
  const scanPage = read('admin/scan.php');

  assert.match(styles, /\.recent-list,\s*\.category-list,\s*\.coverage-list,\s*\.activity-feed\s*\{\s*max-height:\s*340px;\s*overflow-y:\s*auto;/);
  assert.match(sync, /uploads\.forEach\(\(upload\) => \{/);
  assert.doesNotMatch(sync, /uploads\.slice\(0,\s*5\)/);
  assert.doesNotMatch(scanPage, /\.recent-list\s*\{[^}]*overflow-y/);
});

test('uploaded gradesheets scroll inside their existing table wrapper', () => {
  const styles = read('admin/gradesheets.css');
  const page = read('admin/gradesheets.php');

  assert.match(styles, /\.gradesheet-history-panel\s+\.gs-table-wrap\s*\{\s*max-height:\s*340px;\s*overflow-y:\s*auto;/);
  assert.match(page, /<div class="gs-table-wrap">\s*<table class="gs-table gs-batch-table">/);
});

test('analytics route redirects and administrator navigation no longer links to it', () => {
  const adminDir = path.join(root, 'admin');
  const analytics = read('admin/analytics.php');
  assert.match(analytics, /header\(['"]Location: index\.php['"], true, 302\)/);
  assert.doesNotMatch(analytics, /<!doctype html>|<main/i);

  for (const filename of fs.readdirSync(adminDir).filter((name) => name.endsWith('.php') && name !== 'analytics.php')) {
    assert.doesNotMatch(read(`admin/${filename}`), /href=["']analytics\.php["']/, filename);
  }
});

test('dashboard export uses the consolidated analytics response', () => {
  const script = read('admin/script.js');
  const exportStart = script.indexOf("exportButton?.addEventListener('click'");
  const exportEnd = script.indexOf('let selectedFiles', exportStart);
  const exportHandler = script.slice(exportStart, exportEnd);
  assert.match(exportHandler, /resource=analytics/);
  assert.doesNotMatch(exportHandler, /resource=annual-report/);
  for (const section of [
    'Archived records',
    'Student archive coverage',
    'Archived this week',
    'Search-ready records',
    'Coverage by college',
    'Eight-week throughput',
    'Archive composition',
    'Records requiring attention',
    'Archived file size',
  ]) assert.match(exportHandler, new RegExp(section, 'i'), section);
  assert.match(exportHandler, /zppsu-archive-dashboard-report-/);
});
