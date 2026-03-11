/* ============================================================
   Clear WebApp – app.js
   Screens: auth → lists → items
   Features: swipe-to-delete/complete, drag-to-reorder,
             multi-device sync via polling
============================================================ */

'use strict';

// ── API wrapper ─────────────────────────────────────────────
const api = (() => {
  async function req(action, data = null, method = null) {
    const isGet = data === null || method === 'GET';
    const url   = `api.php?action=${action}${isGet && data ? '&' + new URLSearchParams(data) : ''}`;
    const opts  = { credentials: 'same-origin', headers: { 'Content-Type': 'application/json' } };
    if (!isGet) { opts.method = 'POST'; opts.body = JSON.stringify(data); }
    const res  = await fetch(url, opts);
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'Unbekannter Fehler');
    return json.data;
  }
  return {
    get:  (action, params) => req(action, params, 'GET'),
    post: (action, body)   => req(action, body,   'POST'),
  };
})();

// ── State ───────────────────────────────────────────────────
const state = {
  username:    null,
  lists:       [],        // [{id,title,color,position}]
  currentList: null,      // list object
  items:       [],        // [{id,text,completed,position}]
  syncTimer:   null,
};

// ── Screens ─────────────────────────────────────────────────
function showScreen(id) {
  document.querySelectorAll('.screen').forEach(s => {
    s.classList.remove('active', 'slide-out');
  });
  const next = document.getElementById(id);
  next.classList.add('active');
}

// ── Auth ─────────────────────────────────────────────────────
const authScreen    = document.getElementById('screen-auth');
const formLogin     = document.getElementById('form-login');
const formRegister  = document.getElementById('form-register');
const authError     = document.getElementById('auth-error');

document.getElementById('btn-show-register').addEventListener('click', () => {
  formLogin.classList.add('hidden');
  formRegister.classList.remove('hidden');
  authError.classList.add('hidden');
});

document.getElementById('btn-show-login').addEventListener('click', () => {
  formRegister.classList.add('hidden');
  formLogin.classList.remove('hidden');
  authError.classList.add('hidden');
});

formLogin.addEventListener('submit', async e => {
  e.preventDefault();
  authError.classList.add('hidden');
  try {
    const d = await api.post('login', {
      username: document.getElementById('auth-username').value,
      password: document.getElementById('auth-password').value,
    });
    onLoggedIn(d.username);
  } catch (err) {
    authError.textContent = err.message;
    authError.classList.remove('hidden');
  }
});

formRegister.addEventListener('submit', async e => {
  e.preventDefault();
  authError.classList.add('hidden');
  try {
    const d = await api.post('register', {
      username: document.getElementById('reg-username').value,
      password: document.getElementById('reg-password').value,
    });
    onLoggedIn(d.username);
  } catch (err) {
    authError.textContent = err.message;
    authError.classList.remove('hidden');
  }
});

document.getElementById('btn-logout').addEventListener('click', async () => {
  await api.post('logout');
  stopSync();
  state.username = null;
  state.lists    = [];
  showScreen('screen-auth');
});

async function checkSession() {
  try {
    const d = await api.get('me');
    onLoggedIn(d.username);
  } catch {
    showScreen('screen-auth');
  }
}

function onLoggedIn(username) {
  state.username = username;
  showScreen('screen-lists');
  loadLists();
  startSync();
}

// ── Lists view ───────────────────────────────────────────────
const listContainer = document.getElementById('list-container');
const listsEmpty    = document.getElementById('lists-empty');

async function loadLists() {
  state.lists = await api.get('lists');
  renderLists();
}

function renderLists() {
  listContainer.innerHTML = '';
  listsEmpty.classList.toggle('hidden', state.lists.length > 0);

  state.lists.forEach(list => {
    const li = document.createElement('li');
    li.className   = 'list-card';
    li.dataset.id  = list.id;

    // Background color
    const bg = document.createElement('div');
    bg.className = 'list-card__bg';
    bg.style.background = `linear-gradient(135deg, ${list.color}cc, ${list.color}88)`;

    // Swipe hint (delete)
    const hint = document.createElement('div');
    hint.className = 'list-card__swipe-hint list-card__swipe-hint--delete';
    hint.textContent = '🗑 Löschen';

    // Content
    const content = document.createElement('div');
    content.className = 'list-card__content';
    content.innerHTML = `
      <span class="list-card__title">${esc(list.title)}</span>
      <span class="list-card__count" data-list-count="${list.id}"></span>
      <span class="drag-handle" data-drag>&#x2630;</span>`;

    li.append(bg, hint, content);
    listContainer.appendChild(li);

    // Open list on tap (not on drag handle)
    content.addEventListener('click', e => {
      if (e.target.closest('[data-drag]')) return;
      openList(list);
    });

    attachSwipe(li, null, () => deleteList(list.id));
    loadItemCount(list.id);
  });

  makeDraggable(listContainer, 'list-card', saveListOrder);
}

