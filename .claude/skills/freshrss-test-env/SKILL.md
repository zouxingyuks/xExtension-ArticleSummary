---
name: freshrss-test-env
description: 快速搭建 FreshRSS 插件开发测试环境。使用此 skill 当用户需要创建、配置、重置 FreshRSS 测试环境，部署插件进行调试，或管理 RSS 阅读器开发环境时。支持插件热部署、数据持久化、环境重置等开发调试场景。
---

# FreshRSS 插件测试环境

这个 skill 帮助快速搭建和管理 FreshRSS 插件开发测试环境，专为插件开发调试场景设计。

## 使用场景

- 创建新的 FreshRSS 测试环境
- 部署和更新插件到测试环境
- 重启容器使插件生效
- 重置环境数据进行全新测试
- 查看容器日志进行调试

## 环境特点

- **持久化数据**：配置、订阅源、文章数据长期保存
- **快速部署**：使用 SQLite 数据库，无需额外容器
- **预配置就绪**：自动完成初始化，开箱即用
- **灵活挂载**：支持通过 docker cp 部署任意插件
- **开发友好**：提供便捷的管理脚本

## 工作流程

### 1. 创建测试环境

当用户请求创建 FreshRSS 测试环境时：

1. **确认配置参数**
   - 工作目录（默认：`./freshrss-test`）
   - 端口号（默认：8080）
   - 管理员密码（默认：admin123）
   - 是否需要预装测试 RSS 源

2. **生成配置文件**

创建 `docker-compose.yml`：

```yaml
version: "3"
services:
  freshrss:
    image: freshrss/freshrss:latest
    container_name: freshrss-test
    hostname: freshrss-test
    restart: unless-stopped
    ports:
      - "8080:80"
    volumes:
      - ./data:/var/www/FreshRSS/data
      - ./extensions:/var/www/FreshRSS/extensions
    environment:
      TZ: Asia/Shanghai
      CRON_MIN: '*/30'
      ADMIN_EMAIL: admin@test.local
      ADMIN_PASSWORD: admin123
      ADMIN_API_PASSWORD: admin123
      BASE_URL: http://localhost:8080
      TRUSTED_PROXY: 172.16.0.0/12 192.168.0.0/16
```

3. **创建目录结构**

```bash
mkdir -p data extensions
```

4. **生成管理脚本**

创建以下脚本文件：
- `start.sh` - 启动环境
- `stop.sh` - 停止环境
- `restart.sh` - 重启容器
- `deploy-plugin.sh` - 部署插件
- `reset.sh` - 重置数据
- `logs.sh` - 查看日志
- `status.sh` - 查看状态

### 2. 部署插件

创建 `deploy-plugin.sh` 脚本：

```bash
#!/bin/bash
# 部署插件到 FreshRSS 测试环境

set -e

if [ $# -eq 0 ]; then
    echo "用法: ./deploy-plugin.sh <插件路径1> [插件路径2] ..."
    echo "示例: ./deploy-plugin.sh ../xExtension-ArticleSummary"
    exit 1
fi

echo "=== 部署插件到 FreshRSS 测试环境 ==="

# 检查容器是否运行
if ! docker ps | grep -q freshrss-test; then
    echo "错误：FreshRSS 容器未运行"
    echo "请先运行: ./start.sh"
    exit 1
fi

# 部署每个插件
for plugin_path in "$@"; do
    if [ ! -d "$plugin_path" ]; then
        echo "警告：插件路径不存在: $plugin_path"
        continue
    fi

    plugin_name=$(basename "$plugin_path")
    echo "正在部署插件: $plugin_name"

    # 复制插件到容器
    docker cp "$plugin_path" freshrss-test:/var/www/FreshRSS/extensions/

    # 同时复制到本地 extensions 目录（保持同步）
    cp -r "$plugin_path" ./extensions/

    echo "✓ 插件 $plugin_name 部署完成"
done

echo ""
echo "=== 重启容器使插件生效 ==="
docker restart freshrss-test

echo ""
echo "等待容器启动..."
sleep 5

echo ""
echo "✓ 部署完成！"
echo "访问 http://localhost:8080 查看效果"
echo "在 FreshRSS 管理界面 -> 扩展 中启用插件"
```

