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
  if (window.lucide) lucide.createIcons();

  const mobileMenu = document.querySelector('#mobileMenu');
  const sidebar = document.querySelector('#sidebar');
  const toast = document.querySelector('#adminToast');
  const showToast = (message) => {
    toast.textContent = message;
    toast.classList.add('show');
    window.setTimeout(() => toast.classList.remove('show'), 3200);
  };

  mobileMenu?.addEventListener('click', () => {
    const isOpen = mobileMenu.getAttribute('aria-expanded') === 'true';
    mobileMenu.setAttribute('aria-expanded', String(!isOpen));
    sidebar.classList.toggle('open', !isOpen);
  });

  document.querySelector('#scanButton')?.addEventListener('click', () => { window.location.href = 'scan.php'; });
  const taskList = document.querySelector('.task-panel .task-list');
  taskList?.addEventListener('click', (event) => {
    const task = event.target.closest('[data-task]');
    if (!task) return;
    showToast(`${task.dataset.task} is ready for review.`);
  });
  document.querySelectorAll('[data-action]').forEach((action) => action.addEventListener('click', () => {
    const actionRoutes = { scan: 'scan.php', upload: 'scan.php', help: 'mailto:registrar@zppsu.edu.ph?subject=Workflow%20help' };
    window.location.href = actionRoutes[action.dataset.action];
  }));

  const studentModal = document.querySelector('#studentModal');
  const addStudentButton = document.querySelector('#addStudentButton');
  const saveStudentButton = document.querySelector('#saveStudentButton');
  const closeStudentModal = document.querySelector('#closeStudentModal');
  const studentCollegeSelect = document.querySelector('#studentCollegeSelect');
  const studentCourseSelect = document.querySelector('#studentCourseSelect');
  const studentMajorSelect = document.querySelector('#studentMajorSelect');
  const collegeData = new Map();

  const populateStudentCourses = () => {
    const college = collegeData.get(studentCollegeSelect?.value);
    studentCourseSelect.replaceChildren(new Option(college ? 'Select course' : 'Select college first', ''));
    studentCourseSelect.disabled = !college;
    studentMajorSelect.replaceChildren(new Option('Select course first', ''));
    studentMajorSelect.disabled = true;
    (college?.courses || []).forEach((course) => studentCourseSelect.add(new Option(course.name, course.id)));
  };

  const populateStudentMajors = () => {
    const college = collegeData.get(studentCollegeSelect?.value);
    const course = college?.courses?.find((item) => String(item.id) === studentCourseSelect?.value);
    studentMajorSelect.replaceChildren(new Option(course?.majors?.length ? 'Select major' : 'No majors for this course', ''));
    studentMajorSelect.disabled = !course?.majors?.length;
    (course?.majors || []).forEach((major) => studentMajorSelect.add(new Option(`Major in ${major.name}`, major.id)));
  };

  if (studentCollegeSelect) {
    fetch('../api.php?resource=colleges', { credentials: 'same-origin' }).then((response) => response.ok ? response.json() : null).then((result) => {
      (result?.data || []).forEach((college) => {
        collegeData.set(college.name, college);
        studentCollegeSelect.add(new Option(college.name, college.name));
      });
    });
    studentCollegeSelect.addEventListener('change', populateStudentCourses);
    studentCourseSelect.addEventListener('change', populateStudentMajors);
  }

  addStudentButton?.addEventListener('click', () => { studentModal.hidden = false; });
  closeStudentModal?.addEventListener('click', () => { studentModal.hidden = true; });
  studentModal?.addEventListener('click', (event) => { if (event.target === studentModal) studentModal.hidden = true; });
  saveStudentButton?.addEventListener('click', async () => {
    const fields = [...studentModal.querySelectorAll('.student-form-grid:not(.academic-fields) input, .student-form-grid:not(.academic-fields) select')];
    const college = collegeData.get(studentCollegeSelect.value);
    const course = college?.courses?.find((item) => String(item.id) === studentCourseSelect.value);
    const student = { student_number: fields[0]?.value, last_name: fields[1]?.value, first_name: fields[2]?.value, middle_initial: fields[3]?.value, gender: fields[4]?.value, birthdate: fields[5]?.value, address: fields[6]?.value, civil_status: fields[7]?.value, contact_number: fields[8]?.value, college_id: college?.id || null, course_id: course?.id || null, major_id: studentMajorSelect.value || null, academic_year: document.querySelector('#studentAcademicYear')?.value, year_level: document.querySelector('#studentYearLevel')?.value };
    const response = await fetch('../api.php?resource=students', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify(student) });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) return showToast(result.error || 'Unable to save student information.');
    studentModal.hidden = true;
    showToast('Student information saved.');
    window.location.reload();
  });

  const historySearch = document.querySelector('#historySearch');
  const historyStatus = document.querySelector('#historyStatus');
  const historyPeriod = document.querySelector('#historyPeriod');
  const historyRows = document.querySelector('#historyRows');
  const emptyHistory = document.querySelector('#emptyHistory');
  const filterHistory = () => {
    const search = historySearch.value.toLowerCase();
    let visible = 0;
    historyRows?.querySelectorAll('tr').forEach((row) => {
      const matchesSearch = row.textContent.toLowerCase().includes(search);
      const matchesStatus = historyStatus.value === 'all' || row.dataset.status === historyStatus.value;
      const matchesPeriod = historyPeriod.value === 'all' || row.dataset.period === historyPeriod.value;
      row.hidden = !(matchesSearch && matchesStatus && matchesPeriod);
      if (!row.hidden) visible += 1;
    });
    if (emptyHistory) emptyHistory.hidden = visible !== 0;
  };
  [historySearch, historyStatus, historyPeriod].forEach((control) => control?.addEventListener('input', filterHistory));

  const historyModal = document.querySelector('#historyDetailModal');
  const closeHistoryDetailModal = document.querySelector('#closeHistoryDetailModal');
  const renderHistoryDetailModal = (row) => {
    if (!historyModal || !row) return;
    const title = row.querySelector('strong')?.textContent || 'Upload details';
    const statusText = row.dataset.status || 'Processing';
    const ocrStatus = row.dataset.ocrStatus || 'Pending';
    const detailTitle = document.querySelector('#historyDetailTitle');
    const detailStudent = document.querySelector('#historyDetailStudent');
    const detailCategory = document.querySelector('#historyDetailCategory');
    const detailDate = document.querySelector('#historyDetailDate');
    const detailStatus = document.querySelector('#historyDetailStatus');
    const detailOcrStatus = document.querySelector('#historyDetailOcrStatus');
    const detailAiSummary = document.querySelector('#historyDetailAiSummary');
    const detailAiCategory = document.querySelector('#historyDetailAiCategory');
    const detailOcrText = document.querySelector('#historyDetailOcrText');
    const detailOcrError = document.querySelector('#historyDetailOcrError');
    if (detailTitle) detailTitle.textContent = title;
    if (detailStudent) detailStudent.textContent = row.dataset.studentNumber || '—';
    if (detailCategory) detailCategory.textContent = row.dataset.categoryCode || '—';
    if (detailDate) detailDate.textContent = row.dataset.uploadedAt || '—';
    if (detailStatus) detailStatus.textContent = statusText;
    if (detailOcrStatus) {
      detailOcrStatus.textContent = ocrStatus;
      detailOcrStatus.className = `history-detail-status ${ocrStatus.toLowerCase() === 'completed' ? 'completed' : ocrStatus.toLowerCase() === 'failed' ? 'failed' : ocrStatus.toLowerCase() === 'processing' ? 'processing' : 'pending'}`;
    }
    if (detailAiSummary) detailAiSummary.textContent = row.dataset.aiSummary || 'No AI summary available yet.';
    if (detailAiCategory) detailAiCategory.textContent = row.dataset.aiDetectedCategory || 'Not detected.';
    if (detailOcrText) detailOcrText.textContent = row.dataset.ocrText || 'No extracted text is available for this upload.';
    if (detailOcrError) detailOcrError.textContent = row.dataset.ocrError || 'No OCR errors reported.';
    historyModal.hidden = false;
  };

  const closeHistoryModal = () => {
    if (historyModal) historyModal.hidden = true;
  };
  closeHistoryDetailModal?.addEventListener('click', closeHistoryModal);
  historyModal?.addEventListener('click', (event) => {
    if (event.target === historyModal) closeHistoryModal();
  });
  historyRows?.addEventListener('click', (event) => {
    const actionButton = event.target.closest('.row-action');
    if (!actionButton) return;
    const row = actionButton.closest('tr');
    if (!row) return;
    renderHistoryDetailModal(row);
  });

  const categoryModal = document.querySelector('#categoryModal');
  const categoryForm = document.querySelector('#categoryForm');
  const categoryGrid = document.querySelector('#categoryGrid');
  const resetCategoryModal = () => {
    const modalTitle = document.querySelector('#categoryModalTitle');
    const submitButton = categoryForm?.querySelector('.login-submit');
    const form = categoryForm;
    if (modalTitle) modalTitle.textContent = 'Add document category';
    if (submitButton) submitButton.innerHTML = 'Add category <i data-lucide="arrow-right"></i>';
    if (form) {
      delete form.dataset.mode;
      delete form.dataset.categoryId;
    }
    categoryForm?.reset();
  };
  const closeCategoryModal = () => {
    if (categoryModal) categoryModal.hidden = true;
    resetCategoryModal();
  };
  document.querySelector('#addCategoryButton')?.addEventListener('click', () => {
    resetCategoryModal();
    categoryModal.hidden = false;
    document.querySelector('#categoryName')?.focus();
  });
  document.querySelector('#closeCategoryModal')?.addEventListener('click', closeCategoryModal);
  document.querySelector('#cancelCategoryButton')?.addEventListener('click', closeCategoryModal);
  categoryModal?.addEventListener('click', (event) => { if (event.target === categoryModal) closeCategoryModal(); });
  categoryForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const name = document.querySelector('#categoryName').value.trim();
    const isEdit = categoryForm.dataset.mode === 'edit';
    const payload = isEdit ? { action: 'update', id: Number(categoryForm.dataset.categoryId), name } : { name };

    if (!name) {
      showToast('Category name is required.');
      return;
    }

    const response = await fetch('../api.php?resource=categories', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) {
      showToast(result.error || (isEdit ? 'Unable to update category.' : 'Unable to add category.'));
      return;
    }

    if (isEdit) {
      const existingCard = categoryGrid?.querySelector(`[data-category-id="${payload.id}"]`);
      if (existingCard) {
        existingCard.querySelector('h4').textContent = name;
        existingCard.querySelector('.category-edit-button').setAttribute('aria-label', `Edit ${name}`);
        const summary = document.querySelector('.category-summary strong');
        if (summary) summary.textContent = String(categoryGrid.querySelectorAll('.category-card').length);
      }
      showToast('Category updated successfully.');
    } else {
      const card = document.createElement('article');
      card.className = 'category-card';
      card.dataset.categoryId = String(result.data?.id || 'new');
      const iconClass = ['gold-icon', 'rose-icon', 'blue-icon'][categoryGrid?.children.length % 3 || 0] || '';
      const actionButton = document.createElement('button');
      actionButton.type = 'button';
      actionButton.className = 'category-edit-button';
      actionButton.textContent = 'Edit';
      actionButton.setAttribute('aria-label', `Edit ${name}`);
      actionButton.addEventListener('click', () => {
        const modal = document.querySelector('#categoryModal');
        const form = document.querySelector('#categoryForm');
        const modalTitle = document.querySelector('#categoryModalTitle');
        const submitButton = form?.querySelector('.login-submit');
        if (!modal || !form || !modalTitle || !submitButton) return;
        form.dataset.mode = 'edit';
        form.dataset.categoryId = String(card.dataset.categoryId);
        modalTitle.textContent = 'Edit document category';
        document.querySelector('#categoryName').value = card.querySelector('h4')?.textContent || '';
        submitButton.innerHTML = 'Save changes <i data-lucide="arrow-right"></i>';
        if (window.lucide) lucide.createIcons();
        modal.hidden = false;
        document.querySelector('#categoryName')?.focus();
      });
      card.innerHTML = `<div class="category-icon ${iconClass}"><i data-lucide="folder-open"></i></div><div><h4></h4></div><span class="category-count">0 files</span>`;
      card.querySelector('h4').textContent = name;
      card.querySelector('.category-count').textContent = '0 files';
      card.append(actionButton);
      categoryGrid.append(card);
      const categoryTotal = document.querySelector('.category-summary strong');
      if (categoryTotal) categoryTotal.textContent = String(categoryGrid.querySelectorAll('.category-card').length);
      showToast('Document category added.');
    }

    categoryForm.reset();
    closeCategoryModal();
    if (window.lucide) lucide.createIcons();
  });
});
