/* Certify 2.0.6 — quiet Batch Details layout, opt-in selection, PDF preview. */
(() => {
  'use strict';
  const filters = document.getElementById('batchRecipientFilters');
  if (filters) {
    const search = document.getElementById('recipientSearch');
    const status = document.getElementById('recipientStatus');
    const sort = document.getElementById('recipientSort');
    let debounce;
    let composing = false;
    const submitFilters = () => {
      clearTimeout(debounce);
      if (typeof filters.requestSubmit === 'function') filters.requestSubmit();
      else filters.submit();
    };
    if (search) {
      search.addEventListener('compositionstart', () => { composing = true; });
      search.addEventListener('compositionend', () => { composing = false; clearTimeout(debounce); debounce = setTimeout(submitFilters, 550); });
      search.addEventListener('input', () => {
        if (composing) return;
        clearTimeout(debounce);
        debounce = setTimeout(submitFilters, 550);
      });
      filters.addEventListener('submit', () => clearTimeout(debounce));
    }
    status?.addEventListener('change', submitFilters);
    sort?.addEventListener('change', submitFilters);
  }

  const selectForm = document.getElementById('batchSelectionForm');
  if (selectForm) {
    const page = document.querySelector('.batch-detail-page');
    const toggle = document.getElementById('batchSelectToggle');
    const boxes = [...selectForm.querySelectorAll('.batch-detail-select')];
    const all = document.getElementById('selectAllReady');
    const count = document.getElementById('batchSelectedCount');
    const download = document.getElementById('downloadSelected');
    let selecting = false;
    const syncSelection = () => {
      const selected = boxes.filter(box => box.checked).length;
      if (count) count.textContent = `${selected} certificate${selected === 1 ? '' : 's'} selected`;
      if (download) download.disabled = selected === 0;
      if (all) {
        all.disabled = boxes.length === 0;
        all.checked = boxes.length > 0 && selected === boxes.length;
        all.indeterminate = selected > 0 && selected < boxes.length;
      }
    };
    const setSelecting = active => {
      selecting = active;
      page?.classList.toggle('is-selecting', selecting);
      if (toggle) {
        toggle.setAttribute('aria-pressed', String(selecting));
        toggle.textContent = selecting ? 'Cancel selection' : 'Select PDFs';
      }
      if (!selecting) boxes.forEach(box => { box.checked = false; });
      syncSelection();
    };
    toggle?.addEventListener('click', () => setSelecting(!selecting));
    all?.addEventListener('change', () => {
      boxes.forEach(box => { box.checked = all.checked; });
      syncSelection();
    });
    boxes.forEach(box => box.addEventListener('change', syncSelection));
    selectForm.addEventListener('submit', e => {
      if (!selecting || !boxes.some(box => box.checked)) {
        e.preventDefault();
        syncSelection();
      }
    });
    syncSelection();
  }

  const modal = document.getElementById('batchPdfModal');
  const frame = document.getElementById('batchPdfFrame');
  const title = document.getElementById('batchPdfTitle');
  const open = document.getElementById('batchPdfOpen');
  const download = document.getElementById('batchPdfDownload');
  const close = document.getElementById('batchPdfClose');
  let previousFocus = null;
  if (modal && frame && title && open && download && close) {
    const closePreview = () => {
      if (modal.hidden) return;
      modal.hidden = true;
      frame.removeAttribute('src'); // Stop PDF rendering when the dialog closes.
      document.body.classList.remove('batch-pdf-preview-open');
      previousFocus?.focus();
    };
    document.querySelectorAll('.batch-detail-row-preview').forEach(button => {
      button.addEventListener('click', () => {
        const previewUrl = button.dataset.previewUrl;
        const downloadUrl = button.dataset.downloadUrl;
        if (!previewUrl || !downloadUrl) return;
        previousFocus = button;
        title.textContent = button.dataset.recipient || 'Certificate preview';
        frame.src = previewUrl;
        open.href = previewUrl;
        download.href = downloadUrl;
        modal.hidden = false;
        document.body.classList.add('batch-pdf-preview-open');
        close.focus();
      });
    });
    close.addEventListener('click', closePreview);
    modal.addEventListener('click', event => { if (event.target === modal) closePreview(); });
    document.addEventListener('keydown', event => {
      if (modal.hidden) return;
      if (event.key === 'Escape') {
        event.preventDefault();
        closePreview();
      }
      if (event.key === 'Tab') {
        // Keep keyboard focus inside the modal's own controls.
        const focusables = [open, download, close];
        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault(); last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault(); first.focus();
        }
      }
    });
  }
})();
