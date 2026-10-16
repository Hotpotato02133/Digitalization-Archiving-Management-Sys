(function exposeGradesheetFlow(root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.DatsGradesheetFlow = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, () => {
  const clean = (value) => String(value ?? '').trim();
  const normalizeStudentText = (value) => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase().replace(/[^A-Z0-9]/g, '');
  const studentNameVariants = (name) => {
    const value = clean(name);
    if (!value) return [];
    const variants = [value];
    if (value.includes(',')) {
      const [surname, given] = value.split(',', 2);
      variants.push(`${given} ${surname}`);
      const givenNames = given.split(/\s+/).filter((token) => token.length > 1);
      if (givenNames.length) variants.push(`${surname}, ${givenNames.join(' ')}`, `${givenNames.join(' ')} ${surname}`);
    } else {
      const tokens = value.split(/\s+/).filter(Boolean);
      if (tokens.length > 1) variants.push(`${tokens[tokens.length - 1]}, ${tokens.slice(0, -1).join(' ')}`);
      const fullNames = tokens.filter((token) => token.length > 1);
      if (fullNames.length > 1) variants.push(`${fullNames[fullNames.length - 1]}, ${fullNames.slice(0, -1).join(' ')}`);
    }
    return variants.map(normalizeStudentText).filter((variant) => variant.length >= 6);
  };
  const findStudentPageNumbers = (ocrText, names = []) => {
    const candidates = [...new Set(names.flatMap(studentNameVariants))];
    if (!candidates.length) return [];
    return String(ocrText ?? '').split(/\f/).reduce((matches, pageText, index) => {
      const page = normalizeStudentText(pageText);
      if (candidates.some((candidate) => page.includes(candidate))) matches.push(index + 1);
      return matches;
    }, []);
  };
  const findStudentRecordPageNumbers = (ocrText, names = [], record = {}) => {
    const nameCandidates = [...new Set(names.flatMap(studentNameVariants))];
    const subjectCandidates = [record.subject_code, record.subject_name]
      .map(normalizeStudentText)
      .filter((candidate) => candidate.length >= 3);
    if (!nameCandidates.length || !subjectCandidates.length) return [];
    return String(ocrText ?? '').split(/\f/).reduce((matches, pageText, index) => {
      const page = normalizeStudentText(pageText);
      const hasStudent = nameCandidates.some((candidate) => page.includes(candidate));
      const hasSubject = subjectCandidates.some((candidate) => page.includes(candidate));
      if (hasStudent && hasSubject) matches.push(index + 1);
      return matches;
    }, []);
  };

  const GRADESHEET_SEMESTERS = ['1st Semester', '2nd Semester', 'Summer', 'Midyear'];

  const validYear = (value) => /^\d{4}$/.test(clean(value))
    && Number(value) >= 2000 && Number(value) <= 2100;

  /** Mirrors update_gradesheet_entry() in api.php so the edit form fails fast with the same message. */
  const validateEntry = (entry = {}) => {
    if (!clean(entry.student_name)) return 'Student name is required.';
    if (!clean(entry.subject_code) || !clean(entry.subject_name)) return 'Subject code and title are required.';
    if (!validYear(entry.academic_year)) return 'Enter the school year as a 4-digit starting year (e.g. 2014 for S.Y. 2014–2015).';
    if (!GRADESHEET_SEMESTERS.includes(clean(entry.semester))) return 'Select a semester.';
    const units = clean(entry.units);
    if (units && (!Number.isFinite(Number(units)) || Number(units) < 0 || Number(units) > 99.99)) {
      return 'Units must be a number between 0 and 99.99.';
    }
    if (clean(entry.grade).length > 10) return 'Grade must be 10 characters or fewer.';
    if (clean(entry.remarks).length > 100) return 'Remarks must be 100 characters or fewer.';
    return null;
  };

  const formatSchoolYear = (year) => (validYear(year) ? `${year}–${Number(year) + 1}` : '—');

  const initials = (name) => {
    const [surname = '', given = ''] = clean(name).split(',');
    const letters = `${clean(given).charAt(0)}${clean(surname).charAt(0)}`.toUpperCase();
    return letters || '?';
  };

  const termAverage = (rows = []) => {
    let units = 0;
    let weighted = 0;
    rows.forEach((row) => {
      const grade = Number(row.grade);
      const rowUnits = Number(row.units);
      if (!clean(row.grade) || !Number.isFinite(grade) || !Number.isFinite(rowUnits) || rowUnits <= 0) return;
      units += rowUnits;
      weighted += rowUnits * grade;
    });
    return units > 0 ? weighted / units : null;
  };

  /** Groups one student's entries into school-year/semester terms, oldest first. */
  const groupEntriesByTerm = (entries = []) => {
    const order = (semester) => {
      const index = GRADESHEET_SEMESTERS.indexOf(semester);
      return index === -1 ? GRADESHEET_SEMESTERS.length : index;
    };
    const groups = new Map();
    entries.forEach((entry) => {
      const key = `${entry.academic_year || ''}|${entry.semester || ''}`;
      if (!groups.has(key)) groups.set(key, { academic_year: entry.academic_year || null, semester: entry.semester || null, rows: [] });
      groups.get(key).rows.push(entry);
    });
    return [...groups.values()]
      .sort((a, b) => (Number(a.academic_year) || 9999) - (Number(b.academic_year) || 9999) || order(a.semester) - order(b.semester))
      .map((group) => ({
        ...group,
        rows: group.rows.slice().sort((a, b) => clean(a.subject_code).localeCompare(clean(b.subject_code))),
        average: termAverage(group.rows),
      }));
  };

  // ---------------------------------------------------------------------------
  // Scan preprocessing. Pure functions over RGBA pixel buffers ({width, height, data}) so the
  // exact same code runs on a browser canvas ImageData and in Node tests.
  // ---------------------------------------------------------------------------

  /** Otsu's method: the luminance cut that best separates ink from paper on this particular scan. */
  const otsuThreshold = (histogram, total) => {
    let sum = 0;
    for (let i = 0; i < 256; i += 1) sum += i * histogram[i];
    let sumBackground = 0;
    let weightBackground = 0;
    let best = 0;
    let threshold = 150;
    for (let i = 0; i < 256; i += 1) {
      weightBackground += histogram[i];
      if (!weightBackground) continue;
      const weightForeground = total - weightBackground;
      if (!weightForeground) break;
      sumBackground += i * histogram[i];
      const meanBackground = sumBackground / weightBackground;
      const meanForeground = (sum - sumBackground) / weightForeground;
      const between = weightBackground * weightForeground * (meanBackground - meanForeground) ** 2;
      if (between > best) { best = between; threshold = i; }
    }
    return threshold;
  };

  /**
   * Binarizes the page and erases table rules. Gradesheets are ruled grids, and Tesseract reads
   * the grid lines as "|[]" noise that splits names apart; on a real ZPPSU Report of Rating this
   * step took one page from ~12 readable student names to ~36.
   */
  const cleanTableImageData = (imageData) => {
    const { width, height, data } = imageData;
    const pixels = width * height;
    const luminance = new Uint8Array(pixels);
    const histogram = new Array(256).fill(0);
    for (let i = 0; i < pixels; i += 1) {
      const value = Math.round(0.299 * data[i * 4] + 0.587 * data[i * 4 + 1] + 0.114 * data[i * 4 + 2]);
      luminance[i] = value;
      histogram[value] += 1;
    }
    // Clamped so a page that is mostly blank (or mostly dark) can't push the cut to an extreme.
    const threshold = Math.min(180, Math.max(120, otsuThreshold(histogram, pixels)));
    const dark = new Uint8Array(pixels);
    for (let i = 0; i < pixels; i += 1) dark[i] = luminance[i] < threshold ? 1 : 0;

    const erase = new Uint8Array(pixels);
    const minRowRun = Math.floor(width * 0.08);
    for (let y = 0; y < height; y += 1) {
      let run = 0;
      for (let x = 0; x <= width; x += 1) {
        if (x < width && dark[y * width + x]) { run += 1; continue; }
        if (run >= minRowRun) for (let k = x - run; k < x; k += 1) erase[y * width + k] = 1;
        run = 0;
      }
    }
    const minColumnRun = Math.floor(height * 0.05);
    for (let x = 0; x < width; x += 1) {
      let run = 0;
      for (let y = 0; y <= height; y += 1) {
        if (y < height && dark[y * width + x]) { run += 1; continue; }
        if (run >= minColumnRun) for (let k = y - run; k < y; k += 1) erase[k * width + x] = 1;
        run = 0;
      }
    }
    for (let i = 0; i < pixels; i += 1) {
      const value = dark[i] && !erase[i] ? 0 : 255;
      data[i * 4] = value;
      data[i * 4 + 1] = value;
      data[i * 4 + 2] = value;
      data[i * 4 + 3] = 255;
    }
    return imageData;
  };

  const ORIENTATION_KEYWORDS = /semester|course|student|grade|rating|remarks|subject|units|description/i;

  /**
   * Scores one OCR attempt at a given rotation by how many real words it read confidently.
   * Tesseract's average confidence alone is NOT reliable here: on a real sideways gradesheet the
   * wrong rotation still averaged 65. Counting confident alphabetic words is decisive — the right
   * rotation read 39 of them, every wrong rotation 0-2. Must be measured with the same page
   * segmentation mode as the final pass (some modes silently read vertical text on their own).
   */
  const orientationScore = (result = {}) => {
    const words = Array.isArray(result.words) ? result.words : [];
    const confident = words.filter((word) => Number(word.confidence) >= 70 && /^[A-Za-z]{3,}[.,:]?$/.test(String(word.text || ''))).length;
    return confident + (ORIENTATION_KEYWORDS.test(String(result.text || '')) ? 10 : 0);
  };

  // An orientation this good is accepted immediately, so a normal upright scan costs one pass.
  const ORIENTATION_ACCEPT_SCORE = 30;

  /** Rotations to try, the last page's winner first (a scanned batch is almost always uniform). */
  const orientationCandidates = (preferred) => {
    const order = [preferred, 0, 180, 90, 270].filter((value) => [0, 90, 180, 270].includes(value));
    return [...new Set(order)];
  };

  /**
   * Overall progress for a multi-file, multi-page OCR run. Callers must also keep it monotonic;
   * this only maps (file, page, stage) to a 0..1 position.
   */
  const overallProgress = ({ fileIndex, fileCount, pageIndex, pageCount, pageFraction }) => {
    const files = Math.max(1, fileCount);
    const pages = Math.max(1, pageCount);
    const within = (Math.min(pageIndex, pages - 1) + Math.min(1, Math.max(0, pageFraction))) / pages;
    return Math.min(1, (fileIndex + within) / files);
  };

  // ---------------------------------------------------------------------------
  // Handwritten Final Grade reading.
  //
  // Tesseract is built for print and reads handwritten grades as noise (≈4 of 48 correct on a
  // real ZPPSU sheet). Instead each grade cell is split into digit glyphs, every glyph is read by
  // a small convolutional network trained on the MNIST handwritten-digit set and fine-tuned on digits
  // cut from real ZPPSU sheets (weights shipped as admin/assets/grade-digits.json, ~28 KB), and the
  // digits are matched against the only values a grade can take. Tested on pages held out from the
  // fine-tuning, it read 187 of 201 handwritten cells correctly; reads below the confidence bar are
  // saved with a "?" so an admin checks them, and of the rest only 1 of 158 was wrong.
  // ---------------------------------------------------------------------------

  const decodeBase64 = (text) => {
    if (typeof atob === 'function') {
      const binary = atob(text);
      const bytes = new Uint8Array(binary.length);
      for (let i = 0; i < binary.length; i += 1) bytes[i] = binary.charCodeAt(i);
      return bytes;
    }
    return Uint8Array.from(Buffer.from(text, 'base64'));
  };

  /** Turns the shipped int8-quantized weights file into Float32 parameter arrays. */
  const loadDigitModel = (json) => {
    if (!json || json.format !== 'dats-digit-cnn-v1') throw new Error('Unrecognized grade model file.');
    const params = {};
    Object.entries(json.params).forEach(([name, { scale, data }]) => {
      const bytes = decodeBase64(data);
      params[name] = Float32Array.from(new Int8Array(bytes.buffer, bytes.byteOffset, bytes.length), (v) => v * scale);
    });
    return params;
  };

  /** Inference for the digit network: 28x28 -> conv5x5x8 -> pool -> conv5x5x16 -> pool -> 64 -> 10. */
  const classifyDigit = (p, x) => {
    const a1 = new Float32Array(8 * 576);
    for (let f = 0; f < 8; f += 1) {
      for (let y = 0; y < 24; y += 1) {
        for (let xx = 0; xx < 24; xx += 1) {
          let s = p.b1[f];
          for (let ky = 0; ky < 5; ky += 1) {
            const row = (y + ky) * 28 + xx; const w = f * 25 + ky * 5;
            s += x[row] * p.W1[w] + x[row + 1] * p.W1[w + 1] + x[row + 2] * p.W1[w + 2] + x[row + 3] * p.W1[w + 3] + x[row + 4] * p.W1[w + 4];
          }
          a1[f * 576 + y * 24 + xx] = s > 0 ? s : 0;
        }
      }
    }
    const p1 = new Float32Array(8 * 144);
    for (let f = 0; f < 8; f += 1) {
      for (let y = 0; y < 12; y += 1) {
        for (let xx = 0; xx < 12; xx += 1) {
          const i = f * 576 + 2 * y * 24 + 2 * xx;
          p1[f * 144 + y * 12 + xx] = Math.max(a1[i], a1[i + 1], a1[i + 24], a1[i + 25]);
        }
      }
    }
    const a2 = new Float32Array(16 * 64);
    for (let f = 0; f < 16; f += 1) {
      for (let y = 0; y < 8; y += 1) {
        for (let xx = 0; xx < 8; xx += 1) {
          let s = p.b2[f];
          for (let c = 0; c < 8; c += 1) {
            for (let ky = 0; ky < 5; ky += 1) {
              const row = c * 144 + (y + ky) * 12 + xx; const w = (f * 8 + c) * 25 + ky * 5;
              s += p1[row] * p.W2[w] + p1[row + 1] * p.W2[w + 1] + p1[row + 2] * p.W2[w + 2] + p1[row + 3] * p.W2[w + 3] + p1[row + 4] * p.W2[w + 4];
            }
          }
          a2[f * 64 + y * 8 + xx] = s > 0 ? s : 0;
        }
      }
    }
    const p2 = new Float32Array(256);
    for (let f = 0; f < 16; f += 1) {
      for (let y = 0; y < 4; y += 1) {
        for (let xx = 0; xx < 4; xx += 1) {
          const i = f * 64 + 2 * y * 8 + 2 * xx;
          p2[f * 16 + y * 4 + xx] = Math.max(a2[i], a2[i + 1], a2[i + 8], a2[i + 9]);
        }
      }
    }
    const h = new Float32Array(64);
    for (let o = 0; o < 64; o += 1) {
      let s = p.b3[o];
      for (let i = 0; i < 256; i += 1) s += p2[i] * p.W3[o * 256 + i];
      h[o] = s > 0 ? s : 0;
    }
    const z = new Float32Array(10);
    let max = -Infinity;
    for (let o = 0; o < 10; o += 1) {
      let s = p.b4[o];
      for (let i = 0; i < 64; i += 1) s += h[i] * p.W4[o * 64 + i];
      z[o] = s;
      if (s > max) max = s;
    }
    let sum = 0;
    for (let o = 0; o < 10; o += 1) { z[o] = Math.exp(z[o] - max); sum += z[o]; }
    for (let o = 0; o < 10; o += 1) z[o] /= sum;
    return z;
  };

  /** Ink pixels of one cell, with any table-rule remnants along its edges removed. */
  const cellInkMask = ({ width, height, data }) => {
    const n = width * height;
    const luminance = new Uint8Array(n);
    const histogram = new Array(256).fill(0);
    for (let i = 0; i < n; i += 1) {
      const value = Math.round(0.299 * data[i * 4] + 0.587 * data[i * 4 + 1] + 0.114 * data[i * 4 + 2]);
      luminance[i] = value;
      histogram[value] += 1;
    }
    const threshold = Math.min(185, Math.max(110, otsuThreshold(histogram, n)));
    const ink = new Uint8Array(n);
    for (let i = 0; i < n; i += 1) ink[i] = luminance[i] < threshold ? 1 : 0;
    for (let y = 0; y < height; y += 1) {
      let run = 0;
      for (let x = 0; x <= width; x += 1) {
        if (x < width && ink[y * width + x]) { run += 1; continue; }
        if (run > width * 0.6) for (let k = x - run; k < x; k += 1) ink[y * width + k] = 0;
        run = 0;
      }
    }
    for (let x = 0; x < width; x += 1) {
      let run = 0;
      for (let y = 0; y <= height; y += 1) {
        if (y < height && ink[y * width + x]) { run += 1; continue; }
        if (run > height * 0.7) for (let k = y - run; k < y; k += 1) ink[k * width + x] = 0;
        run = 0;
      }
    }
    return { ink, width, height };
  };

  const glyphBox = (pixels, width) => {
    const glyph = { pixels, x0: Infinity, y0: Infinity, x1: -1, y1: -1 };
    pixels.forEach((p) => {
      const x = p % width; const y = (p - x) / width;
      if (x < glyph.x0) glyph.x0 = x;
      if (x > glyph.x1) glyph.x1 = x;
      if (y < glyph.y0) glyph.y0 = y;
      if (y > glyph.y1) glyph.y1 = y;
    });
    return { ...glyph, w: glyph.x1 - glyph.x0 + 1, h: glyph.y1 - glyph.y0 + 1, area: pixels.length };
  };

  const connectedComponents = ({ ink, width, height }) => {
    const seen = new Uint8Array(width * height);
    const found = [];
    for (let start = 0; start < width * height; start += 1) {
      if (!ink[start] || seen[start]) continue;
      const stack = [start];
      const pixels = [];
      seen[start] = 1;
      while (stack.length) {
        const p = stack.pop();
        pixels.push(p);
        const x = p % width; const y = (p - x) / width;
        for (let dy = -1; dy <= 1; dy += 1) {
          for (let dx = -1; dx <= 1; dx += 1) {
            const nx = x + dx; const ny = y + dy;
            if (nx < 0 || ny < 0 || nx >= width || ny >= height) continue;
            const q = ny * width + nx;
            if (ink[q] && !seen[q]) { seen[q] = 1; stack.push(q); }
          }
        }
      }
      found.push(glyphBox(pixels, width));
    }
    return found;
  };

  /** Splits a grade cell into digit glyphs (left to right); decimal points and specks are dropped. */
  const segmentGradeCell = (image) => {
    const mask = cellInkMask(image);
    const { width, height } = mask;
    let parts = connectedComponents(mask).filter((c) => c.area >= 6);
    parts = parts.filter((c) => !((c.x0 <= 1 || c.x1 >= width - 2) && c.w < Math.max(4, c.h * 0.3)));
    parts = parts.filter((c) => !((c.y0 <= 1 || c.y1 >= height - 2) && c.h < Math.max(4, c.w * 0.3)));
    if (!parts.length || parts.reduce((n, c) => n + c.area, 0) < 40) return { blank: true, glyphs: [], mask };
    const tallest = Math.max(...parts.map((c) => c.h));
    const glyphs = parts.filter((c) => c.h >= tallest * 0.5).sort((a, b) => a.x0 - b.x0);
    if (!glyphs.length) return { blank: true, glyphs: [], mask };
    const merged = [];
    glyphs.forEach((g) => {
      const previous = merged[merged.length - 1];
      if (previous) {
        const overlap = Math.min(previous.x1, g.x1) - Math.max(previous.x0, g.x0);
        if (overlap > Math.min(previous.w, g.w) * 0.45) {
          merged[merged.length - 1] = glyphBox(previous.pixels.concat(g.pixels), width);
          return;
        }
      }
      merged.push(g);
    });
    return { blank: false, glyphs: merged, mask };
  };

  const splitWideGlyph = (glyph, width) => {
    const columns = new Array(glyph.w).fill(0);
    glyph.pixels.forEach((p) => { columns[(p % width) - glyph.x0] += 1; });
    let cut = Math.floor(glyph.w / 2);
    let least = Infinity;
    for (let i = Math.floor(glyph.w * 0.3); i <= Math.floor(glyph.w * 0.7); i += 1) {
      if (columns[i] < least) { least = columns[i]; cut = i; }
    }
    const left = []; const right = [];
    glyph.pixels.forEach((p) => ((p % width) - glyph.x0 < cut ? left : right).push(p));
    return [left, right].filter((pixels) => pixels.length).map((pixels) => glyphBox(pixels, width));
  };

  /** MNIST-style 28x28 input: fit in 20x20 keeping aspect, stroke thickened, centered by mass. */
  const glyphToMnist = (glyph, width) => {
    const bw = glyph.w; const bh = glyph.h;
    const source = new Uint8Array(bw * bh);
    glyph.pixels.forEach((p) => {
      const x = p % width; const y = (p - x) / width;
      source[(y - glyph.y0) * bw + (x - glyph.x0)] = 1;
    });
    const scale = 20 / Math.max(bw, bh);
    const radius = Math.max(0, Math.round(1.1 / scale - 1));
    const thick = new Uint8Array(bw * bh);
    for (let y = 0; y < bh; y += 1) {
      for (let x = 0; x < bw; x += 1) {
        if (!source[y * bw + x]) continue;
        for (let dy = -radius; dy <= radius; dy += 1) {
          for (let dx = -radius; dx <= radius; dx += 1) {
            const nx = x + dx; const ny = y + dy;
            if (nx >= 0 && ny >= 0 && nx < bw && ny < bh) thick[ny * bw + nx] = 1;
          }
        }
      }
    }
    const tw = Math.max(1, Math.round(bw * scale)); const th = Math.max(1, Math.round(bh * scale));
    const small = new Float32Array(tw * th);
    for (let y = 0; y < th; y += 1) {
      for (let x = 0; x < tw; x += 1) {
        const sx0 = Math.floor(x / scale); const sx1 = Math.max(sx0 + 1, Math.floor((x + 1) / scale));
        const sy0 = Math.floor(y / scale); const sy1 = Math.max(sy0 + 1, Math.floor((y + 1) / scale));
        let sum = 0; let count = 0;
        for (let yy = sy0; yy < Math.min(bh, sy1); yy += 1) {
          for (let xx = sx0; xx < Math.min(bw, sx1); xx += 1) { sum += thick[yy * bw + xx]; count += 1; }
        }
        small[y * tw + x] = count ? sum / count : 0;
      }
    }
    let mx = 0; let my = 0; let mass = 0;
    for (let y = 0; y < th; y += 1) {
      for (let x = 0; x < tw; x += 1) { const v = small[y * tw + x]; mx += x * v; my += y * v; mass += v; }
    }
    const ox = Math.round(14 - (mass ? mx / mass : tw / 2)); const oy = Math.round(14 - (mass ? my / mass : th / 2));
    const out = new Float32Array(784);
    for (let y = 0; y < th; y += 1) {
      for (let x = 0; x < tw; x += 1) {
        const X = x + ox; const Y = y + oy;
        if (X >= 0 && Y >= 0 && X < 28 && Y < 28) out[Y * 28 + X] = Math.min(1, small[y * tw + x] * 1.3);
      }
    }
    return out;
  };

  // Every way a valid grade is written, as its digit sequence (the decimal point is not a digit).
  const GRADE_SPELLINGS = [
    ['10', '1.0'], ['100', '1.0'], ['125', '1.25'], ['15', '1.5'], ['150', '1.5'], ['175', '1.75'],
    ['20', '2.0'], ['200', '2.0'], ['225', '2.25'], ['25', '2.5'], ['250', '2.5'], ['275', '2.75'],
    ['30', '3.0'], ['300', '3.0'], ['40', '4.0'], ['50', '5.0'], ['500', '5.0'],
  ];

  /** A read at or above this confidence is saved as-is; below it, the grade is saved but flagged. */
  const GRADE_CONFIDENCE_ACCEPT = 0.95;

  /**
   * Reads one handwritten grade cell. Tries a few segmentations (as-is, a wide glyph split in two,
   * a short glyph taken as the decimal point, an edge sliver dropped), reads every glyph and scores
   * each legal spelling. Confidence is the posterior of the winning grade over all hypotheses, so an
   * ambiguous cell reports low confidence rather than a confident misread.
   */
  /**
   * Candidate digit segmentations of a grade cell, each with a log-penalty. First what to drop
   * (nothing, a short glyph taken as the decimal point, a leading edge sliver), then on each of
   * those, one repair of joined digits (a wide glyph split in two, or two touching glyphs re-joined
   * and re-split where a "7" bar running into the "5" misled the first cut).
   */
  const gradeHypotheses = (glyphs, width) => {
    const tallest = Math.max(...glyphs.map((g) => g.h));
    const medianArea = glyphs.map((g) => g.area).sort((a, b) => a - b)[Math.floor(glyphs.length / 2)];
    const bases = [{ glyphs, penalty: 0 }];
    glyphs.forEach((g, i) => {
      if (i > 0 && i < glyphs.length - 1 && g.h < tallest * 0.72 && g.area < medianArea * 0.6) {
        bases.push({ glyphs: glyphs.filter((_, k) => k !== i), penalty: 0.4 });
      }
      if (i === 0 && glyphs.length >= 3 && g.w < g.h * 0.3) bases.push({ glyphs: glyphs.slice(1), penalty: 1.0 });
    });
    const hypotheses = [];
    bases.forEach((base) => {
      hypotheses.push(base);
      const list = base.glyphs;
      list.forEach((g, i) => {
        if (g.w > g.h * 0.75) {
          hypotheses.push({ glyphs: [...list.slice(0, i), ...splitWideGlyph(g, width), ...list.slice(i + 1)], penalty: base.penalty + (g.w > g.h * 1.05 ? 0.3 : 1.2) });
        }
        const next = list[i + 1];
        if (next && next.x0 <= g.x1 + 2) {
          const joined = glyphBox(g.pixels.concat(next.pixels), width);
          hypotheses.push({ glyphs: [...list.slice(0, i), ...splitWideGlyph(joined, width), ...list.slice(i + 2)], penalty: base.penalty + 0.5 });
        }
      });
    });
    return hypotheses;
  };

  const readHandwrittenGrade = (image, model) => {
    const segmented = segmentGradeCell(image);
    if (segmented.blank) return { blank: true, grade: null, confidence: 1 };
    const { glyphs, mask } = segmented;
    const hypotheses = gradeHypotheses(glyphs, mask.width);

    const probabilities = new Map();
    const probabilitiesFor = (glyph) => {
      if (!probabilities.has(glyph)) probabilities.set(glyph, classifyDigit(model, glyphToMnist(glyph, mask.width)));
      return probabilities.get(glyph);
    };
    const scored = [];
    hypotheses.forEach((hypothesis) => {
      if (hypothesis.glyphs.length < 2 || hypothesis.glyphs.length > 3) return;
      const probs = hypothesis.glyphs.map(probabilitiesFor);
      GRADE_SPELLINGS.forEach(([spelling, grade]) => {
        if (spelling.length !== probs.length) return;
        let logp = -hypothesis.penalty;
        for (let k = 0; k < spelling.length; k += 1) logp += Math.log(Math.max(1e-6, probs[k][Number(spelling[k])]));
        scored.push({ grade, logp, glyphs: spelling.length });
      });
    });
    if (!scored.length) return { blank: false, grade: null, confidence: 0 };
    const top = Math.max(...scored.map((s) => s.logp));
    const mass = new Map();
    scored.forEach((s) => mass.set(s.grade, (mass.get(s.grade) || 0) + Math.exp(s.logp - top)));
    const total = [...mass.values()].reduce((a, b) => a + b, 0);
    let best = null;
    mass.forEach((value, grade) => { if (!best || value > best.value) best = { grade, value }; });
    // The posterior only compares legal grades with each other; when even the best one fits the
    // ink poorly (per-digit probability under 0.3), the read is not trusted however it ranks.
    const winner = scored.find((s) => s.logp === top);
    const fit = Math.exp(top / winner.glyphs);
    return { blank: false, grade: best.grade, confidence: fit >= 0.3 ? best.value / total : 0 };
  };

  // ---------------------------------------------------------------------------
  // Table grid: locates each student row's Name / Course / Final Grade / Remarks cells, so rows
  // are read cell by cell instead of guessing column boundaries from OCR text lines.
  // ---------------------------------------------------------------------------

  const darkPixels = (imageData) => {
    const { width, height, data } = imageData;
    const n = width * height;
    const luminance = new Uint8Array(n);
    const histogram = new Array(256).fill(0);
    for (let i = 0; i < n; i += 1) {
      const value = Math.round(0.299 * data[i * 4] + 0.587 * data[i * 4 + 1] + 0.114 * data[i * 4 + 2]);
      luminance[i] = value;
      histogram[value] += 1;
    }
    const threshold = Math.min(180, Math.max(120, otsuThreshold(histogram, n)));
    const dark = new Uint8Array(n);
    for (let i = 0; i < n; i += 1) dark[i] = luminance[i] < threshold ? 1 : 0;
    return dark;
  };

  /** Centers of runs of profile values at or above `threshold`. */
  const profilePeaks = (profile, threshold, from = 0, to = profile.length) => {
    const peaks = [];
    let start = -1;
    for (let i = from; i <= to; i += 1) {
      const on = i < to && profile[i] >= threshold;
      if (on && start < 0) start = i;
      if (!on && start >= 0) { peaks.push(Math.round((start + i - 1) / 2)); start = -1; }
    }
    return peaks;
  };

  const wordCenter = (word) => ({ x: (word.bbox.x0 + word.bbox.x1) / 2, y: (word.bbox.y0 + word.bbox.y1) / 2 });

  /**
   * Finds ruled grade tables: vertical rules from a column-darkness profile (tolerant of slight
   * skew, unlike looking for unbroken lines), the Final Grade column from the "Final"/"Grade"
   * header word, neighbouring columns from the rules beside it, and row rules within each table.
   */
  const locateGradeTables = (imageData, words, trace = null) => {
    const note = (message) => { if (trace) trace.push(message); };
    const { width, height } = imageData;
    const dark = darkPixels(imageData);
    // Per column, the number of rows with ink within ±3px: a rule on a slightly skewed scan
    // drifts sideways, so counting exact pixel columns spread it too thin to stand out.
    const columnProfile = new Uint32Array(width);
    for (let y = 0; y < height; y += 1) {
      const row = y * width;
      let inWindow = 0;
      for (let x = 0; x < Math.min(3, width); x += 1) inWindow += dark[row + x];
      for (let x = 0; x < width; x += 1) {
        if (x + 3 < width) inWindow += dark[row + x + 3];
        if (x - 4 >= 0) inWindow -= dark[row + x - 4];
        if (inWindow) columnProfile[x] += 1;
      }
    }
    // Table rules are the darkest columns on the page by far (they run the full table height);
    // text columns are much lighter. A fixed fraction of page height alone let dense text columns
    // through, so the cut is also tied to the darkest column.
    let darkest = 0;
    for (let x = 0; x < width; x += 1) if (columnProfile[x] > darkest) darkest = columnProfile[x];
    const vertical = profilePeaks(columnProfile, Math.max(height * 0.12, darkest * 0.55))
      .reduce((merged, x) => {
        if (merged.length && x - merged[merged.length - 1] <= 8) merged[merged.length - 1] = Math.round((merged[merged.length - 1] + x) / 2);
        else merged.push(x);
        return merged;
      }, []);
    note(`vertical rules: ${vertical.join(" ")}`);
    if (vertical.length < 3) return [];

    // Header words that pin down the Final Grade column: the "Final"/"Grade" heading itself, or the
    // headings of the columns on either side of it ("Course" to its left, "Remarks" to its right),
    // since OCR does not always read every header word. Positions use where the word starts,
    // because a heading can be read glued to its neighbour ("Grade Remarks").
    const anchors = words
      .map((word) => {
        const letters = String(word.text || '').replace(/[^A-Za-z]/g, '');
        const kind = /^fina?l?$|^final|^grade$/i.test(letters) ? 'grade'
          : /^remark/i.test(letters) ? 'remarks'
            : /^course$/i.test(letters) ? 'course' : null;
        if (!kind) return null;
        const { x0, x1, y0, y1 } = word.bbox;
        // The column holding most of the word's first 120px (a glued "GradeRemarks" still lands
        // in the grade column; a box that starts a little left of its rule does not jump columns).
        const end = Math.min(x1, x0 + 120);
        let x = x0 + Math.min(24, (x1 - x0) / 2);
        let bestOverlap = 0;
        [0, ...vertical, width].forEach((left, i, edges) => {
          const right = edges[i + 1];
          if (right === undefined) return;
          const overlap = Math.min(end, right) - Math.max(x0, left);
          if (overlap > bestOverlap) { bestOverlap = overlap; x = (left + right) / 2; }
        });
        return { kind, x, y: (y0 + y1) / 2 };
      })
      .filter(Boolean)
      .sort((a, b) => (a.kind === 'grade' ? 0 : 1) - (b.kind === 'grade' ? 0 : 1) || a.x - b.x);
    const tables = [];
    // Fraction of the table's height over which column x is inked (±6px, for scan skew). A real
    // rule is continuous (~1.0); a column of repeated text such as "BEED II-A" has gaps between rows.
    const continuity = (x, top, bottom) => {
      let covered = 0;
      for (let y = top; y < bottom; y += 1) {
        const row = y * width;
        for (let dx = -6; dx <= 6; dx += 1) {
          if (x + dx >= 0 && x + dx < width && dark[row + x + dx]) { covered += 1; break; }
        }
      }
      return bottom > top ? covered / (bottom - top) : 0;
    };

    anchors.forEach((anchor) => {
      const leftIn = (list, x) => list.filter((v) => v < x - 4).pop();
      const rightIn = (list, x) => list.find((v) => v > x + 4);
      // The column the header word sits in, then the grade column relative to it.
      const wordLeft = leftIn(vertical, anchor.x); const wordRight = rightIn(vertical, anchor.x);
      // The Remarks column's outer rule can be lost at the page edge; only its left rule matters.
      if (wordLeft === undefined || (wordRight === undefined && anchor.kind !== 'remarks')) return;
      let firstLeft = wordLeft; let firstRight = wordRight;
      if (anchor.kind === 'remarks') { firstRight = wordLeft; firstLeft = leftIn(vertical, wordLeft); }
      if (anchor.kind === 'course') { firstLeft = wordRight; firstRight = rightIn(vertical, wordRight); }
      note(`anchor ${anchor.kind} (${Math.round(anchor.x)},${Math.round(anchor.y)}) candidate grade col ${firstLeft}-${firstRight}`);
      if (firstLeft === undefined || firstRight === undefined) return;
      if (tables.some((t) => firstLeft >= t.columns.grade[0] - 6 && firstRight <= t.columns.grade[1] + 6)) return;

      // Rows first, measured inside the Final Grade column only (handwriting there is sparse).
      const rowsWithin = (x0, x1) => {
        const rowProfile = new Uint32Array(height);
        for (let y = 0; y < height; y += 1) {
          let count = 0;
          const row = y * width;
          for (let x = x0; x < x1; x += 1) count += dark[row + x];
          rowProfile[y] = count;
        }
        return profilePeaks(rowProfile, (x1 - x0) * 0.5);
      };
      const probeLines = rowsWithin(firstLeft, firstRight);
      const probeBands = probeLines.slice(1).map((y, i) => [probeLines[i], y]).filter(([, bottom]) => bottom > anchor.y);
      if (probeBands.length < 3) return;
      // The table ends at the first band far taller than a row (the gap down to the legend).
      const probeHeights = probeBands.map(([top, bottom]) => bottom - top).sort((a, b) => a - b);
      const probeTypical = probeHeights[Math.floor(probeHeights.length / 2)];
      const spanTop = probeBands[0][0];
      let spanBottom = spanTop;
      for (const [top, bottom] of probeBands.slice(1)) {
        if (bottom - top > probeTypical * 2.2) break;
        spanBottom = bottom;
      }
      if (spanBottom - spanTop < probeTypical * 2) return;

      // Keep only continuous rules, then read the column layout from those.
      const rules = vertical.filter((x) => continuity(x, spanTop, spanBottom) >= 0.8);
      note(`  continuous rules: ${rules.join(' ')}`);
      const gradeMiddle = (firstLeft + firstRight) / 2;
      const gradeLeft = leftIn(rules, gradeMiddle); const gradeRight = rightIn(rules, gradeMiddle);
      if (gradeLeft === undefined || gradeRight === undefined) return;
      const cellWidth = gradeRight - gradeLeft;
      if (cellWidth < width * 0.02 || cellWidth > width * 0.2) return;
      if (tables.some((t) => t.columns.grade[0] === gradeLeft)) return; // "Final" and "Grade" share a column
      const courseLeft = leftIn(rules, gradeLeft);
      const nameLeft = courseLeft === undefined ? undefined : leftIn(rules, courseLeft);
      if (courseLeft === undefined || nameLeft === undefined) return;
      const numberLeft = leftIn(rules, nameLeft);
      const remarksRight = rightIn(rules, gradeRight) ?? Math.min(width - 1, gradeRight + cellWidth * 1.3);

      // Row rules measured per column, not across the whole table: on a slightly skewed scan a
      // rule drifts several pixels over the table's width, so no single pixel row is dark enough
      // end to end. A rule must show up in at least two of the name/course/grade columns, which
      // also keeps a stray handwritten stroke in one cell from passing as a rule.
      const perColumn = [[nameLeft, courseLeft], [courseLeft, gradeLeft], [gradeLeft, gradeRight]]
        .map(([left, right]) => rowsWithin(left, right));
      const lines = [];
      perColumn.flat().sort((a, b) => a - b).forEach((y) => {
        if (lines.some((line) => Math.abs(line - y) <= 10)) return;
        const hits = perColumn.filter((column) => column.some((v) => Math.abs(v - y) <= 10));
        if (hits.length < 2) return;
        const near = hits.map((column) => column.reduce((best, v) => (Math.abs(v - y) < Math.abs(best - y) ? v : best)));
        lines.push(Math.round(near.reduce((sum, v) => sum + v, 0) / near.length));
      });
      lines.sort((a, b) => a - b);
      const bands = lines.slice(1).map((y, i) => [lines[i], y]).filter(([top, bottom]) => bottom > anchor.y);
      note(`  row rules: ${lines.join(" ")}`);
      if (bands.length < 2) return;
      // The first band below/around the header word is the header itself; data rows follow it.
      const headerIndex = bands.findIndex(([top, bottom]) => anchor.y >= top && anchor.y <= bottom);
      const rows = bands.slice(headerIndex + 1);
      const heights = rows.map(([top, bottom]) => bottom - top).sort((a, b) => a - b);
      const typical = heights[Math.floor(heights.length / 2)] || 0;
      const dataRows = [];
      for (const [top, bottom] of rows) {
        const h = bottom - top;
        if (h < typical * 0.55) continue;
        if (h > typical * 1.8) break; // past the last row (e.g. the gap down to the grading legend)
        dataRows.push([top, bottom]);
      }
      note(`  header band index ${headerIndex}, typical row ${typical}px, data rows ${dataRows.length}`);
      if (dataRows.length < 2) return;
      // The anchoring word must be a heading of this table (e.g. not the "Course No.:" line at the
      // top of the page), and a table is only found once however many headings point at it.
      if (headerIndex < 0 || anchor.y < bands[headerIndex][0] - typical * 0.5) return;
      const span = [nameLeft, Math.round(remarksRight)];
      if (tables.some((t) => Math.min(span[1], t.columns.remarks[1]) - Math.max(span[0], t.columns.name[0]) > (span[1] - span[0]) * 0.3)) return;
      tables.push({
        top: bands[Math.max(0, headerIndex)][0],
        bottom: dataRows[dataRows.length - 1][1],
        columns: {
          number: numberLeft === undefined ? null : [numberLeft, nameLeft],
          name: [nameLeft, courseLeft],
          course: [courseLeft, gradeLeft],
          grade: [gradeLeft, gradeRight],
          remarks: [gradeRight, Math.round(remarksRight)],
        },
        rows: dataRows,
      });
    });
    return tables;
  };

  const wordsInBox = (words, [x0, x1], [y0, y1]) => words
    .filter((word) => { const c = wordCenter(word); return c.x > x0 && c.x < x1 && c.y > y0 && c.y < y1; })
    .sort((a, b) => a.bbox.x0 - b.bbox.x0);

  const cropImageData = ({ width, data }, x0, y0, x1, y1) => {
    const w = Math.max(1, x1 - x0); const h = Math.max(1, y1 - y0);
    const out = new Uint8ClampedArray(w * h * 4);
    for (let y = 0; y < h; y += 1) {
      const from = ((y0 + y) * width + x0) * 4;
      out.set(data.subarray(from, from + w * 4), y * w * 4);
    }
    return { width: w, height: h, data: out };
  };

  /** A typed grade as OCR'd by Tesseract, if it is a legal value (mirrors the PHP normalizer). */
  const typedGrade = (text) => {
    const t = String(text || '').toUpperCase().replace(/[\s|]/g, '').replace(/^[^0-9A-Z]+|[^0-9A-Z]+$/g, '');
    if (['INC', 'DRP', 'NG', 'W'].includes(t)) return t;
    const match = t.match(/^([1-5])[.,:·]?(\d{1,2})$/);
    if (!match) return null;
    const value = Number(`${match[1]}.${match[2]}`);
    const legal = ['1.0', '1.25', '1.5', '1.75', '2.0', '2.25', '2.5', '2.75', '3.0', '4.0', '5.0'];
    return legal.find((grade) => Math.abs(Number(grade) - value) < 0.001) || null;
  };

  const cleanField = (text) => String(text || '').replace(/[|@]/g, ' ').replace(/[_~]+/g, ' ').replace(/\s+/g, ' ').trim();

  // A pen checkmark (or any other stray mark) placed on or beside a printed/handwritten name
  // is ink Tesseract still tries to read, but it comes out with no letters and low confidence —
  // unlike a real initial or name fragment, which Tesseract reads with reasonable confidence even
  // when short. Confidence 0-100, as Tesseract.js reports it.
  const NAME_NOISE_CONFIDENCE = 60;
  const isNameNoiseWord = (word) => {
    const letters = (String(word.text || '').match(/[A-Za-zÑñ]/g) || []).length;
    return letters === 0 && Number(word.confidence) < NAME_NOISE_CONFIDENCE;
  };
  // A word that DOES have letters but Tesseract itself is quite unsure of — poor handwriting, a
  // smudge, a blurry scan — is kept (dropping it would lose part of the name outright) but still
  // marks the whole name uncertain, since a low-confidence letter is exactly how a surname ends up
  // spelled wrong. Lower than NAME_NOISE_CONFIDENCE on purpose: a short real initial often scores in
  // the 60s-70s and must not be flagged just for being short.
  const NAME_LOW_CONFIDENCE = 50;
  const isNameUnsureWord = (word) => Number(word.confidence) < NAME_LOW_CONFIDENCE;
  /**
   * Builds the Name cell's text with ink-mark noise words dropped, so a checkmark does not glue
   * itself onto the name. A "?" is appended whenever a word was dropped, or a kept word's reading
   * is unsure — mirroring the grade cell's uncertain-read convention — so the record is still
   * flagged for a human check rather than silently trusting a cleanup or a shaky read.
   */
  /** Mean Tesseract confidence (0-100) of the words kept in a Name cell; 0 when nothing was read. */
  const nameConfidenceIn = (words, column, band) => {
    if (!column) return 0;
    const kept = wordsInBox(words, column, band).filter((word) => !isNameNoiseWord(word));
    return kept.length ? kept.reduce((sum, word) => sum + Number(word.confidence || 0), 0) / kept.length : 0;
  };
  const nameTextIn = (words, column, band) => {
    if (!column) return '';
    const cellWords = wordsInBox(words, column, band);
    const clean = cellWords.filter((word) => !isNameNoiseWord(word));
    const name = cleanField(clean.map((word) => word.text).join(' '));
    const uncertain = clean.length < cellWords.length || clean.some(isNameUnsureWord);
    return uncertain ? `${name}?` : name;
  };

  /**
   * Rebuilds a page's student rows from its table cells as "@ROW|no|name|course|grade|remarks"
   * lines for the server parser. The grade is taken from Tesseract only when it is typed and read
   * confidently; otherwise the handwriting reader supplies it, with a trailing "?" when it is not
   * sure enough to be saved without a check.
   */
  const rowLine = (field) => `@ROW|${field.number}|${field.name}|${field.course}|${field.grade}|${field.remarks}`;
  const pageText = (header, fields) => `${header.join('\n')}\n${fields.map(rowLine).join('\n')}\nNote: end of table\n`;

  const readTableRows = (imageData, words, model) => {
    const tables = locateGradeTables(imageData, words);
    const dark = tables.length ? darkPixels(imageData) : null;
    // Share of a pixel row inside the name column that is inked; ~1 across a shaded
    // "***NOTHING FOLLOWS***" bar, whose white lettering OCR cannot read.
    const shade = (table, y) => {
      const [x0, x1] = table.columns.name;
      let count = 0;
      for (let x = x0 + 4; x < x1 - 4; x += 1) count += dark[y * imageData.width + x];
      return count / Math.max(1, x1 - x0 - 8);
    };
    const rows = [];
    const cells = [];
    const fields = [];
    tables.forEach((table) => {
      const tableStart = rows.length;
      for (const [rowTop, rowBottom] of table.rows) {
        // Trim shaded rows off the cell edges (a bar with no rule above it merges into the row
        // before it); a row that is mostly shaded is the end-of-list bar itself.
        let top = rowTop; let bottom = rowBottom;
        while (bottom - top > 4 && shade(table, bottom - 1) > 0.5) bottom -= 1;
        while (bottom - top > 4 && shade(table, top) > 0.5) top += 1;
        if (bottom - top < (rowBottom - rowTop) * 0.45) break;
        const inset = Math.max(3, Math.round((bottom - top) * 0.1));
        const band = [top + inset / 2, bottom - inset / 2];
        const textIn = (column) => (column ? cleanField(wordsInBox(words, column, band).map((w) => w.text).join(' ')) : '');
        const name = nameTextIn(words, table.columns.name, band);
        if (/nothing|follows?/i.test(name)) break;
        // A handwritten "Nothing follows" OCRs loosely ("Mothing fotioos", "Mothipy Bollows").
        if (/^[\W\d]*[mn][l1i]?othi|[bfp]o[l1it]{1,2}[o0]w?s\W*$/i.test(name)) continue;
        if ((name.match(/[A-Za-z]/g) || []).length < 3) continue;
        const gradeWords = wordsInBox(words, table.columns.grade, band);
        const typed = typedGrade(gradeWords.map((w) => w.text).join(''));
        const typedConfident = typed && gradeWords.every((w) => Number(w.confidence) >= 75);
        let grade = '';
        if (typedConfident) {
          grade = typed;
        } else if (model) {
          const [gx0, gx1] = table.columns.grade;
          const cell = cropImageData(imageData, gx0 + inset, top + inset, gx1 - inset, bottom - inset);
          const read = readHandwrittenGrade(cell, model);
          if (!read.blank && read.grade) grade = read.confidence >= GRADE_CONFIDENCE_ACCEPT ? read.grade : `${read.grade}?`;
          else if (!read.blank) grade = '?';
        } else if (typed) {
          grade = `${typed}?`;
        }
        const number = textIn(table.columns.number).replace(/[^0-9]/g, '');
        const remarks = textIn(table.columns.remarks);
        // Specks OCR'd in an empty row: no grade, no remarks, and too little text to be a name.
        if (!grade && !/[A-Za-z]{3}/.test(remarks) && (name.match(/[A-Za-z]/g) || []).length < 6) continue;
        const field = {
          number, name, course: textIn(table.columns.course), grade, remarks,
          nameConfidence: nameConfidenceIn(words, table.columns.name, band),
          nameBox: [table.columns.name[0], Math.round(top + inset / 2), table.columns.name[1], Math.round(bottom - inset / 2)],
        };
        rows.push(rowLine(field));
        fields.push(field);
        cells.push({ grade: [table.columns.grade[0], top, table.columns.grade[1], bottom] });
      }
      // The last rows of a list with neither a grade nor remarks are the handwritten "Nothing
      // follows" line (OCR'd as "Wothim Poles", "plothing Folloos"…) or specks below it.
      while (rows.length > tableStart) {
        const [, , , , grade, remarks] = rows[rows.length - 1].split('|');
        if (grade.replace(/\?/g, '') || /[A-Za-z]{3}/.test(remarks)) break;
        rows.pop();
        cells.pop();
        fields.pop();
      }
    });
    return { rows, cells, fields, top: tables.length ? Math.min(...tables.map((t) => t.top)) : null };
  };

  /**
   * The page text sent to the server: the header lines OCR'd above the table (course, semester…)
   * followed by the rebuilt rows. Falls back to the plain OCR text when no ruled table is found.
   */
  const composePageText = (ocrData, imageData, model) => {
    const words = Array.isArray(ocrData.words) ? ocrData.words : [];
    const { rows, cells, fields, top } = readTableRows(imageData, words, model);
    if (!rows.length) return { text: ocrData.text || '', structured: false, rows: 0, cells: [], fields: [], header: [] };
    const header = (ocrData.lines || []).filter((line) => line.bbox && line.bbox.y1 < top).map((line) => String(line.text || '').trim());
    return { text: pageText(header, fields), structured: true, rows: rows.length, cells, fields, header };
  };

  // ---------------------------------------------------------------------------
  // Second, independent read of every Name cell.
  //
  // Names are handwritten, and the page-wide OCR pass reads them as part of a whole table. Each Name
  // cell is therefore cut out, stripped of table-rule remnants, enlarged and read again on its own
  // as a single line restricted to name characters. Two independent reads that agree are strong
  // evidence; reads that disagree flag the record instead of quietly trusting either one.
  // ---------------------------------------------------------------------------

  const letterKey = (text) => String(text || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase().replace(/[^A-Z]/g, '');

  const editDistance = (a, b) => {
    if (a === b) return 0;
    if (!a.length) return b.length;
    if (!b.length) return a.length;
    let previous = Array.from({ length: b.length + 1 }, (_, i) => i);
    for (let i = 1; i <= a.length; i += 1) {
      const current = [i];
      for (let j = 1; j <= b.length; j += 1) {
        current[j] = Math.min(previous[j] + 1, current[j - 1] + 1, previous[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
      }
      previous = current;
    }
    return previous[b.length];
  };

  /** Characters a name can contain; anything else the second read might see is noise. */
  const NAME_WHITELIST = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyzñÑ .,'-";

  /**
   * The Name cell with table-rule remnants and paper speckle erased and ink left at its original
   * grey level (the caller enlarges it smoothly; a hard black-and-white cut would make jagged strokes).
   */
  const prepareNameCellImage = (image) => {
    const { ink, width, height } = cellInkMask(image);
    const data = new Uint8ClampedArray(width * height * 4);
    for (let i = 0; i < width * height; i += 1) {
      const luminance = ink[i] ? Math.round(0.299 * image.data[i * 4] + 0.587 * image.data[i * 4 + 1] + 0.114 * image.data[i * 4 + 2]) : 255;
      data[i * 4] = luminance;
      data[i * 4 + 1] = luminance;
      data[i * 4 + 2] = luminance;
      data[i * 4 + 3] = 255;
    }
    return { width, height, data };
  };

  const tidyNameRead = (text) => cleanField(String(text || '').replace(/[^A-Za-zÑñ .,'\-]/g, ' '));

  /**
   * Combines the page-level read (original, possibly ending in "?") with the cell-level re-read.
   * Agreement (same letters, or about one letter in ten apart) keeps the better-formatted text and
   * drops a doubt marker the agreement resolves; disagreement keeps the more confident read but
   * marks it uncertain so the record is checked by a person.
   */
  const mergeNameReads = (original, refined) => {
    const originalText = String(original.text || '');
    const unsure = originalText.endsWith('?');
    const base = originalText.replace(/\?+$/, '').trim();
    const second = tidyNameRead(refined && refined.text);
    if ((second.match(/[A-Za-zÑñ]/g) || []).length < 3) return originalText;
    if ((base.match(/[A-Za-zÑñ]/g) || []).length < 3) return `${second}?`;

    const a = letterKey(base);
    const b = letterKey(second);
    const distance = editDistance(a, b);
    if (distance <= Math.max(1, Math.floor(Math.max(a.length, b.length) * 0.1))) {
      // The same name read twice. Prefer the version already shaped like "SURNAME, Given".
      const pick = base.includes(',') || !second.includes(',') ? base : second;
      const confirmed = Number(refined.confidence) >= 70 && Number(original.confidence) >= 50;
      return unsure && !confirmed ? `${pick}?` : pick;
    }
    const pick = Number(refined.confidence) > Number(original.confidence) ? second : base;
    return `${pick}?`;
  };

  /** Rebuilds the page text with re-read names; reads maps a row index to { text, confidence }. */
  const applyNameReads = (composed, reads) => {
    if (!composed.structured || !reads) return composed.text;
    const fields = composed.fields.map((field, index) => (reads[index]
      ? { ...field, name: mergeNameReads({ text: field.name, confidence: field.nameConfidence }, reads[index]) }
      : field));
    return pageText(composed.header, fields);
  };

  return {
    GRADESHEET_SEMESTERS,
    validYear,
    validateEntry,
    findStudentPageNumbers,
    findStudentRecordPageNumbers,
    formatSchoolYear,
    initials,
    termAverage,
    groupEntriesByTerm,
    otsuThreshold,
    cleanTableImageData,
    orientationScore,
    ORIENTATION_ACCEPT_SCORE,
    orientationCandidates,
    overallProgress,
    loadDigitModel,
    classifyDigit,
    readHandwrittenGrade,
    GRADE_CONFIDENCE_ACCEPT,
    locateGradeTables,
    readTableRows,
    composePageText,
    applyNameReads,
    mergeNameReads,
    prepareNameCellImage,
    NAME_WHITELIST,
    typedGrade,
    // Exposed for the Node test harness only.
    internals: { segmentGradeCell, gradeHypotheses, glyphToMnist, splitWideGlyph, cropImageData, GRADE_SPELLINGS, isNameNoiseWord, isNameUnsureWord, nameTextIn, nameConfidenceIn, editDistance, letterKey, pageText },
  };
}));
