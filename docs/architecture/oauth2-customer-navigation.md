# OAuth2 客户账户导航架构

- 状态：Accepted
- 日期：2026-09-09
- 决策者：XFE 开发组

## 背景

`XFE_OAuth2` 模块在 storefront 提供两个 customer-facing 页面：

- `/oauth2/client/index` — 我的 API Clients 列表
- `/oauth2/client/new` — 新建 API Client 表单

这两个页面必须和 Magento 标准 My Account 中心保持一致的视觉与交互：

- 左侧展示 **My Account 导航栏**（Account Dashboard / Account Information / Address Book / My Orders / …）
- 高亮当前页（`My API Clients` 在导航中以 `current` 状态显示）
- 维持 `2columns-left` 页面布局与 `my-account` 容器结构

## 诊断过程

### 现象

`/oauth2/client/index` 页面 2columns-left 模板正确切换，但 `col-left` 区域为空，没有 `customer_account_navigation` 渲染。

### Magento 1 layout merge 机制

1. `Mage_Core_Controller_Front_Action::loadLayout()` 不带参数调用时，按以下顺序 `addHandle`：
   - `default`
   - `STORE_xxx`
   - `THEME_xxx`
   - `PACKAGE_xxx`
   - `user_logged_in` 或 `user_logged_out`
   - **当前 action 全名 = `getRequestedRouteName() . '_' . getRequestedControllerName() . '_' . getRequestedActionName()`**
2. **只有上面这些 handle 才会被 merge**。`<customer_account>` 不会自动加进来。
3. 要让 nav block 在 OAuth2 页面被创建，必须让 `<customer_account>` handle 进入 merge 队列。

### 关键代码：路由名 ≠ frontName

```php
// app/code/core/Mage/Core/Controller/Varien/Router/Standard.php
$request->setRouteName($this->getRouteByFrontName($module));

// app/code/core/Mage/Core/Controller/Varien/Action.php
public function getFullActionName($delimiter='_')
{
    return $this->getRequest()->getRequestedRouteName() . $delimiter
        . $this->getRequest()->getRequestedControllerName() . $delimiter
        . $this->getRequest()->getRequestedActionName();
}
```

`getRequestedRouteName()` 返回的是 **`<routers><xfeoauth2>` 标签的 `name` 属性**（即 router name），**不是** `frontName`。

XFE_OAuth2 的路由配置：

```xml
<!-- app/code/community/XFE/OAuth2/etc/config.xml -->
<frontend>
    <routers>
        <xfeoauth2>                    <!-- ← 这就是 router name（route name） -->
            <use>standard</use>
            <args>
                <module>XFE_OAuth2</module>
                <frontName>oauth2</frontName>  <!-- ← 这只是 URL 前缀 -->
            </args>
        </xfeoauth2>
    </routers>
</frontend>
```

因此：

- URL `/oauth2/client/index/` 匹配到 frontName `oauth2` 后被解析到 `XFE_OAuth2_ClientController::indexAction`
- `getFullActionName()` 返回 **`xfeoauth2_client_index`**
- `addHandle('xfeoauth2_client_index')` 进入 merge 队列
- **Magento 找的就是 `<xfeoauth2_client_index>` 这个 layout handle**

### 第一次错误的 XML 写法

```xml
<!-- ❌ 错：handle 名字用了 frontName + controller + action，Magento 永远不会找这个 handle -->
<oauth2_client_index>
    <update handle="customer_account"/>
    ...
</oauth2_client_index>
```

调试日志显示 `handles` 列表里**只有** `xfeoauth2_client_index`，**没有** `oauth2_client_index`，更**没有** `customer_account`。这证明：

- Magento 在按正确名字找 handle
- 我声明的 `<oauth2_client_index>` 永远不被 merge
- 整个 `xfeoauth2_customer.xml` 在 `xfeoauth2_client_index` handle 下等于空文件
- `<update handle="customer_account"/>` 也跟着没生效

### 第二次错误归因

最初以为"rwd 主题下 `<update>` 机制坏了"，于是把 9 条链接全部塞进 controller 兜底。功能上能跑，但**完全绕开了 Magento 1 layout 系统**，controller 持有了所有模块的导航数据，违反"各模块声明自己链接"的设计原则。

**实际上** rwd 主题下 `<update>` 机制**是好的**，问题从来不在主题。