### 3. 环境管理脚本

**start.sh** - 启动环境：
```bash
#!/bin/bash
echo "=== 启动 FreshRSS 测试环境 ==="

if docker ps -a | grep -q freshrss-test; then
    docker start freshrss-test
    echo "✓ 容器已启动"
else
    docker compose up -d
    echo "✓ 环境已创建并启动"
    echo ""
    echo "首次启动需要初始化，请等待约 10 秒..."
    sleep 10
fi

echo ""
echo "访问地址: http://localhost:8080"
echo "管理员账户: admin"
echo "管理员密码: admin123"
```

**restart.sh** - 重启容器：
```bash
#!/bin/bash
echo "=== 重启 FreshRSS 容器 ==="
docker restart freshrss-test
echo "✓ 容器已重启"
echo "等待服务就绪..."
sleep 3
echo "访问地址: http://localhost:8080"
```

**logs.sh** - 查看日志：
```bash
#!/bin/bash
echo "=== FreshRSS 容器日志 ==="
echo "按 Ctrl+C 退出日志查看"
echo ""
docker logs -f --tail=100 freshrss-test
```

**reset.sh** - 重置数据：
```bash
#!/bin/bash
echo "=== 重置 FreshRSS 测试环境 ==="
echo "警告：此操作将删除所有数据（订阅源、文章、配置）"
read -p "确认重置？(yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    echo "已取消"
    exit 0
fi

echo "停止容器..."
docker stop freshrss-test

echo "删除数据..."
rm -rf data/*

echo "重新启动..."
docker start freshrss-test

echo ""
echo "✓ 环境已重置"
echo "等待初始化完成..."
sleep 10
echo "访问 http://localhost:8080 重新配置"
```

**status.sh** - 查看状态：
```bash
#!/bin/bash
echo "=== FreshRSS 测试环境状态 ==="
echo ""

if docker ps | grep -q freshrss-test; then
    echo "状态: ✓ 运行中"
    echo "容器 ID: $(docker ps --filter name=freshrss-test --format '{{.ID}}')"
    echo "运行时间: $(docker ps --filter name=freshrss-test --format '{{.Status}}')"
    echo "端口映射: $(docker ps --filter name=freshrss-test --format '{{.Ports}}')"
    echo ""
    echo "访问地址: http://localhost:8080"
    echo ""
    echo "已安装插件:"
    if [ -d "./extensions" ]; then
        ls -1 ./extensions/ | grep -E "^x" || echo "  (无)"
    else
        echo "  (extensions 目录不存在)"
    fi
else
    echo "状态: ✗ 未运行"
    echo "运行 ./start.sh 启动环境"
fi
```

### 4. 创建 README

生成 `README.md` 说明文档：

```markdown
# FreshRSS 插件测试环境

## 快速开始

### 启动环境
```bash
./start.sh
```

访问 http://localhost:8080
- 用户名: admin
- 密码: admin123

### 部署插件
```bash
./deploy-plugin.sh /path/to/your/plugin
```

插件会自动复制到容器并重启生效。

### 查看日志
```bash
./logs.sh
```

### 重启容器
```bash
./restart.sh
```

修改插件代码后，运行此命令使更改生效。

### 查看状态
```bash
./status.sh
```

### 重置环境
```bash
./reset.sh
```

清空所有数据，重新开始测试。

## 目录结构

```
freshrss-test/
├── docker-compose.yml    # Docker 配置
├── data/                 # 持久化数据（配置、数据库）
├── extensions/           # 插件目录
├── start.sh             # 启动脚本
├── stop.sh              # 停止脚本
├── restart.sh           # 重启脚本
├── deploy-plugin.sh     # 部署插件脚本
├── reset.sh             # 重置脚本
├── logs.sh              # 日志查看脚本
├── status.sh            # 状态查看脚本
└── README.md            # 本文档
```

## 插件开发工作流

1. 修改插件代码
2. 运行 `./deploy-plugin.sh /path/to/plugin` 部署
3. 访问 http://localhost:8080 测试
4. 查看 `./logs.sh` 调试
5. 重复 1-4

## 常见问题

### 端口被占用
编辑 `docker-compose.yml`，修改端口映射：
```yaml
ports:
  - "8081:80"  # 改为其他端口
