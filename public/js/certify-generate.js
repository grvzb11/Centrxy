/* Certify Desktop 2.0.51 Alpha — grouped/individual award assignments + existing Generate workflow. */
(() => {
    'use strict';
    const form = document.getElementById('generateForm');
    if (!form) return;
    const byId = id => document.getElementById(id);
    const names = byId('names');
    const file = byId('csv_file');
    const button = byId('generateButton');
    const title = byId('title');
    const picker = byId('template_id');
    const count = byId('nameCount');
    const summary = byId('nameSummary');
    const validation = byId('nameValidation');
    const reviewButton = byId('reviewNames');
    const reviewDialog = byId('recipientReviewDialog');
    const reviewText = byId('recipientReviewText');
    const picked = byId('filePicked');
    const filePlaceholder = byId('filePlaceholder');
    const clearFile = byId('clearImportedFile');
    const removeDuplicates = byId('remove_duplicates');
    const readiness = byId('generationReadiness');
    const preview = byId('templatePaper');
    const previewCaption = byId('previewCaption');
    const previewButton = byId('previewRecipient');
    const proofChoice = byId('proofRecipientChoice');
    const proofButton = byId('checkProof');
    const proofMessage = byId('proofMessage');
    const previewFeedback = byId('previewFeedback');
    const format = byId('previewFormat');
    const previewName = byId('previewName');
    const dimensions = byId('previewDimensions');
    const edit = byId('editTemplate');
    const original = byId('viewTemplate');
    const numberSettings = byId('certificateNumberSettings');
    const filenameRule = byId('filenameRule');
    const prefix = byId('certificate_prefix');
    const numberFormat = byId('certificate_format');
    const numberExample = byId('certificateNumberExample');
    const importToggle = byId('importToggle');
    const importPanel = byId('nameImportPanel');
    const confirmDialog = byId('generateConfirm');
    const confirmCancel = byId('generateConfirmCancel');
    const confirmGo = byId('generateConfirmGo');
    const layoutChoices = Array.from(form.querySelectorAll('input[name="print_layout"]'));
    const twoUpOptions = byId('twoUpOptions');
    const cutGuide = byId('cutting_guide');
    const printMargin = byId('print_margin_mm');
    const layoutWarning = byId('layoutWarning');
    const sheetPlan = byId('sheetPlan');
    const layoutDescription = byId('layoutDescription');
    const outputSummary = byId('generateOutputSummary');
    const awardSection = byId('awardPlacementSection');
    const awardStatus = byId('awardAssignmentStatus');
    const awardEditLink = byId('awardEditLink');

    const templateJson = byId('generateTemplateData');
    const configJson = byId('generateConfig');
    let templates = [];
    let config = {};
    try { templates = JSON.parse(templateJson?.textContent || '[]'); } catch (_) { /* Fall back to form validation. */ }
    try { config = JSON.parse(configJson?.textContent || '{}'); } catch (_) { /* Fall back to defaults. */ }
    const maxRows = Number(config.max_rows) || 1000;
    let imported = [];
    let importError = '';
    let sampleUrl = null;
    let sampleRequest = null;
    let sampleSerial = 0;
    let confirmed = false;
    const normalize = value => String(value ?? '').trim().replace(/\s+/gu, ' ');
    const pastedNames = () => names.value.split(/\r\n|\n|\r/g).map(line => normalize(line.split('\t')[0])).filter(Boolean);
    const allNames = () => pastedNames().concat(imported);
    const dedupe = values => {
        const seen = new Set();
        return values.filter(value => {
            const key = value.toLocaleLowerCase();
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        });
    };
    const chosenNames = () => removeDuplicates.checked ? dedupe(allNames()) : allNames();
    const duplicateCount = values => values.length - dedupe(values).length;
    const currentTemplate = () => templates.find(item => String(item.id) === String(picker.value));
    const hasAwards = () => Boolean(currentTemplate()?.has_award_placement);
    const cleanAward = value => String(value ?? '').trim().replace(/\s+/gu,' ');
    function awardState(recipientList) {
        const recipients=recipientList||chosenNames();
        if(!hasAwards())return {assigned:[],error:'',total:0};
        const configuration=currentTemplate()?.award_config;
        if(!configuration)return {assigned:[],error:'Configure award placements in Templates → Edit, then save.',total:0};
        let assigned=[];
        if(configuration.mode==='manual') {
            assigned=String(configuration.manual||'').split(/\r\n|\n|\r/).map(cleanAward);
        } else {
            const groups=configuration.groups||[];
            if(!groups.length)return {assigned:[],error:'Configure at least one placement in the Template Editor.',total:0};
            for(const group of groups){
                if(!Number.isInteger(Number(group.count))||Number(group.count)<1||Number(group.count)>1000||!cleanAward(group.label))
                    return {assigned:[],error:'Review the saved placement settings in the Template Editor.',total:0};
                for(let i=0;i<Number(group.count);i++)assigned.push(cleanAward(group.label));
            }
        }
        const total=assigned.length;
        const invalid=assigned.some(value=>!value||[...value].length>100||/[\x00-\x1F\x7F]/.test(value));
        const error=invalid?'Some saved placement titles are missing or invalid. Review them in the Template Editor.'
            :total!==recipients.length?`This template assigns ${total} awards; your list has ${recipients.length} recipients. Edit placements in the Template Editor or adjust the names.`:'';
        return {assigned,error,total};
    }
    function refreshAwardFields(){
        if(!awardSection)return;
        awardSection.hidden=!hasAwards();
        if(!hasAwards())return;
        const item=currentTemplate();
        const state=awardState(chosenNames());
        const hasNames=chosenNames().length>0;
        const missing=!item.award_config;
        awardStatus.textContent=missing?'Configure placements in the Template Editor before generating.'
            :hasNames?(state.error||`${state.total} recipients · ${item.award_config.summary}`)
            :`${item.award_config.total} awards configured · ${item.award_config.summary}`;
        awardStatus.classList.toggle('is-warning',missing||(hasNames&&!!state.error));
        awardEditLink.href=item.edit_url;
    }

    const firstRecipient = () => { const list = chosenNames(); if (!list.length) return ''; if (isTwoUp()) return list[0]; if (proofChoice?.value === 'longest') return [...list].sort((a,b) => b.length-a.length)[0]; if (proofChoice?.value === 'shortest') return [...list].sort((a,b) => a.length-b.length)[0]; return list[0]; };
    const currentLayout = () => layoutChoices.find(item => item.checked)?.value || 'single';
    const isTwoUp = () => currentLayout() === 'two_up';
    const supportsTwoUp = () => { const t = currentTemplate(); return t && Number(t.width_mm) > Number(t.height_mm); };
    const sheetCount = n => Math.ceil(n / 2);

    function parseCsv(text) {
        // Simple RFC 4180-compatible reader; first column only, including quoted commas/newlines.
        const rows = [];
        let field = '', row = [], quoted = false;
        const source = text.replace(/^\uFEFF/, '');
        for (let i = 0; i < source.length; i++) {
            const char = source[i];
            if (quoted) {
                if (char === '"' && source[i + 1] === '"') { field += '"'; i++; }
                else if (char === '"') quoted = false;
                else field += char;
            } else if (char === '"' && field === '') quoted = true;
            else if (char === ',') { row.push(field); field = ''; }
            else if (char === '\n' || char === '\r') {
                row.push(field); rows.push(row); row = []; field = '';
                if (rows.length > maxRows) break;
                if (char === '\r' && source[i + 1] === '\n') i++;
            } else field += char;
        }
        if (rows.length <= maxRows) {
            row.push(field);
            if (row.some(col => col.length)) rows.push(row);
        }
        const firstColumn = rows.map(columns => normalize(columns[0]));
        if (['name', 'recipient', 'recipient_name', 'full_name', 'full name'].includes(firstColumn[0]?.toLocaleLowerCase())) firstColumn.shift();
        return firstColumn.filter(Boolean);
    }

    async function readImport() {
        const selected = file.files?.[0];
        const ticket = ++fileTicket.value;
        imported = [];
        importError = '';
        filePlaceholder.textContent = selected?.name || 'No file selected';
        clearFile.hidden = !selected;
        picked.textContent = selected ? 'Reading recipient names…' : '';
        picked.classList.remove('is-error');
        if (selected) {
            if (!/\.(csv|txt|xlsx)$/i.test(selected.name)) importError = 'Choose a CSV, TXT, or XLSX file.';
            else if (selected.size > 25 * 1024 * 1024) importError = 'The import file must be 25 MB or smaller.';
            else {
                try {
                    // Use the same importer as the final submit: CSV quoting,
                    // Excel shared strings, row limits, and validation stay in sync.
                    const body = new FormData();
                    body.set('_token', form.querySelector('input[name="_token"]').value);
                    body.set('csv_file', selected);
                    const response = await fetch(config.import_url, {
                        method: 'POST', body,
                        headers: {'Accept': 'application/json', 'X-Requested-With':'XMLHttpRequest'},
                    });
                    const data = await response.json().catch(() => ({}));
                    if (ticket !== fileTicket.value) return;
                    if (!response.ok) throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'Could not import names.');
                    imported = Array.isArray(data.names) ? data.names : [];
                    if (!imported.length) importError = 'No recipient names were found in the selected file.';
                } catch (error) { if (ticket !== fileTicket.value) return; importError = error.message || 'Could not read the selected file.'; }
            }
        }
        if (ticket !== fileTicket.value) return;
        picked.textContent = importError || (selected ? `${imported.length.toLocaleString()} recipients found in ${selected.name}` : '');
        picked.classList.toggle('is-error', Boolean(importError));
        refreshNames(); resetSample();
    }
    const fileTicket = { value: 0 };

    function clearImport() {
        // Invalidate any asynchronous read to prevent late results re-adding old names.
        ++fileTicket.value;
        file.value = '';
        imported = [];
        importError = '';
        clearFile.hidden = true;
        filePlaceholder.textContent = 'No file selected';
        picked.classList.remove('is-error');
        picked.textContent = 'File removed. Manually entered recipients are unchanged.';
        refreshNames();
        resetSample();
        file.focus();
    }


    function validity() {
        const records = chosenNames();
        const invalid = records.find(name => [...name].length > 180 || /[\x00-\x1F\x7F]/.test(name));
        let error = importError;
        if (!error && records.length > maxRows) error = `Too many recipients. Maximum: ${maxRows.toLocaleString()}.`;
        if (!error && invalid) error = 'Recipient names must be 180 characters or fewer, without control characters.';
        if (!error && title.value.trim().length === 0) error = 'Add a batch title to continue.';
        if (!error && !records.length) error = 'Add at least one recipient.';
        if (!error && !picker.value) error = 'Select a certificate template.';
        if (!error && isTwoUp() && !supportsTwoUp()) error = 'Two per A4 portrait sheet needs a landscape template.';
        if (!error && hasAwards()) error=awardState(records).error;
        if (!error && !form.checkValidity()) error = 'Check the required fields and certificate numbering format.';
        if (!error && button.dataset.pdfEngineMissing === '1') error = 'PDF setup must be completed first.';
        return { error, records };
    }

    function refreshNames() {
        const all = allNames();
        const list = chosenNames();
        const duplicates = duplicateCount(all);
        refreshAwardFields();
        count.replaceChildren();
        const strong = document.createElement('b');
        strong.textContent = list.length.toLocaleString();
        count.append(strong, document.createTextNode(list.length === 1 ? ' recipient' : ' recipients'));
        if (reviewButton) { reviewButton.disabled = !list.length; reviewButton.hidden = !list.length; }
        summary.textContent = list.length ? `${list.length.toLocaleString()} PDF ${list.length === 1 ? 'certificate' : 'certificates'} planned.` : 'One certificate per recipient.';
        if (sheetPlan) sheetPlan.textContent = `${list.length.toLocaleString()} certificates on ${sheetCount(list.length).toLocaleString()} A4 portrait ${sheetCount(list.length) === 1 ? 'sheet' : 'sheets'}. ${list.length % 2 ? 'The last sheet will have a blank bottom half.' : 'Each sheet will have two different recipients.'} Each original certificate PDF stays available.`;
        if (twoUpOptions) twoUpOptions.hidden = !isTwoUp();
        if (layoutDescription) layoutDescription.textContent = isTwoUp()
            ? 'A4 portrait · two landscape certificates, one above the other.'
            : 'One certificate per page. Each recipient gets an individual PDF.';
        if (outputSummary) outputSummary.textContent = isTwoUp()
            ? `${list.length.toLocaleString()} ${list.length === 1 ? 'recipient' : 'recipients'} · ${sheetCount(list.length).toLocaleString()} A4 ${sheetCount(list.length) === 1 ? 'sheet' : 'sheets'}`
            : `${list.length.toLocaleString()} ${list.length === 1 ? 'recipient' : 'recipients'} · ${list.length.toLocaleString()} ${list.length === 1 ? 'PDF' : 'PDFs'}`;
        if (layoutWarning) layoutWarning.hidden = !isTwoUp() || supportsTwoUp();
        if (previewButton) previewButton.textContent = isTwoUp() ? 'Preview first A4 sheet' : 'Preview selected recipient';
        if (proofChoice) proofChoice.disabled = isTwoUp();
        const issues = [];
        if (duplicates) issues.push(removeDuplicates.checked
            ? `${duplicates} repeated ${duplicates === 1 ? 'name' : 'names'} will be removed.`
            : `${duplicates} repeated ${duplicates === 1 ? 'name' : 'names'} found. Check whether these are intended.`);
        const suspicious = list.filter(value => /[\\/:*?"<>|]/.test(value)).length;
        if (suspicious) issues.push(`${suspicious} ${suspicious === 1 ? 'name contains' : 'names contain'} filename characters; Certify will use safe PDF filenames.`);
        const state = validity();
        validation.replaceChildren();
        validation.classList.toggle('is-error', Boolean(state.error));
        if (state.error && state.records.length) issues.unshift(state.error);
        issues.forEach(issue => {
            const line = document.createElement('p');
            line.textContent = issue;
            validation.append(line);
        });
        button.disabled = Boolean(state.error);
        previewButton.disabled = !firstRecipient() || Boolean(importError) || button.dataset.pdfEngineMissing === '1';
        if (proofButton) proofButton.disabled = !chosenNames().length || Boolean(importError) || !picker.value;
        readiness.classList.toggle('is-ready', !state.error);
        const status = readiness.querySelector('span:last-child');
        if (status) status.textContent = state.error || `Ready to generate ${state.records.length.toLocaleString()} ${state.records.length === 1 ? 'certificate' : 'certificates'}.`;
    }

    function resetSample() {
        ++sampleSerial;
        sampleRequest?.abort();
        sampleRequest = null;
        if (sampleUrl) { URL.revokeObjectURL(sampleUrl); sampleUrl = null; }
        previewFeedback.textContent = '';
        preview.classList.remove('has-cutting-guide');
        previewCaption.textContent = 'Original design — preview recipients with the actual PDF renderer.';
        const selected = currentTemplate();
        if (selected) preview.style.aspectRatio = `${Math.max(1, Number(selected.width_mm) || 1)} / ${Math.max(1, Number(selected.height_mm) || 1)}`;
        const item = currentTemplate();
        if (!item) return;
        const isPdf = item.mime === 'application/pdf';
        const page = document.createElement(isPdf ? 'iframe' : 'img');
        if (isPdf) {
            page.title = 'Original certificate PDF preview';
            page.src = `${item.url}#toolbar=0&navpanes=0&scrollbar=0`;
            page.loading = 'lazy';
        } else {
            page.alt = 'Original certificate artwork';
            page.src = item.url;
        }
        preview.replaceChildren(page);
    }

    function refreshTemplate() {
        const item = currentTemplate();
        if (!item) return;
        preview.style.aspectRatio = `${Math.max(1, Number(item.width_mm) || 1)} / ${Math.max(1, Number(item.height_mm) || 1)}`;
        format.textContent = item.mime === 'application/pdf' ? 'PDF' : (item.mime === 'image/png' ? 'PNG' : (item.mime === 'image/jpeg' ? 'JPG' : 'Image'));
        previewName.textContent = item.name || 'Selected template';
        const landscape = Number(item.width_mm) >= Number(item.height_mm);
        dimensions.textContent = `${Math.round(Number(item.width_mm) || 0)} × ${Math.round(Number(item.height_mm) || 0)} mm · ${landscape ? 'Landscape' : 'Portrait'}`;
        edit.href = item.edit_url;
        original.href = item.url;
        refreshAwardFields();
        if (numberSettings) {
            const numbered = Boolean(item.has_certificate_number);
            numberSettings.hidden = !numbered;
            numberSettings.querySelectorAll('input, select').forEach(control => { control.disabled = !numbered; });
            filenameRule.textContent = numbered ? 'Output filename: Recipient Name - Certificate Number.pdf' : 'Output filename: Recipient Name.pdf';
        }
        resetSample();
        refreshNames();
    }

    async function previewRecipient() {
        const first = firstRecipient();
        if (!first || !picker.value) return;
        const serial = ++sampleSerial;
        sampleRequest?.abort();
        sampleRequest = new AbortController();
        previewButton.disabled = true;
        previewFeedback.textContent = `Rendering ${first}…`;
        try {
            const body = new FormData();
            body.set('_token', form.querySelector('input[name="_token"]').value);
            body.set('template_id', picker.value);
            body.set('recipient_name', first);
            if (hasAwards()) {
                const selectedIndex = isTwoUp() ? 0 : Math.max(0, chosenNames().indexOf(first));
                body.set('award_total', String(chosenNames().length));
                body.set('award_preview_index',String(selectedIndex));
            }
            if (isTwoUp()) {
                body.set('print_layout', 'two_up');
                body.set('second_recipient', chosenNames()[1] || '');
                if (cutGuide.checked) body.set('cutting_guide', '1');
                body.set('print_margin_mm', printMargin?.value || '3');
            }
            const response = await fetch(config.preview_url, {
                method: 'POST', body, signal: sampleRequest.signal,
                headers: { Accept: 'application/pdf, application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) {
                const error = await response.json().catch(() => ({}));
                throw new Error(error.message || `Preview unavailable (${response.status}).`);
            }
            const pdf = await response.blob();
            if (serial !== sampleSerial) return;
            if (sampleUrl) URL.revokeObjectURL(sampleUrl);
            sampleUrl = URL.createObjectURL(pdf);
            const frame = document.createElement('iframe');
            frame.title = `PDF sample for ${first}`;
            frame.src = `${sampleUrl}#toolbar=0&navpanes=0&scrollbar=0`;
            preview.replaceChildren(frame);
            if (isTwoUp()) preview.style.aspectRatio = '210 / 297';
            // A matching screen overlay keeps the guide visible even when the
            // browser's PDF viewer downsamples the actual PDF cut line.
            preview.classList.toggle('has-cutting-guide', isTwoUp() && cutGuide.checked);
            previewCaption.textContent = isTwoUp() ? `A4 sheet sample: ${first}${chosenNames()[1] ? ' / ' + chosenNames()[1] : ' (bottom blank)'}.` : `Actual PDF sample for ${first}.`;
            previewFeedback.textContent = isTwoUp() && cutGuide.checked
                ? 'Sample ready. The dashed line marks the exact horizontal cut and is included in the downloaded A4 PDF.'
                : 'Sample ready. Certificate numbers are generated when the batch is created.';
        } catch (err) {
            if (err.name !== 'AbortError' && serial === sampleSerial) previewFeedback.textContent = err.message;
        } finally {
            if (serial === sampleSerial) previewButton.disabled = false;
        }
    }

    function refreshNumberExample() {
        if (!prefix || !numberExample) return;
        const value = prefix.value.trim().toUpperCase();
        numberExample.textContent = numberFormat?.value === 'numeric'
            ? `${value ? value + '-' : ''}4827-1950-3264`
            : `${value ? value + '-' : ''}7K4M-2Q9P-B6TX`;
    }

    names.addEventListener('input', () => { refreshNames(); resetSample(); });
    title.addEventListener('input', refreshNames);
    picker.addEventListener('change', refreshTemplate);
    layoutChoices.forEach(choice => choice.addEventListener('change', () => { resetSample(); refreshNames(); }));
    cutGuide?.addEventListener('change', () => {
        const hadSheetPreview = Boolean(sampleUrl) && isTwoUp();
        resetSample();
        // Users should not have to click Preview again after toggling the line.
        if (hadSheetPreview) void previewRecipient();
    });
    printMargin?.addEventListener('change', () => { const old = Boolean(sampleUrl) && isTwoUp(); resetSample(); if (old) void previewRecipient(); });
    file.addEventListener('change', readImport);
    clearFile.addEventListener('click', clearImport);
    removeDuplicates.addEventListener('change', () => { refreshNames(); resetSample(); });
    prefix?.addEventListener('input', () => { refreshNumberExample(); refreshNames(); resetSample(); });
    numberFormat?.addEventListener('change', () => { refreshNumberExample(); resetSample(); });
    importToggle.addEventListener('click', () => {
        importPanel.hidden = !importPanel.hidden;
        importToggle.setAttribute('aria-expanded', String(!importPanel.hidden));
        if (!importPanel.hidden) file.focus();
    });
    previewButton.addEventListener('click', previewRecipient);
    reviewButton?.addEventListener('click', () => {
        reviewText.value = chosenNames().join('\n');
        if (typeof reviewDialog.showModal === 'function') reviewDialog.showModal();
        else reviewDialog.setAttribute('open', '');
    });
    byId('recipientReviewCancel')?.addEventListener('click', () => reviewDialog.close());
    byId('recipientReviewApply')?.addEventListener('click', () => {
        const edited = reviewText.value.split(/\r\n|\n|\r/g).map(normalize).filter(Boolean);
        if (edited.length > maxRows || edited.some(name => name.length > 180 || /[\x00-\x1F\x7F]/.test(name))) {
            window.alert(`Check the edited names. Each must be up to 180 characters; maximum ${maxRows} recipients.`); return;
        }
        // Materialize the reviewed list into the textarea so no CSV/XLSX
        // will be uploaded again or merged into it on form submission.
        names.value = edited.join('\n');
        removeDuplicates.checked = false;
        clearImport();
        reviewDialog.close();
        refreshNames(); resetSample();
    });
    proofChoice?.addEventListener('change', resetSample);
    proofButton?.addEventListener('click', async () => {
        const list = chosenNames();
        if (!list.length) return;
        proofMessage.textContent = 'Checking potential layout risks…';
        proofButton.disabled = true;
        try {
            const body = new FormData();
            body.set('_token', form.querySelector('input[name="_token"]').value);
            body.set('template_id', picker.value);
            list.forEach((name, index) => body.append(`names[${index}]`, name));
            const response = await fetch(config.proof_url, {method:'POST', body, headers:{'Accept':'application/json'}});
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'The proof check could not finish.');
            proofMessage.textContent = data.warnings.join(' ');
            proofMessage.classList.toggle('is-warning', data.warnings.some(value => /may|overflow|outside|short/i.test(value)));
        } catch(error) { proofMessage.textContent = error.message || 'Proofing could not finish.'; }
        finally { proofButton.disabled = false; }
    });
    window.addEventListener('beforeunload', () => { if (sampleUrl) URL.revokeObjectURL(sampleUrl); });

    function performSubmit() {
        button.disabled = true;
        button.textContent = 'Preparing certificates…';
        form.submit();
    }
    form.addEventListener('submit', event => {
        event.preventDefault();
        const state = validity();
        if (state.error) {
            if (!form.checkValidity()) form.reportValidity();
            else if (!title.value.trim()) title.focus();
            else names.focus();
            refreshNames();
            return;
        }
        if (!confirmed && state.records.length >= 25 && typeof confirmDialog.showModal === 'function') {
            byId('confirmTitle').textContent = title.value.trim();
            byId('confirmTemplate').textContent = currentTemplate()?.name || picker.selectedOptions[0]?.textContent || '';
            byId('confirmRecipients').textContent = `${state.records.length.toLocaleString()} certificates`;
            byId('confirmLayout').textContent = isTwoUp() ? `${sheetCount(state.records.length)} A4 portrait sheets` : 'Individual PDFs';
            confirmDialog.showModal();
            return;
        }
        performSubmit();
    });
    confirmCancel.addEventListener('click', () => confirmDialog.close());
    confirmGo.addEventListener('click', () => { confirmed = true; confirmDialog.close(); form.requestSubmit(button); });
    refreshNumberExample();
    refreshTemplate();
    refreshNames();
})();
