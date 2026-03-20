---
name: freshrss-plugin-debug
description: |
  FreshRSS 插件端到端调试工具。当用户需要测试、调试、验证 FreshRSS 插件功能时使用此 skill。

  适用场景：
  - 完整的插件调试流程（环境启动 → 插件部署 → 配置 → 功能验证）
  - 部署插件到已有的 FreshRSS 测试环境
  - 使用浏览器自动化测试插件功能
  - 验证插件的 UI 交互、API 调用、数据处理
  - 生成包含截图和日志的测试报告

  即使用户没有明确说"调试"或"测试"，只要提到需要验证 FreshRSS 插件是否工作、检查插件功能、或者想看插件的实际效果，都应该使用此 skill。
compatibility:
  tools:
    - Bash
    - Playwright
    - Read
    - Write
  dependencies:
    - Docker
    - FreshRSS 测试环境（freshrss-test）
---

# FreshRSS 插件调试 Skill

这个 skill 提供完整的 FreshRSS 插件调试工作流，从环境准备到功能验证的全流程自动化。

## 核心能力

1. **环境管理**：启动/重启 FreshRSS Docker 测试环境
2. **插件部署**：自动部署插件到容器并重启生效
3. **浏览器自动化**：登录、配置、测试插件功能
4. **结果验证**：生成包含截图和日志的测试报告

## 工作流程

### 阶段 1：环境准备

检查 FreshRSS 测试环境状态：

```bash
cd /path/to/freshrss-test
./status.sh
```

如果环境未运行，启动它：

```bash
./start.sh
```

**关键点**：
- 确认容器状态为 "运行中"
- 确认端口 8080 可访问
- 等待 5-10 秒让服务完全启动

### 阶段 2：插件部署

使用 `deploy-plugin.sh` 部署插件：

```bash
./deploy-plugin.sh /path/to/plugin
```

这个脚本会：
1. 使用 rsync 复制插件文件到 `extensions/` 目录
2. 排除不必要的文件（.git, vendor, node_modules 等）
3. 自动重启容器使插件生效

**验证部署**：
```bash
docker exec freshrss-test ls -la /var/www/FreshRSS/extensions/
```

### 阶段 3：浏览器自动化配置

使用 Playwright 工具完成以下步骤：

#### 3.1 登录 FreshRSS

```
1. 导航到 http://localhost:8080
2. 如果看到登录页面，输入凭据（默认：admin/admin123）
3. 点击登录按钮
```

#### 3.2 启用插件

```
1. 点击右上角的配置图标（⚙️）
2. 选择 "Extensions" 或"扩展"
3. 找到目标插件
4. 如果插件未启用，点击启用按钮
5. 截图保存启用状态
```

#### 3.3 配置插件（如果需要）

如果插件有配置页面：

```
1. 在扩展列表中点击插件的配置按钮
2. 填写必要的配置项（API key、URL 等）
3. 保存配置
4. 截图保存配置页面
```

**常见配置项**：
- API 端点 URL
- API 密钥
- 模型名称
- 自定义提示词

### 阶段 4：功能测试

根据插件类型执行相应的测试：

#### 4.1 添加测试数据（如果需要）

对于需要 RSS 源的测试：

```
1. 导航到订阅管理页面
2. 添加测试 RSS 源（例如：https://blog.sleepstars.net/rss.xml）
3. 等待文章加载
4. 截图确认文章列表
```

#### 4.2 测试插件功能

**UI 增强类插件**：
- 导航到文章列表或详情页
- 查找插件添加的 UI 元素（按钮、面板等）
- 截图记录 UI 变化

**交互功能类插件**：
- 点击插件添加的按钮或控件
- 观察响应（弹窗、内容变化、加载状态）
- 截图记录交互过程

**API 调用类插件**：
- 触发需要 API 调用的功能
- 使用 `browser_network_requests` 捕获网络请求
- 验证请求 URL、参数、响应状态
- 截图记录结果

#### 4.3 验证数据处理

如果插件处理或显示数据：
- 检查数据格式是否正确
- 验证错误处理（如 API 失败时的提示）
- 测试边界情况（空数据、长文本等）

### 阶段 5：日志收集

收集调试信息：

```bash
# 容器日志
docker logs freshrss-test --tail=100 > /tmp/freshrss-container.log

# PHP 错误日志（如果存在）
docker exec freshrss-test cat /var/www/FreshRSS/data/users/_/log.txt > /tmp/freshrss-php.log 2>/dev/null || echo "No PHP log found"

# 浏览器控制台日志
# 使用 browser_console_messages 工具获取
```

### 阶段 6：生成测试报告

