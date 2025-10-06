const $ = (s, el=document) => el.querySelector(s);
const $$ = (s, el=document) => Array.from(el.querySelectorAll(s));
const money = (n, lang=getLang()) => new Intl.NumberFormat(lang==='en'?'en-US':lang==='es'?'es-ES':'pt-BR',{style:'currency', currency:'BRL'}).format(n);
const byId = (id) => document.getElementById(id);
const now = () => Date.now();

const UI = {
  pt: {
    tab_burgers: 'Hambúrgueres', tab_combos:'Combos', tab_drinks:'Bebidas', tab_desserts:'Sobremesas',
    your_ticket:'Sua Nota', subtotal:'Subtotal ', prep_time:'Tempo total ', finish_order:'Finalizar Pedido',
    add_to_order:'Adicionar à nota', remove_something:'Algo para remover?', min:'min', footer_all_rights:'todos os direitos reservados',
    checkout_title:'Checkout', order_summary:'Resumo do pedido', choose_payment:'Escolha o pagamento', pay_card:'Cartão', card_number:'Número do cartão', card_name:'Nome impresso', expiry:'Validade', back_to_menu:'Voltar ao menu', confirm_and_pay:'Confirmar e pagar',
    order_status:'Status do pedido', order_code:'Pedido', make_new_order:'Fazer novo pedido', preparing:'Preparando', ready:'Pronto'
  },
  en: {
    tab_burgers: 'Burgers', tab_combos:'Combos', tab_drinks:'Drinks', tab_desserts:'Desserts',
    your_ticket:'Your Ticket', subtotal:'Subtotal ', prep_time:'Total time ', finish_order:'Place Order',
    add_to_order:'Add to ticket', remove_something:'Remove anything?', min:'min', footer_all_rights:'all rights reserved',
    checkout_title:'Checkout', order_summary:'Order summary', choose_payment:'Choose payment', pay_card:'Card', card_number:'Card number', card_name:'Name on card', expiry:'Expiry', back_to_menu:'Back to menu', confirm_and_pay:'Confirm & pay',
    order_status:'Order status', order_code:'Order', make_new_order:'Make a new order', preparing:'Preparing', ready:'Ready'
  },
  es: {
    tab_burgers: 'Hamburguesas', tab_combos:'Combos', tab_drinks:'Bebidas', tab_desserts:'Postres',
    your_ticket:'Tu Nota', subtotal:'Subtotal ', prep_time:'Tiempo total ', finish_order:'Finalizar pedido',
    add_to_order:'Agregar a la nota', remove_something:'¿Quitar algo?', min:'min', footer_all_rights:'todos los derechos reservados',
    checkout_title:'Pago', order_summary:'Resumen del pedido', choose_payment:'Elige el pago', pay_card:'Tarjeta', card_number:'Número de tarjeta', card_name:'Nombre impreso', expiry:'Vencimiento', confirm_and_pay:'Confirmar y pagar',
    order_status:'Estado del pedido', order_code:'Pedido', make_new_order:'Hacer un nuevo pedido', preparing:'Preparando', ready:'Listo'
  }
};

function getLang(){ return localStorage.getItem('lang') || 'pt'; }
function t(key){ const lang=getLang(); return (UI[lang] && UI[lang][key]) || UI.pt[key] || key; }
function applyI18n(){
  $$('#langSelect').forEach(sel=> sel.value=getLang());
  $$('[data-i18n]').forEach(el=> el.textContent = t(el.dataset.i18n));
}

(function clearCatalogSessionCache(){
  try{
    for (let i = sessionStorage.length - 1; i >= 0; i--) {
      const k = sessionStorage.key(i);
      if (k && k.startsWith('bh_catalog_')) sessionStorage.removeItem(k);
    }
  }catch(e){}
})();


