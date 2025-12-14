# 安装指南

## 快速开始

### 1. 环境准备

确保您的服务器环境满足以下要求：
- PHP >= 7.4
- MySQL >= 5.7
- Apache/Nginx Web服务器
- 启用 PDO MySQL 扩展

### 2. 数据库配置

#### 步骤1：创建数据库

```bash
mysql -u root -p < schema.sql
```

或者手动执行：
1. 登录MySQL：`mysql -u root -p`
2. 执行 `schema.sql` 文件中的所有SQL语句

#### 步骤2：导入示例数据（可选）

```bash
mysql -u root -p exam_system < sample_data.sql
```

这将导入30道示例题目，涵盖三个题库：
- ERP理论与应用（10题）
- 数据挖掘（10题）
- Java基础（10题）

#### 步骤3：初始化管理员密码

运行初始化脚本确保管理员密码正确：

```bash
php init_admin.php
```

或者通过浏览器访问：`http://your-domain/init_admin.php`

默认管理员账号：
- 用户名：`admin`
- 密码：`admin123`

**重要**：生产环境请务必修改默认密码！

### 3. 配置文件修改

编辑 `config.php` 文件，修改数据库连接信息：

```php
define('DB_HOST', 'localhost');  // 数据库主机
define('DB_NAME', 'exam_system'); // 数据库名
define('DB_USER', 'root');        // 数据库用户名
define('DB_PASS', '');            // 数据库密码
```

### 4. Web服务器配置

#### Apache配置

确保 `.htaccess` 文件生效，如果未生效，检查：
1. Apache已启用 `mod_rewrite` 模块
2. 虚拟主机配置中 `AllowOverride` 设置为 `All`

#### Nginx配置示例

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/exam_system;
    index index.php login.php;

    location / {
        try_files $uri $uri/ /login.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

### 5. 文件权限设置

确保Web服务器对以下目录有写入权限（如果需要上传文件功能）：
- `uploads/`（如果存在）

```bash
chmod -R 755 /path/to/exam_system
chown -R www-data:www-data /path/to/exam_system
```

### 6. 访问系统

1. 打开浏览器访问：`http://your-domain/login.php`
2. 使用管理员账号登录：`admin` / `admin123`
3. 开始使用系统！

## 常见问题

### Q1: 数据库连接失败

**解决方案**：
1. 检查 `config.php` 中的数据库配置是否正确
2. 确认MySQL服务正在运行
3. 检查数据库用户权限

### Q2: 页面样式不显示

**解决方案**：
1. 检查 `BASE_PATH` 配置是否正确
2. 查看浏览器控制台是否有404错误
3. 确认 `assets/css/style.css` 文件存在

### Q3: 登录后页面空白

**解决方案**：
1. 检查PHP错误日志
2. 确认session功能正常
3. 检查文件权限

### Q4: API请求失败

**解决方案**：
1. 检查 `api/` 目录下的文件是否存在
2. 确认路径配置正确（BASE_PATH）
3. 查看浏览器网络请求详情

### Q5: 批量导入失败

**解决方案**：
1. 检查CSV/JSON格式是否正确
2. 确认数据库表结构完整
3. 查看PHP错误日志

## 测试账号

系统安装完成后，可以使用以下账号进行测试：

### 管理员账号
- 用户名：`admin`
- 密码：`admin123`
- 权限：全部功能

### 创建测试用户

1. 使用管理员账号登录
2. 进入"用户管理"
3. 点击"添加用户"
4. 创建普通用户账号

## 数据备份

定期备份数据库：

```bash
mysqldump -u root -p exam_system > backup_$(date +%Y%m%d).sql
```

恢复数据库：

```bash
mysql -u root -p exam_system < backup_20240101.sql
```

## 安全建议

1. **修改默认密码**：安装后立即修改管理员密码
2. **数据库安全**：使用强密码，限制数据库用户权限
3. **HTTPS**：生产环境建议启用HTTPS
4. **文件权限**：设置合适的文件权限
5. **定期更新**：保持PHP和MySQL版本更新
6. **备份数据**：定期备份数据库

## 技术支持

如遇到问题，请检查：
1. PHP错误日志：`/var/log/php_errors.log`
2. Apache/Nginx错误日志
3. MySQL错误日志

## 下一步

安装完成后，您可以：
1. 创建题库和章节
2. 添加题目
3. 批量导入题目
4. 创建用户账号
5. 开始刷题练习

祝您使用愉快！

