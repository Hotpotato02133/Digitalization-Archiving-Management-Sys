const test = require('node:test');
const assert = require('node:assert/strict');
const flow = require('../../admin/scan-flow.js');

const students = [{
  id: 14,
  student_number: '2026-0014',
  first_name: 'Ana',
  middle_initial: 'M',
  last_name: 'Reyes',
  academic_year: '2026',
  college_name: 'College of Computing',
  course_name: 'BS Information Technology',
}];

test('labeled OCR extracts available student details and normalizes academic year', () => {
  assert.deepEqual(flow.parseStudentDetails([
    'Last Name: Dela Cruz',
    'First Name: Juan',
    'Middle Initial: P.',
    'Gender: Male',
    'Birthdate: January 15, 2004',
    'Academic Year: 2026–2027',
    'Contact Number: 09123456789',
    'College: College of Information and Computing Sciences',
    'Program: BS Information Technology',
    'Year Level: 4th Year',
  ].join('\n')), {
    firstName: 'Juan', middleInitial: 'P.', lastName: 'Dela Cruz', gender: 'Male',
    birthdate: '2004-01-15', academicYear: '2026', contactNumber: '09123456789',
    college: 'College of Information and Computing Sciences',
    course: 'BS Information Technology', yearLevel: '4th Year',
  });
});

test('unlabeled, invalid, or conflicting OCR values are not suggested', () => {
  assert.deepEqual(flow.parseStudentDetails([
    'Juan Cruz',
    'First Name: Juan',
    'First Name: Pedro',
    'Gender: M',
    'Birthdate: 02/30/2004',
    'Academic Year: 2026-2028',
    'Contact Number: 123',
    'Year Level: 6th Year',
  ].join('\n')), {});
});

test('missing OCR student fields remain absent and labeled dates validate', () => {
  assert.deepEqual(flow.parseStudentDetails([
    'Last Name: Santos',
    'Date of Birth: 2004-02-29',
    'Course: BS Nursing',
  ].join('\n')), {
    lastName: 'Santos', birthdate: '2004-02-29', course: 'BS Nursing',
  });
  assert.deepEqual(flow.parseStudentDetails('Birthdate: 2003-02-29'), {});
});

test('composite names and alternate academic labels populate available fields', () => {
  assert.deepEqual(flow.parseStudentDetails([
    'Name: Juan Dela Cruz',
    'Gender: Male',
    'Birthdate: January 10, 2004',
    'Program/Course: BS Information Technology',
    'Year: 3',
  ].join('\n')), {
    firstName: 'Juan', lastName: 'Dela Cruz', gender: 'Male',
    birthdate: '2004-01-10', course: 'BS Information Technology', yearLevel: '3rd Year',
  });

  assert.deepEqual(flow.parseStudentDetails([
    'Student Name: Dela Cruz, Juan P.',
    'Course: BSIT',
    'Level: Third Year',
  ].join('\n')), {
    firstName: 'Juan', middleInitial: 'P.', lastName: 'Dela Cruz',
    course: 'BSIT', yearLevel: '3rd Year',
  });
});

test('student search matches number, name, school metadata, and academic year', () => {
  for (const query of ['0014', 'ana reyes', 'computing', 'information technology', '2026']) {
    assert.deepEqual(flow.filterStudents(students, query, 8).map((student) => student.id), [14]);
  }
});

test('existing selection carries the immutable id and stored academic year', () => {
  assert.deepEqual(flow.getExistingStudentSelection(students[0]), { studentId: 14, academicYear: '2026' });
});

test('detected category resolves only to an active API category', () => {
  const categories = [{ id: 7, code: 'TOR', name: 'Transcript of Records' }];
  assert.equal(flow.resolveDetectedCategory(categories, { id: 7, code: 'TOR', name: 'Transcript of Records' }), '7');
  assert.equal(flow.resolveDetectedCategory(categories, { code: 'TOR', name: 'Transcript of Records' }), '7');
  assert.equal(flow.resolveDetectedCategory([{ code: 'TOR', name: 'Transcript of Records' }], { code: 'TOR', name: 'Transcript of Records' }), 'TOR');
  assert.equal(flow.resolveDetectedCategory(categories, { code: 'UNKNOWN', name: 'Unknown' }), '');
});

