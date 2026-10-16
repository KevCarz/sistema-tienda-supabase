<?php
/**
 * index.php — Pantalla única móvil. Consume api.php con fetch().
 * Estilos: usa assets/app.css si existe (offline); si no, Tailwind por CDN (necesita internet).
 * Para compilar el CSS local ver README.md → "Modo sin internet".
 */
declare(strict_types=1);
require_once __DIR__ . '/config.php';
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#4f46e5">
<title><?= htmlspecialchars(APP_NAME) ?></title>
<?php if (is_file(__DIR__ . '/assets/app.css')): ?>
<link rel="stylesheet" href="assets/app.css"><!-- CSS compilado local: funciona sin internet -->
<?php else: ?>
<script src="https://cdn.tailwindcss.com"></script><!-- Tailwind por CDN: requiere internet -->
<?php endif; ?>
<style>
  html, body { -webkit-tap-highlight-color: transparent; overscroll-behavior-y: contain; }
  .pb-safe { padding-bottom: max(1rem, env(safe-area-inset-bottom)); }
  .sheet-enter { animation: up .22s ease-out; }
  @keyframes up { from { transform: translateY(100%); } to { transform: translateY(0); } }
  input, select, textarea { font-size: 16px !important; } /* evita el zoom en iOS */
</style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">

<header class="sticky top-0 z-30 bg-indigo-600 text-white px-4 py-3 flex items-center gap-3 shadow">
  <div class="w-9 h-9 rounded-full bg-indigo-400 flex items-center justify-center text-xl">🏪</div>
  <div class="flex-1 min-w-0">
    <div class="text-sm font-semibold truncate"><?= htmlspecialchars(APP_NAME) ?></div>
    <div id="reloj" class="text-xs text-indigo-200 truncate"></div>
  </div>
  <button id="btnRefresh" class="text-xs bg-indigo-700 active:bg-indigo-800 px-3 py-2 rounded-lg">Actualizar</button>
</header>

<main class="p-4 space-y-4 max-w-lg mx-auto pb-24">

  <!-- Acciones rápidas -->
  <section class="grid grid-cols-2 gap-3">
    <button data-open="venta"  class="bg-emerald-500 active:bg-emerald-600 text-white rounded-2xl py-5 font-semibold shadow active:scale-95 transition">
      <div class="text-2xl">💰</div>Registrar Venta</button>
    <button data-open="compra" class="bg-amber-500 active:bg-amber-600 text-white rounded-2xl py-5 font-semibold shadow active:scale-95 transition">
      <div class="text-2xl">🛒</div>Registrar Compra</button>
    <button data-open="productos"   class="bg-white rounded-2xl py-4 font-medium shadow-sm active:scale-95 transition"><div class="text-xl">📦</div>Productos</button>
    <button data-open="clientes"    class="bg-white rounded-2xl py-4 font-medium shadow-sm active:scale-95 transition"><div class="text-xl">👥</div>Clientes</button>
    <button data-open="proveedores" class="bg-white rounded-2xl py-4 font-medium shadow-sm active:scale-95 transition"><div class="text-xl">🚚</div>Proveedores</button>
    <button data-open="movimientos" class="bg-indigo-600 active:bg-indigo-700 text-white rounded-2xl py-4 font-medium shadow-sm active:scale-95 transition"><div class="text-xl">📋</div>Movimientos</button>
  </section>

  <!-- Resumen -->
  <section class="bg-white rounded-2xl p-4 shadow-sm">
    <div class="text-xs uppercase tracking-wide text-slate-500">Ventas de hoy</div>
    <div id="vHoy" class="text-3xl font-bold text-emerald-600 mt-1">…</div>
    <div id="vHoyN" class="text-sm text-slate-500"></div>
  </section>

  <section id="boxBajo" class="hidden bg-red-50 border border-red-200 rounded-2xl p-4">
    <div class="font-semibold text-red-700 mb-2">⚠️ Stock bajo</div>
    <ul id="listBajo" class="space-y-1 text-sm"></ul>
  </section>

  <section class="bg-white rounded-2xl p-4 shadow-sm">
    <div class="font-semibold mb-2">Últimas transacciones</div>
    <ul id="listUlt" class="divide-y divide-slate-100 text-sm"></ul>
  </section>
</main>

