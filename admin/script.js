  const getStudentName = (student) => {
    const lastName = String(student.last_name || '').trim();
    const givenNames = [student.first_name, student.middle_name || student.middle_initial]
      .map((name) => String(name || '').trim())
      .filter(Boolean)
      .join(' ');
    return lastName ? `${lastName}, ${givenNames}` : givenNames || 'Unnamed student';
  };
document.addEventListener('DOMContentLoaded', () => {
  const greeting = document.querySelector('[data-greeting]');
  if (greeting) {
    const updateGreeting = () => {
      const hour = new Date().getHours();
      const userName = greeting.dataset.userName || 'User';
      const label = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
      greeting.textContent = `${label}, ${userName}`;
    };
    updateGreeting();
    window.setInterval(updateGreeting, 60000);
  }
  document.querySelectorAll('[data-lucide="list-edit"]').forEach((icon) => icon.setAttribute('data-lucide', 'pencil-line'));
  if (window.lucide) lucide.createIcons();

  const mobileMenu = document.querySelector('#mobileMenu');
  const sidebar = document.querySelector('#sidebar');
  const exportButton = document.querySelector('#exportButton');
  const adminCategoryModal = document.querySelector('#adminCategoryModal');
  const saveAdminCategory = document.querySelector('#saveAdminCategory');
  const toast = document.querySelector('#adminToast');
  const showAdminToast = (message) => {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    window.setTimeout(() => toast.classList.remove('show'), 3200);
  };
  document.querySelectorAll('[data-action="add-category"]').forEach((trigger) => trigger.addEventListener('click', (event) => {
    event.preventDefault();
    adminCategoryModal.hidden = false;
    adminCategoryModal.querySelector('#adminCategoryCode').focus();
  }));
  document.querySelector('#closeAdminCategoryModal')?.addEventListener('click', () => { adminCategoryModal.hidden = true; });
  adminCategoryModal?.addEventListener('click', (event) => { if (event.target === adminCategoryModal) adminCategoryModal.hidden = true; });
  saveAdminCategory?.addEventListener('click', async () => {
    const code = adminCategoryModal.querySelector('#adminCategoryCode').value.trim().toUpperCase();
    const name = adminCategoryModal.querySelector('#adminCategoryName').value.trim();
    const description = adminCategoryModal.querySelector('#adminCategoryDescription').value.trim();
    const message = adminCategoryModal.querySelector('#adminCategoryMessage');
    if (!code || !name || !description) { message.textContent = 'Code, name, and description are required.'; return; }
    saveAdminCategory.disabled = true;
    const response = await fetch('../api.php?resource=categories', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ code, name, description }) });
    const result = await response.json().catch(() => ({}));
    saveAdminCategory.disabled = false;
    if (!response.ok) { message.textContent = result.error || 'Unable to create category.'; return; }
    adminCategoryModal.hidden = true;
    adminCategoryModal.querySelectorAll('input, textarea').forEach((field) => { field.value = ''; });
    message.textContent = '';
    showAdminToast('Document category added.');
  });
  const fileInput = document.querySelector('#fileInput');
  const uploadZone = document.querySelector('#uploadZone');
  const uploadResult = document.querySelector('#uploadResult');
  const documentPreview = document.querySelector('#documentPreview');
  const previewCover = document.querySelector('#previewCover');
  const previewThumbs = document.querySelector('#previewThumbs');
  const archiveButton = document.querySelector('#archiveButton');
  const scanCategory = document.querySelector('#scanCategory');
  const scanAcademicYear = document.querySelector('#scanAcademicYear');
  const scanOcrBanner = document.querySelector('#scanOcrBanner');
  const scanCategorySource = document.querySelector('#scanCategorySource');
  const scanStudentSearch = document.querySelector('#scanStudentSearch');
  const scanStudentId = document.querySelector('#scanStudentId');
  const scanStudentSuggestions = document.querySelector('#scanStudentSuggestions');
  const scanExistingStudentField = document.querySelector('#scanExistingStudentField');
  const scanNewStudentFields = document.querySelector('#scanNewStudentFields');
  const scanNewCollege = document.querySelector('#scanNewCollege');
  const scanNewCourse = document.querySelector('#scanNewCourse');
  const scanNewMajor = document.querySelector('#scanNewMajor');
  const scanNewCollegeOptions = document.querySelector('#scanNewCollegeOptions');
  const scanNewCourseOptions = document.querySelector('#scanNewCourseOptions');
  const scanNewMajorOptions = document.querySelector('#scanNewMajorOptions');
  const addUserButton = document.querySelector('#addUserButton');
  const userModal = document.querySelector('#userModal');
  const closeUserModal = document.querySelector('#closeUserModal');
  const saveUserButton = document.querySelector('#saveUserButton');
  const userSearch = document.querySelector('#userSearch');
  const statusFilter = document.querySelector('#statusFilter');
  const userRows = document.querySelector('#userRows');
  const emptyUsers = document.querySelector('#emptyUsers');
  const addCollegeButton = document.querySelector('#addCollegeButton');
  const addCourseButton = document.querySelector('#addCourseButton');
  const structureModal = document.querySelector('#structureModal');
  const closeStructureModal = document.querySelector('#closeStructureModal');
  const saveStructureButton = document.querySelector('#saveStructureButton');
  const structureName = document.querySelector('#structureName');
  const structureModalTitle = document.querySelector('#structureModalTitle');
  const structureModalCopy = document.querySelector('#structureModalCopy');
  const structureFieldLabel = document.querySelector('#structureFieldLabel');
  const collegeLogoField = document.querySelector('#collegeLogoField');
  const collegeLogo = document.querySelector('#collegeLogo');
  const structureSearch = document.querySelector('#structureSearch');
  const collegeList = document.querySelector('#collegeList');
  const emptyStructure = document.querySelector('#emptyStructure');
  const studentSearch = document.querySelector('#studentSearch');
  const studentSearchSuggestions = document.querySelector('#studentSearchSuggestions');
  const studentGender = document.querySelector('#studentGender');
  const studentCollege = document.querySelector('#studentCollege');
  const studentRows = document.querySelector('#studentRows');
  const emptyStudents = document.querySelector('#emptyStudents');
  const addStudentButton = document.querySelector('#addStudentButton');
  const studentModal = document.querySelector('#studentModal');
  const closeStudentModal = document.querySelector('#closeStudentModal');
  const saveStudentButton = document.querySelector('#saveStudentButton');
  const studentModalTitle = studentModal?.querySelector('#studentModalTitle');
  const studentModalCopy = studentModal?.querySelector('.student-modal p');
  const collegeData = new Map();
  let activeStudentRow;
  let editingStudentId = null;
  let selectedStudentId = null;
  let activeStudentSuggestion = -1;

  if (studentRows) {
    const academicData = {
      '2026-001234': { college: 'College of Computing', course: 'BS Information Technology', year: '2026', level: '3rd Year' },
      '2026-001198': { college: 'College of Education', course: 'Bachelor of Elementary Education', year: '2026', level: '4th Year' },
      '2026-000987': { college: 'College of Business & Accountancy', course: 'BS Accountancy', year: '2026', level: '2nd Year' }
    };
    const headerRow = studentRows.closest('table').querySelector('thead tr');
    if (![...headerRow.children].some((cell) => cell.textContent.trim() === 'College')) {
      ['College', 'Course', 'Academic Year', 'Year Level'].forEach((heading) => {
        const cell = document.createElement('th');
        cell.dataset.academicColumn = 'true';
        cell.textContent = heading;
        headerRow.insertBefore(cell, headerRow.children[1]);
      });
    }
    studentRows.querySelectorAll('tr').forEach((row) => {
      const studentNumber = row.querySelector('.student-identity strong')?.textContent.split(' · ')[0];
      const data = academicData[studentNumber] || { college: row.dataset.college || 'Not assigned', course: 'Not assigned', year: '2026' };
      row.dataset.course = data.course;
      row.dataset.year = data.year;
      [data.college, data.course, data.year, data.level || '1st Year'].forEach((value) => {
        const cell = document.createElement('td');
        cell.textContent = value;
        row.insertBefore(cell, row.lastElementChild);
      });
    });
    const fields = document.createElement('div');
    fields.className = 'student-form-grid academic-fields';
    fields.innerHTML = '<label>College<input id="studentCollege" list="studentCollegeOptions" autocomplete="off" placeholder="Type or select a college" /><datalist id="studentCollegeOptions"></datalist></label><label>Course<input id="studentCourse" type="text" list="studentCourseOptions" autocomplete="off" placeholder="Type or select a course" /><datalist id="studentCourseOptions"></datalist></label><label>Major<input id="studentMajor" type="text" list="studentMajorOptions" autocomplete="off" placeholder="Type or select a major" /><datalist id="studentMajorOptions"></datalist></label><label>Academic year<input id="studentAcademicYear" type="text" inputmode="numeric" maxlength="4" placeholder="e.g. 2026" /></label><label>Year level<select id="studentYearLevel"></select></label>';
    const collegeInput = fields.querySelector('#studentCollege');
    const courseInput = fields.querySelector('#studentCourse');
    const majorInput = fields.querySelector('#studentMajor');
    const collegeOptions = fields.querySelector('#studentCollegeOptions');
    const courseOptions = fields.querySelector('#studentCourseOptions');
    const majorOptions = fields.querySelector('#studentMajorOptions');
    const yearLevels = JSON.parse(document.querySelector('#studentYearLevelOptions')?.textContent || '[]');
    yearLevels.forEach((yearLevel) => fields.querySelector('#studentYearLevel').add(new Option(yearLevel, yearLevel)));
    const academicColleges = () => [...collegeData.values()];
    const academicNameMatches = (name, value) => window.DatsScanFlow.normalizeSearchText(name) === window.DatsScanFlow.normalizeSearchText(value);
    const populateCourses = () => {
      const selection = window.DatsScanFlow.resolveAcademicSelection(academicColleges(), {
        collegeName: collegeInput.value, courseName: '', majorName: '',
      });
      const college = academicColleges().find((item) => String(item.id) === selection.collegeId);
      courseOptions.replaceChildren(...(college?.courses || []).map((course) => new Option(course.name, course.name)));
      if (college) populateMajors();
      else majorOptions.replaceChildren();
      if (courseInput.value && college && !(college.courses || []).some((course) => academicNameMatches(course.name, courseInput.value))) courseInput.value = '';
    };
    const populateMajors = () => {
      const selection = window.DatsScanFlow.resolveAcademicSelection(academicColleges(), {
        collegeName: collegeInput.value, courseName: courseInput.value, majorName: '',
      });
      const college = academicColleges().find((item) => String(item.id) === selection.collegeId);
      const course = college?.courses?.find((item) => String(item.id) === selection.courseId);
      majorOptions.replaceChildren(...(course?.majors || []).map((major) => new Option(major.name, major.name)));
      if (majorInput.value && course && !(course.majors || []).some((major) => academicNameMatches(major.name, majorInput.value))) majorInput.value = '';
    };
    collegeInput.addEventListener('input', populateCourses);
    courseInput.addEventListener('input', populateMajors);
    fetch('../api.php?resource=colleges', { credentials: 'same-origin' }).then((response) => response.ok ? response.json() : null).then((result) => {
      (result?.data || []).forEach((college) => {
        collegeData.set(college.name, college);
        collegeOptions.add(new Option(college.name, college.name));
      });
      populateCourses();
    });
    collegeInput._populateStudentCourses = populateCourses;
    saveStudentButton.before(fields);
  }
  let structureMode = 'college';
  let courseTarget;
  let editCard;
  let selectedUploadFile = null;
  let ocrCache = new Map();
  let scanReferenceData = { students: [], categories: [], colleges: [] };
  let activeScanSuggestion = -1;

  const getScanStudentMode = () => document.querySelector('input[name="scanStudentMode"]:checked')?.value || 'existing';
  const scanNewField = (id) => document.querySelector(`#${id}`)?.value || '';
  const collectNewStudentValues = () => ({
    studentNumber: scanNewField('scanNewStudentNumber'), firstName: scanNewField('scanNewFirstName'),
    middleInitial: scanNewField('scanNewMiddleInitial'), lastName: scanNewField('scanNewLastName'),
    nationality: scanNewField('scanNewNationality'), gender: scanNewField('scanNewGender'),
    birthdate: scanNewField('scanNewBirthdate'), academicYear: scanNewField('scanNewAcademicYear'),
    address: scanNewField('scanNewAddress'), civilStatus: scanNewField('scanNewCivilStatus'),
    contactNumber: scanNewField('scanNewContactNumber'), collegeName: scanNewField('scanNewCollege'),
    courseName: scanNewField('scanNewCourse'), majorName: scanNewField('scanNewMajor'),
    collegeId: '', courseId: '', majorId: '',
    yearLevel: scanNewField('scanNewYearLevel'),
  });

  const closeScanSuggestions = () => {
    if (!scanStudentSuggestions) return;
    scanStudentSuggestions.hidden = true;
    scanStudentSearch?.setAttribute('aria-expanded', 'false');
    scanStudentSearch?.removeAttribute('aria-activedescendant');
    activeScanSuggestion = -1;
  };

  const selectScanStudent = (student) => {
    const selection = window.DatsScanFlow.getExistingStudentSelection(student);
    scanStudentId.value = String(selection.studentId);
    scanStudentSearch.value = `${student.student_number} · ${student.first_name}${student.middle_initial ? ` ${student.middle_initial}` : ''} ${student.last_name}`;
    scanAcademicYear.value = selection.academicYear;
    closeScanSuggestions();
  };

  const renderScanSuggestions = () => {
    if (!scanStudentSuggestions || !scanStudentSearch) return;
    const matches = window.DatsScanFlow.filterStudents(scanReferenceData.students, scanStudentSearch.value, 8);
    scanStudentSuggestions.replaceChildren(...matches.map((student, index) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.role = 'option';
      button.id = `scan-student-option-${student.id}`;
      button.className = `student-suggestion${index === activeScanSuggestion ? ' is-active' : ''}`;
      const title = document.createElement('strong');
      title.textContent = `${student.student_number} · ${student.first_name}${student.middle_initial ? ` ${student.middle_initial}` : ''} ${student.last_name}`;
      const details = document.createElement('small');
      details.textContent = `Academic year ${student.academic_year || 'not assigned'} · ${student.college_name || 'College not assigned'}${student.course_name ? ` / ${student.course_name}` : ''}`;
      button.append(title, details);
      button.addEventListener('mousedown', (event) => { event.preventDefault(); selectScanStudent(student); });
      return button;
    }));
    scanStudentSuggestions.hidden = false;
    scanStudentSearch.setAttribute('aria-expanded', 'true');
  };

  scanStudentSearch?.addEventListener('input', () => {
    scanStudentId.value = '';
    scanAcademicYear.value = '';
    activeScanSuggestion = -1;
    renderScanSuggestions();
  });
  scanStudentSearch?.addEventListener('focus', renderScanSuggestions);
  scanStudentSearch?.addEventListener('keydown', (event) => {
    const options = [...(scanStudentSuggestions?.querySelectorAll('.student-suggestion') || [])];
    if (event.key === 'Escape') { closeScanSuggestions(); return; }
    if (event.key === 'Tab') { closeScanSuggestions(); return; }
    if (!['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)) return;
    event.preventDefault();
    if (event.key === 'Enter' && activeScanSuggestion >= 0) { options[activeScanSuggestion]?.dispatchEvent(new MouseEvent('mousedown')); return; }
    if (!options.length) return;
    activeScanSuggestion = event.key === 'ArrowUp'
      ? (activeScanSuggestion <= 0 ? options.length - 1 : activeScanSuggestion - 1)
      : (activeScanSuggestion + 1) % options.length;
    options.forEach((option, index) => option.classList.toggle('is-active', index === activeScanSuggestion));
    scanStudentSearch.setAttribute('aria-activedescendant', options[activeScanSuggestion].id);
  });
  document.addEventListener('click', (event) => {
    if (!event.target.closest('#scanExistingStudentField')) closeScanSuggestions();
  });

  const academicNameMatches = (name, value) => window.DatsScanFlow.normalizeSearchText(name) === window.DatsScanFlow.normalizeSearchText(value);
  const populateScanCourses = () => {
    const selection = window.DatsScanFlow.resolveAcademicSelection(scanReferenceData.colleges, {
      collegeName: scanNewCollege?.value, courseName: '', majorName: '',
    });
    const college = scanReferenceData.colleges.find((item) => String(item.id) === selection.collegeId);
    if (scanNewCourse) {
      scanNewCourse.disabled = false;
      scanNewCourse.readOnly = false;
      scanNewCourse.placeholder = 'Type or select a course';
    }
    if (!college) {
      scanNewCourseOptions?.replaceChildren();
      if (scanNewMajor) {
        scanNewMajor.disabled = false;
        scanNewMajor.readOnly = false;
        scanNewMajor.placeholder = 'Type or select a major';
      }
      scanNewMajorOptions?.replaceChildren();
      return;
    }
    scanNewCourseOptions?.replaceChildren(...(college.courses || []).map((course) => new Option(course.name, course.name)));
    if (scanNewCourse?.value && !(college.courses || []).some((course) => academicNameMatches(course.name, scanNewCourse.value))) {
      scanNewCourse.value = '';
    }
    populateScanMajors();
  };
  const populateScanMajors = () => {
    const selection = window.DatsScanFlow.resolveAcademicSelection(scanReferenceData.colleges, {
      collegeName: scanNewCollege?.value, courseName: scanNewCourse?.value, majorName: '',
    });
    const college = scanReferenceData.colleges.find((item) => String(item.id) === selection.collegeId);
    const course = college?.courses?.find((item) => String(item.id) === selection.courseId);
    if (scanNewMajor) {
      scanNewMajor.disabled = false;
      scanNewMajor.readOnly = false;
      scanNewMajor.placeholder = 'Type or select a major';
    }
    if (!course) {
      scanNewMajorOptions?.replaceChildren();
      return;
    }
    scanNewMajorOptions?.replaceChildren(...(course.majors || []).map((major) => new Option(major.name, major.name)));
    if (scanNewMajor?.value && !(course.majors || []).some((major) => academicNameMatches(major.name, scanNewMajor.value))) {
      scanNewMajor.value = '';
    }
  };
  scanNewCollege?.addEventListener('input', populateScanCourses);
  scanNewCourse?.addEventListener('input', populateScanMajors);

  window.addEventListener('dats:scan-data-ready', (event) => {
    scanReferenceData = event.detail;
    scanNewCollegeOptions?.replaceChildren(...scanReferenceData.colleges.map((college) => new Option(college.name, college.name)));
    populateScanCourses();
  });

  document.querySelectorAll('input[name="scanStudentMode"]').forEach((radio) => radio.addEventListener('change', () => {
    const newMode = getScanStudentMode() === 'new';
    if (scanExistingStudentField) scanExistingStudentField.hidden = newMode;
    if (scanNewStudentFields) scanNewStudentFields.hidden = !newMode;
    if (scanAcademicYear) {
      scanAcademicYear.closest('label').hidden = newMode;
      scanAcademicYear.value = '';
    }
    if (newMode) {
      if (scanStudentId) scanStudentId.value = '';
      if (scanStudentSearch) scanStudentSearch.value = '';
    } else {
      scanNewStudentFields?.querySelectorAll('input').forEach((field) => { field.value = ''; });
      scanNewStudentFields?.querySelectorAll('select').forEach((field) => { field.selectedIndex = 0; });
      suggestedStudentFields.clear();
      populateScanCourses();
    }
    closeScanSuggestions();
  }));

  const normalizeOcrText = (text = '') => String(text || '').replace(/\s+/g, ' ').trim();

  const summarizeDetectedText = (text = '') => {
    const normalized = normalizeOcrText(text);
    if (!normalized) return 'No readable text could be extracted from the uploaded document.';
    const firstSentence = normalized.split(/(?<=[.!?])\s+/)[0] || normalized.slice(0, 180);
    return firstSentence.length > 220 ? `${firstSentence.slice(0, 220)}...` : firstSentence;
  };

  const ensureOcrLibraries = async () => {
    if (!window.Tesseract) {
      await new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
        script.onload = resolve;
        script.onerror = () => reject(new Error('OCR library failed to load.'));
        document.head.appendChild(script);
      });
    }
  };

  const fileOcrKey = (file) => file ? `${file.name || 'file'}:${file.size}:${file.lastModified || 0}` : null;
  const pdfPreviewCache = new Map();

  const ensurePdfJs = async () => {
    if (!('pdfjsLib' in window)) {
      try {
        const pdfjsModule = await import('../lib/pdfjs/pdf.min.mjs');
        window.pdfjsLib = pdfjsModule.default || pdfjsModule;
        window.pdfjsLib.GlobalWorkerOptions = window.pdfjsLib.GlobalWorkerOptions || {};
        window.pdfjsLib.GlobalWorkerOptions.workerSrc = '../lib/pdfjs/pdf.worker.mjs';
      } catch (err) {
        const pdfjsModule = await import('https://cdn.jsdelivr.net/npm/pdfjs-dist@4.10.38/build/pdf.min.mjs');
        window.pdfjsLib = pdfjsModule.default || pdfjsModule;
        window.pdfjsLib.GlobalWorkerOptions = window.pdfjsLib.GlobalWorkerOptions || {};
        window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.10.38/build/pdf.worker.min.mjs';
      }
    }
    return window.pdfjsLib;
  };

  const detectPdfReadableRotation = async (pdfDocument) => {
    if (!pdfDocument || pdfDocument.numPages < 1) return 0;
    try {
      const page = await pdfDocument.getPage(1);
      const defaultRot = window.DatsScanFlow?.normalizeRotation
        ? window.DatsScanFlow.normalizeRotation(page.rotate || 0)
        : ((Math.round((page.rotate || 0) / 90) * 90 % 360) + 360) % 360;

      // 1. Check embedded text transforms if available
      try {
        const textContent = await page.getTextContent();
        if (textContent && Array.isArray(textContent.items) && textContent.items.length > 5) {
          const angles = textContent.items
            .filter((it) => (it.str || '').trim().length > 0 && Array.isArray(it.transform))
            .map((it) => {
              const a = it.transform[0];
              const b = it.transform[1];
              const deg = Math.round(Math.atan2(b, a) * (180 / Math.PI));
              return window.DatsScanFlow ? window.DatsScanFlow.normalizeRotation(deg) : ((Math.round(deg / 90) * 90 % 360) + 360) % 360;
            });
          if (angles.length > 3) {
            const counts = {};
            angles.forEach((deg) => { counts[deg] = (counts[deg] || 0) + 1; });
            const sorted = Object.entries(counts).sort((a, b) => b[1] - a[1]);
            if (sorted[0] && sorted[0][1] >= angles.length * 0.4) {
              const dominantTextAngle = Number(sorted[0][0]);
              const neededRot = window.DatsScanFlow
                ? window.DatsScanFlow.normalizeRotation((360 - dominantTextAngle) % 360)
                : ((360 - dominantTextAngle) % 360 + 360) % 360;
              return neededRot;
            }
          }
        }
      } catch (textErr) {}

      // 2. Test candidate rotations using fast thumbnail rendering & OCR
      const candidates = window.DatsScanFlow?.getOrientationCandidates
        ? window.DatsScanFlow.getOrientationCandidates(defaultRot)
        : [defaultRot, (defaultRot + 270) % 360, (defaultRot + 180) % 360, (defaultRot + 90) % 360];

      const base = page.getViewport({ scale: 1 });
      const scale = Math.min(1.5, Math.max(0.7, 1100 / Math.max(base.width, base.height)));

      const renderThumbnailCanvas = async (rot) => {
        const viewport = page.getViewport({ scale, rotation: rot });
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(viewport.width);
        canvas.height = Math.round(viewport.height);
        const ctx = canvas.getContext('2d');
        await page.render({ canvasContext: ctx, viewport }).promise;
        return { canvas, ctx };
      };

      let tesseractAvailable = Boolean(window.Tesseract);
      if (!tesseractAvailable && typeof ensureOcrLibraries === 'function') {
        try {
          await ensureOcrLibraries();
          tesseractAvailable = Boolean(window.Tesseract);
        } catch (e) {
          tesseractAvailable = false;
        }
      }

      if (tesseractAvailable && window.Tesseract) {
        const scoredCandidates = [];
        for (let i = 0; i < candidates.length; i += 1) {
          const rot = candidates[i];
          const { canvas } = await renderThumbnailCanvas(rot);
          try {
            const ocr = await window.Tesseract.recognize(canvas, 'eng');
            const scored = window.DatsScanFlow?.scoreOcrTextForOrientation
              ? window.DatsScanFlow.scoreOcrTextForOrientation(ocr?.data?.text || '')
              : { score: (ocr?.data?.text || '').length, keywordMatches: 0 };
            scoredCandidates.push({ rotation: rot, ...scored });
            if (window.DatsScanFlow?.isDecisiveOrientationScore && window.DatsScanFlow.isDecisiveOrientationScore(scored)) {
              return rot;
            }
          } catch (ocrErr) {}
        }
        return window.DatsScanFlow?.chooseBestOrientationCandidate
          ? window.DatsScanFlow.chooseBestOrientationCandidate(scoredCandidates, defaultRot)
          : (scoredCandidates.sort((a, b) => b.score - a.score)[0]?.rotation ?? defaultRot);
      }

      // 3. Fallback: projection variance analysis (no network / no Tesseract required)
      const { canvas, ctx } = await renderThumbnailCanvas(defaultRot);
      const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
      const proj = window.DatsScanFlow?.calculateProjectionVariance
        ? window.DatsScanFlow.calculateProjectionVariance(imgData)
        : { isSideways: false };
      if (proj.isSideways) {
        return (defaultRot + 270) % 360;
      }

      return defaultRot;
    } catch (err) {
      console.warn('detectPdfReadableRotation error:', err);
      return 0;
    }
  };

  const detectImageReadableRotation = async (file) => {
    if (!file || !file.type.startsWith('image/')) return 0;
    try {
      const candidates = window.DatsScanFlow?.getOrientationCandidates
        ? window.DatsScanFlow.getOrientationCandidates(0)
        : [0, 270, 180, 90];

      const img = new Image();
      const objectUrl = URL.createObjectURL(file);
      await new Promise((resolve, reject) => {
        img.onload = resolve;
        img.onerror = reject;
        img.src = objectUrl;
      });

      const renderImageThumb = (rot) => {
        const isSwapped = rot % 180 !== 0;
        const maxDim = 1100;
        const scale = Math.min(1, maxDim / Math.max(img.naturalWidth, img.naturalHeight));
        const w = Math.round((isSwapped ? img.naturalHeight : img.naturalWidth) * scale);
        const h = Math.round((isSwapped ? img.naturalWidth : img.naturalHeight) * scale);
        const canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext('2d');
        ctx.translate(w / 2, h / 2);
        ctx.rotate((rot * Math.PI) / 180);
        ctx.drawImage(img, -((img.naturalWidth * scale) / 2), -((img.naturalHeight * scale) / 2), img.naturalWidth * scale, img.naturalHeight * scale);
        return { canvas, ctx };
      };

      let tesseractAvailable = Boolean(window.Tesseract);
      if (!tesseractAvailable && typeof ensureOcrLibraries === 'function') {
        try {
          await ensureOcrLibraries();
          tesseractAvailable = Boolean(window.Tesseract);
        } catch (e) {
          tesseractAvailable = false;
        }
      }

      if (tesseractAvailable && window.Tesseract) {
        const scoredCandidates = [];
        for (let i = 0; i < candidates.length; i += 1) {
          const rot = candidates[i];
          const { canvas } = renderImageThumb(rot);
          try {
            const ocr = await window.Tesseract.recognize(canvas, 'eng');
            const scored = window.DatsScanFlow?.scoreOcrTextForOrientation
              ? window.DatsScanFlow.scoreOcrTextForOrientation(ocr?.data?.text || '')
              : { score: (ocr?.data?.text || '').length, keywordMatches: 0 };
            scoredCandidates.push({ rotation: rot, ...scored });
            if (window.DatsScanFlow?.isDecisiveOrientationScore && window.DatsScanFlow.isDecisiveOrientationScore(scored)) {
              URL.revokeObjectURL(objectUrl);
              return rot;
            }
          } catch (ocrErr) {}
        }
        URL.revokeObjectURL(objectUrl);
        return window.DatsScanFlow?.chooseBestOrientationCandidate
          ? window.DatsScanFlow.chooseBestOrientationCandidate(scoredCandidates, 0)
          : (scoredCandidates.sort((a, b) => b.score - a.score)[0]?.rotation ?? 0);
      }

      const { canvas, ctx } = renderImageThumb(0);
      const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
      const proj = window.DatsScanFlow?.calculateProjectionVariance
        ? window.DatsScanFlow.calculateProjectionVariance(imgData)
        : { isSideways: false };
      URL.revokeObjectURL(objectUrl);
      if (proj.isSideways) {
        return 270;
      }
      return 0;
    } catch (err) {
      console.warn('detectImageReadableRotation error:', err);
      return 0;
    }
  };

  const renderPdfToImage = async (file) => {
    const pdfjsLib = await ensurePdfJs();
    const arrayBuffer = await file.arrayBuffer();
    const pdfDocument = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
    const cached = pdfPreviewCache.get(fileOcrKey(file));
    const effectiveRotation = cached?.effectiveRotation ?? await detectPdfReadableRotation(pdfDocument);
    const pages = [];
    for (let pageNumber = 1; pageNumber <= pdfDocument.numPages; pageNumber += 1) {
      const page = await pdfDocument.getPage(pageNumber);
      const viewport = page.getViewport({ scale: 1.5, rotation: effectiveRotation });
      const canvas = document.createElement('canvas');
      const context = canvas.getContext('2d');
      canvas.width = viewport.width;
      canvas.height = viewport.height;
      await page.render({ canvasContext: context, viewport }).promise;
      pages.push(canvas.toDataURL('image/png'));
    }
    return pages;
  };

  const runServerOcrOnFile = async (file) => {
    if (!file) return null;
    const formData = new FormData();
    formData.append('file', file, file.name || 'document-upload');
    try {
      const response = await fetch('../api.php?resource=document-ocr', {
        method: 'POST',
        credentials: 'same-origin',
        body: formData,
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok || !result?.data) {
        return null;
      }
      const payload = result.data;
      const ocrText = typeof payload.ocr_text === 'string' ? payload.ocr_text : '';
      if (payload.ocr_status !== 'Completed' && payload.ocr_status !== 'Failed') {
        // 'Processing'/'Pending' means the server has no OCR engine installed and never
        // actually attempted extraction — that's "unavailable", not a real failure, so the
        // caller should fall back to client-side Tesseract.js instead of giving up here.
        return null;
      }
      return {
        ocrText,
        ocrStatus: payload.ocr_status === 'Completed' ? 'Completed' : 'Failed',
        ocrError: payload.ocr_error || (ocrText ? null : 'No readable text could be extracted from the uploaded document.'),
        aiSummary: payload.ai_summary || summarizeDetectedText(ocrText),
        aiDetectedCategory: payload.ai_detected_category || window.DatsScanFlow.inferDocumentCategory(ocrText, scanReferenceData.categories)?.name || '',
      };
    } catch (error) {
      console.warn('Server OCR unavailable, falling back to browser OCR.', error);
      return null;
    }
  };

  const runOcrOnFile = async (file) => {
    if (!file) return null;
    const safeFileName = typeof file.name === 'string' && file.name ? file.name : 'uploaded-document';
    const cacheKey = `${safeFileName}:${file.size}:${file.lastModified}`;
    if (ocrCache.has(cacheKey)) {
      return ocrCache.get(cacheKey);
    }

    try {
      await ensureOcrLibraries();
      const isPdf = file.type === 'application/pdf' || /\.pdf$/i.test(safeFileName || '');
      let textChunks = [];

      if (isPdf) {
        const pageImages = await renderPdfToImage(file);
        for (let index = 0; index < pageImages.length; index += 1) {
          const result = await window.Tesseract.recognize(pageImages[index], 'eng', {
            logger: (message) => {
              if (message.status === 'recognizing text' && typeof message.progress === 'number') {
                const percent = Math.round(message.progress * 100);
                toast.textContent = `OCR processing ${percent}% for page ${index + 1}.`;
                toast.classList.add('show');
                window.clearTimeout(window.__datsToastTimer);
                window.__datsToastTimer = window.setTimeout(() => toast.classList.remove('show'), 1500);
              }
            }
          });
          textChunks.push(result.data.text || '');
        }
      } else {
        let imageSource = file;
        const previewPage = previewPages.find((p) => p.key === 'file-0');
        if (previewPage && previewPage.src && previewPage.rotation) {
          imageSource = previewPage.src;
        }
        const result = await window.Tesseract.recognize(imageSource, 'eng', {
          logger: (message) => {
            if (message.status === 'recognizing text' && typeof message.progress === 'number') {
              const percent = Math.round(message.progress * 100);
              toast.textContent = `OCR processing ${percent}%.`;
              toast.classList.add('show');
              window.clearTimeout(window.__datsToastTimer);
              window.__datsToastTimer = window.setTimeout(() => toast.classList.remove('show'), 1500);
            }
          }
        });
        textChunks.push(result.data.text || '');
      }

      const ocrText = textChunks.filter(Boolean).join('\n\n');
      const inferredCategory = window.DatsScanFlow.inferDocumentCategory(ocrText, scanReferenceData.categories);
      const result = {
        ocrText,
        ocrStatus: ocrText.trim() ? 'Completed' : 'Failed',
        ocrError: ocrText.trim() ? null : 'No readable text found in the uploaded document.',
        aiSummary: summarizeDetectedText(ocrText),
        aiDetectedCategory: inferredCategory?.name || '',
      };
      ocrCache.set(cacheKey, result);
      return result;
    } catch (error) {
      const message = error && error.message ? error.message : 'OCR could not read the document.';
      const cleanedMessage = String(message).replace(/\s+/g, ' ').trim();
      console.error('OCR failed for uploaded file:', cleanedMessage, error);
      const result = {
        ocrText: '',
        ocrStatus: 'Failed',
        ocrError: cleanedMessage,
        aiSummary: 'The document was uploaded but no readable text could be extracted.',
        aiDetectedCategory: '',
      };
      ocrCache.set(cacheKey, result);
      return result;
    }
  };

  let categoryManuallySet = false;
  let categoryDetectedFromFile = false;
  const suggestedStudentFields = new Map();
  let lastOcrResult = null;
  let lastOcrFileKey = null;
  let ocrRequestToken = 0;
  let activeOcrFileKey = null;

  scanCategory?.addEventListener('change', () => {
    categoryManuallySet = true;
    categoryDetectedFromFile = false;
    scanCategorySource?.classList.remove('is-detected');
    if (scanCategorySource) scanCategorySource.textContent = 'Document type selected manually.';
  });

  // fileOcrKey declared earlier for preview cache and OCR alignment

  const clearOcrStudentSuggestions = () => {
    suggestedStudentFields.forEach((suggestedValue, fieldId) => {
      const field = document.getElementById(fieldId);
      if (field?.value === suggestedValue) field.value = '';
    });
    suggestedStudentFields.clear();
  };

  const prepareForFileReplacement = () => {
    clearOcrStudentSuggestions();
    if (scanCategory) {
      scanCategory.value = window.DatsScanFlow.categoryForReplacement(scanCategory.value, categoryDetectedFromFile);
    }
    if (categoryDetectedFromFile) {
      scanCategorySource?.classList.remove('is-detected');
      if (scanCategorySource) scanCategorySource.textContent = 'Select a type or wait for automatic detection.';
    }
    categoryDetectedFromFile = false;
    ocrRequestToken += 1;
    activeOcrFileKey = null;
  };

  const detectAcademicYearFromText = (text = '') => {
    const cleaned = normalizeOcrText(text);
    const rangeMatch = cleaned.match(/\b(20\d{2})\s*[-–—]\s*(20\d{2})\b/);
    if (rangeMatch) return rangeMatch[1];
    const labeledMatch = cleaned.match(/(?:A\.?\s*Y\.?|S\.?\s*Y\.?|academic year|school year)\D{0,6}(20\d{2})/i);
    if (labeledMatch) return labeledMatch[1];
    const bareMatch = cleaned.match(/\b(20\d{2})\b/);
    return bareMatch ? bareMatch[1] : '';
  };

  const setOcrBanner = (message, isError = false) => {
    if (!scanOcrBanner) return;
    if (!message) { scanOcrBanner.hidden = true; scanOcrBanner.textContent = ''; return; }
    scanOcrBanner.hidden = false;
    scanOcrBanner.classList.toggle('is-error', Boolean(isError));
    scanOcrBanner.textContent = message;
  };

  const applyStudentSuggestions = (text) => {
    const detected = window.DatsScanFlow.parseStudentDetails(text);
    const fields = [
      ['firstName', 'scanNewFirstName', 'First name'],
      ['middleInitial', 'scanNewMiddleInitial', 'Middle name'],
      ['lastName', 'scanNewLastName', 'Last name'],
      ['gender', 'scanNewGender', 'Gender'],
      ['birthdate', 'scanNewBirthdate', 'Birthdate'],
      ['academicYear', 'scanNewAcademicYear', 'Academic year'],
      ['contactNumber', 'scanNewContactNumber', 'Contact number'],
      ['college', 'scanNewCollege', 'College'],
      ['course', 'scanNewCourse', 'Program / course'],
      ['major', 'scanNewMajor', 'Major'],
      ['yearLevel', 'scanNewYearLevel', 'Year level'],
    ];
    const applied = [];
    fields.forEach(([key, fieldId, label]) => {
      const value = detected[key];
      const field = document.getElementById(fieldId);
      if (!value || !field || (field.value && field.value !== 'Undisclosed')) return;
      if (field.tagName === 'SELECT' && ![...field.options].some((option) => option.value === value)) return;
      field.value = value;
      suggestedStudentFields.set(fieldId, value);
      applied.push(label);
    });
    return applied;
  };

  const applyOcrResultToScanUI = (ocrResult, file) => {
    lastOcrResult = ocrResult;
    lastOcrFileKey = fileOcrKey(file);
    if (!ocrResult) {
      setOcrBanner('OCR could not be run on this file. You can still fill in the details manually.', true);
      return;
    }
    const detectedCategory = window.DatsScanFlow.inferDocumentCategory(ocrResult.ocrText || '', scanReferenceData.categories);
    if (scanCategory && !categoryManuallySet && ocrResult.ocrText) {
      const categoryCode = window.DatsScanFlow.resolveDetectedCategory(scanReferenceData.categories, detectedCategory);
      if (categoryCode) {
        scanCategory.value = categoryCode;
        categoryDetectedFromFile = true;
        scanCategorySource?.classList.add('is-detected');
        if (scanCategorySource) scanCategorySource.textContent = 'Detected from uploaded document — verify before archiving.';
      } else {
        if (categoryDetectedFromFile) scanCategory.value = '';
        categoryDetectedFromFile = false;
        scanCategorySource?.classList.remove('is-detected');
        if (scanCategorySource) scanCategorySource.textContent = 'No supported type was detected. Select a document type.';
      }
    }
    const detectedYear = detectAcademicYearFromText(ocrResult.ocrText || '');
    const suggestedFields = ocrResult.ocrStatus === 'Completed' && ocrResult.ocrText
      ? applyStudentSuggestions(ocrResult.ocrText)
      : [];
    if (ocrResult.ocrStatus === 'Completed' && ocrResult.ocrText) {
      setOcrBanner(`${detectedCategory ? `Detected: ${detectedCategory.name}` : 'No reliable document type detected'}${detectedYear ? ` · Academic year ${detectedYear} found in text` : ''}${suggestedFields.length ? ` · Student details suggested: ${suggestedFields.join(', ')}` : ''}. Review and correct the form before archiving.`);
    } else {
      setOcrBanner(ocrResult.ocrError || 'No readable text could be extracted. Verify the document details before archiving.', true);
    }
  };

  const processFileForOcr = async (file) => {
    if (!file) return null;
    const requestFileKey = fileOcrKey(file);
    const requestToken = ++ocrRequestToken;
    activeOcrFileKey = requestFileKey;
    setOcrBanner('Checking document text and AI classification...');
    let ocrResult = await runServerOcrOnFile(file);
    if (!ocrResult) {
      setOcrBanner('Server OCR is unavailable, so the browser is processing the document instead...');
      ocrResult = await runOcrOnFile(file);
    }
    if (!window.DatsScanFlow.isCurrentOcrResult(activeOcrFileKey, requestFileKey, ocrRequestToken, requestToken)) return null;
    applyOcrResultToScanUI(ocrResult, file);
    return ocrResult;
  };

  const showPdfPreview = (blob, filename) => {
    const url = URL.createObjectURL(blob);
    const modal = document.createElement('div');
    modal.style.cssText = 'position:fixed;inset:0;z-index:20;display:grid;place-items:center;padding:22px;background:rgba(42,23,26,.72);';
    modal.innerHTML = '<section style="display:flex;flex-direction:column;width:min(100%,900px);height:min(92vh,760px);padding:18px;background:#fff;border-top:5px solid #6b1d2a"><div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px"><strong style="font-size:14px">Annual document report preview</strong><button type="button" data-close-preview aria-label="Close preview" style="padding:6px 10px;border:0;background:transparent;font-size:20px">&times;</button></div><iframe title="Annual document report preview" style="width:100%;flex:1;border:1px solid #e8dedb"></iframe><div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px"><button type="button" data-close-preview style="padding:10px 14px;color:#6b1d2a;background:#fff;border:1px solid #e8dedb">Close</button><a data-download-preview style="padding:10px 14px;color:#fff;background:#6b1d2a;text-decoration:none;font-size:11px;font-weight:700">Download PDF</a></div></section>';
    modal.querySelector('iframe').src = url;
    const download = modal.querySelector('[data-download-preview]');
    download.href = url;
    download.download = filename;
    const close = () => { URL.revokeObjectURL(url); modal.remove(); };
    modal.querySelectorAll('[data-close-preview]').forEach((button) => button.addEventListener('click', close));
    modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
    document.body.append(modal);
  };

  mobileMenu?.addEventListener('click', () => {
    const isOpen = mobileMenu.getAttribute('aria-expanded') === 'true';
    mobileMenu.setAttribute('aria-expanded', String(!isOpen));
    sidebar.classList.toggle('open', !isOpen);
  });

  exportButton?.addEventListener('click', async () => {
    if (!window.jspdf?.jsPDF) return showAdminToast('PDF generator is unavailable. Check your internet connection.');
    exportButton.disabled = true;
    try {
      const response = await fetch('../api.php?resource=analytics', { credentials: 'same-origin' });
      const result = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(result.error || 'Unable to load dashboard report data.');
      const report = result.data;
      const { jsPDF } = window.jspdf;
      const pdf = new jsPDF();
      const generatedAt = new Date();
      const today = generatedAt.toLocaleString();
      const reportDate = generatedAt.toISOString().slice(0, 10);
      const reportRate = (value) => `${Math.max(0, Number(value) || 0).toFixed(1)}%`;
      const reportBytes = (value) => {
        const bytes = Math.max(0, Number(value) || 0);
        if (bytes >= 1024 ** 3) return `${(bytes / 1024 ** 3).toFixed(1)} GB`;
        if (bytes >= 1024 ** 2) return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
        if (bytes >= 1024) return `${(bytes / 1024).toFixed(1)} KB`;
        return `${bytes.toLocaleString()} B`;
      };
      let y = 22;
      const ensureRoom = (height = 8) => {
        if (y + height <= 280) return;
        pdf.addPage();
        y = 20;
      };
      const addSection = (title) => {
        ensureRoom(14);
        y += 5;
        pdf.setFontSize(11);
        pdf.text(title, 20, y);
        y += 8;
        pdf.setFontSize(9);
      };
      const addLine = (text, indent = 24) => {
        const lines = pdf.splitTextToSize(String(text), 165);
        ensureRoom(lines.length * 5 + 2);
        pdf.text(lines, indent, y);
        y += lines.length * 5 + 1;
      };
      pdf.setFontSize(18);
      pdf.text('ZPPSU Digital Archive', 20, y);
      pdf.setFontSize(12);
      pdf.text('Dashboard Analytics Report', 20, y + 9);
      pdf.setFontSize(9);
      pdf.text(`Generated: ${today}`, 20, y + 17);
      y += 34;

      addSection('Key performance indicators');
      addLine(`Archived records: ${Number(report.archived_documents || 0).toLocaleString()}`);
      addLine(`Student archive coverage: ${Number(report.students_with_archived_documents || 0).toLocaleString()} of ${Number(report.eligible_students || 0).toLocaleString()} (${reportRate(report.student_archive_coverage_rate)})`);
      addLine(`Archived this week: ${Number(report.archived_this_week || 0).toLocaleString()} (${Number(report.archived_week_change || 0) >= 0 ? '+' : ''}${Number(report.archived_week_change || 0).toLocaleString()} vs last week)`);
      addLine(`Search-ready records: ${Number(report.ocr_completed_archived || 0).toLocaleString()} of ${Number(report.archived_documents || 0).toLocaleString()} (${reportRate(report.search_ready_rate)})`);

      addSection('Coverage by college');
      (report.coverage_by_college || []).forEach((row) => {
        const eligible = Number(row.eligible_students || 0);
        addLine(eligible
          ? `${row.college_name}: ${Number(row.covered_students || 0).toLocaleString()} of ${eligible.toLocaleString()} students (${reportRate(row.coverage_rate)})`
          : `${row.college_name}: No registered students`);
      });

      addSection('Eight-week throughput');
      (report.archive_by_week || []).forEach((row) => addLine(`${row.week_label || row.week_start}: ${Number(row.document_count || 0).toLocaleString()} archived records`));

      addSection('Archive composition');
      (report.archive_by_category || []).forEach((row) => addLine(`${row.category_name}: ${Number(row.document_count || 0).toLocaleString()} archived records`));

      addSection('Records requiring attention');
      addLine(`OCR pending: ${Number(report.ocr_pending_archived || 0).toLocaleString()}`);
      addLine(`OCR failed: ${Number(report.ocr_failed_archived || 0).toLocaleString()}`);
      addLine(`Missing file path: ${Number(report.missing_file_path_count || 0).toLocaleString()}`);
      addLine(`Missing file size: ${Number(report.missing_file_size_count || 0).toLocaleString()}`);
      addLine(`Archived file size: ${reportBytes(report.archived_storage_bytes)}`);

      const filename = `zppsu-archive-dashboard-report-${reportDate}.pdf`;
      showPdfPreview(pdf.output('blob'), filename);
      showAdminToast('PDF preview ready.');
    } catch (error) {
      showAdminToast(error.message);
    } finally {
      exportButton.disabled = false;
    }
  });

  let selectedFiles = [];
  let previewRenderToken = 0;
  const PDF_PREVIEW_PAGE_CAP = 8;

  const formatFileSize = (bytes) => {
    if (!Number.isFinite(bytes)) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  };

  const isPdfFile = (file) => file.type === 'application/pdf' || /\.pdf$/i.test(file.name || '');

  const buildPdfPagePreviews = async (file) => {
    const key = fileOcrKey(file);
    if (pdfPreviewCache.has(key)) return pdfPreviewCache.get(key);
    const pdfjsLib = await ensurePdfJs();
    const arrayBuffer = await file.arrayBuffer();
    const pdfDocument = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
    const totalPages = pdfDocument.numPages;

    const effectiveRotation = await detectPdfReadableRotation(pdfDocument);

    const renderPage = async (pageNumber, scale, outputType = 'image/jpeg', overrideRotation) => {
      const page = await pdfDocument.getPage(pageNumber);
      const rot = overrideRotation !== undefined ? overrideRotation : effectiveRotation;
      const viewport = page.getViewport({ scale, rotation: rot });
      const canvas = document.createElement('canvas');
      canvas.width = viewport.width;
      canvas.height = viewport.height;
      await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
      return outputType === 'image/png' ? canvas.toDataURL('image/png') : canvas.toDataURL('image/jpeg', 0.85);
    };
    const pagesToRender = Math.min(totalPages, PDF_PREVIEW_PAGE_CAP);
    const pages = [];
    for (let pageNumber = 1; pageNumber <= pagesToRender; pageNumber += 1) {
      const scale = pageNumber === 1 ? 1.8 : 0.5;
      pages.push(await renderPage(pageNumber, scale));
    }
    const result = { totalPages, pages, renderPage, effectiveRotation };
    pdfPreviewCache.set(key, result);
    return result;
  };

  const rotateImageDataUrl = (src, degrees) => new Promise((resolve, reject) => {
    const norm = window.DatsScanFlow?.normalizeRotation
      ? window.DatsScanFlow.normalizeRotation(degrees)
      : ((degrees % 360) + 360) % 360;
    if (!norm) { resolve(src); return; }
    const image = new Image();
    image.onload = () => {
      const swapped = norm % 180 !== 0;
      const canvas = document.createElement('canvas');
      canvas.width = swapped ? image.naturalHeight : image.naturalWidth;
      canvas.height = swapped ? image.naturalWidth : image.naturalHeight;
      const context = canvas.getContext('2d');
      context.translate(canvas.width / 2, canvas.height / 2);
      context.rotate((norm * Math.PI) / 180);
      context.drawImage(image, -image.naturalWidth / 2, -image.naturalHeight / 2);
      resolve(canvas.toDataURL('image/jpeg', 0.9));
    };
    image.onerror = reject;
    image.src = src;
  });

  const COVER_ZOOM_MIN = 1;
  const COVER_ZOOM_MAX = 3;
  const COVER_ZOOM_STEP = 0.5;
  let coverBaseSrc = null;
  let coverRotation = 0;
  let coverZoom = COVER_ZOOM_MIN;
  let previewPages = [];
  let activePreviewKey = null;
  let previewCoverRenderToken = 0;
  let panPointerId = null;
  let panStartX = 0;
  let panStartY = 0;
  let panStartScrollLeft = 0;
  let panStartScrollTop = 0;

  const applyCoverZoom = () => {
    if (!previewCover) return;
    const coverImg = previewCover.querySelector('img');
    if (coverImg) coverImg.style.transform = `scale(${coverZoom})`;
    previewCover.classList.toggle('is-zoomed', coverZoom > COVER_ZOOM_MIN);
    const zoomOutBtn = previewCover.querySelector('[data-tool="zoom-out"]');
    const zoomInBtn = previewCover.querySelector('[data-tool="zoom-in"]');
    if (zoomOutBtn) zoomOutBtn.disabled = coverZoom <= COVER_ZOOM_MIN;
    if (zoomInBtn) zoomInBtn.disabled = coverZoom >= COVER_ZOOM_MAX;
    if (coverZoom <= COVER_ZOOM_MIN) previewCover.classList.remove('is-panning');
  };

  const stopCoverPanning = (event) => {
    if (panPointerId === null || (event && event.pointerId !== panPointerId)) return;
    if (event && previewCover?.hasPointerCapture(event.pointerId)) previewCover.releasePointerCapture(event.pointerId);
    panPointerId = null;
    previewCover?.classList.remove('is-panning');
  };

  previewCover?.addEventListener('pointerdown', (event) => {
    if (coverZoom <= COVER_ZOOM_MIN || !event.target.closest('img')) return;
    panPointerId = event.pointerId;
    panStartX = event.clientX;
    panStartY = event.clientY;
    panStartScrollLeft = previewCover.scrollLeft;
    panStartScrollTop = previewCover.scrollTop;
    previewCover.setPointerCapture(event.pointerId);
    previewCover.classList.add('is-panning');
    event.preventDefault();
  });
  previewCover?.addEventListener('pointermove', (event) => {
    if (panPointerId !== event.pointerId) return;
    previewCover.scrollLeft = panStartScrollLeft - (event.clientX - panStartX);
    previewCover.scrollTop = panStartScrollTop - (event.clientY - panStartY);
    event.preventDefault();
  });
  previewCover?.addEventListener('pointerup', stopCoverPanning);
  previewCover?.addEventListener('pointercancel', stopCoverPanning);
  previewCover?.addEventListener('lostpointercapture', () => stopCoverPanning());

  const applyCoverRotation = async () => {
    if (!coverBaseSrc || !previewCover) return;
    const coverImg = previewCover.querySelector('img');
    if (!coverImg) return;
    try {
      coverImg.src = await rotateImageDataUrl(coverBaseSrc, coverRotation);
    } catch (error) {
      // Keep the last successfully rendered orientation if rotation fails.
    }
  };

  const rotateActivePreviewPage = async (deltaDegrees) => {
    const page = previewPages.find((p) => p.key === activePreviewKey);
    if (!page || !coverBaseSrc || !previewCover) return;
    page.manualRotation = ((page.manualRotation || 0) + deltaDegrees + 360) % 360;
    coverRotation = page.manualRotation;
    try {
      const rotatedSrc = await rotateImageDataUrl(coverBaseSrc, coverRotation);
      const coverImg = previewCover.querySelector('img');
      if (coverImg) coverImg.src = rotatedSrc;

      if (!page.thumbBaseSrc && page.src) {
        page.thumbBaseSrc = page.src;
      }
      if (page.thumbBaseSrc) {
        page.src = await rotateImageDataUrl(page.thumbBaseSrc, coverRotation);
        const thumbItem = previewThumbs?.querySelector(`[data-preview-key="${page.key}"]`);
        const thumbImg = thumbItem?.querySelector('img');
        if (thumbImg) thumbImg.src = page.src;
      }
    } catch (error) {
      console.warn('Rotation update failed:', error);
    }
  };

  const resetActivePreviewPage = () => {
    const page = previewPages.find((p) => p.key === activePreviewKey);
    if (page) {
      page.manualRotation = 0;
      if (page.thumbBaseSrc) {
        page.src = page.thumbBaseSrc;
        const thumbItem = previewThumbs?.querySelector(`[data-preview-key="${page.key}"]`);
        const thumbImg = thumbItem?.querySelector('img');
        if (thumbImg) thumbImg.src = page.src;
      }
    }
    coverRotation = 0;
    coverZoom = COVER_ZOOM_MIN;
    applyCoverZoom();
    if (previewCover && coverBaseSrc) {
      const coverImg = previewCover.querySelector('img');
      if (coverImg) coverImg.src = coverBaseSrc;
    }
  };

  const buildCoverToolbar = () => {
    const toolbar = document.createElement('div');
    toolbar.className = 'preview-toolbar';
    const makeToolButton = (toolKey, icon, label, onActivate) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'preview-tool-btn';
      button.dataset.tool = toolKey;
      button.title = label;
      button.setAttribute('aria-label', label);
      button.innerHTML = `<i data-lucide="${icon}"></i>`;
      button.addEventListener('click', (event) => { event.preventDefault(); onActivate(); });
      return button;
    };
    toolbar.append(
      makeToolButton('zoom-out', 'zoom-out', 'Zoom out', () => {
        coverZoom = Math.max(COVER_ZOOM_MIN, +(coverZoom - COVER_ZOOM_STEP).toFixed(2));
        applyCoverZoom();
      }),
      makeToolButton('zoom-in', 'zoom-in', 'Zoom in', () => {
        coverZoom = Math.min(COVER_ZOOM_MAX, +(coverZoom + COVER_ZOOM_STEP).toFixed(2));
        applyCoverZoom();
      }),
      makeToolButton('rotate-left', 'rotate-ccw', 'Rotate left', () => {
        rotateActivePreviewPage(270);
      }),
      makeToolButton('rotate-right', 'rotate-cw', 'Rotate right', () => {
        rotateActivePreviewPage(90);
      }),
      makeToolButton('reset', 'refresh-cw', 'Reset view', () => {
        resetActivePreviewPage();
      }),
    );
    return toolbar;
  };

  const resetUploadState = () => {
    prepareForFileReplacement();
    selectedFiles = [];
    selectedUploadFile = null;
    previewRenderToken += 1;
    previewCoverRenderToken += 1;
    previewPages = [];
    activePreviewKey = null;
    coverBaseSrc = null;
    coverRotation = 0;
    coverZoom = COVER_ZOOM_MIN;
    if (documentPreview) documentPreview.hidden = true;
    if (previewCover) {
      previewCover.replaceChildren();
      previewCover.classList.remove('is-zoomed', 'is-panning');
    }
    if (previewThumbs) { previewThumbs.replaceChildren(); previewThumbs.hidden = true; }
    if (uploadResult) uploadResult.innerHTML = '<i data-lucide="file-plus-2"></i><span>No files selected yet</span>';
    if (fileInput) fileInput.value = '';
    categoryManuallySet = false;
    scanCategorySource?.classList.remove('is-detected');
    if (scanCategorySource) scanCategorySource.textContent = 'Select a type or upload a document for automatic detection.';
    lastOcrResult = null;
    lastOcrFileKey = null;
    setOcrBanner('');
    lucide.createIcons();
  };

  const renderUploadSummary = () => {
    if (!uploadResult) return;
    if (!selectedFiles.length) {
      uploadResult.innerHTML = '<i data-lucide="file-plus-2"></i><span>No files selected yet</span>';
    } else {
      const primaryName = selectedFiles[0].name || 'selected file';
      uploadResult.innerHTML = `<i data-lucide="file-check-2"></i><span>${selectedFiles.length} file${selectedFiles.length > 1 ? 's' : ''} ready to archive · ${primaryName}</span>`;
    }
    lucide.createIcons();
  };

  const removeSelectedFile = (index) => {
    selectedFiles.splice(index, 1);
    if (!selectedFiles.length) {
      resetUploadState();
      return;
    }
    prepareForFileReplacement();
    selectedUploadFile = selectedFiles[0];
    renderUploadSummary();
    renderDocumentPreview();
    processFileForOcr(selectedFiles[0]);
  };

  const buildThumbItem = ({ imageSrc, icon, label, title, previewKey, onActivate, onRemove }) => {
    const item = document.createElement('div');
    item.className = 'preview-thumb-item';
    if (previewKey) {
      item.dataset.previewKey = previewKey;
      item.setAttribute('role', 'button');
      item.setAttribute('tabindex', '0');
      item.setAttribute('aria-current', activePreviewKey === previewKey ? 'true' : 'false');
      item.addEventListener('click', (event) => {
        if (event.target.closest('.preview-remove')) return;
        onActivate?.();
      });
      item.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        event.preventDefault();
        onActivate?.();
      });
    }
    item.classList.toggle('is-active', Boolean(previewKey && activePreviewKey === previewKey));
    const box = document.createElement('div');
    box.className = 'preview-thumb-box';
    if (imageSrc) {
      const img = document.createElement('img');
      img.src = imageSrc;
      img.alt = label;
      box.append(img);
    } else {
      box.innerHTML = `<i data-lucide="${icon || 'file-text'}"></i>`;
    }
    if (onRemove) {
      const removeButton = document.createElement('button');
      removeButton.type = 'button';
      removeButton.className = 'preview-remove';
      removeButton.setAttribute('aria-label', `Remove ${label}`);
      removeButton.innerHTML = '<i data-lucide="x"></i>';
      removeButton.addEventListener('click', (event) => { event.preventDefault(); onRemove(); });
      box.append(removeButton);
    }
    item.append(box);
    const caption = document.createElement('small');
    caption.textContent = label;
    if (title) item.title = title;
    item.append(caption);
    return item;
  };

  const updateActivePreviewThumb = () => {
    previewThumbs?.querySelectorAll('[data-preview-key]').forEach((item) => {
      const isActive = item.dataset.previewKey === activePreviewKey;
      item.classList.toggle('is-active', isActive);
      item.setAttribute('aria-current', String(isActive));
    });
  };

  const renderPreviewCover = (preview) => {
    if (!previewCover || !preview) return;

    previewCover.replaceChildren();
    previewCover.classList.remove('is-zoomed', 'is-panning');
    coverZoom = COVER_ZOOM_MIN;
    coverBaseSrc = preview.baseSrc || coverBaseSrc || preview.src || null;
    coverRotation = preview.manualRotation || 0;

    if (preview.src) {
      const img = document.createElement('img');
      img.src = preview.src;
      img.alt = preview.alt;
      previewCover.append(img);
    } else {
      const fallback = document.createElement('div');
      fallback.className = 'preview-fallback';
      fallback.innerHTML = `<i data-lucide="file-text"></i><small>${preview.fallbackLabel}</small>`;
      previewCover.append(fallback);
    }

    const badge = document.createElement('span');
    badge.className = 'preview-page-badge';
    badge.textContent = preview.badge;
    previewCover.append(badge);

    const removeCoverButton = document.createElement('button');
    removeCoverButton.type = 'button';
    removeCoverButton.className = 'preview-remove preview-remove-cover';
    removeCoverButton.setAttribute('aria-label', `Remove ${preview.fileName}`);
    removeCoverButton.innerHTML = '<i data-lucide="x"></i>';
    removeCoverButton.addEventListener('click', (event) => { event.preventDefault(); removeSelectedFile(0); });
    previewCover.append(removeCoverButton);

    if (preview.src) {
      previewCover.append(buildCoverToolbar());
      applyCoverZoom();
    }
    lucide.createIcons();
  };

  const selectPreviewPage = async (previewKey) => {
    const preview = previewPages.find((page) => page.key === previewKey);
    if (!preview) return;
    const token = ++previewCoverRenderToken;
    let coverPreview = preview;
    if (preview.renderHighQuality) {
      try {
        const src = await preview.renderHighQuality();
        coverPreview = { ...preview, src, baseSrc: src };
      } catch (error) {
        // Keep the thumbnail render if the high-resolution render fails.
      }
    }
    if (token !== previewCoverRenderToken) return;
    activePreviewKey = previewKey;
    coverRotation = preview.manualRotation || 0;
    coverBaseSrc = coverPreview.baseSrc || coverPreview.src || null;
    if (coverRotation && coverBaseSrc) {
      try {
        const rotated = await rotateImageDataUrl(coverBaseSrc, coverRotation);
        coverPreview = { ...coverPreview, src: rotated };
      } catch (error) {}
    }
    renderPreviewCover(coverPreview);
    updateActivePreviewThumb();
    [...(previewThumbs?.querySelectorAll('[data-preview-key]') || [])]
      .find((item) => item.dataset.previewKey === previewKey)
      ?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
  };

  const renderDocumentPreview = async () => {
    if (!documentPreview || !previewCover || !previewThumbs) return;
    if (!selectedFiles.length) {
      documentPreview.hidden = true;
      previewCover.replaceChildren();
      previewThumbs.replaceChildren();
      return;
    }

    const token = ++previewRenderToken;
    previewCoverRenderToken += 1;
    const primaryFile = selectedFiles[0];
    const extraFiles = selectedFiles.slice(1);

    documentPreview.hidden = false;
    previewCover.replaceChildren();
    previewThumbs.replaceChildren();
    previewThumbs.hidden = true;
    previewCover.innerHTML = '<div class="preview-spinner"><i data-lucide="loader-circle"></i><span>Generating preview and optimizing orientation…</span></div>';
    lucide.createIcons();

    let totalPages = 1;
    const primaryIsPdf = isPdfFile(primaryFile);

    if (primaryIsPdf) {
      try {
        const result = await buildPdfPagePreviews(primaryFile);
        totalPages = result.totalPages;
        previewPages = result.pages.map((src, index) => ({
          key: `page-${index + 1}`,
          src,
          baseSrc: src,
          thumbBaseSrc: src,
          rotation: result.effectiveRotation,
          manualRotation: 0,
          alt: `Preview of ${primaryFile.name}, page ${index + 1}`,
          badge: `Page ${index + 1} of ${totalPages}`,
          fileName: primaryFile.name,
          fallbackLabel: primaryFile.name,
          renderHighQuality: () => result.renderPage(index + 1, 3, 'image/png'),
        }));
      } catch (error) {
        previewPages = [];
      }
    } else if (primaryFile.type.startsWith('image/')) {
      let src = URL.createObjectURL(primaryFile);
      let detectedRot = 0;
      try {
        detectedRot = await detectImageReadableRotation(primaryFile);
        if (detectedRot) {
          src = await rotateImageDataUrl(src, detectedRot);
        }
      } catch (e) {}
      previewPages = [{
        key: 'file-0',
        src,
        baseSrc: src,
        thumbBaseSrc: src,
        rotation: detectedRot,
        manualRotation: 0,
        alt: `Preview of ${primaryFile.name}`,
        badge: primaryFile.name,
        fileName: primaryFile.name,
        fallbackLabel: primaryFile.name,
      }];
    } else {
      previewPages = [];
    }

    if (token !== previewRenderToken) return;

    if (!previewPages.length) {
      previewPages = [{
        key: 'file-0',
        src: null,
        alt: `Preview of ${primaryFile.name}`,
        badge: primaryFile.name,
        fileName: primaryFile.name,
        fallbackLabel: primaryFile.name,
      }];
    }

    if (previewPages[0]) await selectPreviewPage(previewPages[0].key);
    if (token !== previewRenderToken) return;

    previewThumbs.replaceChildren();
    previewPages.slice(1).forEach((preview) => {
      previewThumbs.append(buildThumbItem({
        imageSrc: preview.src,
        label: preview.badge,
        title: `Show ${preview.badge}`,
        previewKey: preview.key,
        onActivate: () => selectPreviewPage(preview.key),
      }));
    });
    const unrenderedPages = totalPages - previewPages.length;
    if (unrenderedPages > 0) {
      const more = document.createElement('div');
      more.className = 'preview-more-pages';
      more.textContent = `+${unrenderedPages} more page${unrenderedPages > 1 ? 's' : ''}`;
      previewThumbs.append(more);
    }
    extraFiles.forEach((file, i) => {
      const index = i + 1;
      previewThumbs.append(buildThumbItem({
        imageSrc: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
        label: file.name,
        title: `${file.name} · ${formatFileSize(file.size)}`,
        onRemove: () => removeSelectedFile(index),
      }));
    });
    previewThumbs.hidden = !previewThumbs.children.length;
    lucide.createIcons();
  };

  const handleUploadFiles = async (files) => {
    if (!files || !files.length) return;
    const normalizedFiles = Array.from(files).filter((file) => file && typeof file === 'object');
    if (!normalizedFiles.length) return;

    selectedFiles = normalizedFiles;
    const firstFile = normalizedFiles[0];
    prepareForFileReplacement();
    selectedUploadFile = firstFile;

    renderUploadSummary();
    renderDocumentPreview();
    processFileForOcr(firstFile);
  };

  fileInput?.addEventListener('change', () => handleUploadFiles([...fileInput.files]));

  ['dragenter', 'dragover'].forEach((eventName) => uploadZone?.addEventListener(eventName, (event) => {
    event.preventDefault();
    uploadZone.classList.add('drag-over');
  }));
  ['dragleave', 'drop'].forEach((eventName) => uploadZone?.addEventListener(eventName, (event) => {
    event.preventDefault();
    uploadZone.classList.remove('drag-over');
    if (eventName === 'drop') handleUploadFiles([...event.dataTransfer.files]);
  }));

  archiveButton?.addEventListener('click', async () => {
    const captureFile = selectedUploadFile || fileInput?.files?.[0];
    let ocrResult = lastOcrFileKey === fileOcrKey(captureFile) ? lastOcrResult : null;
    if (captureFile && !ocrResult) {
      ocrResult = await processFileForOcr(captureFile);
    }
    if (!ocrResult) {
      ocrResult = { ocrText: '', ocrStatus: 'Failed', ocrError: 'OCR could not be processed for this document.', aiSummary: '', aiDetectedCategory: 'Other Registrar Records' };
    }

    const mode = getScanStudentMode();
    const newStudent = collectNewStudentValues();
    const academicSelection = mode === 'new'
      ? window.DatsScanFlow.resolveAcademicSelection(scanReferenceData.colleges, newStudent)
      : { collegeId: '', courseId: '', majorId: '', error: '' };
    Object.assign(newStudent, academicSelection);
    const validationErrors = window.DatsScanFlow.validateScanSubmission({
      mode,
      studentId: scanStudentId?.value,
      academicYear: scanAcademicYear?.value,
      newStudent,
      categoryCode: scanCategory?.value,
      file: captureFile,
    });
    if (academicSelection.error) validationErrors.push(academicSelection.error);
    if (validationErrors.length) {
      showAdminToast(validationErrors[0]);
      return;
    }

    archiveButton.disabled = true;

    const ocrSucceeded = ocrResult.ocrStatus === 'Completed' && String(ocrResult.ocrText || '').trim() !== '';
    const studentPayload = window.DatsScanFlow.buildStudentPayload(mode, mode === 'existing'
      ? { studentId: scanStudentId.value }
      : newStudent);
    const uploadPayload = {
      ...studentPayload,
      category_id: scanCategory.value,
      original_filename: captureFile.name,
      mime_type: captureFile.type || 'application/octet-stream',
      file_size_bytes: String(captureFile.size),
      upload_source: 'Manual upload',
      status: 'Archived',
      ocr_status: ocrResult.ocrStatus,
      ocr_text: ocrResult.ocrText || '',
      ocr_error: ocrResult.ocrError || '',
      ai_summary: ocrResult.aiSummary || '',
      ai_detected_category: ocrResult.aiDetectedCategory || 'Other Registrar Records',
    };

    // Handed off to the background job bridge (window.DatsJobs, see data-sync.js) so the save
    // keeps running — and actually finishes — even if the user navigates to another page before
    // it completes. The bridge's status banner covers the "it's working" feedback; this handler
    // only needs to react once the job reaches a terminal state.
    const finishArchiving = () => {
      toast.textContent = ocrSucceeded
        ? 'Document added directly to the archive.'
        : 'Document archived. OCR could not extract readable text, but the entered record details were saved.';
      toast.classList.add('show');
      resetUploadState();
      if (scanCategory) scanCategory.value = '';
      if (scanAcademicYear) scanAcademicYear.value = '';
      if (scanStudentSearch) scanStudentSearch.value = '';
      if (scanStudentId) scanStudentId.value = '';
      scanNewStudentFields?.querySelectorAll('input').forEach((field) => { field.value = ''; });
      scanNewStudentFields?.querySelectorAll('select').forEach((field) => { field.selectedIndex = 0; });
      const existingMode = document.querySelector('#scanStudentExisting');
      if (existingMode) {
        existingMode.checked = true;
        existingMode.dispatchEvent(new Event('change'));
      }
      lucide.createIcons();
      window.dispatchEvent(new CustomEvent('dats:uploads-changed'));
      window.setTimeout(() => toast.classList.remove('show'), 3200);
    };

    const jobId = await window.DatsJobs.enqueue('scan-upload', uploadPayload, captureFile);
    const unsubscribe = window.DatsJobs.onUpdate(async (job) => {
      if (job.id !== jobId) return;
      if (job.status === 'done') {
        unsubscribe();
        archiveButton.disabled = false;
        if (mode === 'new') {
          try {
            const studentsResponse = await fetch('../api.php?resource=students', { credentials: 'same-origin' });
            if (studentsResponse.ok) {
              const studentsResult = await studentsResponse.json();
              scanReferenceData.students = window.DatsScanFlow.mergeStudentReferences(scanReferenceData.students, studentsResult.data || []);
            }
          } catch (error) {
            console.warn('The student list could not be refreshed after registration.', error);
          }
        }
        finishArchiving();
      } else if (job.status === 'failed') {
        unsubscribe();
        archiveButton.disabled = false;
        toast.textContent = job.error || 'Unable to save the document.';
        toast.classList.add('show');
        window.setTimeout(() => toast.classList.remove('show'), 3200);
      } else if (job.status === 'duplicate') {
        // No window.confirm()/window.open() here: this can resolve long after the click that
        // started it (even on a different page), with no user gesture to hang a popup off. The
        // persistent job banner (data-sync.js: student-record-view.php?student_id=...) already renders a clickable review link.
        unsubscribe();
        archiveButton.disabled = false;
        toast.textContent = job.error || 'This document already exists for this student.';
        toast.classList.add('show');
        window.setTimeout(() => toast.classList.remove('show'), 4200);
      }
    });
  });

  const filterUsers = () => {
    const search = userSearch.value.toLowerCase();
    let visibleCount = 0;
    userRows?.querySelectorAll('tr').forEach((row) => {
      const matchesSearch = row.textContent.toLowerCase().includes(search);
      const matchesStatus = statusFilter.value === 'all' || row.querySelector('.status-badge')?.textContent === statusFilter.value;
      const visible = matchesSearch && matchesStatus;
      row.hidden = !visible;
      if (visible) visibleCount += 1;
    });
    if (emptyUsers) emptyUsers.hidden = visibleCount !== 0;
  };

  [userSearch, statusFilter].forEach((control) => control?.addEventListener('input', filterUsers));
  addUserButton?.addEventListener('click', () => {
    userModal.removeAttribute('data-user-id');
    userModal.querySelector('#userFullName').value = '';
    userModal.querySelector('#userIdNumber').value = '';
    userModal.querySelector('#userEmail').value = '';
    userModal.querySelector('#userRole').value = 'Admin';
    userModal.querySelector('#userRole').disabled = false;
    userModal.querySelector('#userPassword').value = '';
    userModal.querySelector('#userPassword').required = true;
    userModal.querySelector('#userFormMessage').textContent = '';
    userModal.querySelector('h2').textContent = 'Add a new user';
    saveUserButton.innerHTML = 'Create user <i data-lucide="arrow-right"></i>';
    userModal.hidden = false;
    if (window.lucide) lucide.createIcons();
  });
  closeUserModal?.addEventListener('click', () => { userModal.hidden = true; });
  userModal?.addEventListener('click', (event) => { if (event.target === userModal) userModal.hidden = true; });
  saveUserButton?.addEventListener('click', async () => {
    const fullName = userModal.querySelector('#userFullName');
    const idNumber = userModal.querySelector('#userIdNumber');
    const email = userModal.querySelector('#userEmail');
    const password = userModal.querySelector('#userPassword');
    const message = userModal.querySelector('#userFormMessage');
    const action = userModal.dataset.userId ? 'update' : 'create';
    const payload = { action, user_id: userModal.dataset.userId, full_name: fullName.value.trim(), id_number: idNumber.value.trim(), email: email.value.trim(), role: userModal.querySelector('#userRole').value };
    if (action === 'create') payload.password = password.value;
    let response;
    try {
      response = await fetch('../api.php?resource=users', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify(payload) });
    } catch (error) {
      message.textContent = 'Unable to reach the server. Check that Apache and MariaDB are running.';
      return;
    }
    if (!response.ok) {
      const result = await response.json().catch(() => ({}));
      message.textContent = result.error || 'Unable to save user.';
      return;
    }
    const result = await response.json().catch(() => ({}));
    if (!result.data?.id) {
      message.textContent = 'The server did not confirm that the user was saved.';
      return;
    }
    userModal.hidden = true;
    userModal.removeAttribute('data-user-id');
    userModal.querySelector('h2').textContent = 'Add a new user';
    password.value = '';
    message.textContent = '';
    saveUserButton.innerHTML = 'Create user <i data-lucide="arrow-right"></i>';
    if (window.lucide) lucide.createIcons();
    toast.textContent = action === 'create' ? 'New user created successfully.' : 'User updated successfully.';
    toast.classList.add('show');
    window.setTimeout(() => toast.classList.remove('show'), 3200);
    window.setTimeout(() => window.location.reload(), 350);
  });
  userRows?.addEventListener('click', (event) => {
    const actionButton = event.target.closest('.users-table .row-action');
    if (!actionButton) return;
    document.querySelectorAll('.user-action-menu').forEach((menu) => menu.remove());
    const row = actionButton.closest('tr');
    const user = row.userData;
    if (!user) return showAdminToast('User data is still loading.');
    const menu = document.createElement('div');
    menu.className = 'user-action-menu';
    menu.innerHTML = '<button type="button" data-user-action="edit"><i data-lucide="pencil"></i> Edit User</button><button type="button" data-user-action="delete"><i data-lucide="trash-2"></i> Delete User</button>';
    actionButton.closest('td').append(menu);
    lucide.createIcons();
    menu.querySelector('[data-user-action="edit"]').addEventListener('click', () => {
      userModal.querySelector('#userFullName').value = user.full_name;
      userModal.querySelector('#userIdNumber').value = user.id_number || '';
      userModal.querySelector('#userEmail').value = user.email;
      userModal.querySelector('#userRole').value = user.role || 'Admin';
      userModal.querySelector('#userRole').disabled = true;
      userModal.querySelector('#userPassword').value = '';
      userModal.querySelector('#userPassword').required = false;
      userModal.querySelector('#userFormMessage').textContent = '';
      userModal.dataset.userId = user.id;
      userModal.querySelector('h2').textContent = 'Edit user';
      saveUserButton.textContent = 'Save changes';
      userModal.hidden = false;
      menu.remove();
    });
    menu.querySelector('[data-user-action="delete"]').addEventListener('click', async () => {
      const response = await fetch('../api.php?resource=users', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ action: 'delete', user_id: user.id }) });
      if (!response.ok) return showAdminToast('Unable to delete user.');
      row.remove();
      showAdminToast('User deleted.');
      menu.remove();
    });
  });

  const openStructureModal = (mode, target, card) => {
    structureMode = mode;
    courseTarget = target;
    editCard = card;
    if (collegeLogoField) collegeLogoField.hidden = mode !== 'college' && mode !== 'edit-college';
    structureModalTitle.textContent = mode === 'college' ? 'Add college' : mode === 'major' ? 'Add major' : 'Add course';
    if (mode === 'edit-college') {
      structureModalTitle.textContent = 'College Settings';
      structureModalCopy.textContent = 'Update the college name or upload a new logo.';
      structureFieldLabel.firstChild.textContent = 'College name';
      structureName.value = card.querySelector('h3').textContent;
      if (collegeLogo) collegeLogo.value = '';
    } else if (mode === 'edit-courses') {
      structureModalTitle.textContent = 'Edit College Courses';
      structureModalCopy.textContent = `Update the courses under ${target}. Use one course per line.`;
      structureFieldLabel.firstChild.textContent = 'Courses';
      structureName.value = [...card.querySelectorAll('.course-list > div span')].map((course) => course.textContent).join('\n');
    } else if (mode === 'edit-course') {
      structureModalTitle.textContent = 'Edit College Course';
      structureModalCopy.textContent = 'Update the name of this course.';
      structureFieldLabel.firstChild.textContent = 'Course name';
      structureName.value = card.querySelector('span').textContent;
    } else if (mode === 'major') {
      structureModalTitle.textContent = 'Add major';
      structureModalCopy.textContent = `Add a major under ${target}.`;
      structureFieldLabel.firstChild.textContent = 'Major name';
      structureName.value = '';
    } else {
      structureModalTitle.textContent = mode === 'college' ? 'Add college' : 'Add course';
      structureModalCopy.textContent = mode === 'college' ? 'Create a new college for the university archive.' : `Add a course under ${target}.`;
      structureFieldLabel.firstChild.textContent = 'Name';
      structureName.value = '';
      if (collegeLogo) collegeLogo.value = '';
    }
    structureModal.hidden = false;
    structureName.focus();
  };

  addCollegeButton?.addEventListener('click', () => openStructureModal('college'));
  addCourseButton?.addEventListener('click', () => showAdminToast('Choose a college row to add a course.'));
  collegeList?.addEventListener('click', async (event) => {
    const addCourse = event.target.closest('[data-add-course]');
    const editCollege = event.target.closest('[data-edit-college]');
    const editCourse = event.target.closest('[data-edit-course]');
    const addMajor = event.target.closest('[data-add-major]');
    const deleteCourse = event.target.closest('[data-delete-course]');
    const deleteCollege = event.target.closest('[data-delete-college]');
    if (addCourse) openStructureModal('course', addCourse.dataset.addCourse);
    if (addMajor) openStructureModal('major', addMajor.closest('.course-list > div')?.querySelector('.course-name')?.firstChild?.textContent.trim());
    if (editCollege) openStructureModal('edit-college', null, editCollege.closest('.college-card'));
    if (editCourse) openStructureModal('edit-course', null, editCourse.closest('.course-list > div'));
    if (deleteCourse) {
      const courseItem = deleteCourse.closest('.course-list > div');
      const card = deleteCourse.closest('.college-card');
      const courseId = courseItem?.dataset.id;
      const courseName = courseItem?.querySelector('span')?.textContent.trim();
      const collegeId = card?.dataset.id;
      const collegeName = card?.dataset.name;
      if (!courseId && (!courseName || (!collegeId && !collegeName))) return showAdminToast('Unable to identify this course.');
      deleteCourse.disabled = true;
      const response = await fetch('../api.php?resource=courses', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ action: 'delete', course_id: courseId, course_name: courseName, college_id: collegeId, college_name: collegeName }),
      });
      if (!response.ok) {
        deleteCourse.disabled = false;
        const result = await response.json().catch(() => ({}));
        return showAdminToast(result.error || 'Unable to delete course.');
      }
      courseItem.remove();
      showAdminToast('Course deleted.');
    }
    if (addMajor) {
      const courseItem = addMajor.closest('.course-list > div');
      const courseId = courseItem?.dataset.id;
      if (!courseId) return showAdminToast('Unable to identify this course.');
      courseTarget = courseId;
    }
    if (deleteCollege) {
      const card = deleteCollege.closest('.college-card');
      const collegeId = card?.dataset.id;
      if (!collegeId) return showAdminToast('Unable to identify this college.');
      deleteCollege.disabled = true;
      const response = await fetch('../api.php?resource=colleges', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ action: 'delete', college_id: collegeId }),
      });
      if (!response.ok) {
        deleteCollege.disabled = false;
        const result = await response.json().catch(() => ({}));
        return showAdminToast(result.error || 'Unable to delete college.');
      }
      card.remove();
      showAdminToast('College deleted.');
    }
  });
  closeStructureModal?.addEventListener('click', () => { structureModal.hidden = true; });
  structureModal?.addEventListener('click', (event) => { if (event.target === structureModal) structureModal.hidden = true; });
  saveStructureButton?.addEventListener('click', async () => {
    const name = structureName.value.trim();
    if (!name) return structureName.focus();
    if (structureMode === 'edit-college') {
      const formData = new FormData();
      formData.append('action', 'update');
      formData.append('college_id', editCard.dataset.id);
      formData.append('name', name);
      if (collegeLogo?.files?.[0]) formData.append('logo', collegeLogo.files[0]);
      const response = await fetch('../api.php?resource=colleges', { method: 'POST', credentials: 'same-origin', body: formData });
      if (!response.ok) {
        const result = await response.json().catch(() => ({}));
        return showAdminToast(result.error || 'Unable to update college settings.');
      }
      const result = await response.json().catch(() => ({}));
      editCard.querySelector('h3').textContent = name;
      editCard.dataset.name = name;
      editCard.querySelector('[data-add-course]')?.setAttribute('data-add-course', name);
      if (result.data?.logo_path) {
        const logo = document.createElement('img');
        logo.src = `../${result.data.logo_path}`;
        logo.alt = `${name} logo`;
        logo.style.cssText = 'display: block; width: 100%; height: 100%; object-fit: contain; border-radius: 2px;';
        editCard.querySelector('.college-icon').replaceChildren(logo);
      }
      showAdminToast('College settings updated.');
    } else if (structureMode === 'edit-course') {
      const response = await fetch('../api.php?resource=courses', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ action: 'update', course_id: editCard.dataset.id, name }) });
      if (!response.ok) return showAdminToast('Unable to update course.');
      editCard.querySelector('span').textContent = name;
      showAdminToast('College course updated.');
    } else if (structureMode === 'major') {
      const response = await fetch('../api.php?resource=majors', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ name, course_id: courseTarget }) });
      if (!response.ok) {
        const result = await response.json().catch(() => ({}));
        return showAdminToast(result.error || 'Unable to save major.');
      }
      const result = await response.json().catch(() => ({}));
      const courseItem = [...collegeList.querySelectorAll('.course-list > div')].find((item) => item.dataset.id === String(courseTarget));
      const courseName = courseItem?.querySelector('.course-name');
      if (courseName) {
        let majorList = courseName.querySelector('.major-list');
        if (!majorList) { majorList = document.createElement('ul'); majorList.className = 'major-list'; courseName.append(majorList); }
        const majorItem = document.createElement('li');
        majorItem.textContent = `Major in ${result.data?.name || name}`;
        majorList.append(majorItem);
      }
      showAdminToast('Major added.');
    } else if (structureMode === 'edit-courses') {
      const courses = structureName.value.split('\n').map((course) => course.trim()).filter(Boolean);
      editCard.querySelector('.course-list').innerHTML = courses.map((course) => `<div><span>${course}</span><b>0 students</b><button class="course-delete" aria-label="Delete ${course}" data-delete-course><i data-lucide="x"></i></button></div>`).join('');
      showAdminToast('College courses updated.');
    } else if (structureMode === 'college') {
      const formData = new FormData();
      formData.append('name', name);
      if (collegeLogo?.files?.[0]) formData.append('logo', collegeLogo.files[0]);
      const response = await fetch('../api.php?resource=colleges', { method: 'POST', credentials: 'same-origin', body: formData });
      if (!response.ok) {
        const result = await response.json().catch(() => ({}));
        return showAdminToast(result.error || 'Unable to save college.');
      }
      const result = await response.json().catch(() => ({}));
      const card = document.createElement('article');
      card.className = 'college-card';
      card.dataset.name = name;
      card.dataset.id = result.data?.id || '';
      card.innerHTML = `<div class="college-heading"><div class="college-title"><span class="college-icon rose-college"><i data-lucide="building-2"></i></span><div><span class="panel-kicker">New college</span><h3>${name}</h3><small>0 courses · 0 students</small></div></div><div class="college-actions"><button class="mini-action" aria-label="Add course to ${name}" data-add-course="${name}"><i data-lucide="plus"></i></button><button class="mini-action delete-college" aria-label="Delete ${name}" data-delete-college><i data-lucide="trash-2"></i></button></div></div><div class="course-list"></div>`;
      if (result.data?.logo_path) {
        const logo = document.createElement('img');
        logo.src = `../${result.data.logo_path}`;
        logo.alt = `${name} logo`;
        logo.style.cssText = 'display: block; width: 100%; height: 100%; object-fit: contain; border-radius: 2px;';
        card.querySelector('.college-icon').replaceChildren(logo);
      }
      collegeList.append(card);
    } else {
      const targetCard = [...collegeList.querySelectorAll('.college-card')].find((card) => card.dataset.name === courseTarget);
      const response = await fetch('../api.php?resource=courses', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ name, college_id: targetCard?.dataset.id }) });
      if (!response.ok) return showAdminToast('Unable to save course.');
      const result = await response.json().catch(() => ({}));
      const courseItem = document.createElement('div');
      courseItem.dataset.id = result.data?.id || '';
      courseItem.innerHTML = `<span></span><b>0 students</b><button class="course-delete" aria-label="Delete ${name}" data-delete-course><i data-lucide="x"></i></button>`;
      courseItem.querySelector('span').textContent = name;
      targetCard?.querySelector('.course-list')?.append(courseItem);
    }
    structureModal.hidden = true;
    lucide.createIcons();
    if (structureMode === 'college' || structureMode === 'course') showAdminToast(`${structureMode === 'college' ? 'College' : 'Course'} added.`);
  });
  structureSearch?.addEventListener('input', () => {
    const search = structureSearch.value.toLowerCase();
    let visible = 0;
    collegeList?.querySelectorAll('.college-card').forEach((card) => {
      const matches = card.textContent.toLowerCase().includes(search);
      card.hidden = !matches;
      if (matches) visible += 1;
    });
    if (emptyStructure) emptyStructure.hidden = visible !== 0;
  });

  const filterStudents = () => {
    const search = studentSearch.value.trim().toLowerCase();
    let visible = 0;
    studentRows?.querySelectorAll('tr').forEach((row) => {
      const student = row.studentData;
      const matchesSearch = selectedStudentId
        ? String(student?.id) === selectedStudentId
        : matchesStudentSearch(student, search);
      const matchesGender = studentGender.value === 'all' || row.dataset.gender === studentGender.value;
      const matchesCollege = studentCollege.value === 'all' || row.dataset.college === studentCollege.value;
      row.hidden = !(matchesSearch && matchesGender && matchesCollege);
      if (!row.hidden) visible += 1;
    });
    if (emptyStudents) emptyStudents.hidden = visible !== 0;
  };

  const getStudentName = (student) => {
    const lastName = String(student.last_name || '').trim();
    const givenNames = [student.first_name, student.middle_initial]
      .map((name) => String(name || '').trim())
      .filter(Boolean)
      .join(' ');
    return lastName ? `${lastName}, ${givenNames}` : givenNames || 'Unnamed student';
  };
  const matchesStudentSearch = (student, search) => {
    if (!student || !search) return true;
    const lastName = String(student.last_name || '').trim().toLowerCase();
    const studentId = String(student.student_number || '').trim().toLowerCase();
    return lastName.startsWith(search) || studentId.includes(search);
  };
  const compareStudentLastNames = (left, right) =>
    String(left.last_name || '').localeCompare(String(right.last_name || ''), undefined, { sensitivity: 'base' })
    || String(left.first_name || '').localeCompare(String(right.first_name || ''), undefined, { sensitivity: 'base' })
    || String(left.student_number || '').localeCompare(String(right.student_number || ''), undefined, { numeric: true, sensitivity: 'base' });
  const closeStudentSuggestions = () => {
    if (!studentSearchSuggestions) return;
    studentSearchSuggestions.hidden = true;
    studentSearchSuggestions.replaceChildren();
    studentSearch?.setAttribute('aria-expanded', 'false');
    studentSearch?.removeAttribute('aria-activedescendant');
    activeStudentSuggestion = -1;
  };
  const renderStudentSuggestions = () => {
    if (!studentSearch || !studentSearchSuggestions) return;
    const term = studentSearch.value.trim().toLowerCase();
    if (!term) { closeStudentSuggestions(); return; }
    const matches = [...(studentRows?.querySelectorAll('tr') || [])]
      .map((row) => row.studentData)
      .filter(Boolean)
      .filter((student) => matchesStudentSearch(student, term))
      .sort(compareStudentLastNames);
    studentSearchSuggestions.replaceChildren(...matches.map((student, index) => {
      const option = document.createElement('button');
      option.type = 'button';
      option.role = 'option';
      option.id = `student-search-option-${student.id}`;
      option.className = `student-search-suggestion${index === activeStudentSuggestion ? ' is-active' : ''}`;
      const label = document.createElement('strong');
      label.textContent = `${student.student_number || '—'} — ${getStudentName(student)}`;
      option.append(label);
      option.addEventListener('mousedown', (event) => {
        event.preventDefault();
        const selectedRow = [...(studentRows?.querySelectorAll('tr') || [])]
          .find((row) => String(row.dataset.studentId) === String(student.id));
        if (!selectedRow) return;
        studentSearch.value = student.student_number || student.last_name || getStudentName(student);
        selectedStudentId = String(student.id);
        studentGender.value = 'all';
        studentCollege.value = 'all';
        closeStudentSuggestions();
        studentRows.querySelectorAll('.is-search-selected').forEach((row) => row.classList.remove('is-search-selected'));
        filterStudents();
        selectedRow.classList.add('is-search-selected');
        selectedRow.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
      });
      return option;
    }));
    if (!matches.length) {
      const empty = document.createElement('div');
      empty.className = 'student-search-empty';
      empty.textContent = 'No student names match this search.';
      studentSearchSuggestions.append(empty);
    }
    studentSearchSuggestions.hidden = false;
    studentSearch.setAttribute('aria-expanded', 'true');
  };

  studentSearch?.addEventListener('input', () => {
    selectedStudentId = null;
    studentRows?.querySelectorAll('.is-search-selected').forEach((row) => row.classList.remove('is-search-selected'));
    activeStudentSuggestion = -1;
    renderStudentSuggestions();
    filterStudents();
  });
  studentSearch?.addEventListener('focus', renderStudentSuggestions);
  window.addEventListener('dats:students-ready', renderStudentSuggestions);
  studentSearch?.addEventListener('keydown', (event) => {
    const options = [...(studentSearchSuggestions?.querySelectorAll('.student-search-suggestion') || [])];
    if (event.key === 'Escape') { closeStudentSuggestions(); return; }
    if (!options.length || !['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)) return;
    event.preventDefault();
    if (event.key === 'Enter' && activeStudentSuggestion >= 0) { options[activeStudentSuggestion].dispatchEvent(new MouseEvent('mousedown')); return; }
    activeStudentSuggestion = event.key === 'ArrowUp'
      ? (activeStudentSuggestion <= 0 ? options.length - 1 : activeStudentSuggestion - 1)
      : (activeStudentSuggestion + 1) % options.length;
    options.forEach((option, index) => option.classList.toggle('is-active', index === activeStudentSuggestion));
    studentSearch.setAttribute('aria-activedescendant', options[activeStudentSuggestion].id);
  });
  document.addEventListener('click', (event) => {
    if (!event.target.closest('.student-search-field')) closeStudentSuggestions();
  });
  [studentGender, studentCollege].forEach((control) => control?.addEventListener('input', filterStudents));
  addStudentButton?.addEventListener('click', () => {
    editingStudentId = null;
    studentModalTitle.textContent = 'Add student';
    studentModalCopy.textContent = 'Register a student profile before attaching scanned documents.';
    studentModal.querySelectorAll('.student-form-grid input').forEach((field) => { field.value = ''; });
    studentModal.querySelectorAll('.student-form-grid select').forEach((field) => { field.selectedIndex = 0; });
    studentModal.querySelector('#studentCollege')._populateStudentCourses();
    studentModal.hidden = false;
  });
  closeStudentModal?.addEventListener('click', () => { studentModal.hidden = true; });
  studentModal?.addEventListener('click', (event) => { if (event.target === studentModal) studentModal.hidden = true; });
  saveStudentButton?.addEventListener('click', async () => {
    const profile = {
      studentNumber: studentModal.querySelector('#studentNumber')?.value,
      lastName: studentModal.querySelector('#studentLastName')?.value,
      firstName: studentModal.querySelector('#studentFirstName')?.value,
      middleInitial: studentModal.querySelector('#studentMiddleInitial')?.value,
      gender: studentModal.querySelector('#studentGenderField')?.value,
      birthdate: studentModal.querySelector('#studentBirthdate')?.value,
      address: studentModal.querySelector('#studentAddress')?.value,
      nationality: studentModal.querySelector('#studentNationality')?.value,
      civilStatus: studentModal.querySelector('#studentCivilStatus')?.value,
      contactNumber: studentModal.querySelector('#studentContactNumber')?.value,
      collegeName: studentModal.querySelector('#studentCollege')?.value,
      courseName: studentModal.querySelector('#studentCourse')?.value,
      majorName: studentModal.querySelector('#studentMajor')?.value,
      academicYear: studentModal.querySelector('#studentAcademicYear')?.value,
      yearLevel: studentModal.querySelector('#studentYearLevel')?.value,
    };
    const academicSelection = window.DatsScanFlow.resolveAcademicSelection([...collegeData.values()], profile);
    if (academicSelection.error) return showAdminToast(academicSelection.error);
    Object.assign(profile, academicSelection);
    const validationErrors = window.DatsScanFlow.validateNewStudentProfile(profile);
    if (validationErrors.length) return showAdminToast(validationErrors[0]);
    const student = window.DatsScanFlow.buildStudentProfilePayload(profile);
    if (editingStudentId) {
      student.action = 'update';
      student.student_id = editingStudentId;
    }
    const response = await fetch('../api.php?resource=students', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify(student) });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) return showAdminToast(result.error || 'Unable to save student information.');
    studentModal.hidden = true;
    showAdminToast(editingStudentId ? 'Student information updated.' : 'Student information saved.');
    window.location.reload();
  });

  const closeStudentActions = () => {
    document.querySelectorAll('.student-action-menu').forEach((menu) => menu.remove());
  };

  const openDocumentsDialog = async (studentId, studentName) => {
    const backdrop = document.createElement('div');
    backdrop.className = 'student-modal-backdrop document-dialog-backdrop';
    backdrop.innerHTML = `<section class="student-modal document-dialog" role="dialog" aria-modal="true" aria-labelledby="documentDialogTitle"><button class="row-action modal-close" type="button" aria-label="Close uploaded documents"><i data-lucide="x"></i></button><span class="panel-kicker">Uploaded documents</span><h2 id="documentDialogTitle"></h2><p>Documents scanned and attached to this student record.</p><div class="uploaded-document-list"><p class="document-loading">Loading documents...</p></div></section>`;
    document.body.append(backdrop);
    backdrop.querySelector('#documentDialogTitle').textContent = studentName;
    lucide.createIcons();
    const close = () => backdrop.remove();
    backdrop.querySelector('.modal-close').addEventListener('click', close);
    backdrop.addEventListener('click', (event) => { if (event.target === backdrop) close(); });
    const list = backdrop.querySelector('.uploaded-document-list');
    try {
      const response = await fetch(`../api.php?resource=documents&student_id=${encodeURIComponent(studentId)}`, { credentials: 'same-origin' });
      const result = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(result.error || 'Unable to load documents.');
      const documents = result.data || [];
      if (!documents.length) {
        list.innerHTML = '<p class="document-loading">No documents have been uploaded for this student.</p>';
        return;
      }
      list.replaceChildren(...documents.map((record) => {
        const item = document.createElement('div');
        item.className = 'uploaded-document-item';
        const preview = document.createElement('div');
        preview.className = 'document-preview-thumb';
        preview.style.cssText = 'display:grid;place-items:center;width:54px;height:54px;overflow:hidden;color:#6b1d2a;background:#f8efd5;';
        if (record.file_path && record.mime_type?.startsWith('image/')) {
          const image = document.createElement('img');
          image.src = `../${record.file_path}`;
          image.alt = `${record.original_filename} preview`;
          image.style.cssText = 'width:100%;height:100%;object-fit:cover;';
          preview.append(image);
        } else if (record.file_path && record.mime_type === 'application/pdf') {
          const pdf = document.createElement('iframe');
          pdf.src = `../${record.file_path}`;
          pdf.title = `${record.original_filename} preview`;
          pdf.style.cssText = 'width:100%;height:100%;border:0;';
          preview.append(pdf);
        } else {
          preview.innerHTML = '<i data-lucide="file-text"></i>';
        }
        const details = document.createElement('div');
        details.innerHTML = '<strong></strong><small></small>';
        details.querySelector('strong').textContent = record.original_filename;
        details.querySelector('small').textContent = `${record.category_code || 'Document'} · ${record.status} · ${new Date(record.uploaded_at.replace(' ', 'T')).toLocaleString()}`;
        item.append(preview, details);
        if (record.file_path) {
          const view = document.createElement('a');
          view.className = 'view-document-button';
          view.href = `../${record.file_path}`;
          view.target = '_blank';
          view.rel = 'noopener';
          view.textContent = 'Preview';
          item.append(view);
        }
        return item;
      }));
      lucide.createIcons();
    } catch (error) {
      list.innerHTML = '<p class="document-loading"></p>';
      list.querySelector('p').textContent = error.message;
    }
  };

  studentRows?.addEventListener('click', (event) => {
    const actionButton = event.target.closest('.students-table .row-action');
    if (!actionButton) return;
    event.stopPropagation();
    closeStudentActions();
    const selectedStudentRow = actionButton.closest('tr');
    const student = selectedStudentRow?.studentData;
    const studentId = Number(student?.id ?? selectedStudentRow?.dataset.studentId);
    if (!selectedStudentRow || !Number.isInteger(studentId) || studentId < 1) {
      showAdminToast('Unable to open actions for this student.');
      return;
    }
    activeStudentRow = selectedStudentRow;
    const studentName = student ? getStudentName(student) : 'Unnamed student';
    const menu = document.createElement('div');
    menu.className = 'student-action-menu';
    menu.innerHTML = `<button type="button" data-student-action="edit"><i data-lucide="pencil"></i> Edit Student Info</button><button type="button" data-student-action="documents"><i data-lucide="folder-open"></i> View Documents Uploaded</button><button type="button" data-student-action="gradesheet"><i data-lucide="graduation-cap"></i> View Gradesheet</button>`;
    actionButton.closest('td').append(menu);
    lucide.createIcons();
    menu.querySelector('[data-student-action="edit"]').addEventListener('click', () => {
      closeStudentActions();
      if (!student) return showAdminToast('Student data is still loading.');
      editingStudentId = studentId;
      studentModal.querySelector('#studentNumber').value = student.student_number || '';
      studentModal.querySelector('#studentLastName').value = student.last_name || '';
      studentModal.querySelector('#studentFirstName').value = student.first_name || '';
      studentModal.querySelector('#studentMiddleInitial').value = student.middle_initial || '';
      studentModal.querySelector('#studentGenderField').value = student.gender || 'Undisclosed';
      studentModal.querySelector('#studentBirthdate').value = student.birthdate || '';
      studentModal.querySelector('#studentAddress').value = student.address || '';
      studentModal.querySelector('#studentNationality').value = student.nationality || '';
      studentModal.querySelector('#studentCivilStatus').value = student.civil_status || 'Undisclosed';
      studentModal.querySelector('#studentContactNumber').value = student.contact_number || '';
      studentModal.querySelector('#studentCollege').value = student.college_name || '';
      studentModal.querySelector('#studentCollege').dispatchEvent(new Event('input'));
      studentModal.querySelector('#studentCourse').value = student.course_name || '';
      studentModal.querySelector('#studentCourse').dispatchEvent(new Event('input'));
      studentModal.querySelector('#studentMajor').value = student.major_name || '';
      studentModal.querySelector('#studentAcademicYear').value = student.academic_year || '';
      studentModal.querySelector('#studentYearLevel').value = student.year_level || 'Undisclosed';
      studentModal.hidden = false;
      studentModalTitle.textContent = 'Edit Student Info';
      studentModalCopy.textContent = `Update the profile for ${studentName}.`;
    });
    menu.querySelector('[data-student-action="documents"]').addEventListener('click', () => {
      closeStudentActions();
      openDocumentsDialog(studentId, studentName);
    });
    menu.querySelector('[data-student-action="gradesheet"]').addEventListener('click', () => {
      closeStudentActions();
      window.DatsStudentGradesheetModal?.open({
        studentId,
        studentName,
        student: student || null,
      });
    });
  });
  document.addEventListener('click', (event) => {
    if (!event.target.closest('.student-action-menu') && !event.target.closest('.students-table .row-action')) closeStudentActions();
  });
});