test('OCR content selects a strong matching active category', () => {
  const categories = [
    { id: 3, code: 'COR', name: 'Certificate of Registration' },
    { id: 8, code: '', name: 'Medical Certificate' },
  ];
  assert.equal(flow.inferDocumentCategory('Medical diagnosis from physician', categories).id, 8);
  assert.equal(flow.inferDocumentCategory('Certificate of Registration and enrolled subjects', categories).id, 3);
});

test('OCR leaves the category unset for weak or ambiguous content', () => {
  const categories = [
    { id: 3, code: 'COR', name: 'Certificate of Registration' },
    { id: 4, code: 'COE', name: 'Certificate of Enrollment' },
  ];
  assert.equal(flow.inferDocumentCategory('This is an official document', categories), null);
  assert.equal(flow.inferDocumentCategory('Certificate', categories), null);
  assert.equal(flow.inferDocumentCategory('enrolled registration', categories), null);
});

test('OCR matching uses the exact available category name for new types', () => {
  const categories = [{ id: 12, code: '', name: 'Certified True Copy' }];
  assert.equal(flow.inferDocumentCategory('CERTIFIED TRUE COPY', categories).id, 12);
});

test('existing student payload excludes editable identity fields', () => {
  assert.deepEqual(flow.buildStudentPayload('existing', { studentId: 14 }), {
    student_mode: 'existing', student_id: '14',
  });
});

test('new student payload trims and maps all supported profile fields', () => {
  const payload = flow.buildStudentPayload('new', {
    studentNumber: ' 2026-0042 ', firstName: ' Ada ', lastName: ' Cruz ',
    middleInitial: ' Q ', nationality: ' Filipino ', academicYear: '2026',
    gender: 'Undisclosed', birthdate: '', address: ' City ', civilStatus: 'Undisclosed',
    contactNumber: '', collegeId: '2', courseId: '5', majorId: '', yearLevel: '1st Year',
  });
  assert.equal(payload.student_number, '2026-0042');
  assert.equal(payload.nationality, 'Filipino');
  assert.equal(payload.college_id, '2');
  assert.equal(payload.student_id, undefined);
});

test('switching student modes clears hidden identity and preserves document state', () => {
  const state = flow.switchStudentMode({
    mode: 'existing', studentId: 14, academicYear: '2026',
    newStudent: { studentNumber: 'draft' }, categoryCode: 'TOR', file: { name: 'tor.pdf' },
  }, 'new');
  assert.equal(state.studentId, null);
  assert.equal(state.academicYear, '');
  assert.equal(state.categoryCode, 'TOR');
  assert.equal(state.file.name, 'tor.pdf');
});

test('submission validation requires identity for the active mode', () => {
  assert.match(flow.validateScanSubmission({
    mode: 'existing', studentId: null, academicYear: '', categoryCode: 'TOR', file: {},
  }).join(' '), /select an existing student/i);
  assert.match(flow.validateScanSubmission({
    mode: 'new', newStudent: { studentNumber: '', firstName: '', lastName: '', academicYear: '' },
    categoryCode: 'TOR', file: {},
  }).join(' '), /student number/i);
});

test('submission validation accepts complete existing and new student states', () => {
  assert.deepEqual(flow.validateScanSubmission({
    mode: 'existing', studentId: 14, academicYear: '2026', categoryCode: 'TOR', file: {},
  }), []);
  assert.deepEqual(flow.validateScanSubmission({
    mode: 'new', newStudent: { studentNumber: '2026-1', firstName: 'Ana', lastName: 'Reyes', academicYear: '2026' },
    categoryCode: 'TOR', file: {},
  }), []);
  assert.deepEqual(flow.validateScanSubmission({
    mode: 'existing', studentId: 14, academicYear: '2100', categoryCode: 'TOR', file: {},
  }), []);
  for (const academicYear of ['1999', '2101']) {
    assert.deepEqual(flow.validateScanSubmission({
      mode: 'new', newStudent: { studentNumber: '2026-1', firstName: 'Ana', lastName: 'Reyes', academicYear },
      categoryCode: 'TOR', file: {},
    }), []);
  }
});

