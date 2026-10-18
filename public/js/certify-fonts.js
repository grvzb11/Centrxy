/* Certify 2.0.48 — font library with accessible client-side pagination. */
(() => {
    'use strict';
    const gallery = document.getElementById('fontGallery');
    if (!gallery) return;

    const cards = [...gallery.querySelectorAll('.font-gallery-card')];
    const items = [...gallery.querySelectorAll('.font-v209-item')];
    const sampleInput = document.getElementById('fontPreviewInput');
    const sampleName = document.getElementById('fontSpecimenName');
    const selectedName = document.getElementById('fontSelectedName');
    const search = document.getElementById('fontSearch');
    const buttons = [...document.querySelectorAll('[data-font-filter]')];
    const empty = document.getElementById('fontNoResults');
    const pagination = document.getElementById('fontPagination');
    const pageRange = document.getElementById('fontPageRange');
    const pageNumbers = document.getElementById('fontPageNumbers');
    const previousPage = document.getElementById('fontPagePrevious');
    const nextPage = document.getElementById('fontPageNext');
    const pageSize = 9;
    let currentPage = 1;
    let matchingItems = [];
    const installDialog = document.getElementById('fontInstallDialog');
    const manageDialog = document.getElementById('fontManageDialog');
    const openInstallButton = document.getElementById('openFontInstall');
    const installForm = document.getElementById('fontInstallForm');
    const fileInput = document.getElementById('font_file');
    const fileSelected = document.getElementById('fontFileSelected');
    const dropzone = document.getElementById('fontDropzone');
    const renameForm = document.getElementById('fontRenameForm');
    const variantForm = document.getElementById('fontVariantForm');
    const variantSelect = document.getElementById('fontVariantStyle');
    const variantStatus = document.getElementById('fontVariantsStatus');
    const variantFile = document.getElementById('fontVariantFile');
    const variantSubmit = document.getElementById('fontVariantSubmit');
    const removeForm = document.getElementById('fontRemoveForm');
    const renameInput = document.getElementById('fontRenameInput');
    const manageTitle = document.getElementById('fontManageTitle');
    let activeFilter = 'all';
    let activeDialog = null;
    let focusBeforeDialog = null;

    function choose(card) {
        if (!card) return;
        cards.forEach(item => {
            const selected = item === card;
            item.classList.toggle('is-selected', selected);
            item.setAttribute('aria-pressed', String(selected));
        });
        sampleName.style.fontFamily = card.dataset.fontFamily || 'Georgia, serif';
        selectedName.textContent = card.dataset.fontName || 'Font';
        // Installed fonts are served from local storage. No internet request is needed.
    }

    function updateSample() {
        const value = sampleInput.value.trim() || 'Juan Dela Cruz';
        sampleName.textContent = value;
        sampleName.classList.toggle('is-long', value.length > 28);
    }

    // Pagination affects only visibility: all font options remain loaded so preview
    // selection and font-management actions keep their original behavior.
    function renderPage() {
        const total = matchingItems.length;
        const totalPages = Math.ceil(total / pageSize);
        currentPage = Math.min(Math.max(currentPage, 1), Math.max(totalPages, 1));
        const start = (currentPage - 1) * pageSize;
        const shown = new Set(matchingItems.slice(start, start + pageSize));
        items.forEach(item => { item.hidden = !shown.has(item); });
        empty.hidden = total !== 0;
        pagination.hidden = totalPages <= 1;
        if (totalPages <= 1) {
            pageNumbers.replaceChildren();
            return;
        }

        pageRange.textContent = `Showing ${start + 1}–${Math.min(start + pageSize, total)} of ${total} fonts`;
        previousPage.disabled = currentPage === 1;
        nextPage.disabled = currentPage === totalPages;
        pageNumbers.replaceChildren();

        // Keep large catalogs usable without displaying dozens of page buttons.
        const pageSet = new Set([1, totalPages, currentPage - 1, currentPage, currentPage + 1]);
        const pages = [...pageSet].filter(page => page >= 1 && page <= totalPages).sort((a, b) => a - b);
        let lastPage = 0;
        pages.forEach(page => {
            if (page - lastPage > 1) {
                const dots = document.createElement('span');
                dots.className = 'font-v248-ellipsis';
                dots.textContent = '…';
                dots.setAttribute('aria-hidden', 'true');
                pageNumbers.appendChild(dots);
            }
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'font-v248-page-number';
            button.textContent = String(page);
            button.setAttribute('aria-label', `Page ${page}`);
            if (page === currentPage) {
                button.classList.add('is-current');
                button.setAttribute('aria-current', 'page');
            }
            button.addEventListener('click', () => goToPage(page));
            pageNumbers.appendChild(button);
            lastPage = page;
        });
    }

    function goToPage(page) {
        const totalPages = Math.ceil(matchingItems.length / pageSize);
        if (page < 1 || page > totalPages || page === currentPage) return;
        currentPage = page;
        renderPage();
    }

    function filter() {
        const query = search.value.trim().toLocaleLowerCase();
        matchingItems = items.filter(item =>
            (activeFilter === 'all' || item.dataset.fontKind === activeFilter) &&
            (item.dataset.fontName || '').toLocaleLowerCase().includes(query)
        );
        currentPage = 1;
        renderPage();
    }

    function focusable(dialog) {
        return [...dialog.querySelectorAll('button:not([disabled]), input:not([disabled]), a[href]')]
            .filter(node => node.getClientRects().length > 0);
    }

    function showDialog(dialog, preferredFocus) {
        if (activeDialog) hideDialog();
        focusBeforeDialog = document.activeElement;
        activeDialog = dialog;
        dialog.hidden = false;
        document.body.classList.add('font-v209-dialog-open');
        (preferredFocus || focusable(dialog)[0])?.focus();
    }

    function hideDialog() {
        if (!activeDialog) return;
        activeDialog.hidden = true;
        activeDialog = null;
        document.body.classList.remove('font-v209-dialog-open');
        if (focusBeforeDialog?.isConnected) focusBeforeDialog.focus();
        focusBeforeDialog = null;
    }

    document.addEventListener('keydown', event => {
        if (!activeDialog) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            hideDialog();
        } else if (event.key === 'Tab') {
            const all = focusable(activeDialog);
            if (!all.length) return;
            const first = all[0], last = all[all.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault(); last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault(); first.focus();
            }
        }
    });

    [installDialog, manageDialog].forEach(dialog => {
        dialog.addEventListener('click', event => {
            if (event.target === dialog || event.target.closest('[data-close-font-dialog]')) hideDialog();
        });
    });
    openInstallButton.addEventListener('click', () => showDialog(installDialog, document.getElementById('font_name')));
    document.querySelectorAll('[data-font-manage]').forEach(trigger => trigger.addEventListener('click', () => {
        const item = trigger.closest('.font-v209-item');
        const fontName = item?.dataset.fontName || 'Font';
        manageTitle.textContent = 'Manage ' + fontName;
        renameInput.value = fontName;
        renameForm.action = trigger.dataset.renameUrl;
        removeForm.action = trigger.dataset.removeUrl;
        variantForm.action = trigger.dataset.variantUrl;
        variantForm.reset();
        const installed = (trigger.dataset.variants || '').split(',').filter(Boolean);
        variantSelect.querySelectorAll('option').forEach(option => { option.disabled = installed.includes(option.value); });
        const available = [...variantSelect.options].find(option => !option.disabled);
        if (available) variantSelect.value = available.value;
        variantSubmit.disabled = !available;
        variantStatus.textContent = 'Regular' + (installed.includes('B') ? ', Bold' : '') + (installed.includes('I') ? ', Italic' : '') + (installed.includes('BI') ? ', Bold Italic' : '') + ' installed.' + (available ? ' Add a matching TTF face below.' : ' All supported styles are installed.');
        showDialog(manageDialog, renameInput);
    }));
    variantFile?.addEventListener('change', () => {
        variantFile.setCustomValidity(validateFile(variantFile.files?.[0]));
    });
    variantForm?.addEventListener('submit', event => {
        const error = validateFile(variantFile.files?.[0]);
        variantFile.setCustomValidity(error);
        if (error) { event.preventDefault(); variantFile.reportValidity(); }
    });
    removeForm?.addEventListener('submit', event => {
        if (!window.confirm(removeForm.dataset.confirm)) event.preventDefault();
    });

    function validateFile(file) {
        if (!file) return '';
        if (!/\.ttf$/i.test(file.name)) return 'Choose a .ttf font file.';
        if (file.size > 5 * 1024 * 1024) return 'The font file must be 5 MB or smaller.';
        return '';
    }

    function refreshFile() {
        const file = fileInput.files?.[0];
        const error = validateFile(file);
        fileInput.setCustomValidity(error);
        fileSelected.textContent = error || (file ? `Selected: ${file.name} (${(file.size / 1024).toFixed(0)} KB)` : '');
        dropzone.classList.toggle('has-file', !!file && !error);
        dropzone.classList.toggle('has-error', !!error);
        // File name is just a suggestion, not a claim to have parsed TTF metadata.
        const nameInput = document.getElementById('font_name');
        if (file && !nameInput.value.trim()) {
            nameInput.value = file.name.replace(/\.ttf$/i, '').replace(/[-_]+/g, ' ').trim().slice(0, 80);
        }
    }
    fileInput?.addEventListener('change', refreshFile);
    if (dropzone && fileInput) {
        ['dragenter', 'dragover'].forEach(eventName => dropzone.addEventListener(eventName, event => {
            if (!event.dataTransfer?.types.includes('Files')) return;
            event.preventDefault();
            dropzone.classList.add('is-dragging');
        }));
        ['dragleave', 'dragend'].forEach(eventName => dropzone.addEventListener(eventName, event => {
            if (eventName === 'dragleave' && dropzone.contains(event.relatedTarget)) return;
            dropzone.classList.remove('is-dragging');
        }));
        dropzone.addEventListener('drop', event => {
            event.preventDefault();
            dropzone.classList.remove('is-dragging');
            if (!event.dataTransfer?.files?.length) return;
            try {
                const transfer = new DataTransfer();
                transfer.items.add(event.dataTransfer.files[0]);
                fileInput.files = transfer.files;
                refreshFile();
            } catch (_) {
                fileSelected.textContent = 'Drop is unavailable here. Use Choose file instead.';
            }
        });
    }
    installForm?.addEventListener('submit', event => {
        refreshFile();
        if (!installForm.checkValidity()) {
            event.preventDefault();
            installForm.reportValidity();
        } else {
            const submit = installForm.querySelector('button[type="submit"]');
            submit.disabled = true;
            submit.textContent = 'Installing…';
        }
    });

    cards.forEach(card => card.addEventListener('click', () => choose(card)));
    sampleInput.addEventListener('input', updateSample);
    search.addEventListener('input', filter);
    previousPage.addEventListener('click', () => goToPage(currentPage - 1));
    nextPage.addEventListener('click', () => goToPage(currentPage + 1));
    buttons.forEach(button => button.addEventListener('click', () => {
        activeFilter = button.dataset.fontFilter;
        buttons.forEach(item => {
            const selected = item === button;
            item.classList.toggle('is-active', selected);
            item.setAttribute('aria-pressed', String(selected));
        });
        filter();
    }));

    const initialFont = cards[0];
    if (initialFont) choose(initialFont);
    updateSample();
    filter();
    // Reopen the install dialog after server validation errors so the fields are visible.
    if (document.querySelector('[data-font-upload-error]')) showDialog(installDialog, document.getElementById('font_name'));
})();
