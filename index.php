<?php
/**
 * index.php — Pantalla optimizada para Escritorio y Móvil.
 */
declare(strict_types=1);
require_once __DIR__ . '/config.php';
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#4f46e5">
<title><?php echo htmlspecialchars(APP_NAME); ?></title>
<script>
let darkModeEnabled = false;
try {
  darkModeEnabled = localStorage.getItem('theme') === 'dark';
} catch (error) {
  console.error('No se pudo cargar la preferencia de tema.', error);
}
if (darkModeEnabled) document.documentElement.classList.add('dark-mode');
</script>
<?php if (is_file(__DIR__ . '/assets/app.css')): ?>
<link rel="stylesheet" href="assets/app.css">
<?php else: ?>
<script src="https://cdn.tailwindcss.com"></script>
<?php endif; ?>
<style>
  html, body { -webkit-tap-highlight-color: transparent; overscroll-behavior-y: contain; }
  .pb-safe { padding-bottom: max(1rem, env(safe-area-inset-bottom)); }
  .sheet-enter { animation: up .22s ease-out; }
  @keyframes up { from { transform: translateY(100%); } to { transform: translateY(0); } }
  input, select, textarea { font-size: 16px !important; }
  html, body { transition: background-color .25s ease, color .25s ease; }
  html.dark-mode { color-scheme: dark; }
  html.dark-mode, html.dark-mode body { background-color: #0b1120; color: #e2e8f0; }
  .app-header {
    background: linear-gradient(115deg, #4f46e5, #4338ca 55%, #3730a3);
    border-bottom: 1px solid rgb(199 210 254 / .24);
    box-shadow: 0 8px 24px rgb(30 27 75 / .2);
    backdrop-filter: blur(14px);
  }
  .app-header-inner {
    max-width: 1400px;
    min-height: 76px;
    margin-inline: auto;
    padding: 12px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
  }
  .app-brand {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 12px;
    color: inherit;
    text-decoration: none;
    border-radius: 14px;
  }
  .app-brand:focus-visible, .app-header-button:focus-visible {
    outline: 3px solid #c7d2fe;
    outline-offset: 3px;
  }
  .app-brand-icon {
    width: 46px;
    height: 46px;
    flex: 0 0 auto;
    display: grid;
    place-items: center;
    border: 1px solid rgb(255 255 255 / .24);
    border-radius: 15px;
    background: linear-gradient(145deg, rgb(255 255 255 / .22), rgb(255 255 255 / .08));
    box-shadow: inset 0 1px rgb(255 255 255 / .18), 0 5px 12px rgb(30 27 75 / .18);
    font-size: 23px;
  }
  .app-brand-copy { min-width: 0; display: flex; flex-direction: column; gap: 5px; }
  .app-brand-name { overflow: hidden; font-size: 16px; font-weight: 750; letter-spacing: -.02em; text-overflow: ellipsis; white-space: nowrap; }
  .app-brand-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 7px 10px; }
  .app-clock { color: #dbeafe; font-size: 11px; line-height: 1.25; }
  .app-status {
    max-width: 100%;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 8px;
    border: 1px solid rgb(110 231 183 / .3);
    border-radius: 999px;
    background: rgb(16 185 129 / .15);
    color: #d1fae5;
    font-size: 10px;
    line-height: 1.3;
    white-space: nowrap;
  }
  .app-status-dot {
    width: 7px;
    height: 7px;
    flex: 0 0 auto;
    border-radius: 50%;
    background: #6ee7b7;
    animation: status-pulse 1.8s ease-in-out infinite;
  }
  .app-status.is-offline { border-color: rgb(252 165 165 / .3); background: rgb(239 68 68 / .16); color: #fee2e2; }
  .app-status-dot.is-offline { background: #fca5a5; animation: none; }
  @keyframes status-pulse { 50% { box-shadow: 0 0 0 4px rgb(110 231 183 / .16); } }
  .app-header-actions { display: flex; align-items: center; gap: 8px; flex: 0 0 auto; }
  .app-header-button {
    min-height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 0 13px;
    border: 1px solid rgb(255 255 255 / .16);
    border-radius: 12px;
    background: rgb(30 27 75 / .22);
    color: #fff;
    font-size: 12px;
    font-weight: 650;
    transition: background-color .18s ease, transform .18s ease, border-color .18s ease;
  }
  .app-header-button:hover { background: rgb(30 27 75 / .38); border-color: rgb(255 255 255 / .3); }
  .app-header-button:active { transform: translateY(1px); }
  .app-header-button svg { width: 16px; height: 16px; }
  html.dark-mode .app-header { background: linear-gradient(110deg, #3730a3, #211c55); border-bottom-color: #6366f1; }
  html.dark-mode .app-header-button { background: rgb(15 23 42 / .32); }
  @media (max-width: 520px) {
    .app-header-inner { min-height: 68px; padding: 10px 14px; gap: 8px; }
    .app-brand { gap: 9px; }
    .app-brand-icon { width: 40px; height: 40px; border-radius: 13px; font-size: 20px; }
    .app-brand-copy { gap: 4px; }
    .app-brand-name { font-size: 14px; }
    .app-brand-meta { gap: 4px 6px; }
    .app-clock { font-size: 10px; }
    .app-status { max-width: 145px; overflow: hidden; text-overflow: ellipsis; }
    .app-header-actions { gap: 6px; }
    .app-header-button { width: 42px; min-height: 42px; padding: 0; border-radius: 12px; }
    .app-action-label { display: none; }
  }
  @media (prefers-reduced-motion: reduce) {
    .app-header-button, .app-status-dot { transition: none; animation: none; }
  }
  html.dark-mode .bg-white { background-color: #1e293b; }
  html.dark-mode .bg-slate-100 { background-color: #151e2e; }
  html.dark-mode .bg-slate-200 { background-color: #334155; }
  html.dark-mode .bg-slate-50 { background-color: #202c3d; }
  html.dark-mode .bg-indigo-50 { background-color: #282653; }
  html.dark-mode .bg-indigo-100 { background-color: #37356d; }
  html.dark-mode .bg-emerald-50 { background-color: #123b36; }
  html.dark-mode .bg-amber-50 { background-color: #49331e; }
  html.dark-mode .bg-red-50 { background-color: #431f2b; }
  html.dark-mode .text-slate-800 { color: #e2e8f0; }
  html.dark-mode .text-slate-700 { color: #dbe5f2; }
  html.dark-mode .text-slate-600 { color: #c6d2e1; }
  html.dark-mode .text-slate-500 { color: #a8b7ca; }
  html.dark-mode .text-slate-400 { color: #8798ae; }
  html.dark-mode .text-indigo-700 { color: #c4c5ff; }
  html.dark-mode .text-indigo-600 { color: #a5b4fc; }
  html.dark-mode .text-emerald-700 { color: #86efc3; }
  html.dark-mode .text-emerald-600 { color: #6ee7b7; }
  html.dark-mode .text-amber-600 { color: #fcd34d; }
  html.dark-mode .text-red-700 { color: #fda4af; }
  html.dark-mode .border-slate-300,
  html.dark-mode .border-slate-100,
  html.dark-mode .border-slate-200,
  html.dark-mode .border-slate-200\/60,
  html.dark-mode .border-slate-200\/80 { border-color: #334155; }
  html.dark-mode .border-indigo-100,
  html.dark-mode .border-emerald-100,
  html.dark-mode .border-amber-100 { border-color: #46516a; }
  html.dark-mode .border-red-200 { border-color: #713344; }
  html.dark-mode .divide-slate-100 > :not([hidden]) ~ :not([hidden]) { border-color: #2d3a4f; }
  html.dark-mode table thead { background-color: #202c3d; }
  html.dark-mode .hover\:bg-slate-50:hover { background-color: #29364a; }
  html.dark-mode .hover\:bg-slate-100:hover { background-color: #29364a; }
  html.dark-mode .hover\:bg-indigo-100:hover { background-color: #45427c; }
  html.dark-mode input, html.dark-mode select, html.dark-mode textarea {
    background-color: #151e2e;
    border-color: #3b4b62;
    color: #e2e8f0;
  }
  html.dark-mode input::placeholder, html.dark-mode textarea::placeholder { color: #8798ae; }
  html.dark-mode option { background-color: #1e293b; color: #e2e8f0; }
  .surface-card { transition: background-color .25s ease, border-color .25s ease, box-shadow .25s ease; }
  .stat-card::before {
    content: "";
    position: absolute;
    inset: 0 auto 0 0;
    width: 3px;
    background: linear-gradient(180deg, #818cf8, #34d399);
    opacity: .75;
  }
  .interactive-card { transition: transform .18s ease, box-shadow .18s ease, filter .18s ease, background-color .18s ease; }
  .interactive-card:focus-visible { outline: 3px solid #a5b4fc; outline-offset: 3px; }
  @media (hover: hover) and (pointer: fine) {
    .interactive-card:hover { transform: translateY(-2px); box-shadow: 0 12px 24px rgb(15 23 42 / .18); filter: brightness(1.04); }
    html.dark-mode .interactive-card:hover { box-shadow: 0 12px 28px rgb(0 0 0 / .35), 0 0 0 1px rgb(165 180 252 / .12); }
    .chip:not(.bg-indigo-600):hover { background-color: #eef2ff; }
    html.dark-mode .chip:not(.bg-indigo-600):hover { background-color: #29364a; }
  }
  @media (prefers-reduced-motion: reduce) {
    html, body, .surface-card, .interactive-card { transition: none; }
  }
</style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex flex-col">

<header class="app-header sticky top-0 z-30 text-white">
  <div class="app-header-inner">
    <a class="app-brand" href="./" aria-label="<?php echo htmlspecialchars(APP_NAME); ?> — panel principal">
      <span class="app-brand-icon" aria-hidden="true">🏪</span>
      <span class="app-brand-copy">
        <span class="app-brand-name"><?php echo htmlspecialchars(APP_NAME); ?></span>
        <span class="app-brand-meta">
          <span id="reloj" class="app-clock"></span>
          <span id="statusBadge" class="app-status" role="status" aria-live="polite">
            <span id="statusDot" class="app-status-dot" aria-hidden="true"></span>
            <span id="statusText">En línea</span>
          </span>
        </span>
      </span>
    </a>
    <div class="app-header-actions">
      <button id="btnTheme" type="button" class="app-header-button" aria-label="Activar modo oscuro" aria-pressed="false" title="Activar modo oscuro">
        <span id="themeIcon" aria-hidden="true">🌙</span>
      </button>
      <button id="btnRefresh" type="button" class="app-header-button" aria-label="Actualizar datos" title="Actualizar datos">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M20 7v5h-5"></path><path d="M4 17v-5h5"></path>
          <path d="M5.6 9a7 7 0 0 1 11.55-2.6L20 12M4 12l2.85 5.6A7 7 0 0 0 18.4 15"></path>
        </svg>
        <span class="app-action-label">Actualizar</span>
      </button>
    </div>
  </div>
</header>

<main class="flex-1 max-w-[1400px] w-full mx-auto p-4 md:p-6">
  <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
      <div class="text-xs font-bold uppercase tracking-widest text-indigo-600">Vista general</div>
      <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-800">Panel de control</h1>
      <p class="mt-1 text-sm text-slate-500">Un resumen claro de la actividad de tu tienda.</p>
    </div>
    <span class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-500 shadow-sm">
      🏪 <?= htmlspecialchars(APP_NAME) ?>
    </span>
  </div>
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
    
    <section class="lg:col-span-8 space-y-6">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="surface-card stat-card bg-white rounded-2xl p-5 shadow-sm border border-slate-200/60 relative overflow-hidden">
          <div class="text-xs uppercase tracking-wider font-semibold text-slate-400">Ventas de hoy</div>
          <div id="vHoy" class="text-3xl font-extrabold text-emerald-600 mt-2">Bs. 0.00</div>
          <div id="vHoyN" class="text-xs text-slate-500 mt-1">0 venta(s) hoy</div>
          <div class="absolute right-3 bottom-3 text-4xl opacity-10">💰</div>
        </div>
        
        <div class="surface-card stat-card bg-white rounded-2xl p-5 shadow-sm border border-slate-200/60 relative overflow-hidden">
          <div class="text-xs uppercase tracking-wider font-semibold text-slate-400">Compras de hoy</div>
          <div id="cHoy" class="text-3xl font-extrabold text-amber-600 mt-2">Bs. 0.00</div>
          <div id="cHoyN" class="text-xs text-slate-500 mt-1">0 compra(s) hoy</div>
          <div class="absolute right-3 bottom-3 text-4xl opacity-10">🛒</div>
        </div>

        <div class="surface-card stat-card bg-white rounded-2xl p-5 shadow-sm border border-slate-200/60 relative overflow-hidden">
          <div class="text-xs uppercase tracking-wider font-semibold text-slate-400">Balance del día</div>
          <div id="balanceHoy" class="text-3xl font-extrabold text-indigo-600 mt-2">Bs. 0.00</div>
          <div class="text-xs text-slate-500 mt-1">Ingreso neto</div>
          <div class="absolute right-3 bottom-3 text-4xl opacity-10">📊</div>
        </div>
      </div>

      <section id="boxBajo" class="surface-card hidden bg-red-50 border border-red-200 rounded-2xl p-5 shadow-sm">
        <div class="font-bold text-red-700 mb-2 flex items-center gap-2"><span>⚠️</span> Alerta de stock bajo</div>
        <ul id="listBajo" class="space-y-1 text-sm"></ul>
      </section>

      <section class="surface-card bg-white rounded-2xl p-5 shadow-sm border border-slate-200/60">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-bold text-base text-slate-800">Últimas transacciones</h2>
          <button data-open="movimientos" class="text-xs text-indigo-600 font-semibold hover:underline">Ver todos los movimientos →</button>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 text-slate-500 uppercase text-xs tracking-wider">
              <tr>
                <th class="px-4 py-3 rounded-l-xl font-semibold">Tipo</th>
                <th class="px-4 py-3 font-semibold">Producto / Cantidad</th>
                <th class="px-4 py-3 font-semibold">Fecha y Hora</th>
                <th class="px-4 py-3 rounded-r-xl font-semibold text-right">Total</th>
              </tr>
            </thead>
            <tbody id="listUlt" class="divide-y divide-slate-100"></tbody>
          </table>
        </div>
      </section>
          <!-- NUEVO: Reportes Gerenciales (Ocupa todo el ancho) -->
    <section class="lg:col-span-8 space-y-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <!-- Top Productos -->
        <div class="surface-card bg-white rounded-2xl p-5 shadow-sm border border-slate-200/60">
          <div class="flex items-center justify-between mb-4">
            <h2 class="font-bold text-base text-slate-800">🏆 Top 5 Productos Más Vendidos</h2>
            <span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-1 rounded-full font-semibold">Histórico</span>
          </div>
          <ul id="listTop" class="space-y-2"></ul>
        </div>

        <!-- Gráfico de Ventas Semanales -->
        <div class="surface-card bg-white rounded-2xl p-5 shadow-sm border border-slate-200/60">
          <div class="flex items-center justify-between mb-4">
            <h2 class="font-bold text-base text-slate-800">📈 Ventas Últimos 7 Días</h2>
            <span class="text-xs bg-emerald-100 text-emerald-700 px-2 py-1 rounded-full font-semibold">Tendencia</span>
          </div>
          <div id="chartSemana" class="flex items-end justify-between h-32 gap-2 pt-2"></div>
        </div>

      </div>
    </section>
    </section>

    <aside class="lg:col-span-4 space-y-6">
      <section class="surface-card bg-white rounded-2xl p-5 shadow-sm border border-slate-200/60 space-y-3">
        <div class="text-xs uppercase tracking-wider font-semibold text-slate-400 mb-1">Acciones rápidas</div>
        <button data-open="venta" class="interactive-card w-full bg-emerald-500 active:bg-emerald-600 text-white rounded-xl py-4 px-4 font-bold shadow-md flex items-center justify-center gap-3 text-lg">
          <span class="text-2xl">💰</span> Registrar Venta
        </button>
        <button data-open="compra" class="interactive-card w-full bg-amber-500 active:bg-amber-600 text-white rounded-xl py-4 px-4 font-bold shadow-md flex items-center justify-center gap-3 text-lg">
          <span class="text-2xl">🛒</span> Registrar Compra
        </button>
      </section>

      <section class="surface-card bg-white rounded-2xl p-5 shadow-sm border border-slate-200/60">
        <div class="text-xs uppercase tracking-wider font-semibold text-slate-400 mb-3">Catálogos y Registros</div>
        <div class="grid grid-cols-2 gap-3">
          <button data-open="productos" class="interactive-card bg-slate-50 hover:bg-slate-100 border border-slate-200/80 rounded-xl p-4 text-center font-medium transition">
            <div class="text-2xl mb-1">📦</div>Productos
          </button>
          <button data-open="clientes" class="interactive-card bg-slate-50 hover:bg-slate-100 border border-slate-200/80 rounded-xl p-4 text-center font-medium transition">
            <div class="text-2xl mb-1">👥</div>Clientes
          </button>
          <button data-open="proveedores" class="interactive-card bg-slate-50 hover:bg-slate-100 border border-slate-200/80 rounded-xl p-4 text-center font-medium transition">
            <div class="text-2xl mb-1">🚚</div>Proveedores
          </button>
          <button data-open="movimientos" class="interactive-card bg-indigo-50 hover:bg-indigo-100 border border-indigo-100 text-indigo-700 rounded-xl p-4 text-center font-semibold transition">
            <div class="text-2xl mb-1">📋</div>Movimientos
          </button>
        </div>
      </section>
    </aside>

  </div>
</main>

<div id="overlay" class="hidden fixed inset-0 z-40 bg-black/50 flex items-center sm:items-center justify-center p-0 sm:p-4">
  <div id="sheet" class="surface-card sheet-enter bg-white w-full max-w-lg rounded-t-3xl sm:rounded-3xl max-h-[92vh] flex flex-col shadow-2xl overflow-hidden">
    <div class="flex items-center justify-between px-6 pt-5 pb-3 border-b border-slate-100">
      <h2 id="sheetTitle" class="text-lg font-bold text-slate-800"></h2>
      <button id="btnClose" class="w-9 h-9 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center font-bold text-lg active:bg-slate-200 transition">✕</button>
    </div>
    <div id="sheetBody" class="px-6 py-4 pb-safe overflow-y-auto flex-1"></div>
  </div>
</div>

<div id="toast" class="hidden fixed left-1/2 -translate-x-1/2 bottom-6 z-50 px-5 py-3 rounded-xl text-white text-sm shadow-xl max-w-[90vw] transition-all"></div>

<script>
const CURRENCY = '<?php echo addslashes(CURRENCY); ?>';
let CSRF = '<?php echo addslashes($_SESSION['csrf'] ?? ''); ?>';
let DB = { clientes: [], proveedores: [], productos: [], resumen: null };

const $ = s => document.querySelector(s);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const money = n => CURRENCY + Number(n || 0).toFixed(2);
const inputCls = 'w-full border border-slate-300 rounded-xl px-4 py-3 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-400';
const btnCls = 'w-full text-white font-bold text-lg rounded-2xl py-4 active:scale-95 transition disabled:opacity-50';

function setTheme(isDark, persist = false) {
  darkModeEnabled = isDark;
  document.documentElement.classList.toggle('dark-mode', isDark);
  const button = $('#btnTheme');
  $('#themeIcon').textContent = isDark ? '☀️' : '🌙';
  button.setAttribute('aria-label', isDark ? 'Activar modo claro' : 'Activar modo oscuro');
  button.setAttribute('aria-pressed', String(isDark));
  button.title = isDark ? 'Activar modo claro' : 'Activar modo oscuro';
  document.querySelector('meta[name="theme-color"]').content = isDark ? '#0f172a' : '#4f46e5';
  if (persist) {
    try { localStorage.setItem('theme', isDark ? 'dark' : 'light'); }
    catch (error) { console.warn('No se pudo guardar la preferencia de tema.', error); }
  }
}
setTheme(darkModeEnabled);
$('#btnTheme').onclick = () => setTheme(!darkModeEnabled, true);

function toast(msg, ok = true) {
  const t = $('#toast');
  t.textContent = msg;
  t.className = 'fixed left-1/2 -translate-x-1/2 bottom-6 z-50 px-5 py-3 rounded-xl text-white text-sm shadow-xl max-w-[90vw] ' + (ok ? 'bg-emerald-600' : 'bg-red-600');
  clearTimeout(toast.t);
  toast.t = setTimeout(() => t.classList.add('hidden'), 3500);
}

function tickClock() {
  $('#reloj').textContent = new Date().toLocaleString('es-BO', { dateStyle: 'medium', timeStyle: 'short' });
}
tickClock(); setInterval(tickClock, 30000);

// ── 1. Gestor de Cola Offline (Sync Queue) ────────────────────────────────────
function getQueue() {
  const queue = JSON.parse(localStorage.getItem('sync_queue') || '[]');
  if (!Array.isArray(queue)) throw new Error('El formato de la cola de sincronización no es válido.');
  return queue;
}
function saveQueue(queue) { localStorage.setItem('sync_queue', JSON.stringify(queue)); }

// Solo se eliminan de la cola los registros confirmados por la API, conservando el orden de envío.
// Esta función se ejecuta automáticamente cuando vuelve el internet
window.addEventListener('online', async () => {
  let queue;
  try {
    queue = getQueue();
  } catch (error) {
    toast('No se pudo leer la cola de sincronización local.', false);
    console.error('No se pudo leer la cola de sincronización local.', error);
    return;
  }
  if (queue.length === 0) return;
  
  toast(`🌐 Conexión restaurada. Sincronizando ${queue.length} registro(s)...`, true);
  
  let successCount = 0;
  // Procesamos la cola uno por uno
  for (let i = 0; i < queue.length; i++) {
    const item = queue[i];
    try {
      // Llamamos a la API (ahora sí hay internet)
      await api(item.action, item.data);
      successCount++;
    } catch (e) {
      toast('❌ Error al sincronizar un registro. Se reintentará.', false);
      break; // Si falla uno, paramos para no perder el orden
    }
  }
  
  // Actualizamos la cola (quitamos los que se enviaron bien)
  const newQueue = queue.slice(successCount);
  saveQueue(newQueue);
  
  if (successCount > 0) {
    toast(`✅ ${successCount} registro(s) sincronizado(s) con la base de datos`, true);
    api('state').catch(e => toast(e.message, false));
  }
});

// ── 2. Llamadas al backend (Con manejo Offline) ───────────────────────────────
async function api(action, data = null) {
  if (!navigator.onLine) {
    if (data) throw new Error('OFFLINE_QUEUE');
    throw new Error('⚠️ Sin conexión a internet.');
  }

  try {
    const headers = { 
      'Content-Type': 'application/json',
      'X-CSRF-Token': CSRF
    };
    
    const res = await fetch('api.php?action=' + action, {
      method: data ? 'POST' : 'GET',
      credentials: 'same-origin',
      headers: headers,
      body: data ? JSON.stringify(data) : undefined,  // ← ESTA LÍNEA ES CRÍTICA
    });

    if (!res.ok) {
      if (res.status === 502 || res.status === 504) {
        throw new Error('⏳ El servidor está despertando. Intenta de nuevo en 10 segundos.');
      }
      throw new Error('Error del servidor (Código: ' + res.status + ')');
    }

    const json = await res.json().catch(() => ({ ok: false, error: 'Respuesta inválida' }));
    if (!json.ok) throw new Error(json.error || 'Error desconocido');
    
    CSRF = json.csrf || CSRF;
    if (json.resumen) { DB = json; renderSummary(); }
    return json;
  } catch (e) {
    if (e.message === 'Failed to fetch') throw new Error('⚠️ No se pudo conectar.');
    throw e;
  }
}

// ── 3. Envío de Formularios (Con Cola Offline) ────────────────────────────────
async function submitForm(form, action, onOk) {
  if (!form.checkValidity()) { form.reportValidity(); return; }

  const btn = form.querySelector('button[type=submit]');
  btn.disabled = true;
  const oldText = btn.innerHTML;
  btn.innerHTML = '<span class="animate-pulse">Procesando...</span>';
  
  try {
    const formData = Object.fromEntries(new FormData(form));
    
    // Validaciones lógicas
    if (formData.cantidad && parseInt(formData.cantidad) <= 0) throw new Error('La cantidad debe ser mayor a 0.');
    if (formData.precio_venta && parseFloat(formData.precio_venta) < 0) throw new Error('El precio no puede ser negativo.');

    await api(action, formData);
    
    toast('✅ Guardado exitosamente en la base de datos', true);
    form.reset(); 
    if (onOk) onOk(); else closeSheet();
    
  } catch (e) { 
    // AQUÍ ESTÁ LA MAGIA: Si el error es la señal OFFLINE_QUEUE, lo guardamos localmente
    if (e.message === 'OFFLINE_QUEUE') {
      try {
        const queue = getQueue();
        queue.push({ action, data: Object.fromEntries(new FormData(form)) });
        saveQueue(queue);

        toast('💾 Sin internet. Guardado localmente (se sincronizará al reconectar)', true);
        form.reset();
        closeSheet();
      } catch (queueError) {
        toast('No se pudo guardar sin conexión: ' + queueError.message, false);
        console.error('No se pudo guardar en la cola de sincronización.', queueError);
      }
    } else {
      toast(e.message, false); // Error normal (ej: validación de backend)
    }
  } finally {
    btn.disabled = false;
    btn.innerHTML = oldText;
  }
}
// ── Resumen y Dashboard ────────────────────────────────────────────────
function renderSummary() {
  const r = DB.resumen; 
  if (!r) return; // Si no hay datos, no hacemos nada

  // 1. Estadísticas del día (Ventas, Compras, Balance)
  $('#vHoy').textContent = money(r.ventas_hoy_total || 0);
  $('#vHoyN').textContent = (r.ventas_hoy_count || 0) + ' venta(s) hoy';
  
  $('#cHoy').textContent = money(r.compras_hoy_total || 0);
  $('#cHoyN').textContent = (r.compras_hoy_count || 0) + ' compra(s) hoy';
  
  const balance = (r.ventas_hoy_total || 0) - (r.compras_hoy_total || 0);
  $('#balanceHoy').textContent = money(balance);
  $('#balanceHoy').className = 'text-3xl font-extrabold mt-2 ' + (balance >= 0 ? 'text-emerald-600' : 'text-red-600');

  // Los datos recibidos de la API se escapan antes de insertarlos como HTML.
  // 2. Alerta de Stock Bajo
  $('#boxBajo').classList.toggle('hidden', r.stock_bajo.length === 0);
  $('#listBajo').innerHTML = r.stock_bajo.map(p =>
    `<li class="flex justify-between"><span>${esc(p.nombre)}</span>
     <b class="${p.stock_actual === 0 ? 'text-red-700' : 'text-amber-600'}">${p.stock_actual} u.</b></li>`
  ).join('');

  // 3. Últimas Transacciones (Tabla)
  $('#listUlt').innerHTML = r.ultimas.length ? r.ultimas.map(t => 
    `<tr class="hover:bg-slate-50/50 transition">
      <td class="px-4 py-3 whitespace-nowrap">
        <span class="inline-flex items-center gap-1.5 font-semibold ${t.tipo === 'venta' ? 'text-emerald-600' : 'text-amber-600'}">
          <span>${t.tipo === 'venta' ? '💰' : '🛒'}</span> ${t.tipo === 'venta' ? 'Venta' : 'Compra'}
        </span>
      </td>
      <td class="px-4 py-3">
        <div class="font-medium text-slate-800">${esc(t.producto)}</div>
        <div class="text-xs text-slate-400">Cant: ${t.cantidad}</div>
      </td>
      <td class="px-4 py-3 whitespace-nowrap text-slate-500 text-xs">${esc(t.fecha_hora)}</td>
      <td class="px-4 py-3 whitespace-nowrap text-right font-bold ${t.tipo === 'venta' ? 'text-emerald-600' : 'text-amber-600'}">
        ${t.tipo === 'venta' ? '+' : '−'}${money(t.total)}
      </td>
    </tr>`
  ).join('') : '<tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Aún no hay transacciones registradas.</td></tr>';

  // 4. Renderizar Top Productos (NUEVO)
  const topList = DB.top_productos || [];
  $('#listTop').innerHTML = topList.length ? topList.map((p, i) => `
    <li class="flex justify-between items-center bg-slate-50 p-3 rounded-xl border border-slate-100">
      <span class="flex items-center gap-3">
        <span class="font-black text-indigo-600 text-lg w-6">#${i+1}</span> 
        <span class="font-medium truncate">${esc(p.nombre)}</span>
      </span>
      <div class="text-right">
        <b class="text-emerald-600 block">${p.total_vendido} u.</b>
        <span class="text-[10px] text-slate-400">${money(p.ingresos_generados)}</span>
      </div>
    </li>
  `).join('') : '<li class="text-slate-400 text-center py-2">Aún no hay ventas registradas.</li>';

  // 5. Renderizar Gráfico de Barras (NUEVO)
  const semana = DB.ventas_semana || [];
  const maxVal = Math.max(...semana.map(d => parseFloat(d.total_dia) || 0), 1); // Evitar división por cero
  
  $('#chartSemana').innerHTML = semana.length ? semana.map(d => {
    const heightPercent = (parseFloat(d.total_dia) / maxVal) * 100;
    const fechaCorta = d.dia ? d.dia.substring(5) : ''; 
    return `
      <div class="flex-1 flex flex-col items-center justify-end h-full group relative">
        <div class="absolute -top-8 bg-slate-800 text-white text-[10px] px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition whitespace-nowrap z-10">
          ${money(d.total_dia)}
        </div>
        <div class="w-full bg-gradient-to-t from-indigo-600 to-indigo-400 rounded-t-md transition-all duration-500 hover:from-indigo-700 hover:to-indigo-500" style="height: ${heightPercent}%"></div>
        <div class="text-[10px] text-slate-400 mt-1 font-mono">${fechaCorta}</div>
      </div>`;
  }).join('') : '<div class="w-full text-center text-slate-400 text-xs py-4">Sin datos esta semana</div>';
}

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

const label = t => '<label class="block text-sm font-medium text-slate-600 mb-1 mt-3">' + t + '</label>';
const options = (rows, fn, ph) => '<option value="">' + ph + '</option>' + rows.map(fn).join('');

function viewVenta() {
  if (!DB.clientes.length) return toast('Primero registra un cliente.', false);
  if (!DB.productos.length) return toast('Primero registra un producto.', false);
  openSheet('Registrar Venta', 
    '<form id="f" class="pb-2">' +
      label('Cliente') +
      '<select name="cliente_id" required class="' + inputCls + '">' + options(DB.clientes, c => '<option value="' + c.id + '">' + esc(c.nombre) + '</option>', 'Selecciona cliente…') + '</select>' +
      label('Producto') +
      '<select name="producto_id" id="selProd" required class="' + inputCls + '">' + options(DB.productos, p => '<option value="' + p.id + '" ' + (p.stock_actual <= 0 ? 'disabled' : '') + '>' + esc(p.nombre) + ' — ' + money(p.precio_venta) + ' (stock ' + p.stock_actual + ')</option>', 'Selecciona producto…') + '</select>' +
      label('Cantidad') +
      '<input name="cantidad" id="qty" type="number" inputmode="numeric" min="1" step="1" value="1" required class="' + inputCls + '">' +
      '<div class="mt-5 mb-4 bg-emerald-50 rounded-2xl p-4 flex justify-between items-center">' +
        '<span class="text-slate-600 font-medium">Total a cobrar</span>' +
        '<b id="total" class="text-3xl text-emerald-600">' + money(0) + '</b>' +
      '</div>' +
      '<button type="submit" class="' + btnCls + ' bg-emerald-500 hover:bg-emerald-600">Confirmar venta</button>' +
    '</form>');
  const calc = () => {
    const p = DB.productos.find(x => x.id == $('#selProd').value);
    $('#total').textContent = money(p ? p.precio_venta * (parseInt($('#qty').value) || 0) : 0);
  };
  $('#selProd').onchange = calc; $('#qty').oninput = calc;
  $('#f').onsubmit = e => { e.preventDefault(); submitForm(e.target, 'venta', closeSheet); };
}

function viewCompra() {
  if (!DB.proveedores.length) return toast('Primero registra un proveedor.', false);
  if (!DB.productos.length) return toast('Primero registra un producto.', false);
  openSheet('Registrar Compra', 
    '<form id="f" class="pb-2">' +
      label('Proveedor') +
      '<select name="proveedor_id" required class="' + inputCls + '">' + options(DB.proveedores, p => '<option value="' + p.id + '">' + esc(p.nombre) + '</option>', 'Selecciona proveedor…') + '</select>' +
      label('Producto') +
      '<select name="producto_id" id="selProd" required class="' + inputCls + '">' + options(DB.productos, p => '<option value="' + p.id + '">' + esc(p.nombre) + ' (stock ' + p.stock_actual + ')</option>', 'Selecciona producto…') + '</select>' +
      label('Cantidad') +
      '<input name="cantidad" id="qty" type="number" inputmode="numeric" min="1" step="1" value="1" required class="' + inputCls + '">' +
      label('Costo unitario') +
      '<input name="costo_unitario" id="cost" type="number" inputmode="decimal" min="0" step="0.01" required class="' + inputCls + '">' +
      '<div class="mt-5 mb-4 bg-amber-50 rounded-2xl p-4 flex justify-between items-center">' +
        '<span class="text-slate-600 font-medium">Total a pagar</span>' +
        '<b id="total" class="text-3xl text-amber-600">' + money(0) + '</b>' +
      '</div>' +
      '<button type="submit" class="' + btnCls + ' bg-amber-500 hover:bg-amber-600">Confirmar compra</button>' +
    '</form>');
  const calc = () => $('#total').textContent = money((parseFloat($('#cost').value) || 0) * (parseInt($('#qty').value) || 0));
  $('#selProd').onchange = () => {
    const p = DB.productos.find(x => x.id == $('#selProd').value);
    if (p) $('#cost').value = p.costo_compra;
    calc();
  };
  $('#qty').oninput = calc; $('#cost').oninput = calc;
  $('#f').onsubmit = e => { e.preventDefault(); submitForm(e.target, 'compra', closeSheet); };
}

function viewProductos() {
  const list = DB.productos.map(p => 
    '<li class="py-3 flex justify-between gap-3">' +
      '<div class="min-w-0"><div class="font-medium truncate">' + esc(p.nombre) + '</div>' +
      '<div class="text-xs text-slate-500 truncate">' + esc(p.descripcion) + '</div></div>' +
      '<div class="text-right shrink-0"><div class="font-semibold">' + money(p.precio_venta) + '</div>' +
      '<div class="text-xs ' + (p.stock_actual <= DB.resumen.limite_stock ? 'text-red-600 font-bold' : 'text-slate-500') + '">stock ' + p.stock_actual + '</div></div>' +
    '</li>').join('') || '<li class="py-3 text-slate-400">Sin productos.</li>';
  
  openSheet('Catálogo de Productos', 
    '<details class="mb-3 bg-indigo-50 rounded-2xl p-3 border border-indigo-100" ' + (DB.productos.length ? '' : 'open') + '>' +
      '<summary class="font-semibold text-indigo-700 cursor-pointer">＋ Nuevo producto</summary>' +
      '<form id="f" class="mt-2">' +
        label('Nombre') + '<input name="nombre" required class="' + inputCls + '">' +
        label('Descripción') + '<input name="descripcion" class="' + inputCls + '">' +
        '<div class="grid grid-cols-2 gap-3">' +
          '<div>' + label('Precio venta') + '<input name="precio_venta" type="number" inputmode="decimal" step="0.01" min="0" required class="' + inputCls + '"></div>' +
          '<div>' + label('Costo compra') + '<input name="costo_compra" type="number" inputmode="decimal" step="0.01" min="0" required class="' + inputCls + '"></div>' +
        '</div>' +
        label('Stock inicial') + '<input name="stock_actual" type="number" inputmode="numeric" min="0" value="0" class="' + inputCls + '">' +
        label('Proveedor (opcional)') + '<select name="proveedor_id" class="' + inputCls + '">' + options(DB.proveedores, p => '<option value="' + p.id + '">' + esc(p.nombre) + '</option>', '— Ninguno —') + '</select>' +
        '<button type="submit" class="' + btnCls + ' bg-indigo-600 hover:bg-indigo-700 mt-4">Guardar producto</button>' +
      '</form>' +
    '</details>' +
    '<ul class="divide-y divide-slate-100 pb-2">' + list + '</ul>');
  $('#f').onsubmit = e => { e.preventDefault(); submitForm(e.target, 'add_producto', viewProductos); };
}

function viewClientes() {
  const list = DB.clientes.map(c => 
    '<li class="py-3"><div class="font-medium">' + esc(c.nombre) + '</div>' +
    '<div class="text-xs text-slate-500">' + esc(c.telefono) + (c.email ? ' · ' + esc(c.email) : '') + '</div></li>').join('')
    || '<li class="py-3 text-slate-400">Sin clientes.</li>';
  
  openSheet('Directorio de Clientes', 
    '<details class="mb-3 bg-indigo-50 rounded-2xl p-3 border border-indigo-100" ' + (DB.clientes.length ? '' : 'open') + '>' +
      '<summary class="font-semibold text-indigo-700 cursor-pointer">＋ Nuevo cliente</summary>' +
      '<form id="f" class="mt-2">' +
        label('Nombre') + '<input name="nombre" required class="' + inputCls + '">' +
        label('Teléfono') + '<input name="telefono" type="tel" class="' + inputCls + '">' +
        label('Email') + '<input name="email" type="email" class="' + inputCls + '">' +
        '<button type="submit" class="' + btnCls + ' bg-indigo-600 hover:bg-indigo-700 mt-4">Guardar cliente</button>' +
      '</form>' +
    '</details>' +
    '<ul class="divide-y divide-slate-100 pb-2">' + list + '</ul>');
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
async function viewMovimientos() {
  openSheet('Historial de Movimientos', '<div class="py-10 text-center text-slate-400">Cargando…</div>');
  let rows, limite;
  try {
    const r = await api('movimientos');
    rows = r.movimientos; limite = r.limite;
  } catch (e) { closeSheet(); return toast(e.message, false); }

  let filtro = 'todos';
  const chip = (id, txt) => '<button data-f="' + id + '" class="chip px-4 py-2 rounded-full text-sm font-medium border transition">' + txt + '</button>';

  const item = m => {
    const venta = m.tipo === 'venta';
    const quien = venta
      ? '👤 ' + esc(m.cliente) + (m.proveedor ? ' · 🚚 ' + esc(m.proveedor) : '')
      : '🚚 ' + esc(m.proveedor);
    return '<tr class="hover:bg-slate-50/50">' +
        '<td class="px-3 py-3 whitespace-nowrap text-slate-500">' + esc(m.fecha_hora) + '</td>' +
        '<td class="px-3 py-3 whitespace-nowrap">' +
          '<span class="font-semibold ' + (venta ? 'text-emerald-600' : 'text-amber-600') + '">' + (venta ? 'Venta' : 'Compra') + '</span>' +
          '<div class="text-xs text-slate-500">' + esc(m.producto) + ' × ' + m.cantidad + '</div>' +
        '</td>' +
        '<td class="px-3 py-3 text-slate-700">' + quien + '</td>' +
        '<td class="px-3 py-3 whitespace-nowrap font-bold ' + (venta ? 'text-emerald-600' : 'text-amber-600') + '">' + (venta ? '+' : '−') + money(m.total) + '</td>' +
        '<td class="px-3 py-3"><span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs text-emerald-700 font-medium">Registrado</span></td>' +
      '</tr>';
  };

  const draw = () => {
    const vis = rows.filter(m => filtro === 'todos' || m.tipo === filtro);
    document.querySelectorAll('#movChips .chip').forEach(b => {
      const on = b.dataset.f === filtro;
      b.className = 'chip px-4 py-2 rounded-full text-sm font-medium border transition ' +
        (on ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50');
    });
    $('#movBody').innerHTML = vis.length
      ? vis.map(item).join('')
      : '<tr><td colspan="5" class="px-3 py-10 text-center text-slate-400">No hay movimientos registrados.</td></tr>';
    $('#movCount').textContent = 'Mostrando ' + vis.length + ' de ' + rows.length + ' (máx. ' + limite + ', los más recientes)';
  };

  const suma = t => rows.filter(m => m.tipo === t).reduce((a, m) => a + m.total, 0);
  openSheet('Historial de Movimientos', 
    '<div class="grid grid-cols-2 gap-3 mb-4">' +
      '<div class="bg-emerald-50 rounded-2xl p-4 border border-emerald-100"><div class="text-xs text-slate-500 font-medium">Total Ventas</div><b class="text-xl text-emerald-600">' + money(suma('venta')) + '</b></div>' +
      '<div class="bg-amber-50 rounded-2xl p-4 border border-amber-100"><div class="text-xs text-slate-500 font-medium">Total Compras</div><b class="text-xl text-amber-600">' + money(suma('compra')) + '</b></div>' +
    '</div>' +
    '<div id="movChips" class="flex gap-2 mb-3">' + chip('todos', 'Todos') + chip('venta', '💰 Ventas') + chip('compra', '🛒 Compras') + '</div>' +
    '<div id="movCount" class="text-xs text-slate-400 mb-2"></div>' +
    '<div class="overflow-x-auto rounded-2xl border border-slate-200">' +
      '<table class="w-full min-w-[620px] text-left text-xs">' +
        '<thead class="bg-slate-100 text-slate-500 uppercase tracking-wider">' +
          '<tr>' +
            '<th class="px-3 py-3 font-semibold">Hora</th>' +
            '<th class="px-3 py-3 font-semibold">Tipo</th>' +
            '<th class="px-4 py-3 font-semibold">Cliente/Proveedor</th>' +
            '<th class="px-3 py-3 font-semibold">Total</th>' +
            '<th class="px-3 py-3 font-semibold">Estado</th>' +
          '</tr>' +
        '</thead>' +
        '<tbody id="movBody" class="divide-y divide-slate-100 text-sm"></tbody>' +
      '</table>' +
    '</div>');
  document.querySelectorAll('#movChips .chip').forEach(b => b.onclick = () => { filtro = b.dataset.f; draw(); });
  draw();
}

const VIEWS = { venta: viewVenta, compra: viewCompra, productos: viewProductos, clientes: viewClientes, proveedores: viewProveedores, movimientos: viewMovimientos };
document.querySelectorAll('[data-open]').forEach(b => b.onclick = () => VIEWS[b.dataset.open]());
api('state').catch(e => toast(e.message, false));
// ── Indicador de Estado de Conexión (UX) ─────────────────────────────────
function updateConnectionStatus() {
  const isOnline = navigator.onLine;
  const badge = $('#statusBadge');
  const dot = $('#statusDot');
  const text = $('#statusText');

  badge.classList.toggle('is-offline', !isOnline);
  dot.classList.toggle('is-offline', !isOnline);
  text.textContent = isOnline ? 'En línea' : 'Sin conexión (Guardado local)';
}

// Escuchar eventos de red del navegador
window.addEventListener('online', updateConnectionStatus);
window.addEventListener('offline', updateConnectionStatus);

// Ejecutar al cargar la página por primera vez
updateConnectionStatus();
</script>
</body>
</html>