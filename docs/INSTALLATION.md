# Installation Guide

## 📋 Requirements

### Server Requirements
- PHP >= 8.2
- Composer
- MySQL 5.7+ / PostgreSQL 10+ / SQLite 3.8.8+
- Web server (Apache/Nginx)
- OpenSSL PHP Extension
- PDO PHP Extension
- Mbstring PHP Extension
- Tokenizer PHP Extension
- XML PHP Extension
- Ctype PHP Extension
- JSON PHP Extension

### Optional
- Redis (untuk caching)
- Supervisor (untuk queue workers)

---

## 🚀 Installation Steps

### 1. Clone Repository

```bash
git clone <repository-url>
cd kasir-pos-system
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configure Database

Edit `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kasir_pos
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 5. Run Migrations

```bash
php artisan migrate
```

### 6. Seed Database

```bash
php artisan db:seed
```

Seeder yang akan dijalankan:
- `RolePermissionSeeder` - Roles & permissions
- `CategoryUnitSeeder` - Categories & units
- `OutletSeeder` - Default outlets
- `SettingSeeder` - Application settings

### 7. Setup Sanctum

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

### 8. Create Storage Link

```bash
php artisan storage:link
```

### 9. Set Permissions (Linux/Mac)

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 10. Configure CORS

Edit `config/cors.php` atau set di `.env`:

```env
FRONTEND_URL=http://localhost:4173
```

### 11. Run Development Server

```bash
php artisan serve
```

Server akan berjalan di `http://localhost:8000`

---

## 🔧 Production Setup

### 1. Optimize Application

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer install --optimize-autoloader --no-dev
```

### 2. Set Environment

```env
APP_ENV=production
APP_DEBUG=false
```

### 3. Setup Queue Workers (Optional)

Jika menggunakan queue, setup Supervisor:

```ini
[program:kasir-pos-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/worker.log
```

### 4. Setup Cron Job

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 📦 Database Setup

### MySQL

1. Create database:
```sql
CREATE DATABASE kasir_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Update `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kasir_pos
DB_USERNAME=root
DB_PASSWORD=your_password
```

3. Run migrations:
```bash
php artisan migrate
```

### SQLite

1. Create database file:
```bash
touch database/database.sqlite
```

2. Update `.env`:
```env
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database.sqlite
```

3. Run migrations:
```bash
php artisan migrate
```

### PostgreSQL

1. Create database:
```sql
CREATE DATABASE kasir_pos;
```

2. Update `.env`:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=kasir_pos
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

3. Run migrations:
```bash
php artisan migrate
```

---

## 👤 Default Users

Setelah menjalankan seeder, default user:

**Super Admin:**
- Email: `admin@example.com`
- Password: `password`

**Note**: Ganti password default setelah first login!

---

## 🔍 Troubleshooting

### Error: "SQLSTATE[HY000] [2002] Connection refused"

**Solution**: Pastikan database server berjalan dan kredensial di `.env` benar.

### Error: "Class 'X' not found"

**Solution**: 
```bash
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

### Error: "No application encryption key"

**Solution**:
```bash
php artisan key:generate
```

### Error: "The stream or file could not be opened"

**Solution**: Pastikan folder `storage` dan `bootstrap/cache` writable:
```bash
chmod -R 775 storage bootstrap/cache
```

### Error: Storage link not found

**Solution**:
```bash
php artisan storage:link
```

---

## ✅ Verification

Setelah instalasi, verifikasi:

1. **Check API health:**
```bash
curl http://localhost:8000/api/v1/login
```

2. **Check database:**
```bash
php artisan tinker
>>> \App\Models\User::count()
```

3. **Check storage:**
```bash
ls -la storage/app/public
```

---

## 📚 Next Steps

1. Setup frontend - Lihat dokumentasi frontend
2. Configure settings - Login dan konfigurasi aplikasi
3. Add products - Tambahkan produk pertama
4. Setup outlets - Konfigurasi outlet
5. Test transactions - Buat transaksi test

---

**Last Updated**: January 2025