创建结构化的测试报告：

```markdown
# FreshRSS 插件测试报告

**插件名称**: [插件名]
**测试时间**: [时间戳]
**测试环境**: FreshRSS Docker (localhost:8080)

## 测试结果总览

- ✅ 环境启动
- ✅ 插件部署
- ✅ 插件启用
- ✅ 配置完成
- ✅ 功能验证

## 详细步骤

### 1. 环境准备
- 状态: ✅ 成功
- 容器 ID: [container_id]
- 运行时间: [uptime]

### 2. 插件部署
- 状态: ✅ 成功
- 部署路径: /var/www/FreshRSS/extensions/[plugin_name]
- 文件数量: [count]

### 3. 浏览器配置
- 登录: ✅ 成功
- 插件启用: ✅ 成功
- 配置保存: ✅ 成功
- 截图: [screenshot_paths]

### 4. 功能测试
- 测试场景: [描述]
- 结果: ✅ 成功 / ❌ 失败
- 截图: [screenshot_paths]
- 网络请求: [request_count] 个请求
  - API 调用: [api_url]
  - 响应状态: [status_code]
  - 响应时间: [duration]ms

### 5. 日志分析
- 容器日志: 无错误
- PHP 日志: 无错误
- 浏览器控制台: [error_count] 个错误

## 问题和建议

[如果有问题，在这里列出]

## 附件

- 截图目录: [path]
- 日志文件: [paths]
```

## 输出格式

测试完成后，提供：

1. **测试报告 Markdown 文件**：保存到 `/tmp/freshrss-test-report-[timestamp].md`
2. **截图文件**：保存到 `/tmp/freshrss-screenshots/`
3. **日志文件**：保存到 `/tmp/freshrss-logs/`
4. **简要总结**：在对话中直接显示关键结果

## 错误处理

### 常见问题

**容器未启动**：
```bash
cd /path/to/freshrss-test
./start.sh
sleep 10  # 等待初始化
```

**插件未加载**：
```bash
./restart.sh  # 重启容器
```

**登录失败**：
- 检查凭据（默认 admin/admin123）
- 查看容器日志确认服务正常

**网络请求失败**：
- 检查 API 配置（URL、密钥）
- 查看浏览器控制台错误
- 检查 CORS 设置

**截图失败**：
- 确认页面已完全加载
- 等待动态内容渲染
- 使用 `browser_wait_for` 等待特定元素

## 最佳实践

1. **截图时机**：在每个关键步骤后立即截图，不要等到最后
2. **等待策略**：使用 `browser_wait_for` 而不是固定的 sleep
3. **错误捕获**：每个步骤都要检查是否成功，失败时立即记录
4. **日志保存**：即使测试成功也要保存日志，便于后续分析
5. **清理环境**：测试完成后询问用户是否需要重置环境

## 扩展场景

### 测试多个插件

如果需要测试多个插件的交互：

```bash
./deploy-plugin.sh /path/to/plugin1 /path/to/plugin2
```

然后在浏览器中依次启用和测试每个插件。

### 性能测试

如果需要测试插件性能：

1. 使用 `browser_network_requests` 记录所有请求
2. 分析响应时间
3. 检查是否有不必要的重复请求
4. 验证缓存策略

### 兼容性测试

测试插件在不同场景下的表现：

- 空数据状态
- 大量数据
- 网络错误
- API 超时
- 并发操作

## 示例用法

**场景 1：完整的端到端测试**

用户说："我刚写了一个 FreshRSS 插件，想测试一下是否工作"

执行：
1. 检查并启动测试环境
2. 部署插件
3. 浏览器自动化配置
4. 测试核心功能
5. 生成完整报告

**场景 2：快速验证已部署的插件**

用户说："插件已经部署了，帮我测试一下摘要功能"

执行：
1. 跳过环境启动和部署
2. 直接打开浏览器
3. 导航到文章页面
4. 测试摘要按钮
5. 验证 API 调用和响应

**场景 3：调试配置问题**

用户说："插件启用了但是不工作，帮我看看哪里有问题"

执行：
1. 检查插件是否正确加载
2. 查看浏览器控制台错误
3. 检查网络请求
4. 分析 PHP 日志
5. 提供诊断建议

## 注意事项

- 这个 skill 设计为通用的 FreshRSS 插件调试工具，不局限于特定插件
- 根据插件类型调整测试步骤（UI 增强、API 集成、数据处理等）
- 始终生成结构化的测试报告，即使测试失败
- 保存所有截图和日志，便于问题排查
- 测试完成后询问用户是否需要进一步的调试或优化建议