<!-- Modal tipo "bottom sheet" -->
<div id="overlay" class="hidden fixed inset-0 z-40 bg-black/50 flex items-end justify-center">
  <div id="sheet" class="sheet-enter bg-white w-full max-w-lg rounded-t-3xl max-h-[92vh] flex flex-col">
    <div class="flex items-center justify-between px-5 pt-5 pb-3">
      <h2 id="sheetTitle" class="text-lg font-bold"></h2>
      <button id="btnClose" class="w-9 h-9 rounded-full bg-slate-100 text-lg active:bg-slate-200">✕</button>
    </div>
    <div id="sheetBody" class="px-5 pb-safe overflow-y-auto"></div>
  </div>
</div>

<div id="toast" class="hidden fixed left-1/2 -translate-x-1/2 bottom-6 z-50 px-4 py-3 rounded-xl text-white text-sm shadow-lg max-w-[90vw]"></div>

<script>
const CURRENCY = <?= json_encode(CURRENCY) ?>;
let CSRF = <?= json_encode($_SESSION['csrf']) ?>;
let DB = { clientes: [], proveedores: [], productos: [], resumen: null };

// ── Utilidades ─────────────────────────────────────────────
const $ = s => document.querySelector(s);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const money = n => CURRENCY + Number(n || 0).toFixed(2);
const inputCls = 'w-full border border-slate-300 rounded-xl px-4 py-3 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-400';
const btnCls = 'w-full text-white font-bold text-lg rounded-2xl py-4 active:scale-95 transition disabled:opacity-50';

function toast(msg, ok = true) {
  const t = $('#toast');
  t.textContent = msg;
  t.className = 'fixed left-1/2 -translate-x-1/2 bottom-6 z-50 px-4 py-3 rounded-xl text-white text-sm shadow-lg max-w-[90vw] ' + (ok ? 'bg-emerald-600' : 'bg-red-600');
  clearTimeout(toast.t);
  toast.t = setTimeout(() => t.classList.add('hidden'), 3500);
}

function tickClock() {
  $('#reloj').textContent = new Date().toLocaleString('es-BO', { dateStyle: 'full', timeStyle: 'short' });
}
tickClock(); setInterval(tickClock, 30000);

// ── Llamadas al backend ────────────────────────────────────
async function api(action, data = null) {
  const res = await fetch('api.php?action=' + action, {
    method: data ? 'POST' : 'GET',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
    body: data ? JSON.stringify(data) : undefined,
  });
  const json = await res.json().catch(() => ({ ok: false, error: 'Respuesta inválida del servidor' }));
  if (!json.ok) throw new Error(json.error || 'Error desconocido');
  CSRF = json.csrf || CSRF;
  if (json.resumen) {          // solo las respuestas de "estado" actualizan los datos principales
    DB = json;
    renderSummary();
  }
  return json;
}

/** Envía un formulario: bloquea el botón, llama al API y ejecuta onOk. */
async function submitForm(form, action, onOk) {
  const btn = form.querySelector('button[type=submit]');
  btn.disabled = true;
  const old = btn.textContent;
  btn.textContent = 'Guardando…';
  try {
    await api(action, Object.fromEntries(new FormData(form)));
    toast('✅ Guardado');
    onOk();
  } catch (e) { toast(e.message, false); }
  btn.disabled = false;
  btn.textContent = old;
}

// ── Resumen ────────────────────────────────────────────────
function renderSummary() {
  const r = DB.resumen; if (!r) return;
  $('#vHoy').textContent = money(r.ventas_hoy_total);
  $('#vHoyN').textContent = r.ventas_hoy_count + ' venta(s) hoy';

  $('#boxBajo').classList.toggle('hidden', r.stock_bajo.length === 0);
  $('#listBajo').innerHTML = r.stock_bajo.map(p =>
    `<li class="flex justify-between"><span>${esc(p.nombre)}</span>
     <b class="${p.stock_actual === 0 ? 'text-red-700' : 'text-amber-600'}">${p.stock_actual} u.</b></li>`).join('');

  $('#listUlt').innerHTML = r.ultimas.length ? r.ultimas.map(t => `
    <li class="py-2 flex items-center gap-3">
      <span class="text-xl">${t.tipo === 'venta' ? '💰' : '🛒'}</span>
      <div class="flex-1 min-w-0">
        <div class="truncate">${esc(t.producto)} × ${t.cantidad}</div>
        <div class="text-xs text-slate-400">${esc(t.fecha_hora)}</div>
      </div>
      <b class="${t.tipo === 'venta' ? 'text-emerald-600' : 'text-amber-600'}">${t.tipo === 'venta' ? '+' : '−'}${money(t.total)}</b>
    </li>`).join('') : '<li class="py-3 text-slate-400">Aún no hay transacciones.</li>';
}