async function loadItemCount(listId) {
  try {
    const items = await api.get('items', { list_id: listId });
    const open  = items.filter(i => !i.completed).length;
    const el    = document.querySelector(`[data-list-count="${listId}"]`);
    if (el) el.textContent = open > 0 ? `${open} offen` : (items.length ? '✓ alles erledigt' : '');
  } catch { /* ignore */ }
}

document.getElementById('btn-add-list').addEventListener('click', () => {
  openModal('Neue Liste', '', true, async ({ text, color }) => {
    if (!text) return;
    const list = await api.post('lists/create', { title: text, color });
    state.lists.push(list);
    renderLists();
  });
});

async function deleteList(id) {
  await api.post('lists/delete', { id });
  state.lists = state.lists.filter(l => l.id !== id);
  renderLists();
}

async function saveListOrder(ids) {
  await api.post('lists/reorder', { ids });
}

// ── Items view ───────────────────────────────────────────────
const itemContainer = document.getElementById('item-container');
const itemsEmpty    = document.getElementById('items-empty');
const itemsTitle    = document.getElementById('items-title');
const itemsHeader   = document.getElementById('items-header');

function openList(list) {
  state.currentList = list;
  itemsTitle.textContent = list.title;
  itemsHeader.style.borderBottom = `2px solid ${list.color}`;
  showScreen('screen-items');
  loadItems();
}

document.getElementById('btn-back').addEventListener('click', () => {
  showScreen('screen-lists');
  loadLists(); // refresh counts
});

async function loadItems() {
  state.items = await api.get('items', { list_id: state.currentList.id });
  renderItems();
}

function renderItems() {
  itemContainer.innerHTML = '';
  itemsEmpty.classList.toggle('hidden', state.items.length > 0);

  state.items.forEach(item => {
    const li = document.createElement('li');
    li.className = 'item-row' + (item.completed ? ' completed' : '');
    li.dataset.id = item.id;

    li.innerHTML = `
      <div class="item-row__swipe-hint item-row__swipe-hint--done">✓ Erledigt</div>
      <div class="item-row__swipe-hint item-row__swipe-hint--delete">🗑 Löschen</div>
      <div class="item-row__inner">
        <div class="item-check">${item.completed ? '✓' : ''}</div>
        <span class="item-row__text">${esc(item.text)}</span>
        <span class="drag-handle" data-drag>&#x2630;</span>
      </div>`;

    // tap check circle = toggle complete
    li.querySelector('.item-check').addEventListener('click', () => toggleItem(item));

    itemContainer.appendChild(li);

    attachSwipe(
      li,
      () => toggleItem(item),          // swipe right  → complete
      () => deleteItem(item.id)        // swipe left   → delete
    );
  });

  makeDraggable(itemContainer, 'item-row', saveItemOrder);
}

async function toggleItem(item) {
  item.completed = !item.completed;
  await api.post('items/update', { id: item.id, completed: item.completed });
  renderItems();
}

document.getElementById('btn-add-item').addEventListener('click', () => {
  openModal('Neues Element', '', false, async ({ text }) => {
    if (!text) return;
    const item = await api.post('items/create', { list_id: state.currentList.id, text });
    state.items.push(item);
    renderItems();
  });
});

async function deleteItem(id) {
  await api.post('items/delete', { id });
  state.items = state.items.filter(i => i.id !== id);
  renderItems();
}

async function saveItemOrder(ids) {
  await api.post('items/reorder', { ids, list_id: state.currentList.id });
}

// Rename list by tapping title
itemsTitle.addEventListener('click', () => {
  if (!state.currentList) return;
  openModal('Liste umbenennen', state.currentList.title, false, async ({ text }) => {
    if (!text) return;
    await api.post('lists/update', { id: state.currentList.id, title: text });
    state.currentList.title = text;
    itemsTitle.textContent  = text;
    const l = state.lists.find(x => x.id === state.currentList.id);
    if (l) l.title = text;
  });
});

