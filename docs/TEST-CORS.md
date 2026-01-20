# Test CORS - Cara Testing

## Test OPTIONS Request (Preflight)

### Test 1: Test ke Domain Production (dengan Cloudflare)

```bash
curl -v -X OPTIONS \
     -H "Origin: https://kasir-pos.sunnflower.site/" \
     -H "Access-Control-Request-Method: POST" \
     -H "Access-Control-Request-Headers: Content-Type,Authorization" \
     https://kasir-pos-api.sunnflower.site/api/v1/login
```

**Response yang diharapkan:**
```
HTTP/1.1 200 OK
Access-Control-Allow-Origin: https://kasir-pos.sunnflower.site/
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Client-Type, X-Client-Version, Accept, Origin
Access-Control-Allow-Credentials: true
Access-Control-Max-Age: 86400
```

### Test 2: Test ke Localhost (bypass Cloudflare)

Jika server backend ada di server yang sama, test langsung:

```bash
# Ganti dengan IP atau hostname server kamu
curl -v -X OPTIONS \
     -H "Origin: https://kasir-pos.sunnflower.site/" \
     -H "Access-Control-Request-Method: POST" \
     -H "Access-Control-Request-Headers: Content-Type,Authorization" \
     http://localhost/api/v1/login
```

Atau jika backend di port lain:

```bash
curl -v -X OPTIONS \
     -H "Origin: https://kasir-pos.sunnflower.site/" \
     -H "Access-Control-Request-Method: POST" \
     http://127.0.0.1:8000/api/v1/login
```

### Test 3: Test Actual Request (POST)

```bash
curl -v -X POST \
     -H "Origin: https://kasir-pos.sunnflower.site/" \
     -H "Content-Type: application/json" \
     -d '{"email":"test@example.com","password":"password"}' \
     https://kasir-pos-api.sunnflower.site/api/v1/login
```

**Response harus mengandung:**
```
Access-Control-Allow-Origin: https://kasir-pos.sunnflower.site/
Access-Control-Allow-Credentials: true
```

## Troubleshooting

### Jika OPTIONS request return 404 atau 500

Masalahnya di backend middleware atau routing.

### Jika OPTIONS request tidak ada CORS headers

1. Cek apakah `HandleCors` middleware ter-register
2. Cek apakah middleware berjalan (tambahkan logging)
3. Cek config cache

### Jika OPTIONS request ada CORS headers tapi browser masih error

1. Cek Cloudflare settings
2. Cek apakah origin yang dikirim browser sesuai dengan yang di-allow
3. Clear browser cache

## Debugging di Server

### Cek apakah middleware berjalan

Edit `app/Http/Middleware/HandleCors.php` sementara, tambahkan logging:

```php
public function handle(Request $request, Closure $next): Response
{
    // Debug logging
    \Log::info('HandleCors: Method=' . $request->getMethod() . ', Origin=' . $request->header('Origin'));
    
    // ... rest of code
}
```

Lalu cek log:
```bash
tail -f storage/logs/laravel.log
```

### Cek route list

```bash
php artisan route:list | grep api
```

### Test langsung PHP

```bash
php artisan tinker
```

Di dalam tinker:
```php
$request = Request::create('/api/v1/login', 'OPTIONS', [], [], [], [
    'HTTP_Origin' => 'https://kasir-pos.sunnflower.site/',
]);
$response = app()->handle($request);
$response->headers->all(); // Lihat semua headers
```

