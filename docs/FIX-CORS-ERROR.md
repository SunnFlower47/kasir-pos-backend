# Fix: CORS Error - "No 'Access-Control-Allow-Origin' header is present"

## 🔧 Masalah

API requests dari frontend (`https://kasir-pos.sunnflower.site/`) ke backend (`https://kasir-pos-api.sunnflower.site`) diblokir oleh CORS policy. Error:
```
Access to XMLHttpRequest at 'https://kasir-pos-api.sunnflower.site/api/v1/profile' 
from origin 'https://kasir-pos.sunnflower.site/' has been blocked by CORS policy: 
Response to preflight request doesn't pass access control check: 
No 'Access-Control-Allow-Origin' header is present on the requested resource.
```

## ✅ Solusi yang Sudah Diterapkan

1. **`config/cors.php`** - Origin production sudah ditambahkan:
   ```php
   'allowed_origins' => [
       'https://kasir-pos.sunnflower.site/', // Production frontend
       'http://localhost:4173', // Local development
       'http://127.0.0.1:4173', // Local development
   ],
   ```

2. **`app/Http/Middleware/AllowElectronOrigin.php`** - Ditambahkan handling untuk preflight OPTIONS request

## 📋 Langkah Fix di Server Production

### Step 1: SSH ke Server dan Pull Perubahan

```bash
cd /path/to/kasir-pos-system
git pull origin main
```

### Step 2: Clear SEMUA Cache (SANGAT PENTING!)

```bash
# Clear semua cache
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Clear opcache jika menggunakan PHP-FPM
sudo systemctl reload php-fpm
# atau
sudo service php8.2-fpm reload
```

### Step 3: Verifikasi Config

```bash
# Test apakah config bisa di-cache tanpa error
php artisan config:cache
```

Jika ada error, jangan lanjutkan ke step berikutnya. Cek error message.

### Step 4: Rebuild Cache

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 5: Verifikasi CORS Config

```bash
# Cek apakah config CORS sudah benar
php artisan tinker
```

Di dalam tinker:
```php
config('cors.allowed_origins');
// Seharusnya menampilkan array dengan 'https://kasir-pos.sunnflower.site/'
```

### Step 6: Restart Web Server (Jika Perlu)

```bash
# Apache
sudo systemctl restart apache2
# atau
sudo service apache2 restart

# Nginx (jika menggunakan PHP-FPM)
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm
```

### Step 7: Test API dari Server

```bash
# Test apakah API mengirim CORS headers
curl -H "Origin: https://kasir-pos.sunnflower.site/" \
     -H "Access-Control-Request-Method: GET" \
     -H "Access-Control-Request-Headers: Content-Type,Authorization" \
     -X OPTIONS \
     https://kasir-pos-api.sunnflower.site/api/v1/profile \
     -v
```

Response harus mengandung header:
```
Access-Control-Allow-Origin: https://kasir-pos.sunnflower.site/
Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Client-Type, X-Client-Version
Access-Control-Allow-Credentials: true
```

## 🔍 Troubleshooting

### Jika Masih Error Setelah Clear Cache

1. **Cek apakah Laravel CORS package terinstall:**
   ```bash
   composer show fruitcake/laravel-cors
   ```
   Jika tidak terinstall, install dengan:
   ```bash
   composer require fruitcake/laravel-cors
   ```

2. **Cek `.htaccess` di `public/` folder:**
   Pastikan tidak ada aturan yang memblokir OPTIONS request atau mengubah headers.

3. **Cek web server config (Apache/Nginx):**
   Pastikan tidak ada aturan yang memblokir OPTIONS request atau mengubah CORS headers.

4. **Cek file permissions:**
   ```bash
   chmod -R 755 storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache
   ```

5. **Cek error logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

### Jika Error "Target class [env] does not exist"

Lihat dokumentasi: `docs/FIX-ENV-ERROR.md`

## ⚠️ Important Notes

1. **JANGAN skip Step 2 (Clear Cache)** - Ini adalah langkah paling penting!

2. **Config cache harus di-rebuild setelah pull perubahan** - Config cache yang lama tidak akan otomatis update.

3. **Web server mungkin perlu di-restart** - Terutama jika menggunakan PHP-FPM dengan opcache.

4. **Frontend `.htaccess` tidak mempengaruhi CORS** - CORS adalah masalah backend, bukan frontend.

## 📝 Checklist

- [ ] Pull perubahan dari git
- [ ] Clear semua cache (`config:clear`, `cache:clear`, `route:clear`, `view:clear`)
- [ ] Rebuild config cache (`config:cache`)
- [ ] Restart web server / PHP-FPM
- [ ] Test API dengan curl (OPTIONS request)
- [ ] Test dari browser (clear browser cache jika perlu)
- [ ] Verifikasi CORS headers ada di response

---

**Last Updated**: January 2025

