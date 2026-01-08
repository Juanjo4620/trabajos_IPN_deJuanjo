const express = require('express');
const cors = require('cors');
const fs = require('fs').promises;
const path = require('path');
const multer = require('multer');
const bcrypt = require('bcryptjs');

const app = express();
app.use(cors());
app.use(express.json());
app.use(express.static('public'));

const DATA_DIR = path.join(__dirname, 'data');
const COMPONENTS_FILE = path.join(DATA_DIR, 'components.json');
const USERS_FILE = path.join(DATA_DIR, 'users.json');
const CARTS_FILE = path.join(DATA_DIR, 'carts.json');
const CATEGORIES_FILE = path.join(DATA_DIR, 'categories.json');

// simple in-memory token store: token -> { username, role }
const tokens = new Map();

async function ensureData() {
  await fs.mkdir(DATA_DIR, { recursive: true });
  try { await fs.access(COMPONENTS_FILE); } catch { await fs.writeFile(COMPONENTS_FILE, JSON.stringify([], null, 2)); }
  try { await fs.access(USERS_FILE); } catch { await fs.writeFile(USERS_FILE, JSON.stringify([], null, 2)); }
  try { await fs.access(CARTS_FILE); } catch { await fs.writeFile(CARTS_FILE, JSON.stringify({}, null, 2)); }
  try { await fs.access(CATEGORIES_FILE); } catch { await fs.writeFile(CATEGORIES_FILE, JSON.stringify([], null, 2)); }
  // ensure uploads folder exists
  await fs.mkdir(path.join(__dirname, 'public', 'uploads'), { recursive: true });
}

async function readJson(file) { const raw = await fs.readFile(file, 'utf8'); return JSON.parse(raw || 'null'); }
async function writeJson(file, data) { await fs.writeFile(file, JSON.stringify(data, null, 2)); }

function genToken() { return `${Date.now()}-${Math.random().toString(36).slice(2,10)}`; }

async function loadUsers() { const users = await readJson(USERS_FILE); return users || []; }
async function loadComponents() { const comps = await readJson(COMPONENTS_FILE); return comps || []; }
async function loadCarts() { const carts = await readJson(CARTS_FILE); return carts || {}; }
async function loadCategories() { const cats = await readJson(CATEGORIES_FILE); return cats || []; }

function authMiddleware(req, res, next) {
  const auth = req.headers['authorization'] || req.headers['x-token'];
  if (!auth) return res.status(401).json({ error: 'No token' });
  const token = (auth.startsWith('Bearer ') ? auth.slice(7) : auth);
  const info = tokens.get(token);
  if (!info) {
    console.warn('Auth failed: invalid token', token);
    return res.status(401).json({ error: 'Token inválido' });
  }
  req.user = info;
  req.token = token;
  next();
}

function requireRole(role) {
  return (req, res, next) => {
    if (!req.user) return res.status(401).json({ error: 'No autenticado' });
    if (req.user.role !== role) return res.status(403).json({ error: 'Permiso denegado' });
    next();
  };
}

// Initialize data and create a default admin/user if none exist
// Initialize data and create a default admin/user if none exist
(async () => {
  await ensureData();
  // ensure default users exist (passwords kept in plain text as requested)
  let users = await loadUsers();
  if (users.length === 0) {
    users = [
      { username: 'admin', password: 'admin123', role: 'admin' },
      { username: 'user', password: 'user123', role: 'user' }
    ];
    await writeJson(USERS_FILE, users);
    console.log('Default users created: admin/admin123 and user/user123 (plaintext)');
  }
})();

// Auth
app.post('/api/login', async (req, res) => {
  const { username, password } = req.body || {};
  if (!username || !password) return res.status(400).json({ error: 'username y password requeridos' });
  const users = await loadUsers();
  const u = users.find(x => x.username === username);
  if (!u) return res.status(401).json({ error: 'Credenciales inválidas' });
  // support both bcrypt-hashed passwords and plaintext stored passwords
  let match = false;
  try {
    if (u.password && typeof u.password === 'string' && u.password.startsWith('$2')) {
      match = await bcrypt.compare(password, u.password);
    } else {
      match = password === u.password;
    }
  } catch (e) {
    match = password === u.password;
  }
  if (!match) return res.status(401).json({ error: 'Credenciales inválidas' });
  const token = genToken();
  tokens.set(token, { username: u.username, role: u.role });
  res.json({ token, role: u.role, username: u.username });
});

