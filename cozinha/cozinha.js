const $  = (s, el = document) => el.querySelector(s);
const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));
const byId = (id) => document.getElementById(id);

// Relógio alinhado ao servidor (corrige fuso/atraso)
let TIME_SKEW = 0; // ms somados ao relógio local
const now = () => Date.now() + TIME_SKEW;

const KDS_API = '../actions/public/kds';

// Formatação de hora no Brasil 
const BR_TZ = 'America/Sao_Paulo';
const BR_TIME_OPTS = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: BR_TZ };
const BR_TIME_OPTS_NOSEC = { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: BR_TZ };
const brTime = (ts, withSeconds = true) =>
  (ts instanceof Date ? ts : new Date(ts)).toLocaleTimeString('pt-BR', withSeconds ? BR_TIME_OPTS : BR_TIME_OPTS_NOSEC);

// Estado em memória
let activeOrders = [];   
let historyOrders = [];  
let pendingId = null;

// Utilidades de tempo/strings
function timeAgo(ts) {
  const diff = Math.max(0, now() - ts);
  const m = Math.floor(diff / 60000);
  const h = Math.floor(m / 60);
  if (h > 0) return `${h}h ${m % 60}m`;
  if (m > 0) return `${m} min`;
  const s = Math.floor(diff / 1000);
  return `${s}s`;
}
const cap = (s) => s.charAt(0).toUpperCase() + s.slice(1);

// Garante um ETA padrão quando o pedido não traz
function ensureEtaMs(order) {
  if (!order.etaMs || order.etaMs <= 0) {
    const totalQty = order.items.reduce((sum, it) => sum + (it.qty || 1), 0);
    const estMin = Math.min(30, 8 + totalQty * 2); // fallback simples
    order.etaMs = estMin * 60000;
  }
}

// Retorna progresso do preparo e minutos restantes
function progressInfo(order) {
  if (order.status !== 'preparando') return { pct: 0, remainMin: 0 };
  ensureEtaMs(order);
  const start = order.startedAt || order.updatedAt || order.createdAt || now();
  const elapsed = now() - start;
  const pct = Math.max(0, Math.min(100, Math.floor((elapsed / order.etaMs) * 100)));
  const remainMs = Math.max(0, order.etaMs - elapsed);
  const remainMin = Math.ceil(remainMs / 60000);
  return { pct, remainMin };
}

// Minutos desde o início (ou criação) do pedido
function elapsedMinutes(order) {
  const ref = order.status === 'preparando' ? (order.startedAt || order.createdAt) : order.createdAt;
  return Math.floor(Math.max(0, now() - ref) / 60000);
}

// Mostra/oculta estados vazios
function toggleEmptyStates(activeCount, historyCount) {
  const ea = byId('emptyActive'), eh = byId('emptyHistory');
  if (ea) { const hide = activeCount > 0; ea.hidden = hide; ea.style.display = hide ? 'none' : ''; }
  if (eh) { const hide = historyCount > 0; eh.hidden = hide; eh.style.display = hide ? 'none' : ''; }
}

// Carrossel para grid de pedidos ativos
function setupCarousel() {
  const grid = byId('activeGrid');
  if (!grid) return;

  grid.classList.add('carousel');

  if (!grid.parentElement.classList.contains('carousel-ctr')) {
    const wrap = document.createElement('div');
    wrap.className = 'carousel-ctr';
    grid.parentElement.insertBefore(wrap, grid);
    wrap.appendChild(grid);

    const left  = document.createElement('button');
    left.className = 'carousel-btn left';
    left.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';

    const right = document.createElement('button');
    right.className = 'carousel-btn right';
    right.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';

    wrap.append(left, right);

    const step = () => Math.max(280, Math.floor(grid.clientWidth * 0.9));

    const updateBtns = () => {
      const needArrows = grid.scrollWidth > grid.clientWidth + 1;
      left.style.display  = needArrows ? '' : 'none';
      right.style.display = needArrows ? '' : 'none';
      grid.style.padding  = needArrows ? '6px 48px' : '6px 0';
      const maxScroll = grid.scrollWidth - grid.clientWidth - 1;
      left.disabled  = (grid.scrollLeft <= 0);
      right.disabled = (grid.scrollLeft >= maxScroll);
    };

    left.addEventListener('click',  () => grid.scrollBy({ left: -step(), behavior: 'smooth' }));
    right.addEventListener('click', () => grid.scrollBy({ left:  step(), behavior: 'smooth' }));
    grid.addEventListener('scroll', updateBtns);
    window.addEventListener('resize', updateBtns);
    updateBtns();
  }
}

