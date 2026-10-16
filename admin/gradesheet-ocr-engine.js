(function exposeGradesheetOcrEngine(root) {
  const flow = new Proxy({}, {
    get: (_target, prop) => root.DatsGradesheetFlow?.[prop],
  });

  const getAppBasePath = () => {
    const path = window.location?.pathname || '';
    const adminIndex = path.toLowerCase().indexOf('/admin/');
    if (adminIndex !== -1) return path.slice(0, adminIndex);
    return root.DatsAppBase || '';
  };

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
    if (!root.pdfjsLib) {
      const module = await import('https://cdn.jsdelivr.net/npm/pdfjs-dist@4.10.38/build/pdf.min.mjs');
      root.pdfjsLib = module.default || module;
      root.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.10.38/build/pdf.worker.min.mjs';
    }
    return root.pdfjsLib;
  };

  // Handwritten-grade reader weights (a 28 KB file shipped with DATS; nothing is sent anywhere).
  // Absolute path: unlike the in-page copy of this loader, this module can be invoked from a
  // script injected into any page (any directory depth), so a relative path is not safe here.
  let digitModelPromise = null;
  const getDigitModel = () => {
    if (!digitModelPromise) {
      const base = getAppBasePath();
      digitModelPromise = fetch(`${base}/admin/assets/grade-digits.json`, { credentials: 'same-origin' })
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
        if (!root.Tesseract) await loadScript('https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js');
        const worker = await root.Tesseract.createWorker('eng', 1, {
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
        if (!root.Tesseract) await loadScript('https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js');
        const worker = await root.Tesseract.createWorker('eng', 1);
        await worker.setParameters({ tessedit_pageseg_mode: '7', tessedit_char_whitelist: flow.NAME_WHITELIST, preserve_interword_spaces: '1' });
        return worker;
      })().catch((error) => { nameWorkerPromise = null; throw error; });
    }
    return nameWorkerPromise;
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

  root.DatsGradesheetOcrEngine = { ocrFile, MAX_PAGES };
}(typeof globalThis !== 'undefined' ? globalThis : this));
