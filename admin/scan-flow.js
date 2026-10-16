(function exposeScanFlow(root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.DatsScanFlow = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, () => {
  const clean = (value) => String(value ?? '').trim();

  const parseStudentDetails = (text = '') => {
    const patterns = {
      firstName: /^(?:first\s+name|given\s+name)\s*[:=]\s*(.*?)\s*$/i,
      middleInitial: /^(?:middle\s+initial|middle\s+name|middle)\s*[:=]\s*(.*?)\s*$/i,
      lastName: /^(?:last\s+name|surname|family\s+name)\s*[:=]\s*(.*?)\s*$/i,
      fullName: /^(?:student\s+name|name)\s*[:=]\s*(.*?)\s*$/i,
      gender: /^gender\s*[:=]\s*(.*?)\s*$/i,
      birthdate: /^(?:birth\s*date|date\s+of\s+birth|dob)\s*[:=]\s*(.*?)\s*$/i,
      academicYear: /^(?:academic\s+year|school\s+year|a\.?\s*y\.?|s\.?\s*y\.?)\s*[:=]\s*(.*?)\s*$/i,
      contactNumber: /^(?:contact(?:\s+number|\s+no\.?)?|mobile(?:\s+number|\s+no\.?)?|phone(?:\s+number|\s+no\.?)?|telephone|tel\.?)\s*[:=]\s*(.*?)\s*$/i,
      college: /^college(?:\s+name)?\s*[:=]\s*(.*?)\s*$/i,
      course: /^(?:program\s*(?:\/|and)\s*course|program|course)\s*[:=]\s*(.*?)\s*$/i,
      major: /^major\s*[:=]\s*(.*?)\s*$/i,
      yearLevel: /^(?:year\s+level|year|level)\s*[:=]\s*(.*?)\s*$/i,
    };
    const candidates = Object.fromEntries(Object.keys(patterns).map((key) => [key, new Set()]));
    String(text ?? '').split(/\r?\n/).forEach((line) => {
      Object.entries(patterns).some(([key, pattern]) => {
        const match = line.match(pattern);
        const value = clean(match?.[1]);
        if (value) candidates[key].add(value);
        return Boolean(match);
      });
    });
    const uniqueValue = (key) => candidates[key].size === 1 ? [...candidates[key]][0] : '';
    const details = {};
    const namePattern = /^[\p{L}][\p{L} .'-]*$/u;

    const fullName = uniqueValue('fullName');
    if (fullName && fullName.length <= 180 && /^[\p{L}][\p{L} ,.'-]*$/u.test(fullName)) {
      const nameParts = fullName.split(',').map((part) => clean(part)).filter(Boolean);
      if (nameParts.length === 2) {
        const givenParts = nameParts[1].split(/\s+/);
        details.firstName = givenParts[0];
        details.lastName = nameParts[0];
        if (givenParts[1] && givenParts[1].length <= 10) details.middleInitial = givenParts[1];
      } else if (nameParts.length === 1) {
        const tokens = nameParts[0].split(/\s+/);
        if (tokens.length >= 2 && tokens.length <= 5) {
          details.firstName = tokens[0];
          if (/^[\p{L}]\.?$/u.test(tokens[1]) && tokens.length >= 3) {
            details.middleInitial = tokens[1];
            details.lastName = tokens.slice(2).join(' ');
          } else {
            details.lastName = tokens.slice(1).join(' ');
          }
        }
      }
    }
    ['firstName', 'middleInitial', 'lastName'].forEach((key) => {
      const value = uniqueValue(key);
      if (value && value.length <= (key === 'middleInitial' ? 10 : 80) && namePattern.test(value)) details[key] = value;
    });

    const gender = uniqueValue('gender');
    if (/^female$/i.test(gender)) details.gender = 'Female';
    else if (/^male$/i.test(gender)) details.gender = 'Male';
    else if (/^other$/i.test(gender)) details.gender = 'Other';

    const birthdate = uniqueValue('birthdate');
    let dateParts = birthdate.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!dateParts && /^[A-Za-z]+\s+\d{1,2},?\s+\d{4}$/.test(birthdate)) {
      const parsed = new Date(birthdate);
      if (!Number.isNaN(parsed.getTime())) dateParts = [birthdate, String(parsed.getFullYear()), String(parsed.getMonth() + 1).padStart(2, '0'), String(parsed.getDate()).padStart(2, '0')];
    }
    if (dateParts) {
      const [, year, month, day] = dateParts;
      const parsed = new Date(Date.UTC(Number(year), Number(month) - 1, Number(day)));
      if (parsed.getUTCFullYear() === Number(year) && parsed.getUTCMonth() + 1 === Number(month) && parsed.getUTCDate() === Number(day)) {
        details.birthdate = `${year}-${month}-${day}`;
      }
    }

    const academicYear = uniqueValue('academicYear');
    const yearMatch = academicYear.match(/^(20\d{2})(?:\s*[-–—]\s*(20\d{2}))?$/);
    if (yearMatch && (!yearMatch[2] || Number(yearMatch[2]) === Number(yearMatch[1]) + 1)
      && Number(yearMatch[1]) >= 1901 && Number(yearMatch[1]) <= 2155) {
      details.academicYear = yearMatch[1];
    }

    const contactNumber = uniqueValue('contactNumber');
    const contactDigits = contactNumber.replace(/\D/g, '');
    if (contactNumber.length <= 30 && /^[+\d().\-\s]+$/.test(contactNumber) && contactDigits.length >= 7 && contactDigits.length <= 15) {
      details.contactNumber = contactNumber;
    }

    ['college', 'course', 'major'].forEach((key) => {
      const value = uniqueValue(key);
      const maxLength = key === 'course' ? 180 : key === 'college' ? 150 : 150;
      if (value && value.length <= maxLength) details[key] = value;
    });

    const rawYearLevel = uniqueValue('yearLevel').toLowerCase().replace(/\s+/g, ' ').trim();
    const numericYearLevel = rawYearLevel.match(/^([1-5])(?:st|nd|rd|th)?(?:\s+year)?$/);
    const yearLevel = numericYearLevel ? `${numericYearLevel[1]}${['th', 'st', 'nd', 'rd', 'th', 'th'][Number(numericYearLevel[1])]} year` : rawYearLevel;
    const yearLevels = {
      '1st year': '1st Year', 'first year': '1st Year',
      '2nd year': '2nd Year', 'second year': '2nd Year',
      '3rd year': '3rd Year', 'third year': '3rd Year',
      '4th year': '4th Year', 'fourth year': '4th Year',
      '5th year': '5th Year', 'fifth year': '5th Year',
      'senior high school': 'Senior High School', graduate: 'Graduate',
      'high school': 'High School', elementary: 'Elementary',
      'pre-baccalaureate': 'Pre-Baccalaureate',
    };
    if (yearLevels[yearLevel]) details.yearLevel = yearLevels[yearLevel];

    return details;
  };

  const normalizeSearchText = (value) => clean(value)
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/\s+/g, ' ')
    .toLowerCase();

  const documentCategoryHints = [
    { code: 'TOR', name: 'Transcript of Records', keywords: ['transcript', 'grades', 'gpa', 'record of grades', 'academic record'] },
    { code: 'COR', name: 'Certificate of Registration', keywords: ['certificate of registration', 'registration', 'subjects', 'registered', 'enrolled'] },
    { code: 'COE', name: 'Certificate of Enrollment', keywords: ['certificate of enrollment', 'enrollment', 'student status', 'currently enrolled', 'enrolled'] },
    { code: 'COG', name: 'Certificate of Graduation', keywords: ['certificate of graduation', 'graduated', 'graduation', 'completed', 'degree'] },
    { code: 'GMC', name: 'Good Moral Certificate', keywords: ['good moral', 'moral character', 'conduct', 'good standing'] },
    { code: 'DIP', name: 'Diploma', keywords: ['diploma', 'degree award', 'completion'] },
    { code: 'ACR', name: 'Academic Clearance', keywords: ['clearance', 'academic clearance', 'clearance certificate'] },
    { code: 'MC', name: 'Medical Certificate', keywords: ['medical certificate', 'medical', 'physician', 'diagnosis', 'sick', 'medical examination'] },
    { code: 'GRD', name: 'Grade Sheet', keywords: ['grade sheet', 'gradesheet', 'grading sheet', 'subject code', 'final grade', 'units earned'] },
  ];

  const categoryHint = (category) => documentCategoryHints.find((hint) => (
    clean(category?.code).toUpperCase() === hint.code
    || normalizeSearchText(category?.name) === normalizeSearchText(hint.name)
  ));

  const categoryNameTokens = (name) => normalizeSearchText(name)
    .split(' ')
    .filter((token) => token.length >= 4 && !['certificate', 'records', 'record', 'official'].includes(token));

  const inferDocumentCategory = (text = '', categories = []) => {
    const cleaned = normalizeSearchText(text);
    if (!cleaned) return null;
    const candidates = (categories || []).filter((category) => Number(category?.id) > 0);
    const scored = candidates.map((category) => {
      const name = normalizeSearchText(category.name);
      const hint = categoryHint(category);
      let score = name && cleaned.includes(name) ? 100 + name.length : 0;
      (hint?.keywords || []).forEach((keyword) => {
        if (cleaned.includes(normalizeSearchText(keyword))) score += keyword.includes(' ') ? 8 : 4;
      });
      categoryNameTokens(category.name).forEach((token) => {
        if (cleaned.includes(token)) score += token.length >= 8 ? 3 : 2;
      });
      return { category, score };
    }).sort((left, right) => right.score - left.score);
    const topScore = scored[0]?.score || 0;
    const secondScore = scored[1]?.score || 0;
    const hasExactMatch = topScore >= 100;
    if (!scored.length || topScore < 6 || (!hasExactMatch && secondScore > 0 && topScore - secondScore <= 7)) return null;
    return scored[0].category;
  };

  const studentSearchText = (student) => normalizeSearchText([
    student.student_number,
    student.first_name,
    student.middle_initial,
    student.last_name,
    student.academic_year,
    student.college_name,
    student.course_name,
    student.major_name,
  ].filter(Boolean).join(' '));

  const filterStudents = (students, query, limit = 8) => {
    const needle = normalizeSearchText(query);
    if (!needle) return (students || []).slice(0, limit);
    const terms = needle.split(' ').filter(Boolean);
    return (students || []).filter((student) => {
      const haystack = studentSearchText(student);
      return terms.every((term) => haystack.includes(term));
    }).slice(0, limit);
  };

  const getExistingStudentSelection = (student) => ({
    studentId: Number(student?.id || 0),
    academicYear: clean(student?.academic_year),
  });

  const resolveDetectedCategory = (categories, detected) => {
    if (!detected) return '';
    const detectedId = Number(detected.id || 0);
    if (detectedId > 0) {
      const idMatch = (categories || []).find((category) => Number(category.id) === detectedId);
      if (idMatch) return String(idMatch.id);
    }
    const detectedCode = clean(detected?.code).toUpperCase();
    const detectedName = normalizeSearchText(detected?.name);
    const match = (categories || []).find((category) => (
      clean(category.code).toUpperCase() === detectedCode
      || normalizeSearchText(category.name) === detectedName
    ));
    return match ? String(match.id || match.code || '') : '';
  };

  const buildStudentPayload = (mode, values = {}) => {
    if (mode === 'existing') {
      return { student_mode: 'existing', student_id: String(Number(values.studentId || 0)) };
    }
    const payload = {
      student_mode: 'new',
      student_number: clean(values.studentNumber),
      first_name: clean(values.firstName),
      middle_initial: clean(values.middleInitial),
      last_name: clean(values.lastName),
      nationality: clean(values.nationality),
      gender: clean(values.gender) || 'Undisclosed',
      birthdate: clean(values.birthdate),
      address: clean(values.address),
      civil_status: clean(values.civilStatus) || 'Undisclosed',
      contact_number: clean(values.contactNumber),
      college_id: clean(values.collegeId),
      course_id: clean(values.courseId),
      major_id: clean(values.majorId),
      academic_year: clean(values.academicYear),
      year_level: clean(values.yearLevel) || 'Undisclosed',
    };
    [['college_name', values.collegeName], ['course_name', values.courseName], ['major_name', values.majorName]]
      .forEach(([key, value]) => { if (clean(value)) payload[key] = clean(value); });
    return payload;
  };

  const buildStudentProfilePayload = (values = {}) => {
    const { student_mode: ignoredMode, ...profile } = buildStudentPayload('new', values);
    return profile;
  };

  const switchStudentMode = (state, nextMode) => {
    const next = { ...state, mode: nextMode };
    if (nextMode === 'new') {
      next.studentId = null;
      next.academicYear = '';
    } else {
      next.newStudent = {};
    }
    return next;
  };

  const validYear = (value) => /^\d{4}$/.test(clean(value))
    && Number(value) >= 1901 && Number(value) <= 2155;

  const validateNewStudentProfile = (student = {}) => {
    const errors = [];
    if (!clean(student.studentNumber)) errors.push('Enter the student number.');
    if (!clean(student.firstName)) errors.push('Enter the first name.');
    if (!clean(student.lastName)) errors.push('Enter the last name.');
    if (!validYear(student.academicYear)) errors.push('Enter a valid four-digit academic year.');
    return errors;
  };

  const validateScanSubmission = (state = {}) => {
    const errors = [];
    if (!state.file) errors.push('Upload a document file.');
    if (!clean(state.categoryCode)) errors.push('Select a document type.');
    if (state.mode === 'new') {
      errors.push(...validateNewStudentProfile(state.newStudent));
    } else {
      if (Number(state.studentId || 0) < 1) errors.push('Select an existing student from the suggestions.');
      if (!validYear(state.academicYear)) errors.push('The selected student needs a valid academic year.');
    }
    return errors;
  };

  const categoryForReplacement = (categoryCode, wasDetectedFromFile) => (
    wasDetectedFromFile ? '' : clean(categoryCode)
  );

  const isCurrentOcrResult = (activeFileKey, resultFileKey, activeToken, resultToken) => (
    Boolean(activeFileKey) && activeFileKey === resultFileKey && activeToken === resultToken
  );

  const mergeStudentReferences = (current, refreshed) => {
    const byId = new Map((current || []).map((student) => [String(student.id), student]));
    (refreshed || []).forEach((student) => byId.set(String(student.id), student));
    return [...byId.values()];
  };

  const resolveAcademicSelection = (colleges, values = {}) => {
    const collegeName = normalizeSearchText(values.collegeName);
    const courseName = normalizeSearchText(values.courseName);
    const majorName = normalizeSearchText(values.majorName);
    const empty = { collegeId: '', courseId: '', majorId: '', error: '' };
    if (!collegeName && !courseName && !majorName) return empty;
    if (!collegeName) return { ...empty, error: 'Select or enter a valid college before the course or major.' };
    const college = (colleges || []).find((item) => normalizeSearchText(item.name) === collegeName);
    const result = { ...empty, collegeId: college ? String(college.id) : '' };
    if (!courseName && !majorName) return result;
    if (!courseName) return { ...result, error: 'Select or enter a valid course before the major.' };
    if (!college) return result;
    const course = (college.courses || []).find((item) => normalizeSearchText(item.name) === courseName);
    if (!course) return result;
    result.courseId = String(course.id);
    if (!majorName) return result;
    const major = (course.majors || []).find((item) => normalizeSearchText(item.name) === majorName);
    if (!major) return result;
    result.majorId = String(major.id);
    return result;
  };

  return {
    parseStudentDetails,
    normalizeSearchText,
    filterStudents,
    getExistingStudentSelection,
    inferDocumentCategory,
    resolveDetectedCategory,
    buildStudentPayload,
    buildStudentProfilePayload,
    switchStudentMode,
    validateScanSubmission,
    validateNewStudentProfile,
    categoryForReplacement,
    isCurrentOcrResult,
    mergeStudentReferences,
    resolveAcademicSelection,
  };
}));
