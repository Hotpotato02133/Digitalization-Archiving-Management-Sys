document.addEventListener('DOMContentLoaded', () => {
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  const renderIcons = () => {
    if (window.lucide) window.lucide.createIcons();
  };
  renderIcons();

  /* Sticky header & scroll-to-top visibility */
  const topbar = document.querySelector('.topbar');
  const scrollTopButton = document.querySelector('#scrollTopButton');
  const updateScrollUI = () => {
    const y = window.scrollY;
    topbar?.classList.toggle('is-scrolled', y > 8);
    scrollTopButton?.classList.toggle('is-visible', y > 360);
  };
  updateScrollUI();
  window.addEventListener('scroll', updateScrollUI, { passive: true });

  scrollTopButton?.addEventListener('click', (event) => {
    window.scrollTo({
      top: 0,
      behavior: reducedMotion.matches ? 'auto' : 'smooth',
    });
    if (event.detail === 0) {
      document.querySelector('.brand')?.focus({ preventScroll: true });
    }
  });

  /* Mobile navigation drawer */
  const menuButton = document.querySelector('#menuButton');
  const siteNav = document.querySelector('#siteNav');

  const setMenuOpen = (open) => {
    if (!menuButton || !siteNav) return;
    menuButton.setAttribute('aria-expanded', String(open));
    siteNav.classList.toggle('is-open', open);
    document.body.classList.toggle('menu-open', open);
  };

  menuButton?.addEventListener('click', (event) => {
    event.stopPropagation();
    setMenuOpen(menuButton.getAttribute('aria-expanded') !== 'true');
  });

  siteNav?.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => setMenuOpen(false));
  });

  document.addEventListener('click', (event) => {
    if (!siteNav?.classList.contains('is-open')) return;
    if (siteNav.contains(event.target) || menuButton?.contains(event.target)) return;
    setMenuOpen(false);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && siteNav?.classList.contains('is-open')) {
      setMenuOpen(false);
      menuButton?.focus();
    }
  });

  /* Scroll-spy: highlight the nav link for the section in view */
  const spyLinks = [...document.querySelectorAll('[data-spy]')];
  const spySections = spyLinks
    .map((link) => document.querySelector(link.getAttribute('href')))
    .filter(Boolean);

  const setActiveLink = (id) => {
    spyLinks.forEach((link) => {
      const active = link.getAttribute('href') === '#' + id;
      link.classList.toggle('is-active', active);
      if (active) link.setAttribute('aria-current', 'location');
      else link.removeAttribute('aria-current');
    });
  };

  if (spySections.length && 'IntersectionObserver' in window) {
    const visible = new Map();
    const spy = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => visible.set(entry.target.id, entry.isIntersecting ? entry.intersectionRatio : 0));
        let best = null;
        visible.forEach((ratio, id) => {
          if (ratio > 0 && (best === null || ratio > visible.get(best))) best = id;
        });
        if (best) setActiveLink(best);
      },
      { rootMargin: '-35% 0px -50% 0px', threshold: [0, 0.1, 0.25, 0.5, 0.75, 1] }
    );
    spySections.forEach((section) => spy.observe(section));
  } else if (spyLinks.length) {
    setActiveLink('overview');
  }

  /* Sign-in form */
  const loginForm = document.querySelector('#loginForm');
  const emailInput = document.querySelector('#emailInput');
  const passwordInput = document.querySelector('#passwordInput');
  const passwordToggle = document.querySelector('#passwordToggle');
  const loginError = document.querySelector('#loginError');
  const loginSubmit = document.querySelector('#loginSubmit');

  const loginDialog = document.querySelector('#loginDialog');
  const closeLoginButton = document.querySelector('#closeLoginButton');
  let loginTrigger = null;

  const openLogin = (trigger = null) => {
    if (!loginDialog) return;
    loginTrigger = trigger;
    setMenuOpen(false);
    if (typeof loginDialog.showModal === 'function') {
      if (!loginDialog.open) loginDialog.showModal();
    } else {
      loginDialog.setAttribute('open', '');
    }
    window.requestAnimationFrame(() => emailInput?.focus());
  };

  const closeLogin = () => {
    if (!loginDialog || !loginDialog.open) return;
    if (typeof loginDialog.close === 'function') loginDialog.close();
    else loginDialog.removeAttribute('open');
  };

  document.querySelectorAll('[data-open-login]').forEach((trigger) => {
    trigger.addEventListener('click', (event) => {
      event.preventDefault();
      openLogin(trigger);
    });
  });

  closeLoginButton?.addEventListener('click', closeLogin);

  loginDialog?.querySelectorAll('[data-close-login]').forEach((link) => {
    link.addEventListener('click', () => closeLogin());
  });

  // Click on the dimmed backdrop (outside the card) closes the dialog.
  loginDialog?.addEventListener('click', (event) => {
    if (event.target === loginDialog) closeLogin();
  });

  loginDialog?.addEventListener('close', () => {
    clearError();
    if (loginTrigger && typeof loginTrigger.focus === 'function') loginTrigger.focus();
    loginTrigger = null;
  });

  const showError = (message) => {
    if (!loginError) return;
    loginError.textContent = message;
    loginError.hidden = false;
  };

  const clearError = () => {
    if (!loginError) return;
    loginError.hidden = true;
    loginError.textContent = '';
    emailInput?.removeAttribute('aria-invalid');
    passwordInput?.removeAttribute('aria-invalid');
  };

  passwordToggle?.addEventListener('click', () => {
    if (!passwordInput) return;
    const reveal = passwordInput.type === 'password';
    passwordInput.type = reveal ? 'text' : 'password';
    passwordToggle.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
    passwordToggle.setAttribute('aria-pressed', String(reveal));
    passwordToggle.innerHTML = '<i data-lucide="' + (reveal ? 'eye-off' : 'eye') + '" aria-hidden="true"></i>';
    renderIcons();
    passwordInput.focus({ preventScroll: true });
  });

  [emailInput, passwordInput].forEach((input) => input?.addEventListener('input', clearError));

  loginForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearError();

    const email = (emailInput?.value || '').trim();
    const password = passwordInput?.value || '';

    if (!email || !password) {
      const target = !email ? emailInput : passwordInput;
      showError('Enter your work email and password to continue.');
      target?.setAttribute('aria-invalid', 'true');
      target?.focus();
      return;
    }

    loginForm.classList.add('is-busy');
    if (loginSubmit) loginSubmit.disabled = true;
    const label = loginSubmit?.querySelector('.button-label');
    const originalLabel = label?.textContent;
    if (label) label.textContent = 'Signing in…';

    try {
      const response = await fetch('login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ email, password }),
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(result.error || 'Unable to sign in. Check your email and password.');
      }
      if (label) label.textContent = 'Opening workspace…';
      window.location.href = result.redirect || 'admin/index.php';
    } catch (error) {
      const offline = error instanceof TypeError;
      showError(offline ? 'The server could not be reached. Check your connection and try again.' : error.message);
      emailInput?.setAttribute('aria-invalid', 'true');
      passwordInput?.setAttribute('aria-invalid', 'true');
      passwordInput?.focus();
      loginForm.classList.remove('is-busy');
      if (loginSubmit) loginSubmit.disabled = false;
      if (label && originalLabel) label.textContent = originalLabel;
    }
  });

  /* Reveal-on-scroll, skipped for reduced motion and without IntersectionObserver */
  const revealNodes = [...document.querySelectorAll('[data-reveal]')];
  if (reducedMotion.matches || !('IntersectionObserver' in window)) {
    revealNodes.forEach((node) => node.classList.add('is-revealed'));
  } else {
    const reveal = new IntersectionObserver(
      (entries, observer) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          entry.target.classList.add('is-revealed');
          observer.unobserve(entry.target);
        });
      },
      { threshold: 0.1, rootMargin: '0px 0px -8% 0px' }
    );
    revealNodes.forEach((node) => reveal.observe(node));

    // Safety net: anything already on screen is shown even if the observer is throttled
    // (background tabs, print preview, very old engines).
    window.setTimeout(() => {
      const viewportBottom = window.innerHeight * 1.1;
      revealNodes.forEach((node) => {
        if (node.classList.contains('is-revealed')) return;
        if (node.getBoundingClientRect().top < viewportBottom) node.classList.add('is-revealed');
      });
    }, 1500);
  }
});