// Ao trocar idioma, refetch do catálogo e rerender
function setLang(lang){
  localStorage.setItem('lang', lang);
  applyI18n();
  fetchCatalogFromAPI(true)
    .then(()=>{
      reRenderProducts();
      renderCart();
      renderSummary();
      renderStatus();
    })
    .catch(()=>{ reRenderProducts(); });
}

//  CATÁLOGO VIA API 
let CATALOG = null;         
let CATALOG_LOADED_AT = 0;  

function showCatalogLoading(){
  const wrap = byId('catalog');
  if (!wrap) return;
  wrap.innerHTML = `
    <div class="skeleton-grid">
      ${Array.from({length: 6}).map(()=>`
        <div class="card-item sr" style="--sr-delay:90ms">
          <div class="card-media skeleton"></div>
          <div class="card-body">
            <div class="card-title">
              <div class="skeleton skeleton-text" style="width:55%"></div>
              <div class="skeleton skeleton-badge" style="width:90px;height:24px;border-radius:8px"></div>
            </div>
            <div class="skeleton skeleton-text" style="width:90%"></div>
            <div class="skeleton skeleton-text" style="width:70%"></div>
            <div class="card-actions" style="margin-top:10px;display:flex;justify-content:space-between;align-items:center;">
              <div class="skeleton skeleton-text" style="width:100px"></div>
              <div class="skeleton skeleton-badge" style="width:120px;height:36px;border-radius:10px"></div>
            </div>
          </div>
        </div>
      `).join('')}
    </div>
  `;
}const DISABLE_CATALOG_CACHE = true;


async function fetchCatalogFromAPI(force=false){
  const lang = getLang();
  const url = `./actions/public/get_catalog.php?lang=${encodeURIComponent(lang)}&_=${Date.now()}`;
  const res = await fetch(url, { method:'GET', headers:{ 'Accept':'application/json' }, cache:'no-store' });
  const text = await res.text();
  if(!res.ok){ console.error('Catalog API HTTP error', res.status, text); throw new Error(`HTTP ${res.status}`); }
  const data = JSON.parse(text);
  CATALOG = {
    burgers: Array.isArray(data.burgers)?data.burgers:[],
    combos: Array.isArray(data.combos)?data.combos:[],
    drinks: Array.isArray(data.drinks)?data.drinks:[],
    desserts: Array.isArray(data.desserts)?data.desserts:[]
  };
  return CATALOG;
}



//  CARRINHO / PEDIDO 
const CART_KEY = 'bh_cart';
const ORDER_KEY = 'bh_last_order';

function readCart(){
  try { return JSON.parse(localStorage.getItem(CART_KEY)||'[]'); } catch(e){ return []; }
}
function writeCart(items){ localStorage.setItem(CART_KEY, JSON.stringify(items)); }
function addItemToCart(item){ const cart=readCart(); cart.push(item); writeCart(cart); bumpCartBadge(); }
function clearCart(){ writeCart([]); }

function bumpCartBadge(){ const c=readCart().length; const el=byId('cartCount'); if(el) el.textContent=c; }

//  CATÁLOGO (UI) 
let CURRENT_CAT = 'burgers';

function reRenderProducts(){
  const page = document.body.dataset.page; if(page!=='dashboard') return;
  renderCatalog(CURRENT_CAT);
}

let srObserver=null;
function setupObserver(){
  if(!('IntersectionObserver' in window)) return null;
  return new IntersectionObserver((entries, obs)=>{
    entries.forEach(en=>{
      if(en.isIntersecting){
        en.target.classList.add('sr-in');
        obs.unobserve(en.target);
      }
    });
  }, { threshold: 0.15, rootMargin: '0px 0px -10% 0px' });
}

function initScrollReveal(){
  const cards = $$('.grid .card-item');
  if(!cards.length) return;

  cards.forEach(c=>{
    c.classList.remove('sr-in');
    c.classList.remove('sr');
    c.style.removeProperty('--sr-delay');
  });

  requestAnimationFrame(()=>{
    cards.forEach((card, i)=>{
      card.classList.add('sr');
      card.style.setProperty('--sr-delay', `${(i % 6) * 90}ms`);
    });

    if(!('IntersectionObserver' in window)){
      cards.forEach(c=>c.classList.add('sr-in'));
      return;
    }

    if(srObserver) srObserver.disconnect();
    srObserver = setupObserver();
    cards.forEach(card=> srObserver.observe(card));
  });
}

