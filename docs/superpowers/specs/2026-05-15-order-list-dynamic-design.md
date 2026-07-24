# 前台订单列表动态显示 — 设计方案

## 概述

将 Magento 1.x 前台"我的订单"列表从传统的服务端静态 HTML 渲染改造为 AJAX 驱动的动态列表，使用 Bootstrap 5.0 + Alpine.js 构建现代化交互体验，同时增加订单数量统计功能。

## 技术栈

| 技术 | 版本 | 用途 |
|------|------|------|
| Bootstrap | 5.0 | UI 框架（表格、卡片、分页、徽章） |
| Alpine.js | 3.x | 前端响应式状态管理、AJAX 交互 |
| PHP | Magento 1 | 后端控制器、Block、JSON API |
| MySQL | Magento 1 | 订单数据查询 |

## 架构

```
浏览器 ──→ /xfe_order/order/index  (初始 HTML 渲染)
     │
     └── Alpine.js init ──→ AJAX /xfe_order/order/list ──→ 返回 JSON
                                   │                        ├── stats（今日/本周/本月/今年订单数）
                                   │                        ├── orders（订单列表）
                                   │                        └── pagination（分页信息）
                                   │
                                   └── 搜索/筛选/分页 → 再次 AJAX 请求，刷新列表
```

- **初始请求**：传统服务端渲染，返回 Alpine.js 挂载容器
- **后续交互**：全部通过 Alpine.js 发送 AJAX 请求获取 JSON，无刷新更新 DOM
- **认证方式**：直接复用 Magento Customer Session，无需额外 Token

## 模块结构

### 新建模块 `XFE_OrderFrontend`

```
app/code/community/XFE/OrderFrontend/
├── etc/
│   └── config.xml                    # 模块配置 + 前端路由
├── controllers/
│   └── OrderController.php           # indexAction + listAction
├── Block/
│   └── Order/
│       └── History.php               # 页面 Block
└── Helper/
    └── Data.php                      # 工具方法（可选）

app/etc/modules/XFE_OrderFrontend.xml  # 模块声明
```

### 新建主题 `exp5/default`

```
app/design/frontend/exp5/default/
├── etc/
│   └── theme.xml                     # 主题声明，parent: base/default
├── layout/
│   ├── page.xml                      # 全局 Bootstrap CSS 引入
│   ├── xfe_order.xml                 # 订单页面布局
│   └── customer_account.xml          # 导航菜单覆盖
└── template/
    └── xfe_order/
        └── order/
            └── history.phtml         # 前端模板 + Alpine.js 组件
```

## 路由

| URL | Action | 说明 |
|-----|--------|------|
| `/xfe_order/order/index` | `indexAction` | 服务端渲染初始页面 |
| `/xfe_order/order/list` | `listAction` | AJAX JSON 端点 |

### JSON 响应格式

```json
{
  "success": true,
  "data": {
    "stats": {
      "today": 3,
      "week": 18,
      "month": 45,
      "year": 312
    },
    "orders": [
      {
        "entity_id": 123,
        "increment_id": "100000123",
        "created_at": "2026-05-15 10:30:00",
        "status": "processing",
        "status_label": "处理中",
        "grand_total": "199.99",
        "currency": "CNY",
        "shipping_name": "张三",
        "can_reorder": true
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 5,
      "per_page": 10,
      "total": 48
    }
  }
}
```

## 控制器设计

### OrderController

**`indexAction()`**：
1. 检查客户登录，未登录跳转登录页
2. `loadLayout()` → 设置标题 "My Orders" → `renderLayout()`

**`listAction()`**：
1. 验证 Customer Session 登录，未登录返回 JSON 错误
2. 读取请求参数：`page`, `limit`, `search`, `status`, `date_from`, `date_to`
3. 构建 `sales/order_collection` 查询：
   - 过滤当前客户的可见状态订单
   - `search` → `increment_id LIKE %keyword%`
   - `status` → `status = value`
   - `date_from/date_to` → `created_at` 范围过滤
   - 按 `created_at DESC` 排序
   - 分页：`setPageSize(limit)` + `setCurPage(page)`
4. 同时计算统计数据（4 次独立 COUNT 查询）：
   - 今日：`created_at >= today 00:00:00`
   - 本周：`created_at >= 本周一 00:00:00`
   - 本月：`created_at >= 本月1日 00:00:00`
   - 今年：`created_at >= 今年1月1日 00:00:00`