// ── Modal ──────────────────────────────────────────────────
function openSheet(title, html) {
  $('#sheetTitle').textContent = title;
  $('#sheetBody').innerHTML = html;
  $('#overlay').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}
function closeSheet() {
  $('#overlay').classList.add('hidden');
  document.body.style.overflow = '';
}
$('#btnClose').onclick = closeSheet;
$('#overlay').addEventListener('click', e => { if (e.target.id === 'overlay') closeSheet(); });
$('#btnRefresh').onclick = () => api('state').then(() => toast('Datos actualizados')).catch(e => toast(e.message, false));

const label = t => `<label class="block text-sm font-medium text-slate-600 mb-1 mt-3">${t}</label>`;
const options = (rows, fn, ph) => `<option value="">${ph}</option>` + rows.map(fn).join('');

// ── Formulario de VENTA ────────────────────────────────────
function viewVenta() {
  if (!DB.clientes.length) return toast('Primero registra un cliente.', false);
  if (!DB.productos.length) return toast('Primero registra un producto.', false);
  openSheet('Registrar Venta', `
    <form id="f" class="pb-2">
      ${label('Cliente')}
      <select name="cliente_id" required class="${inputCls}">${options(DB.clientes, c => `<option value="${c.id}">${esc(c.nombre)}</option>`, 'Selecciona cliente…')}</select>
      ${label('Producto')}
      <select name="producto_id" id="selProd" required class="${inputCls}">${options(DB.productos,
        p => `<option value="${p.id}" ${p.stock_actual <= 0 ? 'disabled' : ''}>${esc(p.nombre)} — ${money(p.precio_venta)} (stock ${p.stock_actual})</option>`, 'Selecciona producto…')}</select>
      ${label('Cantidad')}
      <input name="cantidad" id="qty" type="number" inputmode="numeric" min="1" step="1" value="1" required class="${inputCls}">
      <div class="mt-5 mb-4 bg-emerald-50 rounded-2xl p-4 flex justify-between items-center">
        <span class="text-slate-600">Total</span>
        <b id="total" class="text-3xl text-emerald-600">${money(0)}</b>
      </div>
      <button type="submit" class="${btnCls} bg-emerald-500">Confirmar venta</button>
    </form>`);
  const calc = () => {
    const p = DB.productos.find(x => x.id == $('#selProd').value);
    $('#total').textContent = money(p ? p.precio_venta * (parseInt($('#qty').value) || 0) : 0);
  };
  $('#selProd').onchange = calc; $('#qty').oninput = calc;
  $('#f').onsubmit = e => { e.preventDefault(); submitForm(e.target, 'venta', closeSheet); };
}

// ── Formulario de COMPRA ───────────────────────────────────
function viewCompra() {
  if (!DB.proveedores.length) return toast('Primero registra un proveedor.', false);
  if (!DB.productos.length) return toast('Primero registra un producto.', false);
  openSheet('Registrar Compra', `
    <form id="f" class="pb-2">
      ${label('Proveedor')}
      <select name="proveedor_id" required class="${inputCls}">${options(DB.proveedores, p => `<option value="${p.id}">${esc(p.nombre)}</option>`, 'Selecciona proveedor…')}</select>
      ${label('Producto')}
      <select name="producto_id" id="selProd" required class="${inputCls}">${options(DB.productos, p => `<option value="${p.id}">${esc(p.nombre)} (stock ${p.stock_actual})</option>`, 'Selecciona producto…')}</select>
      ${label('Cantidad')}
      <input name="cantidad" id="qty" type="number" inputmode="numeric" min="1" step="1" value="1" required class="${inputCls}">
      ${label('Costo unitario')}
      <input name="costo_unitario" id="cost" type="number" inputmode="decimal" min="0" step="0.01" required class="${inputCls}">
      <div class="mt-5 mb-4 bg-amber-50 rounded-2xl p-4 flex justify-between items-center">
        <span class="text-slate-600">Total</span>
        <b id="total" class="text-3xl text-amber-600">${money(0)}</b>
      </div>
      <button type="submit" class="${btnCls} bg-amber-500">Confirmar compra</button>
    </form>`);
  const calc = () => $('#total').textContent = money((parseFloat($('#cost').value) || 0) * (parseInt($('#qty').value) || 0));
  $('#selProd').onchange = () => {
    const p = DB.productos.find(x => x.id == $('#selProd').value);
    if (p) $('#cost').value = p.costo_compra;
    calc();
  };
  $('#qty').oninput = calc; $('#cost').oninput = calc;
  $('#f').onsubmit = e => { e.preventDefault(); submitForm(e.target, 'compra', closeSheet); };
}