// Renderiza pedidos ativos e histórico
function render() {
  const grid = byId('activeGrid');
  grid.innerHTML = '';

  // Ordena: preparando > aguardando, e por seq dentro do grupo
  const weight = (s) => (s === 'preparando' ? 0 : s === 'aguardando' ? 1 : 2);
  const active = [...activeOrders].sort((a, b) =>
    (weight(a.status) - weight(b.status)) || ((a.seq || 0) - (b.seq || 0))
  );

  active.forEach(o => {
    const card = document.createElement('article');
    card.className = 'card order-card fade-in';
    card.dataset.id = o.id;

    const totalItems = o.items.reduce((s, x) => s + (x.qty || 1), 0);
    const prog = progressInfo(o);
    const etaTotalMin = Math.max(0, Math.round((o.etaMs || 0) / 60000));
    const decMin = elapsedMinutes(o);

    const progBlock = (o.status === 'preparando')
      ? `
        <div class="k-progress" aria-label="Progresso do pedido">
          <span class="k-bar progress-bar" style="width:${prog.pct}%"></span>
        </div>
        <div class="meta" style="text-align:right;margin-top:4px">
          Restante: <span class="remain" data-id="${o.id}">${prog.pct < 100 ? `${prog.remainMin}` : 0}</span> min
        </div>
        <div class="meta eta-info" style="text-align:right;margin-top:2px">
          Est.: ${etaTotalMin} min • Dec.: <span class="elapsed" data-id="${o.id}">${decMin}</span> min
        </div>`
      : `
        <div class="meta eta-info" style="text-align:right;margin-top:4px">
          Est.: ${etaTotalMin} min
        </div>`;

    const itemsHtml = o.items.map(it => {
      const extras = (it.extras && it.extras.length)
        ? it.extras.map(n => `<span class="tag">${n}</span>`).join('')
        : `<span class="tag empty">Sem extras</span>`;
      const note = it.note ? `— ${it.note}` : `<span class="notes-empty">Sem observações</span>`;
      return `
        <div class="item-row">
          <div class="item-main">
            <div class="item-title">${it.qty}× ${it.name}</div>
            <div class="item-notes">${note}</div>
          </div>
          <div class="item-extras">${extras}</div>
        </div>`;
    }).join('');

    const mesaLabel = o.table ? `Mesa ${o.table}` : 'Balcão';
    const elapsedRef = (o.status === 'preparando') ? (o.startedAt || o.createdAt) : o.createdAt;

    card.innerHTML = `
      <div class="card-head">
        <div>
          <div class="code">Pedido ${o.code || ('#' + o.id)}</div>
          <div class="seq-line">
            <span class="seq-badge">${String(o.seq ?? '').padStart(2, '0')}</span>
            <span class="mesa-badge">${mesaLabel}</span>
            <span class="meta"><i class="fa-regular fa-clock"></i> ${timeAgo(o.createdAt)} • ${totalItems} item${totalItems > 1 ? 's' : ''}</span>
          </div>
        </div>
        <div class="time" data-elref="${elapsedRef}">${timeAgo(elapsedRef)}</div>
      </div>

      <div class="items">
        ${itemsHtml}
        ${progBlock}
      </div>

      <div class="actions">
        <button class="status-btn ${o.status === 'aguardando' ? 'status-wait' : o.status === 'preparando' ? 'status-prep' : 'status-done'}" data-action="advance">
          <i class="fa-solid ${o.status === 'aguardando' ? 'fa-hourglass-start' : o.status === 'preparando' ? 'fa-fire-burner' : 'fa-check'}"></i>
          <span class="label">${cap(o.status)}</span>
        </button>
        <span class="meta">Criado: ${brTime(o.createdAt, false)}</span>
      </div>
    `;
    grid.appendChild(card);
  });

  // Histórico
  const hist = byId('historyList');
  hist.innerHTML = '';
  const histSorted = [...historyOrders].sort((a, b) =>
    (b.finalizedAt || b.createdAt) - (a.finalizedAt || a.createdAt)
  );
  histSorted.forEach(o => {
    const row = document.createElement('div');
    row.className = 'history-item fade-in';
    const summary = o.items.map(it => `${it.qty}× ${it.name}`).join(' • ');
    row.innerHTML = `
      <div>
        <strong>${o.code || ('#' + o.id)}</strong>
        <div class="meta">Ord. ${String(o.seq || '-')} • ${brTime(o.createdAt, false)}</div>
      </div>
      <div class="meta">${summary}</div>
      <div class="pill pill-done" title="Finalizado às ${brTime(o.finalizedAt || o.createdAt, false)}">Finalizado</div>
    `;
    hist.appendChild(row);
  });

  setupCarousel();
  toggleEmptyStates(activeOrders.length, historyOrders.length);
}

// Atualiza tempos e barras sem rerender completo
function tickUI() {
  activeOrders.forEach(o => {
    const card = $(`#activeGrid .card[data-id="${o.id}"]`);
    if (!card) return;

    // tempo decorrido
    const elTime = card.querySelector('.time');
    if (elTime) {
      const ref = (o.status === 'preparando') ? (o.startedAt || o.createdAt) : o.createdAt;
      elTime.textContent = timeAgo(ref);
    }

    // barra e rótulos quando em preparo
    if (o.status === 'preparando') {
      ensureEtaMs(o);
      const start = o.startedAt || o.updatedAt || o.createdAt || now();
      const elapsed = now() - start;
      const pct = Math.max(0, Math.min(100, Math.floor((elapsed / o.etaMs) * 100)));
      const remainMin = Math.ceil(Math.max(0, o.etaMs - elapsed) / 60000);

      const bar = card.querySelector('.progress-bar');
      const remain = card.querySelector('.remain[data-id]');
      const elapsedEl = card.querySelector('.elapsed[data-id]');
      if (bar) bar.style.width = `${pct}%`;
      if (remain) remain.textContent = `${remainMin}`;
      if (elapsedEl) elapsedEl.textContent = `${elapsedMinutes(o)}`;
    }
  });
}

