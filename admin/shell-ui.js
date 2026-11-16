/* Admin shell presentation helper shared by every admin and staff page.
   Owns the #mobileMenu hamburger toggle across desktop, tablet, and mobile:
   - Desktop / tablet (> 640px): toggles the sidebar on and off
     (body.sidebar-collapsed + #sidebar.open) and expands/contracts the main panel.
   - Mobile (<= 640px): opens and closes the slide-over navigation drawer
     (body.drawer-open + #sidebar.open) with scrim, close button, Escape, and
     scroll lock.
   Never fetches, writes, or changes application data. */
(() => {
  'use strict';
  window.__datsShellUiMounted = true;

  const STORAGE_KEY = 'dats_sidebar_collapsed';
  const body = document.body;
  const sidebar = document.querySelector('#sidebar');
  const mobileMenu = document.querySelector('#mobileMenu');
  const scrim = document.querySelector('[data-drawer-scrim]');
  const drawerClose = document.querySelector('[data-drawer-close]');
  const mobileMedia = window.matchMedia('(max-width: 640px)');

  if (!sidebar || !mobileMenu) return;

  const isMobile = () => mobileMedia.matches;
  const isSidebarOpen = () => sidebar.classList.contains('open');

  const getStoredCollapsed = () => {
    try {
      return window.localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
      return false;
    }
  };

  const setStoredCollapsed = (collapsed) => {
    try {
      window.localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
    } catch {
      // Ignore storage restrictions.
    }
  };

  const updateToggleMeta = (open) => {
    mobileMenu.setAttribute('aria-expanded', String(open));
    const label = open ? 'Hide navigation sidebar' : 'Show navigation sidebar';
    mobileMenu.setAttribute('aria-label', label);
    mobileMenu.setAttribute('title', label);
  };

  const syncShell = (persistDesktop = false) => {
    const open = isSidebarOpen();
    if (isMobile()) {
      body.classList.toggle('drawer-open', open);
      body.classList.remove('sidebar-collapsed');
    } else {
      body.classList.remove('drawer-open');
      body.classList.toggle('sidebar-collapsed', !open);
      if (persistDesktop) setStoredCollapsed(!open);
    }
    updateToggleMeta(open);
  };

  const setSidebarOpen = (open, persistDesktop = false) => {
    sidebar.classList.toggle('open', Boolean(open));
    syncShell(persistDesktop);
  };

  const applyViewportMode = () => {
    if (isMobile()) {
      setSidebarOpen(false, false);
    } else {
      const collapsed = getStoredCollapsed();
      setSidebarOpen(!collapsed, false);
    }
  };

  applyViewportMode();

  // Capture-phase handler on window so clicking the hamburger button (or its
  // icon) toggles once and cannot be undone by legacy bubble listeners in page scripts.
  window.addEventListener(
    'click',
    (event) => {
      const target = event.target instanceof Element ? event.target : null;
      const toggleBtn = target ? target.closest('#mobileMenu') : null;
      if (!toggleBtn) return;
      event.preventDefault();
      event.stopPropagation();
      event.stopImmediatePropagation();
      setSidebarOpen(!isSidebarOpen(), true);
    },
    true
  );

  const closeSidebar = (event) => {
    event?.preventDefault();
    if (isSidebarOpen()) {
      setSidebarOpen(false, !isMobile());
    }
  };

  scrim?.addEventListener('click', closeSidebar);
  drawerClose?.addEventListener('click', closeSidebar);
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && isMobile() && isSidebarOpen()) {
      setSidebarOpen(false, false);
    }
  });
  if (typeof mobileMedia.addEventListener === 'function') {
    mobileMedia.addEventListener('change', applyViewportMode);
  } else if (typeof mobileMedia.addListener === 'function') {
    mobileMedia.addListener(applyViewportMode);
  }

  window.requestAnimationFrame(() => {
    window.requestAnimationFrame(() => {
      body.classList.add('shell-ready');
    });
  });
})();
