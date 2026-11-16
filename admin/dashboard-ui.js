/* Admin dashboard presentation helpers (admin/index.php only).
   Adds CSS state classes for empty and zero data. The phone-drawer chrome
   (scrim, close button, Escape, scroll lock) lives in shell-ui.js, which every
   admin page loads. It reads the DOM the existing scripts render and never
   fetches, writes, or changes data. */
(() => {
  'use strict';

  // --- Empty and zero-state classes, applied after the dashboard renderer writes its rows.
  const asNumber = (text) => Number(String(text || '').replace(/[^\d.-]/g, '')) || 0;

  const markChart = () => {
    const chart = document.querySelector('[data-dashboard="archive_by_week"]');
    if (!chart) return;
    const columns = chart.querySelectorAll('.throughput-column');
    let total = 0;
    columns.forEach((column) => {
      const count = asNumber(column.querySelector('strong')?.textContent);
      total += count;
      column.querySelector('.throughput-track > span')?.classList.toggle('is-zero', count === 0);
    });
    chart.classList.toggle('is-empty', columns.length > 0 && total === 0);
  };

  const markList = (selector) => {
    const list = document.querySelector(selector);
    if (!list) return;
    const rows = list.querySelectorAll('.dashboard-progress-row');
    let total = 0;
    rows.forEach((row) => { total += asNumber(row.querySelector('div:first-child span')?.textContent); });
    list.classList.toggle('is-empty', rows.length > 0 && total === 0);
  };

  const markAttention = () => {
    const grid = document.querySelector('[data-dashboard="records_requiring_attention"]');
    if (!grid) return;
    let issues = 0;
    grid.querySelectorAll('[data-attention]').forEach((value) => {
      const count = asNumber(value.textContent);
      issues += count;
      value.closest('div')?.classList.toggle('has-issues', count > 0 && value.textContent.trim() !== '—');
    });
    grid.classList.toggle('is-clear', issues === 0);
  };

  const markTrend = () => {
    const trend = document.querySelector('[data-dashboard-detail="archived_week_change"]');
    if (!trend) return;
    trend.classList.toggle('is-neutral', /^\+?0\b/.test(trend.textContent.trim()));
  };

  const refresh = () => { markChart(); markList('[data-dashboard="coverage_by_college"]'); markList('[data-dashboard="archive_by_category"]'); markAttention(); markTrend(); };
  const root = document.querySelector('[data-dashboard-root]');
  if (root) {
    let frame = 0;
    new MutationObserver(() => {
      window.cancelAnimationFrame(frame);
      frame = window.requestAnimationFrame(refresh);
    }).observe(root, { childList: true, subtree: true, characterData: true });
  }
  refresh();
})();
