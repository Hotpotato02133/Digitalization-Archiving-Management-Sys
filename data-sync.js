// ---------------------------------------------------------------------------
// Background job bridge: keeps "Add to archive" (File Scanning) and the Gradesheets
// OCR-then-save pipeline running even if the user navigates to another page mid-upload.
// The actual work is done by a Service Worker (sw.js, DATS root) so it survives navigation;
// this bridge registers it, relays OCR work to whichever page is open (OCR needs real
// canvas/Worker APIs the service worker itself doesn't have), and renders a small persistent
// status banner driven by whatever the worker reports. If service workers aren't available
// (older browser, or a future non-HTTPS/non-localhost deployment), everything below falls
// back to doing the same work inline on this page — a plain regression to pre-fix behavior,
// never a broken feature.
window.DatsJobs = (function buildJobBridge() {
  const getAppBasePath = () => {
    const path = window.location.pathname;
    // Strip any known workspace subdirectory so the result is always the app root.
    // This covers /admin/, /staff/, and any future subdirectory added at the same level.
    const knownDirs = ['/admin/', '/staff/'];
    for (const dir of knownDirs) {
      const idx = path.toLowerCase().indexOf(dir);
      if (idx !== -1) return path.slice(0, idx);
    }
    const lastSlash = path.lastIndexOf('/');
    return lastSlash > 0 ? path.slice(0, lastSlash) : '';
  };
  const appBase = getAppBasePath();
  window.DatsAppBase = appBase;

  const supportsSw = 'serviceWorker' in navigator;
  const listeners = new Set();
  const jobRows = new Map();
  let bannerEl = null;
  let swReady = null;

  const generateId = () => (window.crypto?.randomUUID ? window.crypto.randomUUID() : `job-${Date.now()}-${Math.random().toString(16).slice(2)}`);
  const strip = (job) => { const { file, ...rest } = job; return rest; };
  const notify = (job) => {
    const clean = strip(job);
    renderJob(clean);
    listeners.forEach((listener) => { try { listener(clean); } catch (error) { console.error(error); } });
  };
  const onUpdate = (listener) => { listeners.add(listener); return () => listeners.delete(listener); };

  // --- Persistent cross-page status banner (inline-styled: pages mix .admin-toast and
  // .staff-toast CSS, so this can't depend on either). Bottom-left, so it never overlaps the
  // existing bottom-right per-page toast. ---
  const ensureBanner = () => {
    if (bannerEl && document.body.contains(bannerEl)) return bannerEl;
    bannerEl = document.createElement('div');
    bannerEl.id = 'datsJobBanner';
    bannerEl.style.cssText = 'position:fixed;left:16px;bottom:16px;z-index:2147483000;display:flex;flex-direction:column;gap:8px;max-width:340px;font-family:system-ui,-apple-system,Segoe UI,sans-serif;';
    document.body.appendChild(bannerEl);
    return bannerEl;
  };
  const describeJob = (job) => {
    const fileName = job.payload?.original_filename || job.payload?.fileName || 'document';
    if (job.kind === 'scan-upload') {
      if (job.status === 'saving') return `Archiving ${fileName}…`;
      if (job.status === 'done') return `${fileName} added to the archive.`;
      if (job.status === 'duplicate') return job.error || 'This document already exists for this student.';
      if (job.status === 'failed') return job.error || `Could not archive ${fileName}.`;
      return `Preparing to archive ${fileName}…`;
    }
    if (job.status === 'ocr-running') return `${job.progress?.label || `Reading ${fileName}`}${job.progress?.detail ? ` · ${job.progress.detail}` : ''}`;
    if (job.status === 'saving') {
      return (job.kind === 'gradesheet-upload' || job.kind === 'gradesheet-rescan')
        ? `Transcribing ${fileName} with Gemini AI…`
        : `Saving grades from ${fileName}…`;
    }
    if (job.status === 'done') {
      const summary = job.resultSummary?.summary || job.resultSummary;
      return summary?.entries ? `${fileName}: ${summary.students} students, ${summary.entries} grade records read.` : `${fileName}: no grade rows could be read.`;
    }
    if (job.status === 'failed') return job.error || `Could not read ${fileName}.`;
    return `Queued: ${job.kind === 'gradesheet-rescan' ? 're-scanning' : 'reading'} ${fileName}…`;
  };
  const renderJob = (job) => {
    const container = ensureBanner();
    let row = jobRows.get(job.id);
    if (!row) {
      row = document.createElement('div');
      row.style.cssText = 'background:#1c1c1c;color:#fff;border-left:3px solid #c9a227;padding:10px 30px 10px 12px;border-radius:4px;font-size:12px;line-height:1.4;box-shadow:0 4px 14px rgba(0,0,0,.25);position:relative;';
      const text = document.createElement('div');
      text.className = 'dats-job-text';
      const barTrack = document.createElement('div');
      barTrack.className = 'dats-job-track';
      barTrack.style.cssText = 'margin-top:6px;height:3px;background:rgba(255,255,255,.2);border-radius:2px;overflow:hidden;display:none;';
      const barFill = document.createElement('div');
      barFill.style.cssText = 'height:100%;width:0%;background:#c9a227;';
      barTrack.appendChild(barFill);
      const linkRow = document.createElement('div');
      linkRow.className = 'dats-job-link';
      linkRow.style.cssText = 'margin-top:6px;display:none;';
      const link = document.createElement('a');
      link.textContent = 'Review the existing document';
      link.style.cssText = 'color:#f5d78e;text-decoration:underline;';
      linkRow.appendChild(link);
      const closeButton = document.createElement('button');
      closeButton.type = 'button';
      closeButton.setAttribute('aria-label', 'Dismiss');
      closeButton.textContent = '×';
      closeButton.style.cssText = 'position:absolute;top:4px;right:8px;background:none;border:0;color:#bbb;cursor:pointer;font-size:16px;line-height:1;display:none;';
      closeButton.addEventListener('click', () => { row.remove(); jobRows.delete(job.id); ack(job.id); });
      row.append(text, barTrack, linkRow, closeButton);
      jobRows.set(job.id, row);
      container.appendChild(row);
    }
    const text = row.querySelector('.dats-job-text');
    const barTrack = row.querySelector('.dats-job-track');
    const barFill = barTrack.firstElementChild;
    const linkRow = row.querySelector('.dats-job-link');
    const link = linkRow.querySelector('a');
    const closeButton = row.querySelector('button');

    const isError = job.status === 'failed';
    const isDuplicate = job.status === 'duplicate';
    row.style.borderLeftColor = isError ? '#c0392b' : isDuplicate ? '#d08b1d' : '#c9a227';
    text.textContent = describeJob(job);

    const showProgress = job.status === 'ocr-running' || job.status === 'saving';
    barTrack.style.display = showProgress ? 'block' : 'none';
    if (showProgress) barFill.style.width = `${Math.round(Math.max(0, Math.min(1, job.status === 'saving' ? 1 : (job.progress?.value || 0))) * 100)}%`;

    const showLink = isDuplicate && job.duplicate?.id && job.duplicate?.student_id;
    linkRow.style.display = showLink ? 'block' : 'none';
    if (showLink) link.href = `${appBase}/admin/student-record-view.php?student_id=${encodeURIComponent(job.duplicate.student_id)}&document_id=${encodeURIComponent(job.duplicate.id)}`;

    closeButton.style.display = (isError || isDuplicate || job.status === 'done') ? 'block' : 'none';
    if (job.status === 'done' && !row.dataset.autoDismiss) {
      row.dataset.autoDismiss = '1';
      window.setTimeout(() => { if (jobRows.get(job.id) === row) { row.remove(); jobRows.delete(job.id); ack(job.id); } }, 5000);
    }
  };

  // --- Talking to the service worker ---
  const trySendToSw = async (message) => {
    if (!supportsSw || !swReady) return false;
    try {
      const registration = await Promise.race([
        swReady,
        new Promise((resolve) => setTimeout(() => resolve(null), 800)),
      ]);
      const target = registration?.active || navigator.serviceWorker.controller;
      if (!target) return false;
      target.postMessage(message);
      return true;
    } catch (error) {
      return false;
    }
  };
  const ack = (jobId) => { trySendToSw({ type: 'ACK_JOB', jobId }); };

  if (supportsSw) {
    const swUrl = `${appBase}/sw.js`;
    const swScope = `${appBase}/`;
    swReady = navigator.serviceWorker.register(swUrl, { scope: swScope })
      .then(() => Promise.race([
        navigator.serviceWorker.ready,
        new Promise((resolve) => setTimeout(() => resolve(null), 2500)),
      ]))
      .catch((error) => { console.warn('Background job worker unavailable; uploads will not survive navigation.', error); return null; });

    navigator.serviceWorker.addEventListener('message', (event) => {
      const data = event.data || {};
      if (data.type === 'JOB_UPDATE' && data.job) notify(data.job);
      else if (data.type === 'JOBS_SNAPSHOT' && Array.isArray(data.jobs)) data.jobs.forEach(notify);
      else if (data.type === 'RUN_OCR') runOcr(data);
    });

    const sendReady = () => { trySendToSw({ type: 'CLIENT_READY' }); };
    sendReady();
    document.addEventListener('visibilitychange', () => { if (!document.hidden) sendReady(); });
  }

  // --- OCR relay / inline fallback: runs the actual OCR pipeline on whichever page is open,
  // since that needs real DOM/canvas/Worker APIs the service worker itself doesn't have. ---
  const loadEngineScript = (src) => new Promise((resolve, reject) => {
    if (document.querySelector(`script[src="${src}"]`)) { resolve(); return; }
    const script = document.createElement('script');
    script.src = src;
    script.onload = resolve;
    script.onerror = () => reject(new Error(`Failed to load ${src}`));
    document.head.appendChild(script);
  });
  const ensureGradesheetEngine = async () => {
    if (!window.DatsGradesheetFlow) await loadEngineScript(`${appBase}/admin/gradesheet-flow.js`);
    if (!window.DatsGradesheetOcrEngine) await loadEngineScript(`${appBase}/admin/gradesheet-ocr-engine.js`);
  };

  // The engine module memoizes a single set of Tesseract workers (and a single in-flight
  // "current progress callback") per page, so two OCR runs on this page can never overlap —
  // the SW may dispatch RUN_OCR for several queued files back-to-back, and the no-SW fallback
  // path below can be asked to start a new file before a previous one finishes. Both funnel
  // through this chain so files are still read one at a time, same as before this change.
  let ocrChain = Promise.resolve();
  const runOcrSerialized = (file, onProgress) => {
    const run = ocrChain.then(() => ensureGradesheetEngine().then(() => window.DatsGradesheetOcrEngine.ocrFile(file, onProgress)));
    ocrChain = run.catch(() => {});
    return run;
  };

  const runOcr = async (data) => {
    const { jobId, engine, file } = data;
    try {
      if (engine !== 'gradesheet') throw new Error(`Unknown OCR engine: ${engine}`);
      const result = await runOcrSerialized(file, (pageIndex, pageCount, fraction, stage) => {
        trySendToSw({
          type: 'OCR_PROGRESS',
          jobId,
          value: (pageIndex + Math.min(1, Math.max(0, fraction))) / Math.max(1, pageCount),
          label: stage,
          detail: `Page ${pageIndex + 1} of ${pageCount}`,
        });
      });
      await trySendToSw({ type: 'OCR_RESULT', jobId, ok: true, ocrText: result.ocrText, ocrStatus: result.ocrStatus, ocrError: result.ocrError, truncated: result.truncated });
    } catch (error) {
      console.error('Background OCR failed:', error);
      await trySendToSw({ type: 'OCR_RESULT', jobId, ok: false, ocrError: error?.message || 'OCR failed in the browser.' });
    }
  };

  // --- Inline fallback path (no service worker available): does the exact same save the
  // worker would have done, directly on this page. Loses nothing except navigation-survival. ---
  const runInlineSave = async (job) => {
    notify({ ...job, status: 'saving' });
    let response;
    try {
      if (job.kind === 'scan-upload') {
        const formData = new FormData();
        Object.entries(job.payload || {}).forEach(([key, value]) => formData.append(key, value));
        if (job.file) formData.append('file', job.file, job.payload?.original_filename || 'document');
        response = await fetch(`${appBase}/api.php?resource=documents`, { method: 'POST', credentials: 'same-origin', body: formData });
      } else if (job.kind === 'gradesheet-upload') {
        const formData = new FormData();
        formData.append('file', job.file, job.payload?.fileName || 'gradesheet');
        formData.append('ocr_text', job.result?.ocrText || '');
        formData.append('ocr_status', job.result?.ocrStatus || '');
        formData.append('ocr_error', job.result?.ocrError || '');
        response = await fetch(`${appBase}/api.php?resource=gradesheet-batches`, { method: 'POST', credentials: 'same-origin', body: formData });
      } else {
        response = await fetch(`${appBase}/api.php?resource=gradesheet-batches`, {
          method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'rescan', batch_id: job.payload?.batchId, ocr_text: job.result?.ocrText || '', ocr_status: job.result?.ocrStatus || '', ocr_error: job.result?.ocrError || '' }),
        });
      }
    } catch (error) {
      notify({ ...job, status: 'failed', error: 'Unable to reach the server. Check that Apache and MariaDB are running.' });
      return;
    }
    const result = await response.json().catch(() => ({}));
    if (!response.ok) {
      if (response.status === 409 && result.duplicate) notify({ ...job, status: 'duplicate', duplicate: result.duplicate, error: result.error });
      else notify({ ...job, status: 'failed', error: result.error || 'The server could not complete the request.' });
      return;
    }
    const summary = (result.data && result.data.summary) ? { ...result.data.summary, ...result.data } : result.data;
    notify({ ...job, status: 'done', resultSummary: summary });
  };
  const runJobInline = async (job) => {
    if (job.kind === 'scan-upload' || job.kind === 'gradesheet-upload' || job.kind === 'gradesheet-rescan') {
      await runInlineSave(job);
      return;
    }
    notify({ ...job, status: 'ocr-running', progress: { value: 0, label: 'Starting OCR…', detail: '' } });
    const result = await runOcrSerialized(job.file, (pageIndex, pageCount, fraction, stage) => {
      notify({ ...job, status: 'ocr-running', progress: { value: (pageIndex + Math.min(1, Math.max(0, fraction))) / Math.max(1, pageCount), label: stage, detail: `Page ${pageIndex + 1} of ${pageCount}` } });
    });
    await runInlineSave({ ...job, result: { ocrText: result.ocrText, ocrStatus: result.ocrStatus, ocrError: result.ocrError, truncated: result.truncated } });
  };

  const enqueue = async (kind, payload, file) => {
    const job = { id: generateId(), kind, payload, file: file || null };
    notify({ ...job, status: 'queued' });
    const sent = await trySendToSw({ type: 'ENQUEUE_JOB', job });
    if (!sent) {
      runJobInline(job).catch((error) => {
        console.error(error);
        notify({ ...job, status: 'failed', error: error?.message || 'Unable to complete this request.' });
      });
    }
    return job.id;
  };

  return { enqueue, onUpdate, ack, isAvailable: () => supportsSw };
}());

