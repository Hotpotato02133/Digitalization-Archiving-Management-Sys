// Students information → "View Gradesheet": a dialog with every digitized grade recorded for one student.
(function exposeStudentGradesheetModal(root) {
  const flow = root.DatsGradesheetFlow;
  const refreshIcons = () => { if (root.lucide) root.lucide.createIcons(); };
  const el = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
  };
  const icon = (name) => {
    const node = document.createElement('i');
    node.dataset.lucide = name;
    return node;
  };
  const button = (label, iconName, className = '') => {
    const node = el('button', `sgm-button ${className}`.trim());
    node.type = 'button';
    if (iconName) node.append(icon(iconName));
    node.append(label);
    return node;
  };
  const link = (label, iconName, href, className = '') => {
    const node = el('a', `sgm-button ${className}`.trim());
    node.href = href;
    if (iconName) node.append(icon(iconName));
    node.append(label);
    return node;
  };

  // Philippine grading: 1.00 is highest, 3.00 is the lowest passing grade, 5.00 is failing.
  const gradeTone = (grade) => {
    const value = String(grade || '').trim();
    if (!value) return 'is-empty';
    const number = Number(value);
    if (!Number.isFinite(number)) return 'is-special';
    if (number > 3) return 'is-fail';
    return number <= 1.75 ? 'is-high' : '';
  };
  const remarkBadge = (remarks) => {
    const value = String(remarks || '').trim().toUpperCase();
    if (!value) return el('span', 'sgm-remark is-other', '—');
    const kind = value === 'PASSED' ? 'is-passed' : value === 'FAILED' ? 'is-failed' : 'is-other';
    return el('span', `sgm-remark ${kind}`, value);
  };
  const formatUnits = (value) => (Number.isFinite(value) ? String(Number(value.toFixed(1))) : '—');
  const initialsOf = (student, fallbackName) => {
    if (student?.first_name || student?.last_name) {
      return `${String(student.first_name || '').charAt(0)}${String(student.last_name || '').charAt(0)}`.toUpperCase() || '?';
    }
    return flow ? flow.initials(fallbackName) : '?';
  };

  const open = async ({ studentId, studentName, student = null }) => {
    const opener = document.activeElement;
    const backdrop = el('div', 'sgm-backdrop');
    const dialog = el('section', 'sgm-dialog');
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-modal', 'true');
    dialog.setAttribute('aria-labelledby', 'sgmTitle');
    dialog.tabIndex = -1;

    // Header: who this gradesheet belongs to. Filled from the directory row right away so the
    // dialog never opens blank while grades load.
    const head = el('header', 'sgm-head');
    const avatar = el('span', 'sgm-avatar', initialsOf(student, studentName));
    avatar.setAttribute('aria-hidden', 'true');
    const identity = el('div', 'sgm-identity');
    const title = el('h2', '', studentName);
    title.id = 'sgmTitle';
    const meta = el('ul', 'sgm-meta');
    const addMeta = (iconName, text) => {
      if (!text) return;
      const item = el('li');
      item.append(icon(iconName), text);
      meta.append(item);
    };
    addMeta('id-card', student?.student_number);
    addMeta('book-open', student?.course_name);
    addMeta('building-2', student?.college_name);
    identity.append(el('span', 'sgm-kicker', 'Digitized gradesheet'), title, meta);
    const closeButton = el('button', 'sgm-close');
    closeButton.type = 'button';
    closeButton.setAttribute('aria-label', 'Close gradesheet');
    closeButton.append(icon('x'));
    head.append(avatar, identity, closeButton);

    const body = el('div', 'sgm-body');
    body.setAttribute('aria-live', 'polite');
    const foot = el('footer', 'sgm-foot');
    dialog.append(head, body, foot);
    backdrop.append(dialog);
    document.body.append(backdrop);
    document.body.classList.add('sgm-open');

    const close = () => {
      backdrop.remove();
      document.removeEventListener('keydown', onKeydown);
      if (!document.querySelector('.sgm-backdrop')) document.body.classList.remove('sgm-open');
      if (opener && document.contains(opener)) opener.focus();
    };
    const onKeydown = (event) => {
      if (event.key === 'Escape') { event.preventDefault(); close(); return; }
      if (event.key !== 'Tab') return;
      const focusable = [...dialog.querySelectorAll('button, a[href], [tabindex]:not([tabindex="-1"])')].filter((node) => node.offsetParent !== null);
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && (document.activeElement === first || document.activeElement === dialog)) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    };
    closeButton.addEventListener('click', close);
    backdrop.addEventListener('mousedown', (event) => { if (event.target === backdrop) close(); });
    document.addEventListener('keydown', onKeydown);

    const setFooter = (note, ...actions) => {
      const wrap = el('div', 'sgm-foot-actions');
      const done = button('Close', null);
      done.addEventListener('click', close);
      wrap.append(...actions, done);
      foot.replaceChildren(...(note ? [el('span', 'sgm-foot-note', note)] : []), wrap);
    };

    const showLoading = () => {
      const skeleton = el('div', 'sgm-skeleton');
      skeleton.append(el('span', 'is-strip'), el('span', 'is-card'), el('span', 'is-card'), el('p', 'sgm-sr', 'Loading grades…'));
      body.replaceChildren(skeleton);
      setFooter('');
    };

    const showState = ({ tone = '', iconName, heading, message, steps = [], actions = [] }) => {
      const state = el('div', `sgm-state ${tone}`.trim());
      const badge = el('span', 'sgm-state-icon');
      badge.append(icon(iconName));
      state.append(badge, el('h3', '', heading), el('p', '', message));
      if (steps.length) {
        const list = el('ol', 'sgm-state-steps');
        steps.forEach((step) => list.append(el('li', '', step)));
        state.append(list);
      }
      if (actions.length) {
        const wrap = el('div', 'sgm-foot-actions');
        wrap.append(...actions);
        state.append(wrap);
      }
      body.replaceChildren(state);
    };

    const showEmpty = () => {
      showState({
        iconName: 'file-search',
        heading: 'No gradesheet on file yet',
        message: `We couldn't find any digitized grades for ${studentName}. Grades appear here automatically once a gradesheet or Report of Rating that lists this student is uploaded.`,
        steps: [
          'Go to Gradesheets and upload the scanned gradesheet or Report of Rating.',
          'Make sure the student\'s name or student number on the sheet matches this profile.',
          'Come back here — every subject will be grouped by semester and school year.',
        ],
      });
      setFooter('Nothing to display for this student yet.', link('Upload gradesheets', 'cloud-upload', 'gradesheets.php', 'is-primary'));
      refreshIcons();
    };

    const showError = (message) => {
      const retry = button('Try again', 'refresh-cw', 'is-primary');
      retry.addEventListener('click', load);
      showState({
        tone: 'is-error',
        iconName: 'triangle-alert',
        heading: 'Unable to load grades',
        message: message || 'The server could not complete the request. Please try again.',
        actions: [retry],
      });
      setFooter('');
      refreshIcons();
    };

    const showGrades = (data) => {
      const entries = data.entries || [];
      const info = data.student || {};
      if (info.display_name) title.textContent = info.display_name;
      if (!meta.children.length) {
        addMeta('id-card', info.student_number);
        addMeta('book-open', info.course_name);
        addMeta('building-2', info.college_name);
      }

      const terms = flow.groupEntriesByTerm(entries);
      const totalUnits = entries.reduce((sum, entry) => sum + (Number(entry.units) || 0), 0);
      const overall = flow.termAverage(entries);
      const flagged = entries.filter((entry) => Number(entry.needs_review)).length;

      const stats = el('div', 'sgm-stats');
      const stat = (value, label, highlight = false) => {
        const node = el('div', `sgm-stat${highlight ? ' is-highlight' : ''}`);
        node.append(el('strong', '', value), el('span', '', label));
        stats.append(node);
      };
      stat(overall !== null ? overall.toFixed(2) : '—', 'General weighted avg.', true);
      stat(String(terms.length), terms.length === 1 ? 'Semester' : 'Semesters');
      stat(String(entries.length), entries.length === 1 ? 'Subject' : 'Subjects');
      stat(totalUnits ? formatUnits(totalUnits) : '—', 'Total units');

      const parts = [stats];
      if (flagged) {
        const notice = el('div', 'sgm-notice');
        notice.append(icon('triangle-alert'), el('span', '', `${flagged} ${flagged === 1 ? 'record was' : 'records were'} read with low confidence and marked “Check”. Verify ${flagged === 1 ? 'it' : 'them'} against the original sheet in Gradesheets.`));
        parts.push(notice);
      }

      const nav = el('nav', 'sgm-terms-nav');
      nav.setAttribute('aria-label', 'Jump to semester');
      const cards = terms.map((term, index) => {
        const id = `sgmTerm${index}`;
        const label = `${term.semester || 'Semester not read'} · S.Y. ${flow.formatSchoolYear(term.academic_year)}`;
        const chip = el('button', `sgm-chip${index === 0 ? ' is-active' : ''}`, label);
        chip.type = 'button';
        chip.dataset.target = id;
        nav.append(chip);

        const card = el('section', 'sgm-term');
        card.id = id;
        const termHead = el('header', 'sgm-term-head');
        const heading = el('h3');
        heading.append(icon('calendar-range'), term.semester || 'Semester not read', ' ', el('small', '', `S.Y. ${flow.formatSchoolYear(term.academic_year)}`));
        const termStats = el('div', 'sgm-term-stats');
        const termStat = (name, value) => { const node = el('span', '', `${name} `); node.append(el('b', '', value)); termStats.append(node); };
        const units = term.rows.reduce((sum, entry) => sum + (Number(entry.units) || 0), 0);
        termStat('Subjects', String(term.rows.length));
        termStat('Units', units ? formatUnits(units) : '—');
        termStat('Weighted avg.', term.average !== null ? term.average.toFixed(2) : '—');
        termHead.append(heading, termStats);

        const table = el('table', 'sgm-table');
        table.innerHTML = '<thead><tr><th>Code</th><th class="sgm-col-title">Descriptive title</th><th class="is-num">Units</th><th class="is-num">Final grade</th><th>Remarks</th></tr></thead>';
        const tbody = el('tbody');
        term.rows.forEach((entry) => {
          const row = el('tr');
          const gradeCell = el('td', 'is-num');
          gradeCell.append(el('span', `sgm-grade ${gradeTone(entry.grade)}`.trim(), entry.grade || 'No grade'));
          if (Number(entry.needs_review)) gradeCell.append(el('span', 'sgm-flag', 'Check'));
          const remarksCell = el('td');
          remarksCell.append(remarkBadge(entry.remarks));
          const entryUnits = entry.units === null || entry.units === undefined || entry.units === '' ? '—' : String(Number(entry.units));
          row.append(el('td', 'sgm-code', entry.subject_code || '—'), el('td', 'sgm-col-title', entry.subject_name || '—'), el('td', 'is-num', entryUnits), gradeCell, remarksCell);
          tbody.append(row);
        });
        table.append(tbody);
        const wrap = el('div', 'sgm-table-wrap');
        wrap.append(table);
        card.append(termHead, wrap);
        return card;
      });
      if (terms.length > 1) parts.push(nav);
      parts.push(...cards);
      body.replaceChildren(...parts);
      body.scrollTop = 0;

      nav.addEventListener('click', (event) => {
        const chip = event.target.closest('.sgm-chip');
        if (!chip) return;
        body.querySelector(`#${chip.dataset.target}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
      // Highlight the semester currently in view.
      body.addEventListener('scroll', () => {
        const top = body.getBoundingClientRect().top + 70;
        let current = cards[0];
        cards.forEach((card) => { if (card.getBoundingClientRect().top <= top) current = card; });
        nav.querySelectorAll('.sgm-chip').forEach((chip) => chip.classList.toggle('is-active', chip.dataset.target === current?.id));
      }, { passive: true });

      setFooter(`${entries.length} ${entries.length === 1 ? 'subject' : 'subjects'} across ${terms.length} ${terms.length === 1 ? 'semester' : 'semesters'}`,
        link('Open in Gradesheets', 'graduation-cap', 'gradesheets.php'));
      refreshIcons();
    };

    async function load() {
      showLoading();
      refreshIcons();
      try {
        const response = await fetch(`../api.php?resource=gradesheets&student_id=${encodeURIComponent(studentId)}`, { credentials: 'same-origin' });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(result.error || 'Unable to load grades.');
        if (!document.contains(backdrop)) return;
        if (!(result.data?.entries || []).length) showEmpty();
        else showGrades(result.data);
      } catch (error) {
        if (document.contains(backdrop)) showError(error.message);
      }
    }

    refreshIcons();
    dialog.focus();
    if (!flow) { showError('The gradesheet helpers failed to load. Refresh the page and try again.'); return; }
    await load();
  };

  root.DatsStudentGradesheetModal = { open };
}(window));
