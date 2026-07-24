# Checkout MySQL 错误捕获与记录模块设计

## 概述

在 Magento 1.9.3.7 的下单流程中，当保存地址（billing/shipping）或提交订单（saveOrder）时出现 MySQL 异常，需要捕获错误、记录到数据库和日志文件，并返回 JSON 错误响应给前端，不输出 PHP 错误页面。

## 约束

- 不修改任何 Magento 核心源码
- 通过标准模块扩展机制实现

## 技术方案

创建 `XFE_CheckoutErrors` 模块，通过 controller 路由优先级覆盖 `Mage_Checkout_OnepageController`，在 `dispatch()` 方法中统一包裹 try-catch，拦截所有 AJAX 保存步骤的异常。

## 模块结构

```
app/code/community/XFE/CheckoutErrors/
├── etc/
│   └── config.xml
├── controllers/
│   ├── OnepageController.php
│   └── Adminhtml/
│       └── Xfe/
│           └── CheckoutErrorController.php
├── Model/
│   ├── CheckoutError.php
│   └── Resource/
│       ├── CheckoutError.php
│       └── CheckoutError/
│           └── Collection.php
├── Block/
│   └── Adminhtml/
│       ├── CheckoutError.php
│       └── CheckoutError/
│           └── Grid.php
├── Helper/
│   └── Data.php
├── sql/
│   └── CheckoutErrors_setup/
│       └── install-0.1.0.php
```

## 数据库

表名：`xfe_checkout_error`

| 字段 | 类型 | 说明 |
|------|------|------|
| error_id | INT UNSIGNED AUTO_INCREMENT PK | 主键 |
| reference_id | VARCHAR(32) NOT NULL | 唯一参考号（ERR-YYYYMMDDHHmmss-xxxx） |
| step | VARCHAR(32) NOT NULL | 步骤名（saveBilling/saveShipping/saveShippingMethod/savePayment/saveOrder） |
| error_message | TEXT NULL | 错误消息 |
| error_trace | TEXT NULL | 异常堆栈 |
| request_data | TEXT NULL | 序列化的请求数据 |
| customer_id | INT UNSIGNED NULL DEFAULT 0 | 客户ID（游客为0） |
| customer_email | VARCHAR(128) NULL | 客户邮箱 |
| quote_id | INT UNSIGNED NULL DEFAULT 0 | Quote ID |
| store_id | SMALLINT UNSIGNED NULL DEFAULT 0 | 店铺ID |
| created_at | DATETIME NOT NULL | 创建时间 |

索引：reference_id, step, created_at

## 核心逻辑

### config.xml

- 模块声明，版本 0.1.0
- Model/Resource/Collection/Helper/Block 声明
- 数据库安装脚本
- 前台 checkout router 模块优先级覆盖（before="Mage_Checkout"）
- 后台路由注册（adminhtml 模块注入）
- 后台菜单（Sales > Checkout 错误记录）
- ACL 权限节点

### OnepageController.php

- 继承 `Mage_Checkout_OnepageController`
- 重写 `dispatch($action)` 方法
- 仅拦截 5 个 AJAX action：saveBilling, saveShipping, saveShippingMethod, savePayment, saveOrder
- try-catch 统一捕获 Exception：
  1. 生成参考号 ERR-{datetime}-{rand}
  2. 写入 DB 表（含完整错误信息、请求数据、客户信息）
  3. 写入文件日志 var/log/checkout_error.log（Mage::log）
  4. 返回 JSON：{error: true, message: "下单失败，请联系客服，参考号：ERR-xxx", ref_id: "ERR-xxx"}

### Helper/Data.php

- `logError($refId, $step, $e, $request)` 方法
- 格式化日志：时间、参考号、步骤、错误消息、堆栈、请求数据

### Admin Grid

- 控制器：`Adminhtml/Xfe/CheckoutErrorController`，indexAction 渲染 Grid 页面
- Grid Block：展示参考号、步骤、错误信息、客户邮箱、时间
- 按 created_at 倒序排列
- 支持按步骤筛选、按时间排序
- 菜单路径：Sales > Checkout 错误记录

## 数据流

```
前端 AJAX POST
  → XFE_CheckoutErrors_OnepageController::dispatch()
    → parent::dispatch() [调用核心 saveXxxAction]
    → 发生 MySQL 异常
    → catch: _handleCheckoutError()
      1. Mage::getModel('xfe_checkouterrors/checkoutError')->save()   [DB]
      2. Mage::helper('xfe_checkouterrors')->logError()                [文件日志]
      3. 返回 JSON error 响应                                              [前端]
```

## 不在此范围

- 前端 JS 修改（依赖 Magento 标准 AJAX 错误处理）
- 订单重试/恢复机制
- 详细错误查看页面（行点击跳转详情）
