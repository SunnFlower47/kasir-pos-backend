# Troubleshooting CORS Issues

## Problem: "No 'Access-Control-Allow-Origin' header is present"

Jika Anda mengalami error CORS di production, ikuti langkah-langkah berikut:

### 1. Pastikan Middleware HandleCors Berjalan

**Cek di `bootstrap/app.php`:**
```php
// CORS FIRST - handle OPTIONS before anything else
$middleware->prepend(\App\Http\Middleware\HandleCors::class);
```

### 2. Pastikan Origin Ada di Whitelist

**Cek di `app/Http/Middleware/HandleCors.php`:**
```php
$allowedWebOrigins = [
    'https://kasir-pos.sunnflower.site', // Pastikan origin production ada di sini
];
```

### 3. Web Server Configuration

**CRITICAL:** Web server (Nginx/Apache) **TIDAK BOLEH** menangani OPTIONS request. Semua request (termasuk OPTIONS) harus diteruskan ke Laravel.

#### Nginx Configuration

```nginx
server {
    listen 443 ssl http2;
    server_name kasir-pos-api.sunnflower.site;

    # CRITICAL: Don't handle OPTIONS - let Laravel handle it
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        # CRITICAL: Don't add CORS headers here - Laravel middleware handles it
    }
}
```

**JANGAN tambahkan CORS headers di Nginx jika menggunakan Laravel middleware!**

#### Apache Configuration

```apache
<VirtualHost *:443>
    ServerName kasir-pos-api.sunnflower.site
    
    # CRITICAL: Don't handle OPTIONS - let Laravel handle it
    # Don't add CORS headers here if using Laravel middleware
    
    <Directory /var/www/kasir-pos-system/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 4. Test Preflight Request

```bash
# Test OPTIONS (preflight) request
curl -X OPTIONS \
  -H "Origin: https://kasir-pos.sunnflower.site" \
  -H "Access-Control-Request-Method: GET" \
  -H "Access-Control-Request-Headers: Authorization,Content-Type" \
  -v \
  https://kasir-pos-api.sunnflower.site/api/v1/dashboard
```

**Expected Response Headers:**
```
HTTP/1.1 200 OK
Access-Control-Allow-Origin: https://kasir-pos.sunnflower.site
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Client-Type, X-Client-Version, Accept, Origin
Access-Control-Allow-Credentials: true
Access-Control-Max-Age: 86400
```

### 5. Test Actual Request

```bash
# Test actual GET request
curl -X GET \
  -H "Origin: https://kasir-pos.sunnflower.site" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -v \
  https://kasir-pos-api.sunnflower.site/api/v1/dashboard
```

**Expected Response Headers:**
```
HTTP/1.1 200 OK
Access-Control-Allow-Origin: https://kasir-pos.sunnflower.site
Access-Control-Allow-Credentials: true
Access-Control-Expose-Headers: Content-Disposition
```

### 6. Debugging Steps

1. **Cek Environment:**
   ```bash
   php artisan tinker
   >>> app()->environment() // Should return 'production'
   ```

2. **Cek Origin Header:**
   - Buka browser DevTools → Network tab
   - Lihat request yang gagal
   - Cek apakah header `Origin` dikirim dengan benar

3. **Cek Laravel Logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

4. **Test dengan curl:**
   ```bash
   # Simulate preflight
   curl -X OPTIONS -H "Origin: https://kasir-pos.sunnflower.site" \
     -H "Access-Control-Request-Method: GET" \
     -v https://kasir-pos-api.sunnflower.site/api/v1/dashboard 2>&1 | grep -i "access-control"
   ```

### 7. Common Issues

#### Issue 1: Web Server Handling OPTIONS
**Symptom:** Preflight request tidak sampai ke Laravel  
**Solution:** Pastikan web server meneruskan semua request (termasuk OPTIONS) ke Laravel

#### Issue 2: Origin Mismatch
**Symptom:** Origin tidak match karena case sensitivity atau trailing slash  
**Solution:** Middleware sudah menggunakan case-insensitive comparison, pastikan origin exact match

#### Issue 3: Cache
**Symptom:** Perubahan tidak terlihat  
**Solution:**
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

#### Issue 4: Multiple CORS Headers
**Symptom:** Browser melihat duplicate CORS headers  
**Solution:** Pastikan hanya Laravel middleware yang menambahkan CORS headers, bukan web server

### 8. Verification Checklist

- [ ] `HandleCors` middleware di-register di `bootstrap/app.php`
- [ ] Origin production ada di `$allowedWebOrigins` array
- [ ] Web server tidak menangani OPTIONS request
- [ ] Web server tidak menambahkan CORS headers
- [ ] `APP_ENV=production` di `.env`
- [ ] Cache cleared setelah deploy
- [ ] PHP-FPM restarted setelah deploy

### 9. Emergency Fix (Temporary)

Jika sangat urgent dan perlu fix cepat, tambahkan CORS headers di web server:

**Nginx (TEMPORARY FIX - NOT RECOMMENDED):**
```nginx
location /api {
    if ($request_method = 'OPTIONS') {
        add_header 'Access-Control-Allow-Origin' 'https://kasir-pos.sunnflower.site';
        add_header 'Access-Control-Allow-Methods' 'GET, POST, PUT, PATCH, DELETE, OPTIONS';
        add_header 'Access-Control-Allow-Headers' 'Authorization, Content-Type, X-Requested-With, X-Client-Type';
        add_header 'Access-Control-Allow-Credentials' 'true';
        add_header 'Access-Control-Max-Age' '86400';
        return 204;
    }
    
    # ... rest of config
}
```

**Catatan:** Ini hanya temporary fix. Setelah middleware Laravel bekerja, hapus CORS headers dari web server.


