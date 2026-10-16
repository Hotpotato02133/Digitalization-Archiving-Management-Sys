(function studentImport() {
  const openButton = document.querySelector('#importStudentsButton');
  const modal = document.querySelector('#studentImportModal');
  if (!openButton || !modal) return;

  const $ = (selector) => modal.querySelector(selector);
  const fileInput = $('#siFile');
  const yearInput = $('#siYear');
  const status = $('#siStatus');
  const results = $('#siResults');
  const confirmButton = $('#siConfirm');
  const toast = document.querySelector('#adminToast');

  // Same columns and sample row as dats_student_import_template() in lib/student-import.php.
  const TEMPLATE = 'Student Number,Last Name,First Name,Middle Initial,Gender,Birthdate,Address,Nationality,Civil Status,Contact Number,College,Course,Major,Academic Year,Year Level\r\n'
    + '2026-000123,Dela Cruz,Juan,M.,Male,2005-03-14,"123 Rizal St., Zamboanga City",Filipino,Single,09171234567,College of Education,Bachelor of Elementary Education,,2026,1st Year\r\n';

  const el = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
  };
  const plural = (count, word) => `${count} ${word}${count === 1 ? '' : 's'}`;
  const showToast = (message) => {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    window.setTimeout(() => toast.classList.remove('show'), 4200);
  };

  let created = false;
  let busy = false;
  const setBusy = (value, message = '') => {
    busy = value;
    fileInput.disabled = value;
    confirmButton.disabled = value || confirmButton.dataset.ready !== '1';
    status.textContent = message;
  };

  const reset = () => {
    fileInput.value = '';
    yearInput.value = String(new Date().getFullYear());
    results.replaceChildren();
    status.textContent = '';
    confirmButton.dataset.ready = '0';
    confirmButton.disabled = true;
    confirmButton.textContent = 'Import students';
    created = false;
  };

  const open = () => { reset(); modal.hidden = false; fileInput.focus(); };
  const close = () => {
    modal.hidden = true;
    if (created) window.location.reload();
  };

  const post = async (action) => {
    const body = new FormData();
    body.append('action', action);
    body.append('default_academic_year', yearInput.value.trim());
    body.append('file', fileInput.files[0]);
    const response = await fetch('../api.php?resource=students', { method: 'POST', credentials: 'same-origin', body });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.error || 'The import could not be completed.');
    return data.data ?? data;
  };

  const stat = (value, label, tone) => {
    const node = el('div', tone);
    node.append(el('strong', '', String(value)), el('span', '', label));
    return node;
  };

  const rowTable = (rows) => {
    const table = el('table', 'si-table');
    const head = el('tr');
    ['Line', 'Student no.', 'Name', 'Result'].forEach((label) => head.append(el('th', '', label)));
    const thead = el('thead');
    thead.append(head);
    table.append(thead);
    const body = el('tbody');
    rows.forEach((row) => {
      const tr = el('tr');
      const result = el('td');
      result.append(el('span', `si-tag is-${row.status}`, row.status === 'ready' ? 'will import' : row.status === 'duplicate' ? 'skipped' : 'error'));
      row.messages.forEach((message) => result.append(document.createElement('br'), document.createTextNode(message)));
      tr.append(el('td', '', String(row.line)), el('td', '', row.student_number || '—'), el('td', '', row.name || '—'), result);
      body.append(tr);
    });
    table.append(body);
    const wrap = el('div', 'si-table-wrap');
    wrap.append(table);
    return wrap;
  };

  const renderPreview = (preview) => {
    const { summary } = preview;
    const parts = [];
    const stats = el('div', 'si-summary');
    stats.append(
      stat(summary.ready, 'will import', 'is-ready'),
      stat(summary.duplicate, 'already exist / repeated', 'is-skip'),
      stat(summary.error, 'need fixing', 'is-error'),
      stat(summary.total, 'rows read', ''),
    );
    parts.push(stats);
    if (preview.new_colleges.length || preview.new_courses.length) {
      const names = [...preview.new_colleges.map((name) => `college "${name}"`), ...preview.new_courses.map((name) => `course "${name.split(' > ')[1]}"`)];
      parts.push(el('p', 'si-note', `Importing will also create ${names.length} new ${names.length === 1 ? 'entry' : 'entries'} in Structure: ${names.slice(0, 6).join(', ')}${names.length > 6 ? '…' : ''}. If one is a spelling variant of an existing name, fix it in the file first.`));
    }
    if (preview.unmapped_columns.length) {
      parts.push(el('p', 'si-note', `Ignored columns (not recognized): ${preview.unmapped_columns.join(', ')}.`));
    }
    const rows = [...preview.problem_rows, ...preview.sample_rows];
    if (rows.length) {
      const caption = preview.problem_rows.length
        ? `Rows needing attention${preview.problem_rows_truncated ? ' (first 300)' : ''}, then a sample of clean rows:`
        : 'A sample of the rows that will be imported:';
      parts.push(el('p', 'si-status', caption), rowTable(rows));
    }
    results.replaceChildren(...parts);
    confirmButton.dataset.ready = summary.ready > 0 ? '1' : '0';
    confirmButton.disabled = summary.ready === 0;
    confirmButton.textContent = summary.ready > 0 ? `Import ${plural(summary.ready, 'student')}` : 'Nothing to import';
  };

  const check = async () => {
    if (!fileInput.files.length || busy) return;
    results.replaceChildren();
    confirmButton.dataset.ready = '0';
    setBusy(true, 'Checking the file…');
    try {
      renderPreview(await post('import-preview'));
      setBusy(false, 'Nothing has been imported yet. Review the list, then confirm.');
    } catch (error) {
      setBusy(false, '');
      results.replaceChildren(el('p', 'si-note', error.message));
    }
  };

  const importNow = async () => {
    if (busy || confirmButton.dataset.ready !== '1') return;
    setBusy(true, 'Importing…');
    try {
      const result = await post('import');
      created = result.created > 0;
      const skipped = result.skipped_count || 0;
      const parts = [el('p', 'si-note', `Imported ${plural(result.created, 'student')}${skipped ? `; ${skipped} skipped (see below).` : '.'}`)];
      if (skipped) {
        parts.push(rowTable(result.skipped.map((row) => ({ line: row.line, status: 'error', student_number: row.student_number, name: '', messages: [row.reason] }))));
      }
      results.replaceChildren(...parts);
      confirmButton.dataset.ready = '0';
      confirmButton.textContent = 'Done';
      showToast(`Imported ${plural(result.created, 'student')}.`);
      setBusy(false, 'Close this dialog to refresh the list.');
    } catch (error) {
      setBusy(false, '');
      results.replaceChildren(el('p', 'si-note', error.message));
    }
  };

  openButton.addEventListener('click', open);
  $('#siClose').addEventListener('click', close);
  $('#siCancel').addEventListener('click', close);
  modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !modal.hidden) close(); });
  fileInput.addEventListener('change', check);
  yearInput.addEventListener('change', check);
  confirmButton.addEventListener('click', importNow);
  $('#siTemplate').addEventListener('click', () => {
    const url = URL.createObjectURL(new Blob(['﻿', TEMPLATE], { type: 'text/csv;charset=utf-8' }));
    const link = el('a');
    link.href = url;
    link.download = 'student-import-template.csv';
    document.body.append(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  });
}());
