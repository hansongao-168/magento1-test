import { getProducts, categories } from '../data/products.js';
import { escapeHTML, formatPrice, placeholderSVG } from '../utils/dom.js';
import { router } from '../router/index.js';
import cart from '../store/index.js';

function renderCategories(active) {
  return categories
    .map(
      (c) => `
      <button
        type="button"
        class="category-pill ${c.id === active ? 'is-active' : ''}"
        data-category="${escapeHTML(c.id)}">
        ${escapeHTML(c.name)}
      </button>`,
    )
    .join('');
}

function renderCard(p) {
  const discount = p.originalPrice > p.price
    ? `<span class="badge">-${Math.round((1 - p.price / p.originalPrice) * 100)}%</span>`
    : '';
  return `
    <article class="product-card" data-id="${escapeHTML(p.id)}">
      <a class="product-card__media" href="#/product/${escapeHTML(p.id)}">
        <img
          src="${escapeHTML(p.image)}"
          alt="${escapeHTML(p.name)}"
          loading="lazy"
          onerror="this.onerror=null;this.src='${placeholderSVG(p.name, p.color)}'">
        ${discount}
      </a>
      <div class="product-card__body">
        <h3 class="product-card__title">
          <a href="#/product/${escapeHTML(p.id)}">${escapeHTML(p.name)}</a>
        </h3>
        <p class="product-card__summary">${escapeHTML(p.summary)}</p>
        <div class="product-card__meta">
          <span class="price">${formatPrice(p.price)}</span>
          ${
            p.originalPrice > p.price
              ? `<s class="price-original">${formatPrice(p.originalPrice)}</s>`
              : ''
          }
          <span class="rating">★ ${p.rating.toFixed(1)}</span>
        </div>
        <button type="button" class="btn btn-primary js-add-to-cart" data-id="${escapeHTML(p.id)}">
          加入购物车
        </button>
      </div>
    </article>`;
}

export function renderListPage(root, route) {
  const { category = 'all' } = route.params;
  const keyword = route.query.q || '';
  const items = getProducts({ category, keyword });

  root.innerHTML = `
    <section class="page page-list">
      <header class="page-header">
        <h1>商品列表</h1>
        <p class="muted">共 ${items.length} 件商品</p>
      </header>

      <div class="toolbar">
        <nav class="categories" aria-label="商品分类">${renderCategories(category)}</nav>
        <div class="search">
          <input
            type="search"
            id="kw"
            placeholder="搜索商品…"
            value="${escapeHTML(keyword)}"
            aria-label="搜索商品">
        </div>
      </div>

      ${
        items.length
          ? `<div class="product-grid">${items.map(renderCard).join('')}</div>`
          : `<div class="empty">没有匹配的商品,试试其他关键字或分类。</div>`
      }
    </section>
  `;

  // Bind category pill clicks
  root.querySelectorAll('.category-pill').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.category;
      router.navigate(id === 'all' ? '/' : `/category/${id}`);
    });
  });

  // Search input — debounced navigation
  const input = root.querySelector('#kw');
  let timer;
  input.addEventListener('input', (e) => {
    clearTimeout(timer);
    const value = e.target.value;
    timer = setTimeout(() => {
      const path = category === 'all' ? '/' : `/category/${category}`;
      const query = value ? `?q=${encodeURIComponent(value)}` : '';
      router.navigate(`${path}${query}`);
    }, 250);
  });

  // Add-to-cart buttons on each card
  root.querySelectorAll('.js-add-to-cart').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const id = btn.dataset.id;
      const product = items.find((p) => p.id === id);
      if (!product) return;
      cart.add(product, 1);
      flashAdded(btn);
    });
  });
}

function flashAdded(btn) {
  const original = btn.textContent;
  btn.textContent = '已加入 ✓';
  btn.disabled = true;
  setTimeout(() => {
    btn.textContent = original;
    btn.disabled = false;
  }, 800);
}

export default renderListPage;
