@extends('layouts.app')
@section('hide_top', '1')
@section('title', 'Backup & restore')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/certify-backup-231.css') }}?v=2.0.80">
@endpush
@section('content')
<div class="certify-backup">
    <header class="cb-header">
        <h1>Backup &amp; restore</h1>
        <p>Keep your templates, recipient records, PDFs, fonts, and database together in a verified backup.</p>
    </header>

    <section class="cb-grid" aria-label="Backup actions">
        <div class="cb-card cb-action-card cb-primary-card">
            <div class="cb-card-topline">
                <div class="cb-symbol" aria-hidden="true">↓</div>
                <div>
                    <h2>Create a backup</h2>
                    <p>Generate a fresh archive from your current Certrxy data and private storage.</p>
                </div>
            </div>
            <ul class="cb-feature-list">
                <li>Creates one verified ZIP archive</li>
                <li>Includes database and private certificate files</li>
                <li>Keeps the backup available for download below</li>
            </ul>
            <div class="cb-action-row">
                <form action="{{ route('settings.backup.create') }}" method="post" class="cb-submit-form">
                    @csrf
                    <button type="submit" class="cb-button cb-button-primary">Create backup <span aria-hidden="true">→</span></button>
                </form>
                <small>Recommended before large edits, imports, or restores.</small>
            </div>
            @if ($driver === 'mysql')
                <details class="cb-inline-help">
                    <summary>Need help with MySQL backup tools?</summary>
                    <div class="cb-inline-help-body">
                        <p>If backup creation is not working, Certrxy can check whether XAMPP's MySQL tools are installed and can sign in.</p>
                        <form method="post" action="{{ route('settings.backup.diagnose') }}" class="cb-submit-form">
                            @csrf
                            <button type="submit" class="cb-button cb-button-outline">Check MySQL tools</button>
                        </form>
                        <small>Read-only check. Does not create, modify, or restore any database records.</small>
                    </div>
                </details>
            @endif
        </div>

        <div class="cb-card cb-action-card cb-secondary-card">
            <div class="cb-card-topline">
                <div class="cb-symbol" aria-hidden="true">↑</div>
                <div>
                    <h2>Import a backup</h2>
                    <p>Upload an existing Certrxy backup ZIP and verify it before allowing restore.</p>
                </div>
            </div>
            <form action="{{ route('settings.backup.upload') }}" method="post" enctype="multipart/form-data" class="cb-upload-form cb-submit-form">
                @csrf
                <label for="backupArchive" class="cb-file-label">Select ZIP archive</label>
                <div class="cb-upload-dropzone">
                    <input id="backupArchive" name="archive" type="file" accept=".zip,application/zip" required>
                    <span class="cb-upload-hint">Choose backup ZIP</span>
                    <small>No Certrxy file-size limit. Your PHP upload settings and available disk space still apply.</small>
                </div>
                <button type="submit" class="cb-button cb-button-outline">Upload &amp; verify</button>
            </form>
        </div>
    </section>

    <section class="cb-card cb-library" aria-labelledby="backupLibraryTitle">
        <div class="cb-section-heading">
            <div>
                <h2 id="backupLibraryTitle">Saved backups</h2>
                <p>Verify, download, restore, or delete saved local backups.</p>
            </div>
            <div class="cb-heading-meta">
                <span class="cb-count">{{ count($backups) }} shown</span>
            </div>
        </div>
        @forelse($backups as $item)
            <article class="cb-backup-row">
                <div class="cb-file-icon" aria-hidden="true">ZIP</div>
                <div class="cb-file-details">
                    <strong title="{{ $item['name'] }}">{{ $item['name'] }}</strong>
                    <span>{{ date('M j, Y · g:i A', $item['modified']) }} · {{ number_format($item['bytes'] / 1024 / 1024, 2) }} MB</span>
                </div>
                <div class="cb-row-actions">
                    <form method="post" action="{{ route('settings.backup.verify', ['name' => $item['name']]) }}" class="cb-submit-form">
                        @csrf
                        <button type="submit" class="cb-link-button cb-action-pill">Verify</button>
                    </form>
                    <a class="cb-link-button cb-action-pill" href="{{ route('settings.backup.download', ['name' => $item['name']]) }}">Download</a>
                    <details class="cb-restore-details">
                        <summary class="cb-action-pill cb-danger-pill">Restore</summary>
                        <div class="cb-restore-popover">
                            <strong>Replace current Certrxy data?</strong>
                            <p>This restores the selected {{ strtoupper($driver) }} database and private files. Changes made since that backup may be lost. Only restore backups you trust.</p>
                            <form action="{{ route('settings.backup.restore', ['name' => $item['name']]) }}" method="post" class="cb-submit-form cb-restore-form">
                                @csrf
                                <label class="cb-checkbox"><input type="checkbox" name="acknowledge" value="1" required> I understand this replaces my current data.</label>
                                <label for="confirm-{{ $loop->index }}">Type <b>RESTORE CERTRXY</b> to continue</label>
                                <input id="confirm-{{ $loop->index }}" type="text" name="confirmation" placeholder="RESTORE CERTRXY" autocomplete="off" required pattern="RESTORE CERTRXY">
                                <button class="cb-button cb-button-danger" type="submit">Restore selected backup</button>
                            </form>
                            <small>A separate safety backup is created and verified first. Stop other Certrxy windows and background workers before restoring.</small>
                        </div>
                    </details>
                    <details class="cb-delete-details">
                        <summary class="cb-more-trigger" aria-label="More options for {{ $item['name'] }}" title="More options">
                            <svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
                        </summary>
                        <div class="cb-delete-popover">
                            <strong>Delete this backup?</strong>
                            <p class="cb-delete-filename">{{ $item['name'] }}</p>
                            <p>Deletes only this saved ZIP ({{ number_format($item['bytes'] / 1024 / 1024, 2) }} MB). Your live database, templates, and certificates stay unchanged. This cannot be undone.</p>
                            @if($loop->first)
                                <p class="cb-latest-note"><strong>Newest backup</strong> &mdash; deleting your latest recovery copy needs additional confirmation.</p>
                            @endif
                            <form method="post" action="{{ route('settings.backup.destroy', ['name' => $item['name']]) }}" class="cb-submit-form cb-delete-form" data-backup-name="{{ $item['name'] }}">
                                @csrf
                                @method('DELETE')
                                <label for="backup-delete-{{ $loop->index }}">Type <b>DELETE BACKUP</b> to confirm</label>
                                <input id="backup-delete-{{ $loop->index }}" name="confirmation" type="text" required autocomplete="off" pattern="DELETE BACKUP" placeholder="DELETE BACKUP">
                                @if($loop->first)
                                    <label class="cb-checkbox"><input type="checkbox" name="acknowledge_latest" value="1" required> I understand this is the newest backup.</label>
                                    <label for="backup-delete-latest-{{ $loop->index }}">Type <b>DELETE LATEST BACKUP</b></label>
                                    <input id="backup-delete-latest-{{ $loop->index }}" name="latest_confirmation" type="text" required autocomplete="off" pattern="DELETE LATEST BACKUP" placeholder="DELETE LATEST BACKUP">
                                @endif
                                <button type="submit" class="cb-button cb-button-danger">Delete permanently</button>
                            </form>
                        </div>
                    </details>
                </div>
            </article>
        @empty
            <div class="cb-empty"><div aria-hidden="true">▤</div><strong>No backups saved yet</strong><p>Create your first verified backup above.</p></div>
        @endforelse
        <p class="cb-security-note">Backups contain personal data. Store them securely. Deleting a saved archive is permanent and does not change live Certrxy data.</p>
    </section>


</div>
@endsection
@push('scripts')
<script>
(function () {
    document.querySelectorAll('.cb-submit-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.classList.contains('cb-delete-form')) {
                const name = form.dataset.backupName || 'this backup';
                if (!window.confirm('Permanently delete ' + name + '? This cannot be undone.')) {
                    event.preventDefault(); return;
                }
            }
            if (form.classList.contains('cb-restore-form')) {
                if (!window.confirm('This will replace current Certrxy data. A safety backup will be created first. Continue?')) {
                    event.preventDefault(); return;
                }
            }
            const button = form.querySelector('button[type="submit"]');
            if (button) { button.disabled = true; button.textContent = 'Please wait…'; }
        });
    });
})();
</script>
@endpush
