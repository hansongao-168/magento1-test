// Hash-based router. Routes:
//   #/             -> product list
//   #/category/:id -> product list filtered by category
//   #/product/:id  -> product detail
//   #/cart         -> shopping cart
const listeners = new Set();
let currentRoute = parseHash(window.location.hash);

function parseHash(hash) {
  const raw = (hash || '').replace(/^#/, '') || '/';
  const [pathPart, queryPart = ''] = raw.split('?');
  const segments = pathPart.split('/').filter(Boolean);
  const query = Object.fromEntries(new URLSearchParams(queryPart));

  if (segments.length === 0) {
    return { name: 'list', params: { category: 'all' }, query };
  }
  if (segments[0] === 'category' && segments[1]) {
    return { name: 'list', params: { category: decodeURIComponent(segments[1]) }, query };
  }
  if (segments[0] === 'product' && segments[1]) {
    return { name: 'detail', params: { id: decodeURIComponent(segments[1]) }, query };
  }
  if (segments[0] === 'cart') {
    return { name: 'cart', params: {}, query };
  }
  return { name: 'notFound', params: {}, query };
}

function emit() {
  listeners.forEach((fn) => fn(currentRoute));
}

window.addEventListener('hashchange', () => {
  currentRoute = parseHash(window.location.hash);
  emit();
});

export const router = {
  get current() {
    return currentRoute;
  },

  navigate(path) {
    if (window.location.hash === `#${path}`) return;
    window.location.hash = path;
  },

  subscribe(fn) {
    listeners.add(fn);
    fn(currentRoute);
    return () => listeners.delete(fn);
  },
};

export default router;
