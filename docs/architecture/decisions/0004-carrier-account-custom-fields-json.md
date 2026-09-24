# 0004. 承运商账号自定义字段: JSON 键值对 vs 真 EAV vs 独立扩展表

- 状态:**Accepted**
- 日期:2026-09-02
- 决策者:hanson.gao + AI 助手

## 背景

`XFE_Carrier` 现有的 `xfe_carrier_carrier_account` 与
`xfe_carrier_carrier_ftp_account` 两张账号表只有**固定列**(`account_name` /
`api_key` / `endpoint_url` 等)。不同承运商(顺丰 / FedEx / DHL / EMS / GLS)的
私有参数差异很大,需要在账号级别提供"任意键值对"的扩展机制。

## 决策

采用**方案 A:单列 JSON 键值对**:

- 在两张账号表各加 1 列 `custom_fields_json TEXT NULL`,整体存 JSON 对象。
- 键值对用 `XFE_Carrier_Domain_CustomField` / `CustomFieldCollection` /
  `CustomFieldCodec`(L1 Domain)描述,内含 `label` / `type` / `value` / `options`。
- 编辑页面提供可视化"键值对编辑器"(增/删/改行,所见即所得)。
- 业务模块通过现有 Facade / Resolver 拿账号,再调 `getCustomField($key)` 读取
  单个值,**不**暴露为公开 API(由用户在 `consumer_visibility` 选择
  `internal_only`)。
- 跨模块契约靠事件 `xfe_carrier_account_custom_field_changed` 广播,载荷
  只含 `account_id` / `key` / `action`,**不**含 `value`(防凭据泄露)。

## 备选方案

### 方案 B: 真正的 EAV(5 张表 + attribute set)

- 仿 `catalog_product` 的 EAV,新建 `xfe_carrier_account_eav_attribute` /
  `xfe_carrier_account_eav_varchar` / `..._int` / `..._decimal` / `..._text`。
- 优点:支持 `WHERE` / `ORDER BY` / 索引,查询能力强。
- 缺点:实现复杂(5 张表 + 5 个 Resource Model + 5 个 Backend Model);
  Magento 1 EAV 抽象层本身有历史包袱;本场景**不需要**按扩展字段做查询/排序
  ——所有"按扩展字段过滤"都是业务模块自己的事,不需要 Carrier 模块替它做。

### 方案 C: 每个字段一列(sparse columns)

- 顺丰加 `sf_account_abc`、FedEx 加 `fedex_label_type`、DHL 加 `dhl_product` ...
- 优点:能加索引,支持范围查询。
- 缺点:**表被严重污染**,加新承运商必须 ALTER TABLE;Magento Resource setup
  每次升级脚本都得回头补列;命名空间冲突难解。

### 方案 D: 独立扩展表(1:N)

- 新建 `xfe_carrier_account_custom_field(account_id, key, value)`,每行一个字段。
- 优点:可索引、可 JOIN。
- 缺点:增删字段要 4 次 IO;业务模块需要 join 才能一次拿到全部;前端编辑
  器要处理"先有 key 后有 value"的中间态;反而比 JSON 复杂。

### 方案 E: system config 的 `accounts_json`

- 沿用 `XFE_DocumentUpload` 的做法,在 system config 里存一个 `accounts_json`。
- 优点:不动表结构。
- 缺点:违反 [`carrier-facade.md`](../carrier-facade.md) 的"统一挑账号"原则;
  管理员要为同一承运商在多个模块重复配置;审计困难。

## 后果

### 正面

- ✅ **演进成本低**:后续加新承运商 / 新参数,只需要改前端编辑器,不动表结构。
- ✅ **实现简洁**:3 个 L1 Domain 类 + 1 个 Service,无新表、无新 Resource Model。
- ✅ **不破坏现有数据**:`custom_fields_json` 默认为 `NULL`,历史账号不受影响。
- ✅ **编辑器所见即所得**:管理员在页面上即可增/删字段,无需了解 JSON。
- ✅ **凭据保护**:`getCustomField($key)` 返回的是 `value` 字段,日志/事件不会
  把它原文泄露。

### 负面

- ⚠️ **不能按扩展字段做 SQL 查询/排序**:必须用 JSON 函数(`JSON_EXTRACT`)
  或读全表;MySQL 5.7+ 才支持 JSON 函数;Magento 1 一般是 MySQL 5.6,需
  要应用层做内存过滤。
- ⚠️ **JSON 校验**:`custom_fields_json` 是 `TEXT`,DB 不知道里面是 JSON;
  L1 Domain 的 Codec 负责校验,Controller 必须走 Service 不能绕过。
- ⚠️ **导入导出**:CSV 模板新增 `custom_fields_json` 列,值是带转义的长 JSON
  字符串,管理员手工编辑 CSV 体验较差,需要强调"用导入功能"。

### 缓解

- 对负面 1:目前没有任何业务场景**需要**按扩展字段做 SQL 过滤;真到了那一天,
  可以重建为方案 D(`1:N` 扩展表),JSON 列是**可逆**的——已经存的 JSON 可以
  一次脚本拆出多行。
- 对负面 2:`Domain/Constant/CustomField.php` 把"非法 key" / "非法 type" / "
  非法 select options" 全部抛 `InvalidArgumentException`,Service 在
  `applyFromPost()` 处捕获后抛 `Mage_Core_Exception`,Controller 写入 session
  error 并回滚,不让脏数据进 DB。
- 对负面 3:CSV 模板下载页提供"JSON 字段"行,带示例值;Excel 打开长 JSON 字段
  默认会自动换行(因为是单字段),不会破坏其他列。

## 反向 / 不可逆点

- DB 列一旦加上,**没有强理由再撤回**(向下兼容即可)。
- L1 Domain 的 4 个 `TYPE_*` 常量是**公开契约**,未来要加类型(例如 `date`)
  必须走 ADR;不能默默加。
- CSV 模板的列顺序是导入兼容性的硬约束,新加列只能 append 不能 reorder。

## 验证

- `php -l` 所有新文件。
- 单元测试覆盖 Codec 往返、空集合、非法 JSON。
- 手动测试一个真实账号:加 3 个字段(text/number/select 各 1)、刷新、删 1 个、
  保存、再读出来——数据完整一致。
