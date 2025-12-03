# Deployment Guide

## 📋 Pre-Deployment Checklist

- [ ] All code committed and pushed
- [ ] Environment variables configured
- [ ] Database migrations tested
- [ ] Security audit completed
- [ ] Performance optimization applied
- [ ] Backup strategy in place

---

## 🚀 Deployment Methods

### Method 1: Traditional Hosting (Shared/VPS)

#### Step 1: Upload Files

```bash
# Via FTP/SFTP atau Git
git clone <repository-url>
# atau upload via FTP client
```

#### Step 2: Install Dependencies

```bash
cd kasir-pos-system
composer install --optimize-autoloader --no-dev
```

#### Step 3: Setup Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` dengan production values:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=your_db_host
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

FRONTEND_URL=https://your-frontend-domain.com
```

#### Step 4: Setup Web Server

**Apache (.htaccess)**
```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

**Nginx**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

#### Step 5: Set Permissions

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

#### Step 6: Run Migrations

```bash
php artisan migrate --force
```

#### Step 7: Optimize Application

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### Step 8: Setup SSL (HTTPS)

Use Let's Encrypt:
```bash
sudo certbot --nginx -d your-domain.com
```

---

### Method 2: Docker Deployment

#### Dockerfile

```dockerfile
FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

WORKDIR /var/www

COPY . .

RUN composer install --optimize-autoloader --no-dev

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
RUN chmod -R 775 /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
```

#### docker-compose.yml

```yaml
version: '3.8'

services:
  app:
    build: .
    volumes:
      - .:/var/www
    networks:
      - kasir-pos-network

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - .:/var/www
      - ./nginx.conf:/etc/nginx/conf.d/default.conf
    networks:
      - kasir-pos-network
    depends_on:
      - app

  db:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: kasir_pos
      MYSQL_USER: kasir_user
      MYSQL_PASSWORD: kasir_password
      MYSQL_ROOT_PASSWORD: root_password
    volumes:
      - db_data:/var/lib/mysql
    networks:
      - kasir-pos-network

networks:
  kasir-pos-network:
    driver: bridge

volumes:
  db_data:
```

---

## 🔒 Security Hardening

### 1. Environment Variables

Jangan commit `.env` file. Pastikan sudah di `.gitignore`.

### 2. File Permissions

```bash
# Storage & cache writable
chmod -R 775 storage bootstrap/cache

# Other files read-only
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
```

### 3. Disable Debug Mode

```env
APP_DEBUG=false
```

### 4. HTTPS Enforcement

Middleware `ForceHttps` sudah tersedia dan otomatis aktif di production.

### 5. Security Headers

Middleware `SecurityHeaders` sudah tersedia dan otomatis aktif.

### 6. Rate Limiting

Sudah dikonfigurasi:
- Login: 5 requests/minute
- API: 60 requests/minute

---

## 📊 Monitoring & Logs

### Laravel Logs

```bash
tail -f storage/logs/laravel.log
```

### Error Tracking

Setup error tracking service (Sentry, Bugsnag, dll) jika diperlukan.

### Performance Monitoring

Monitor dengan tools seperti:
- New Relic
- DataDog
- Laravel Telescope (development only)

---

## 🔄 Backup Strategy

### Automated Backups

Setup cron job untuk backup:

```bash
# Daily backup at 2 AM
0 2 * * * cd /path-to-project && php artisan backup:run
```

### Manual Backup

```bash
php artisan backup:run
```

### Database Backup Only

```bash
mysqldump -u username -p database_name > backup.sql
```

### Restore Backup

```bash
# Via Laravel backup
php artisan backup:restore backup.zip

# Via MySQL
mysql -u username -p database_name < backup.sql
```

---

## 🚨 Troubleshooting

### Issue: 500 Internal Server Error

**Check:**
1. Logs: `storage/logs/laravel.log`
2. File permissions
3. `.env` configuration
4. PHP error log

### Issue: Database Connection Error

**Check:**
1. Database credentials di `.env`
2. Database server running
3. Network connectivity
4. Firewall rules

### Issue: Permission Denied

**Solution:**
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Issue: Route Not Found

**Solution:**
```bash
php artisan route:clear
php artisan route:cache
```

### Issue: Storage Link Not Working

**Solution:**
```bash
php artisan storage:link
```

---

## 📈 Performance Optimization

### 1. OPcache (PHP)

Enable OPcache di `php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
```

### 2. Database Indexes

Indexes sudah dibuat via migration. Pastikan migration sudah dijalankan.

### 3. Caching

```bash
# Cache config
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache
```

### 4. Redis Caching (Optional)

Setup Redis untuk caching:
```env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

---

## 🔄 Update Deployment

### Step 1: Backup

```bash
php artisan backup:run
```

### Step 2: Pull Changes

```bash
git pull origin main
```

### Step 3: Update Dependencies

```bash
composer install --optimize-autoloader --no-dev
```

### Step 4: Run Migrations

```bash
php artisan migrate --force
```

### Step 5: Clear Caches

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

### Step 6: Rebuild Caches

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

**Last Updated**: January 2025

