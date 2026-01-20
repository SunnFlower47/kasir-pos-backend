# Quick Fix: CORS 500 Error

Jika Anda mengalami HTTP 500 error setelah update middleware HandleCors, ikuti langkah ini:

## Langkah 1: Cek Error Log

```bash
tail -n 50 storage/logs/laravel.log
```

Cari error message untuk mengetahui penyebabnya.

## Langkah 2: Rollback ke Versi Sederhana (Emergency Fix)

Jika error masih terjadi, gunakan versi sederhana ini:

**Copy isi file `app/Http/Middleware/HandleCors.php` dengan ini:**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleCors
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('Origin');
        $isProduction = app()->environment('production');

        // Allowed origins for web clients
        $allowedOrigins = [
            'https://kasir-pos.sunnflower.site/',
        ];

        // Add development origins only in non-production
        if (!$isProduction) {
            $allowedOrigins = array_merge($allowedOrigins, [
                'http://localhost:4173',
                'http://127.0.0.1:4173',
                'http://localhost:8081',
                'http://127.0.0.1:8081',
            ]);
        }

        // Handle OPTIONS (preflight)
        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 200);
            
            if ($origin && in_array($origin, $allowedOrigins)) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
                $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Client-Type, X-Client-Version, Accept, Origin');
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
                $response->headers->set('Access-Control-Max-Age', '86400');
            }
            
            return $response;
        }

        // Handle actual request
        $response = $next($request);

        // Set CORS headers
        if ($origin && in_array($origin, $allowedOrigins)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Expose-Headers', 'Content-Disposition');
        }

        return $response;
    }
}
```

## Langkah 3: Clear Cache dan Restart

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan optimize:clear

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
# atau
sudo systemctl restart php-fpm
```

## Langkah 4: Test

```bash
curl -X OPTIONS \
  -H "Origin: https://kasir-pos.sunnflower.site/" \
  -H "Access-Control-Request-Method: GET" \
  -i \
  https://kasir-pos-api.sunnflower.site/api/v1/dashboard
```

Harusnya return HTTP 200 dengan CORS headers.

## Common Causes of 500 Error

1. **PHP Version**: Pastikan PHP >= 8.0
2. **Syntax Error**: Cek dengan `php -l app/Http/Middleware/HandleCors.php`
3. **Memory Limit**: Cek `php.ini` memory_limit
4. **Permission**: Pastikan file bisa di-read oleh web server

## Verify PHP Version

```bash
php -v
```

Harusnya PHP >= 8.0 (atau sesuai requirement Laravel 12).