// ── Catálogos (lista + alta) ───────────────────────────────
function viewProductos() {
  const list = DB.productos.map(p => `
    <li class="py-3 flex justify-between gap-3">
      <div class="min-w-0"><div class="font-medium truncate">${esc(p.nombre)}</div>
      <div class="text-xs text-slate-500 truncate">${esc(p.descripcion)}</div></div>
      <div class="text-right shrink-0"><div class="font-semibold">${money(p.precio_venta)}</div>
      <div class="text-xs ${p.stock_actual <= DB.resumen.limite_stock ? 'text-red-600 font-bold' : 'text-slate-500'}">stock ${p.stock_actual}</div></div>
    </li>`).join('') || '<li class="py-3 text-slate-400">Sin productos.</li>';
  openSheet('Productos', `
    <details class="mb-3 bg-indigo-50 rounded-2xl p-3" ${DB.productos.length ? '' : 'open'}>
      <summary class="font-semibold text-indigo-700 cursor-pointer">＋ Nuevo producto</summary>
      <form id="f">
        ${label('Nombre')}<input name="nombre" required class="${inputCls}">
        ${label('Descripción')}<input name="descripcion" class="${inputCls}">
        <div class="grid grid-cols-2 gap-3">
          <div>${label('Precio venta')}<input name="precio_venta" type="number" inputmode="decimal" step="0.01" min="0" required class="${inputCls}"></div>
          <div>${label('Costo compra')}<input name="costo_compra" type="number" inputmode="decimal" step="0.01" min="0" required class="${inputCls}"></div>
        </div>
        ${label('Stock inicial')}<input name="stock_actual" type="number" inputmode="numeric" min="0" value="0" class="${inputCls}">
        ${label('Proveedor (opcional)')}<select name="proveedor_id" class="${inputCls}">${options(DB.proveedores, p => `<option value="${p.id}">${esc(p.nombre)}</option>`, '— Ninguno —')}</select>
        <button type="submit" class="${btnCls} bg-indigo-600 mt-4">Guardar producto</button>
      </form>
    </details>
    <ul class="divide-y divide-slate-100 pb-2">${list}</ul>`);
  $('#f').onsubmit = e => { e.preventDefault(); submitForm(e.target, 'add_producto', viewProductos); };
}

function viewClientes() {
  const list = DB.clientes.map(c => `
    <li class="py-3"><div class="font-medium">${esc(c.nombre)}</div>
    <div class="text-xs text-slate-500">${esc(c.telefono)} ${c.email ? '· ' + esc(c.email) : ''}</div></li>`).join('')
    || '<li class="py-3 text-slate-400">Sin clientes.</li>';
  openSheet('Clientes', `
    <details class="mb-3 bg-indigo-50 rounded-2xl p-3" ${DB.clientes.length ? '' : 'open'}>
      <summary class="font-semibold text-indigo-700 cursor-pointer">＋ Nuevo cliente</summary>
      <form id="f">
        ${label('Nombre')}<input name="nombre" required class="${inputCls}">
        ${label('Teléfono')}<input name="telefono" type="tel" class="${inputCls}">
        ${label('Email')}<input name="email" type="email" class="${inputCls}">
        <button type="submit" class="${btnCls} bg-indigo-600 mt-4">Guardar cliente</button>
      </form>
    </details>
    <ul class="divide-y divide-slate-100 pb-2">${list}</ul>`);
  $('#f').onsubmit = e => { e.preventDefault(); submitForm(e.target, 'add_cliente', viewClientes); };
}

