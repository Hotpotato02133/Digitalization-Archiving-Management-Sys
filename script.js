document.addEventListener('DOMContentLoaded', () => {
  const updateDateTime = () => {
    const value = new Intl.DateTimeFormat(undefined, { dateStyle: 'full', timeStyle: 'medium' }).format(new Date());
    document.querySelectorAll('[data-current-datetime]').forEach((element) => { element.textContent = value; });
  };
  updateDateTime();
  window.setInterval(updateDateTime, 1000);

  if (window.lucide) {
    lucide.createIcons();
  }

  const signInButton = document.querySelector('#signInButton');
  const toast = document.querySelector('#toast');
  const menuButton = document.querySelector('.menu-button');
  const nav = document.querySelector('.nav-links');
  const loginModal = document.querySelector('#loginModal');
  const closeLoginButton = document.querySelector('#closeLoginButton');
  const loginForm = document.querySelector('#loginForm');
  const passwordInput = document.querySelector('#passwordInput');
  const passwordToggle = document.querySelector('#passwordToggle');

  signInButton?.addEventListener('click', () => {
    loginModal.hidden = false;
    document.body.classList.add('modal-open');
    document.querySelector('#emailInput')?.focus();
  });

  const closeLogin = () => {
    loginModal.hidden = true;
    document.body.classList.remove('modal-open');
  };

  closeLoginButton?.addEventListener('click', closeLogin);
  loginModal?.addEventListener('click', (event) => {
    if (event.target === loginModal) closeLogin();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !loginModal.hidden) closeLogin();
  });

  passwordToggle?.addEventListener('click', () => {
    const isPassword = passwordInput.type === 'password';
    passwordInput.type = isPassword ? 'text' : 'password';
    passwordToggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
  });

  loginForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submitButton = loginForm.querySelector('.login-submit');
    submitButton.disabled = true;
    try {
      const response = await fetch('login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          email: document.querySelector('#emailInput').value,
          password: passwordInput.value,
        }),
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || 'Unable to sign in.');
      window.location.href = result.redirect;
    } catch (error) {
      toast.textContent = error.message;
      toast.classList.add('show');
      window.setTimeout(() => toast.classList.remove('show'), 3200);
      submitButton.disabled = false;
    }
  });

  menuButton?.addEventListener('click', () => {
    const isOpen = menuButton.getAttribute('aria-expanded') === 'true';
    menuButton.setAttribute('aria-expanded', String(!isOpen));
    nav.classList.toggle('open', !isOpen);
  });
});
