(function () {
  const storageKey = 'theme';

  const getStoredTheme = () => {
    try {
      return localStorage.getItem(storageKey);
    } catch (err) {
      console.warn('Theme storage unavailable', err);
      return null;
    }
  };

  const setStoredTheme = (theme) => {
    try {
      localStorage.setItem(storageKey, theme);
    } catch (err) {
      console.warn('Theme storage unavailable', err);
    }
  };

  const setTheme = (theme) => {
    document.documentElement.setAttribute('data-bs-theme', theme);
  };

  const preferredTheme = () => getStoredTheme() || 'light';

  setTheme(preferredTheme());

  window.__setTheme = (theme) => {
    setTheme(theme);
    setStoredTheme(theme);
  };

  document.addEventListener('DOMContentLoaded', () => {
    const themeSwitcher = document.getElementById('theme-switcher');
    const themeIcon = themeSwitcher ? themeSwitcher.querySelector('.theme-icon-active') : null;

    const applyIcon = (theme) => {
      if (!themeIcon) return;
      themeIcon.classList.remove('bi-sun-fill', 'bi-moon-stars-fill');
      themeIcon.classList.add(theme === 'dark' ? 'bi-moon-stars-fill' : 'bi-sun-fill');
    };

    applyIcon(preferredTheme());

    if (themeSwitcher) {
      themeSwitcher.addEventListener('click', () => {
        const currentTheme = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
        const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
        window.__setTheme(nextTheme);
        applyIcon(nextTheme);
      });
    }

    const siteHeader = document.querySelector('.site-header');
    if (!siteHeader) return;

    const compactThreshold = 40;

    const updateHeader = () => {
      if ((window.scrollY || 0) > compactThreshold) {
        siteHeader.classList.add('is-compact');
      } else {
        siteHeader.classList.remove('is-compact');
      }
    };

    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });
  });
})();
