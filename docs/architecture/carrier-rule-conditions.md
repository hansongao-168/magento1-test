# 承运商规则条件描述与订单创建时间

## 1. 目的

承运商规则管理页的规则列表必须同时展示规则描述和条件描述，并在条件选择器中增加“订单创建时间”条件。

本次改动属于 `XFE_Carrier` 模块内部行为扩展，不新增数据库字段，不改变条件树的持久化结构，也不改变其他模块对 `XFE_Carrier` 的调用方式。

## 2. 设计范围

### 2.1 规则列表

以下三个后台规则列表统一增加“条件描述”列：

- 承运商编辑页的规则列表：`XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_Rules_Grid`
- 承运商账号编辑页的规则列表：`XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_Account_Rules_Grid`
- 承运商 FTP 账号编辑页的规则列表：`XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_FtpAccount_Rules_Grid`

列使用同一行格式化入口，不在各个 Grid 内复制条件解析逻辑。

### 2.2 条件描述格式

条件描述由规则模型从 `conditions_data` 读取并生成：

- 顶层条件组之间使用 `全部条件组（AND）` / `任意条件组（OR）` 的关系表达；当前规则引擎实际语义为各顶层组全部满足。
- 单个条件格式为：`属性中文名 操作符中文名 值`，例如 `目的地国家 等于 US`。
- `is_null` 和 `is_not_null` 不显示值。
- 条件值为空但不是空值操作符时显示为空字符串。
- 空条件树显示“匹配所有”。
- 未知属性使用属性代码原文，避免后台静默丢失规则信息。

`getConditionsDescription()` 是模型层的公开读取接口，仅负责展示格式化，不承担条件求值。

## 3. 订单创建时间条件

新增条件属性：`order_created_at`，显示名称为“订单创建时间”。

### 3.1 编辑器契约

- 属性元数据类型为 `datetime`。
- 条件编辑器提供文本值输入，格式为 `YYYY-MM-DD HH:mm:ss`。
- 可用操作符：等于、不等于、大于、大于等于、小于、小于等于、范围、为空、不为空。
- `datetime` 不套用纯数字 `between` 的旧 `x~y` 规则；若当前共享编辑器只支持字符串和数字输入框，则至少支持 ISO 时间字符串比较，并将该限制记录在模块架构文档中。

### 3.2 订单上下文契约

`XFE_Carrier_Model_Service_Rule_OrderContextBuilder` 增加：

```php
'order_created_at' => $this->_order->getCreatedAt()
```

值使用订单对象的原始 `created_at` 字符串，求值器按时间戳进行可比较运算。报价上下文不提供伪造的订单创建时间；缺少该值时条件不匹配。

### 3.3 评估契约

- `order_created_at` 可使用时间比较操作符。
- 两个时间值必须都能解析为有效时间；无法解析的时间值不匹配比较操作符。
- `is_null` 与 `is_not_null` 继续依据上下文是否存在 `order_created_at` 判断。
- 规则条件表继续使用现有 `attribute`、`operator`、`value` 字段，不需要数据库升级脚本。

## 4. 依赖与边界

- 规则模型负责读取和格式化已持久化条件树。
- 订单上下文构建器负责把 `Mage_Sales_Model_Order` 的业务字段映射到 `MatchContext`。
- 求值器负责时间语义比较，不读取数据库。
- Grid 仅请求条件描述，不直接读取其他模块的私有数据。

### 4.1 规则 Grid 的条件树加载

Magento 的 Db collection 加载后**不会**对每行调用 `afterLoad()`，因此资源模型的 `_afterLoad()`（负责生成 `conditions_data`）在集合行上不会执行。若不处理，规则列表的“条件描述”列会对所有规则显示“匹配所有”。

- 规则集合新增公开方法 `XFE_Carrier_Model_Resource_Rule_Collection::loadConditions()`：遍历已加载行并调用 `$rule->afterLoad()` 补全 `conditions_data`。
- 三个规则 Grid（承运商/账号/FTP账号）在 `_afterLoadCollection()` 中调用该方法（集合为空或非规则集合时跳过）。
- 该方法**刻意做成 opt-in**，不挂进集合 `_afterLoad()`：规则求值器 `Rule\Resolver` 在每次解析时都加载该集合并自行读取条件树，若集合自动加载条件会导致每次解析重复查询。

### 4.2 保存链路中的 `groups_data_hidden`

Magento 的原生 `form.submit()`（`varienForm.submit()` 内部调用）**不会**触发 submit 事件，因此条件构建器 JS 里的 `editForm.observe('submit', updateHiddenField)` 不会在保存按钮被点击时运行。`groups_data_hidden` 必须在每次条件变更、**以及 init 阶段**就被写入，提交时才能带上合法 JSON。

`skin/xfe_shippingrule/js/condition-builder.js` 的 `initConditionBuilder` 在默认分支后追加了一段安全网：`addConditionGroup()` 跑完后若 `groups_data_hidden` 仍无值（容器 `condition-groups-container` 尚未被 `varienTabs` 移入 `edit_form`），主动注册一个空 group 并 `updateHiddenField()`。这样**新建规则的 `groups_data` 永远是非空且合法的 JSON**，`saveRuleAction` / `_afterSave` 一定能把条件树落库。

### 4.3 模型层兜底

`XFE_Carrier_Model_Carrier_Rule::getConditionsData()` 末尾：当 `conditions_data` 为空 + 规则有 id 时主动 `$this->afterLoad()` 重新拉条件树。任何调用 `getConditionsDescription()` 的代码路径——包括 Grid 集合行、未来的 Grid、单条加载、任何 service——都能拿到真实条件，无需依赖 Grid 的批量预热，也不依赖缓存。这一层让"有条件就显示真实描述"成为模型的固有行为。

### 4.4 共享条件编辑器只接收由 `XFE_Carrier_Helper_Data::getConditionAttributeOptions()` 和 `getAttributeTypeMap()` 下发的属性契约；若编辑器需要真正的日期选择控件，应单独设计并避免影响 `XFE_ShippingRule` 等其他消费者。

## 5. 验证要求

- 覆盖三处规则 Grid 的条件描述列配置，或至少覆盖承运商主列表并对复用列做静态检查。
- 覆盖条件描述的普通条件、嵌套条件组、空值操作符、空树和未知属性。
- 覆盖 `order_created_at` 在订单上下文中的映射。
- 覆盖时间等于、前后比较、范围和非法时间值。
- 执行新增/修改 PHP 文件的 `php -l`；如有可用 PHPUnit 入口，执行相关 Carrier 测试。