// ── Modal ────────────────────────────────────────────────────
const modalOverlay = document.getElementById('modal-overlay');
const modalLabel   = document.getElementById('modal-label');
const modalInput   = document.getElementById('modal-input');
const modalColor   = document.getElementById('modal-color');
const modalColorW  = document.getElementById('modal-color-wrap');
const modalOk      = document.getElementById('modal-ok');
const modalCancel  = document.getElementById('modal-cancel');

let _modalCallback = null;

function openModal(label, defaultText, showColor, callback) {
  modalLabel.textContent = label;
  modalInput.value       = defaultText;
  modalColor.value       = '#' + Math.floor(Math.random() * 0xffffff).toString(16).padStart(6, '0');
  modalColorW.classList.toggle('hidden', !showColor);
  modalOverlay.classList.remove('hidden');
  _modalCallback = callback;
  setTimeout(() => { modalInput.focus(); modalInput.select(); }, 80);
}

function closeModal() { modalOverlay.classList.add('hidden'); _modalCallback = null; }

modalOk.addEventListener('click', () => {
  const text  = modalInput.value.trim();
  const color = modalColor.value;
  if (_modalCallback) _modalCallback({ text, color });
  closeModal();
});

modalCancel.addEventListener('click', closeModal);

modalInput.addEventListener('keydown', e => {
  if (e.key === 'Enter') modalOk.click();
  if (e.key === 'Escape') closeModal();
});

modalOverlay.addEventListener('click', e => {
  if (e.target === modalOverlay) closeModal();
});

// ── Swipe gestures ───────────────────────────────────────────
function attachSwipe(el, onSwipeRight, onSwipeLeft) {
  let startX, startY, dx = 0;
  const THRESHOLD = 80; // px to trigger action
  const MAX_V     = 20; // ignore if vertical scroll

  const inner = el.querySelector('.item-row__inner, .list-card__content');

  function onStart(e) {
    const t = e.touches ? e.touches[0] : e;
    startX = t.clientX;
    startY = t.clientY;
    dx     = 0;
  }

  function onMove(e) {
    if (!startX) return;
    const t  = e.touches ? e.touches[0] : e;
    dx       = t.clientX - startX;
    const dy = Math.abs(t.clientY - startY);
    if (dy > MAX_V && Math.abs(dx) < dy) { cancel(); return; }

    e.preventDefault(); // prevent scroll while swiping
    if (inner) inner.style.transform = `translateX(${dx}px)`;

    const hintDone   = el.querySelector('.item-row__swipe-hint--done, .list-card__swipe-hint--done');
    const hintDelete = el.querySelector('.item-row__swipe-hint--delete, .list-card__swipe-hint--delete');
    const progress   = Math.min(Math.abs(dx) / THRESHOLD, 1);

    if (hintDone)   hintDone.style.opacity   = dx > 0 ? progress : 0;
    if (hintDelete) hintDelete.style.opacity = dx < 0 ? progress : 0;
  }

  function onEnd() {
    if (!startX) return;
    if (Math.abs(dx) >= THRESHOLD) {
      if (dx > 0 && onSwipeRight) {
        snapOut(el, 'right', onSwipeRight);
      } else if (dx < 0 && onSwipeLeft) {
        snapOut(el, 'left', onSwipeLeft);
      } else {
        reset();
      }
    } else {
      reset();
    }
    startX = null;
  }

  function cancel() { reset(); startX = null; }

  function reset() {
    if (inner) {
      inner.style.transition = 'transform .2s ease';
      inner.style.transform  = 'translateX(0)';
    }
    const hints = el.querySelectorAll('.item-row__swipe-hint, .list-card__swipe-hint');
    hints.forEach(h => h.style.opacity = 0);
    setTimeout(() => { if (inner) inner.style.transition = ''; }, 220);
  }

  function snapOut(el, dir, cb) {
    if (inner) {
      inner.style.transition = 'transform .18s ease';
      inner.style.transform  = `translateX(${dir === 'right' ? '120%' : '-120%'})`;
    }
    el.style.transition = 'opacity .18s, max-height .28s .1s, margin .28s .1s, padding .28s .1s';
    el.style.opacity    = '0';
    el.style.maxHeight  = el.offsetHeight + 'px';
    setTimeout(() => {
      el.style.maxHeight = '0';
      el.style.margin    = '0';
    }, 10);
    setTimeout(cb, 320);
  }

  el.addEventListener('touchstart', onStart, { passive: true });
  el.addEventListener('touchmove',  onMove,  { passive: false });
  el.addEventListener('touchend',   onEnd);

  // Mouse swipe support (desktop)
  el.addEventListener('mousedown', e => { if (e.target.closest('[data-drag]')) return; onStart(e); });
  window.addEventListener('mousemove', e => { if (startX) onMove(e); });
  window.addEventListener('mouseup',   e => { if (startX) onEnd(e); });
}