async function renderCatalog(cat = 'burgers') {
  CURRENT_CAT = cat;
  const wrap = byId('catalog'); 
  if (!wrap) return;

  // garante dados
  if (!CATALOG) {
    try{
      await fetchCatalogFromAPI();
    }catch(e){
      wrap.innerHTML = `<div class="card" style="padding:16px;">Falha ao carregar catálogo. Tente novamente.</div>`;
      console.error(e);
      return;
    }
  }

  const lang = getLang();
  const list = (CATALOG[cat] || []);
  wrap.innerHTML = '';

  if (!list.length){
    wrap.innerHTML = `<div class="card" style="padding:16px;">Nenhum item disponível nesta categoria.</div>`;
    return;
  }

  list.forEach(prod => {
    const card = document.createElement('article');
    card.className = 'card-item';

    const bg = prod.img 
      ? `style="background-image:url('actions/uploads/${prod.img}');background-size:cover;background-position:center;"`
      : '';

    card.innerHTML = `
      <div class="card-media" ${bg}>
        <span class="badge-time"><i class="fa-regular fa-clock"></i> ${prod.prep} ${t('min')}</span>
      </div>
      <div class="card-body">
        <div class="card-title">
          <h3>${prod.name}</h3>
          <span class="price">${money(prod.price, lang)}</span>
        </div>
        <p class="card-desc">${prod.desc || ''}</p>
        <div class="card-actions">
          <span class="small"><i class="fa-solid fa-burger"></i> ${cat.toUpperCase()}</span>
          <button class="btn btn-primary" data-id="${prod.id}" data-cat="${cat}">
            <i class="fa-solid fa-plus"></i> ${t('add_to_order')}
          </button>
        </div>
      </div>`;
    wrap.appendChild(card);
  });

  initScrollReveal();
  window.addEventListener('load', ()=>initScrollReveal(), { once:true });
}

function handleTabClicks(){
  $$('.tab').forEach(btn=>{
    btn.addEventListener('click',()=>{
      $$('.tab').forEach(b=>b.classList.remove('active'));
      btn.classList.add('active');
      renderCatalog(btn.dataset.cat);
      window.scrollTo({top:0,behavior:'smooth'});
    });
  });
}

//  MODAL DO ITEM 
let ACTIVE_PRODUCT=null, ACTIVE_QTY=1, ACTIVE_EXTRAS=new Set();

function openItemModal(prod, cat){
  // prod.name e prod.desc agora já são strings no idioma atual
  ACTIVE_PRODUCT={...prod, cat}; ACTIVE_QTY=1; ACTIVE_EXTRAS=new Set();
  byId('modalTitle').textContent = prod.name || '';
  byId('modalDesc').textContent = prod.desc || '';
  byId('modalPrep').textContent = prod.prep;
  byId('modalPrice').textContent = money(prod.price);
  byId('removeNotes').value='';
  byId('qtyValue').textContent='1';
  const exWrap=byId('extrasWrap');
  exWrap.innerHTML='';
  (prod.extras||[]).forEach(ex=>{
    const id = `ex_${ex.k}`;
    const label=document.createElement('label');
    label.innerHTML = `<input type="checkbox" id="${id}" data-k="${ex.k}" data-p="${ex.p}"> <span>${ex.n}</span> <strong>${ex.p?money(ex.p):''}</strong>`;
    exWrap.appendChild(label);
  });
  const modal=byId('itemModal'); modal.classList.add('show'); modal.setAttribute('aria-hidden','false');
}
function closeItemModal(){ const modal=byId('itemModal'); modal.classList.remove('show'); modal.setAttribute('aria-hidden','true'); }