## 决策

**采用「layout XML 正确 handle 名 + `<update handle="customer_account"/>` 标准做法」，controller 只保留最小兜底**。

### 修改点

#### 1. 三个主题的 `xfeoauth2_customer.xml` 把 handle 名从 `oauth2_*` 改为 `xfeoauth2_*`

```xml
<!-- ✅ 改：handle 名字 = router name + controller + action -->
<xfeoauth2_client_index>
    <update handle="customer_account"/>
    <reference name="root">
        <action method="setHeaderTitle" translate="title" module="xfeoauth2"><title>My API Clients</title></action>
    </reference>
    <reference name="content">
        <block type="page/html_wrapper" name="my.account.wrapper" translate="label">
            <label>My Account Wrapper</label>
            <action method="setElementClass"><value>my-account</value></action>
        </block>
    </reference>
    <reference name="my.account.wrapper">
        <block type="xfeoauth2/customer_oauth2Client" name="customer.oauth2.client.list"
               template="xfeoauth2/customer/oauth2client/list.phtml"/>
    </reference>
</xfeoauth2_client_index>
```

`xfeoauth2_client_new` 同理。

#### 2. Controller 简化：删除 addLink 兜底，只留 setTemplate + wrapper 兜底

```php
protected function _prepareOAuth2Layout($pageTitle, $blockName)
{
    $this->loadLayout();
    $layout = $this->getLayout();

    // (1) 强制 2columns-left 根模板
    $root = $layout->getBlock('root');
    if ($root) {
        $root->setTemplate('page/2columns-left.phtml');
        $root->setHeaderTitle($this->__($pageTitle));
    }

    // (2) wrapper 兜底（exp5 主题会重声明 <customer_account> 把 wrapper 吞掉）
    $wrapper = $layout->getBlock('my.account.wrapper');
    if (!$wrapper) {
        $layout->getBlock('content')->insert(
            $layout->createBlock('page/html_wrapper', 'my.account.wrapper')
                ->setElementClass('my-account')
        );
    }

    // (3) 挂 OAuth2 列表/表单 block
    $template = $blockName === 'new'
        ? 'xfeoauth2/customer/oauth2client/new.phtml'
        : 'xfeoauth2/customer/oauth2client/list.phtml';
    $newName  = 'customer.oauth2.client.' . $blockName;
    if ($layout->getBlock($newName)) {
        $layout->removeBlock($newName);
    }
    $block = $layout->createBlock('xfeoauth2/customer_oauth2Client', $newName, array('template' => $template));
    $layout->getBlock('my.account.wrapper')->append($block);

    $this->_initLayoutMessages('customer/session');
    $layout->getBlock('head')->setTitle($this->__($pageTitle));
}
```

#### 3. `<customer_account>` 链接声明（保留 base/rwd/exp5 三个主题的现有写法）

`xfeoauth2_customer.xml` 的 `<customer_account>` 节点里**只声明 OAuth2 自己的链接**：

```xml
<customer_account>
    <reference name="customer_account_navigation">
        <action method="addLink" translate="label" module="xfeoauth2">
            <name>oauth2_clients</name>
            <path>oauth2/client/index</path>
            <label>My API Clients</label>
        </action>
    </reference>
</customer_account>
```

其他模块的链接（Account Dashboard / My Orders / My Wishlist / …）由它们自己的 layout XML 在 `<customer_account>` handle 下声明，**不需要** XFE_OAuth2 模块关心。

## 链接来源原则（最终）

`customer_account_navigation` block 是 Magento 1 的**共享容器**，由各业务模块通过 layout XML 在 `<customer_account>` handle 下 `addLink` 填充。**`XFE_OAuth2` 只声明自己的链接**，与 L4（Controller）层职责保持一致。

| 链接 | 声明位置 |
|------|---------|
| Account Dashboard / Information / Address Book | `app/design/frontend/{base,rwd,exp5}/default/layout/customer.xml` |
| My Orders | `…/sales.xml` |
| My Wishlist | `…/wishlist.xml` |
| Newsletter Subscriptions | `…/newsletter.xml` |
| My Product Reviews | `…/review.xml` |
| My Tags | `…/tag.xml` |
| My Downloadable Products | `…/downloadable.xml` |
| My Applications | `…/oauth.xml`（Mage_Oauth 模块） |
| **My API Clients** | **`app/design/frontend/{base,rwd,exp5}/default/layout/xfeoauth2_customer.xml` `<customer_account>`** |

