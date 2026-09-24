# M1 测试商城

简单的纯前端商城 SPA · Vanilla JS + Webpack 5

## 功能
- 商品列表(分类筛选 + 关键字搜索)
- 商品详情(数量选择、面包屑、加入购物车)
- 购物车(增删改、合计、持久化到 localStorage)
- Hash 路由,零服务端依赖

## 目录
```
src/
  data/products.js   # mock 商品数据
  store/index.js     # 购物车 store(localStorage 持久化)
  router/index.js    # hash 路由
  pages/             # 列表 / 详情 / 购物车
  components/header  # 顶部导航 + 徽章
  styles/            # Sass 模块化
  index.js / index.html
```

## 启动
```bash
npm install
npm start         # 开发服务器 http://localhost:8081
npm run build     # 生产构建到 dist/
```

## 构建产物大小
- main.css ~ 8.15 KB
- main.js  ~ 16.4 KB (minified)
