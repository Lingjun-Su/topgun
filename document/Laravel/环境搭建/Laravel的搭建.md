# Laravel的搭建
搭建 Laravel 项目需要以下前置条件和步骤：

### 一、前置条件

#### 1. **PHP 环境**
- **PHP 版本**：Laravel 10.x 需要 PHP 8.1+，Laravel 11.x 需要 PHP 8.2+
- **必需的 PHP 扩展**：
  - OpenSSL
  - PDO
  - Mbstring
  - Tokenizer
  - XML
  - Ctype
  - JSON
  - BCMath
  - Fileinfo

#### 2. **包管理器**
- **Composer**：PHP 依赖管理工具
  - 下载地址：https://getcomposer.org/

#### 3. **数据库（选其一）**
- MySQL 5.7+/MariaDB 10.3+
- PostgreSQL 10+
- SQLite 3.8.8+
- SQL Server 2017+

#### 4. **Web 服务器（开发可选）**
- Apache / Nginx
- Laravel 内置开发服务器（无需额外安装）

#### 5. **可选工具**
- Node.js & NPM（用于前端资源）
- Redis / Memcached（用于缓存）

---

### 二、安装步骤

#### **步骤 1：安装 Composer**
```bash
# Windows：下载 Composer-Setup.exe
# macOS/Linux：
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

#### **步骤 2：创建 Laravel 项目**

**方法 A：使用 Composer（推荐）**
```bash
composer create-project laravel/laravel 项目名称
```

**方法 B：使用 Laravel Installer**
```bash
composer global require laravel/installer
laravel new 项目名称
```

**方法 C：使用 Docker**
```bash
# 使用 Laravel Sail
curl -s "https://laravel.build/项目名称" | bash
```

#### **步骤 3：进入项目目录**
```bash
cd 项目名称
```

#### **步骤 4：环境配置**
1. **复制环境文件**：
   ```bash
   cp .env.example .env
   ```

2. **生成应用密钥**：
   ```bash
   php artisan key:generate
   ```

3. **配置数据库**：
   编辑 `.env` 文件：
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=laravel
   DB_USERNAME=root
   DB_PASSWORD=yourpassword
   ```

#### **步骤 5：运行项目**

**方式 A：使用内置服务器**
```bash
php artisan serve
# 访问 http://localhost:8000
```

**方式 B：使用 Laravel Sail（Docker）**
```bash
./vendor/bin/sail up
# 访问 http://localhost
```

**方式 C：配置虚拟主机（生产环境）**
- Apache：配置 VirtualHost 指向 `public/` 目录
- Nginx：配置 server 指向 `public/` 目录

---

### 三、验证安装
访问 `http://localhost:8000`，看到 Laravel 欢迎页面表示安装成功。

---

### 四、常用后续操作

#### 1. **安装前端依赖**
```bash
npm install
npm run dev  # 开发环境编译
npm run build # 生产环境编译
```

#### 2. **配置缓存**
```bash
php artisan config:cache  # 缓存配置
php artisan route:cache   # 缓存路由
php artisan view:cache    # 缓存视图
```

#### 3. **设置目录权限**
```bash
# Linux/macOS
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache public

# Windows（通常无需特别设置）
```

---

### 五、快速检查命令
```bash
# 检查环境
php artisan about

# 检查系统要求
php artisan env

# 查看所有可用命令
php artisan list
```

---

### 六、常见问题解决

#### **问题 1：Composer 安装慢**
```bash
# 使用中国镜像
composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/
```

#### **问题 2：缺少 PHP 扩展**
```bash
# Ubuntu/Debian
sudo apt install php-mysql php-xml php-mbstring php-curl

# CentOS/RHEL
sudo yum install php-mysqlnd php-xml php-mbstring php-curl

# macOS
brew install php@8.2
```

#### **问题 3：权限错误**
```bash
# 修复存储目录权限
sudo chmod -R 775 storage/
sudo chmod -R 775 bootstrap/cache/
```

---

### 七、不同操作系统特定说明

#### **Windows**
1. 使用 XAMPP/WAMP 或单独安装 PHP
2. 确保 PHP 在系统 PATH 中
3. 使用 Git Bash 或 PowerShell 执行命令

#### **macOS**
```bash
# 使用 Homebrew
brew install php
brew install mysql
brew services start mysql
```

#### **Linux (Ubuntu)**
```bash
sudo apt update
sudo apt install php php-cli php-mysql php-mbstring php-xml php-curl
sudo apt install mysql-server
```

---

### 八、推荐开发工具
1. **编辑器**：PHPStorm、VS Code（安装 Laravel 扩展）
2. **数据库工具**：MySQL Workbench、TablePlus、HeidiSQL
3. **终端工具**：iTerm2（macOS）、Windows Terminal
4. **调试工具**：Laravel Debugbar、Telescope

按照以上步骤，你应该能成功搭建 Laravel 开发环境。如果遇到问题，可以查看 Laravel 官方文档：https://laravel.com/docs