`<xfeoauth2_client_index>` 通过 `<update handle="customer_account"/>` 继承整张 nav。

## 备选方案（已弃）

### 方案 A：`<update handle="customer_account"/>` + 错 handle 名（第一版）

```xml
<oauth2_client_index>    <!-- ❌ 错：应该是 xfeoauth2_client_index -->
    <update handle="customer_account"/>
    ...
</oauth2_client_index>
```

- **弃用原因**：handle 名错，整个节点永不 merge。

### 方案 B：Controller 全量 addLink 9 条（中间版）

- **优点**：功能能跑
- **缺点**：controller 持有所有模块的导航数据，违反 L4 层职责与"各模块声明自己链接"原则
- **弃用原因**：根因已找到，纯 XML 方案完全够用

### 方案 C：纯 layout XML 错 handle 名 + Controller 全部兜底（最糟中间态）

- 已识别为反模式，回归方案 B

## 后果

- **正面**：
  - layout XML 与 Magento 1 标准一致，无特殊逻辑
  - controller 干净，职责回归 L4（只做请求/响应转换与最小兜底）
  - 各模块加/减/改导航项时**自动生效**，不需要同步 XFE_OAuth2 模块
  - rwd/base/exp5 三主题统一行为
- **负面**：
  - 仍然保留 controller 里 `setTemplate` + `wrapper` 兜底（防御性，exp5 主题会重声明 `<customer_account>`）
  - 如果未来有第二个 frontName 不同的 router 也要做相同事情，需要在那个模块里同样写 `<that_router_client_index>` handle

## 受影响文件

| 路径 | 改动 |
|------|------|
| `app/design/frontend/base/default/layout/xfeoauth2_customer.xml` | `<oauth2_client_index>` / `<oauth2_client_new>` → `<xfeoauth2_client_index>` / `<xfeoauth2_client_new>` |
| `app/design/frontend/rwd/default/layout/xfeoauth2_customer.xml` | 同上 |
| `app/design/frontend/exp5/default/layout/xfeoauth2_customer.xml` | 同上 |
| `app/code/community/XFE/OAuth2/controllers/ClientController.php` | `_prepareOAuth2Layout()` 删除 addLink 兜底，只保留 `setTemplate` + `my.account.wrapper` 兜底 |

## 调试代码（已清理）

为定位问题，在 controller 临时加过：

- `_writeDebug()` 写日志到 `var/log/oauth2_debug.log`
- `_injectDebugIntoResponse()` 把日志渲染到 HTML 页面顶部

问题解决后已全部清掉。`var/log/oauth2_debug.log` 已删除。

## 验证步骤

1. `rm -rf var/cache/*`
2. 访问 `/oauth2/client/index/`
3. 断言响应 HTML 包含：
   - `<div class="my-account">…</div>` （my.account.wrapper 渲染）
   - `<ul class="nav">…My API Clients…</ul>` （customer_account_navigation 渲染）
   - 9 条链接都在：Account Dashboard / Account Information / Address Book / My Orders / My Wishlist / Newsletter Subscriptions / My Downloadable Products / My Product Reviews / My Tags / My Applications / My API Clients
4. 同样访问 `/oauth2/client/new/` 验证

## 未来工作

- 在测试套件中加一个集成测试：访问 `/oauth2/client/index` 断言响应 HTML 包含全部 9 条链接与 `My API Clients` 链接
- 任何新的 XFE 前端模块如果挂在 `customer_account` 旁边，**handle 名也要用 router name 而不是 frontName**，避免重蹈本次覆辙
- 考虑在 `AGENTS.md` 加一条"layout handle 必须用 router name"的规则

## 修订记录

| 日期 | 变更 |
|------|------|
| 2026-09-09 | 初版：rwd 主题下 `<update>` 不工作，controller 主动 addLink 单条（仅 My API Clients） |
| 2026-09-09 | 升级：业务要求显示完整导航，controller 主动 addLink 全部 9 条 |
| 2026-09-09 | **根因修复**：handle 名错（`oauth2_*` 应为 `xfeoauth2_*`），controller 简化只留 setTemplate + wrapper 兜底 |
