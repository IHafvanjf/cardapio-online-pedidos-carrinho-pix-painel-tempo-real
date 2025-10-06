// ========== CONFIG/API ==========
const API_BASE = '../actions'; // ajuste se necessário

// ========== CONSTANTES ==========
const CATS = [
  { id: 'hamburgueres', label: 'Hambúrgueres', icon: 'fa-burger' },
  { id: 'combos',       label: 'Combos',       icon: 'fa-bowl-food' },
  { id: 'bebidas',      label: 'Bebidas',      icon: 'fa-glass-water' },
  { id: 'sobremesas',   label: 'Sobremesas',   icon: 'fa-ice-cream' }
];

// ========== ESTADO ==========
let state = { products: [], ingredients: [], extras: [] };
let currentCat = 'hamburgueres';

// ========== HELPERS ==========
function $(s, el = document) { return el.querySelector(s); }
function $$(s, el = document) { return Array.from(el.querySelectorAll(s)); }
function money(n) { return `R$ ${Number(n).toFixed(2)}`.replace('.', ','); }

function toast(msg) {
  const t = $('#toast'); if (!t) return alert(msg);
  t.textContent = msg; t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 1800);
}
function confirmDialog(msg) { return new Promise(res => res(window.confirm(msg))); }

function productBlockedByIngredients(p) {
  if (!p.ingredients?.length) return false;
  return p.ingredients.some(id => state.ingredients.find(i => Number(i.id) === Number(id) && !i.active));
}

// Dedup por id+name (evita repetição no modal/listas)
function uniqueByIdOrName(arr) {
  const seen = new Set();
  return (arr || []).filter(it => {
    const key = (it.id ?? '') + '::' + (it.name ?? '').toLowerCase().trim();
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}

async function apiGet(path, params = {}) {
  const url = new URL(`${API_BASE}/${path}`, window.location.href);
  Object.entries(params).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, v);
  });
  const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
  const j = await r.json().catch(() => ({ success:false }));
  if (!r.ok || !j.success) throw new Error(j.message || `Falha ao carregar: ${path}`);
  return j.data;
}
async function apiPost(path, bodyObj) {
  const form = new FormData();
  Object.entries(bodyObj || {}).forEach(([k, v]) => {
    if (Array.isArray(v)) v.forEach(item => form.append(k + '[]', item));
    else form.append(k, v);
  });
  const r = await fetch(`${API_BASE}/${path}`, { method: 'POST', body: form });
  const j = await r.json().catch(() => ({ success:false }));
  if (!r.ok || !j.success) throw new Error(j.message || 'Falha na requisição');
  return j.data || {};
}

// Base64 de arquivo de imagem (para capa)
function imgToBase64(file) {
  return new Promise((res, rej) => {
    const r = new FileReader();
    r.onload = () => res(r.result);
    r.onerror = rej;
    r.readAsDataURL(file);
  });
}

// ========== LOAD ==========
async function loadState() {
  const [ings, exs, prods] = await Promise.all([
    apiGet('ingredients/list.php'),
    apiGet('extras/list.php'),
    apiGet('products/list.php')
  ]);

  state.ingredients = uniqueByIdOrName(
    (ings.ingredients || []).map(i => ({ id:i.id, name:i.name, active: !!i.ativo || i.active === true }))
  );

  state.extras = uniqueByIdOrName(
    (exs.extras || []).map(e => ({ id:e.id, name:e.name, price:Number(e.price||0), active: !!e.ativo || e.active === true }))
  );

  state.products = (prods.items || []).map(p => ({
  id: p.id,
  name: p.name,
  category: p.category,
  price: Number(p.price || 0),
  desc: p.desc || '',
  img: p.img || '',
  active: !!p.active,
  ingredients: (p.ingredients || []).map(Number),
  extras: (p.extras || []).map(Number),
  // ⬇⬇⬇ trazer do backend pro estado
  prep_time_min: Number(p.prep_time_min || 0)
}));

}

