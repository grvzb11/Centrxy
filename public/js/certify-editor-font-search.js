/* MS Word-like searchable font picker for Certify template editor. */
(() => {
  'use strict';
  const root = document.querySelector('.certify-editor-page');
  if (!root) return;

  const IDS = ['fontFamily', 'editorDetailsFont'];
  const RECENT_LIMIT = 3;
  const plainName = (text) => String(text || '').replace(/\s*\((?:built-in|installed)\)\s*$/i, '').trim();
  const controls = [];
  let openControl = null;

  function storageKey(control) {
    return 'certify.fontpicker.recent.' + control.select.id;
  }

  function loadRecent(control) {
    try {
      const raw = localStorage.getItem(storageKey(control));
      const parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed.filter(v => typeof v === 'string') : [];
    } catch {
      return [];
    }
  }

  function saveRecent(control, value) {
    if (!value) return;
    const next = [value, ...loadRecent(control).filter(v => v !== value)].slice(0, RECENT_LIMIT);
    try { localStorage.setItem(storageKey(control), JSON.stringify(next)); } catch {}
  }

  function getAllEntries(control) {
    return Array.from(control.select.options)
      .filter(option => !option.disabled && !option.hidden && option.value !== '__mixed__')
      .map(option => ({ id: option.value, label: plainName(option.textContent) }));
  }

  function selectedLabel(control) {
    const selected = control.select.options[control.select.selectedIndex];
    return selected ? plainName(selected.textContent) : '';
  }

  function syncInput(control, forceSelected = false) {
    if (forceSelected || openControl !== control || !control.input.matches(':focus')) {
      control.input.value = selectedLabel(control);
    }
    control.input.title = selectedLabel(control) || 'Choose a font';
  }

  function closeMenu(returnFocus = false, commitDisplay = true) {
    if (!openControl) return;
    const current = openControl;
    openControl = null;
    current.menu.hidden = true;
    current.combo.classList.remove('is-open');
    current.input.setAttribute('aria-expanded', 'false');
    current.button.setAttribute('aria-expanded', 'false');
    current.input.removeAttribute('aria-activedescendant');
    if (commitDisplay) syncInput(current, true);
    if (returnFocus) current.input.focus({ preventScroll: true });
  }

  function placeMenu(control) {
    if (control.menu.hidden) return;
    const bounds = control.combo.getBoundingClientRect();
    const viewportHeight = window.innerHeight;
    const viewportWidth = window.innerWidth;
    const width = Math.min(Math.max(bounds.width, 240), viewportWidth - 20);
    const left = Math.min(Math.max(bounds.left, 10), Math.max(10, viewportWidth - width - 10));
    const spaceBelow = viewportHeight - bounds.bottom - 10;
    const spaceAbove = bounds.top - 10;
    const upward = spaceBelow < 240 && spaceAbove > spaceBelow;
    const maxHeight = Math.min(330, Math.max(160, upward ? spaceAbove : spaceBelow));
    control.menu.style.width = width + 'px';
    control.menu.style.left = left + 'px';
    control.menu.style.maxHeight = maxHeight + 'px';
    control.menu.style.top = upward ? Math.max(8, bounds.top - maxHeight - 4) + 'px' : Math.min(viewportHeight - 8, bounds.bottom + 4) + 'px';
  }

  function setActive(control, index, scroll = true) {
    if (!control.entries.length) return;
    control.activeIndex = (index + control.entries.length) % control.entries.length;
    const options = control.list.querySelectorAll('[role="option"]');
    options.forEach((option, i) => option.classList.toggle('is-active', i === control.activeIndex));
    const active = options[control.activeIndex];
    if (active) {
      control.input.setAttribute('aria-activedescendant', active.id);
      if (scroll) active.scrollIntoView({ block: 'nearest' });
    }
  }

  function appendSectionLabel(list, text) {
    const label = document.createElement('div');
    label.className = 'certify-font-search-section-label';
    label.textContent = text;
    list.appendChild(label);
  }

  function appendDivider(list) {
    const divider = document.createElement('div');
    divider.className = 'certify-font-search-divider';
    list.appendChild(divider);
  }

  function appendOption(control, list, entry, selectedId) {
    const index = control.entries.length;
    control.entries.push(entry);
    const item = document.createElement('div');
    item.id = `certify-font-${control.select.id}-choice-${index}`;
    item.className = 'certify-font-search-option';
    item.setAttribute('role', 'option');
    item.setAttribute('aria-selected', String(entry.id === selectedId));
    item.textContent = entry.label;
    item.addEventListener('pointerdown', e => e.preventDefault());
    item.addEventListener('click', () => selectValue(control, entry.id));
    list.appendChild(item);
  }

  function renderOptions(control, query = '') {
    const list = control.list;
    list.replaceChildren();
    control.entries = [];
    const selectedId = control.select.value;
    const all = getAllEntries(control);
    const filter = query.trim().toLocaleLowerCase();

    if (!filter) {
      const recentIds = loadRecent(control);
      const recentEntries = recentIds
        .map(id => all.find(entry => entry.id === id))
        .filter(Boolean)
        .slice(0, RECENT_LIMIT);

      if (recentEntries.length) {
        appendSectionLabel(list, 'Recently used');
        recentEntries.forEach(entry => appendOption(control, list, entry, selectedId));
        appendDivider(list);
      }

      appendSectionLabel(list, 'All fonts');
      all.filter(entry => !recentEntries.some(recent => recent.id === entry.id))
         .forEach(entry => appendOption(control, list, entry, selectedId));
    } else {
      const filtered = all.filter(entry => entry.label.toLocaleLowerCase().includes(filter));
      if (!filtered.length) {
        const empty = document.createElement('div');
        empty.className = 'certify-font-no-results';
        empty.textContent = 'No matching fonts';
        list.appendChild(empty);
        control.activeIndex = -1;
        control.input.removeAttribute('aria-activedescendant');
        return;
      }
      filtered.forEach(entry => appendOption(control, list, entry, selectedId));
    }

    if (!control.entries.length) {
      const empty = document.createElement('div');
      empty.className = 'certify-font-no-results';
      empty.textContent = 'No fonts available';
      list.appendChild(empty);
      control.activeIndex = -1;
      control.input.removeAttribute('aria-activedescendant');
      return;
    }

    control.activeIndex = Math.max(0, control.entries.findIndex(entry => entry.id === selectedId));
    setActive(control, control.activeIndex, false);
  }

  function selectValue(control, value) {
    if (control.select.value !== value) {
      control.select.value = value;
      control.select.dispatchEvent(new Event('change', { bubbles: true }));
    }
    saveRecent(control, value);
    syncInput(control, true);
    closeMenu(true, true);
  }

  function showMenu(control, preserveQuery = false) {
    if (openControl && openControl !== control) closeMenu();
    openControl = control;
    control.menu.hidden = false;
    control.combo.classList.add('is-open');
    control.input.setAttribute('aria-expanded', 'true');
    control.button.setAttribute('aria-expanded', 'true');
    const query = preserveQuery ? control.input.value : '';
    if (!preserveQuery) control.input.value = '';
    renderOptions(control, query);
    placeMenu(control);
  }

  function ensureOpenForInput(control) {
    if (openControl !== control) showMenu(control, true);
    else {
      renderOptions(control, control.input.value);
      placeMenu(control);
    }
  }

  IDS.forEach(id => {
    const select = document.getElementById(id);
    if (!select || select.dataset.certifySearchReady !== '1') return;
    // Rebuild only if refined UI not already applied.
    const existingHolder = select.parentNode;
    if (!existingHolder || !existingHolder.classList.contains('certify-font-search-holder')) return;
    select.classList.add('certify-font-original');
    select.setAttribute('aria-hidden', 'true');
    select.tabIndex = -1;

    // Clear old visible controls inside holder but keep select.
    Array.from(existingHolder.children).forEach(child => { if (child !== select) child.remove(); });

    const combo = document.createElement('div');
    combo.className = 'certify-font-combo';
    existingHolder.appendChild(combo);

    const icon = document.createElement('span');
    icon.className = 'certify-font-combo-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = '🔎';
    existingHolder.appendChild(icon);

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'certify-font-combo-input';
    input.placeholder = 'Search fonts...';
    input.autocomplete = 'off';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-label', id === 'fontFamily' ? 'Search font family' : 'Search signatory font');
    input.id = id + 'SearchField';
    combo.appendChild(input);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'certify-font-combo-button';
    button.setAttribute('aria-label', 'Show font options');
    button.setAttribute('aria-haspopup', 'listbox');
    button.setAttribute('aria-expanded', 'false');
    const arrow = document.createElement('span');
    arrow.className = 'certify-font-search-chevron';
    arrow.setAttribute('aria-hidden', 'true');
    arrow.textContent = '▼';
    button.appendChild(arrow);
    combo.appendChild(button);

    const label = document.querySelector('label[for="' + id + '"]');
    if (label) label.htmlFor = input.id;

    const menu = document.createElement('div');
    menu.className = 'certify-font-search-menu';
    menu.hidden = true;
    const list = document.createElement('div');
    list.id = 'certify-font-' + id + '-list';
    list.className = 'certify-font-search-results';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', 'Available fonts');
    input.setAttribute('aria-controls', list.id);
    button.setAttribute('aria-controls', list.id);
    menu.appendChild(list);
    document.body.appendChild(menu);

    const control = { select, combo, input, button, menu, list, entries: [], activeIndex: 0 };
    controls.push(control);
    syncInput(control, true);

    input.addEventListener('focus', () => ensureOpenForInput(control));
    input.addEventListener('input', () => ensureOpenForInput(control));
    input.addEventListener('click', () => ensureOpenForInput(control));
    input.addEventListener('keydown', event => {
      if (event.key === 'Escape') { event.preventDefault(); closeMenu(true, true); }
      else if (event.key === 'ArrowDown') { event.preventDefault(); ensureOpenForInput(control); setActive(control, control.activeIndex + 1); }
      else if (event.key === 'ArrowUp') { event.preventDefault(); ensureOpenForInput(control); setActive(control, control.activeIndex - 1); }
      else if (event.key === 'Enter') {
        if (openControl === control) {
          event.preventDefault();
          const entry = control.entries[control.activeIndex];
          if (entry) selectValue(control, entry.id);
        }
      } else if (event.key === 'Tab') {
        closeMenu(false, true);
      }
    });
    button.addEventListener('click', () => {
      if (openControl === control) closeMenu(true, true);
      else {
        input.focus({ preventScroll: true });
        ensureOpenForInput(control);
      }
    });
    select.addEventListener('change', () => syncInput(control, true));
  });

  const selectionTitle = document.getElementById('elementTitle');
  if (selectionTitle) new MutationObserver(() => controls.forEach(control => syncInput(control, true)))
    .observe(selectionTitle, { childList: true, characterData: true, subtree: true });

  document.addEventListener('pointerdown', event => {
    if (!openControl) return;
    if (!openControl.menu.contains(event.target) && !openControl.combo.contains(event.target)) closeMenu();
  }, true);
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && openControl) closeMenu(true, true); });
  document.addEventListener('focusin', event => {
    if (!openControl) return;
    if (!openControl.menu.contains(event.target) && !openControl.combo.contains(event.target)) closeMenu(false, true);
  });
  window.addEventListener('resize', () => { if (openControl) placeMenu(openControl); });
  document.addEventListener('scroll', () => { if (openControl) placeMenu(openControl); }, true);
})();
