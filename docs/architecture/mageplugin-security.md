# XFE_MagePlugin 后台安全与权限扩展架构

> 隶属模块：`XFE_MagePlugin`。本文档描述本需求新增的 4 项功能架构，遵循 `AGENTS.md` 单向依赖 4 层原则。

## 概述

在 `XFE_MagePlugin` 模块内新增后台安全与数据脱敏能力，全部通过 `<rewrite>` / 事件 observer 实现，**不修改 `app/code/core/`**。

- 特殊账号判定：`admin_user.user_id` 为 1 和 2 的账号享受完整权限。
- 其余账号受脱敏 / 权限限制。

## 分层映射

| 分层 | 落地形态 | 说明 |
|------|---------|------|
| L1 Domain | `Model/Account/Privileged.php` | 纯判定逻辑：是否特殊账号（user_id 1/2）、是否可短信加 IP（1~5） |
| L2 Repository | `Model/Resource/...` | IP 白名单、短信会话、用户手机号的数据访问 |
| L3 Service | `Model/Service/IpGuard.php`、`Model/Service/Sms.php`、`Model/Service/TrackingDetector.php` | 用例编排：登录 IP 判定、短信验证、承运商跟踪判定 |
| L3 Service | `Model/Sms/Gateway/` | 短信网关抽象（接口 + 配置驱动） |
| L4 Controller/Observer | `Model/Observer.php`、controllers | 事件入口 / HTTP 请求入口 |
| Block 重写 | `Block/Adminhtml/...` | 订单 grid、客户 grid、客户地址、订单详情 view 的展示控制 |

## 模块一：登录 IP 白名单 + 短信验证

### 数据表
- `xfe_mageplugin_admin_ip_whitelist`：user_id、ip、is_auto_added（是否短信自动添加）、created_at。
- `xfe_mageplugin_admin_sms`：user_id、phone、code（哈希存储）、ip、expires_at、verified、created_at。
- 用户手机号：`xfe_mageplugin_admin_phone`（user_id 唯一）。

### 流程
1. 登录 POST → `Mage_Admin_Model_User::authenticate()` dispatch `admin_user_authenticate_before`。
2. Observer 读取当前 IP，查该用户 IP 白名单。
   - 命中 → 放行（不拦截）。
   - 未命中且用户为 1~5 → 拦截登录，跳转短信验证流程（发验证码 → 校验 → 写入白名单 → 重新登录）。
   - 未命中且用户非 1~5 → 直接拒绝登录（提示需在白名单 IP 登录）。
3. 短信网关：`Model/Sms/Gateway/SmsGatewayInterface` + `Model/Sms/Gateway/Log`（测试模拟）+ 配置驱动后续接入真实服务商。

## 模块二：订单导出脱敏

- 重写 `adminhtml/sales_order_grid`（`Block/Adminhtml/Sales/Order/Grid.php`）。
- `_prepareColumns()` 新增寄/收件人完整地址列：公司、姓名、详细地址、电话、邮箱、邮编、城市（需 join `sales/order_address`）。
- 导出时（`getCsvFile` / `getExcelFile`）：特殊账号导出完整列；非特殊账号只导出邮编+城市，其余地址列填充空值或整列移除。

## 模块三：客户地址查看/导出权限

- 重写 `adminhtml/customer_grid`：非特殊账号移除 `name`/`email`/`Telephone` 列 + 移除导出按钮。
- 重写 `adminhtml/customer_edit_tab_addresses`（或模板）：非特殊账号仅渲染邮编/城市/国家。
- 防复制：CSS 遮挡 + `user-select:none` + 阻止 `copy` 事件。

## 模块四：退款控制

- `Model/Service/TrackingDetector.php`：判定订单 `sales_flat_shipment_track` 是否有承运商跟踪记录。预留 `isSelfScanned()` 扩展方法（另一项目接入点，当前返回 false）。
- 重写 `adminhtml/sales_order_view`：有承运商跟踪记录时不移除/不添加 Credit Memo 按钮。
- 退款 controller 层拦截（防绕过 UI）。

## 依赖方向
- Controller/Observer → Service → Repository/Domain；Block 重写通过 config 注册。
- 无跨模块私有访问、无循环依赖、无 `Model -> Block` 反向引用。
