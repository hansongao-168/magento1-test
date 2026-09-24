# 0028. GLS PoD "无凭证"业务异常分层处理

- 状态：Accepted
- 日期：2026-09-21
- 决策者：AI 助手（经用户确认）
- 关联文档：
  - [`../logistic-gls-pod-diagnostics.md`](../logistic-gls-pod-diagnostics.md) — `ZWLNVW8X` 实测排查报告
  - [`../logistic-gls-api.md`](../logistic-gls-api.md) — GLS POD 接口契约（同步更新解析策略）
  - [`../logistic-architecture.md`](../logistic-architecture.md) — 模块分层（同步更新异常位置）
  - ADR 0019 — PodService 主流路径接入注入
  - ADR 0020 — GLS 凭据 data-upgrade 迁移
  - ADR 0021 — PodService 注入最终态（Proposed，未落地）

## 背景

实测运单 `TrackID = ZWLNVW8X` 触发 GLS `POST /parcelpod` 返回 `404 Not Found`，header 含机器可读错误码 `Error: NO_POD_IMAGE_FOUND`。这是**业务结论**（运单无 Signature/Geo PoD 图片），不是端点/资源名配错，也不是调用方请求错误。但当前代码：

1. **`GlsGateway::_extractGlsErrorMessage()` 按候选顺序取第一个命中**：候选里 `message`（人类可读英文）在 `error`（机器可读枚举码）之前，导致 `NO_POD_IMAGE_FOUND` 永远读不到。上层只能拿英文整句做模糊匹配。
2. **`GlsApiException::isClientError()=true` 把这条 404 一刀切归为"调用方请求错误"**：上层据此判定"重试无益、请修正 TrackID"，但实际上是"运单正常、只是没凭证"。
3. **404 语义过载未拆分**：单看 HTTP 状态码无法区分"端点配错"和"无凭证"。
4. **`XFE_Logistic_Domain_PodResult` 没有"凭证类型/可用性"字段**：上层拿到值对象即"已拿到文件"，无法表达"无凭证"业务状态。

实测响应（关键 header，2026-09-21 回收）：

```text
HTTP/1.1 404 Not Found
Content-Length: 0
Message: PoD (Proof of Delivery) document cannot be created for parcel with TrackID "ZWLNVW8X", it has no Signature/Geo PoD image.
Error: NO_POD_IMAGE_FOUND
Args: ["ZWLNVW8X"]
ServerExecutionTime: 41
```

## 决策

**重构错误响应处理：L2 提取协议信息 → L3 业务分类 → L1 新增专门异常**。

### 1. 分层职责再切分

| 层 | 职责 | 不做 |
|----|------|------|
| **L2 GlsGateway** | 从响应 header 提取 `Error`（机器可读）+ `Message`（人类可读），二者作为协议层契约携带在异常上 | 不做"什么算业务错误 / 客户端错误"的判断 |
| **L3 PodService** | 维护"业务错误码白名单"（当前仅 `NO_POD_IMAGE_FOUND`），命中即抛专门异常；其余维持 `GlsApiException` | 不直接解析 HTTP header |
| **L1 Domain** | 新增 `PoDNotAvailableException` 专门承载"无凭证"业务状态 | — |

**关键原则**：业务错误码判定（白名单匹配）属于**业务规则**，必须落在 L3；L2 只做协议层 header 提取。

### 2. GlsApiException 扩展

构造器增加 `$errorCode` 参数（默认空字符串），异常可携带 GLS 机器可读错误码：

```php
public function __construct($message = '', $httpCode = 0, $errorCode = '', ?Exception $previous = null)
```

- `getErrorCode(): string` 新增
- **不**新增 `isBusinessError()` 等业务方法——避免在 L1 通用异常上做业务分类（越权）
- 已有的 `isClientError()` / `isServerError()` 维持 4xx/5xx 判定不变

### 3. GlsGateway 重构

`_extractGlsErrorMessage()` 改为返回结构化数组：

```php
protected function _extractGlsErrorInfo(array $headers): array
// 返回 ['code' => string, 'message' => string]
```

- 优先读 `Error` header（小写归一化后键为 `error`），作为机器可读枚举码（`code`）
- 回退读 `Message` header（键为 `message`），作为人类可读文案（`message`）
- 都没有 → 二者皆空字符串

`requestParcelPod()` 在非 2xx 时把 `code` 透传给 `GlsApiException` 构造器第三个参数。

### 4. GlsApiConfig 常量扩展

新增以下常量（避免魔法字符串）：

```php
const RESPONSE_HEADER_ERROR         = 'Error';
const RESPONSE_HEADER_MESSAGE       = 'Message';
const RESPONSE_HEADER_ARGS          = 'Args';
const RESPONSE_HEADER_SERVER_EXECUTION_TIME = 'ServerExecutionTime';

const ERROR_CODE_NO_POD_IMAGE_FOUND = 'NO_POD_IMAGE_FOUND';

const POD_NOT_AVAILABLE_ERROR_CODES = array(
    self::ERROR_CODE_NO_POD_IMAGE_FOUND,
);
```

