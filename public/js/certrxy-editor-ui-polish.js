/* Certrxy editor UI refinement: presentation only. Keep #certrxyTextCase as the source of truth. */
(() => {
  'use strict';
  const native = document.getElementById('certrxyTextCase');
  const container = native?.closest('.certrxy-case-control');
  if (!native || !container || container.querySelector('.certrxy-case-widget')) return;

  const widget = document.createElement('div');
  widget.className = 'certrxy-case-widget';
  const trigger = document.createElement('button');
  trigger.type = 'button';
  trigger.className = 'certrxy-case-trigger';
  trigger.setAttribute('aria-haspopup', 'listbox');
  trigger.setAttribute('aria-expanded', 'false');
  const listId = 'certrxy-case-options';
  trigger.setAttribute('aria-controls', listId);
  const selectedLabel = document.createElement('span');
  selectedLabel.className = 'certrxy-case-selected';
  const chevron = document.createElement('span');
  chevron.className = 'certrxy-case-chevron';
  chevron.setAttribute('aria-hidden', 'true');
  chevron.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
  trigger.append(selectedLabel, chevron);

  const list = document.createElement('div');
  list.className = 'certrxy-case-options';
  list.id = listId;
  list.setAttribute('role', 'listbox');
  list.setAttribute('aria-label', 'Change case');
  list.hidden = true;
  const optionButtons = Array.from(native.options).map((option, index) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'certrxy-case-option';
    button.setAttribute('role', 'option');
    button.dataset.caseValue = option.value;
    button.setAttribute('aria-selected', 'false');
    button.tabIndex = -1;
    const caption = document.createElement('span');
    caption.textContent = option.textContent;
    const check = document.createElement('span');
    check.className = 'certrxy-case-check';
    check.setAttribute('aria-hidden', 'true');
    check.textContent = '✓';
    button.append(caption, check);
    button.addEventListener('click', () => choose(index));
    list.append(button);
    return button;
  });
  widget.append(trigger, list);
  native.insertAdjacentElement('afterend', widget);
  native.classList.add('certrxy-case-native-hidden');
  native.tabIndex = -1;
  native.setAttribute('aria-hidden', 'true');

  let open = false;
  const sync = () => {
    const option = native.selectedOptions[0] || native.options[0];
    selectedLabel.textContent = option?.textContent || 'Original / No change';
    trigger.disabled = native.disabled;
    if (trigger.disabled && open) close();
    optionButtons.forEach(button => {
      const selected = button.dataset.caseValue === native.value;
      button.setAttribute('aria-selected', selected ? 'true' : 'false');
    });
  };
  function close(restoreFocus = false) {
    open = false;
    list.hidden = true;
    trigger.setAttribute('aria-expanded', 'false');
    if (restoreFocus) trigger.focus();
  }
  function show(focusValue = native.value) {
    if (trigger.disabled) return;
    open = true;
    list.hidden = false;
    trigger.setAttribute('aria-expanded', 'true');
    const index = Math.max(0, optionButtons.findIndex(b => b.dataset.caseValue === focusValue));
    optionButtons[index]?.focus();
  }
  function choose(index) {
    const button = optionButtons[index];
    if (!button || native.disabled) return;
    const value = button.dataset.caseValue;
    if (native.value !== value) {
      native.value = value;
      native.dispatchEvent(new Event('change', {bubbles: true}));
    }
    sync();
    close(true);
  }
  trigger.addEventListener('click', () => {if(open) close(); else show();});
  trigger.addEventListener('keydown', event => {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      show(event.key === 'ArrowUp' ? optionButtons.at(-1)?.dataset.caseValue : native.value);
    }
  });
  list.addEventListener('keydown', event => {
    const index = optionButtons.indexOf(document.activeElement);
    if (event.key === 'Escape') {event.preventDefault(); close(true); return;}
    if (event.key === 'Tab') {close(); return;}
    let next = index;
    if (event.key === 'ArrowDown') next = (index + 1) % optionButtons.length;
    else if (event.key === 'ArrowUp') next = (index - 1 + optionButtons.length) % optionButtons.length;
    else if (event.key === 'Home') next = 0;
    else if (event.key === 'End') next = optionButtons.length - 1;
    else if (event.key === 'Enter' || event.key === ' ') {event.preventDefault(); choose(index); return;}
    else return;
    event.preventDefault();
    optionButtons[next]?.focus();
  });
  document.addEventListener('pointerdown', event => {
    if (open && !widget.contains(event.target)) close();
  });
  // Editor JS programmatically updates native.value when users switch fields.
  // Sync after normal editor events, without patching its data/state logic.
  let queued = false;
  const queueSync = () => {
    if (queued) return;
    queued = true;
    requestAnimationFrame(() => {queued = false; sync();});
  };
  for (const eventName of ['click','change','input','keyup']) {
    document.addEventListener(eventName, queueSync);
  }
  native.addEventListener('change', queueSync);
  sync();
})();