// Carrega feed do servidor e normaliza datas
async function loadFeed() {
  try {
    const resp = await fetch(`${KDS_API}/feed.php`, { cache: 'no-store' });
    const data = await resp.json();

    // ajusta relógio local ao do servidor
    const srvNow = Date.parse(data.server_time);
    TIME_SKEW = Number.isFinite(srvNow) ? (srvNow - Date.now()) : 0;

    // interpreta DATETIME do MySQL usando o fuso do servidor
    const srvTZ = (String(data.server_time).match(/([+-]\d{2}:\d{2})$/) || [])[1] || 'Z';
    const parseSrv = (s) => {
      const str = String(s || '').replace(' ', 'T');
      const t = Date.parse(str + (str.endsWith('Z') || /[+-]\d{2}:\d{2}$/.test(str) ? '' : srvTZ));
      return Number.isFinite(t) ? t : new Date(s).getTime();
    };

    // pedidos ativos
    activeOrders = (data.active || []).map(o => {
      const createdAt   = parseSrv(o.created_at);
      const lastChange  = parseSrv(o.last_change || o.created_at);
      const startedAtDb = o.prep_started_at ? parseSrv(o.prep_started_at) : null;

      return {
        id: String(o.oid),
        code: o.code,
        seq: o.seq,
        table: o.table,
        status: o.status, // 'aguardando' | 'preparando'
        createdAt,
        updatedAt: lastChange,
        // início real do preparo
        startedAt: (o.status === 'preparando') ? (startedAtDb ?? lastChange) : null,
        items: (o.items || []).map(it => ({
          qty: it.qty, name: it.name, extras: it.extras || [], note: it.note || ''
        })),
        // ETA do back-end (min -> ms)
        etaMs: (Number(o.eta_min) || 0) * 60000
      };
    });

    // histórico
    historyOrders = (data.history || []).map(h => ({
      id: String(h.oid),
      code: h.code,
      createdAt: parseSrv(h.created_at),
      finalizedAt: parseSrv(h.finalized_at || h.created_at),
      items: parseHistorySummary(h.summary || ''),
      status: 'finalizado'
    }));

    render();
  } catch (e) {
    // silencioso
  }
}

// Converte string "2× X • 1× Y" em array de itens
function parseHistorySummary(summary) {
  if (!summary) return [];
  return summary.split(' • ').map(chunk => {
    const parts = chunk.split('×');
    if (parts.length >= 2) {
      const qty = parseInt(parts[0].trim(), 10) || 1;
      const name = parts.slice(1).join('×').trim();
      return { qty, name, extras: [], note: '' };
    }
    return { qty: 1, name: chunk.trim(), extras: [], note: '' };
  });
}

// Avança status (aguardando → preparando → finalizado)
async function advanceStatus(id) {
  const order = activeOrders.find(o => o.id === id);
  if (!order) return;

  if (order.status === 'aguardando') {
    await fetch(`${KDS_API}/advance_status.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ order_id: Number(id), to_status: 'preparando' })
    });
    await loadFeed();
    return;
  }

  if (order.status === 'preparando') {
    openConfirm(id);
    return;
  }
}

// Finaliza o pedido pendente no modal
async function finalizePending() {
  if (!pendingId) return;
  await fetch(`${KDS_API}/advance_status.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_id: Number(pendingId), to_status: 'finalizado' })
  });
  closeConfirm();
  await loadFeed();
}

// Modal de confirmação
function openConfirm(id) {
  pendingId = id;
  const ord = activeOrders.find(o => o.id === id);
  byId('confirmText').textContent = `Pedido ${ord?.code || ('#' + id)} será movido para o histórico.`;
  const m = byId('confirmModal');
  m.classList.add('show');
  m.setAttribute('aria-hidden', 'false');
}
function closeConfirm() {
  const m = byId('confirmModal');
  m.classList.remove('show');
  m.setAttribute('aria-hidden', 'true');
  pendingId = null;
}

// Eventos e polling
async function bind() {
  byId('year').textContent = new Date().getFullYear();
  setInterval(() => { byId('clock').textContent = brTime(Date.now()); }, 1000);

  byId('closeConfirm').addEventListener('click', closeConfirm);
  byId('cancelConfirm').addEventListener('click', closeConfirm);
  byId('okConfirm').addEventListener('click', finalizePending);

  byId('activeGrid').addEventListener('click', e => {
    const btn = e.target.closest('[data-action="advance"]'); if (!btn) return;
    const card = e.target.closest('.card'); if (!card) return;
    advanceStatus(card.dataset.id);
  });

  await loadFeed();
  setInterval(loadFeed, 5000); // atualiza dados
  setInterval(tickUI, 1000);   // atualiza barras/tempos
}

document.addEventListener('DOMContentLoaded', bind);
