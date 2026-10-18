/* Certify: accessible, searchable font picker. Original <select> remains authoritative. */
(() => {
  'use strict';
  const ids = ['fontFamily', 'editorDetailsFont'];
  const key = 'certify.editor.fonts.recent.v3';
  const maxRecent = 3;
  const strip = text => String(text ?? '').replace(/\s*\((?:built-in|installed)\)\s*$/i, '').trim();
  let memoryRecent = [];
  const pickers = [];
  let opened = null;

  function getRecent() {
    try {
      const saved = JSON.parse(window.localStorage.getItem(key) || '[]');
      return Array.isArray(saved) ? saved.filter(item => typeof item === 'string').slice(0, maxRecent) : memoryRecent;
    } catch (_error) {
      return memoryRecent;
    }
  }
  function remember(id) {
    if (!id) return;
    memoryRecent = [id, ...getRecent().filter(old => old !== id)].slice(0, maxRecent);
    try { window.localStorage.setItem(key, JSON.stringify(memoryRecent)); } catch (_error) { /* private storage disabled */ }
  }
  // MS Word-style ordering: recently used stays newest-first, but the full
  // font list and search results are always sorted A–Z by display name.
  const fontCollator = new Intl.Collator('en', { sensitivity: 'base', numeric: true });
  function available(picker) {
    return Array.from(picker.select.options)
      .filter(opt => !opt.disabled && !opt.hidden && opt.value && opt.value !== '__mixed__')
      .map(opt => ({ id: opt.value, name: strip(opt.textContent) }))
      .sort((a, b) => fontCollator.compare(a.name, b.name) || fontCollator.compare(a.id, b.id));
  }
  function currentName(picker) {
    const selected = picker.select.selectedOptions?.[0] || picker.select.options[picker.select.selectedIndex];
    return selected ? strip(selected.textContent) : '';
  }
  function sync(picker, force = false) {
    if (force || opened !== picker || document.activeElement !== picker.input) {
      picker.input.value = currentName(picker);
    }
  }
  function close(focusInput = false) {
    if (!opened) return;
    const picker = opened;
    opened = null;
    picker.dropdown.hidden = true;
    picker.wrapper.classList.remove('is-open');
    picker.input.setAttribute('aria-expanded', 'false');
    picker.button.setAttribute('aria-expanded', 'false');
    picker.input.removeAttribute('aria-activedescendant');
    sync(picker, true);
    if (focusInput) picker.input.focus({ preventScroll: true });
  }
  function place(picker) {
    if (!picker || picker.dropdown.hidden) return;
    const rect = picker.wrapper.getBoundingClientRect();
    const width = Math.max(180, Math.min(rect.width, window.innerWidth - 24));
    const left = Math.max(12, Math.min(rect.left, window.innerWidth - width - 12));
    const below = window.innerHeight - rect.bottom - 12;
    const above = rect.top - 12;
    const putAbove = below < 190 && above > below;
    const room = Math.max(90, putAbove ? above : below);
    const height = Math.min(265, room);
    picker.dropdown.style.width = `${width}px`;
    picker.dropdown.style.left = `${left}px`;
    picker.dropdown.style.maxHeight = `${height}px`;
    picker.dropdown.style.top = `${putAbove ? Math.max(8, rect.top - height - 5) : rect.bottom + 5}px`;
  }
  function choose(picker, id) {
    if (!available(picker).some(item => item.id === id)) return;
    if (picker.select.value !== id) {
      picker.select.value = id;
      picker.select.dispatchEvent(new Event('change', { bubbles: true }));
    }
    remember(id);
    close(true);
    pickers.forEach(p => sync(p, true));
  }
  function addHeading(target, label) {
    const heading = document.createElement('div');
    heading.className = 'cfp-heading';
    heading.textContent = label;
    target.appendChild(heading);
  }
  function draw(picker, query = '') {
    const all = available(picker);
    const match = query.trim().toLocaleLowerCase();
    const list = picker.list;
    list.replaceChildren();
    picker.entries = [];

    const addOption = item => {
      const index = picker.entries.length;
      picker.entries.push(item);
      const option = document.createElement('div');
      option.id = `${picker.select.id}-picker-option-${index}`;
      option.className = 'cfp-item';
      option.setAttribute('role', 'option');
      option.setAttribute('aria-selected', String(item.id === picker.select.value));
      const name = document.createElement('span');
      name.textContent = item.name;
      option.appendChild(name);
      if (item.id === picker.select.value) {
        const tick = document.createElement('span');
        tick.className = 'cfp-tick';
        tick.textContent = '✓';
        tick.setAttribute('aria-hidden', 'true');
        option.appendChild(tick);
      }
      option.addEventListener('pointerdown', event => event.preventDefault());
      option.addEventListener('click', () => choose(picker, item.id));
      list.appendChild(option);
    };

    if (match) {
      all.filter(item => item.name.toLocaleLowerCase().includes(match)).forEach(addOption);
    } else {
      const recent = getRecent().map(id => all.find(item => item.id === id)).filter(Boolean);
      if (recent.length) {
        addHeading(list, 'Recently used');
        recent.forEach(addOption);
      }
      addHeading(list, 'All fonts');
      all.filter(item => !recent.some(recentItem => recentItem.id === item.id)).forEach(addOption);
    }

    if (!picker.entries.length) {
      const empty = document.createElement('div');
      empty.className = 'cfp-empty';
      empty.textContent = 'No matching fonts';
      list.appendChild(empty);
      picker.active = -1;
      picker.input.removeAttribute('aria-activedescendant');
      return;
    }
    // Keep Recently used visible at top. Keyboard navigation starts at first option.
    picker.active = 0;
    updateActive(picker, false);
    list.scrollTop = 0;
  }
  function updateActive(picker, scroll = true) {
    if (!picker.entries.length) return;
    picker.active = (picker.active + picker.entries.length) % picker.entries.length;
    const options = picker.list.querySelectorAll('[role="option"]');
    options.forEach((opt, i) => opt.classList.toggle('is-keyboard-active', i === picker.active));
    const item = options[picker.active];
    if (item) {
      picker.input.setAttribute('aria-activedescendant', item.id);
      if (scroll) item.scrollIntoView({ block: 'nearest' });
    }
  }
  function open(picker, query = '') {
    if (opened && opened !== picker) close();
    opened = picker;
    picker.dropdown.hidden = false;
    picker.wrapper.classList.add('is-open');
    picker.input.setAttribute('aria-expanded', 'true');
    picker.button.setAttribute('aria-expanded', 'true');
    draw(picker, query);
    place(picker);
  }

  function init() {
    // Never require a pre-existing marker: initial state is an untouched native select.
    ids.forEach(id => {
      const select = document.getElementById(id);
      if (!select || select.dataset.cfpWordReady === 'yes') return;
      select.dataset.cfpWordReady = 'yes';

      const host = document.createElement('div');
      host.className = 'cfp-host';
      select.parentNode.insertBefore(host, select);
      host.appendChild(select);
      select.classList.add('cfp-native');
      select.tabIndex = -1;
      select.setAttribute('aria-hidden', 'true');

      const wrapper = document.createElement('div');
      wrapper.className = 'cfp-combo';
      host.appendChild(wrapper);
      const input = document.createElement('input');
      input.type = 'text';
      input.className = 'cfp-input';
      input.autocomplete = 'off';
      input.spellcheck = false;
      input.placeholder = 'Search fonts';
      input.id = `${id}-word-search`;
      input.setAttribute('role', 'combobox');
      input.setAttribute('aria-autocomplete', 'list');
      input.setAttribute('aria-expanded', 'false');
      input.setAttribute('aria-label', id === 'fontFamily' ? 'Search font family' : 'Search signatory font');
      wrapper.appendChild(input);
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'cfp-arrow';
      button.setAttribute('aria-label', 'Show fonts');
      button.setAttribute('aria-expanded', 'false');
      button.setAttribute('aria-haspopup', 'listbox');
      button.innerHTML = '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="m3.5 6 4.5 4 4.5-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/></svg>';
      wrapper.appendChild(button);

      const label = document.querySelector(`label[for="${id}"]`);
      if (label) label.htmlFor = input.id;
      const dropdown = document.createElement('div');
      dropdown.className = 'cfp-dropdown';
      dropdown.hidden = true;
      const list = document.createElement('div');
      list.className = 'cfp-list';
      list.id = `${id}-word-list`;
      list.setAttribute('role', 'listbox');
      list.setAttribute('aria-label', 'Available fonts');
      dropdown.appendChild(list);
      input.setAttribute('aria-controls', list.id);
      button.setAttribute('aria-controls', list.id);
      document.body.appendChild(dropdown);

      const picker = { select, host, wrapper, input, button, dropdown, list, entries: [], active: 0 };
      pickers.push(picker);
      sync(picker, true);

      input.addEventListener('focus', () => {
        if (opened !== picker) open(picker);
        input.select(); // Typing replaces current font name, like Word.
      });
      input.addEventListener('click', () => {
        if (opened !== picker) open(picker);
        input.select();
      });
      input.addEventListener('input', () => {
        if (opened !== picker) open(picker, input.value);
        else { draw(picker, input.value); place(picker); }
      });
      input.addEventListener('keydown', event => {
        if (event.key === 'Escape') { if (opened === picker) { event.preventDefault(); close(true); } }
        else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
          event.preventDefault();
          if (opened !== picker) open(picker);
          else { picker.active += event.key === 'ArrowDown' ? 1 : -1; updateActive(picker); }
        }
        else if (event.key === 'Enter' && opened === picker) {
          event.preventDefault();
          const selected = picker.entries[picker.active];
          if (selected) choose(picker, selected.id);
        }
        else if (event.key === 'Tab' && opened === picker) close();
      });
      button.addEventListener('click', () => {
        if (opened === picker) { close(); return; }
        open(picker);
        input.focus({ preventScroll: true });
      });
      select.addEventListener('change', () => sync(picker, true));
    });

    const selectionTitle = document.getElementById('elementTitle');
    if (selectionTitle) {
      new MutationObserver(() => pickers.forEach(p => sync(p, true)))
        .observe(selectionTitle, { characterData: true, childList: true, subtree: true });
    }
    document.addEventListener('pointerdown', event => {
      if (opened && !opened.wrapper.contains(event.target) && !opened.dropdown.contains(event.target)) close();
    }, true);
    document.addEventListener('focusin', event => {
      if (opened && !opened.wrapper.contains(event.target) && !opened.dropdown.contains(event.target)) close();
    });
    window.addEventListener('resize', () => { if (opened) place(opened); });
    document.addEventListener('scroll', () => { if (opened) place(opened); }, true);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();
