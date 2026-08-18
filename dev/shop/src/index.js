import './styles/main.scss';
import { router } from './router/index.js';
import { renderHeader } from './components/header.js';
import { renderListPage } from './pages/list.js';
import { renderDetailPage } from './pages/detail.js';
import { renderCartPage } from './pages/cart.js';

const app = document.getElementById('app');

function mountShell() {
  app.innerHTML = `
    <div class="layout">
      <div id="site-header"></div>
      <main id="page" class="layout__main"></main>
      <footer class="site-footer">
        <p>© 2026 M1 测试商城 · 用 Vanilla JS + Webpack 5 打造</p>
      </footer>
    </div>
  `;
  renderHeader(document.getElementById('site-header'));
}

function render(route) {
  const main = document.getElementById('page');
  if (!main) return;
  window.scrollTo({ top: 0, behavior: 'instant' });
  switch (route.name) {
    case 'list':
      renderListPage(main, route);
      break;
    case 'detail':
      renderDetailPage(main, route);
      break;
    case 'cart':
      renderCartPage(main);
      break;
    default:
      main.innerHTML = `
        <section class="page">
          <div class="empty">
            <h1>404</h1>
            <p>页面不存在。</p>
            <a class="btn btn-primary" href="#/">返回首页</a>
          </div>
        </section>`;
  }
}

mountShell();
router.subscribe(render);
