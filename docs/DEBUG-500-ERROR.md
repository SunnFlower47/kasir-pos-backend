# Debug 500 Error pada OPTIONS Request

## Masalah
OPTIONS request ke `/api/v1/login` mengembalikan `HTTP/2 500` instead of `200 OK` dengan CORS headers.

## Langkah Debugging

### 1. Cek Error Logs di Server

```bash
# Cek Laravel error log
tail -f storage/logs/laravel.log

# Cek PHP error log
tail -f /var/log/php8.2-fpm.log
# atau
tail -f /var/log/apache2/error.log
```

**Jalankan OPTIONS request lagi** dan lihat error message di log.

### 2. Test Apakah Middleware Berjalan

Edit `app/Http/Middleware/HandleCors.php` sementara, tambahkan logging:

```php
public function handle(Request $request, Closure $next): Response
{
    \Log::info('HandleCors: Method=' . $request->getMethod());
    \Log::info('HandleCors: Origin=' . $request->header('Origin'));
    
    // ... rest of code
}
```

Lalu test lagi dan cek log.

### 3. Clear SEMUA Cache

```bash
# Hapus file cache
rm -f bootstrap/cache/*.php
rm -f storage/framework/cache/data/*
rm -f storage/framework/views/*

# Clear via artisan
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Clear opcache
sudo systemctl reload php-fpm
```

### 4. Test Route List

```bash
# Cek apakah route terdaftar
php artisan route:list | grep login
```

### 5. Test Tanpa Cloudflare

Cari IP server kamu, lalu test langsung:

```bash
# Ganti dengan IP server kamu (bukan [IP_SERVER])
# Contoh: curl -v -X OPTIONS http://103.123.45.67/api/v1/login

curl -v -X OPTIONS \
     -H "Origin: https://kasir-pos.sunnflower.site" \
     -H "Access-Control-Request-Method: POST" \
     http://YOUR_SERVER_IP/api/v1/login
```

## Kemungkinan Penyebab Error 500

1. **Config cache corrupted** - Hapus `bootstrap/cache/config.php`
2. **Middleware crash** - Cek error log untuk stack trace
3. **Route conflict** - Ada route yang konflik dengan OPTIONS
4. **PHP error** - Syntax error atau missing class

## Quick Fix: Bypass Middleware Lain Sementara

Edit `bootstrap/app.php`, comment semua middleware kecuali HandleCors:

```php
$middleware->prepend(\App\Http\Middleware\HandleCors::class);

// Comment sementara untuk test
// if (app()->environment('production')) {
//     $middleware->append(\App\Http\Middleware\ForceHttps::class);
// }
// $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
// $middleware->append(\App\Http\Middleware\AllowElectronOrigin::class);
```

Test lagi. Jika berhasil, enable satu per satu untuk menemukan yang bermasalah.

