/* ==========================================================================
   DATS - dashboard table behaviour
   Progressive enhancement shared by every data table in admin/ and staff/.
   Rows are rebuilt from the API by data-sync.js and script.js, so everything
   here re-applies itself through a MutationObserver instead of running once.
   ========================================================================== */
document.addEventListener('DOMContentLoaded', () => {
  const TABLES = '.users-table, .students-table, .history-table, .student-year-table';

  /* Cells carry their column name so the small-screen card layout can print
     it via `content: attr(data-label)`. */
  const labelCells = (table) => {
    const headings = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());
    table.querySelectorAll('tbody tr').forEach((row) => {
      if (row.children.length !== headings.length) return;   // skip colspan rows
      [...row.children].forEach((cell, index) => {
        if (headings[index]) cell.dataset.label = headings[index];
      });
    });
  };

  /* --- Sorting ----------------------------------------------------------
     Sort keys are read from rendered text, so this works for both the
     server-rendered rows and the ones the API renders later. */
  const sortKey = (row, index) => {
    const cell = row.children[index];
    if (!cell) return '';
    // Identity cells pair a title with supporting detail (email, college,
    // file size). Sort on the title alone when there is one.
    const title = cell.querySelector('.table-user strong, .history-file strong, .student-identity strong');
    const source = title || cell;
    // innerText keeps block-level content (date above time) separated.
    return (source.innerText || source.textContent || '').trim().replace(/\s+/g, ' ');
  };

  const toNumber = (key) => Number(key.replace(/[^\d.-]/g, ''));

  /* One type for the whole column, decided from the keys it actually holds,
     so a stray "Never" cannot make a date column sort as text. */
  const columnType = (keys) => {
    const filled = keys.filter((key) => key !== '');
    if (!filled.length) return 'text';
    if (filled.every((key) => /^[^a-z]*\d[^a-z]*$/i.test(key) && !Number.isNaN(toNumber(key)))) return 'number';
    // >= so a column that is half real dates and half "Never" still counts
    // as a date column, letting the placeholders sink via rankOf.
    if (filled.filter((key) => !Number.isNaN(Date.parse(key))).length >= filled.length / 2) return 'date';
    return 'text';
  };

  /* Cells with nothing to compare ("Never", "Not provided", blank) always
     sink to the bottom, in both directions - rank is applied before the
     ascending/descending flip. */
  const rankOf = (key, type) => {
    if (!key) return 1;
    if (type === 'number') return Number.isNaN(toNumber(key)) ? 1 : 0;
    if (type === 'date') return Number.isNaN(Date.parse(key)) ? 1 : 0;
    return 0;
  };

  const compareValues = (a, b, type) => {
    if (type === 'number') return toNumber(a) - toNumber(b);
    if (type === 'date') return Date.parse(a) - Date.parse(b);
    // Plain collation on purpose: numeric collation reads the hyphen in a
    // student number like 2026-001234 as a minus sign and inverts the order.
    return a.localeCompare(b, undefined, { sensitivity: 'base' });
  };

  const applySort = (table) => {
    const header = table.querySelector('thead th[aria-sort]:not([aria-sort="none"])');
    const body = table.querySelector('tbody');
    if (!header || !body) return;
    const index = [...header.parentElement.children].indexOf(header);
    const direction = header.getAttribute('aria-sort') === 'descending' ? -1 : 1;
    const columnCount = header.parentElement.children.length;

    const rows = [...body.rows].filter((row) => row.children.length === columnCount);
    if (rows.length < 2) return;

    const entries = rows.map((row, position) => ({ row, position, key: sortKey(row, index) }));
    const type = columnType(entries.map((entry) => entry.key));
    entries.forEach((entry) => { entry.rank = rankOf(entry.key, type); });

    entries
      .sort((a, b) => {
        if (a.rank !== b.rank) return a.rank - b.rank;
        if (a.rank === 1) return a.position - b.position;
        const result = compareValues(a.key, b.key, type);
        return result ? result * direction : a.position - b.position;   // stable
      })
      .forEach((entry) => body.append(entry.row));
  };

  const enhance = (table) => {
    if (table.dataset.enhanced) return;
    table.dataset.enhanced = 'true';

    const body = table.querySelector('tbody');
    const headings = [...table.querySelectorAll('thead th')];

    /* Sorting moves rows, which is itself a childList mutation. The observer
       is detached around our own writes (and its queue dropped) so the
       re-sort cannot retrigger itself into an endless loop. */
    let observer = null;
    const refresh = () => {
      observer?.disconnect();
      labelCells(table);
      applySort(table);
      observer?.takeRecords();
      if (body) observer?.observe(body, { childList: true });
    };

    headings.forEach((th, index) => {
      // The trailing actions column holds buttons, not sortable values.
      if (index === headings.length - 1) return;
      if (!th.textContent.trim()) return;
      th.dataset.sortKey = String(index);
      th.tabIndex = 0;
      th.setAttribute('aria-sort', 'none');
      th.title = `Sort by ${th.textContent.trim()}`;

      const toggle = () => {
        const next = th.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending';
        headings.forEach((other) => other.hasAttribute('aria-sort') && other.setAttribute('aria-sort', 'none'));
        th.setAttribute('aria-sort', next);
        refresh();
      };

      th.addEventListener('click', toggle);
      th.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        event.preventDefault();
        toggle();
      });
    });

    labelCells(table);
    if (!body) return;
    // Re-label and re-apply the sort whenever the API swaps the rows in.
    observer = new MutationObserver(refresh);
    observer.observe(body, { childList: true });
  };

  document.querySelectorAll(TABLES).forEach(enhance);

  /* Tables rendered wholesale into a container after load (the year folders
     in admin/files.php) are picked up when they appear. */
  const lateTables = document.querySelector('#yearStudentList');
  if (lateTables) {
    new MutationObserver(() => {
      lateTables.querySelectorAll(TABLES).forEach(enhance);
    }).observe(lateTables, { childList: true, subtree: true });
  }

  /* --- Row action menus --------------------------------------------------
     script.js appends these into the <td>. That cell now lives inside a
     scrolling, sticky-positioned container which would clip the menu, so
     move it to <body> and pin it to the button it belongs to. */
  const anchorMenu = (menu) => {
    const cell = menu.closest('td');
    const trigger = cell?.querySelector('.row-action');
    if (!trigger) return;

    menu.classList.add('is-anchored');
    document.body.append(menu);

    const place = () => {
      const rect = trigger.getBoundingClientRect();
      const width = menu.offsetWidth || 190;
      const height = menu.offsetHeight || 90;
      // Fall back to the document element when the window reports no size
      // (a hidden or not-yet-painted tab), so the menu is never flung off-screen.
      const viewWidth = window.innerWidth || document.documentElement.clientWidth || width + 16;
      const viewHeight = window.innerHeight || document.documentElement.clientHeight || height + 16;

      const right = Math.max(8, Math.min(viewWidth - 8, rect.right));
      const left = Math.max(8, Math.min(right - width, viewWidth - width - 8));
      const below = rect.bottom + 6;
      // Flip above the button when there is no room underneath.
      const top = below + height > viewHeight - 8 ? Math.max(8, rect.top - height - 6) : below;

      menu.style.left = `${Math.round(left)}px`;
      menu.style.top = `${Math.round(top)}px`;
    };

    place();
    const reposition = () => (menu.isConnected ? place() : cleanup());
    const cleanup = () => {
      window.removeEventListener('scroll', reposition, true);
      window.removeEventListener('resize', reposition);
    };
    window.addEventListener('scroll', reposition, true);
    window.addEventListener('resize', reposition);
  };

  new MutationObserver((records) => {
    records.forEach((record) => {
      record.addedNodes.forEach((node) => {
        if (node.nodeType !== 1) return;
        if (!node.matches?.('.user-action-menu, .student-action-menu')) return;
        if (node.classList.contains('is-anchored')) return;
        anchorMenu(node);
      });
    });
  }).observe(document.body, { childList: true, subtree: true });
});
