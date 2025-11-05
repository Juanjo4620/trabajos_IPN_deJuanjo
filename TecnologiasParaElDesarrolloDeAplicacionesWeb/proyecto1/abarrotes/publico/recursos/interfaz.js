/* ========= Notificaciones (toast) ========= */
/**
 * Muestra un toast flotante.
 * @param {string} mensaje - Texto a mostrar.
 * @param {'info'|'ok'|'warn'|'err'} [tipo='info'] - Estilo del toast.
 * @param {number} [duracion=3000] - Duración en ms.
 */
function toast(mensaje, tipo = 'info', duracion = 3000) {
  let t = document.querySelector('.toast');
  if (!t) {
    t = document.createElement('div');
    t.className = 'toast';
    document.body.appendChild(t);
  }

  // Reinicia estado/clases y aplica tipo
  t.className = 'toast ' + (tipo || 'info');
  t.textContent = mensaje;

  // Reinicia animación/temporizador si ya había un toast
  void t.offsetWidth; // truco para reiniciar transición
  t.classList.add('show');

  clearTimeout(t._timeoutId);
  t._timeoutId = setTimeout(() => {
    t.classList.remove('show');
  }, duracion);
}

/* ========= Utilidades de autenticación (opcionales) ========= */

function obtenerToken() {
  return localStorage.getItem('token') || '';
}

function obtenerRol() {
  return localStorage.getItem('rol') || 'comprador';
}

function establecerRol(rol) {
  if (rol) localStorage.setItem('rol', rol);
}

function guardarToken(token) {
  if (token) localStorage.setItem('token', token);
}

function limpiarSesion() {
  localStorage.removeItem('token');
  localStorage.removeItem('rol');
}

/**
 * Devuelve headers comunes para JSON + Authorization si hay token.
 * @param {object} extra - Headers extra a combinar.
 */
function headersAutenticados(extra = {}) {
  const token = obtenerToken();
  return Object.assign(
    { 'Content-Type': 'application/json' },
    token ? { 'Authorization': 'Bearer ' + token } : {},
    extra
  );
}

/**
 * Configura la UI de sesión: muestra el rol y enlaza el botón de salir.
 * @param {string} [idBadge='rolUsuario'] - id del span donde mostrar el rol.
 * @param {string} [idBotonSalir='btnSalir'] - id del botón de salir.
 * @param {string} [redirect='/abarrotes/publico/'] - URL a la que redirigir al salir.
 */
function configurarSesionUI(idBadge = 'rolUsuario', idBotonSalir = 'btnSalir', redirect = '/abarrotes/publico/') {
  const badge = document.getElementById(idBadge);
  const btn = document.getElementById(idBotonSalir);
  const rol = obtenerRol();

  if (badge && rol) badge.textContent = rol;
  if (btn) {
    if (rol) btn.style.display = 'inline-block';
    btn.onclick = () => {
      limpiarSesion();
      location.href = redirect;
    };
  }
}

/* ========= Helpers varios (opcionales) ========= */

/**
 * Convierte un formulario en objeto plano.
 * @param {HTMLFormElement} form
 * @returns {object}
 */
function datosFormulario(form) {
  return Object.fromEntries(new FormData(form).entries());
}

/**
 * Construye query string desde un objeto.
 * @param {object} obj
 * @returns {string} e.g. "?a=1&b=2" o "" si vacío
 */
function qs(obj = {}) {
  const s = new URLSearchParams(obj).toString();
  return s ? '?' + s : '';
}

/* ========= Exponer en window para uso directo en las páginas ========= */
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
