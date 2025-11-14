/* ========= Notificaciones (toast) ========= */
/**
 * Muestra un toast flotante.
 * @param {string} mensaje
 * @param {'info'|'ok'|'warn'|'err'} [tipo='info']
 * @param {number} [duracion=3000]
 */
function toast(mensaje, tipo = 'info', duracion = 3000) {
  let t = document.querySelector('.toast');
  if (!t) {
    t = document.createElement('div');
    t.className = 'toast';
    document.body.appendChild(t);
  }
  t.className = 'toast ' + (tipo || 'info');
  t.textContent = mensaje;
  void t.offsetWidth;
  t.classList.add('show');
  clearTimeout(t._timeoutId);
  t._timeoutId = setTimeout(() => t.classList.remove('show'), duracion);
}

/* ========= Utilidades de autenticación ========= */
function obtenerToken() { return localStorage.getItem('token') || ''; }
function obtenerRol() { return localStorage.getItem('rol') || 'comprador'; }
function establecerRol(rol) { if (rol) localStorage.setItem('rol', rol); }
function guardarToken(token) { if (token) localStorage.setItem('token', token); }
function limpiarSesion() { localStorage.removeItem('token'); localStorage.removeItem('rol'); }

function headersAutenticados(extra = {}) {
  const token = obtenerToken();
  return Object.assign(
    { 'Content-Type': 'application/json' },
    token ? { 'Authorization': 'Bearer ' + token } : {},
    extra
  );
}

function configurarSesionUI(idBadge = 'rolUsuario', idBotonSalir = 'btnSalir', redirect = '/abarrotes/publico/') {
  const badge = document.getElementById(idBadge);
  const btn = document.getElementById(idBotonSalir);
  const rol = obtenerRol();
  if (badge && rol) badge.textContent = rol;
  if (btn) {
    if (rol) btn.style.display = 'inline-block';
    btn.onclick = () => { limpiarSesion(); location.href = redirect; };
  }
}

/* ========= Helpers ========= */
function datosFormulario(form) {
  return Object.fromEntries(new FormData(form).entries());
}
function qs(obj = {}) {
  const s = new URLSearchParams(obj).toString();
  return s ? '?' + s : '';
}

/* ====== NAV: dropdowns (solo desktop) ====== */
function initDropdowns(root = document) {
  // Abre/cierra por click; cierra al hacer click fuera o con ESC.
  root.querySelectorAll('.dropdown > .dropbtn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const dd = btn.closest('.dropdown');
      const isOpen = dd.classList.contains('open');
      root.querySelectorAll('.dropdown.open').forEach(x => x.classList.remove('open'));
      if (!isOpen) dd.classList.add('open');
    });
  });
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.dropdown')) root.querySelectorAll('.dropdown.open').forEach(x => x.classList.remove('open'));
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') root.querySelectorAll('.dropdown.open').forEach(x => x.classList.remove('open'));
  });
}

/* ====== MODALES ====== */
function crearModal({ title = 'Aviso', html = '', actions = [{ label: 'Cerrar' }] } = {}) {
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay';
  overlay.innerHTML = `
    <div class="modal" role="dialog" aria-modal="true" aria-label="${title}">
      <h3>${title}</h3>
      <div class="modal-body">${html}</div>
      <div class="modal-actions"></div>
    </div>`;
  const actWrap = overlay.querySelector('.modal-actions');

  actions.forEach(a => {
    const b = document.createElement('button');
    b.textContent = a.label || 'OK';
    if (a.className) b.className = a.className;
    b.addEventListener('click', () => {
      if (typeof a.onClick === 'function') a.onClick();
      document.body.removeChild(overlay);
    });
    actWrap.appendChild(b);
  });

  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) document.body.removeChild(overlay);
  });
  document.body.appendChild(overlay);
  requestAnimationFrame(() => overlay.classList.add('show'));
  return overlay;
}

function alertModal(titulo, mensaje) {
  crearModal({ title: titulo, html: `<p>${mensaje}</p>`, actions: [{ label: 'Cerrar', className: 'secondary' }] });
}
function confirmModal(titulo, mensaje) {
  return new Promise(resolve => {
    crearModal({
      title: titulo,
      html: `<p>${mensaje}</p>`,
      actions: [
        { label: 'Cancelar', className: 'secondary', onClick: () => resolve(false) },
        { label: 'Confirmar', onClick: () => resolve(true) }
      ]
    });
  });
}

/* ========= PERFIL Y BARRA ========= */
async function obtenerPerfilServidor() {
  const token = obtenerToken();
  if (!token) return null;
  try {
    const r = await fetch('/abarrotes/api/usuarios/perfil', {
      headers: { 'Authorization': 'Bearer ' + token }
    });
    const j = await r.json();
    if (r.ok && j && j.usuario) {
      localStorage.setItem('perfil_nombre', j.usuario.nombre || '');
      establecerRol(j.usuario.rol || 'comprador');
      return j.usuario;
    }
  } catch {}
  return null;
}

async function mostrarUsuarioEnBarra(idBadge = 'rolUsuario') {
  const badge = document.getElementById(idBadge);
  if (!badge) return;
  const nombre = localStorage.getItem('perfil_nombre') || '';
  const rol = obtenerRol();
  if (nombre || rol) badge.textContent = (nombre ? `${nombre} (${rol})` : rol);
  if (!nombre && obtenerToken()) {
    const u = await obtenerPerfilServidor();
    if (u) badge.textContent = `${u.nombre || ''} (${u.rol || 'comprador'})`;
  }
}

/* ========= INACTIVIDAD ========= */
function marcarActividad() {
  localStorage.setItem('last_activity_ms', String(Date.now()));
}

function iniciarTemporizadorInactividad(timeoutMs = 60000, redirectLogin = '/abarrotes/publico/usuarios/inicioSesion.html') {
  marcarActividad();
  // Desktop: eventos comunes (se eliminó touchstart)
  ['click','mousemove','keydown','scroll'].forEach(evt => {
    window.addEventListener(evt, marcarActividad, { passive: true });
  });

  if (window._inactividadTimer) clearInterval(window._inactividadTimer);
  window._inactividadTimer = setInterval(() => {
    const last = Number(localStorage.getItem('last_activity_ms') || '0');
    if (!obtenerToken()) return;
    if (Date.now() - last > timeoutMs) {
      limpiarSesion();
      toast?.('Sesión expirada por inactividad', 'warn');
      location.href = redirectLogin;
    }
  }, 2000);
}

function protegerPagina({ redirectLogin = '/abarrotes/publico/usuarios/inicioSesion.html', timeoutMs = 60000 } = {}) {
  if (!obtenerToken()) {
    location.href = redirectLogin;
    return false;
  }
  iniciarTemporizadorInactividad(timeoutMs, redirectLogin);
  return true;
}

/* ========= Exponer en window ========= */
window.toast = toast;
window.obtenerToken = obtenerToken;
window.obtenerRol = obtenerRol;
window.establecerRol = establecerRol;
window.guardarToken = guardarToken;
window.limpiarSesion = limpiarSesion;
window.headersAutenticados = headersAutenticados;
window.configurarSesionUI = configurarSesionUI;
window.datosFormulario = datosFormulario;
window.qs = qs;
window.initDropdowns = initDropdowns;
window.alertModal = alertModal;
window.confirmModal = confirmModal;
window.obtenerPerfilServidor = obtenerPerfilServidor;
window.mostrarUsuarioEnBarra = mostrarUsuarioEnBarra;
window.iniciarTemporizadorInactividad = iniciarTemporizadorInactividad;
window.protegerPagina = protegerPagina;
window.marcarActividad = marcarActividad;
