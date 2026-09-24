import { escapeHTML } from '../utils/dom.js';
import { router } from '../router/index.js';
import cart from '../store/index.js';

export function renderHeader(root) {
  root.innerHTML = `
    <header class="site-header">
      <div class="site-header__inner">
        <a class="brand" href="#/" aria-label="返回首页">
          <span class="brand__logo">M1</span>
          <span class="brand__name">测试商城</span>
        </a>
        <nav class="site-nav" aria-label="主导航">
          <a href="#/" data-nav="/">首页</a>
          <a href="#/category/audio" data-nav="category">商品</a>
        </nav>
        <a class="cart-link" href="#/cart" aria-label="购物车">
          <span class="cart-link__icon" aria-hidden="true">🛒</span>
          <span>购物车</span>
          <span class="cart-badge" id="cart-badge">${cart.totalCount()}</span>
        </a>
      </div>
    </header>
  `;

  const badge = root.querySelector('#cart-badge');
  cart.subscribe(() => {
    badge.textContent = String(cart.totalCount());
  });

  // Highlight active nav link when route changes
  router.subscribe((route) => {
    root.querySelectorAll('.site-nav a').forEach((a) => {
      const isHome = a.dataset.nav === '/' && route.name === 'list' && route.params.category === 'all';
      const isList = a.dataset.nav === 'category' && (route.name === 'list' || route.name === 'detail');
      a.classList.toggle('is-active', isHome || isList);
    });
  });
}

export default renderHeader;
