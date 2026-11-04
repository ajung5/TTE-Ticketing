/* TTE internal UI v47-v49. No business rules or network activity. */
(() => {
  'use strict';
  const root = document.querySelector('.tte-internal');
  if (!root) return;
  const sidebar = root.querySelector('.sidebar');
  const toggle = sidebar?.querySelector('[data-menu-toggle]');
  if (sidebar && toggle) {
    sidebar.classList.add('ui-menu-ready');
    const setOpen = (value) => {
      sidebar.classList.toggle('ui-menu-open', value);
      toggle.setAttribute('aria-expanded', String(value));
    };
    toggle.addEventListener('click', () => setOpen(!sidebar.classList.contains('ui-menu-open')));
    // Reset menu state when returning to desktop breakpoint.
    window.matchMedia('(min-width: 681px)').addEventListener('change', e => { if (e.matches) setOpen(false); });
  }

  const openDialog = (dialog, trigger) => {
    if (!dialog) return;
    if (typeof dialog.showModal === 'function') {
      if (!dialog.open) dialog.showModal();
    } else {
      dialog.setAttribute('open', '');
    }
    dialog.__returnFocus = trigger || null;
  };
  document.addEventListener('click', e => {
    const opener = e.target.closest('[data-open-dialog]');
    if (opener) {
      openDialog(document.getElementById(opener.dataset.openDialog), opener);
      return;
    }
    const closer = e.target.closest('[data-close-dialog]');
    if (closer) {
      const dialog = closer.closest('dialog');
      if (dialog) typeof dialog.close === 'function' ? dialog.close() : dialog.removeAttribute('open');
    }
  });
  for (const dialog of root.querySelectorAll('.tte-verifier-dialog')) {
    dialog.addEventListener('close', () => dialog.__returnFocus?.focus());
    if (dialog.hasAttribute('data-auto-open')) openDialog(dialog, null);
  }

  // Only one preset editor should be expanded, including browsers without details[name] grouping.
  const presets = [...root.querySelectorAll('.tte-preset-detail')];
  for (const preset of presets) {
    preset.addEventListener('toggle', () => {
      if (preset.open) presets.forEach(other => { if (other !== preset) other.open = false; });
    });
  }
})();