test('new student profile validation is reusable outside file scanning', () => {
  assert.deepEqual(flow.validateNewStudentProfile({
    studentNumber: '2026-1', firstName: 'Ana', lastName: 'Reyes', academicYear: '2026',
  }), []);
  assert.match(flow.validateNewStudentProfile({
    studentNumber: '', firstName: '', lastName: '', academicYear: '2026-27',
  }).join(' '), /student number/i);
  assert.match(flow.validateNewStudentProfile({
    studentNumber: '1', firstName: 'Ana', lastName: 'Reyes', academicYear: '1900',
  }).join(' '), /valid four-digit academic year/i);
});

test('student profile serialization preserves hierarchy ids and nationality', () => {
  assert.deepEqual(flow.buildStudentProfilePayload({
    studentNumber: '2026-0010', firstName: 'Lin', middleInitial: '', lastName: 'Santos',
    nationality: ' Filipino ', gender: 'Undisclosed', birthdate: '', address: '',
    civilStatus: 'Undisclosed', contactNumber: '', collegeId: '2', courseId: '5',
    majorId: '7', academicYear: '2026', yearLevel: '1st Year',
  }), {
    student_number: '2026-0010', first_name: 'Lin', middle_initial: '', last_name: 'Santos',
    nationality: 'Filipino', gender: 'Undisclosed', birthdate: '', address: '',
    civil_status: 'Undisclosed', contact_number: '', college_id: '2', course_id: '5',
    major_id: '7', academic_year: '2026', year_level: '1st Year',
  });
});

test('replacing a file clears only a category derived from the previous file', () => {
  assert.equal(flow.categoryForReplacement('TOR', true), '');
  assert.equal(flow.categoryForReplacement('TOR', false), 'TOR');
});

test('OCR results apply only to the active file request', () => {
  assert.equal(flow.isCurrentOcrResult('b.pdf:20', 'b.pdf:20', 4, 4), true);
  assert.equal(flow.isCurrentOcrResult('b.pdf:20', 'a.pdf:10', 4, 3), false);
  assert.equal(flow.isCurrentOcrResult(null, 'b.pdf:20', 5, 4), false);
});

test('refreshed student references add new students and replace stale rows by id', () => {
  const merged = flow.mergeStudentReferences(
    [{ id: 1, first_name: 'Old' }],
    [{ id: 1, first_name: 'Updated' }, { id: 2, first_name: 'New' }],
  );
  assert.deepEqual(merged.map((student) => [student.id, student.first_name]), [[1, 'Updated'], [2, 'New']]);
});

const colleges = [{
  id: 2,
  name: 'College of Computing',
  courses: [{
    id: 5,
    name: 'BS Information Technology',
    majors: [{ id: 7, name: 'Web and Mobile Development' }],
  }],
}];

test('typed academic names resolve case-insensitively to hierarchy ids', () => {
  assert.deepEqual(flow.resolveAcademicSelection(colleges, {
    collegeName: ' college of computing ',
    courseName: 'bs information technology',
    majorName: 'WEB AND MOBILE DEVELOPMENT',
  }), { collegeId: '2', courseId: '5', majorId: '7', error: '' });
});

test('typed academic names remain available for backend creation', () => {
  assert.match(flow.resolveAcademicSelection(colleges, {
    collegeName: 'College of Computing', courseName: 'BS Nursing', majorName: '',
  }).error, /^$/);
  assert.match(flow.resolveAcademicSelection(colleges, {
    collegeName: 'New College', courseName: 'New Course', majorName: 'New Major',
  }).error, /^$/);
});

test('blank academic inputs remain optional', () => {
  assert.deepEqual(flow.resolveAcademicSelection(colleges, {
    collegeName: '', courseName: '', majorName: '',
  }), { collegeId: '', courseId: '', majorId: '', error: '' });
});

