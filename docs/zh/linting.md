---
title: 标记检查
locale: zh
status: stable
---

# 检查 API

```bash
php artisan api:lint
```

## 为什么需要它

本扩展包在设计上就是「静默失败」的，而这正是这个命令存在的全部理由。

控制器方法被改名后，对应的动作会返回 **404** —— 与地址写错时完全相同的 404，
因此在日志里「这个端点没了」和「有人乱请求」无法区分。无法识别的类型会被悄悄
当作 `string`。`@response 200 {UserResponse}` 若引用了从未定义的模板，就会生成
一个指向空处的 `$ref` —— 而这份规范依然能通过校验。

以上每一种情况，应用都能正常启动，测试全绿，而错误最终落到 API 使用者身上。

`api:lint` 用与 OpenAPI 生成器完全相同的解析器读取路由表和 docblock 标记，
把那些本会悄无声息的问题报出来。

## 选项

| 选项 | 作用 |
|---|---|
| `--api-version=v1` | 只检查某一个版本，而非全部 |
| `--strict` | 警告也视为失败，而不仅仅是错误 |
| `--unrouted` | 额外报告没有任何动作指向的公有控制器方法 |
| `--json` | 以 JSON 输出报告 |

存在错误时退出码为 `1`；加上 `--strict` 时，存在警告也为 `1`。因此它可以直接
作为 CI 步骤：

```yaml
- run: php artisan api:lint --strict
```

`--unrouted` 默认关闭：控制器里可能有从未打算作为端点的辅助方法，而对这些方法
喋喋不休的检查器，最终会被人无视。命令会主动说明该项检查已跳过 —— 悄悄缩小的
检查范围会被读成「一切正常」。

## 检查内容

### 路由表

| 规则 | 级别 | 含义 |
|---|---|---|
| `action.missing-method` | 错误 | 动作指向了不存在的控制器方法。**正是那个无人察觉的 404。** |
| `action.unreachable-method` | 错误 | 方法存在，但不是公有的或者是静态的 —— 无法服务请求 |
| `action.unknown-http-method` | 错误 | 动词不在 `laravel-api.available_methods` 之内 |
| `controller.missing-class` | 错误 | `controller` 键指向不存在的类 |
| `controller.missing-key` | 错误 | 控制器条目根本没有 `controller` 键 |
| `middleware.missing-class` | 错误 | 级联中的某个中间件类不存在 |
| `api.missing-class` | 错误 | `getApiVersionList()` 把版本映射到了缺失的类 |
| `controller.unrouted-method` | 警告 | 没有任何动作指向的公有方法（仅 `--unrouted`） |

不含命名空间分隔符的中间件名不予处理：那是路由组和别名，不启动整个应用就无法
区分拼写错误与检查器未曾听说过的组。

### 标记

| 规则 | 级别 | 含义 |
|---|---|---|
| `tag.malformed` | 错误 | 标签内容无法解析，生成器会一声不响地丢弃它 |
| `tag.empty` | 警告 | 标签后面什么都没有 |
| `tag.callable-misplaced` | 警告 | 在 `@input` 之外的标签上使用了 `[method]` 形式 |
| `tag.template-misplaced` | 警告 | 在 `@output` 之外的标签上使用了 `{Template}` 形式 |
| `tag.unknown-template` | 错误 | `@input @Model` / `@output @Model[]` 引用了未定义的模板 |
| `tag.callable-missing` | 错误 | `@input [method]` 引用了控制器没有的方法 |
| `tag.unknown-type` | 警告 | 类型不在已知集合内 —— 会被悄悄当作 `string` |
| `tag.duplicate-variable` | 警告 | 同一变量声明了两次，以最后一次为准 |
| `tag.orphan-nesting` | 警告 | 有 `$address.city` 却没有声明 `$address` |
| `tag.nesting-type-mismatch` | 警告 | `$tags[].id` 的父级被声明为 `array` 以外的类型 |
| `response.malformed` | 错误 | `@response` 无法解析 |
| `response.unknown-template` | 错误 | `@response 200 {Name}` 引用了未定义的模板 |
| `response.impossible-code` | 错误 | 状态码不在 100–599 范围内 |
| `response.duplicate-code` | 警告 | 同一状态码有两个响应，以最后一个为准 |
| `security.unknown-scheme` | 错误 | `@security Name` 或动作的 `security` 键引用了未定义的方案 |
| `template.unknown-ref` | 错误 | 模板字段通过 `@Other` 引用了不存在的模板 |
| `default.unknown-variable`、`example.unknown-variable` | 警告 | 为没有 `@input` 的变量设置默认值或示例，该值会被忽略 |
| `default.malformed`、`example.malformed` | 错误 | 标签内容无法解析 |

由中间件提供的输入会被计入：为其中声明的变量写 `@default` 是合法的，不会被
报告。

## 如何阅读报告

```
v1 · user.update
  error    The action points at App\Api\Controllers\UserController::updte(), and there is no such method.  [action.missing-method]
           At runtime this answers 404 — the same 404 as a wrong URL, which is why it goes unnoticed.
  warning  @input $role: the type `enum` is unknown and becomes `string`.  [tag.unknown-type]
           Known types: string, file, number, integer, boolean, array, object.
```

定位给出的是端点（`版本 · 控制器.动作`），而不是文件和行号：一个端点的标记分散
在控制器的 docblock、Api 类的路由表和中间件链中，因此值得回答的问题是「哪个端点
坏了」。

每条结果都带有稳定的规则标识，因此两次运行的报告可以直接比对。

## 在代码中使用

检查器是一个服务，命令只是它的一层薄封装。

```php
use Dskripchenko\LaravelApi\Services\Linter\OpenApiLinter;

$issues = app(OpenApiLinter::class)->lint();            // 全部已注册版本
$issues = app(OpenApiLinter::class)->lint('v1');        // 仅其中之一

// 或者用显式映射，绕过已注册的模块：
$issues = app(OpenApiLinter::class)->lintVersionList(['v1' => MyApi::class]);

foreach ($issues as $issue) {
    $issue->severity;   // 'error' | 'warning'
    $issue->rule;       // 'action.missing-method'
    $issue->where;      // 'v1 · user.update'
    $issue->message;
    $issue->hint;
}
```

## 另请参阅

- [Docblock 标签参考](docblock-tags.md)
- [食谱](cookbook.md)