// Register new user (default role: user)
app.post('/api/register', async (req, res) => {
  try {
    const { username, password } = req.body || {};
    if (!username || !password) return res.status(400).json({ error: 'username y password requeridos' });
    const users = await loadUsers();
    if (users.find(u => u.username.toLowerCase() === username.toLowerCase())) return res.status(400).json({ error: 'Usuario ya existe' });
    // store password as plain text per user request
    const user = { username, password: password, role: 'user' };
    users.push(user);
    await writeJson(USERS_FILE, users);
    // auto-login
    const token = genToken();
    tokens.set(token, { username: user.username, role: user.role });
    res.json({ ok: true, token, username: user.username, role: user.role });
  } catch (err) {
    console.error('Register error', err);
    res.status(500).json({ error: 'Error registrando usuario', details: err && err.message });
  }
});

// Components
app.get('/api/components', async (req, res) => {
  const comps = await loadComponents();
  res.json(comps);
});

app.post('/api/components', authMiddleware, requireRole('admin'), async (req, res) => {
  try {
    const data = req.body;
    if (!data || !data.name || data.price == null) return res.status(400).json({ error: 'name y price requeridos' });
    const price = Number(data.price);
    if (Number.isNaN(price) || price < 0) return res.status(400).json({ error: 'price inválido' });
    const stock = data.stock == null ? 0 : Number(data.stock);
    if (Number.isNaN(stock) || stock < 0) return res.status(400).json({ error: 'stock inválido' });
    // if category provided, ensure exists
    if (data.category) {
      const cats = await loadCategories();
      if (!cats.find(c => c.id === data.category)) return res.status(400).json({ error: 'category no existe' });
    }
    const comps = await loadComponents();
    const id = Date.now().toString();
    const item = { id, name: data.name, price, stock, description: data.description || '', category: data.category || null, image: data.image || null };
    comps.push(item);
    await writeJson(COMPONENTS_FILE, comps);
    res.json(item);
  } catch (err) {
    console.error('Error creating component', err);
    res.status(500).json({ error: 'Error creando componente', details: err && err.message });
  }
});

app.put('/api/components/:id', authMiddleware, requireRole('admin'), async (req, res) => {
  const id = req.params.id;
  const data = req.body;
  try {
    const comps = await loadComponents();
    const idx = comps.findIndex(c => c.id === id);
    if (idx === -1) return res.status(404).json({ error: 'Componente no encontrado' });
    if (data.price != null) {
      const p = Number(data.price);
      if (Number.isNaN(p) || p < 0) return res.status(400).json({ error: 'price inválido' });
      data.price = p;
    }
    if (data.stock != null) {
      const s = Number(data.stock);
      if (Number.isNaN(s) || s < 0) return res.status(400).json({ error: 'stock inválido' });
      data.stock = s;
    }
    if (data.category) {
      const cats = await loadCategories();
      if (!cats.find(c => c.id === data.category)) return res.status(400).json({ error: 'category no existe' });
    }
    comps[idx] = { ...comps[idx], ...data };
    await writeJson(COMPONENTS_FILE, comps);
    res.json(comps[idx]);
  } catch (err) {
    console.error('Error updating component', err);
    res.status(500).json({ error: 'Error actualizando componente', details: err && err.message });
  }
});