- `POD_NOT_AVAILABLE_ERROR_CODES` 为**当前白名单**，仅 `NO_POD_IMAGE_FOUND` 一项
- 取得 GLS 完整 `Error` 枚举（待 B1 答复）后**仅追加**，不改既有项
- **不要**在 L2 用此白名单——白名单是 L3 的业务规则

### 5. L1 新增 PoDNotAvailableException

新文件：`app/code/community/XFE/Logistic/Domain/Exception/PoDNotAvailableException.php`

```php
final class XFE_Logistic_Domain_Exception_PoDNotAvailableException extends Exception
```

- **不**继承 `GlsApiException`，避免被 `isClientError()` 误归类
- **不**继承 `Mage_Core_Exception`，避免 Magento 默认错误页吞掉业务语义
- 字段：`trackId`、`errorCode`、`humanMessage`
- 默认中文消息：`"GLS 运单 {trackId} 暂无签收凭证（{errorCode}），请稍后再试或联系 GLS 客服"`
- `getTrackId() / getErrorCode() / getHumanMessage()` 三个 getter

### 6. PodService 业务分类

`getProofOfDelivery()` 改造：

```php
try {
    $podItem = $this->_gateway->requestParcelPod(...);
} catch (XFE_Logistic_Domain_Exception_GlsApiException $e) {
    $config = 'XFE_Logistic_Domain_Constant_GlsApiConfig';
    $errorCode = $e->getErrorCode();
    if ($errorCode !== '' && in_array($errorCode, $config::POD_NOT_AVAILABLE_ERROR_CODES, true)) {
        throw new XFE_Logistic_Domain_Exception_PoDNotAvailableException(
            $trackId, $errorCode, $e->getRawMessage()
        );
    }
    throw $e;
}
```

**`GlsApiException` 上额外暴露原始 Message**：

为了构造新异常时能携带 GLS 原文 Message（便于日志/排障），`GlsApiException` 暴露 `getRawMessage(): string`（人类可读原文）。原 `getMessage()` 是构造时已拼好"中文前缀 + HTTP 状态码 + 原文"的版本，对外行为不变。

### 7. 404 语义分流验证

| 场景 | HTTP | Error header | PodService 抛出 |
|------|------|--------------|------------------|
| 业务：无凭证（`ZWLNVW8X`） | 404 | `NO_POD_IMAGE_FOUND` | `PoDNotAvailableException` |
| 配置：端点/资源名配错 | 404 | （空） | `GlsApiException`（维持原行为） |
| 请求：TrackID 非法 | 400 | （空或 `BAD_REQUEST` 等） | `GlsApiException` |
| 凭据：401 | 401 | （空或 `UNAUTHORIZED`） | `GlsApiException` |
| 服务端：5xx | 5xx | （可能带 `Error`） | `GlsApiException`（5xx 不归业务白名单） |

### 8. 测试覆盖

| 测试文件 | 覆盖 |
|---------|------|
| `Test/Unit/GlsGatewayErrorHeaderTest.php`（新建） | `_extractGlsErrorInfo` 5+ 断言：Error 优先 / Message 回退 / 二者皆空 / 大小写归一化 / 不命中未知 header |
| `Test/Unit/PodServiceClassificationTest.php`（新建） | `NO_POD_IMAGE_FOUND` → `PoDNotAvailableException`；其他 errorCode → rethrow `GlsApiException`；空 errorCode → rethrow；HTTP 5xx + 有 Error → rethrow（不归业务白名单） |

注册到 `tests/php/run-tests.php`（保持现有"无 Mage 引导"模式，断言 ≥ +30）。

### 9. Api/PodApiInterface PHPDoc 更新

`getProofOfDelivery()` 的 PHPDoc 异常说明新增：

```
@throws XFE_Logistic_Domain_Exception_PoDNotAvailableException
        当 GLS 业务错误码命中"无凭证"白名单（如 NO_POD_IMAGE_FOUND）时抛出，
        表示该运单暂无签收凭证（业务结论，非调用方错误）
```

## 备选方案

### A. ✅ L2 抛异常 + L3 业务分类 + L1 新异常（本决策）

- 优点：严格遵守 AGENTS.md 单向依赖 4 层；L2 不含业务判断；测试可在 L3 mock 异常路径
- 缺点：`GlsApiException` 需扩展字段；改动面较大
- 采纳

### B. L2 直接抛 `PoDNotAvailableException`

- 优点：少一层 catch
- 缺点：L2 含业务判断（"什么算业务错误码"），违反分层；维护 L2 时必须懂业务
- 否决

### C. 在 `GlsApiException` 上新增 `isBusinessError()` 方法

- 优点：少一个 L1 异常类
- 缺点：在 L1 通用异常上做业务分类；`GlsApiException` 本是"协议层异常"通用基类；新业务码每次都要改基类
- 否决

### D. 错误码白名单放 `GlsApiConfig` 但被 L2 读

- 优点：常量集中
- 缺点：L2 越权读业务白名单
- 否决

## 后果

### 正面