// ========== RENDER: PRODUTOS ==========
function renderProducts() {
  const grid = $('#productGrid');
  grid.innerHTML = '';

  const q = $('#globalSearch').value.trim().toLowerCase();

  const prods = state.products
    .filter(p => p.category === currentCat)
    .filter(p => !q || [p.name, p.desc].join(' ').toLowerCase().includes(q))
    .sort((a, b) => a.name.localeCompare(b.name));

  if (!prods.length) {
    grid.innerHTML = `<div class="muted">Nenhum produto nessa categoria.</div>`;
    return;
  }

  for (const p of prods) {
    const blocked = productBlockedByIngredients(p);
    const badge = blocked
      ? `<span class="badge warn">Ingrediente indisponível</span>`
      : (p.active ? '' : `<span class="badge off">Indisponível</span>`);

    const extraNames = p.extras
      .map(id => state.extras.find(e => Number(e.id) === Number(id))?.name)
      .filter(Boolean);

    const img = p.img ? `<img src="${p.img}" alt="${p.name}">` : '';

    const card = document.createElement('article');
    card.className = 'card';
    card.innerHTML = `
      <div class="thumb">${img}${badge || ''}</div>
      <div class="body">
        <div class="title">
          <span>${p.name}</span>
          <strong>${money(p.price)}</strong>
        </div>
        <div class="muted">${p.desc || ''}</div>
        ${
          extraNames.length
            ? `<div class="tags">${
                extraNames.slice(0, 4).map(n => `<span class="tag">${n}</span>`).join('')
              }${
                extraNames.length > 4 ? ' <span class="tag">+' + (extraNames.length - 4) + '</span>' : ''
              }</div>` : ''
        }
        <div class="row">
          <span class="muted"><i class="fa-solid fa-layer-group"></i> ${CATS.find(c => c.id===p.category)?.label}</span>
          <label class="switch">
            <input type="checkbox" ${p.active ? 'checked' : ''} data-toggle="${p.id}">
            <span></span> Ativo
          </label>
        </div>
      </div>
      <div class="actions">
        <button class="small-btn" data-edit="${p.id}"><i class="fa-regular fa-pen-to-square"></i> Editar</button>
        <button class="small-btn" data-dup="${p.id}"><i class="fa-regular fa-copy"></i> Duplicar</button>
        <button class="small-btn" data-img="${p.id}"><i class="fa-regular fa-image"></i> Imagem</button>
        <button class="small-btn" data-del="${p.id}"><i class="fa-regular fa-trash-can"></i> Apagar</button>
      </div>
    `;
    grid.appendChild(card);
  }
}

// ========== RENDER: INGREDIENTES ==========
function renderIngredients() {
  const list = $('#ingredientsList');
  if (!list) return;
  list.innerHTML = '';

  const q = ($('#ingredientsSearch')?.value || '').trim().toLowerCase();

  uniqueByIdOrName(state.ingredients)
    .filter(i => !q || i.name.toLowerCase().includes(q))
    .sort((a, b) => a.name.localeCompare(b.name))
    .forEach(i => {
      const line = document.createElement('div');
      line.className = 'line';
      line.innerHTML = `
        <div>
          <div class="name">${i.name}</div>
          <div class="muted">${i.active ? 'Disponível' : 'Indisponível'}</div>
        </div>
        <label class="switch">
          <input type="checkbox" ${i.active ? 'checked' : ''} data-ing-toggle="${i.id}">
          <span></span>
        </label>
        <div class="right">
          <button class="icon-btn" data-ing-edit="${i.id}" title="Editar"><i class="fa-regular fa-pen-to-square"></i></button>
          <button class="icon-btn" data-ing-del="${i.id}" title="Apagar"><i class="fa-regular fa-trash-can"></i></button>
        </div>
      `;
      list.appendChild(line);
    });
}

// ========== RENDER: EXTRAS ==========
function renderExtras() {
  const list = $('#extrasList');
  if (!list) return;
  list.innerHTML = '';

  const q = ($('#extrasSearch')?.value || '').trim().toLowerCase();

  uniqueByIdOrName(state.extras)
    .filter(e => !q || e.name.toLowerCase().includes(q))
    .sort((a, b) => a.name.localeCompare(b.name) || a.price - b.price)
    .forEach(e => {
      const line = document.createElement('div');
      line.className = 'line';
      line.innerHTML = `
        <div>
          <div class="name">${e.name}</div>
          <div class="muted">${e.active ? 'Disponível' : 'Indisponível'} • Acréscimo: ${money(e.price)}</div>
        </div>
        <label class="switch">
          <input type="checkbox" ${e.active ? 'checked' : ''} data-extra-toggle="${e.id}">
          <span></span>
        </label>
        <div class="right">
          <button class="icon-btn" data-extra-edit="${e.id}" title="Editar"><i class="fa-regular fa-pen-to-square"></i></button>
          <button class="icon-btn" data-extra-del="${e.id}" title="Apagar"><i class="fa-regular fa-trash-can"></i></button>
        </div>
      `;
      list.appendChild(line);
    });
}