function viewProveedores() {
  const list = DB.proveedores.map(p => `
    <li class="py-3"><div class="font-medium">${esc(p.nombre)}</div>
    <div class="text-xs text-slate-500">${esc(p.contacto)} ${p.telefono ? '· ' + esc(p.telefono) : ''}</div></li>`).join('')
    || '<li class="py-3 text-slate-400">Sin proveedores.</li>';
  openSheet('Proveedores', `
    <details class="mb-3 bg-indigo-50 rounded-2xl p-3" ${DB.proveedores.length ? '' : 'open'}>
      <summary class="font-semibold text-indigo-700 cursor-pointer">＋ Nuevo proveedor</summary>
      <form id="f">
        ${label('Nombre')}<input name="nombre" required class="${inputCls}">
        ${label('Persona de contacto')}<input name="contacto" class="${inputCls}">
        ${label('Teléfono')}<input name="telefono" type="tel" class="${inputCls}">
        <button type="submit" class="${btnCls} bg-indigo-600 mt-4">Guardar proveedor</button>
      </form>
    </details>
    <ul class="divide-y divide-slate-100 pb-2">${list}</ul>`);
  $('#f').onsubmit = e => { e.preventDefault(); submitForm(e.target, 'add_proveedor', viewProveedores); };
}

// ── Movimientos (ventas + compras, últimos 120) ────────────
async function viewMovimientos() {
  openSheet('Movimientos', '<div class="py-10 text-center text-slate-400">Cargando…</div>');
  let rows, limite;
  try {
    const r = await api('movimientos');
    rows = r.movimientos; limite = r.limite;
  } catch (e) { closeSheet(); return toast(e.message, false); }

  let filtro = 'todos';
  const chip = (id, txt) => `<button data-f="${id}" class="chip px-4 py-2 rounded-full text-sm font-medium border"> ${txt}</button>`;

  const item = m => {
    const venta = m.tipo === 'venta';
    const quien = venta
      ? '👤 ' + esc(m.cliente) + (m.proveedor ? ' · 🚚 ' + esc(m.proveedor) : '')
      : '🚚 ' + esc(m.proveedor);
    return `
      <li class="py-3 flex gap-3">
        <span class="text-xl">${venta ? '💰' : '🛒'}</span>
        <div class="flex-1 min-w-0">
          <div class="font-medium truncate">${esc(m.producto)} × ${m.cantidad}</div>
          <div class="text-xs text-slate-600 truncate">${quien}</div>
          <div class="text-xs text-slate-400">${esc(m.fecha_hora)} · ${money(m.valor_unitario)} c/u</div>
        </div>
        <b class="shrink-0 ${venta ? 'text-emerald-600' : 'text-amber-600'}">${venta ? '+' : '−'}${money(m.total)}</b>
      </li>`;
  };

  const draw = () => {
    const vis = rows.filter(m => filtro === 'todos' || m.tipo === filtro);
    document.querySelectorAll('#movChips .chip').forEach(b => {
      const on = b.dataset.f === filtro;
      b.className = 'chip px-4 py-2 rounded-full text-sm font-medium border ' +
        (on ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-300');
    });
    $('#movBody').innerHTML = vis.length
      ? `<ul class="divide-y divide-slate-100">${vis.map(item).join('')}</ul>`
      : '<div class="py-10 text-center text-slate-400">No hay movimientos.</div>';
    $('#movCount').textContent = `Mostrando ${vis.length} de ${rows.length} (máx. ${limite}, los más recientes)`;
  };

  const suma = t => rows.filter(m => m.tipo === t).reduce((a, m) => a + m.total, 0);
  $('#sheetBody').innerHTML = `
    <div class="grid grid-cols-2 gap-3 mb-3">
      <div class="bg-emerald-50 rounded-2xl p-3"><div class="text-xs text-slate-500">Ventas</div><b class="text-emerald-600">${money(suma('venta'))}</b></div>
      <div class="bg-amber-50 rounded-2xl p-3"><div class="text-xs text-slate-500">Compras</div><b class="text-amber-600">${money(suma('compra'))}</b></div>
    </div>
    <div id="movChips" class="flex gap-2 mb-2">${chip('todos', 'Todos')}${chip('venta', '💰 Ventas')}${chip('compra', '🛒 Compras')}</div>
    <div id="movCount" class="text-xs text-slate-400 mb-1"></div>
    <div id="movBody" class="pb-2"></div>`;
  document.querySelectorAll('#movChips .chip').forEach(b => b.onclick = () => { filtro = b.dataset.f; draw(); });
  draw();
}

// ── Arranque ───────────────────────────────────────────────
const VIEWS = { venta: viewVenta, compra: viewCompra, productos: viewProductos, clientes: viewClientes, proveedores: viewProveedores, movimientos: viewMovimientos };
document.querySelectorAll('[data-open]').forEach(b => b.onclick = () => VIEWS[b.dataset.open]());
api('state').catch(e => toast(e.message, false));
</script>
</body>
</html>
