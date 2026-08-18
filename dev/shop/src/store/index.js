// Tiny event-emitter + cart store. Cart is persisted to localStorage so a
// page refresh doesn't wipe it.
const STORAGE_KEY = 'm1-shop:cart:v1';

class Store {
  constructor(initialState) {
    this.state = initialState;
    this.listeners = new Set();
  }

  get() {
    return this.state;
  }

  set(updater) {
    const next = typeof updater === 'function' ? updater(this.state) : updater;
    this.state = { ...this.state, ...next };
    this.emit();
  }

  subscribe(fn) {
    this.listeners.add(fn);
    return () => this.listeners.delete(fn);
  }

  emit() {
    this.listeners.forEach((fn) => fn(this.state));
  }
}

function loadCart() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch (err) {
    console.warn('[shop] failed to read cart from storage', err);
    return [];
  }
}

function saveCart(items) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
  } catch (err) {
    console.warn('[shop] failed to persist cart', err);
  }
}

function findIndex(items, id) {
  return items.findIndex((it) => it.id === id);
}

const cartStore = new Store({
  items: loadCart(),
});

cartStore.subscribe((state) => saveCart(state.items));

export const cart = {
  get items() {
    return cartStore.get().items;
  },

  add(product, qty = 1) {
    cartStore.set((state) => {
      const items = [...state.items];
      const idx = findIndex(items, product.id);
      if (idx >= 0) {
        items[idx] = { ...items[idx], qty: items[idx].qty + qty };
      } else {
        items.push({
          id: product.id,
          name: product.name,
          price: product.price,
          image: product.image,
          color: product.color,
          qty,
        });
      }
      return { items };
    });
  },

  setQty(id, qty) {
    const safe = Math.max(0, Math.floor(Number(qty) || 0));
    cartStore.set((state) => {
      const items = safe === 0
        ? state.items.filter((it) => it.id !== id)
        : state.items.map((it) => (it.id === id ? { ...it, qty: safe } : it));
      return { items };
    });
  },

  remove(id) {
    cartStore.set((state) => ({
      items: state.items.filter((it) => it.id !== id),
    }));
  },

  clear() {
    cartStore.set({ items: [] });
  },

  totalCount() {
    return this.items.reduce((sum, it) => sum + it.qty, 0);
  },

  totalPrice() {
    return this.items.reduce((sum, it) => sum + it.qty * it.price, 0);
  },

  subscribe(fn) {
    return cartStore.subscribe(fn);
  },
};

export default cart;
