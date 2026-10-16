document.addEventListener('DOMContentLoaded', () => {
  const flow = window.DatsGradesheetFlow;
  const $ = (selector) => document.querySelector(selector);

  const greeting = $('[data-greeting]');
  if (greeting) {
    const updateGreeting = () => {
      const hour = new Date().getHours();
      const label = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
      greeting.textContent = `${label}, ${greeting.dataset.userName || 'User'}`;
    };
    updateGreeting();
    window.setInterval(updateGreeting, 60000);
  }
  const refreshIcons = () => { if (window.lucide) window.lucide.createIcons(); };
  refreshIcons();

  const mobileMenu = $('#mobileMenu');
  mobileMenu?.addEventListener('click', () => {
    const isOpen = mobileMenu.getAttribute('aria-expanded') === 'true';
    mobileMenu.setAttribute('aria-expanded', String(!isOpen));
    $('#sidebar')?.classList.toggle('open', !isOpen);
  });

  // One toast at a time; each new message cancels the previous hide timer instead of racing it.
  const toast = $('#adminToast');
  let toastTimer = 0;
  const showToast = (message, duration = 4200) => {
    if (!toast) return;
    window.clearTimeout(toastTimer);
    toast.textContent = message;
    toast.classList.add('show');
    toastTimer = window.setTimeout(() => toast.classList.remove('show'), duration);
  };

  const api = async (query, options = {}) => {
    const response = await fetch(`../api.php?${query}`, { credentials: 'same-origin', ...options });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.error || 'The server could not complete the request.');
    return result.data;
  };
  const postJson = (resource, payload) => api(`resource=${resource}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });

  const el = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
  };
  const iconButton = (icon, label, onClick) => {
    const button = el('button', 'gs-icon-button');
    button.type = 'button';
    button.title = label;
    button.setAttribute('aria-label', label);
    button.innerHTML = `<i data-lucide="${icon}"></i>`;
    button.addEventListener('click', onClick);
    return button;
  };
  const remarkBadge = (remarks) => {
    const value = String(remarks || '').toUpperCase();
    if (!value) return el('span', 'gs-remark is-other', '—');
    const kind = value === 'PASSED' ? 'is-passed' : value === 'FAILED' ? 'is-failed' : 'is-other';
    return el('span', `gs-remark ${kind}`, value);
  };
  const formatDate = (value) => (value ? new Date(String(value).replace(' ', 'T')).toLocaleString() : '—');

  // ---------------------------------------------------------------------------
  // Progress: one stable DOM structure, updated in place. Progress only moves forward and
  // paints at most once per animation frame, so per-page OCR resets never make it jump back.
  // ---------------------------------------------------------------------------
  const progressBox = $('#gsProgress');
  const progressBar = $('#gsProgressBar');
  const progressTrack = $('#gsProgressTrack');
  const progressLabel = $('#gsProgressLabel');
  const progressPercent = $('#gsProgressPercent');
  const progressDetail = $('#gsProgressDetail');
  const triggerPercent = $('#gsTriggerPercent');
  const progressState = { value: 0, label: '', detail: '', frame: 0 };
  const paintProgress = () => {
    progressState.frame = 0;
    const percent = Math.round(progressState.value * 100);
    progressBar.style.transform = `scaleX(${progressState.value})`;
    if (progressPercent.textContent !== `${percent}%`) progressPercent.textContent = `${percent}%`;
    if (triggerPercent.textContent !== `${percent}%`) triggerPercent.textContent = `${percent}%`;
    progressTrack.setAttribute('aria-valuenow', String(percent));
    if (progressLabel.textContent !== progressState.label) progressLabel.textContent = progressState.label;
    if (progressDetail.textContent !== progressState.detail) progressDetail.textContent = progressState.detail;
  };
  const updateProgress = ({ value, label, detail } = {}) => {
    if (typeof value === 'number') progressState.value = Math.max(progressState.value, Math.min(1, value));
    if (label !== undefined) progressState.label = label;
    if (detail !== undefined) progressState.detail = detail;
    if (!progressState.frame) progressState.frame = window.requestAnimationFrame(paintProgress);
  };
  const startProgress = () => {
    progressState.value = 0;
    progressBox.classList.remove('is-done');
    progressBox.hidden = false;
    updateProgress({ value: 0, label: 'Preparing…', detail: '' });
  };
  const finishProgress = (label) => {
    progressBox.classList.add('is-done');
    updateProgress({ value: 1, label, detail: '' });
    window.setTimeout(() => { if (activeProgressJob === null) progressBox.hidden = true; }, 2600);
  };

  // ---------------------------------------------------------------------------
  // OCR in the browser: renders each page, finds its orientation, erases table rules, reads it.
  // ---------------------------------------------------------------------------
  const TARGET_LONG_SIDE = 3400; // ≈2400px wide for an A4 page — header text misreads below this
  const DETECTION_LONG_SIDE = 1100;
  const MAX_PAGES = 60;

  const loadScript = (src) => new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = src;
    script.onload = resolve;
    script.onerror = () => reject(new Error('The OCR engine could not be downloaded. Check the internet connection.'));
    document.head.appendChild(script);
  });
  const ensurePdfJs = async () => {
    if (!window.pdfjsLib) {
      const module = await import('https://cdn.jsdelivr.net/npm/pdfjs-dist@4.10.38/build/pdf.min.mjs');
      window.pdfjsLib = module.default || module;
      window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.10.38/build/pdf.worker.min.mjs';
    }
    return window.pdfjsLib;
  };

  // Handwritten-grade reader weights (a 28 KB file shipped with DATS; nothing is sent anywhere).
  let digitModelPromise = null;
  const getDigitModel = () => {
    if (!digitModelPromise) {
      digitModelPromise = fetch(document.body.dataset.digitModel || 'assets/grade-digits.json', { credentials: 'same-origin' })
        .then((response) => {
          if (!response.ok) throw new Error('The grade reader model could not be loaded.');
          return response.json();
        })
        .then((json) => flow.loadDigitModel(json))
        .catch((error) => {
          console.error(error);
          digitModelPromise = null;
          return null; // Without it, handwritten grades are left for review instead of failing the upload.
        });
    }
    return digitModelPromise;
  };

  let workerPromise = null;
  let onOcrProgress = null;
  const getOcrWorker = () => {
    if (!workerPromise) {
      workerPromise = (async () => {
        if (!window.Tesseract) await loadScript('https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js');
        const worker = await window.Tesseract.createWorker('eng', 1, {
          logger: (message) => { if (onOcrProgress) onOcrProgress(message); },
        });
        // Mode 6 (a single uniform block) reads table rows as lines; orientation is detected in
        // the same mode because other modes quietly read sideways text and skew the result.
        await worker.setParameters({ tessedit_pageseg_mode: '6' });
        return worker;
      })().catch((error) => { workerPromise = null; throw error; });
    }
    return workerPromise;
  };

  // A separate worker for the second read of each Name cell: one line of text, name characters only.
  // It cannot share the page worker, whose segmentation mode and character set are set per worker.
  let nameWorkerPromise = null;
  const getNameWorker = () => {
    if (!nameWorkerPromise) {
      nameWorkerPromise = (async () => {
        if (!window.Tesseract) await loadScript('https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js');
        const worker = await window.Tesseract.createWorker('eng', 1);
        await worker.setParameters({ tessedit_pageseg_mode: '7', tessedit_char_whitelist: flow.NAME_WHITELIST, preserve_interword_spaces: '1' });
        return worker;
      })().catch((error) => { nameWorkerPromise = null; throw error; });
    }
    return nameWorkerPromise;
  };

    let pageMatchWorkerPromise = null;
    const getPageMatchWorker = () => {
      if (!pageMatchWorkerPromise) {
        pageMatchWorkerPromise = (async () => {
          if (!window.Tesseract) await loadScript('https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js');
          const worker = await window.Tesseract.createWorker('eng', 1);
          await worker.setParameters({ tessedit_pageseg_mode: '6' });
          return worker;
        })().catch((error) => { pageMatchWorkerPromise = null; throw error; });
      }
      return pageMatchWorkerPromise;
    };

  const canvasFrom = (source, width, height) => {
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(width));
    canvas.height = Math.max(1, Math.round(height));
    const context = canvas.getContext('2d', { willReadFrequently: true });
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(source, 0, 0, canvas.width, canvas.height);
    return canvas;
  };
  const scaledCopy = (canvas, longSide) => {
    const scale = Math.min(1, longSide / Math.max(canvas.width, canvas.height));
    return canvasFrom(canvas, canvas.width * scale, canvas.height * scale);
  };
  const rotateCanvas = (source, degrees) => {
    if (!degrees) return source;
    const canvas = document.createElement('canvas');
    const sideways = degrees % 180 !== 0;
    canvas.width = sideways ? source.height : source.width;
    canvas.height = sideways ? source.width : source.height;
    const context = canvas.getContext('2d', { willReadFrequently: true });
    context.translate(canvas.width / 2, canvas.height / 2);
    context.rotate((degrees * Math.PI) / 180);
    context.drawImage(source, -source.width / 2, -source.height / 2);
    return canvas;
  };

  const openPages = async (file) => {
    const isPdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name || '');
    if (isPdf) {
      const pdfjsLib = await ensurePdfJs();
      const pdf = await pdfjsLib.getDocument({ data: await file.arrayBuffer() }).promise;
      return {
        count: Math.min(pdf.numPages, MAX_PAGES),
        truncated: pdf.numPages > MAX_PAGES,
        render: async (index) => {
          const page = await pdf.getPage(index + 1);
          const base = page.getViewport({ scale: 1 });
          const viewport = page.getViewport({ scale: TARGET_LONG_SIDE / Math.max(base.width, base.height) });
          const canvas = document.createElement('canvas');
          canvas.width = Math.round(viewport.width);
          canvas.height = Math.round(viewport.height);
          await page.render({ canvasContext: canvas.getContext('2d', { willReadFrequently: true }), viewport, background: '#ffffff' }).promise;
          page.cleanup();
          return canvas;
        },
        close: () => pdf.destroy(),
      };
    }
    const bitmap = await createImageBitmap(file);
    return {
      count: 1,
      truncated: false,
      render: async () => {
        const scale = Math.min(2, TARGET_LONG_SIDE / Math.max(bitmap.width, bitmap.height));
        return canvasFrom(bitmap, bitmap.width * scale, bitmap.height * scale);
      },
      close: () => bitmap.close(),
    };
  };

  const NAME_CELL_SCALE = 3;
  const NAME_CELL_PADDING = 14;

  /** Name cell -> rule-free, enlarged, padded canvas ready for a single-line read. */
  const nameCellCanvas = (original, [x0, y0, x1, y1]) => {
    const crop = flow.internals.cropImageData(original, Math.max(0, x0 + 2), Math.max(0, y0), Math.min(original.width, x1 - 2), Math.min(original.height, y1));
    const prepared = flow.prepareNameCellImage(crop);
    const source = document.createElement('canvas');
    source.width = prepared.width;
    source.height = prepared.height;
    source.getContext('2d').putImageData(new ImageData(prepared.data, prepared.width, prepared.height), 0, 0);
    const canvas = document.createElement('canvas');
    canvas.width = prepared.width * NAME_CELL_SCALE + NAME_CELL_PADDING * 2;
    canvas.height = prepared.height * NAME_CELL_SCALE + NAME_CELL_PADDING * 2;
    const context = canvas.getContext('2d');
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.imageSmoothingEnabled = true;
    context.imageSmoothingQuality = 'high';
    context.drawImage(source, NAME_CELL_PADDING, NAME_CELL_PADDING, prepared.width * NAME_CELL_SCALE, prepared.height * NAME_CELL_SCALE);
    return canvas;
  };

  /**
   * Turns one page's OCR into the text sent to the server. Table pages get every Name cell read a
   * second time (see flow.applyNameReads); a cell that fails to re-read keeps its first reading, and
   * a failed second pass never fails the upload.
   */
  const composePageWithNames = async (data, original, digitModel, onProgress) => {
    const composed = flow.composePageText(data, original, digitModel);
    if (!composed.structured) return composed.text;
    let worker;
    try { worker = await getNameWorker(); } catch (error) { console.error(error); return composed.text; }
    const reads = {};
    for (let row = 0; row < composed.fields.length; row += 1) {
      if (onProgress) onProgress(row / composed.fields.length);
      try {
        const [x0, y0, x1, y1] = composed.fields[row].nameBox;
        if (x1 - x0 < 20 || y1 - y0 < 8) continue;
        const { data: read } = await worker.recognize(nameCellCanvas(original, composed.fields[row].nameBox));
        reads[row] = { text: read.text, confidence: read.confidence };
      } catch (error) {
        console.error(error);
      }
    }
    return flow.applyNameReads(composed, reads);
  };

  /** OCRs one file. `report(pageIndex, pageCount, fraction, stage)` receives progress. */
  const ocrFile = async (file, report) => {
    const worker = await getOcrWorker();
    const digitModel = await getDigitModel();
    const pages = await openPages(file);
    const texts = [];
    let preferredRotation = 0;
    try {
      for (let index = 0; index < pages.count; index += 1) {
        report(index, pages.count, 0, 'Rendering page');
        const page = await pages.render(index);

        // 1) Orientation: short reads of a small copy, keeping the best-scoring rotation.
        const small = scaledCopy(page, DETECTION_LONG_SIDE);
        const candidates = flow.orientationCandidates(preferredRotation);
        let best = { rotation: preferredRotation, score: -1 };
        for (let attempt = 0; attempt < candidates.length; attempt += 1) {
          const rotation = candidates[attempt];
          onOcrProgress = (message) => {
            if (message.status === 'recognizing text') {
              report(index, pages.count, (0.25 * (attempt + message.progress)) / candidates.length, 'Checking page orientation');
            }
          };
          const { data } = await worker.recognize(rotateCanvas(small, rotation));
          const score = flow.orientationScore(data);
          if (score > best.score) best = { rotation, score };
          if (score >= flow.ORIENTATION_ACCEPT_SCORE) break;
        }
        preferredRotation = best.rotation;

        // 2) Full-resolution read of the upright page with its grid lines erased. The original
        //    pixels are kept: the table grid is located on them and grade cells are cut from them.
        const upright = rotateCanvas(page, best.rotation);
        const context = upright.getContext('2d', { willReadFrequently: true });
        const original = context.getImageData(0, 0, upright.width, upright.height);
        const cleaned = new ImageData(new Uint8ClampedArray(original.data), original.width, original.height);
        context.putImageData(flow.cleanTableImageData(cleaned), 0, 0);
        onOcrProgress = (message) => {
          if (message.status === 'recognizing text') report(index, pages.count, 0.25 + 0.65 * message.progress, 'Reading page');
        };
        const { data } = await worker.recognize(upright);

        // 3) Rebuild each student row from its table cells; handwritten grades go to the reader.
        report(index, pages.count, 0.92, 'Reading grades on page');
        await new Promise((resolve) => window.setTimeout(resolve, 0)); // let the progress bar paint
        texts.push(await composePageWithNames(data, original, digitModel, (fraction) => report(index, pages.count, 0.92 + 0.08 * fraction, 'Re-reading names on page')));
        report(index, pages.count, 1, 'Re-reading names on page');
      }
    } finally {
      onOcrProgress = null;
      pages.close?.();
    }
    const text = texts.join('\n\f\n');
    return {
      ocrText: text,
      ocrStatus: text.trim() ? 'Completed' : 'Failed',
      ocrError: text.trim() ? '' : 'No readable text was found in this file.',
      truncated: pages.truncated,
    };
  };

  const ocrPages = async (file, pageNumbers) => {
    const worker = await getOcrWorker();
    const digitModel = await getDigitModel();
    const pages = await openPages(file);
    const texts = [];
    let preferredRotation = 0;
    try {
      for (const pageNumber of pageNumbers) {
        if (pageNumber < 1 || pageNumber > pages.count) { texts.push(''); continue; }
        const page = await pages.render(pageNumber - 1);
        const small = scaledCopy(page, DETECTION_LONG_SIDE);
        let best = { rotation: preferredRotation, score: -1 };
        for (const rotation of flow.orientationCandidates(preferredRotation)) {
          const { data } = await worker.recognize(rotateCanvas(small, rotation));
          const score = flow.orientationScore(data);
          if (score > best.score) best = { rotation, score };
          if (score >= flow.ORIENTATION_ACCEPT_SCORE) break;
        }
        preferredRotation = best.rotation;
        const upright = rotateCanvas(page, best.rotation);
        const context = upright.getContext('2d', { willReadFrequently: true });
        const original = context.getImageData(0, 0, upright.width, upright.height);
        const cleaned = new ImageData(new Uint8ClampedArray(original.data), original.width, original.height);
        context.putImageData(flow.cleanTableImageData(cleaned), 0, 0);
        const { data } = await worker.recognize(upright);
        texts.push(await composePageWithNames(data, original, digitModel));
      }
    } finally {
      onOcrProgress = null;
      pages.close?.();
    }
    return texts;
  };

  const openSourcePages = async (entry, student, studentKey) => {
    const studentName = student?.display_name || entry.student_name || 'this student';
    const backdrop = el('div', 'gs-modal-backdrop gs-modal-top');
    const modal = el('section', 'gs-modal gs-modal-wide gs-page-view-modal');
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'gsPageViewTitle');
    const header = el('div', 'gs-modal-header');
    const identity = el('div');
    const heading = el('h2', '', entry.subject_name || 'Source pages');
    heading.id = 'gsPageViewTitle';
    identity.append(el('span', 'panel-kicker', 'Original gradesheet'), heading);
    const closeButton = el('button', 'row-action modal-close');
    closeButton.type = 'button';
    closeButton.setAttribute('aria-label', 'Close source pages');
    closeButton.innerHTML = '<i data-lucide="x"></i>';
    header.append(identity, closeButton);
    const status = el('p', 'gs-page-view-status', 'Preparing the original upload…');
    status.setAttribute('role', 'status');
    const pageList = el('div', 'gs-page-view-body');
    modal.append(header, status, pageList);
    backdrop.append(modal);
    document.body.append(backdrop);
    const hadModalOpen = document.body.classList.contains('gs-modal-open');
    document.body.classList.add('gs-modal-open');
    refreshIcons();

    const close = () => {
      backdrop.remove();
      document.removeEventListener('keydown', onKeydown);
      if (!hadModalOpen) document.body.classList.remove('gs-modal-open');
    };
    const onKeydown = (event) => { if (event.key === 'Escape') close(); };
    closeButton.addEventListener('click', close);
    backdrop.addEventListener('click', (event) => { if (event.target === backdrop) close(); });
    document.addEventListener('keydown', onKeydown);

    let pages = null;
    try {
      const sourceParams = new URLSearchParams({
        entry_id: String(entry.id || 0),
        student_id: String(student?.student_id || 0),
        student_name: student?.display_name || entry.student_name || '',
        student_key: studentKey || student?.student_key || '',
        batch_id: String(entry.batch_id || 0),
        document_id: String(entry.document_id || 0),
      });
      const sourceResponse = await fetch(`../api.php?resource=gradesheet-entry-source&${sourceParams}`, { credentials: 'same-origin' });
      const sourceResult = await sourceResponse.json().catch(() => ({}));
      if (!sourceResponse.ok) throw new Error(sourceResult.error || 'The original gradesheet could not be loaded.');
      const source = sourceResult.data;
      heading.textContent = source.original_filename || entry.subject_name || 'Source pages';
      const names = [source.student_name, entry.student_name, student?.display_name].filter(Boolean);
      let pageNumbers = Array.isArray(source.source_pages) ? source.source_pages : null;
      if (pageNumbers === null && source.ocr_text.includes('\f')) {
        pageNumbers = flow.findStudentRecordPageNumbers(source.ocr_text, names, source);
        if (pageNumbers.length) {
          await postJson('gradesheets', {
            action: 'index-pages', id: entry.id, student_id: student?.student_id || 0,
            student_name: student?.display_name || entry.student_name || '', student_key: studentKey || '',
            batch_id: entry.batch_id || 0, document_id: entry.document_id || 0, source_pages: pageNumbers,
          });
        } else {
          pageNumbers = null;
        }
      }
      const needsOcrIndex = pageNumbers === null;
      if (Array.isArray(pageNumbers) && pageNumbers.length === 0) {
        status.textContent = 'Student name not found in this document.';
        return;
      }
      const response = await fetch(`../api.php?resource=gradesheet-entry-file&${sourceParams}`, { credentials: 'same-origin' });
      if (!response.ok) throw new Error((await response.json().catch(() => ({}))).error || 'The original gradesheet could not be loaded.');
      const blob = await response.blob();
      const file = new File([blob], source.original_filename || 'gradesheet', { type: blob.type || source.mime_type || '' });
      pages = await openPages(file);
      const matches = [];
      if (!needsOcrIndex && pageNumbers?.length) {
        for (const pageNumber of pageNumbers) {
          if (!document.contains(backdrop)) return;
          if (pageNumber > pages.count) continue;
          status.textContent = `Opening matching page ${pageNumber} for ${studentName}…`;
          matches.push({ number: pageNumber, canvas: scaledCopy(await pages.render(pageNumber - 1), 1800) });
        }
      } else if (needsOcrIndex) {
        const worker = await getPageMatchWorker();
        let preferredRotation = 0;
        for (let index = 0; index < pages.count; index += 1) {
          if (!document.contains(backdrop)) return;
          status.textContent = `Checking page ${index + 1} of ${pages.count} for ${studentName}…`;
          const page = await pages.render(index);
          const small = scaledCopy(page, DETECTION_LONG_SIDE);
          let best = { rotation: preferredRotation, score: -1 };
          for (const rotation of flow.orientationCandidates(preferredRotation)) {
            const { data } = await worker.recognize(rotateCanvas(small, rotation));
            const score = flow.orientationScore(data);
            if (score > best.score) best = { rotation, score };
            if (score >= flow.ORIENTATION_ACCEPT_SCORE) break;
          }
          preferredRotation = best.rotation;
          const upright = rotateCanvas(page, best.rotation);
          const context = upright.getContext('2d', { willReadFrequently: true });
          const original = context.getImageData(0, 0, upright.width, upright.height);
          context.putImageData(flow.cleanTableImageData(original), 0, 0);
          const { data } = await worker.recognize(upright);
          if (flow.findStudentRecordPageNumbers(data.text, names, source).length) {
            matches.push({ number: index + 1, canvas: scaledCopy(upright, 1800) });
          }
        }
        pageNumbers = matches.map(({ number }) => number);
        await postJson('gradesheets', {
          action: 'index-pages', id: entry.id, student_id: student?.student_id || 0,
          student_name: student?.display_name || entry.student_name || '', student_key: studentKey || '',
          batch_id: entry.batch_id || 0, document_id: entry.document_id || 0, source_pages: pageNumbers,
        });
      }

      if (!document.contains(backdrop)) return;
      if (!matches.length) {
        status.textContent = 'Student name not found in this document.';
        return;
      }
      status.textContent = `${matches.length} matching ${matches.length === 1 ? 'page' : 'pages'} found for ${studentName}.`;
      pageList.replaceChildren(...matches.map(({ number, canvas }) => {
        const page = el('figure', 'gs-page-view-page');
        const pageHeader = el('div', 'gs-page-view-page-head');
        pageHeader.append(el('figcaption', '', `Page ${number}`));
        const rotate = (degrees) => {
          const rotated = document.createElement('canvas');
          rotated.width = canvas.height;
          rotated.height = canvas.width;
          const context = rotated.getContext('2d');
          context.translate(rotated.width / 2, rotated.height / 2);
          context.rotate((degrees * Math.PI) / 180);
          context.drawImage(canvas, -canvas.width / 2, -canvas.height / 2);
          rotated.setAttribute('aria-label', `Gradesheet page ${number} containing ${studentName}`);
          canvas.replaceWith(rotated);
          canvas = rotated;
        };
        const rotateLeft = el('button', 'gs-icon-button gs-page-rotate');
        rotateLeft.type = 'button';
        rotateLeft.title = 'Rotate page left';
        rotateLeft.setAttribute('aria-label', `Rotate page ${number} left`);
        rotateLeft.innerHTML = '<i data-lucide="rotate-ccw"></i><span>Rotate left</span>';
        rotateLeft.addEventListener('click', () => rotate(-90));
        const rotateRight = el('button', 'gs-icon-button gs-page-rotate');
        rotateRight.type = 'button';
        rotateRight.title = 'Rotate page right';
        rotateRight.setAttribute('aria-label', `Rotate page ${number} right`);
        rotateRight.innerHTML = '<i data-lucide="rotate-cw"></i><span>Rotate right</span>';
        rotateRight.addEventListener('click', () => rotate(90));
        const controls = el('div', 'gs-page-view-controls');
        controls.append(rotateLeft, rotateRight);
        pageHeader.append(controls);
        canvas.setAttribute('aria-label', `Gradesheet page ${number} containing ${studentName}`);
        page.append(pageHeader, canvas);
        return page;
      }));
      refreshIcons();
    } catch (error) {
      if (document.contains(backdrop)) status.textContent = error.message || 'Unable to locate the student pages in this gradesheet.';
    } finally {
      pages?.close?.();
    }
  };

  // ---------------------------------------------------------------------------
  // Job queue: uploads and re-scans run one at a time, in order.
  // ---------------------------------------------------------------------------
  const uploadZone = $('#gsUploadZone');
  const fileInput = $('#gsFileInput');
  const uploadTrigger = $('#gsOpenUpload');
  const backgroundNote = $('#gsBackgroundNote');

  const summaryMessage = (name, summary) => {
    if (!summary || !summary.entries) return `${name}: no student grade rows could be read. Try Re-scan, or check that the file is a legible gradesheet.`;
    const check = summary.needs_review ? ` · ${summary.needs_review} to check` : '';
    return `${name}: ${summary.students} students, ${summary.entries} grade records read${check}. Review and Save in Upload history before they're searchable.`;
  };

  // Per-file list in the upload modal, so results stay visible after their toast is gone.
  // Uploads/re-scans themselves now run in the background job bridge (window.DatsJobs, see
  // data-sync.js) so they keep going — and actually finish — even if the user navigates away;
  // everything below just reflects whatever the bridge reports back for each job id.
  const queueBox = $('#gsQueueBox');
  const queueList = $('#gsQueueList');
  const queueItems = new Map();
  const QUEUE_STATES = {
    queued: { icon: 'clock', label: 'Waiting' },
    reading: { icon: 'loader', label: 'Reading' },
    saved: { icon: 'check-circle-2', label: 'Read' },
    empty: { icon: 'alert-triangle', label: 'Nothing read' },
    failed: { icon: 'x-circle', label: 'Failed' },
  };
  const formatSize = (bytes) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);
  const setQueueItem = (jobId, info, state, message) => {
    let item = queueItems.get(jobId);
    if (!item) {
      item = el('li', 'gs-queue-item');
      item.append(el('span', 'gs-queue-icon'), el('span', 'gs-queue-text'), el('span', 'gs-queue-state'));
      queueList.prepend(item);
      queueBox.hidden = false;
      queueItems.set(jobId, item);
    }
    const { icon, label } = QUEUE_STATES[state];
    item.className = `gs-queue-item is-${state}`;
    item.dataset.state = state;
    item.children[0].innerHTML = `<i data-lucide="${icon}"></i>`;
    const text = item.children[1];
    text.replaceChildren(el('strong', '', info.fileName || 'gradesheet'), el('small', '', message || `${formatSize(info.fileSize || 0)}${info.kind === 'rescan' ? ' · re-scan' : ''}`));
    item.children[2].textContent = label;
    refreshIcons();
  };
  $('#gsQueueClear').addEventListener('click', () => {
    queueList.querySelectorAll('.gs-queue-item:not(.is-queued):not(.is-reading)').forEach((item) => item.remove());
    queueBox.hidden = !queueList.children.length;
  });
  const setBusy = (busy) => {
    uploadZone.classList.toggle('gs-upload-zone-busy', busy);
    uploadTrigger.classList.toggle('is-busy', busy);
    backgroundNote.hidden = !busy;
  };
  const pendingJobIds = new Set();
  const markPending = (id) => { if (!pendingJobIds.has(id)) { pendingJobIds.add(id); setBusy(true); } };
  const markSettled = (id) => { if (pendingJobIds.delete(id)) setBusy(pendingJobIds.size > 0); };

  const queueStateFor = (job) => {
    if (job.status === 'failed') return 'failed';
    if (job.status === 'done') return job.resultSummary?.entries ? 'saved' : 'empty';
    if (job.status === 'queued') return 'queued';
    return 'reading'; // ocr-running / saving
  };
  const queueMessageFor = (job) => {
    if (job.status === 'failed') return job.error || 'Something went wrong.';
    if (job.status !== 'done') return undefined; // falls back to the default size/kind caption
    const summary = job.resultSummary;
    if (!summary?.entries) return 'No grade rows could be read — try Re-scan from Upload history.';
    const check = summary.needs_review ? ` · ${summary.needs_review} to check` : '';
    const truncated = job.result?.truncated;
    return `${summary.students} students · ${summary.entries} records${check}${truncated ? ` · first ${MAX_PAGES} pages only` : ''} · awaiting save`;
  };

  let activeProgressJob = null;
  const onGradesheetJobUpdate = (job) => {
    if (job.kind === 'scan-upload') return;
    const info = { fileName: job.payload?.fileName, fileSize: job.payload?.fileSize, kind: job.kind === 'gradesheet-rescan' ? 'rescan' : 'upload' };
    setQueueItem(job.id, info, queueStateFor(job), queueMessageFor(job));
    const name = info.fileName || 'gradesheet';

    if (job.status === 'queued' || job.status === 'ocr-running' || job.status === 'saving') {
      markPending(job.id);
      if (activeProgressJob !== job.id) { startProgress(); activeProgressJob = job.id; }
      if (job.status === 'saving') {
        updateProgress({ value: 0.97, label: `Saving grades · ${name}`, detail: '' });
      } else if (job.status === 'ocr-running') {
        updateProgress({
          value: Math.min(0.97, (job.progress?.value || 0) * 0.97),
          label: `${job.progress?.label || 'Starting OCR'} · ${name}`,
          detail: job.progress?.detail || '',
        });
      }
      return;
    }

    if (job.status === 'done' || job.status === 'failed') {
      const message = job.status === 'done'
        ? summaryMessage(name, job.resultSummary) + (job.result?.truncated ? ` Only the first ${MAX_PAGES} pages were read.` : '')
        : `${name}: ${job.error}`;
      if (uploadModal.hidden) showToast(message, 6000);
      if (activeProgressJob === job.id) { activeProgressJob = null; finishProgress(message); }
      markSettled(job.id);
      loadBatches();
      if (currentStudent) loadStudentGrades(currentStudent, { quiet: true });
      window.DatsJobs.ack(job.id);
    }
  };
  window.DatsJobs.onUpdate(onGradesheetJobUpdate);

  const queueFiles = (fileList) => {
    const list = [...(fileList || [])];
    if (!list.length) return;
    const files = list.filter((file) => file && file.size > 0);
    if (!files.length) {
      showToast('The selected file is empty.');
      return;
    }
    const accepted = files.filter((file) => /^(image\/(jpeg|png)|application\/pdf)$/i.test(file.type) || /\.(pdf|jpe?g|png)$/i.test(file.name));
    if (accepted.length < files.length) showToast('Only PDF, JPG, and PNG files can be uploaded.');
    accepted.forEach((file) => window.DatsJobs.enqueue('gradesheet-upload', { fileName: file.name, fileSize: file.size }, file));
  };

  fileInput.addEventListener('change', () => { queueFiles(fileInput.files); fileInput.value = ''; });
  ['dragenter', 'dragover'].forEach((type) => uploadZone.addEventListener(type, (event) => { event.preventDefault(); uploadZone.classList.add('drag-over'); }));
  ['dragleave', 'drop'].forEach((type) => uploadZone.addEventListener(type, (event) => { event.preventDefault(); uploadZone.classList.remove('drag-over'); }));
  uploadZone.addEventListener('drop', (event) => queueFiles(event.dataTransfer?.files));
  uploadZone.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    event.preventDefault();
    fileInput.click();
  });

  // Upload modal: opened from the page header (or "Upload more"), or by dragging files anywhere
  // onto the page. Reading keeps going when it is closed; the header button shows the progress.
  const uploadModal = $('#gsUploadModal');
  let uploadReturnFocus = null;
  const openUploadModal = () => {
    if (!uploadModal.hidden) return;
    uploadReturnFocus = document.activeElement;
    uploadModal.hidden = false;
    document.body.classList.add('gs-modal-open');
    refreshIcons();
    uploadZone.focus();
  };
  const closeUploadModal = () => {
    uploadModal.hidden = true;
    uploadZone.classList.remove('drag-over');
    document.body.classList.remove('gs-modal-open');
    uploadReturnFocus?.focus?.();
  };
  uploadTrigger.addEventListener('click', openUploadModal);
  document.querySelectorAll('[data-open-upload]').forEach((button) => button.addEventListener('click', openUploadModal));
  const draggingFiles = (event) => [...(event.dataTransfer?.types || [])].includes('Files');
  document.addEventListener('dragenter', (event) => {
    if (!draggingFiles(event) || !editModal.hidden || !batchModal.hidden) return;
    openUploadModal();
  });
  // Dropping just outside the zone must not make the browser navigate away to the file.
  document.addEventListener('dragover', (event) => { if (draggingFiles(event)) event.preventDefault(); });
  document.addEventListener('drop', (event) => {
    if (!draggingFiles(event) || uploadZone.contains(event.target)) return;
    event.preventDefault();
    if (!uploadModal.hidden) queueFiles(event.dataTransfer.files);
  });

  const rescanBatch = async (batch) => {
    if (!window.confirm(`Re-scan "${batch.original_filename}"? Its records will be read again and replaced, including any corrections made to them.`)) return;
    try {
      const response = await fetch(`../api.php?resource=gradesheet-batch-file&batch_id=${encodeURIComponent(batch.id)}`, { credentials: 'same-origin' });
      if (!response.ok) throw new Error((await response.json().catch(() => ({}))).error || 'The stored file could not be loaded.');
      const blob = await response.blob();
      const file = new File([blob], batch.original_filename, { type: blob.type || batch.mime_type || '' });
      window.DatsJobs.enqueue('gradesheet-rescan', { fileName: batch.original_filename, fileSize: file.size, batchId: batch.id }, file);
    } catch (error) {
      showToast(error.message);
    }
  };

  const deleteBatch = async (batch) => {
    if (!window.confirm(`Delete "${batch.original_filename}" and all ${batch.committed_entry_count} grade records read from it? This cannot be undone.`)) return;
    try {
      await postJson('gradesheet-batches', { action: 'delete', batch_id: batch.id });
      showToast('Upload and its grade records deleted.');
      loadBatches();
      if (currentStudent) loadStudentGrades(currentStudent, { quiet: true });
    } catch (error) {
      showToast(error.message);
    }
  };

  // ---------------------------------------------------------------------------
  // Upload history
  // ---------------------------------------------------------------------------
  const batchRows = $('#gsBatchRows');
  const tableNote = (colspan, text) => {
    const row = el('tr');
    const cell = el('td', 'gs-table-note', text);
    cell.colSpan = colspan;
    row.append(cell);
    return row;
  };
  const statusClass = (status) => (status === 'Completed' ? 'is-completed' : status === 'Failed' ? 'is-failed' : status === 'Pending Review' ? 'is-pending' : 'is-needs-review');
  const statusLabel = (status) => (status === 'Needs Review' ? 'To check' : status === 'Pending Review' ? 'Awaiting save' : status);

  const renderBatchRow = (batch) => {
    const row = el('tr');
    const fileCell = el('td');
    fileCell.append(el('strong', '', batch.original_filename));
    if (batch.status === 'Failed' && batch.ocr_error) fileCell.append(el('small', '', batch.ocr_error));
    else fileCell.append(el('small', '', batch.uploaded_by_name ? `by ${batch.uploaded_by_name}` : ''));
    const statusCell = el('td');
    statusCell.append(el('span', `gs-status-pill ${statusClass(batch.status)}`, statusLabel(batch.status)));
    const actions = el('div', 'gs-row-actions');
    const viewButton = iconButton('eye', 'View records', () => openBatchModal(batch));
    viewButton.disabled = !Number(batch.committed_entry_count);
    actions.append(viewButton, iconButton('refresh-ccw', 'Re-scan file', () => rescanBatch(batch)), iconButton('trash-2', 'Delete upload', () => deleteBatch(batch)));
    const actionsCell = el('td');
    actionsCell.append(actions);
    row.append(
      fileCell,
      statusCell,
      el('td', 'gs-num', String(batch.detected_student_count ?? 0)),
      el('td', 'gs-num', String(batch.committed_entry_count ?? 0)),
      el('td', 'gs-num', String(batch.needs_review_count ?? 0)),
      el('td', 'gs-num', formatDate(batch.uploaded_at)),
      actionsCell,
    );
    return row;
  };

  const loadBatches = async () => {
    try {
      const batches = await api('resource=gradesheet-batches');
      batchRows.replaceChildren(...(batches.length ? batches.map(renderBatchRow) : [tableNote(7, 'No gradesheets uploaded yet.')]));
      refreshIcons();
    } catch (error) {
      batchRows.replaceChildren(tableNote(7, error.message));
    }
  };

  // ---------------------------------------------------------------------------
  // Modals
  // ---------------------------------------------------------------------------
  const batchModal = $('#gsBatchModal');
  const addStudentModal = $('#gsAddStudentModal');
  const editModal = $('#gsEditModal');
  const closeModal = (modal) => {
    if (modal === uploadModal) closeUploadModal();
    else modal.hidden = true;
  };
  [uploadModal, batchModal, editModal].forEach((modal) => {
    modal.addEventListener('click', (event) => {
      if (event.target === modal || event.target.closest('[data-close-modal]')) closeModal(modal);
    });
  });
  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    if (!addStudentModal.hidden) closeAddStudentModal();
    else if (!editModal.hidden) closeModal(editModal);
    else if (!batchModal.hidden) closeModal(batchModal);
    else if (!uploadModal.hidden) closeModal(uploadModal);
  });

  let currentBatch = null;
  let currentBatchEntries = [];
  const batchEntryRows = $('#gsBatchEntryRows');
  const onlyFlagged = $('#gsBatchOnlyFlagged');
  const batchSearch = $('#gsBatchSearch');

  // Diacritic/case-insensitive substring match — lets a reviewer type a name as printed ("Dela
  // Cruz", "dela cruz", "Dêla Cruz") and still find it regardless of how the scan capitalized it.
  const searchFold = (value) => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  const matchesBatchSearch = (entry, term) => {
    if (!term) return true;
    return [entry.directory_name, entry.student_name, entry.subject_code, entry.subject_name, entry.year_course]
      .some((value) => value && searchFold(value).includes(term));
  };

  const renderBatchEntries = () => {
    const term = searchFold(batchSearch.value.trim());
    let entries = onlyFlagged.checked ? currentBatchEntries.filter((entry) => Number(entry.needs_review)) : currentBatchEntries;
    entries = entries.filter((entry) => matchesBatchSearch(entry, term));
    if (!entries.length) {
      const reason = term ? 'No records match your search.' : onlyFlagged.checked ? 'Nothing left to check in this upload.' : 'No records.';
      batchEntryRows.replaceChildren(tableNote(7, reason));
      return;
    }
    batchEntryRows.replaceChildren(...entries.map((entry) => {
      const row = el('tr', Number(entry.needs_review) ? 'is-flagged' : '');
      const student = el('td');
      student.append(el('strong', '', entry.directory_name || entry.student_name || '—'));
      if (entry.directory_name) student.append(el('small', '', 'Linked to Students directory'));
      if (entry.suggested_name && !entry.directory_name) {
        const suggest = el('button', 'gs-suggest', `Did you mean ${entry.suggested_name}?`);
        suggest.type = 'button';
        suggest.title = 'Open this record with the suggested name filled in';
        suggest.addEventListener('click', () => openEditModal(entry, { suggestedName: entry.suggested_name }));
        student.append(document.createElement('br'), suggest);
      }
      const subject = el('td');
      subject.append(el('strong', '', entry.subject_code || '—'), el('small', '', entry.subject_name || ''));
      const grade = el('td', 'gs-num', entry.grade || '—');
      if (Number(entry.needs_review)) {
        const reasons = String(entry.review_reasons || '').split(',').map((value) => value.trim()).filter(Boolean);
        const flag = el('span', 'gs-flag', reasons.length ? `Check ${reasons.join(', ')}` : 'Check');
        if (reasons.length) flag.title = `Needs a look: ${reasons.join(', ')}`;
        grade.append(' ', flag);
      }
      const remarks = el('td');
      remarks.append(remarkBadge(entry.remarks));
      const actions = el('td');
      const wrap = el('div', 'gs-row-actions');
      const batchStudent = {
        student_id: entry.student_id || null,
        display_name: entry.directory_name || entry.student_name || '',
      };
      const viewButton = el('button', 'gs-icon-button gs-view-button');
      viewButton.type = 'button';
      viewButton.title = 'View source pages containing this student';
      viewButton.setAttribute('aria-label', 'View source pages containing this student');
      viewButton.innerHTML = '<i data-lucide="eye"></i><span>View</span>';
      viewButton.addEventListener('click', () => openSourcePages(entry, batchStudent, entry.student_name));
      wrap.append(viewButton);
      wrap.append(iconButton('pencil', 'Edit record', () => openEditModal(entry)));
      actions.append(wrap);
      row.append(student, el('td', '', entry.year_course || '—'), subject, el('td', 'gs-num', `${flow.formatSchoolYear(entry.academic_year)} · ${entry.semester || '—'}`), grade, remarks, actions);
      return row;
    }));
    refreshIcons();
  };

  const batchPendingNote = $('#gsBatchPendingNote');
  const batchSaveButton = $('#gsBatchSave');
  const applyBatchSaveState = () => {
    const pending = currentBatch?.status === 'Pending Review';
    batchPendingNote.hidden = !pending;
    batchSaveButton.hidden = !pending;
  };

  const openBatchModal = async (batch, { resetFilters = true } = {}) => {
    currentBatch = batch;
    $('#gsBatchModalTitle').textContent = batch.original_filename;
    $('#gsBatchModalSummary').textContent = 'Loading…';
    batchEntryRows.replaceChildren(tableNote(7, 'Loading records…'));
    if (resetFilters) { batchSearch.value = ''; onlyFlagged.checked = false; }
    applyBatchSaveState();
    batchModal.hidden = false;
    try {
      const data = await api(`resource=gradesheet-batch-entries&batch_id=${encodeURIComponent(batch.id)}`);
      currentBatch = { ...batch, ...data.batch };
      currentBatchEntries = data.entries;
      const flagged = data.entries.filter((entry) => Number(entry.needs_review)).length;
      $('#gsBatchModalSummary').textContent = `${data.batch.detected_student_count} students · ${data.entries.length} grade records · ${flagged} to check`;
      applyBatchSaveState();
      renderBatchEntries();
    } catch (error) {
      batchEntryRows.replaceChildren(tableNote(7, error.message));
    }
  };
  onlyFlagged.addEventListener('change', renderBatchEntries);
  batchSearch.addEventListener('input', renderBatchEntries);

  batchSaveButton.addEventListener('click', async () => {
    if (!currentBatch) return;
    batchSaveButton.disabled = true;
    try {
      const result = await postJson('gradesheet-batches', { action: 'save', batch_id: currentBatch.id });
      currentBatch = { ...currentBatch, ...result.batch };
      applyBatchSaveState();
      showToast('Saved. These records are now searchable.');
      loadBatches();
      if (currentStudent) loadStudentGrades(currentStudent, { quiet: true });
    } catch (error) {
      showToast(error.message);
    } finally {
      batchSaveButton.disabled = false;
    }
  });

  const addStudentForm = $('#gsAddStudentForm');
  const addStudentError = $('#gsAddStudentError');
  const addStudentSave = $('#gsAddStudentSave');
  const addStudentFields = {
    name: $('#gsAddStudentName'), year: $('#gsAddStudentYear'), course: $('#gsAddStudentCourse'),
    semester: $('#gsAddSemester'), academicYear: $('#gsAddYear'), code: $('#gsAddCode'), units: $('#gsAddUnits'),
    title: $('#gsAddTitle'), grade: $('#gsAddGrade'), remarks: $('#gsAddRemarks'),
  };
  flow.GRADESHEET_SEMESTERS.forEach((semester) => addStudentFields.semester.add(new Option(semester, semester)));
  const closeAddStudentModal = () => {
    addStudentModal.hidden = true;
    document.body.classList.remove('gs-modal-open');
  };
  $('#gsAddStudentOpen').addEventListener('click', () => {
    addStudentForm.reset();
    addStudentError.hidden = true;
    addStudentModal.hidden = false;
    document.body.classList.add('gs-modal-open');
    refreshIcons();
    addStudentFields.name.focus();
  });
  addStudentModal.querySelectorAll('[data-close-add-student]').forEach((button) => button.addEventListener('click', closeAddStudentModal));
  addStudentModal.addEventListener('click', (event) => {
    if (event.target === addStudentModal) closeAddStudentModal();
  });

  const completeGradesheetStudentAssociation = async (initialAssociation, criteria, draftEntryId) => {
    let association = initialAssociation;
    let fallbackFailures = 0;
    const readBatchFile = async (batchId) => {
      const response = await fetch(`../api.php?resource=gradesheet-batch-file&batch_id=${encodeURIComponent(batchId)}`, { credentials: 'same-origin' });
      if (!response.ok) throw new Error('The stored upload could not be opened.');
      const blob = await response.blob();
      const filename = response.headers.get('content-disposition')?.match(/filename="?([^";]+)"?/i)?.[1] || 'gradesheet';
      return new File([blob], filename, { type: blob.type });
    };
    const mergeAssociation = (result) => {
      association.matching_batches = [...new Set([...(association.matching_batches || []), ...(result.matching_batches || [])])];
      association.matched_records += Number(result.matched_records || 0);
      association.created_records += Number(result.created_records || 0);
      association.linked_records += Number(result.linked_records || 0);
    };
    for (const batch of association.missing_ocr_batches || []) {
      try {
        const ocr = await ocrFile(await readBatchFile(batch.id), () => {});
        if (!ocr.ocrText.trim()) throw new Error('No readable text was found.');
        const result = await postJson('gradesheets', { action: 'associate-student', batch_id: batch.id, ...criteria, draft_entry_id: draftEntryId, ocr_text: ocr.ocrText });
        mergeAssociation(result);
      } catch (error) {
        fallbackFailures += 1;
      }
    }
    for (const batch of association.unparsed_matching_batches || []) {
      try {
        const text = (await ocrPages(await readBatchFile(batch.id), batch.page_numbers)).join('\f');
        if (!text.trim()) throw new Error('No readable text was found on the matching pages.');
        const result = await postJson('gradesheets', {
          action: 'associate-student', batch_id: batch.id, ...criteria,
          draft_entry_id: draftEntryId, ocr_text: text, ocr_page_numbers: batch.page_numbers,
        });
        mergeAssociation(result);
      } catch (error) {
        fallbackFailures += 1;
      }
    }
    return { association, fallbackFailures };
  };

  addStudentForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    addStudentError.hidden = true;
    const criteria = {
      student_name: addStudentFields.name.value.trim(),
      year_course: normalizeYearCourse(addStudentFields.year.value, addStudentFields.course.value),
      semester: addStudentFields.semester.value,
      academic_year: addStudentFields.academicYear.value.trim(),
      subject_code: addStudentFields.code.value.trim(),
      units: addStudentFields.units.value.trim(),
      subject_name: addStudentFields.title.value.trim(),
      grade: addStudentFields.grade.value.trim().toUpperCase(),
      remarks: addStudentFields.remarks.value.trim().toUpperCase(),
    };
    const problem = flow.validateEntry(criteria);
    if (problem) {
      addStudentError.textContent = problem;
      addStudentError.hidden = false;
      return;
    }
    const payload = {
      action: 'add-student',
      ...criteria,
    };
    addStudentSave.disabled = true;
    addStudentSave.textContent = 'Searching uploaded gradesheets…';
    try {
      const created = await postJson('gradesheets', payload);
      closeAddStudentModal();
      loadBatches();
      const selectAddedStudent = async () => {
        const matches = await api(`resource=gradesheet-students&search=${encodeURIComponent(criteria.student_name)}`);
        const searchKey = criteria.student_name.replace(/[Ññ]/g, '\uE000').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase().replace(/\uE000/g, 'Ñ').replace(/[^A-ZÑ]/g, '');
        const found = matches.find((student) => student.student_key === searchKey);
        if (found) selectStudent(found);
        return found;
      };
      let addedStudent = await selectAddedStudent();
      const { association, fallbackFailures } = await completeGradesheetStudentAssociation(created.association || {}, criteria, created.draft_entry_id);
      loadBatches();
      addedStudent = await selectAddedStudent() || addedStudent;
      if (addedStudent) loadStudentGrades(addedStudent, { quiet: true });
      const summary = association.matched_records;
      const message = summary
        ? `Student added and found in ${summary} gradesheet record${summary === 1 ? '' : 's'} across ${association.matching_batches.length} file${association.matching_batches.length === 1 ? '' : 's'}.`
        : 'Student added, but no matching gradesheet records were found in the uploaded files.';
      showToast(fallbackFailures ? `${message} ${fallbackFailures} upload${fallbackFailures === 1 ? '' : 's'} could not be re-read.` : message, 6500);
    } catch (error) {
      addStudentError.textContent = error.message || 'Unable to add the student.';
      addStudentError.hidden = false;
    } finally {
      addStudentSave.disabled = false;
      addStudentSave.innerHTML = '<i data-lucide="user-round-plus"></i> Save &amp; find grades';
      refreshIcons();
    }
  });

  const editForm = $('#gsEditForm');
  const editError = $('#gsEditError');
  const parseYearCourse = (value = '') => {
    const text = String(value ?? '').trim();
    if (!text) return { year: '', course: '' };
    const yearPattern = '(?:\\d{1,2}(?:st|nd|rd|th)?\\s*(?:Year|Yr)|Undisclosed|Senior High School|Graduate|High School|Elementary|Pre-Baccalaureate)';
    const match = text.match(new RegExp(`^(.*?)(?:\\s*[-–|/]\\s*|\\s+)(${yearPattern})$`, 'i'));
    if (match) return { course: match[1].trim(), year: match[2].trim() };
    const yearToken = text.match(new RegExp(yearPattern, 'i'));
    if (yearToken) {
      const index = yearToken.index ?? 0;
      const before = text.slice(0, index).trim();
      const after = text.slice(index + yearToken[0].length).trim();
      if (before || after) return { course: [before, after].filter(Boolean).join(' '), year: yearToken[0].trim() };
    }
    return { year: '', course: text };
  };
  const normalizeYearCourse = (yearValue, courseValue, existingValue = '') => {
    const year = String(yearValue ?? '').trim();
    const course = String(courseValue ?? '').trim();
    if (!year && !course) {
      const parsed = parseYearCourse(existingValue);
      return [parsed.course, parsed.year].filter(Boolean).join(' ');
    }
    return [course, year].filter(Boolean).join(' ');
  };
  const editFields = {
    name: $('#gsEditStudentName'), studentYear: $('#gsEditStudentYear'), studentCourse: $('#gsEditStudentCourse'), semester: $('#gsEditSemester'), year: $('#gsEditYear'), code: $('#gsEditCode'),
    units: $('#gsEditUnits'), title: $('#gsEditTitle'), grade: $('#gsEditGrade'), remarks: $('#gsEditRemarks'),
  };
  flow.GRADESHEET_SEMESTERS.forEach((semester) => editFields.semester.add(new Option(semester, semester)));
  let editingEntry = null;

  const suggestRow = $('#gsEditNameSuggest');
  const applyAllRow = $('#gsEditApplyAllRow');
  const applyAll = $('#gsEditApplyAll');

  /** Other unlinked records in the open upload that carry exactly this misread spelling. */
  const sameSpellingCount = (entry) => {
    const name = String(entry.student_name || '').trim();
    if (!name || entry.student_id || !currentBatch || Number(entry.batch_id) !== Number(currentBatch.id)) return 0;
    return currentBatchEntries.filter((other) => other.id !== entry.id && !other.student_id && String(other.student_name || '').trim() === name).length;
  };
  const refreshApplyAll = () => {
    const others = editingEntry ? sameSpellingCount(editingEntry) : 0;
    const changed = editingEntry && editFields.name.value.trim() !== String(editingEntry.student_name || '').trim() && editFields.name.value.trim() !== '';
    applyAllRow.hidden = !(others > 0 && changed);
    if (!applyAllRow.hidden) $('#gsEditApplyAllText').textContent = `Also correct ${others} other record${others === 1 ? '' : 's'} in this upload read as "${editingEntry.student_name}"`;
  };
  editFields.name.addEventListener('input', refreshApplyAll);

  const openEditModal = (entry, { suggestedName = '' } = {}) => {
    editingEntry = entry;
    const linked = Boolean(entry.student_id);
    const parsedYearCourse = parseYearCourse(entry.year_course || '');
    editFields.name.value = suggestedName || entry.student_name || entry.directory_name || '';
    editFields.name.readOnly = linked && !entry.student_name;
    suggestRow.replaceChildren();
    suggestRow.hidden = true;
    if (entry.suggested_name && entry.suggested_name !== editFields.name.value) {
      const use = el('button', 'gs-suggest', `Use ${entry.suggested_name}`);
      use.type = 'button';
      use.title = 'The closest student in the Students directory';
      use.addEventListener('click', () => { editFields.name.value = entry.suggested_name; refreshApplyAll(); });
      suggestRow.append(el('small', 'field-help', 'Closest directory match:'), use);
      suggestRow.hidden = false;
    }
    applyAll.checked = true;
    refreshApplyAll();
    editFields.studentYear.value = parsedYearCourse.year;
    editFields.studentCourse.value = parsedYearCourse.course;
    $('#gsEditStudentHelp').textContent = linked ? 'Linked to a Students directory record.' : 'Correct the spelling if the scan misread it.';
    editFields.semester.value = entry.semester || '';
    editFields.year.value = entry.academic_year || '';
    editFields.code.value = entry.subject_code || '';
    editFields.units.value = entry.units ?? '';
    editFields.title.value = entry.subject_name || '';
    editFields.grade.value = entry.grade || '';
    editFields.remarks.value = entry.remarks || '';
    editError.hidden = true;
    editModal.hidden = false;
    refreshIcons();
    (entry.grade ? editFields.remarks : editFields.grade).focus();
  };

  const afterEntryChange = () => {
    if (currentStudent) loadStudentGrades(currentStudent, { quiet: true });
    if (!batchModal.hidden && currentBatch) openBatchModal(currentBatch, { resetFilters: false });
    loadBatches();
  };

  editForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const payload = {
      action: 'update-entry',
      id: editingEntry.id,
      student_name: editFields.name.value.trim(),
      year_course: normalizeYearCourse(editFields.studentYear.value, editFields.studentCourse.value, editingEntry.year_course || ''),
      semester: editFields.semester.value,
      academic_year: editFields.year.value.trim(),
      subject_code: editFields.code.value.trim(),
      subject_name: editFields.title.value.trim(),
      units: editFields.units.value.trim(),
      grade: editFields.grade.value.trim().toUpperCase(),
      remarks: editFields.remarks.value.trim().toUpperCase(),
    };
    if (!applyAllRow.hidden && applyAll.checked) payload.apply_to_batch = true;
    const problem = flow.validateEntry(editingEntry.student_id ? { ...payload, student_name: payload.student_name || 'linked' } : payload);
    if (problem) { editError.textContent = problem; editError.hidden = false; return; }
    const saveButton = $('#gsEditSave');
    saveButton.disabled = true;
    try {
      const saved = await postJson('gradesheets', payload);
      closeModal(editModal);
      const bulk = Number(saved?.bulk_updated ?? saved?.data?.bulk_updated ?? 0);
      showToast(bulk > 0 ? `Grade record saved. Name corrected on ${bulk} other record${bulk === 1 ? '' : 's'} too.` : 'Grade record saved and marked verified.');
      afterEntryChange();
    } catch (error) {
      editError.textContent = error.message;
      editError.hidden = false;
    } finally {
      saveButton.disabled = false;
    }
  });

  $('#gsEditDelete').addEventListener('click', async () => {
    if (!editingEntry || !window.confirm('Delete this grade record? This cannot be undone.')) return;
    try {
      await postJson('gradesheets', { action: 'delete-entry', id: editingEntry.id });
      closeModal(editModal);
      showToast('Grade record deleted.');
      afterEntryChange();
    } catch (error) {
      editError.textContent = error.message;
      editError.hidden = false;
    }
  });

  // ---------------------------------------------------------------------------
  // Student search (same component and keyboard behavior as the Files page search)
  // ---------------------------------------------------------------------------
  const searchInput = $('#gsStudentSearch');
  const suggestions = $('#gsStudentSuggestions');
  const resultBox = $('#gsStudentGradesResult');
  let searchTimer = 0;
  let searchToken = 0;
  let currentStudent = null;

  const closeSuggestions = () => {
    suggestions.hidden = true;
    suggestions.replaceChildren();
    searchInput.setAttribute('aria-expanded', 'false');
  };
  const selectStudent = (student) => {
    searchInput.value = student.display_name;
    closeSuggestions();
    loadStudentGrades(student);
  };

  const renderSuggestions = (term, students) => {
    if (!students.length) {
      suggestions.replaceChildren(el('div', 'search-suggestion-empty', `No students with grade records match “${term}”.`));
    } else {
      suggestions.replaceChildren(...students.map((student) => {
        const item = el('button', 'search-suggestion-item');
        item.type = 'button';
        item.setAttribute('role', 'option');
        const text = el('span');
        const meta = [student.similar ? 'similar spelling' : null, student.student_number, student.year_course, student.in_directory ? null : 'from gradesheet'].filter(Boolean).join(' · ');
        text.append(el('strong', '', student.display_name), el('small', '', meta || '—'));
        const count = el('span', 'gs-suggestion-meta', `${student.entry_count} record${Number(student.entry_count) === 1 ? '' : 's'}`);
        item.append(el('span', 'student-mini-avatar', flow.initials(student.display_name)), text, count);
        item.addEventListener('click', () => selectStudent(student));
        item.studentData = student;
        return item;
      }));
    }
    suggestions.hidden = false;
    searchInput.setAttribute('aria-expanded', 'true');
  };

  const runSearch = async (term) => {
    const token = ++searchToken;
    try {
      const students = await api(`resource=gradesheet-students&search=${encodeURIComponent(term)}`);
      if (token === searchToken && searchInput.value.trim() === term) renderSuggestions(term, students);
    } catch (error) {
      if (token === searchToken) showToast(error.message);
    }
  };

  searchInput.addEventListener('input', () => {
    window.clearTimeout(searchTimer);
    const term = searchInput.value.trim();
    if (!term) { searchToken += 1; closeSuggestions(); return; }
    searchTimer = window.setTimeout(() => runSearch(term), 200);
  });
  searchInput.addEventListener('focus', () => { if (searchInput.value.trim() && suggestions.hidden) runSearch(searchInput.value.trim()); });
  searchInput.addEventListener('keydown', (event) => {
    const items = [...suggestions.querySelectorAll('.search-suggestion-item')];
    if (suggestions.hidden || !items.length) {
      if (event.key === 'Escape') closeSuggestions();
      return;
    }
    const current = items.findIndex((item) => item.classList.contains('active'));
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      const next = event.key === 'ArrowDown' ? (current + 1) % items.length : (current - 1 + items.length) % items.length;
      items.forEach((item, index) => item.classList.toggle('active', index === next));
      items[next].scrollIntoView({ block: 'nearest' });
    } else if (event.key === 'Enter') {
      event.preventDefault();
      selectStudent(items[Math.max(0, current)].studentData);
    } else if (event.key === 'Escape') {
      closeSuggestions();
    }
  });
  document.addEventListener('click', (event) => {
    if (!suggestions.hidden && !event.target.closest('.gs-search-toolbar .search-box')) closeSuggestions();
  });

  const renderStudentGrades = ({ student, entries }) => {
    const flagged = entries.filter((entry) => Number(entry.needs_review)).length;
    const summary = el('div', 'gs-student-summary');
    const who = el('div');
    const subtitle = [student.student_number, student.in_directory ? 'Students directory' : 'Recorded from gradesheets (not in Students directory)'].filter(Boolean).join(' · ');
    who.append(el('strong', '', student.display_name), el('small', '', subtitle));
    const terms = flow.groupEntriesByTerm(entries);
    const stats = el('div', 'gs-summary-stats');
    const stat = (value, label) => { const node = el('span'); node.append(el('b', '', String(value)), ` ${label}`); stats.append(node); };
    stat(terms.length, terms.length === 1 ? 'semester' : 'semesters');
    stat(entries.length, entries.length === 1 ? 'subject' : 'subjects');
    if (flagged) stat(flagged, 'to check');
    summary.append(el('span', 'student-mini-avatar', flow.initials(student.display_name)), who, stats);
    const studentId = student?.student_id ?? student?.id ?? null;
    if (student.in_directory && studentId) {
      const link = el('a', 'gs-open-full-page');
      link.href = `student-gradesheet-view.php?student_id=${encodeURIComponent(String(studentId))}`;
      link.innerHTML = '<i data-lucide="external-link"></i> Full page';
      summary.append(link);
    }

    // One card per semester and school year, holding every subject the student took that term —
    // graded or not — so the whole record reads top to bottom under a single name.
    const termCards = terms.map((term) => {
      const card = el('section', 'gs-term-card');
      const head = el('header', 'gs-term-head');
      const title = el('div');
      title.append(el('h4', '', `${term.semester || 'Semester not read'} · S.Y. ${flow.formatSchoolYear(term.academic_year)}`));
      const termStats = el('div', 'gs-term-stats');
      const termStat = (label, value) => { const node = el('span', '', `${label} `); node.append(el('b', '', value)); termStats.append(node); };
      const units = term.rows.reduce((sum, entry) => sum + (Number(entry.units) || 0), 0);
      termStat('Subjects', String(term.rows.length));
      if (units) termStat('Units', String(Number(units.toFixed(1))));
      termStat('Weighted average', term.average !== null ? term.average.toFixed(2) : '—');
      head.append(title, termStats);

      const table = el('table', 'gs-table gs-records-table');
      table.innerHTML = '<thead><tr><th>Subject code</th><th>Descriptive title</th><th>Year &amp; course</th><th class="gs-col-num">Units</th><th>Final grade</th><th>Remarks</th><th><span class="sr-only">Actions</span></th></tr></thead>';
      const body = el('tbody');
      term.rows.forEach((entry) => {
        const flaggedRow = Number(entry.needs_review);
        const row = el('tr', flaggedRow ? 'is-flagged' : '');
        const grade = el('td', 'gs-num');
        grade.append(entry.grade ? el('b', 'gs-grade', entry.grade) : el('span', 'gs-no-grade', 'No grade'));
        if (flaggedRow) grade.append(' ', el('span', 'gs-flag', 'Check'));
        const remarks = el('td');
        remarks.append(remarkBadge(entry.remarks));
        const actions = el('td');
        const wrap = el('div', 'gs-row-actions');
        const viewButton = el('button', 'gs-icon-button gs-view-button');
        viewButton.type = 'button';
        viewButton.title = 'View source pages containing this student';
        viewButton.setAttribute('aria-label', 'View source pages containing this student');
        viewButton.innerHTML = '<i data-lucide="eye"></i><span>View</span>';
        viewButton.disabled = !Number(entry.batch_id || entry.document_id);
        if (viewButton.disabled) viewButton.title = 'No uploaded source is linked to this entry';
        viewButton.addEventListener('click', () => openSourcePages(entry, student, student.student_key));
        wrap.append(viewButton);
        wrap.append(iconButton('pencil', 'Edit record', () => openEditModal(entry)));
        actions.append(wrap);
        const entryUnits = entry.units === null || entry.units === undefined ? '—' : String(Number(entry.units));
        const yearCourse = entry.year_course || '—';
        row.append(
          el('td', 'gs-subject-code', entry.subject_code || '—'),
          el('td', '', entry.subject_name || '—'),
          el('td', 'gs-year-course', yearCourse),
          el('td', 'gs-num gs-col-num', entryUnits),
          grade,
          remarks,
          actions,
        );
        body.append(row);
      });
      table.append(body);
      const tableWrap = el('div', 'gs-table-wrap');
      tableWrap.append(table);
      card.append(head, tableWrap);
      return card;
    });
    const list = el('div', 'gs-term-list');
    list.append(...termCards);
    resultBox.replaceChildren(summary, list);
    refreshIcons();
  };

  const loadStudentGrades = async (student, { quiet = false } = {}) => {
    currentStudent = student;
    if (!quiet) resultBox.replaceChildren(el('div', 'gs-empty', 'Loading grades…'));
    const query = student.student_id
      ? `student_id=${encodeURIComponent(student.student_id)}`
      : `student_key=${encodeURIComponent(student.student_key || student.student_name || student.display_name)}`;
    try {
      renderStudentGrades(await api(`resource=gradesheets&${query}`));
    } catch (error) {
      const empty = el('div', 'gs-empty');
      empty.append(el('strong', '', 'No grades to show'), el('span', '', error.message));
      resultBox.replaceChildren(empty);
      if (quiet) currentStudent = null;
    }
  };

  loadBatches();
});
