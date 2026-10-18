/* Private server-side autosave and opt-in crash recovery; the saved template remains authoritative. */
(() => {
  'use strict';
  const config = window.CertifyDraftConfig;
  const editor = window.CertifyDraftEditor;
  if (!config || !editor || !document.getElementById('saveForm')) return;

  let timer = null;
  let sending = false;
  let inFlight = null;
  let submitted = false;
  let pending = null;
  const status = document.createElement('span');
  status.className = 'certify-draft-status';
  status.setAttribute('role', 'status');
  status.setAttribute('aria-live', 'polite');
  const footer = document.querySelector('.inspector-footer .editor-change-row') || document.querySelector('.inspector-footer');
  if (footer) footer.appendChild(status);

  const request = (method, body) => fetch(config.url, {
    method,
    credentials: 'same-origin',
    cache: 'no-store',
    headers: {
      'Accept': 'application/json',
      'X-CSRF-TOKEN': config.csrf,
      ...(body ? { 'Content-Type': 'application/json' } : {}),
    },
    ...(body ? { body: JSON.stringify(body) } : {}),
  });

  async function drain() {
    if (sending || submitted || !pending) return;
    sending = true;
    const state = pending;
    pending = null;
    status.textContent = 'Backing up draft…';
    try {
      inFlight = request('POST', { baseline: config.baseline, state });
      const response = await inFlight;
      if (!response.ok) throw new Error(`Draft save failed (${response.status})`);
      status.textContent = 'Draft backed up on this computer';
    } catch (error) {
      status.textContent = 'Draft backup unavailable';
      // Keep the latest state for retry on the next change, without interrupting editing.
      if (!submitted && !pending) pending = state;
    } finally {
      inFlight = null;
      sending = false;
      if (pending && pending !== state && !submitted) schedule();
    }
  }

  function schedule() {
    if (submitted) return;
    clearTimeout(timer);
    timer = setTimeout(() => {
      if (!editor.isDirty()) return;
      pending = editor.snapshot();
      drain();
    }, 1200);
  }

  // Discard changes must clear the private backup before the editor reloads.
  window.CertifyDraftClear = async () => {
    submitted = true;
    clearTimeout(timer);
    pending = null;
    if (inFlight) { try { await inFlight; } catch (_) {} }
    const response = await request('DELETE');
    if (!response.ok) throw new Error('Could not clear draft');
  };

  document.addEventListener('certify:editor-change', schedule);
  document.getElementById('saveForm').addEventListener('submit', () => {
    submitted = true;
    clearTimeout(timer);
    pending = null;
  });
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden' && editor.isDirty() && !submitted) {
      clearTimeout(timer);
      pending = editor.snapshot();
      drain();
    }
  });

  function showRecovery(draft) {
    const overlay = document.createElement('div');
    overlay.className = 'certify-draft-overlay';
    overlay.innerHTML = `
      <section class="certify-draft-dialog" role="dialog" aria-modal="true" aria-labelledby="certifyDraftHeading" aria-describedby="certifyDraftMessage">
        <div class="certify-draft-kicker">UNSAVED DESIGN FOUND</div>
        <h2 id="certifyDraftHeading">Recover your draft?</h2>
        <p id="certifyDraftMessage">Certify saved an unfinished working copy on this computer. You can restore it or keep the last saved template.</p>
        <p class="certify-draft-timestamp"></p>
        <div class="certify-draft-actions">
          <button type="button" class="certify-draft-secondary" data-draft-keep>Keep saved version</button>
          <button type="button" class="certify-draft-primary" data-draft-restore>Restore draft</button>
        </div>
      </section>`;
    const timestamp = overlay.querySelector('.certify-draft-timestamp');
    const when = Date.parse(draft.saved_at);
    if (Number.isFinite(when)) timestamp.textContent = `Last draft backup: ${new Date(when).toLocaleString()}`;
    document.body.appendChild(overlay);
    const restore = overlay.querySelector('[data-draft-restore]');
    const keep = overlay.querySelector('[data-draft-keep]');
    const resolve = async (choice) => {
      restore.disabled = true;
      keep.disabled = true;
      if (choice === 'restore') {
        try {
          editor.restore(draft.state);
          overlay.remove();
        } catch (_) {
          timestamp.textContent = 'This draft could not be restored. The saved version is unchanged.';
          keep.disabled = false;
        }
      } else {
        try {
          const response = await request('DELETE');
          if (!response.ok) throw new Error('Discard request failed');
          overlay.remove();
        } catch (_) {
          timestamp.textContent = 'Could not clear draft yet. Please try again.';
          keep.disabled = false;
          restore.disabled = false;
        }
      }
    };
    restore.addEventListener('click', () => resolve('restore'));
    keep.addEventListener('click', () => resolve('keep'));
    // Require an explicit decision: do not silently overwrite the saved design.
    restore.focus();
    overlay.addEventListener('keydown', (event) => {
      if (event.key !== 'Tab') return;
      if (event.shiftKey && document.activeElement === restore) { event.preventDefault(); keep.focus(); }
      if (!event.shiftKey && document.activeElement === keep) { event.preventDefault(); restore.focus(); }
    });
  }

  (async () => {
    try {
      const response = await request('GET');
      if (!response.ok) return;
      const data = await response.json();
      const draft = data?.draft;
      if (!draft || typeof draft.state !== 'string' || !draft.state.length || editor.isDirty()) return;
      showRecovery(draft);
    } catch (_) {
      // Offline app remains usable if draft recovery storage is inaccessible.
    }
  })();
})();
