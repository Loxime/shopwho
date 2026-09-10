(() => {
  const selector = '[data-profile-dashboard]';

  const getDashboard = () =>
    document.querySelector(selector);

  const loadSection = async (
    url,
    updateHistory = true
  ) => {
    const dashboard = getDashboard();

    if (!dashboard) {
      window.location.href = url;
      return;
    }

    const main = dashboard.querySelector(
      '[data-profile-dashboard-main]'
    );

    const nav = dashboard.querySelector(
      '[data-profile-dashboard-nav]'
    );

    if (!main || !nav) {
      window.location.href = url;
      return;
    }

    dashboard.classList.add('is-loading');
    main.setAttribute('aria-busy', 'true');

    try {
      const response = await fetch(url, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      if (!response.ok) {
        throw new Error(
          `HTTP ${response.status}`
        );
      }

      const html = await response.text();

      const nextDocument =
        new DOMParser().parseFromString(
          html,
          'text/html'
        );

      const nextMain =
        nextDocument.querySelector(
          '[data-profile-dashboard-main]'
        );

      const nextNav =
        nextDocument.querySelector(
          '[data-profile-dashboard-nav]'
        );

      if (!nextMain || !nextNav) {
        throw new Error(
          'Dashboard content missing'
        );
      }

      main.innerHTML = nextMain.innerHTML;
      nav.innerHTML = nextNav.innerHTML;

      document.title =
        nextDocument.title || document.title;

      if (updateHistory) {
        history.pushState(
          {
            profileDashboard: true,
          },
          '',
          url
        );
      }

      document.dispatchEvent(
        new CustomEvent(
          'shopwho:profile-section-changed'
        )
      );

      main.focus({
        preventScroll: true,
      });
    } catch (error) {
      window.location.href = url;
    } finally {
      dashboard.classList.remove(
        'is-loading'
      );

      main.removeAttribute('aria-busy');
    }
  };

  document.addEventListener(
    'click',
    (event) => {
      const link = event.target.closest(
        '[data-profile-dashboard-link]'
      );

      if (!link) {
        return;
      }

      if (
        event.button !== 0
        || event.metaKey
        || event.ctrlKey
        || event.shiftKey
        || event.altKey
      ) {
        return;
      }

      event.preventDefault();

      loadSection(link.href);
    }
  );

  window.addEventListener(
    'popstate',
    () => {
      if (
        getDashboard()
        && window.location.pathname
          .startsWith('/profil')
      ) {
        loadSection(
          window.location.href,
          false
        );
      }
    }
  );
})();