function bindCatalogClicks(){
  byId('catalog').addEventListener('click',(ev)=>{
    const btn = ev.target.closest('button[data-id]');
    if(!btn) return;
    const cat=btn.dataset.cat; const id=btn.dataset.id;
    const prod = (CATALOG && CATALOG[cat] || []).find(p=>String(p.id)===String(id));
    if(prod) openItemModal(prod, cat);
  });
}

function bindModal(){
  byId('closeItemModal').addEventListener('click', closeItemModal);
  byId('qtyPlus').addEventListener('click',()=>{ ACTIVE_QTY++; byId('qtyValue').textContent=ACTIVE_QTY; });
  byId('qtyMinus').addEventListener('click',()=>{ ACTIVE_QTY=Math.max(1, ACTIVE_QTY-1); byId('qtyValue').textContent=ACTIVE_QTY; });
  byId('extrasWrap').addEventListener('change',(e)=>{
    const cb=e.target; if(cb && cb.dataset.k){ if(cb.checked) ACTIVE_EXTRAS.add(cb.dataset.k); else ACTIVE_EXTRAS.delete(cb.dataset.k); }
  });
  byId('addToCart').addEventListener('click',()=>{
    if(!ACTIVE_PRODUCT) return;
    const notes = byId('removeNotes').value.trim();
    const extrasArr = (ACTIVE_PRODUCT.extras||[]).filter(ex=>ACTIVE_EXTRAS.has(ex.k));
    const extrasTotal = extrasArr.reduce((s,x)=>s+(x.p||0),0);
    const item = {
      id: ACTIVE_PRODUCT.id,
      cat: ACTIVE_PRODUCT.cat,
      name: ACTIVE_PRODUCT.name,
      basePrice: ACTIVE_PRODUCT.price,
      price: ACTIVE_PRODUCT.price + extrasTotal,
      qty: ACTIVE_QTY,
      prep: ACTIVE_PRODUCT.prep,
      extras: extrasArr.map(x=>({k:x.k, n:x.n, p:x.p})),
      remove: notes,
      thumb: ACTIVE_PRODUCT.img || null
    };
    addItemToCart(item);
    renderCart();
    closeItemModal();
    openCart();
  });
}

function getProductImage(cat, id){
  const prod = (CATALOG && CATALOG[cat] || []).find(p=>String(p.id)===String(id));
  return prod && prod.img ? prod.img : null;
}

//  CARRINHO (UI) 
function renderCart(){
  const page = document.body.dataset.page; if(page!=='dashboard') return;
  const list = byId('cartItems'); if(!list) return;
  const cart = readCart();
  list.innerHTML='';
  let subtotal=0, totalTime=0;

  cart.forEach((it, idx)=>{
    const extras = it.extras && it.extras.length ? ` • ${it.extras.map(e=>`${e.n}${e.p?` (${money(e.p)})`:''}`).join(', ')}` : '';
    const remove = it.remove ? `<div class="meta">- ${it.remove}</div>` : '';
    const lineTotal = it.price * it.qty;
    subtotal += lineTotal; totalTime += (it.prep||0) * it.qty;

    const imgFile = it.thumb || getProductImage(it.cat, it.id) || '';
    const imgTag = imgFile ? `<img class="thumb" src="actions/uploads/${imgFile}" alt="${it.name}">` : `<div class="thumb" aria-hidden="true"></div>`;

    const row=document.createElement('div'); row.className='cart-item';
    row.innerHTML = `
      <button class="qty-btn" data-action="sub" data-idx="${idx}" aria-label="Diminuir 1"><i class="fa-solid fa-minus"></i></button>
      ${imgTag}
      <div>
        <div class="title">${it.qty}× ${it.name} — ${money(it.price)}</div>
        <div class="meta">${it.cat.toUpperCase()}${extras}</div>
        ${remove}
      </div>
      <div>
        <div class="price">${money(lineTotal)}</div>
        <div class="small" style="text-align:right;margin-top:6px;">
          <button class="btn btn-danger btn-sm" data-action="del" data-idx="${idx}" aria-label="Remover item">
            <i class="fa-solid fa-trash-can"></i> Remover
          </button>
        </div>
      </div>`;
    list.appendChild(row);
  });

  byId('subtotal').textContent = money(subtotal);
  byId('totalTime').textContent = `${Math.max(totalTime, cart.reduce((m,i)=>Math.max(m,i.prep||0),0))} ${t('min')}`;
  bumpCartBadge();

  list.addEventListener('click', (ev)=>{
    const btn = ev.target.closest('[data-action]'); if(!btn) return;
    const idx = +btn.dataset.idx; const cart = readCart();
    if(btn.dataset.action==='del'){ cart.splice(idx,1); writeCart(cart); renderCart(); bumpCartBadge(); }
    if(btn.dataset.action==='sub'){ cart[idx].qty = Math.max(1, cart[idx].qty-1); writeCart(cart); renderCart(); }
  }, {once:true});
}

