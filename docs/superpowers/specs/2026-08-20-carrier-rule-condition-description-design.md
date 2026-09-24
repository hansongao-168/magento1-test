# 承运商规则条件描述与订单创建时间设计

## 目标

让承运商规则列表显示条件的可读描述，并允许管理员按订单 `created_at` 创建规则条件。

## 方案

采用方案 A：在现有 `XFE_Carrier_Model_Carrier_Rule` 中提供条件描述格式化方法，在三处 Carrier Grid 中复用；订单上下文构建器增加 `order_created_at`，求值器增加时间比较语义。保持现有条件表不变。

## 行为契约

- 条件描述从 `conditions_data` 递归生成，支持嵌套组。
- 空树显示“匹配所有”。
- `is_null` / `is_not_null` 不输出值。
- `order_created_at` 仅从订单上下文获取，不从报价上下文伪造。
- 时间输入采用 `YYYY-MM-DD HH:mm:ss`；无法解析的时间不参与时间比较。

## 变更边界

修改 `XFE_Carrier` 的规则模型、订单上下文构建器、规则求值器、Carrier Helper、三个规则 Grid 和相关测试。先更新本设计文档与架构文档，再修改代码。

