/* Small accessible About dialog; no remote update check or network request. */
(() => {
  'use strict';
  const open = document.querySelector('[data-certify-about-open]');
  const dialog = document.getElementById('certifyAboutDialog');
  if (!open || !dialog) return;
  const close = dialog.querySelector('[data-certify-about-close]');
  let previous = null;
  function hide() {
    dialog.hidden = true;
    document.body.classList.remove('certify-about-is-open');
    (previous || open).focus();
  }
  open.addEventListener('click', () => {
    previous = document.activeElement;
    dialog.hidden = false;
    document.body.classList.add('certify-about-is-open');
    close.focus();
  });
  close.addEventListener('click', hide);
  dialog.addEventListener('pointerdown', event => {
    if (event.target === dialog) hide();
  });
  dialog.addEventListener('keydown', event => {
    if (event.key === 'Escape') { event.preventDefault(); hide(); }
    if (event.key !== 'Tab') return;
    const focusables = [...dialog.querySelectorAll('button:not(:disabled), a[href]')];
    if (!focusables.length) return;
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
})();