- `NO_POD_IMAGE_FOUND` 等机器可读错误码被正确捕获，上层不再依赖英文整句模糊匹配
- 404 双重语义（端点配错 vs 无凭证）在代码层显式分流
- `PoDNotAvailableException` 提供业务级异常类型，上层（L4 / UI）可针对性给出中文提示，无需判 HTTP 状态码
- L2/L3 职责边界清晰：L2 只做协议层提取，L3 业务分类，未来增错误码只需追加白名单
- 测试不依赖 Magento 引导（与 ADR 0018 一致），可在 CI 跑

### 负面

- `GlsApiException` 构造器新增第 3 参数：所有 `new XFE_Logistic_Domain_Exception_GlsApiException(...)` 调用点需检查兼容性
  - 缓解：第 3 参数默认空字符串，向后兼容；现有调用方零改动
- `GlsApiException::getMessage()` 与新增 `getRawMessage()` 双胞胎：API 上略冗余
  - 缓解：`getMessage()` 维持原语义（已拼中文前缀），新方法只用于内部构造新异常时取原文
- 业务错误码白名单暂只一项（`NO_POD_IMAGE_FOUND`），未穷举（依赖 GLS B1 答复）
  - 缓解：白名单**可追加**，未来增错误码无需改 L2/L1

### 后续工作

1. 取得 GLS `..._API-Error-messages.pdf` 后（B1 答复），按枚举值扩展 `POD_NOT_AVAILABLE_ERROR_CODES` 白名单（仅追加）
2. 单元测试新增更多 5xx + Error header 组合验证（待 B3 答复）
3. L4 接入 UI 后，文案由 `PoDNotAvailableException` 中文消息直接渲染，无需手动翻译
4. 评估是否要把 `PoDNotAvailableException` 提升为接口（`Api/PoDExceptionInterface`）让 L4 面向接口编程——本期不做

## 实施检查清单

- [ ] ADR 0028（本文档）
- [ ] `GlsApiConfig` 新增 4 个 RESPONSE_HEADER_* 常量 + 2 个 ERROR_CODE_* 常量 + 1 个 POD_NOT_AVAILABLE_ERROR_CODES 数组
- [ ] `GlsApiException` 构造器增加 `$errorCode` 参数 + `getErrorCode()` + `getRawMessage()`
- [ ] `GlsGateway::_extractGlsErrorMessage()` 重构为 `_extractGlsErrorInfo()` 返回结构化数组
- [ ] `GlsGateway::requestParcelPod()` 透传 errorCode 给异常
- [ ] 新建 `Domain/Exception/PoDNotAvailableException.php`
- [ ] `PodService::getProofOfDelivery()` catch + 白名单分类
- [ ] `Api/PodApiInterface` PHPDoc 异常说明新增
- [ ] 新建 `Test/Unit/GlsGatewayErrorHeaderTest.php`
- [ ] 新建 `Test/Unit/PodServiceClassificationTest.php`
- [ ] `tests/php/run-tests.php` 注册新套件
- [ ] 跑 `php -l` 全部 0 syntax error
- [ ] 跑 `php tests/php/run-tests.php` 全部通过（断言 ≥ +30）
- [ ] 更新 `logistic-gls-api.md` §3.3 反映新解析策略
- [ ] 更新 `logistic-architecture.md` §3 异常位置 + §2 分层图
- [ ] 更新 `task_plan.md` + `progress.md` 记录本次任务

## 注意事项

- **L2 越权风险**：`POD_NOT_AVAILABLE_ERROR_CODES` 常量虽然定义在 L1，但**只能**被 L3 引用；L2 (`GlsGateway`) 不得使用。提交前自检（AGENTS.md §5.3）需 `grep "POD_NOT_AVAILABLE_ERROR_CODES" GlsGateway.php` 应为 0 命中。
- **`PoDNotAvailableException` 不继承 `Mage_Core_Exception`**：L1 Domain 不依赖 Magento（遵守 `logistic-architecture.md` §2）。如需 Magento 框架捕获，可用 `try/catch` 显式捕获该类型。
- **白名单扩展纪律**：GLS 给出新业务错误码后，**只追加**到 `POD_NOT_AVAILABLE_ERROR_CODES`，不要删除既有项（避免回归）。
- **错误码大小写**：GLS 实际响应 header 名经 `ucwords(strtolower())` 显示为 `Message` / `Serverexecutiontime`，但 `CURLOPT_HEADERFUNCTION` 回调里我们用 `strtolower` 归一化为 `message` / `serverexecutiontime` 后存到数组（小写键访问一致）。
- **L1 异常命名空间**：`Domain/Exception/` 下与 `GlsApiException.php` 同级，命名规范 `XFE_Logistic_Domain_Exception_*`。
- **测试无 Mage 引导**：现有 Logistic 测试 `MigrateGlsCredentialsTest.php` 用 `class_exists('Mage', false)` 防御检查 + mock stdClass 替代 Varien_Db；新测试沿用此模式（不引导 Mage，仅 stub `Mage::throwException` / `Mage::getModel`）。