document.addEventListener('DOMContentLoaded', async () => {
  const getGreetingText = (userName = 'User') => {
    const hour = new Date().getHours();
    const label = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
    return `${label}, ${userName}`;
  };

  const initialsOf = (name = 'User') => {
    const parts = String(name).trim().split(/\s+/);
    return `${parts[0]?.[0] || ''}${parts.at(-1)?.[0] || ''}`.toUpperCase() || 'U';
  };

  const applyProfileToChrome = (profile) => {
    document.querySelectorAll('.user-chip strong').forEach((element) => { element.textContent = profile.full_name; });
    document.querySelectorAll('.user-chip .avatar').forEach((element) => { element.textContent = initialsOf(profile.full_name); });
    document.querySelectorAll('.admin-header h1, .staff-header h1').forEach((heading) => {
      const greetingEl = heading.querySelector('[data-greeting]');
      if (greetingEl) {
        greetingEl.dataset.userName = profile.full_name;
        greetingEl.textContent = getGreetingText(profile.full_name);
        return;
      }
      heading.textContent = getGreetingText(profile.full_name);
    });
  };

  let profileTip = null;

  const updateTimeGreeting = () => {
    const profileName = document.querySelector('.user-chip strong')?.textContent?.trim() || 'User';
    const headingNames = [...document.querySelectorAll('.admin-header h1, .staff-header h1')];

    headingNames.forEach((heading) => {
      const greetingEl = heading.querySelector('[data-greeting]');
      if (greetingEl) {
        greetingEl.dataset.userName = profileName;
        greetingEl.textContent = getGreetingText(profileName);
        return;
      }

      heading.textContent = getGreetingText(profileName);
    });
  };

  const dateTimeFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'full',
    timeStyle: 'medium',
  });
  let lastGreetingHour = -1;

  const updateDateTime = () => {
    const now = new Date();
    const value = dateTimeFormatter.format(now);
    document.querySelectorAll('[data-current-datetime]').forEach((element) => { element.textContent = value; });
    if (now.getHours() !== lastGreetingHour) {
      lastGreetingHour = now.getHours();
      updateTimeGreeting();
    }
  };

  let clockTimer = null;
  const startClock = () => {
    window.clearTimeout(clockTimer);
    updateDateTime();
    // Re-align on every tick so the seconds flip with the system clock instead of drifting.
    clockTimer = window.setTimeout(startClock, 1000 - (Date.now() % 1000));
  };
  startClock();
  document.addEventListener('visibilitychange', () => { if (!document.hidden) startClock(); });

  const appBase = window.DatsAppBase;
  const apiUrl = `${window.location.origin}${appBase}/api.php`;
  const profileStyles = document.createElement('link');
  profileStyles.rel = 'stylesheet';
  profileStyles.href = `${window.location.origin}${appBase}/profile.css`;
  document.head.append(profileStyles);
  const request = async (resource) => {
    try {
      const response = await fetch(`${apiUrl}?resource=${resource}`, { credentials: 'same-origin' });
      if (!response.ok) return null;
      const result = await response.json();
      return result.data;
    } catch (error) {
      return null;
    }
  };

  const recentList = document.querySelector('.recent-list');
  const renderRecentUploads = (uploads) => {
    if (!recentList || !Array.isArray(uploads)) return;
    recentList.replaceChildren();
    if (!uploads.length) {
      const empty = document.createElement('p');
      empty.className = 'recent-empty';
      empty.textContent = 'No uploaded records yet.';
      recentList.append(empty);
      return;
    }
    uploads.forEach((upload) => {
      const row = document.createElement('div');
      const icon = document.createElement('span');
      const archived = upload.status === 'Archived';
      icon.className = `recent-file${archived ? '' : ' gold-file'}`;
      icon.innerHTML = `<i data-lucide="${archived ? 'file-check-2' : 'file-image'}"></i>`;
      const details = document.createElement('p');
      const filename = document.createElement('strong');
      filename.textContent = upload.original_filename || 'Unnamed document';
      const metadata = document.createElement('small');
      metadata.textContent = `${upload.student_number || 'No student number'} · ${upload.uploaded_at || 'Unknown time'}`;
      details.append(filename, metadata);
      const status = document.createElement('span');
      status.className = `pill ${archived || upload.status === 'Uploaded' ? 'success' : 'pending'}`;
      status.textContent = upload.status || 'Processing';
      row.append(icon, details, status);
      recentList.append(row);
    });
    if (window.lucide) lucide.createIcons();
  };
  if (recentList) {
    request('uploads').then(renderRecentUploads);
    window.addEventListener('dats:uploads-changed', async () => renderRecentUploads(await request('uploads')));
  }

  const userChips = document.querySelectorAll('.user-chip');
  if (userChips.length) {
    const profile = await request('profile');
    if (profile) {
      const roleLabels = { Admin: 'System Administrator', Staff: 'Registrar Staff' };
      const roleLabel = roleLabels[profile.role] || 'User';
      const tip = document.createElement('div');
      tip.className = 'profile-tip';
      tip.hidden = true;
      tip.setAttribute('role', 'menu');
      tip.innerHTML = `<div class="profile-tip-heading"><span class="avatar"></span><div><strong></strong><small></small></div></div><span class="profile-tip-role"></span><div class="profile-tip-links"><a href="${appBase}/admin/settings.php" role="menuitem"><i data-lucide="settings-2"></i> Account settings</a><a class="profile-tip-logout" href="${appBase}/logout.php" role="menuitem"><i data-lucide="log-out"></i> Log out</a></div>`;
      tip.querySelector('.avatar').textContent = initialsOf(profile.full_name);
      tip.querySelector('.profile-tip-heading strong').textContent = profile.full_name || 'User';
      tip.querySelector('.profile-tip-heading small').textContent = profile.email || '';
      tip.querySelector('.profile-tip-role').textContent = roleLabel;
      document.body.append(tip);
      profileTip = tip;

      let openChip = null;
      const closeTip = () => {
        tip.hidden = true;
        openChip?.setAttribute('aria-expanded', 'false');
        openChip = null;
      };
      const openTip = (chip) => {
        document.querySelector('.notification-panel')?.setAttribute('hidden', '');
        document.querySelector('.header-icon[aria-label="Notifications"]')?.setAttribute('aria-expanded', 'false');
        const rect = chip.getBoundingClientRect();
        tip.style.top = `${rect.bottom + 10}px`;
        tip.style.right = `${Math.max(12, window.innerWidth - rect.right)}px`;
        tip.hidden = false;
        chip.setAttribute('aria-expanded', 'true');
        openChip = chip;
      };
      const toggleTip = (chip) => { if (openChip === chip) { closeTip(); } else { openTip(chip); } };

      userChips.forEach((chip) => {
        chip.setAttribute('role', 'button');
        chip.setAttribute('tabindex', '0');
        chip.setAttribute('aria-haspopup', 'true');
        chip.setAttribute('aria-expanded', 'false');
        chip.setAttribute('aria-label', 'Open account menu');
        chip.addEventListener('click', (event) => { event.stopPropagation(); toggleTip(chip); });
        chip.addEventListener('keydown', (event) => {
          if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); toggleTip(chip); }
        });
      });
      document.addEventListener('click', (event) => { if (!tip.hidden && !tip.contains(event.target)) closeTip(); });
      document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeTip(); });
      window.addEventListener('scroll', () => { if (!tip.hidden) closeTip(); }, true);
      window.addEventListener('resize', () => { if (!tip.hidden) closeTip(); });
      if (window.lucide) lucide.createIcons();
    }
  }

  document.querySelectorAll('.settings-toggle-visibility').forEach((button) => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.target);
      if (!input) return;
      const showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
      button.innerHTML = `<i data-lucide="${showing ? 'eye' : 'eye-off'}"></i>`;
      if (window.lucide) lucide.createIcons();
    });
  });

  document.querySelectorAll('.account-settings-form').forEach((form) => {
    const message = form.querySelector('.settings-form-message, .profile-form-message');
    const saveHint = form.querySelector('.settings-save-hint');
    const discardButton = form.querySelector('.settings-discard');
    const mismatchHint = form.querySelector('#settingsPasswordMismatch');
    const passwordInput = form.elements.password;
    const confirmInput = form.querySelector('#settingsPasswordConfirm');
    const trackedFields = ['full_name', 'email'];
    let initialValues = trackedFields.reduce((acc, name) => { acc[name] = form.elements[name]?.value ?? ''; return acc; }, {});

    const isDirty = () => trackedFields.some((name) => form.elements[name]?.value !== initialValues[name]) || Boolean(passwordInput?.value);

    const refreshDirtyState = () => {
      const dirty = isDirty();
      if (saveHint) saveHint.hidden = !dirty;
      if (discardButton) discardButton.hidden = !dirty;
    };

    form.addEventListener('input', () => {
      if (mismatchHint) mismatchHint.hidden = true;
      refreshDirtyState();
    });

    discardButton?.addEventListener('click', () => {
      trackedFields.forEach((name) => { if (form.elements[name]) form.elements[name].value = initialValues[name]; });
      if (passwordInput) passwordInput.value = '';
      if (confirmInput) confirmInput.value = '';
      if (mismatchHint) mismatchHint.hidden = true;
      if (message) message.textContent = '';
      refreshDirtyState();
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (passwordInput?.value && confirmInput && passwordInput.value !== confirmInput.value) {
        if (mismatchHint) mismatchHint.hidden = false;
        confirmInput.focus();
        return;
      }
      if (message) message.textContent = 'Saving changes...';
      const response = await fetch(`${apiUrl}?resource=profile`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(Object.fromEntries(new FormData(form))),
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok) {
        if (message) message.textContent = result.error || 'Unable to update your profile.';
        return;
      }
      if (passwordInput) passwordInput.value = '';
      if (confirmInput) confirmInput.value = '';
      initialValues = trackedFields.reduce((acc, name) => { acc[name] = result.data[name] ?? ''; return acc; }, {});
      refreshDirtyState();
      applyProfileToChrome(result.data);
      if (message) message.textContent = 'Profile updated successfully.';
      const toast = document.querySelector('#adminToast, #staffToast');
      if (toast) {
        toast.textContent = 'Profile updated successfully.';
        toast.classList.add('show');
        window.setTimeout(() => toast.classList.remove('show'), 3200);
      }
    });

    refreshDirtyState();
  });

  const notificationButton = document.querySelector('.header-icon[aria-label="Notifications"]');
  if (notificationButton) {
    const badge = notificationButton.querySelector('span');
    const panel = document.createElement('div');
    panel.className = 'notification-panel';
    panel.hidden = true;
    notificationButton.setAttribute('aria-expanded', 'false');
    panel.innerHTML = '<div class="notification-panel-heading"><strong>Notifications</strong><button type="button" class="notification-close" aria-label="Close notifications">&times;</button></div><div class="notification-list"></div>';
    notificationButton.parentElement.append(panel);

    const list = panel.querySelector('.notification-list');

    const syncNotifications = async () => {
      const notifications = (await request('notifications')) || [];
      const unread = notifications.filter((notification) => !Number(notification.is_read));

      if (badge) {
        badge.textContent = unread.length ? String(unread.length) : '';
        badge.classList.toggle('notification-count', unread.length > 0);
      }

      if (!notifications.length) {
        list.innerHTML = '<p class="notification-empty">No notifications yet.</p>';
        return;
      }

      const notificationIcon = (resource) => ({
        users: 'user-plus',
        documents: 'file-plus-2',
        students: 'trash-2',
      }[resource] || 'bell');

      list.replaceChildren(...notifications.map((notification) => {
        const item = document.createElement('article');
        item.className = `notification-item${Number(notification.is_read) ? '' : ' unread'}`;
        item.innerHTML = '<span class="notification-icon"><i data-lucide=""></i></span><div class="notification-body"><strong></strong><p></p><time></time></div>';
        item.querySelector('[data-lucide]').setAttribute('data-lucide', notificationIcon(notification.resource));
        item.querySelector('strong').textContent = notification.title;
        item.querySelector('p').textContent = notification.message;
        item.querySelector('time').textContent = new Date(notification.created_at.replace(' ', 'T')).toLocaleString();
        return item;
      }));
      if (window.lucide) lucide.createIcons();
    };

    notificationButton.addEventListener('click', async () => {
      panel.hidden = !panel.hidden;
      notificationButton.setAttribute('aria-expanded', String(!panel.hidden));
      if (!panel.hidden && profileTip) profileTip.hidden = true;

      if (panel.hidden) {
        return;
      }

      await syncNotifications();
      const notifications = (await request('notifications')) || [];
      const unread = notifications.filter((notification) => !Number(notification.is_read));
      if (unread.length) {
        await fetch(`${apiUrl}?resource=notifications`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({})
        });
        await syncNotifications();
      }
    });

    panel.querySelector('.notification-close').addEventListener('click', () => {
      panel.hidden = true;
      notificationButton.setAttribute('aria-expanded', 'false');
    });

    document.addEventListener('click', (event) => {
      if (!notificationButton.parentElement.contains(event.target)) {
        panel.hidden = true;
        notificationButton.setAttribute('aria-expanded', 'false');
      }
    });

    await syncNotifications();
  }

  const dashboardPercent = (value) => Math.min(100, Math.max(0, Number(value) || 0));
  const formatStorageBytes = (value) => {
    const bytes = Math.max(0, Number(value) || 0);
    if (bytes >= 1024 ** 4) return `${(bytes / 1024 ** 4).toFixed(1)} TB`;
    if (bytes >= 1024 ** 3) return `${(bytes / 1024 ** 3).toFixed(1)} GB`;
    if (bytes >= 1024 ** 2) return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
    if (bytes >= 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${bytes.toLocaleString()} B`;
  };
  const setDashboardText = (selector, value) => {
    const element = document.querySelector(selector);
    if (element) element.textContent = value;
  };
  const setDashboardProgress = (key, rate) => {
    const bar = document.querySelector(`[data-dashboard-progress="${key}"]`);
    if (!bar) return;
    const percentage = dashboardPercent(rate);
    bar.style.width = `${percentage}%`;
    const progress = bar.closest('[role="progressbar"]');
    progress?.setAttribute('aria-valuenow', String(percentage));
    progress?.setAttribute('aria-valuetext', `${percentage.toFixed(1)}%`);
  };
  const renderDashboardError = (message) => {
    const error = document.querySelector('[data-dashboard-error]');
    if (!error) return;
    error.textContent = message;
    error.hidden = false;
  };
  const createProgressRow = (title, detail, percentage) => {
    const row = document.createElement('div');
    row.className = 'dashboard-progress-row';
    const heading = document.createElement('div');
    const name = document.createElement('strong');
    const value = document.createElement('span');
    name.textContent = title;
    value.textContent = detail;
    heading.append(name, value);
    const track = document.createElement('div');
    track.className = 'dashboard-progress-track';
    track.setAttribute('role', 'progressbar');
    track.setAttribute('aria-label', `${title}: ${detail}`);
    track.setAttribute('aria-valuemin', '0');
    track.setAttribute('aria-valuemax', '100');
    track.setAttribute('aria-valuenow', String(dashboardPercent(percentage)));
    const bar = document.createElement('span');
    bar.style.width = `${dashboardPercent(percentage)}%`;
    track.append(bar);
    row.append(heading, track);
    return row;
  };
  const renderDashboardAnalytics = (analytics) => {
    setDashboardText('[data-dashboard="archived_documents"]', Number(analytics.archived_documents || 0).toLocaleString());
    const coverageRate = dashboardPercent(analytics.student_archive_coverage_rate);
    setDashboardText('[data-dashboard="student_archive_coverage_rate"]', `${coverageRate.toFixed(1)}%`);
    setDashboardText('[data-dashboard-detail="covered_students"]', `${Number(analytics.students_with_archived_documents || 0).toLocaleString()} of ${Number(analytics.eligible_students || 0).toLocaleString()}`);
    setDashboardProgress('student_archive_coverage_rate', coverageRate);

    setDashboardText('[data-dashboard="archived_this_week"]', Number(analytics.archived_this_week || 0).toLocaleString());
    const weekChange = Number(analytics.archived_week_change || 0);
    setDashboardText('[data-dashboard-detail="archived_week_change"]', `${weekChange >= 0 ? '+' : ''}${weekChange.toLocaleString()} vs last week`);

    const searchReadyRate = dashboardPercent(analytics.search_ready_rate);
    setDashboardText('[data-dashboard="search_ready_rate"]', `${searchReadyRate.toFixed(1)}%`);
    setDashboardText('[data-dashboard-detail="search_ready_count"]', `${Number(analytics.ocr_completed_archived || 0).toLocaleString()} of ${Number(analytics.archived_documents || 0).toLocaleString()}`);
    setDashboardProgress('search_ready_rate', searchReadyRate);
    setDashboardText('[data-dashboard-detail="archived_storage_bytes"]', formatStorageBytes(analytics.archived_storage_bytes));

    const coverage = document.querySelector('[data-dashboard="coverage_by_college"]');
    if (coverage) {
      const rows = Array.isArray(analytics.coverage_by_college) ? analytics.coverage_by_college : [];
      coverage.replaceChildren(...rows.map((college) => {
        const eligible = Number(college.eligible_students || 0);
        const covered = Number(college.covered_students || 0);
        const detail = eligible
          ? `${covered.toLocaleString()} of ${eligible.toLocaleString()} students · ${dashboardPercent(college.coverage_rate).toFixed(1)}%`
          : 'No registered students';
        return createProgressRow(college.college_name || 'Unnamed college', detail, eligible ? college.coverage_rate : 0);
      }));
      if (!rows.length) coverage.textContent = 'No active colleges found.';
    }

    const throughput = document.querySelector('[data-dashboard="archive_by_week"]');
    if (throughput) {
      const weeks = Array.isArray(analytics.archive_by_week) ? analytics.archive_by_week : [];
      const maximum = Math.max(1, ...weeks.map((week) => Number(week.document_count || 0)));
      throughput.replaceChildren(...weeks.map((week) => {
        const count = Number(week.document_count || 0);
        const column = document.createElement('div');
        column.className = 'throughput-column';
        const value = document.createElement('strong');
        value.textContent = count.toLocaleString();
        const track = document.createElement('div');
        track.className = 'throughput-track';
        track.setAttribute('aria-label', `${week.week_label}: ${count} archived records`);
        const bar = document.createElement('span');
        bar.style.height = `${count ? Math.max(8, (count / maximum) * 100) : 0}%`;
        track.append(bar);
        const label = document.createElement('small');
        label.textContent = week.week_label || week.week_start;
        column.append(value, track, label);
        return column;
      }));
      if (!weeks.length) throughput.textContent = 'No throughput data available.';
    }

    const categories = document.querySelector('[data-dashboard="archive_by_category"]');
    if (categories) {
      const rows = Array.isArray(analytics.archive_by_category) ? analytics.archive_by_category : [];
      const maximum = Math.max(1, ...rows.map((category) => Number(category.document_count || 0)));
      categories.replaceChildren(...rows.map((category) => {
        const count = Number(category.document_count || 0);
        return createProgressRow(
          category.category_name || 'Uncategorized',
          `${count.toLocaleString()} archived record${count === 1 ? '' : 's'}`,
          (count / maximum) * 100,
        );
      }));
      if (!rows.length) categories.textContent = 'No active document categories found.';
    }

    ['ocr_pending_archived', 'ocr_failed_archived', 'missing_file_path_count', 'missing_file_size_count'].forEach((key) => {
      setDashboardText(`[data-attention="${key}"]`, Number(analytics[key] || 0).toLocaleString());
    });

    const error = document.querySelector('[data-dashboard-error]');
    if (error) error.hidden = true;
  };
  const renderDashboardActivity = (activities) => {
    const feed = document.querySelector('[data-dashboard="dashboard_activity"]');
    if (!feed) return;
    if (!Array.isArray(activities) || !activities.length) {
      feed.textContent = 'No archive activity has been recorded yet.';
      return;
    }
    feed.replaceChildren(...activities.map((activity) => {
      const item = document.createElement('article');
      item.className = 'activity-feed-item';
      const marker = document.createElement('span');
      marker.className = 'activity-marker';
      const content = document.createElement('div');
      const title = document.createElement('strong');
      title.textContent = activity.description || activity.action || 'Archive activity';
      const context = document.createElement('p');
      context.textContent = [activity.category_name, activity.student_name, activity.document_name].filter(Boolean).join(' · ') || 'Archived record activity';
      const meta = document.createElement('small');
      const rawDate = String(activity.created_at || '').replace(' ', 'T');
      const parsedDate = rawDate ? new Date(rawDate) : null;
      const timestamp = parsedDate && !Number.isNaN(parsedDate.getTime()) ? parsedDate.toLocaleString() : 'Unknown time';
      meta.textContent = `${activity.user_name || 'System'} · ${timestamp}`;
      content.append(title, context, meta);
      const status = document.createElement('span');
      status.className = 'activity-status';
      status.textContent = activity.document_status || activity.action || 'Logged';
      item.append(marker, content, status);
      return item;
    }));
  };

  const analytics = await request('analytics');
  if (analytics) {
    renderDashboardAnalytics(analytics);
    ['total_students', 'total_documents', 'total_colleges', 'total_courses'].forEach((key) => {
      document.querySelectorAll(`[data-stat="${key}"]`).forEach((value) => { value.textContent = Number(analytics[key] || 0).toLocaleString(); });
    });
    document.querySelectorAll('[data-stat="pending_requests"]').forEach((value) => {
      value.textContent = Number(analytics.processing_documents || 0) + Number(analytics.documents_needing_review || 0);
    });
    const setStaffStat = (name, value) => {
      document.querySelectorAll(`[data-staff-stat="${name}"]`).forEach((element) => { element.textContent = value; });
    };
    setStaffStat('assigned', Number(analytics.queue_count || 0).toLocaleString());
    setStaffStat('due-today', Number(analytics.queue_count || 0).toLocaleString());
    setStaffStat('processed', Number(analytics.processed_this_week || 0).toLocaleString());
    setStaffStat('review', Number(analytics.documents_needing_review || 0).toLocaleString());
    setStaffStat('urgent', `${Number(analytics.review_count || 0).toLocaleString()} urgent`);
    const processedThisWeek = Number(analytics.processed_this_week || 0);
    const processedLastWeek = Number(analytics.processed_last_week || 0);
    const processedChange = processedLastWeek
      ? `${processedThisWeek >= processedLastWeek ? '+' : ''}${Math.round((processedThisWeek - processedLastWeek) / processedLastWeek * 100)}%`
      : '—';
    setStaffStat('processed-change', processedChange);
  } else if (document.querySelector('[data-dashboard-root]')) {
    renderDashboardError('Dashboard analytics could not be loaded. Navigation and quick actions remain available.');
  }

  const scanStudentSearch = document.querySelector('#scanStudentSearch');
  const scanCategory = document.querySelector('#scanCategory');
  if (scanStudentSearch && scanCategory) {
    const [students, categories, colleges] = await Promise.all([
      request('students'), request('categories'), request('colleges'),
    ]);
    if (categories) {
      scanCategory.replaceChildren(new Option('Select document type', ''));
      categories.forEach((category) => scanCategory.add(new Option(category.name, String(category.id))));
    }
    window.dispatchEvent(new CustomEvent('dats:scan-data-ready', {
      detail: { students: students || [], categories: categories || [], colleges: colleges || [] },
    }));
  }

  const studentRows = document.querySelector('#studentRows');
  if (studentRows) {
    const formatStudentName = (student) => {
      const lastName = String(student.last_name || '').trim();
      const givenNames = [student.first_name, student.middle_initial]
        .map((name) => String(name || '').trim())
        .filter(Boolean)
        .join(' ');
      return lastName ? `${lastName}, ${givenNames}` : givenNames || 'Unknown student';
    };
    const students = await request('students');
    if (students) {
      const sortedStudents = [...students].sort((left, right) => String(left.last_name || '').localeCompare(String(right.last_name || ''), undefined, { sensitivity: 'base' }));
      studentRows.replaceChildren(...sortedStudents.map((student) => {
        const row = document.createElement('tr');
        row.studentData = student;
        row.dataset.studentId = student.id;
        row.dataset.gender = student.gender;
        row.dataset.college = student.college_name || '';
        const birthdate = student.birthdate ? new Date(`${student.birthdate}T00:00:00`).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : 'Not provided';
        row.innerHTML = '<td><div class="student-identity"><span class="student-avatar"></span><span><strong></strong><em><i data-lucide="file-check-2"></i> Documents attached: <b></b></em></span></div></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td><span class="status-badge active-status"></span></td><td></td><td></td><td><button class="row-action student-row-action" type="button" aria-label="Open student actions"><i data-lucide="more-horizontal"></i><span>Actions</span></button></td>';
        const initials = `${student.first_name[0] || ''}${student.last_name[0] || ''}`;
        row.querySelector('.student-avatar').textContent = initials;
        row.querySelector('.student-identity strong').textContent = `${student.student_number || '—'} - ${formatStudentName(student)}`;
        row.querySelector('.student-identity b').textContent = `${student.document_count} file${Number(student.document_count) === 1 ? '' : 's'}`;
        row.children[1].textContent = student.college_name || 'Not assigned';
        row.children[2].textContent = student.course_name || 'Not assigned';
        row.children[3].textContent = student.academic_year || 'Not assigned';
        row.children[4].textContent = student.year_level || 'Not assigned';
        row.children[5].textContent = student.gender;
        row.children[6].innerHTML = `${birthdate}<br /><small>${student.birthdate ? `Age ${new Date().getFullYear() - new Date(`${student.birthdate}T00:00:00`).getFullYear()}` : 'Age unavailable'}</small>`;
        row.children[7].textContent = student.address || 'Not provided';
        row.children[8].textContent = student.civil_status;
        row.children[9].textContent = student.contact_number || 'Not provided';
        row.children[10].querySelector('.status-badge').textContent = student.document_count;
        row.children[11].textContent = student.status;
        row.children[12].textContent = student.created_at ? new Date(student.created_at.replace(' ', 'T')).toLocaleDateString() : 'Not available';
        return row;
      }));
      if (window.lucide) lucide.createIcons();
      window.dispatchEvent(new CustomEvent('dats:students-ready'));
    }
    const collegeFilter = document.querySelector('#studentCollege');
    const colleges = await request('colleges');
    if (collegeFilter && colleges) {
      collegeFilter.replaceChildren(new Option('All colleges', 'all'));
      colleges.forEach((college) => collegeFilter.add(new Option(college.name, college.name)));
    }
  }

  const categoryGrid = document.querySelector('#categoryGrid');
  if (categoryGrid) {
    const categories = await request('categories');
    if (categories) {
      categoryGrid.replaceChildren(...categories.map((category, index) => {
        const card = document.createElement('article');
        card.className = `category-card${index === 0 ? ' featured' : ''}`;
        card.dataset.categoryId = String(category.id);
        const iconClass = ['gold-icon', 'rose-icon', 'blue-icon'][index % 3] || '';
        const actionButton = document.createElement('button');
        actionButton.type = 'button';
        actionButton.className = 'category-edit-button';
        actionButton.innerHTML = '<i data-lucide="pencil"></i>';
        actionButton.setAttribute('aria-label', `Edit ${category.name}`);
        actionButton.addEventListener('click', () => {
          const modal = document.querySelector('#categoryModal');
          const form = document.querySelector('#categoryForm');
          const modalTitle = document.querySelector('#categoryModalTitle');
          const submitButton = form?.querySelector('.login-submit');
          if (!modal || !form || !modalTitle || !submitButton) return;

          form.dataset.mode = 'edit';
          form.dataset.categoryId = String(category.id);
          modalTitle.textContent = 'Edit document category';
          document.querySelector('#categoryName').value = category.name || '';
          submitButton.innerHTML = 'Save changes <i data-lucide="arrow-right"></i>';
          if (window.lucide) lucide.createIcons();
          modal.hidden = false;
          document.querySelector('#categoryName')?.focus();
        });
        card.innerHTML = `<div class="category-icon ${iconClass}"><i data-lucide="folder-open"></i></div><div><h4></h4></div><span class="category-count"></span>`;
        card.querySelector('h4').textContent = category.name;
        card.querySelector('.category-count').textContent = `${Number(category.document_count).toLocaleString()} files`;
        card.append(actionButton);
        return card;
      }));
      const total = document.querySelector('.category-summary strong');
      if (total) total.textContent = categories.length;
      if (window.lucide) lucide.createIcons();
    }
  }

  const historyRows = document.querySelector('#historyRows');
  if (historyRows) {
    const uploads = await request('uploads');
    if (uploads) {
      const now = new Date();
      const today = now.toDateString();
      const weekStart = new Date(now);
      const day = weekStart.getDay() || 7;
      weekStart.setHours(0, 0, 0, 0);
      weekStart.setDate(weekStart.getDate() - day + 1);
      const setHistoryStat = (name, value) => {
        document.querySelectorAll(`[data-history-stat="${name}"]`).forEach((element) => { element.textContent = value; });
      };
      setHistoryStat('total', uploads.length.toLocaleString());
      setHistoryStat('completed', uploads.filter((upload) => upload.status === 'Uploaded' || upload.status === 'Archived').length.toLocaleString());
      setHistoryStat('processing', uploads.filter((upload) => upload.status === 'Processing').length.toLocaleString());
      setHistoryStat('review', uploads.filter((upload) => upload.status === 'Needs review').length.toLocaleString());
      const rows = uploads.map((upload) => {
        const row = document.createElement('tr');
        const uploadedAt = new Date(upload.uploaded_at.replace(' ', 'T'));
        const fileStatus = upload.status === 'Archived' ? 'Uploaded' : upload.status;
        row.dataset.status = fileStatus;
        row.dataset.period = uploadedAt.toDateString() === today ? 'today' : uploadedAt >= weekStart ? 'week' : 'all';
        row.dataset.ocrStatus = upload.ocr_status || 'Pending';
        row.dataset.ocrText = upload.ocr_text || '';
        row.dataset.ocrError = upload.ocr_error || '';
        row.dataset.aiSummary = upload.ai_summary || '';
        row.dataset.aiDetectedCategory = upload.ai_detected_category || '';
        row.dataset.studentNumber = upload.student_number || '';
        row.dataset.categoryCode = upload.category_code || '';
        row.dataset.uploadedAt = uploadedAt.toLocaleString();
        row.innerHTML = '<td><div class="history-file"><span class="file-icon"><i data-lucide="file-text"></i></span><span><strong></strong><small></small></span></div></td><td class="mono-cell"></td><td></td><td></td><td><span class="history-status"></span></td><td><button class="row-action" type="button" aria-label="View upload"><i data-lucide="more-horizontal"></i></button></td>';
        row.querySelector('strong').textContent = upload.original_filename;
        row.querySelector('small').textContent = `${(upload.mime_type || 'file').split('/').pop().toUpperCase()} · ${Math.max(1, Math.round(Number(upload.file_size_bytes || 0) / 1024 / 1024 * 10) / 10)} MB`;
        row.querySelector('.mono-cell').textContent = upload.student_number;
        row.children[2].textContent = upload.category_code;
        row.children[3].textContent = uploadedAt.toLocaleString();
        row.querySelector('.history-status').textContent = fileStatus;
        row.querySelector('.history-status').className = `history-status ${fileStatus === 'Uploaded' || fileStatus === 'Archived' ? 'uploaded' : fileStatus === 'Processing' ? 'processing' : 'review-status'}`;
        row.querySelector('.row-action').setAttribute('aria-label', `View ${upload.original_filename} details`);
        return row;
      });
      if (!rows.length) {
        const emptyRow = document.createElement('tr');
        emptyRow.innerHTML = '<td colspan="6" class="history-empty">No uploads found for your account.</td>';
        rows.push(emptyRow);
      }
      historyRows.replaceChildren(...rows);
      if (window.lucide) lucide.createIcons();
    }
  }

  const userRows = document.querySelector('#userRows');
  if (userRows) {
    const users = await request('users');
    if (users) {
      userRows.replaceChildren(...users.map((user) => {
        const row = document.createElement('tr');
        row.dataset.userId = user.id;
        row.userData = user;
        const initials = `${user.full_name.split(' ')[0]?.[0] || ''}${user.full_name.split(' ').at(-1)?.[0] || ''}`.toUpperCase();
        // Tint each avatar from the name so rows are distinguishable at a glance.
        const avatarTints = ['maroon-avatar', 'gold-avatar', 'blue-avatar', 'rose-avatar'];
        const avatarTint = avatarTints[[...user.full_name].reduce((total, character) => total + character.charCodeAt(0), 0) % avatarTints.length];
        const lastLogin = user.last_login_at ? new Date(user.last_login_at.replace(' ', 'T')) : null;
        const dateText = lastLogin ? lastLogin.toLocaleDateString('en-US', { month: 'numeric', day: 'numeric', year: 'numeric' }) : 'Never';
        const timeText = lastLogin ? lastLogin.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit' }) : '';
        row.innerHTML = `<td><div class="table-user"><span class="user-avatar ${avatarTint}"></span><span><strong></strong><small></small></span></div></td><td class="user-email-cell"></td><td><div class="last-active"><span class="last-active-date">${dateText}</span>${timeText ? `<span class="last-active-time">${timeText}</span>` : ''}</div></td><td><span class="status-badge active-status"></span></td><td><button class="row-action" type="button" aria-label="More user options"><i data-lucide="more-horizontal"></i></button></td>`;
        row.querySelector('.user-avatar').textContent = initials;
        row.querySelector('strong').textContent = `${user.full_name} (${user.role || 'Admin'})`;
        row.querySelector('small').textContent = [user.id_number, user.role || 'Admin'].filter(Boolean).join(' | ');
        row.querySelector('.user-email-cell').textContent = user.email;
        row.querySelector('.status-badge').textContent = user.status;
        row.querySelector('.status-badge').className = `status-badge ${user.status === 'Active' ? 'active-status' : 'inactive-status'}`;
        return row;
      }));
      const summary = document.querySelectorAll('.user-summary strong');
      if (summary.length >= 2) {
        summary[0].textContent = users.length;
        summary[1].textContent = users.filter((user) => user.status === 'Active').length;
      }
      if (window.lucide) lucide.createIcons();
    }
  }

  const collegeList = document.querySelector('#collegeList');
  if (collegeList) {
    const colleges = await request('colleges');
    if (colleges) {
      collegeList.replaceChildren(...colleges.map((college, index) => {
        const card = document.createElement('article');
        card.className = 'college-card';
        card.dataset.name = college.name;
        card.dataset.id = college.id;
        const courses = college.courses || [];
        card.innerHTML = `<div class="college-heading"><div class="college-title"><span class="college-icon ${index % 2 ? 'gold-college' : 'maroon-college'}"><i data-lucide="building-2"></i></span><div><span class="panel-kicker">College ${String(index + 1).padStart(2, '0')}</span><h3></h3><small></small></div></div><div class="college-actions"><button class="mini-action" type="button" aria-label="College settings" data-edit-college><i data-lucide="settings-2"></i></button><button class="mini-action" type="button" aria-label="Add course" data-add-course=""><i data-lucide="plus"></i></button><button class="mini-action delete-college" type="button" aria-label="Delete college" data-delete-college><i data-lucide="trash-2"></i></button></div></div><div class="course-list"></div>`;
        card.querySelector('h3').textContent = college.name;
        card.querySelector('small').textContent = `${courses.length} courses · ${Number(college.student_count).toLocaleString()} students`;
        card.querySelector('[data-add-course]').dataset.addCourse = college.name;
        if (college.logo_path) {
          const logo = document.createElement('img');
          logo.src = `../${college.logo_path}`;
          logo.alt = `${college.name} logo`;
          logo.style.cssText = 'display: block; width: 100%; height: 100%; object-fit: contain; border-radius: 2px;';
          card.querySelector('.college-icon').replaceChildren(logo);
        }
        const courseList = card.querySelector('.course-list');
        courses.forEach((course) => {
          const item = document.createElement('div');
          item.dataset.id = course.id;
          item.innerHTML = `<span class="course-name"></span><b></b><button class="course-add-major" type="button" aria-label="Add major" data-add-major><i data-lucide="plus"></i></button><button class="course-edit" type="button" aria-label="Edit course" data-edit-course><i data-lucide="pencil"></i></button><button class="course-delete" type="button" aria-label="Delete course" data-delete-course><i data-lucide="x"></i></button>`;
          item.querySelector('.course-name').textContent = course.name;
          if (course.majors?.length) {
            const majorList = document.createElement('ul');
            majorList.className = 'major-list';
            course.majors.forEach((major) => {
              const majorItem = document.createElement('li');
              majorItem.textContent = `Major in ${major.name}`;
              majorList.append(majorItem);
            });
            item.querySelector('.course-name').append(majorList);
          }
          item.querySelector('b').textContent = `${Number(course.student_count || 0).toLocaleString()} student${Number(course.student_count || 0) === 1 ? '' : 's'}`;
          courseList.append(item);
        });
        return card;
      }));
      if (window.lucide) lucide.createIcons();
    }
  }

  const dashboardActivity = document.querySelector('[data-dashboard="dashboard_activity"]');
  if (dashboardActivity) {
    const activities = await request('activity');
    if (activities) renderDashboardActivity(activities);
    else dashboardActivity.textContent = 'Recent activity could not be loaded.';
  }

  const requestList = document.querySelector('#requests .request-list');
  if (requestList) {
    const requests = await request('requests');
    if (requests) {
      requestList.replaceChildren(...requests.map((requestItem, index) => {
        const row = document.createElement('div');
        row.className = 'request-row';
        row.innerHTML = `<span class="request-icon ${index === 0 ? 'gold' : index === 1 ? 'blue' : 'rose'}"><i data-lucide="file-check-2"></i></span><div><strong></strong><small></small></div><span class="request-status urgent">Archived</span>`;
        row.querySelector('strong').textContent = requestItem.category_name;
        row.querySelector('small').textContent = `${requestItem.student_name} · ${requestItem.uploaded_by_name} · ${new Date(requestItem.uploaded_at.replace(' ', 'T')).toLocaleString()}`;
        return row;
      }));
      if (window.lucide) lucide.createIcons();
    }
  }

  const taskPanel = document.querySelector('.task-panel');
  const taskList = taskPanel?.querySelector('.task-list');
  const taskQueueLabel = taskPanel?.querySelector('.queue-label');
  if (taskList) {
    const queueItems = await request('requests');
    if (Array.isArray(queueItems) && queueItems.length > 0) {
      taskList.replaceChildren(...queueItems.slice(0, 6).map((taskItem, index) => {
        const row = document.createElement('button');
        row.type = 'button';
        row.className = 'task-row';
        row.dataset.task = taskItem.category_name || 'Document review';
        row.innerHTML = `<span class="task-icon ${index % 3 === 0 ? 'gold' : index % 3 === 1 ? 'blue' : 'rose'}"><i data-lucide="file-text"></i></span><span><strong></strong><small></small></span><span class="task-status ${taskItem.status === 'Needs review' ? 'review' : 'urgent'}"></span><i data-lucide="arrow-up-right"></i>`;
        row.querySelector('strong').textContent = taskItem.category_name || 'Document review';
        row.querySelector('small').textContent = `${taskItem.student_name || 'Student'} · ${taskItem.status === 'Needs review' ? 'Due today' : 'Pending review'}`;
        row.querySelector('.task-status').textContent = taskItem.status === 'Needs review' ? 'Review' : 'Processing';
        return row;
      }));
      if (taskQueueLabel) taskQueueLabel.textContent = `${queueItems.length} open`;
    } else {
      if (taskQueueLabel) taskQueueLabel.textContent = '0 open';
      taskList.innerHTML = '<p class="notification-empty">No pending tasks for review.</p>';
    }
    if (window.lucide) lucide.createIcons();
  }

  const reviewList = document.querySelector('#reviewList');
  const reviewType = document.querySelector('#reviewType');
  if (reviewType) {
    const categories = await request('categories');
    if (categories) {
      reviewType.replaceChildren(new Option('All document types', 'all'));
      categories.forEach((category) => reviewType.add(new Option(category.name, category.name)));
    }
  }
  if (reviewList) {
    const reviews = await request('reviews');
    if (reviews) {
      reviewList.replaceChildren(...reviews.map((review) => {
        const card = document.createElement('article');
        card.className = 'review-card';
        card.innerHTML = '<div class="review-preview"><div class="fake-document transcript"><span></span><small></small></div><span class="preview-label">Archive document</span></div><div class="review-info"><div class="review-title"><div><span class="panel-kicker"></span><h3></h3><p class="review-student"></p></div><span class="request-status review">Pending</span></div><p class="review-meta"></p><div class="review-ai-note" hidden style="font-size:11px;padding:8px 10px;border-radius:6px;background:#f8efd5;color:#4a111b;margin:6px 0;"></div><button type="button" class="review-ocr-toggle" hidden style="background:none;border:0;padding:0;margin:0 0 6px;color:#6b1d2a;font-size:10px;font-weight:700;cursor:pointer;text-decoration:underline;">View extracted text</button><div class="review-ocr-text" hidden style="font-size:11px;max-height:120px;overflow:auto;background:#faf8f6;border:1px solid #e8dedb;border-radius:6px;padding:8px;margin-bottom:8px;white-space:pre-wrap;"></div><div class="review-actions"><button class="login-submit" type="button" data-action="approve" data-document-id="">Approve</button><button class="outline-button" type="button" data-action="reject" data-document-id=""></button></div></div>';
        const preview = card.querySelector('.review-preview');
        const fakeDocument = card.querySelector('.fake-document');
        if (review.file_path && review.mime_type?.startsWith('image/')) {
          const image = document.createElement('img');
          image.src = `../${review.file_path}`;
          image.alt = `${review.original_filename} preview`;
          image.style.cssText = 'width:100%;height:100%;object-fit:contain;';
          fakeDocument.replaceWith(image);
        } else if (review.file_path && review.mime_type === 'application/pdf') {
          const pdf = document.createElement('iframe');
          pdf.src = `../${review.file_path}`;
          pdf.title = `${review.original_filename} preview`;
          pdf.style.cssText = 'width:100%;height:100%;border:0;';
          fakeDocument.replaceWith(pdf);
        } else {
          fakeDocument.querySelector('span').textContent = review.category_name;
          fakeDocument.querySelector('small').textContent = `ZPPSU · ${review.academic_year || '2026'}`;
        }
        card.querySelector('.panel-kicker').textContent = review.category_name;
        card.querySelector('h3').textContent = review.original_filename;
        card.querySelector('.review-student').textContent = `${review.student_name} · ${review.student_number}`;
        card.querySelector('.review-meta').textContent = `${review.upload_source} · ${new Date(review.uploaded_at.replace(' ', 'T')).toLocaleString()}`;
        card.querySelector('[data-action="reject"]').textContent = 'Reject';
        card.querySelectorAll('[data-document-id]').forEach((button) => { button.dataset.documentId = review.document_id || review.id; });

        const aiNote = card.querySelector('.review-ai-note');
        const staffCategory = (review.category_name || '').trim();
        const aiCategory = (review.ai_detected_category || '').trim();
        if (aiCategory && aiCategory !== staffCategory) {
          aiNote.textContent = `AI check: staff filed this as "${staffCategory}", but the extracted text looks more like "${aiCategory}" — please verify before approving.`;
          aiNote.hidden = false;
        } else if (aiCategory) {
          aiNote.textContent = `AI check: extracted text matches the filed category (${aiCategory}).`;
          aiNote.hidden = false;
        }

        const ocrToggle = card.querySelector('.review-ocr-toggle');
        const ocrTextBlock = card.querySelector('.review-ocr-text');
        const ocrText = (review.ocr_text || '').trim();
        if (ocrText) {
          ocrTextBlock.textContent = ocrText;
          ocrToggle.hidden = false;
          ocrToggle.addEventListener('click', () => {
            ocrTextBlock.hidden = !ocrTextBlock.hidden;
            ocrToggle.textContent = ocrTextBlock.hidden ? 'View extracted text' : 'Hide extracted text';
          });
        }
        return card;
      }));
      const pendingCount = document.querySelector('#pendingCount');
      if (pendingCount) pendingCount.textContent = reviews.length;
      if (window.lucide) lucide.createIcons();
    }
  }
});