function openCart(){ const d=byId('cartDrawer'); if(!d) return; d.classList.add('show'); d.setAttribute('aria-hidden','false'); }
function closeCart(){ const d=byId('cartDrawer'); if(!d) return; d.classList.remove('show'); d.setAttribute('aria-hidden','true'); }

//  CHECKOUT 
function renderSummary(){
  const page = document.body.dataset.page; if(page!=='checkout') return;
  const list = byId('summaryList'); if(!list) return;
  const cart = readCart();
  list.innerHTML='';
  let subtotal=0, totalTime=0;
  cart.forEach(it=>{
    const div=document.createElement('div'); div.className='row';
    const extras = it.extras && it.extras.length ? ` • ${it.extras.map(e=>`${e.n}${e.p?` (${money(e.p)})`:''}`).join(', ')}` : '';
    const remove = it.remove ? ` — <em>${it.remove}</em>` : '';
    const lineTotal=it.price*it.qty; subtotal+=lineTotal; totalTime += (it.prep||0)*it.qty;
    div.innerHTML=`<div>${it.qty}× ${it.name}${extras}${remove}</div><strong>${money(lineTotal)}</strong>`;
    list.appendChild(div);
  });
  byId('sumSubtotal').textContent=money(subtotal);
  byId('sumTime').textContent=`${Math.max(totalTime, cart.reduce((m,i)=>Math.max(m,i.prep||0),0))} ${t('min')}`;
}

function bindPaymentUI(){
  if(document.body.dataset.page!=='checkout') return;
  const radios = $$('input[name="pay"]');
  const boxPix = byId('payPix');

  const update = () => {
    const v = $('input[name="pay"]:checked').value;
    if(boxPix) boxPix.hidden = (v !== 'pix');
  };
  radios.forEach(r=> r.addEventListener('change', update));
  update();

  const copyBtn = byId('copyPix');
  const hidden = byId('pixHiddenCode');
  if(copyBtn){
    const code = copyBtn.dataset.code || (hidden ? hidden.value.trim() : '');
    copyBtn.addEventListener('click', async ()=>{
      try{
        if(navigator.clipboard?.writeText){
          await navigator.clipboard.writeText(code);
        }else if(hidden){
          hidden.removeAttribute('disabled');
          hidden.focus(); hidden.select();
          document.execCommand('copy');
        }
        copyBtn.innerHTML = '<i class="fa-solid fa-check"></i> Copiado!';
        setTimeout(()=> copyBtn.innerHTML = '<i class="fa-regular fa-copy"></i> Copiar código PIX', 1400);
      }catch(e){
        alert('Não foi possível copiar. Tente novamente.');
      }
    });
  }
}