app.delete('/api/components/:id', authMiddleware, requireRole('admin'), async (req, res) => {
  const id = req.params.id;
  try {
    const comps = await loadComponents();
    const idx = comps.findIndex(c => c.id === id);
    if (idx === -1) return res.status(404).json({ error: 'Componente no encontrado' });
    const removed = comps.splice(idx, 1)[0];
    // remove from carts
    const carts = await loadCarts();
    let changed = false;
    Object.keys(carts).forEach(user => {
      const before = carts[user].length;
      carts[user] = (carts[user] || []).filter(i => i.componentId !== id);
      if (carts[user].length !== before) changed = true;
    });
    if (changed) await writeJson(CARTS_FILE, carts);
    // remove image file if exists
    if (removed.image) {
      const imgPath = path.join(__dirname, 'public', removed.image.replace(/^\//,''));
      try { await fs.unlink(imgPath); } catch (e) { /* ignore missing file */ }
    }
    await writeJson(COMPONENTS_FILE, comps);
    res.json({ ok: true, removed });
  } catch (err) {
    console.error('Error deleting component', err);
    res.status(500).json({ error: 'Error eliminando componente', details: err && err.message });
  }
});

// Categories
app.get('/api/categories', async (req, res) => {
  const cats = await loadCategories();
  res.json(cats);
});

app.post('/api/categories', authMiddleware, requireRole('admin'), async (req, res) => {
  try {
    const data = req.body;
    if (!data || !data.name) return res.status(400).json({ error: 'name requerido' });
    const cats = await loadCategories();
    // ensure unique name (case-insensitive)
    if (cats.find(x => x.name && x.name.toLowerCase() === data.name.toLowerCase())) {
      return res.status(400).json({ error: 'Nombre de categoría ya existe' });
    }
    const id = Date.now().toString();
    const c = { id, name: data.name };
    cats.push(c);
    await writeJson(CATEGORIES_FILE, cats);
    res.json(c);
  } catch (err) {
    console.error('Error creating category', err);
    res.status(500).json({ error: 'Error creando categoría', details: err && err.message });
  }
});

app.put('/api/categories/:id', authMiddleware, requireRole('admin'), async (req, res) => {
  const id = req.params.id; const data = req.body;
  const cats = await loadCategories();
  const idx = cats.findIndex(x => x.id === id);
  if (idx === -1) return res.status(404).json({ error: 'Categoría no encontrada' });
  cats[idx] = { ...cats[idx], ...data };
  await writeJson(CATEGORIES_FILE, cats);
  res.json(cats[idx]);
});

app.delete('/api/categories/:id', authMiddleware, requireRole('admin'), async (req, res) => {
  try {
    const id = req.params.id;
    const cats = await loadCategories();
    const idx = cats.findIndex(x => x.id === id);
    if (idx === -1) return res.status(404).json({ error: 'Categoría no encontrada' });
    // ensure no components reference this category
    const comps = await loadComponents();
    if (comps.find(c => c.category === id)) return res.status(400).json({ error: 'No se puede eliminar: categoría en uso' });
    const removed = cats.splice(idx,1)[0];
    await writeJson(CATEGORIES_FILE, cats);
    res.json({ ok: true, removed });
  } catch (err) {
    console.error('Error deleting category', err);
    res.status(500).json({ error: 'Error eliminando categoría', details: err && err.message });
  }
});

// Image upload (admin only) - store with original extension and safer filename
const uploadsDir = path.join(__dirname, 'public', 'uploads');
const storage = multer.diskStorage({
  destination: uploadsDir,
  filename: (req, file, cb) => {
    const ext = path.extname(file.originalname) || '';
    const name = `${Date.now()}-${Math.random().toString(36).slice(2,8)}${ext}`;
    cb(null, name);
  }
});
const upload = multer({ storage });
app.post('/api/upload', authMiddleware, requireRole('admin'), upload.single('image'), async (req, res) => {
  try {
    if (!req.file) return res.status(400).json({ error: 'file required' });
    const urlPath = `/uploads/${req.file.filename}`;
    res.json({ path: urlPath });
  } catch (err) {
    console.error('Upload error', err);
    res.status(500).json({ error: 'Error subiendo archivo', details: err && err.message });
  }
});

// Export components as CSV for user
app.get('/api/components/export', authMiddleware, async (req, res) => {
  const comps = await loadComponents();
  const rows = ['id,name,price,stock,description,category,image', ...comps.map(c => `${c.id},"${(c.name||'').replace(/\"/g,'\"')}",${c.price},${c.stock || 0},"${(c.description||'').replace(/\"/g,'\"')}","${c.category||''}","${c.image||''}"` )];
  const csv = rows.join('\n');
  res.setHeader('Content-Type', 'text/csv');
  res.setHeader('Content-Disposition', 'attachment; filename="components.csv"');
  res.send(csv);
});

// Cart stored in carts.json per username
app.post('/api/cart/add', authMiddleware, requireRole('user'), async (req, res) => {
  const { componentId, quantity } = req.body || {};
  if (!componentId || !quantity) return res.status(400).json({ error: 'componentId y quantity requeridos' });
  const carts = await loadCarts();
  const username = req.user.username;
  carts[username] = carts[username] || [];
  const existing = carts[username].find(i => i.componentId === componentId);
  if (existing) existing.quantity += Number(quantity);
  else carts[username].push({ componentId, quantity: Number(quantity) });
  await writeJson(CARTS_FILE, carts);
  res.json({ ok: true, cart: carts[username] });
});

app.get('/api/cart', authMiddleware, requireRole('user'), async (req, res) => {
  const carts = await loadCarts();
  const username = req.user.username;
  res.json(carts[username] || []);
});

app.delete('/api/cart/:componentId', authMiddleware, requireRole('user'), async (req, res) => {
  const id = req.params.componentId;
  const carts = await loadCarts();
  const username = req.user.username;
  carts[username] = (carts[username] || []).filter(i => i.componentId !== id);
  await writeJson(CARTS_FILE, carts);
  res.json({ ok: true });
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => console.log(`Server listening on http://localhost:${PORT}`));
