# Fix: 500 Internal Server Error pada OPTIONS Request (CORS Preflight)

## 🔧 Masalah

API mengembalikan error `500 Internal Server Error` pada OPTIONS request (CORS preflight), yang menyebabkan browser tidak bisa mengirim request actual.

## ✅ Solusi yang Sudah Diterapkan

### 1. Middleware Updates

#### `ForceHttps` - Skip OPTIONS requests
```php
// Skip redirect untuk OPTIONS requests (CORS preflight)
if ($request->getMethod() === 'OPTIONS') {
    return $next($request);
}
```

#### `SecurityHeaders` - Skip OPTIONS requests
```php
// For OPTIONS requests, let it pass through immediately
if ($request->getMethod() === 'OPTIONS') {
    return $next($request);
}
```

#### `AllowElectronOrigin` - Hanya untuk Electron
```php
// DO NOT interfere dengan web browser OPTIONS requests
if ($request->getMethod() === 'OPTIONS' && !$isElectron) {
    return $next($request);
}
```

### 2. Middleware Order di `bootstrap/app.php`

```php
// Urutan:
// 1. Laravel CORS middleware (otomatis, berdasarkan config/cors.php)
// 2. ForceHttps (prepend - hanya untuk production)
// 3. SecurityHeaders (append)
// 4. AllowElectronOrigin (append - hanya untuk Electron)
```

## 📋 Langkah Fix di Server Production

### Step 1: Pull Perubahan

```bash
cd /path/to/kasir-pos-system
git pull origin main
```

### Step 2: Clear SEMUA Cache (PENTING!)

```bash
# HAPUS semua file cache
rm -f bootstrap/cache/config.php
rm -f bootstrap/cache/routes.php
rm -f bootstrap/cache/services.php

# Clear via artisan
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Clear opcache jika menggunakan PHP-FPM
sudo systemctl reload php-fpm
# atau
sudo service php8.2-fpm reload
```

### Step 3: Verifikasi Config Files

Pastikan tidak ada error saat membaca config:

```bash
php artisan tinker
```

Di dalam tinker:
```php
// Test config files
config('cors.allowed_origins'); // Should return array
config('app.env'); // Should return 'production' or environment name
app()->environment(); // Should not throw error
```

### Step 4: Rebuild Cache

```bash
php artisan config:cache
php artisan route:cache
```

### Step 5: Test OPTIONS Request

```bash
# Test OPTIONS request
curl -X OPTIONS \
     -H "Origin: https://kasir-pos.sunnflower.site" \
     -H "Access-Control-Request-Method: GET" \
     -H "Access-Control-Request-Headers: authorization,x-client-type,x-client-version" \
     https://kasir-pos-api.sunnflower.site/api/v1/dashboard \
     -v
```

Response harus:
- Status: `200 OK` atau `204 No Content`
- Header: `Access-Control-Allow-Origin: https://kasir-pos.sunnflower.site`
- **TIDAK boleh** `500 Internal Server Error`

### Step 6: Restart Web Server

```bash
# Apache
sudo systemctl restart apache2

# Nginx + PHP-FPM
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm
```

## 🔍 Troubleshooting

### Jika Masih Error 500

1. **Cek Error Logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```
   
   Cari error message yang spesifik saat OPTIONS request.

2. **Cek Apakah Config Cache Berhasil:**
   ```bash
   ls -la bootstrap/cache/
   ```
   
   Pastikan file `config.php` ada dan ter-update.

3. **Test Tanpa Config Cache:**
   ```bash
   php artisan config:clear
   # Test OPTIONS request
   # Jika berhasil tanpa cache, masalahnya di config cache
   ```

4. **Cek PHP Error Logs:**
   ```bash
   tail -f /var/log/php8.2-fpm.log
   # atau
   tail -f /var/log/apache2/error.log
   ```

5. **Disable Semua Middleware Custom Sementara:**
   
   Edit `bootstrap/app.php`, comment semua middleware custom:
   ```php
   // $middleware->prepend(\App\Http\Middleware\ForceHttps::class);
   // $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
   // $middleware->append(\App\Http\Middleware\AllowElectronOrigin::class);
   ```
   
   Test apakah OPTIONS request berhasil. Jika berhasil, enable satu per satu untuk menemukan yang bermasalah.

6. **Cek File Permissions:**
   ```bash
   chmod -R 755 storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache
   ```

## ⚠️ Important Notes

1. **JANGAN skip Step 2 (Clear Cache)** - Ini sangat penting!

2. **Hapus file cache secara manual** - `config:clear` mungkin tidak menghapus file cache yang corrupted.

3. **Test OPTIONS request langsung** - Jangan hanya test dari browser, gunakan curl untuk melihat response yang sebenarnya.

4. **Cek error logs** - Error 500 selalu ada error message di logs.

## 📝 Checklist

- [ ] Pull perubahan dari git
- [ ] Hapus file cache secara manual (`bootstrap/cache/config.php`, dll)
- [ ] Clear semua cache via artisan
- [ ] Rebuild config cache
- [ ] Test OPTIONS request dengan curl
- [ ] Cek error logs jika masih error
- [ ] Restart web server / PHP-FPM
- [ ] Test dari browser

---

**Last Updated**: January 2025

