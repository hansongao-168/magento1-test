import { escapeHTML, formatPrice, placeholderSVG } from '../utils/dom.js';
import cart from '../store/index.js';

function row(it) {
  const subtotal = it.qty * it.price;
  return `
    <tr data-id="${escapeHTML(it.id)}">
      <td class="cart-cell-product">
        <img
          src="${escapeHTML(it.image)}"
          alt="${escapeHTML(it.name)}"
          onerror="this.onerror=null;this.src='${placeholderSVG(it.name, it.color || '#94a3b8')}'">
        <div>
          <a class="cart-cell-product__name" href="#/product/${escapeHTML(it.id)}">
            ${escapeHTML(it.name)}
          </a>
        </div>
      </td>
      <td class="cart-cell-price">${formatPrice(it.price)}</td>
      <td>
        <div class="qty-stepper">
          <button type="button" class="qty-stepper__btn" data-act="dec" aria-label="减少">−</button>
          <input type="number" min="0" value="${it.qty}" data-act="set" aria-label="数量">
          <button type="button" class="qty-stepper__btn" data-act="inc" aria-label="增加">+</button>
        </div>
      </td>
      <td class="cart-cell-subtotal"><strong>${formatPrice(subtotal)}</strong></td>
      <td>
        <button type="button" class="btn-link" data-act="remove">删除</button>
      </td>
    </tr>`;
}

export function renderCartPage(root) {
  const items = cart.items;
  const total = cart.totalPrice();
  const count = cart.totalCount();

  root.innerHTML = `
    <section class="page page-cart">
      <header class="page-header">
        <h1>购物车</h1>
        <p class="muted">共 ${count} 件商品</p>
      </header>

      ${
        items.length
          ? `
        <div class="cart-wrap">
          <table class="cart-table">
            <thead>
              <tr>
                <th>商品</th>
                <th>单价</th>
                <th>数量</th>
                <th>小计</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              ${items.map(row).join('')}
            </tbody>
          </table>

          <div class="cart-summary">
            <button type="button" class="btn-link" id="clear-btn">清空购物车</button>
            <div class="cart-summary__total">
              合计: <strong>${formatPrice(total)}</strong>
            </div>
            <button type="button" class="btn btn-primary btn--lg" id="checkout-btn">
              去结算
            </button>
          </div>
        </div>
        `
          : `
        <div class="empty">
          <p>购物车空空如也。</p>
          <a class="btn btn-primary" href="#/">去逛逛</a>
        </div>
        `
      }
    </section>
  `;

  root.querySelectorAll('tr[data-id]').forEach((tr) => {
    const id = tr.dataset.id;
    const input = tr.querySelector('input[data-act="set"]');

    tr.querySelector('[data-act="inc"]').addEventListener('click', () => {
      cart.setQty(id, Number(input.value || 0) + 1);
    });
    tr.querySelector('[data-act="dec"]').addEventListener('click', () => {
      cart.setQty(id, Number(input.value || 0) - 1);
    });
    input.addEventListener('change', () => {
      cart.setQty(id, Number(input.value || 0));
    });
    tr.querySelector('[data-act="remove"]').addEventListener('click', () => {
      cart.remove(id);
    });
  });

  const clearBtn = root.querySelector('#clear-btn');
  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      if (confirm('确定要清空购物车吗?')) cart.clear();
    });
  }

  const checkoutBtn = root.querySelector('#checkout-btn');
  if (checkoutBtn) {
    checkoutBtn.addEventListener('click', () => {
      alert(`已下单 (演示) — 应付 ${formatPrice(total)}`);
    });
  }
}

export default renderCartPage;