// ── Drag to reorder ──────────────────────────────────────────
function makeDraggable(container, itemClass, onReorder) {
  let ghost = null, dragged = null, placeholder = null;

  container.addEventListener('mousedown',  startDrag);
  container.addEventListener('touchstart', startDrag, { passive: true });

  function startDrag(e) {
    const handle = e.target.closest('[data-drag]');
    if (!handle) return;
    const item = handle.closest('.' + itemClass);
    if (!item) return;
    e.preventDefault?.();

    dragged     = item;
    const rect  = item.getBoundingClientRect();
    const touch = e.touches ? e.touches[0] : e;

    ghost = item.cloneNode(true);
    ghost.classList.add('drag-ghost');
    ghost.style.width  = rect.width  + 'px';
    ghost.style.height = rect.height + 'px';
    ghost.style.top    = rect.top    + 'px';
    ghost.style.left   = rect.left   + 'px';
    document.body.appendChild(ghost);

    placeholder = document.createElement('li');
    placeholder.style.height     = rect.height + 'px';
    placeholder.style.opacity    = '0';
    placeholder.style.flexShrink = '0';
    item.after(placeholder);
    item.classList.add('dragging');

    const offsetY = touch.clientY - rect.top;

    function onMove(e) {
      const t = e.touches ? e.touches[0] : e;
      const y = t.clientY;
      ghost.style.top = (y - offsetY) + 'px';

      const below = document.elementFromPoint(t.clientX, y);
      const target = below?.closest('.' + itemClass);
      if (target && target !== dragged) {
        const r   = target.getBoundingClientRect();
        const mid = r.top + r.height / 2;
        target.parentNode.insertBefore(placeholder, y < mid ? target : target.nextSibling);
      }
    }

    function onEnd() {
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('mouseup',   onEnd);
      document.removeEventListener('touchmove', onMove);
      document.removeEventListener('touchend',  onEnd);

      dragged.classList.remove('dragging');
      placeholder.replaceWith(dragged);
      ghost.remove();
      ghost = null;

      const ids = [...container.querySelectorAll('.' + itemClass)].map(el => +el.dataset.id);
      // Update local state order
      if (itemClass === 'list-card') {
        state.lists = ids.map(id => state.lists.find(l => l.id === id));
      } else {
        state.items = ids.map(id => state.items.find(i => i.id === id));
      }
      onReorder(ids).catch(console.error);
    }

    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup',   onEnd);
    document.addEventListener('touchmove', onMove, { passive: true });
    document.addEventListener('touchend',  onEnd);
  }
}

// ── Sync (polling) ───────────────────────────────────────────
function startSync() {
  stopSync();
  state.syncTimer = setInterval(async () => {
    const currentScreen = document.querySelector('.screen.active')?.id;
    try {
      if (currentScreen === 'screen-lists') {
        const fresh = await api.get('lists');
        // Only re-render if changed (simple JSON compare)
        if (JSON.stringify(fresh) !== JSON.stringify(state.lists)) {
          state.lists = fresh;
          renderLists();
        }
      } else if (currentScreen === 'screen-items' && state.currentList) {
        const fresh = await api.get('items', { list_id: state.currentList.id });
        if (JSON.stringify(fresh) !== JSON.stringify(state.items)) {
          state.items = fresh;
          renderItems();
        }
      }
    } catch { /* silently ignore sync errors */ }
  }, 5000); // every 5 seconds
}

function stopSync() {
  if (state.syncTimer) { clearInterval(state.syncTimer); state.syncTimer = null; }
}

// ── Util ─────────────────────────────────────────────────────
function esc(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

// ── Boot ─────────────────────────────────────────────────────
// Registrieren-Button ausblenden, falls Registrierung gesperrt ist
(async () => {
  try {
    const { open } = await api.get('registration_open');
    if (!open) document.getElementById('btn-show-register').classList.add('hidden');
  } catch { /* ignorieren */ }
})();

checkSession();
