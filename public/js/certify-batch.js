/* Certify v1.4.5: automatically recover outdated dependency failures. */
(() => {
  const panel = document.getElementById('batchProcessor');
  if (!panel) return;

  let pending = Number(panel.dataset.pending || 0);
  if (panel.dataset.engineReady !== '1') return;
  const processUrl = panel.dataset.processUrl;
  const retryUrl = panel.dataset.retryUrl;
  const autoRecovery = panel.dataset.autoRecovery === '1';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  const resume = document.getElementById('resumeButton');
  const error = document.getElementById('processingError');
  const progress = document.getElementById('progressBar');
  const manual = document.getElementById('manualGenerationForm');
  const manualButton = document.getElementById('manualGenerate');
  const zip = document.getElementById('downloadZip');
  const heading = document.getElementById('batchHeading');
  const message = document.getElementById('batchMessage');
  const badge = document.getElementById('batchStatusLabel') || document.getElementById('batchStatus');
  const stateBadge = document.getElementById('batchStatus');
  const completedPercentage = document.getElementById('batchCompletionPercent');
  const processedTotal = document.getElementById('batchProcessedTotal');
  const zipCount = document.getElementById('batchZipCount');
  const info = document.getElementById('processingInfo');
  let running = false;

  const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

  function setPaused(messageText) {
    if (heading) heading.textContent = 'Generation paused';
    if (message) message.textContent = 'Certificate creation stopped. Review the error below and retry when ready.';
    if (badge) badge.textContent = 'Paused';
    if (stateBadge) stateBadge.className = 'batch-detail-status batch-detail-status-warning';
    if (info) info.style.display = 'none';
    if (error) {
      error.textContent = messageText + ' Existing PDFs and recipient names are saved.';
      error.style.display = 'block';
    }
    if (resume) resume.style.display = 'inline-flex';
  }

  async function requestChunk() {
    const response = await fetch(processUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'X-CSRF-TOKEN': csrf || '',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    });

    // TCPDF used to print '<strong>TCPDF ERROR...</strong>' and terminate PHP.
    // Do not attempt to parse an HTML error page as JSON.
    const type = response.headers.get('content-type') || '';
    if (!type.toLowerCase().includes('application/json')) {
      throw new Error(`The server returned an invalid PDF-generation response (HTTP ${response.status}). Check storage/logs/laravel.log for the PDF error.`);
    }

    let data;
    try {
      data = await response.json();
    } catch (_) {
      throw new Error('The server returned invalid JSON. See storage/logs/laravel.log for details.');
    }
    if (!response.ok) {
      throw new Error(data.message || `Generation failed (HTTP ${response.status}).`);
    }
    return data;
  }

  async function processBatch() {
    if (running) return;
    running = true;
    if (resume) resume.style.display = 'none';
    if (manualButton) manualButton.disabled = true;
    if (error) error.style.display = 'none';
    if (badge) badge.textContent = 'Processing';
    if (stateBadge) stateBadge.className = 'batch-detail-status batch-detail-status-processing';
    let busyCount = 0;
    let noProgressCount = 0;
    try {
      // Each request processes a small chunk, letting the browser show progress.
      for (let attempts = 0; attempts < 500; attempts++) {
        const data = await requestChunk();
        if (data.busy) {
          if (++busyCount > 20) throw new Error('Another generation request is taking too long. Reload the page or resume in a moment.');
          await sleep(500);
          continue;
        }
        busyCount = 0;
        const ready = Number(data.ready || 0);
        const failed = Number(data.failed || 0);
        const newPending = Number(data.pending || 0);
        const total = Math.max(1, Number(data.total || 1));
        const percentage = Math.min(100, Math.round((ready + failed) * 100 / total));

        if (newPending === pending) {
          if (++noProgressCount > 3) throw new Error('Generation made no progress. Check the Laravel log before retrying.');
        } else noProgressCount = 0;
        pending = newPending;

        document.getElementById('readyCount').textContent = ready;
        document.getElementById('failedCount').textContent = failed;
        document.getElementById('pendingCount').textContent = pending;
        document.getElementById('progressFill').style.width = percentage + '%';
        if (progress) progress.setAttribute('aria-valuenow', String(percentage));
        if (completedPercentage) {
          completedPercentage.textContent = `${percentage}%`;
          completedPercentage.setAttribute('aria-label', `${percentage} percent processed`);
        }
        if (processedTotal) processedTotal.textContent = `${ready + failed} of ${total} processed`;
        if (zipCount) zipCount.textContent = `(${ready})`;
        if (zip && ready > 0) zip.style.display = 'inline-flex';

        if (pending === 0) { window.location.reload(); return; }
        await sleep(150);
      }
      throw new Error('The batch was paused after too many requests. Resume to continue.');
    } catch (exception) {
      setPaused(exception.message || 'Unable to continue PDF generation.');
    } finally {
      running = false;
      if (manualButton) manualButton.disabled = false;
    }
  }

  if (resume) resume.addEventListener('click', processBatch);
  if (manual && manualButton) {
    manual.addEventListener('submit', () => {
      manualButton.disabled = true;
      manualButton.textContent = 'Generating PDFs…';
    });
  }
  async function recoverOldDependencyFailures() {
    if (!retryUrl || !csrf) return;
    if (heading) heading.textContent = 'Recovering certificates';
    if (message) message.textContent = 'The PDF libraries are available. Retrying the earlier failed certificates now…';
    try {
      const response = await fetch(retryUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'X-CSRF-TOKEN': csrf,
          'Content-Type': 'application/x-www-form-urlencoded',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'auto_recovery=1'
      });
      if (!response.ok) throw new Error('Automatic recovery could not complete. Use Retry failed PDFs to retry manually.');
      // Reload to show the actual new PDF results and any new rendering errors.
      window.location.reload();
    } catch (exception) {
      setPaused(exception.message || 'Automatic recovery failed.');
    }
  }

  if (autoRecovery) {
    recoverOldDependencyFailures();
  } else if (pending) {
    processBatch();
  }
})();