test('normalizeRotation normalizes arbitrary degrees into 0, 90, 180, or 270', () => {
  assert.equal(flow.normalizeRotation(0), 0);
  assert.equal(flow.normalizeRotation(90), 90);
  assert.equal(flow.normalizeRotation(180), 180);
  assert.equal(flow.normalizeRotation(270), 270);
  assert.equal(flow.normalizeRotation(360), 0);
  assert.equal(flow.normalizeRotation(-90), 270);
  assert.equal(flow.normalizeRotation(-180), 180);
  assert.equal(flow.normalizeRotation(450), 90);
  assert.equal(flow.normalizeRotation(268), 270);
});

test('getOrientationCandidates prioritizes initial rotation and 270-degree CCW correction', () => {
  assert.deepEqual(flow.getOrientationCandidates(0), [0, 270, 180, 90]);
  assert.deepEqual(flow.getOrientationCandidates(270), [270, 180, 90, 0]);
  assert.deepEqual(flow.getOrientationCandidates(180), [180, 90, 0, 270]);
});

test('calculateEffectiveRotation combines base and manual rotations modulo 360', () => {
  assert.equal(flow.calculateEffectiveRotation(180, 0), 180);
  assert.equal(flow.calculateEffectiveRotation(180, 90), 270);
  assert.equal(flow.calculateEffectiveRotation(180, 270), 90);
  assert.equal(flow.calculateEffectiveRotation(270, 90), 0);
});

test('scoreOcrTextForOrientation and decisive score distinguish upright vs sideways text', () => {
  const uprightText = 'REPORT OF RATING 1st Semester SY 2014-2015 Course No.: MATH 112 Description: CALCULUS WITH ANALYTIC GEOMETRY ZPPSU College of Science';
  const sidewaysText = 'a. v; 4, .. \n ~ -- 1! /';

  const uprightScore = flow.scoreOcrTextForOrientation(uprightText);
  const sidewaysScore = flow.scoreOcrTextForOrientation(sidewaysText);

  assert.ok(uprightScore.score >= 50, `Expected score >= 50, got ${uprightScore.score}`);
  assert.ok(uprightScore.keywordMatches >= 5, `Expected keywords >= 5, got ${uprightScore.keywordMatches}`);
  assert.equal(flow.isDecisiveOrientationScore(uprightScore), true);

  assert.ok(sidewaysScore.score < 10, `Expected score < 10, got ${sidewaysScore.score}`);
  assert.equal(sidewaysScore.keywordMatches, 0);
  assert.equal(flow.isDecisiveOrientationScore(sidewaysScore), false);
});

test('chooseBestOrientationCandidate selects highest score or falls back to default', () => {
  const candidates = [
    { rotation: 270, score: 0 },
    { rotation: 180, score: 185 },
    { rotation: 90, score: 2 },
    { rotation: 0, score: 4 },
  ];
  assert.equal(flow.chooseBestOrientationCandidate(candidates, 270), 180);
  assert.equal(flow.chooseBestOrientationCandidate([], 270), 270);
  assert.equal(flow.chooseBestOrientationCandidate([{ rotation: 0, score: 0 }], 90), 90);
});

test('calculateProjectionVariance flags vertical text lines as sideways', () => {
  const width = 100;
  const height = 100;

  // Mock vertical lines (columns with dark pixels, others white): simulates text lines rotated 90 degrees
  const verticalData = new Uint8ClampedArray(width * height * 4);
  verticalData.fill(255); // white
  for (let y = 0; y < height; y += 1) {
    for (let x = 0; x < width; x += 10) {
      const idx = (y * width + x) * 4;
      verticalData[idx] = 0;
      verticalData[idx + 1] = 0;
      verticalData[idx + 2] = 0;
    }
  }
  const vertResult = flow.calculateProjectionVariance({ width, height, data: verticalData });
  assert.equal(vertResult.isSideways, true);

  // Mock horizontal lines (rows with dark pixels): simulates normal upright horizontal text lines
  const horizontalData = new Uint8ClampedArray(width * height * 4);
  horizontalData.fill(255);
  for (let y = 0; y < height; y += 10) {
    for (let x = 0; x < width; x += 1) {
      const idx = (y * width + x) * 4;
      horizontalData[idx] = 0;
      horizontalData[idx + 1] = 0;
      horizontalData[idx + 2] = 0;
    }
  }
  const horizResult = flow.calculateProjectionVariance({ width, height, data: horizontalData });
  assert.equal(horizResult.isSideways, false);
});