//  STATUS 
function placeOrder(){
  const cart = readCart();
  if(!cart.length){
    alert('Seu carrinho está vazio.');
    return;
  }
  const id = String(Math.floor(100000 + Math.random()*900000));
  const ts = now();
  const items = cart.map(it => ({
    ...it,
    start: ts,
    readyAt: ts + (it.prep || 0) * 60000
  }));
  const order = { id, createdAt: ts, items };
  localStorage.setItem(ORDER_KEY, JSON.stringify(order));
  clearCart();
  window.location.href = 'status.html';
}

function renderStatus(){
  if(document.body.dataset.page!=='status') return;
  const raw = localStorage.getItem(ORDER_KEY); if(!raw){ byId('statusList').innerHTML='<div class="card">Nenhum pedido ativo.</div>'; return; }
  const order = JSON.parse(raw);
  byId('orderCode').textContent = `#${order.id}`;
  const list = byId('statusList'); list.innerHTML='';
  let allReady=true; let maxReadyAt=0;
  order.items.forEach(it=>{
    const elapsed = Math.max(0, now() - it.start);
    const duration = it.prep*60*1000;
    const pct = Math.min(100, Math.floor((elapsed/duration)*100));
    const remainingMs = Math.max(0, duration - elapsed);
    const remainingMin = Math.ceil(remainingMs/60000);
    if(pct<100) allReady=false; maxReadyAt = Math.max(maxReadyAt, it.readyAt);

    const row=document.createElement('div'); row.className='row';
    row.innerHTML=`
      <div>
        <div><strong>${it.qty}× ${it.name}</strong></div>
        <div class="small">${it.extras&&it.extras.length?('• '+it.extras.map(e=>e.n).join(', ')):'—'} ${it.remove?(' — '+it.remove):''}</div>
      </div>
      <div style="min-width:190px;">
        <div class="progress"><span style="width:${pct}%"></span></div>
        <div class="small" style="text-align:right;margin-top:6px;">${pct<100?(`${remainingMin} ${t('min')}`):t('ready')}</div>
      </div>`;
    list.appendChild(row);
  });
  const state = byId('orderState');
  state.textContent = allReady ? t('ready') : t('preparing');
  state.style.background = allReady ? 'var(--ketchup)' : 'var(--mustard)';

  if(!window.__statusTimer){ window.__statusTimer=setInterval(renderStatus, 5000); }
}

//  BOOT 
function bindGlobal(){
  $$('#langSelect').forEach(sel => sel.addEventListener('change', e => setLang(e.target.value)));
  applyI18n();

  const open = byId('openCart');  if (open)  open.addEventListener('click', openCart);
  const close = byId('closeCart'); if (close) close.addEventListener('click', closeCart);

  const go = byId('goCheckout');
  if (go) go.addEventListener('click', () => {
    window.location.href = 'paginas/checkout.html'; 
  });

  if (document.body.dataset.page === 'dashboard'){
    handleTabClicks();
    bindCatalogClicks();
    bindModal();
    // garante fetch antes da primeira renderização
    fetchCatalogFromAPI().then(()=> renderCatalog('burgers'))
                        .catch(()=> renderCatalog('burgers'));
    bumpCartBadge();
    renderCart();
    setTimeout(()=>initScrollReveal(), 300);
  }

  if (document.body.dataset.page === 'checkout'){
    renderSummary();
    bindPaymentUI();
    const confirmBtn = byId('confirmPayment');
    if (confirmBtn) confirmBtn.addEventListener('click', placeOrder);
  }

  if (document.body.dataset.page === 'status'){
    renderStatus();
  }

  const y = byId('year'); if (y) y.textContent = new Date().getFullYear();
}

document.addEventListener('DOMContentLoaded', bindGlobal);

function normalizeLang(v){
  v = (v || 'pt').toLowerCase();
  if (v.startsWith('pt')) return 'pt';
  if (v.startsWith('en')) return 'en';
  if (v.startsWith('es')) return 'es';
  return 'pt';
}
function getLang(){ return normalizeLang(localStorage.getItem('lang')); }
// ...
const url = `./actions/public/get_catalog.php?lang=${encodeURIComponent(getLang())}`;
