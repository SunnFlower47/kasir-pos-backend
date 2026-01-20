# Check Error Log untuk Debug 500 Error

## Error 500 pada OPTIONS Request

Jika OPTIONS request mengembalikan `HTTP/2 500`, berarti backend crash. Kita perlu cek error log untuk mengetahui penyebabnya.

## Langkah Debugging

### 1. Cek Laravel Error Log

```bash
# Di server backend
tail -f storage/logs/laravel.log
```

**Test OPTIONS request lagi** dan lihat error message yang muncul di log.

### 2. Cek PHP Error Log

```bash
# PHP-FPM error log
tail -f /var/log/php8.2-fpm.log

# Atau Apache error log
tail -f /var/log/apache2/error.log

# Atau cPanel error log (jika pakai cPanel)
tail -f ~/logs/error_log
```

### 3. Test Apakah HandleCors Berjalan

Edit `app/Http/Middleware/HandleCors.php` sementara, tambahkan logging di baris pertama:

```php
public function handle(Request $request, Closure $next): Response
{
    // DEBUG: Log untuk test
    error_log('HandleCors: Method=' . $request->getMethod());
    error_log('HandleCors: Origin=' . $request->header('Origin'));
    
    // ... rest of code
}
```

Lalu test lagi OPTIONS request dan cek:
- `/var/log/php8.2-fpm.log` atau
- `storage/logs/laravel.log`

### 4. Clear SEMUA Cache

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

### 5. Test Route

```bash
# Cek route list
php artisan route:list | grep login

# Test route dengan tinker
php artisan tinker
```

Di dalam tinker:
```php
$request = Request::create('/api/v1/login', 'OPTIONS', [], [], [], [
    'HTTP_Origin' => 'https://kasir-pos.sunnflower.site/',
]);
$response = app()->handle($request);
dd($response->getStatusCode(), $response->headers->all());
```

### 6. Minimal Test: Bypass Semua Middleware

Edit `bootstrap/app.php` sementara, comment semua middleware kecuali HandleCors:

```php
$middleware->prepend(\App\Http\Middleware\HandleCors::class);

// Comment semua middleware lain sementara
// if (app()->environment('production')) {
//     $middleware->append(\App\Http\Middleware\ForceHttps::class);
// }
// $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
// $middleware->append(\App\Http\Middleware\AllowElectronOrigin::class);
```

Test lagi. Jika berhasil, masalahnya di salah satu middleware lain.

---

**Setelah menemukan error message dari log, kirim error message tersebut untuk analisis lebih lanjut.**