// ========== NAVEGAÇÃO ==========
function activatePanel(name) {
  $$('.panel').forEach(p => p.classList.remove('active'));
  $(`#panel-${name}`)?.classList.add('active');
  $$('.nav-item').forEach(b => b.classList.toggle('active', b.dataset.panel === name));
}
function activateTab(cat) {
  currentCat = cat;
  $$('#productTabs .tab').forEach(t => t.classList.toggle('active', t.dataset.cat === cat));
  renderProducts();
}

// ========== MODAIS ==========
function openModal(sel) { const m = $(sel); if (!m) return; m.classList.add('show'); m.setAttribute('aria-hidden','false'); }
function closeModal(sel) { const m = $(sel); if (!m) return; m.classList.remove('show'); m.setAttribute('aria-hidden','true'); }

document.addEventListener('click', (e) => {
  const closeBtn = e.target.closest('[data-close]');
  if (closeBtn) closeModal(closeBtn.getAttribute('data-close'));
});

// Mantém os selecionados independente do filtro/DOM
const pickerState = {
  ing: new Set(),
  extra: new Set(),
  combo: new Set()   // <— novo
};

// ===== Picker com estado persistente =====
function renderPickerItems(container, items, showPrice = false, group = '') {
  container.innerHTML = '';
  const selSet = pickerState[group] || new Set();

  uniqueByIdOrName(items).forEach(it => {
    const idNum = Number(it.id);

if (group === 'combo') {
  const row = document.createElement('label');
  row.className = 'picker-item combo-card';
  row.setAttribute('data-cat', it.cat || '');                 // <- para cor da badge

  const priceStr = typeof it.price === 'number' ? money(it.price) : '';
  row.innerHTML = `
    ${it.img ? `<div class="thumb"><img src="${it.img}" alt="${(it.name||'').replace(/"/g,'&quot;')}"></div>` : ''}
    <div class="name">${it.name}</div>
    ${priceStr ? `<div class="price">${priceStr}</div>` : ''}
    ${it.catLabel ? `<span class="badge">${it.catLabel}</span>` : ''}
    <input type="checkbox" data-pick data-group="combo" value="${idNum}" ${selSet.has(idNum) ? 'checked' : ''}>
  `;
  container.appendChild(row);
  return;
}



    // padrão (ingredientes/extras)
    const row = document.createElement('label');
    row.className = 'picker-item';
    row.innerHTML = `
      <span>${it.name}${showPrice && typeof it.price === 'number' ? ` <small>(${money(it.price)})</small>` : ''}</span>
      <input type="checkbox"
             data-pick
             data-group="${group}"
             value="${idNum}"
             ${selSet.has(idNum) ? 'checked' : ''}>
    `;
    container.appendChild(row);
  });
}



function renderPickerChips(chipContainer, items, group = '') {
  chipContainer.innerHTML = '';
  const selSet = pickerState[group] || new Set();
  const map = new Map(items.map(i => [Number(i.id), i]));

  Array.from(selSet).forEach(id => {
    const it = map.get(Number(id)); if (!it) return;
    const chip = document.createElement('span');
    chip.className = 'chip';
    chip.innerHTML = `
      ${it.name}
      <button aria-label="Remover" data-chip-remove="${id}" data-group="${group}">×</button>
    `;
    chipContainer.appendChild(chip);
  });
}

function initPicker({ listEl, chipsEl, items, searchEl, showPrice = false, group = '' }) {
  if (!pickerState[group]) pickerState[group] = new Set();

  const applyFilter = () => {
    const selSet = pickerState[group];
    const q = (searchEl?.value || '').toLowerCase().trim();
    const filtered = !q ? items : items.filter(i => (i.name || '').toLowerCase().includes(q));

    // Mantém selecionados no topo
    const sorted = filtered.slice().sort((a, b) => {
      const pa = selSet.has(Number(a.id)) ? -1 : 0;
      const pb = selSet.has(Number(b.id)) ? -1 : 0;
      return pa - pb || (a.name || '').localeCompare(b.name || '');
    });

    renderPickerItems(listEl, sorted, showPrice, group);
    renderPickerChips(chipsEl, items, group);
  };

  // render inicial
  applyFilter();

  searchEl?.addEventListener('input', applyFilter);

  // Marcar/desmarcar → atualiza o Set e repinta os chips (não precisa refiltrar)
  listEl.addEventListener('change', (e) => {
    const el = e.target.closest(`input[data-pick][data-group="${group}"]`);
    if (!el) return;
    const id = Number(el.value);
    const selSet = pickerState[group];
    if (el.checked) selSet.add(id); else selSet.delete(id);
    renderPickerChips(chipsEl, items, group);
  });

  // Remover chip → desmarca checkbox correspondente (se estiver renderizado) e atualiza Set
  chipsEl.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-chip-remove]');
    if (!btn) return;
    const id = Number(btn.dataset.chipRemove);
    const selSet = pickerState[group];
    selSet.delete(id);
    const input = listEl.querySelector(`input[data-pick][data-group="${group}"][value="${id}"]`);
    if (input) input.checked = false;
    renderPickerChips(chipsEl, items, group);
  });
}

function openProductModal(productId = null, imageOnly = false) {
  try {
    const modalRoot = document.getElementById('productModal');
    if (!modalRoot) throw new Error('Modal #productModal não encontrado.');

    // util local
    const showEl = (el, show = true) => { if (el) el.classList.toggle('hidden', !show); };

    // esconde/mostra seções do modal conforme categoria
    const applyCategorySections = (cat) => {
      const formTwo   = modalRoot.querySelector('.form-grid.two');
      const colLeft   = modalRoot.querySelector('.form-grid.two > div:first-child');
      const colRight  = modalRoot.querySelector('.form-grid.two > div:last-child');
      const rightLabelEl = colRight?.querySelector(':scope > label');

      const ingPicker   = colLeft?.querySelector('#prodIngs')?.closest('.picker');
      const extraPicker = colRight?.querySelector('#prodExtras')?.closest('.picker');
      const secCombo    = colRight?.querySelector('#sec-combo');

      if (cat === 'combos') {
        formTwo?.classList.add('single');
        colLeft && colLeft.classList.add('hidden');
        rightLabelEl && (rightLabelEl.textContent = 'Itens do combo');
        ingPicker?.classList.add('hidden');
        extraPicker?.classList.add('hidden');
        secCombo?.classList.remove('hidden');
      } else if (cat === 'hamburgueres') {
        formTwo?.classList.remove('single');
        colLeft && colLeft.classList.remove('hidden');
        rightLabelEl && (rightLabelEl.textContent = 'Extras disponíveis');
        ingPicker?.classList.remove('hidden');
        extraPicker?.classList.remove('hidden');
        secCombo?.classList.add('hidden');
      } else {
        formTwo?.classList.add('single');
        colLeft && colLeft.classList.add('hidden');
        rightLabelEl && (rightLabelEl.textContent = 'Extras disponíveis');
        ingPicker?.classList.add('hidden');
        extraPicker?.classList.add('hidden');
        secCombo?.classList.add('hidden');
      }
    };

    const title = document.getElementById('productModalTitle');
    const f = {
      id:     document.getElementById('prodId'),
      name:   document.getElementById('prodName'),
      cat:    document.getElementById('prodCat'),
      price:  document.getElementById('prodPrice'),
      desc:   document.getElementById('prodDesc'),
      active: document.getElementById('prodActive'),
      // NOVO: tempo de preparo
      prep:   document.getElementById('prodPrep'),

      // imagem
      img:     document.getElementById('prodImg'),
      imgDrop: document.getElementById('imgDrop'),
      imgPrev: document.getElementById('imgPreview'),
      // pickers (ingredientes/extras)
      ingList:     document.getElementById('prodIngs'),
      ingChips:    document.getElementById('prodIngsSelected'),
      ingSearch:   document.getElementById('ingSearch'),
      extraList:   document.getElementById('prodExtras'),
      extraChips:  document.getElementById('prodExtrasSelected'),
      extraSearch: document.getElementById('extraSearch'),
      // pickers (combo)
      comboList:   document.getElementById('comboItems'),
      comboChips:  document.getElementById('comboSelected'),
      comboSearch: document.getElementById('comboSearch'),
    };

    const isEdit = !!productId;
    let p = {
      id: null, name: '', category: currentCat, price: 0, desc: '',
      img: '', active: true, ingredients: [], extras: [], combo_items: [],
      prep_time_min: 0 // <- NOVO no estado local do modal
    };

    if (isEdit) {
      const found = state.products.find(x => String(x.id) === String(productId));
      if (found) p = { ...p, ...found };
    }

    title.textContent = isEdit ? (imageOnly ? 'Alterar imagem' : 'Editar produto') : 'Novo produto';

    f.id.value       = p.id || '';
    f.name.value     = p.name || '';
    f.cat.value      = p.category || currentCat;
    f.price.value    = Number(p.price || 0).toFixed(2);
    f.desc.value     = p.desc || '';
    f.active.checked = !!p.active;
    if (f.prep) f.prep.value = Number(p.prep_time_min || 0); // <- PREP preenche

    // imagem
    if (p.img) { f.imgDrop.classList.add('has-img'); f.imgPrev.src = p.img; }
    else { f.imgDrop.classList.remove('has-img'); f.imgPrev.removeAttribute('src'); }

    // handlers de imagem
    const attachImgHandlers = () => {
      f.imgDrop.addEventListener('click', () => f.img.click(), { once: true });
      f.imgDrop.addEventListener('dragover', (ev) => { ev.preventDefault(); f.imgDrop.classList.add('drag'); });
      f.imgDrop.addEventListener('dragleave', () => f.imgDrop.classList.remove('drag'));
      f.imgDrop.addEventListener('drop', async (ev) => {
        ev.preventDefault(); f.imgDrop.classList.remove('drag');
        const file = ev.dataTransfer.files?.[0];
        if (file) {
          const b64 = await imgToBase64(file);
          f.imgDrop.classList.add('has-img');
          f.imgPrev.src = b64;
          f.img.dataset._changed = '1';
          f.img.dataset._value = b64;
        }
      });
      f.img.onchange = async () => {
        const file = f.img.files?.[0];
        if (file) {
          const b64 = await imgToBase64(file);
          f.imgDrop.classList.add('has-img');
          f.imgPrev.src = b64;
          f.img.dataset._changed = '1';
          f.img.dataset._value = b64;
        }
      };
    };
    attachImgHandlers();

    // estado dos pickers
    pickerState.ing   = new Set((p.ingredients || []).map(Number));
    pickerState.extra = new Set((p.extras || []).map(Number));
    pickerState.combo = new Set((p.combo_items || []).map(Number));

    // render dos pickers por categoria
    const initForCat = (cat) => {
      if (cat === 'hamburgueres') {
        initPicker({
          listEl:  f.ingList, chipsEl: f.ingChips,
          items:   Array.isArray(state.ingredients) ? state.ingredients : [],
          searchEl: f.ingSearch, showPrice: false, group: 'ing'
        });
        initPicker({
          listEl:  f.extraList, chipsEl: f.extraChips,
          items:   Array.isArray(state.extras) ? state.extras : [],
          searchEl: f.extraSearch, showPrice: true, group: 'extra'
        });
      } else if (cat === 'combos') {
        const labelByCat = { hamburgueres: 'Hambúrguer', bebidas: 'Bebida', sobremesas: 'Sobremesa' };
        const comboPool = (state.products || [])
          .filter(pr => pr.category !== 'combos' && pr.active)
          .map(pr => ({
            id: pr.id,
            name: pr.name,
            price: pr.price,
            img: pr.img || '',
            cat: pr.category,
            catLabel: labelByCat[pr.category] || pr.category
          }))
          .sort((a,b) => (a.cat || '').localeCompare(b.cat || '') || (a.name || '').localeCompare(b.name || ''));

        initPicker({
          listEl:  f.comboList,
          chipsEl: f.comboChips,
          items:   comboPool,
          searchEl: f.comboSearch,
          showPrice: true,
          group:   'combo'
        });

        f.comboList.classList.add('combo-mode');
      } else {
        f.comboList?.classList.remove('combo-mode');
      }
    };

    // aplica visibilidade + inicializa
    applyCategorySections(f.cat.value);
    initForCat(f.cat.value);

    // troca de categoria
    if (!f.cat.dataset.boundChange) {
      f.cat.addEventListener('change', (ev) => {
        const newCat = ev.target.value;
        applyCategorySections(newCat);

        // limpa conjuntos conforme necessário
        if (newCat === 'combos') {
          pickerState.ing.clear(); pickerState.extra.clear();
        } else if (newCat === 'hamburgueres') {
          pickerState.combo.clear();
        } else {
          pickerState.ing.clear(); pickerState.extra.clear(); pickerState.combo.clear();
        }

        initForCat(newCat);
      });
      f.cat.dataset.boundChange = '1';
    }

    // somente imagem?
    modalRoot.querySelectorAll('.fields-col input, .fields-col select, .fields-col textarea, .form-grid.two input[type=checkbox]')
      .forEach(el => el.disabled = imageOnly);

    openModal('#productModal');
  } catch (err) {
    console.error(err);
    toast(err.message || 'Erro ao abrir o modal.');
  }
}

// ========== SALVAR PRODUTO (CRIAR/EDITAR) ==========
// Salvar (criar/editar) produto
// Salvar (criar/editar) produto
$('#btnSaveProduct')?.addEventListener('click', async () => {
  const id       = $('#prodId').value.trim();
  const name     = $('#prodName').value.trim();
  const category = $('#prodCat').value;
  const price    = parseFloat($('#prodPrice').value || '0');
  const desc     = $('#prodDesc').value.trim();
  const active   = $('#prodActive').checked ? 1 : 0;
  const prep     = parseInt($('#prodPrep')?.value || '0', 10); // <- NOVO

  if (!name) return toast('Informe um nome');

  const imgInput = $('#prodImg');
  const img_b64  = (imgInput.dataset._changed === '1' && imgInput.dataset._value) ? imgInput.dataset._value : '';

  const ingredients = Array.from(pickerState.ing).map(Number);
  const extras      = Array.from(pickerState.extra).map(Number);
  const combo_items = Array.from(pickerState.combo).map(Number);

  // inclui prep_time_min no payload
  const payload = { name, category, price, desc, active, prep_time_min: isNaN(prep) ? 0 : prep };
  if (img_b64) payload.img_b64 = img_b64;
  if (category === 'hamburgueres') {
    payload.ingredients = ingredients;
    payload.extras = extras;
  } else if (category === 'combos') {
    payload.combo_items = combo_items;
  }

  try {
    if (id) await apiPost('products/update.php', { id, ...payload });
    else    await apiPost('products/create.php', payload);

    imgInput.dataset._changed = '';
    imgInput.dataset._value = '';
    closeModal('#productModal');

    await loadState();
    renderProducts();
    toast(id ? 'Produto atualizado' : 'Produto criado');
  } catch (e) {
    console.error(e);
    toast(e.message || 'Erro ao salvar');
  }
});




// ========== AÇÕES DOS CARDS ==========
document.addEventListener('click', async (e) => {
  const btnEdit = e.target.closest('[data-edit]');
  const btnDel  = e.target.closest('[data-del]');
  const btnDup  = e.target.closest('[data-dup]');
  const btnImg  = e.target.closest('[data-img]');
  const toggle  = e.target.closest('input[data-toggle]');

  if (btnEdit) openProductModal(btnEdit.dataset.edit);
  if (btnImg)  openProductModal(btnImg.dataset.img, true);

  if (btnDup) {
    try {
      await apiPost('products/duplicate.php', { id: btnDup.dataset.dup });
      await loadState(); renderProducts();
      toast('Produto duplicado');
    } catch (e2) { toast(e2.message || 'Falha ao duplicar'); }
  }

  if (btnDel) {
    const ok = await confirmDialog('Deseja remover este produto?'); if (!ok) return;
    try {
      await apiPost('products/delete.php', { id: btnDel.dataset.del });
      await loadState(); renderProducts();
      toast('Produto removido');
    } catch (e2) { toast(e2.message || 'Falha ao remover'); }
  }

  if (toggle) {
    const prodId = toggle.dataset.toggle;
    const newVal = toggle.checked ? 1 : 0;
    try {
      await apiPost('products/toggle_active.php', { id: prodId, active: newVal });
      const p = state.products.find(x => String(x.id) === String(prodId));
      if (p) p.active = !!newVal;
      renderProducts();
      toast('Disponibilidade atualizada');
    } catch (e2) {
      toggle.checked = !toggle.checked;
      toast(e2.message || 'Falha ao atualizar');
    }
  }
});

// ========== INGREDIENTES (CRUD) ==========
document.addEventListener('click', async (e) => {
  const tgl = e.target.closest('input[data-ing-toggle]');
  const ed  = e.target.closest('[data-ing-edit]');
  const del = e.target.closest('[data-ing-del]');

  if (tgl) {
    try {
      await apiPost('ingredients/toggle.php', { id: tgl.dataset.ingToggle, active: tgl.checked ? 1 : 0 });
      await loadState(); renderIngredients(); renderProducts();
    } catch (e2) { tgl.checked = !tgl.checked; toast('Erro ao atualizar ingrediente'); }
  }

  if (ed) {
    const ing = state.ingredients.find(i => String(i.id) === String(ed.dataset.ingEdit)); if (!ing) return;
    $('#ingId').value = ing.id; $('#ingName').value = ing.name; $('#ingActive').checked = ing.active;
    $('#ingModalTitle').textContent = 'Editar ingrediente';
    openModal('#ingModal');
  }

  if (del) {
    const ok = await confirmDialog('Remover ingrediente?'); if (!ok) return;
    try {
      await apiPost('ingredients/delete.php', { id: del.dataset.ingDel });
      await loadState(); renderIngredients(); renderProducts();
      toast('Ingrediente removido');
    } catch (e2) { toast('Erro ao remover ingrediente'); }
  }
});

$('#btnNewIngredient')?.addEventListener('click', () => {
  $('#ingId').value = ''; $('#ingName').value = ''; $('#ingActive').checked = true;
  $('#ingModalTitle').textContent = 'Novo ingrediente';
  openModal('#ingModal');
});
$('#btnSaveIng')?.addEventListener('click', async () => {
  const id = $('#ingId').value.trim();
  const name = $('#ingName').value.trim();
  const active = $('#ingActive').checked ? 1 : 0;
  if (!name) return toast('Informe o nome');

  try {
    if (id) await apiPost('ingredients/update.php', { id, name, active });
    else    await apiPost('ingredients/create.php', { name, active });
    closeModal('#ingModal');
    await loadState(); renderIngredients(); renderProducts();
    toast(id ? 'Ingrediente atualizado' : 'Ingrediente criado');
  } catch (e) { toast('Erro ao salvar ingrediente'); }
});

// ========== EXTRAS (CRUD) ==========
document.addEventListener('click', async (e) => {
  const tgl = e.target.closest('input[data-extra-toggle]');
  const ed  = e.target.closest('[data-extra-edit]');
  const del = e.target.closest('[data-extra-del]');

  if (tgl) {
    try {
      await apiPost('extras/toggle.php', { id: tgl.dataset.extraToggle, active: tgl.checked ? 1 : 0 });
      await loadState(); renderExtras();
    } catch (e2) { tgl.checked = !tgl.checked; toast('Erro ao atualizar extra'); }
  }

  if (ed) {
    const ex = state.extras.find(i => String(i.id) === String(ed.dataset.extraEdit)); if (!ex) return;
    $('#extraId').value = ex.id; $('#extraName').value = ex.name; $('#extraPrice').value = Number(ex.price || 0).toFixed(2);
    $('#extraActive').checked = ex.active; $('#extraModalTitle').textContent = 'Editar extra';
    openModal('#extraModal');
  }

  if (del) {
    const ok = await confirmDialog('Remover extra?'); if (!ok) return;
    try {
      await apiPost('extras/delete.php', { id: del.dataset.extraDel });
      await loadState(); renderExtras(); renderProducts();
      toast('Extra removido');
    } catch (e2) { toast('Erro ao remover extra'); }
  }
});

$('#btnNewExtra')?.addEventListener('click', () => {
  $('#extraId').value = ''; $('#extraName').value = ''; $('#extraPrice').value = '0.00';
  $('#extraActive').checked = true; $('#extraModalTitle').textContent = 'Novo extra';
  openModal('#extraModal');
});
$('#btnSaveExtra')?.addEventListener('click', async () => {
  const id = $('#extraId').value.trim();
  const name = $('#extraName').value.trim();
  const price = parseFloat($('#extraPrice').value || '0');
  const active = $('#extraActive').checked ? 1 : 0;
  if (!name) return toast('Informe o nome');

  try {
    if (id) await apiPost('extras/update.php', { id, name, price, active });
    else    await apiPost('extras/create.php', { name, price, active });
    closeModal('#extraModal');
    await loadState(); renderExtras(); renderProducts();
    toast(id ? 'Extra atualizado' : 'Extra criado');
  } catch (e) { toast('Erro ao salvar extra'); }
});

// ========== PREÇOS EM MASSA ==========
$('#btnApplyBulk')?.addEventListener('click', async () => {
  const category = $('#bulkCategory').value;
  const percent = parseFloat($('#bulkPercent').value || '0');
  try {
    await apiPost('categories/adjust_percent.php', { category, percent });
    await loadState(); renderProducts();
    toast(`Ajuste de ${percent}% aplicado`);
  } catch (e) { toast('Falha ao aplicar ajuste'); }
});
$('#btnIncrease5')?.addEventListener('click', () => { $('#bulkPercent').value = 5;  $('#bulkCategory').value = currentCat; $('#btnApplyBulk').click(); });
$('#btnDecrease5')?.addEventListener('click', () => { $('#bulkPercent').value = -5; $('#bulkCategory').value = currentCat; $('#btnApplyBulk').click(); });

// ========== IMPORT/EXPORT ==========
$('#btnExport')?.addEventListener('click', async () => {
  try {
    const data = await apiGet('export.php');
    const text = JSON.stringify(data, null, 2);
    $('#exportPreview').textContent = text;
    const blob = new Blob([text], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = 'burgerhub_menu.json'; a.click();
    URL.revokeObjectURL(url);
    toast('Exportado');
  } catch (e) { toast('Falha ao exportar'); }
});
$('#importFile')?.addEventListener('change', async (e) => {
  const file = e.target.files?.[0]; if (!file) return;
  try {
    const text = await file.text();
    await apiPost('import.php', { json: text });
    await loadState(); renderAll();
    toast('Importado com sucesso');
  } catch { toast('Falha ao importar JSON'); }
  finally { e.target.value = ''; }
});

// ========== BUSCAS ==========
$('#globalSearch')?.addEventListener('input', () => renderProducts());
$('#ingredientsSearch')?.addEventListener('input', () => renderIngredients());
$('#extrasSearch')?.addEventListener('input', () => renderExtras());

// ========== TOP BUTTONS ==========
$('#btnNewProduct')?.addEventListener('click', () => openProductModal(null, false));
$('#btnNewProduct2')?.addEventListener('click', () => openProductModal(null, false));
$('#btnSave')?.addEventListener('click', () => toast('Ações já salvam no servidor.'));
$('#btnReset')?.addEventListener('click', () => toast('Use Importar/Exportar para restaurar a demo.'));

// ========== NAVEGAÇÃO ==========
document.querySelectorAll('.nav-item').forEach(b => b.addEventListener('click', () => activatePanel(b.dataset.panel)));
document.querySelectorAll('#productTabs .tab').forEach(t => t.addEventListener('click', () => activateTab(t.dataset.cat)));

// ========== INICIALIZAÇÃO ==========
function renderAll() { renderProducts(); renderIngredients(); renderExtras(); }
document.addEventListener('DOMContentLoaded', async () => {
  try {
    await loadState();
    renderAll();
    activatePanel('produtos');
    activateTab('hamburgueres');
  } catch (err) {
    console.error(err);
    toast('Não foi possível carregar os dados do servidor.');
  }
});



// ========== DROPDOWN DO USUÁRIO ==========
document.addEventListener("DOMContentLoaded", () => {
  const userBtn = document.querySelector(".user-btn");
  const userDropdown = document.querySelector(".user-dropdown");

  if (userBtn && userDropdown) {
    // Toggle ao clicar
    userBtn.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();

      // Fecha qualquer dropdown aberto antes
      document.querySelectorAll(".user-dropdown.show").forEach(d => {
        if (d !== userDropdown) d.classList.remove("show");
      });

      userDropdown.classList.toggle("show");
      userBtn.setAttribute("aria-expanded", userDropdown.classList.contains("show"));
    });

    // Fechar ao clicar fora
    document.addEventListener("click", (e) => {
      if (!userBtn.contains(e.target) && !userDropdown.contains(e.target)) {
        userDropdown.classList.remove("show");
        userBtn.setAttribute("aria-expanded", "false");
      }
    });

    // Fechar com tecla ESC
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        userDropdown.classList.remove("show");
        userBtn.setAttribute("aria-expanded", "false");
      }
    });
  }
});