document.addEventListener('DOMContentLoaded', () => {
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  const updateDateTime = () => {
    const formatted = new Intl.DateTimeFormat(undefined, {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date());
    document.querySelectorAll('[data-current-datetime]').forEach((element) => {
      element.textContent = formatted;
    });
  };
  updateDateTime();
  window.setInterval(updateDateTime, 30000);

  if (window.lucide) {
    window.lucide.createIcons();
  }

  const menuButton = document.querySelector('#menuButton');
  const siteMenu = document.querySelector('#siteMenu');
  const loginModal = document.querySelector('#loginModal');
  const closeLoginButton = document.querySelector('#closeLoginButton');
  const loginForm = document.querySelector('#loginForm');
  const emailInput = document.querySelector('#emailInput');
  const passwordInput = document.querySelector('#passwordInput');
  const passwordToggle = document.querySelector('#passwordToggle');
  const toast = document.querySelector('#toast');
  let lastTrigger = null;

  const setMenuOpen = (open) => {
    if (!menuButton || !siteMenu) return;
    menuButton.setAttribute('aria-expanded', String(open));
    siteMenu.classList.toggle('open', open);
  };

  const openLogin = (trigger = null) => {
    if (!loginModal) return;
    lastTrigger = trigger;
    setMenuOpen(false);
    loginModal.hidden = false;
    document.body.classList.add('modal-open');
    window.requestAnimationFrame(() => emailInput?.focus());
  };

  const closeLogin = () => {
    if (!loginModal || loginModal.hidden) return;
    loginModal.hidden = true;
    document.body.classList.remove('modal-open');
    if (lastTrigger && typeof lastTrigger.focus === 'function') {
      lastTrigger.focus();
    }
    lastTrigger = null;
  };

  const showToast = (message) => {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    window.setTimeout(() => toast.classList.remove('show'), 3400);
  };

  menuButton?.addEventListener('click', (event) => {
    event.stopPropagation();
    const isOpen = menuButton.getAttribute('aria-expanded') === 'true';
    setMenuOpen(!isOpen);
  });

  siteMenu?.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => setMenuOpen(false));
  });

  document.addEventListener('click', (event) => {
    if (!siteMenu || !siteMenu.classList.contains('open')) return;
    if (siteMenu.contains(event.target) || menuButton?.contains(event.target)) return;
    setMenuOpen(false);
  });

  document.querySelectorAll('[data-open-login]').forEach((trigger) => {
    trigger.addEventListener('click', () => openLogin(trigger));
  });

  closeLoginButton?.addEventListener('click', closeLogin);
  loginModal?.addEventListener('click', (event) => {
    if (event.target === loginModal) {
      closeLogin();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    if (loginModal && !loginModal.hidden) {
      closeLogin();
      return;
    }
    if (siteMenu?.classList.contains('open')) {
      setMenuOpen(false);
      menuButton?.focus();
    }
  });

  passwordToggle?.addEventListener('click', () => {
    if (!passwordInput) return;
    const isPassword = passwordInput.type === 'password';
    passwordInput.type = isPassword ? 'text' : 'password';
    passwordToggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
  });

  loginForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submitButton = loginForm.querySelector('.login-submit');
    if (submitButton) submitButton.disabled = true;

    try {
      const response = await fetch('login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          email: emailInput?.value || '',
          password: passwordInput?.value || '',
        }),
      });
      const result = await response.json();
      if (!response.ok) {
        throw new Error(result.error || 'Unable to sign in.');
      }
      window.location.href = result.redirect;
    } catch (error) {
      showToast(error.message || 'Unable to sign in.');
      if (submitButton) submitButton.disabled = false;
    }
  });

  // Choreographed reveal entrances
  const revealNodes = [...document.querySelectorAll('[data-reveal]')];
  if (reducedMotion.matches || !('IntersectionObserver' in window)) {
    revealNodes.forEach((node) => node.classList.add('is-revealed'));
  } else {
    const observer = new IntersectionObserver(
      (entries, obs) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          entry.target.classList.add('is-revealed');
          obs.unobserve(entry.target);
        });
      },
      { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
    );
    revealNodes.forEach((node) => observer.observe(node));
  }

  // Slow crossfade across hero archival views
  const heroPhotos = [...document.querySelectorAll('.hero-photo')];
  if (heroPhotos.length > 1) {
    let activeIndex = 0;
    let timer = null;

    const advancePhoto = () => {
      if (document.hidden || reducedMotion.matches) return;
      heroPhotos[activeIndex].classList.remove('is-active');
      activeIndex = (activeIndex + 1) % heroPhotos.length;
      heroPhotos[activeIndex].classList.add('is-active');
    };

    const startCrossfade = () => {
      if (timer || reducedMotion.matches) return;
      timer = window.setInterval(advancePhoto, 5500);
    };

    const stopCrossfade = () => {
      if (!timer) return;
      window.clearInterval(timer);
      timer = null;
    };

    document.addEventListener('visibilitychange', () => {
      if (document.hidden) stopCrossfade();
      else startCrossfade();
    });

    reducedMotion.addEventListener('change', (e) => {
      if (e.matches) {
        stopCrossfade();
        heroPhotos.forEach((img, idx) => img.classList.toggle('is-active', idx === 0));
        activeIndex = 0;
      } else {
        startCrossfade();
      }
    });

    startCrossfade();
  }
});
