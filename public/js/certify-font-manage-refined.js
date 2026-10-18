/* Certify Manage Font: show selected font file name, without changing form validation. */
(() => {
  'use strict';
  const file = document.getElementById('fontVariantFile');
  const label = document.getElementById('fontVariantFileName');
  const form = document.getElementById('fontVariantForm');
  if (!file || !label || !form) return;
  const sync = () => {
    const selected = file.files && file.files[0];
    label.textContent = selected ? selected.name : 'No file selected';
    label.title = selected ? selected.name : '';
  };
  file.addEventListener('change', sync);
  form.addEventListener('reset', () => window.setTimeout(sync, 0));
  sync();
})();
