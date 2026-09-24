import { getProductById, categories } from '../data/products.js';
import { escapeHTML, formatPrice, placeholderSVG } from '../utils/dom.js';
import cart from '../store/index.js';

export function renderDetailPage(root, route) {
  const product = getProductById(route.params.id);

  if (!product) {
    root.innerHTML = `
      <section class="page page-detail">
        <div class="empty">
          <p>找不到该商品。</p>
          <a class="btn" href="#/">返回商品列表</a>
        </div>
      </section>`;
    return;
  }

  const cat = categories.find((c) => c.id === product.category);

  root.innerHTML = `
    <section class="page page-detail">
      <nav class="breadcrumb" aria-label="面包屑">
        <a href="#/">首页</a>
        <span>/</span>
        <a href="#/category/${escapeHTML(product.category)}">${escapeHTML(cat ? cat.name : '其他')}</a>
        <span>/</span>
        <span>${escapeHTML(product.name)}</span>
      </nav>

      <div class="detail">
        <div class="detail__media">
          <img
            src="${escapeHTML(product.image)}"
            alt="${escapeHTML(product.name)}"
            onerror="this.onerror=null;this.src='${placeholderSVG(product.name, product.color)}'">
        </div>
        <div class="detail__body">
          <h1>${escapeHTML(product.name)}</h1>
          <p class="detail__summary">${escapeHTML(product.summary)}</p>

          <div class="detail__price">
            <span class="price price--lg">${formatPrice(product.price)}</span>
            ${
              product.originalPrice > product.price
                ? `<s class="price-original">${formatPrice(product.originalPrice)}</s>
                   <span class="badge">省 ${formatPrice(product.originalPrice - product.price)}</span>`
                : ''
            }
          </div>

          <ul class="detail__meta">
            <li>评分: <strong>★ ${product.rating.toFixed(1)}</strong></li>
            <li>库存: <strong>${product.stock}</strong> 件</li>
            <li>分类: <strong>${escapeHTML(cat ? cat.name : '其他')}</strong></li>
          </ul>

          <p class="detail__desc">${escapeHTML(product.description)}</p>

          <div class="detail__actions">
            <label class="qty-input">
              <span>数量</span>
              <button type="button" class="qty-input__btn" data-step="-1" aria-label="减少">−</button>
              <input type="number" id="qty" min="1" max="${product.stock}" value="1">
              <button type="button" class="qty-input__btn" data-step="1" aria-label="增加">+</button>
            </label>
            <button type="button" id="add-btn" class="btn btn-primary btn--lg">加入购物车</button>
            <a class="btn btn-ghost" href="#/cart">查看购物车</a>
          </div>
        </div>
      </div>
    </section>
  `;

  const qtyInput = root.querySelector('#qty');
  root.querySelectorAll('.qty-input__btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      const step = Number(btn.dataset.step);
      const next = Math.max(1, Math.min(product.stock, Number(qtyInput.value || 1) + step));
      qtyInput.value = String(next);
    });
  });

  const addBtn = root.querySelector('#add-btn');
  addBtn.addEventListener('click', () => {
    const qty = Math.max(1, Math.min(product.stock, Number(qtyInput.value || 1)));
    cart.add(product, qty);
    addBtn.textContent = `已加入 ${qty} 件 ✓`;
    addBtn.disabled = true;
    setTimeout(() => {
      addBtn.textContent = '加入购物车';
      addBtn.disabled = false;
    }, 1000);
  });
}

export default renderDetailPage;