5. 拼装 JSON 返回

## Block 设计

### XFE_OrderFrontend_Block_Order_History

| 方法 | 说明 |
|------|------|
| `getListUrl()` | 返回 AJAX 端点 URL |
| `getViewUrl($order)` | 返回原查看订单链接（旧页面跳转） |
| `getReorderUrl($order)` | 返回重新订购链接 |
| `getStatusOptions()` | 返回订单状态下拉选项 |

## 前端模板

### page.xml (exp5/default)

- 全局引入 Bootstrap 5.0 CSS（CDN）
- 全站可用 Bootstrap 样式

### xfe_order.xml

- 引用 `customer_account` handle
- 在 `my.account.wrapper` 中插入订单列表 Block
- 引入 Alpine.js CDN

### customer_account.xml

- 移除旧的 `orders` 导航链接
- 添加新的 `xfe_orders` 导航链接指向 `/xfe_order/order/index`

### history.phtml

页面结构：
1. **统计卡片行**：4 个彩色卡片，Alpine.js 动态渲染 `stats.today`, `stats.week`, `stats.month`, `stats.year`，每个卡片使用不同渐变背景色
2. **搜索/筛选栏**：订单号输入框、状态下拉、开始日期、结束日期、搜索按钮，`x-model` 绑定，`@change` 或 `@click` 触发 `searchOrders()`
3. **统计信息**：显示 "共 N 笔订单"
4. **订单表格**：
   - 加载中：spinner + "加载中..."
   - 无数据："暂无订单记录"
   - 数据行：订单号（链接）、日期、收货人、金额、状态（彩色徽章）、操作（查看链接 + 重新订购链接）
5. **分页导航**：页码范围显示（首尾 + 当前页前后 2 页），上一页/下一页按钮

### Alpine.js 组件（`orderList()`）

**状态属性**：
- `orders[]`、`stats{}`、`loading`、`error`
- `search`、`status`、`dateFrom`、`dateTo`
- `page`、`lastPage`、`perPage`、`total`

**方法**：
- `init()` → 调用 `loadOrders()`
- `loadOrders()` → `fetch(url + params)` → 解析 JSON → 更新状态
- `searchOrders()` → 重置 `page=1` → 调用 `loadOrders()`
- `loadPage(p)` → 设置 `page=p` → 调用 `loadOrders()` → 滚动到顶部
- `paginationRange()` → 计算显示的页码数组（含省略号）
- `statusBadge(status)` → 状态到 Bootstrap 徽章颜色映射
- `formatDate(dateStr)` → 日期格式化

## 统计功能

查询逻辑（在 `_getOrderStats()` 中实现）：
- 4 个轻量 COUNT 查询，基于同一基础查询模板
- 仅统计当前客户、可见状态的订单
- 时间范围通过 `created_at` 字段过滤

## 与原系统的关系

- **不修改核心代码**：所有改动都在社区模块和自定义主题中
- **保留原订单详情页**："查看"操作仍然跳转到 `sales/order/view` 旧页面
- **导航替换**：客户菜单中的"我的订单"链接指向新页面

## 涉及文件清单

| # | 文件路径 | 操作 |
|---|---------|------|
| 1 | `app/etc/modules/XFE_OrderFrontend.xml` | 新建 |
| 2 | `app/code/community/XFE/OrderFrontend/etc/config.xml` | 新建 |
| 3 | `app/code/community/XFE/OrderFrontend/controllers/OrderController.php` | 新建 |
| 4 | `app/code/community/XFE/OrderFrontend/Block/Order/History.php` | 新建 |
| 5 | `app/code/community/XFE/OrderFrontend/Helper/Data.php` | 新建 |
| 6 | `app/design/frontend/exp5/default/etc/theme.xml` | 新建 |
| 7 | `app/design/frontend/exp5/default/layout/page.xml` | 新建 |
| 8 | `app/design/frontend/exp5/default/layout/xfe_order.xml` | 新建 |
| 9 | `app/design/frontend/exp5/default/layout/customer_account.xml` | 新建 |
| 10 | `app/design/frontend/exp5/default/template/xfe_order/order/history.phtml` | 新建 |

共计 10 个文件，无需新增数据库表，无需修改核心代码。
