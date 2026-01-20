# Fix: "Target class [env] does not exist" Error

## 🔧 Masalah

Error ini terjadi ketika menjalankan `php artisan config:clear` atau command artisan lainnya di production server.

## ✅ Solusi yang Sudah Diterapkan

Telah diperbaiki nested `env()` calls di config files berikut:

### 1. `config/filesystems.php`
```php
// ✅ FIXED
'url' => config('app.url').'/storage',
```

### 2. `config/session.php`
```php
// ✅ FIXED
'cookie' => env(
    'SESSION_COOKIE',
    Str::snake((string) config('app.name', 'laravel')).'_session'
),
```

### 3. `config/cache.php`
```php
// ✅ FIXED
'prefix' => env('CACHE_PREFIX', Str::slug((string) config('app.name', 'laravel')).'-cache-'),
```

### 4. `config/database.php`
```php
// ✅ FIXED
'prefix' => env('REDIS_PREFIX', Str::slug((string) config('app.name', 'laravel')).'-database-'),
```

### 5. `config/mail.php`
```php
// ✅ FIXED
'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST)),
```

### 6. `config/sanctum.php`
```php
// ✅ FIXED (setelah update keamanan)
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS') ?: sprintf(
    '%s%s',
    'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
    Sanctum::currentApplicationUrlWithPort(),
)),
```

### 7. `config/cors.php`
```php
// ✅ FIXED (setelah update keamanan - CORS error)
// Menghapus dependency pada config('app.env') yang tidak tersedia saat config di-cache
'allowed_origins' => [
    'https://kasir-pos.sunnflower.site/', // Production frontend
    'http://localhost:4173', // Local development
    'http://127.0.0.1:4173', // Local development
],
```

## 📋 Langkah Fix di Server Production

### Step 1: Pull Perubahan

```bash
cd /path/to/kasir-pos-system
git pull origin main
```

### Step 2: Clear Semua Cache

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### Step 3: Verify Fix

```bash
php artisan config:cache
```

Jika tidak ada error, fix berhasil!

### Step 4: Rebuild Cache (Optional)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## ⚠️ Important Notes

1. **Jangan gunakan nested `env()`:**
   ```php
   // ❌ SALAH
   env('APP_URL').'/storage'
   env('APP_NAME') . '_session'
   
   // ✅ BENAR
   config('app.url').'/storage'
   config('app.name') . '_session'
   ```

2. **Gunakan `env()` hanya di top-level config values:**
   ```php
   // ✅ OK
   'name' => env('APP_NAME', 'Laravel'),
   
   // ❌ TIDAK OK (nested)
   'url' => env('APP_URL').'/storage',
   ```

3. **Gunakan `config()` untuk akses config lain:**
   ```php
   // ✅ BENAR
   config('app.name')
   config('app.url')
   ```

## 🔍 Verifikasi

Setelah fix, test dengan:

```bash
php artisan config:clear
php artisan route:list
php artisan tinker
```

Jika semua command berjalan tanpa error, fix berhasil!

---

**Last Updated**: January 2025