test('splitFullName splits "Last, First MI." and "First MI. Last" forms', () => {
  assert.deepEqual(flow.splitFullName('Dela Cruz, Juan P.'), {
    firstName: 'Juan', middleInitial: 'P.', lastName: 'Dela Cruz',
  });
  assert.deepEqual(flow.splitFullName('Juan Dela Cruz'), { firstName: 'Juan', lastName: 'Dela Cruz' });
  assert.deepEqual(flow.splitFullName('Juan P. Dela Cruz'), {
    firstName: 'Juan', middleInitial: 'P.', lastName: 'Dela Cruz',
  });
  assert.deepEqual(flow.splitFullName(''), {});
  assert.deepEqual(flow.splitFullName('J0hn Cruz3'), {});
});

test('normalizeYearLevel maps numeric, ordinal, and special year-level phrases', () => {
  assert.equal(flow.normalizeYearLevel('3'), '3rd Year');
  assert.equal(flow.normalizeYearLevel('3rd'), '3rd Year');
  assert.equal(flow.normalizeYearLevel('3rd year'), '3rd Year');
  assert.equal(flow.normalizeYearLevel('third year'), '3rd Year');
  assert.equal(flow.normalizeYearLevel('Graduate'), 'Graduate');
  assert.equal(flow.normalizeYearLevel('6th year'), '');
  assert.equal(flow.normalizeYearLevel(''), '');
});

test('detectAcademicYearFromText prefers a range, then a labeled year, then a bare year', () => {
  assert.equal(flow.detectAcademicYearFromText('for school year 2025-2026'), '2025');
  assert.equal(flow.detectAcademicYearFromText('A.Y. 2025'), '2025');
  assert.equal(flow.detectAcademicYearFromText('issued in 2025 for reference'), '2025');
  assert.equal(flow.detectAcademicYearFromText('no year mentioned here'), '');
});

test('parseCertificateOfEnrollmentDetails extracts fields from certify-that prose', () => {
  const text = 'Republic of the Philippines\nZamboanga Peninsula Polytechnic State University\n'
    + 'CERTIFICATE OF ENROLLMENT\n\n'
    + 'This is to certify that DELA CRUZ, Juan P., with Student No. 2026-001234, '
    + 'is currently enrolled in the degree program of BS Information Technology, major in Web and Mobile Development, '
    + '3rd year level, for the Academic Year 2025-2026.';
  assert.deepEqual(flow.parseCertificateOfEnrollmentDetails(text), {
    studentNumber: '2026-001234',
    firstName: 'Juan', middleInitial: 'P.', lastName: 'Dela Cruz',
    course: 'BS Information Technology',
    major: 'Web and Mobile Development',
    yearLevel: '3rd Year',
    academicYear: '2025',
  });
});

test('parseCertificateOfEnrollmentDetails handles first-name-first order and a bare student number', () => {
  const text = 'This is to certify that Ana M. Reyes is a bonafide student of this University, '
    + 'ID No. 2024-00987, currently enrolled in BS Nursing, 1st year, S.Y. 2024-2025.';
  const details = flow.parseCertificateOfEnrollmentDetails(text);
  assert.equal(details.studentNumber, '2024-00987');
  assert.equal(details.firstName, 'Ana');
  assert.equal(details.middleInitial, 'M.');
  assert.equal(details.lastName, 'Reyes');
  assert.equal(details.course, 'BS Nursing');
  assert.equal(details.yearLevel, '1st Year');
  assert.equal(details.academicYear, '2024');
});

test('parseCertificateOfEnrollmentDetails returns an empty object for unrelated text', () => {
  assert.deepEqual(flow.parseCertificateOfEnrollmentDetails('A document with no certificate phrasing at all.'), {});
  assert.deepEqual(flow.parseCertificateOfEnrollmentDetails(''), {});
});

