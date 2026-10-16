document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();

  const mobileMenu = document.querySelector('#mobileMenu');
  const sidebar = document.querySelector('#sidebar');
  const toast = document.querySelector('#staffToast');
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
  document.querySelectorAll('[data-task]').forEach((task) => task.addEventListener('click', () => showToast(`${task.dataset.task} is ready for review.`)));
  document.querySelectorAll('[data-action]').forEach((action) => action.addEventListener('click', () => {
    const actionRoutes = { scan: 'scan.php', upload: 'scan.php', help: 'mailto:registrar@zppsu.edu.ph?subject=Staff%20workflow%20help' };
    window.location.href = actionRoutes[action.dataset.action];
  }));

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

  const categoryModal = document.querySelector('#categoryModal');
  const categoryForm = document.querySelector('#categoryForm');
  const categoryGrid = document.querySelector('#categoryGrid');
  const closeCategoryModal = () => { if (categoryModal) categoryModal.hidden = true; };
  document.querySelector('#addCategoryButton')?.addEventListener('click', () => {
    categoryModal.hidden = false;
    document.querySelector('#categoryCode')?.focus();
  });
  document.querySelector('#closeCategoryModal')?.addEventListener('click', closeCategoryModal);
  document.querySelector('#cancelCategoryButton')?.addEventListener('click', closeCategoryModal);
  categoryModal?.addEventListener('click', (event) => { if (event.target === categoryModal) closeCategoryModal(); });
  categoryForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const code = document.querySelector('#categoryCode').value.trim().toUpperCase();
    const name = document.querySelector('#categoryName').value.trim();
    const description = document.querySelector('#categoryDescription').value.trim();
    const response = await fetch('../api.php?resource=categories', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ code, name, description }),
    });
    if (!response.ok) {
      const result = await response.json().catch(() => ({}));
      showToast(result.error || 'Unable to add category.');
      return;
    }
    const card = document.createElement('article');
    card.className = 'category-card';
    card.innerHTML = '<div class="category-icon blue-icon"><i data-lucide="folder-plus"></i></div><div><span class="category-code"></span><h4></h4><p></p></div><span class="category-count">0 files</span>';
    card.querySelector('.category-code').textContent = code;
    card.querySelector('h4').textContent = name;
    card.querySelector('p').textContent = description;
    categoryGrid.append(card);
    const categoryTotal = document.querySelector('.category-summary strong');
    categoryTotal.textContent = String(Number(categoryTotal.textContent) + 1);
    categoryForm.reset();
    closeCategoryModal();
    if (window.lucide) lucide.createIcons();
  });
});
