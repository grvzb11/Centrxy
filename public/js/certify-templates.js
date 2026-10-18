/* Certify v1.6.19 — PDF/PNG/JPG uploads only; legacy SVG templates remain readable */
(() => {
    'use strict';
    const library = document.getElementById('templateLibrary');
    if (!library) return;
    const grid = document.getElementById('templateGrid');
    const search = document.getElementById('librarySearch');
    const sort = document.getElementById('librarySort');
    const filters = Array.from(document.querySelectorAll('[data-template-filter]'));
    const cards = grid ? Array.from(grid.querySelectorAll('[data-template-card]')) : [];
    const counter = document.getElementById('libraryCount');
    const noResults = document.getElementById('libraryNoResults');
    let selectedFilter = 'all';

    function updateGallery() {
        const query = (search?.value || '').trim().toLocaleLowerCase();
        const visible = cards.filter(card => {
            const matches = card.dataset.name.includes(query) && (selectedFilter === 'all' || card.dataset.type === selectedFilter);
            card.hidden = !matches;
            return matches;
        });
        const order = sort?.value || 'recent';
        visible.sort((a, b) => order === 'name'
            ? a.dataset.name.localeCompare(b.dataset.name)
            : order === 'oldest'
                ? Number(a.dataset.created) - Number(b.dataset.created) || Number(a.dataset.id) - Number(b.dataset.id)
                : Number(b.dataset.created) - Number(a.dataset.created) || Number(b.dataset.id) - Number(a.dataset.id));
        visible.forEach(card => grid.appendChild(card));
        if (counter) counter.textContent = `Showing ${visible.length} of ${cards.length} ${cards.length === 1 ? 'template' : 'templates'}`;
        if (noResults) noResults.hidden = visible.length > 0;
    }
    search?.addEventListener('input', updateGallery);
    sort?.addEventListener('change', updateGallery);
    filters.forEach(button => button.addEventListener('click', () => {
        selectedFilter = button.dataset.templateFilter;
        filters.forEach(item => {
            const active = item === button;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-pressed', String(active));
        });
        updateGallery();
    }));
    document.getElementById('clearTemplateFilters')?.addEventListener('click', () => {
        if (search) search.value = '';
        filters.find(item => item.dataset.templateFilter === 'all')?.click();
        search?.focus();
    });

    // Close only other open menus when opening a new card menu.
    const menus = Array.from(document.querySelectorAll('.library-item-menu'));
    menus.forEach(menu => menu.addEventListener('toggle', () => {
        if (menu.open) menus.forEach(other => { if (other !== menu) other.open = false; });
    }));
    document.addEventListener('click', event => {
        menus.forEach(menu => { if (!menu.contains(event.target)) menu.open = false; });
    });

    function attachDialog(dialog, closeSelector) {
        let opener = null;
        if (!dialog) return { open() {}, close() {} };
        const close = () => { if (dialog.open) dialog.close(); };
        dialog.querySelectorAll(closeSelector).forEach(button => button.addEventListener('click', close));
        dialog.addEventListener('click', event => { if (event.target === dialog) close(); });
        dialog.addEventListener('close', () => { opener?.focus(); opener = null; });
        return {
            open(button) { opener = button || null; if (!dialog.open) dialog.showModal(); },
            close,
        };
    }

    const previewDialog = document.getElementById('templatePreviewDialog');
    const preview = attachDialog(previewDialog, '[data-close-template-preview]');
    const previewStage = document.getElementById('templatePreviewStage');
    const previewOriginal = document.getElementById('templatePreviewOriginal');
    document.querySelectorAll('[data-preview-template]').forEach(button => button.addEventListener('click', () => {
        const source = button.dataset.previewUrl;
        if (!source || !previewStage) return;
        previewStage.replaceChildren();
        const media = document.createElement(button.dataset.previewType === 'pdf' ? 'iframe' : 'img');
        if (media.tagName === 'IFRAME') {
            media.src = `${source}#toolbar=0&view=Fit`;
            media.title = `Original PDF: ${button.dataset.previewName}`;
        } else {
            media.src = source;
            media.alt = `Original design: ${button.dataset.previewName}`;
        }
        previewStage.appendChild(media);
        document.getElementById('templatePreviewTitle').textContent = button.dataset.previewName;
        document.getElementById('templatePreviewMeta').textContent = `${button.dataset.previewFormat} · ${button.dataset.previewSize}`;
        previewOriginal.href = source;
        preview.open(button);
    }));
    previewDialog?.addEventListener('close', () => previewStage?.replaceChildren());

    const renameDialog = document.getElementById('templateRenameDialog');
    const rename = attachDialog(renameDialog, '[data-close-template-rename]');
    const renameForm = document.getElementById('templateRenameForm');
    const renameInput = document.getElementById('templateRenameInput');
    document.querySelectorAll('[data-rename-template]').forEach(button => button.addEventListener('click', () => {
        renameForm.action = button.dataset.renameUrl;
        renameInput.value = button.dataset.templateName;
        rename.open(button);
        renameInput.focus();
        renameInput.select();
    }));

    const archiveDialog = document.getElementById('templateArchiveDialog');
    const archive = attachDialog(archiveDialog, '[data-close-template-archive]');
    const archiveForm = document.getElementById('templateArchiveForm');
    document.querySelectorAll('[data-archive-template]').forEach(button => button.addEventListener('click', () => {
        archiveForm.action = button.dataset.archiveUrl;
        document.getElementById('templateArchiveName').textContent = button.dataset.templateName;
        document.getElementById('templateArchiveMessage').textContent = button.dataset.templateUsed === '1'
            ? 'This design has existing certificate batches. Archiving hides it from your active library and new generation, but preserves batch history and completed PDFs. You can restore it later.'
            : 'Archiving hides the template from active designs and new generation. Its source file and text fields are preserved, and you can restore it later.';
        archive.open(button);
    }));

    const deleteDialog = document.getElementById('templateDeleteDialog');
    const deletion = attachDialog(deleteDialog, '[data-close-template-delete]');
    const deleteForm = document.getElementById('templateDeleteForm');
    const deleteMessage = document.getElementById('templateDeleteMessage');
    const deleteConfirm = document.getElementById('templateDeleteConfirm');
    document.querySelectorAll('[data-delete-template]').forEach(button => button.addEventListener('click', () => {
        const used = button.dataset.templateUsed === '1';
        deleteForm.action = button.dataset.deleteUrl;
        document.getElementById('templateDeleteName').textContent = button.dataset.templateName;
        deleteMessage.textContent = used
            ? 'This design has certificate batches and cannot be permanently deleted. Archive it instead to preserve history and PDFs.'
            : 'The unused template and its original file will be permanently deleted. This action cannot be undone.';
        deleteConfirm.disabled = used;
        deletion.open(button);
    }));

    // Upload dialog retained from v1.6.16.
    const dialog = document.getElementById('templateUploadDialog');
    const upload = attachDialog(dialog, '[data-close-template-upload]');
    document.querySelectorAll('[data-open-template-upload]').forEach(button => button.addEventListener('click', () => upload.open(button)));
    const form = document.getElementById('templateUploadForm');
    const file = document.getElementById('file');
    const drop = document.getElementById('templateFileDrop');
    const fileLabel = document.getElementById('templateFileLabel');
    const fileDescription = document.getElementById('templateFileDescription');
    const submit = document.getElementById('templateSubmitButton');
    const supportedFile = selected => !!selected && /\.(pdf|png|jpe?g)$/i.test(selected.name)
        && (!selected.type || ['application/pdf', 'image/png', 'image/jpeg', 'application/octet-stream'].includes(selected.type));
    function describeFile() {
        const selected = file?.files?.[0];
        const invalid = !!selected && !supportedFile(selected);
        if (fileLabel) fileLabel.textContent = selected ? selected.name : 'Choose a file or drag it here';
        if (fileDescription) fileDescription.textContent = invalid
            ? 'Unsupported file. Please select a PDF, PNG, or JPG certificate design.'
            : selected ? `${(selected.size / (1024 * 1024)).toFixed(2)} MB · Ready to upload` : 'PDF, PNG, or JPG · Up to 20 MB';
        file?.setCustomValidity(invalid ? 'Only PDF, PNG and JPG certificate designs are supported.' : '');
        if (fileDescription) fileDescription.style.color = invalid ? '#b42318' : '';
        drop?.classList.toggle('has-file', !!selected && !invalid);
    }
    file?.addEventListener('change', describeFile);
    if (drop && file) {
        ['dragenter', 'dragover'].forEach(name => drop.addEventListener(name, event => {
            event.preventDefault(); drop.classList.add('is-dragging');
        }));
        ['dragleave', 'drop'].forEach(name => drop.addEventListener(name, event => {
            event.preventDefault(); drop.classList.remove('is-dragging');
        }));
        drop.addEventListener('drop', event => {
            const incoming = event.dataTransfer?.files;
            if (!incoming?.length) return;
            if (typeof DataTransfer !== 'undefined') {
                const transfer = new DataTransfer();
                transfer.items.add(incoming[0]);
                file.files = transfer.files;
                describeFile();
            }
        });
    }
    form?.addEventListener('submit', event => {
        describeFile();
        if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); return; }
        if (submit) { submit.disabled = true; submit.textContent = 'Uploading…'; }
    });
    if (library.dataset.uploadErrors === '1' || window.location.hash === '#upload') upload.open(null);
    updateGallery();
})();