```

### 插件未生效
1. 确认插件已复制到 extensions 目录
2. 在 FreshRSS 管理界面启用插件
3. 运行 `./restart.sh` 重启容器

### 查看 PHP 错误
```bash
docker exec freshrss-test tail -f /var/www/FreshRSS/data/users/_/log.txt
```

## 清理环境

完全删除测试环境：
```bash
docker compose down
rm -rf data extensions
```
```

### 5. 设置脚本权限

所有脚本文件创建后，自动添加执行权限：

```bash
chmod +x *.sh
```

## 输出总结

完成环境创建后，向用户提供清晰的总结：

```
✓ FreshRSS 插件测试环境已创建

目录: ./freshrss-test
访问: http://localhost:8080
账户: admin / admin123

快速命令:
  ./start.sh              - 启动环境
  ./deploy-plugin.sh <路径> - 部署插件
  ./restart.sh            - 重启容器
  ./logs.sh               - 查看日志
  ./status.sh             - 查看状态
  ./reset.sh              - 重置数据

下一步:
1. 运行 ./start.sh 启动环境
2. 访问 http://localhost:8080 完成初始化
3. 使用 ./deploy-plugin.sh 部署你的插件
```

## 注意事项

1. **首次启动**：容器首次启动需要 10-15 秒初始化
2. **插件路径**：deploy-plugin.sh 接受绝对路径或相对路径
3. **数据持久化**：data 目录包含所有配置和文章数据
4. **端口冲突**：如果 8080 被占用，修改 docker-compose.yml
5. **权限问题**：确保脚本有执行权限（chmod +x *.sh）

## 调试技巧

### 查看容器内部
```bash
docker exec -it freshrss-test bash
```

### 查看 PHP 日志
```bash
docker exec freshrss-test tail -f /var/www/FreshRSS/data/users/_/log.txt
```

### 检查插件是否加载
```bash
docker exec freshrss-test ls -la /var/www/FreshRSS/extensions/
```

### 手动重新加载配置
```bash
docker exec freshrss-test php /var/www/FreshRSS/cli/update-user.php --user admin
```

## 扩展功能

### 预装测试 RSS 源

如果用户需要预装测试源，可以在初始化后执行：

```bash
# 添加测试 RSS 源
docker exec freshrss-test php /var/www/FreshRSS/cli/add-feed.php \
  --user admin \
  --feed "https://www.example.com/feed.xml"
```

### 导出/导入配置

导出配置：
```bash
docker exec freshrss-test php /var/www/FreshRSS/cli/export-opml.php \
  --user admin > backup.opml
```

导入配置：
```bash
docker cp backup.opml freshrss-test:/tmp/
docker exec freshrss-test php /var/www/FreshRSS/cli/import-opml.php \
  --user admin --file /tmp/backup.opml
```

## 最佳实践

1. **版本控制**：将 docker-compose.yml 和脚本纳入版本控制
2. **数据备份**：定期备份 data 目录
3. **插件开发**：使用符号链接或 deploy-plugin.sh 保持代码同步
4. **日志监控**：开发时保持 logs.sh 运行以实时查看错误
5. **环境隔离**：为不同项目创建独立的测试环境目录
