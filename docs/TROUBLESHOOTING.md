# Troubleshooting Guide

## 🔧 Common Issues & Solutions

### Error: "Target class [env] does not exist"

**Penyebab:**
- Penggunaan `env()` di config files yang nested (menggunakan `env()` untuk akses ke config lain)
- Config cache yang corrupt
- Penggunaan `env()` di closure atau fungsi kompleks saat config di-cache

**Solusi:**

1. **Clear semua cache:**
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

2. **Fix nested env() calls:**
Ganti nested `env()` dengan `config()`:
```php
// ❌ SALAH
'url' => env('APP_URL').'/storage',
'prefix' => Str::slug(env('APP_NAME')).'-cache-',

// ✅ BENAR
'url' => config('app.url').'/storage',
'prefix' => Str::slug(config('app.name')).'-cache-',
```

3. **Rebuild config cache (jika perlu):**
```bash
php artisan config:cache
```

**Note**: Setelah fix, jangan lupa commit perubahan config files ke repository.

---

### Error: "SQLSTATE[HY000] [2002] Connection refused"

**Penyebab:**
- Database server tidak berjalan
- Kredensial database salah di `.env`
- Host atau port database salah

**Solusi:**

1. **Cek database server:**
```bash
# MySQL
sudo systemctl status mysql

# Start MySQL
sudo systemctl start mysql
```

2. **Verifikasi kredensial di `.env`:**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kasir_pos
DB_USERNAME=root
DB_PASSWORD=your_password
```

3. **Test koneksi:**
```bash
php artisan tinker
>>> DB::connection()->getPdo();
```

---

### Error: "No application encryption key"

**Penyebab:**
- `APP_KEY` tidak ada di `.env`

**Solusi:**
```bash
php artisan key:generate
```

---

### Error: "The stream or file could not be opened"

**Penyebab:**
- Permission folder `storage` atau `bootstrap/cache` salah

**Solusi:**

**Linux/Mac:**
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

**Windows:**
- Pastikan folder tidak read-only
- Check folder permissions

---

### Error: 404 pada routes setelah deployment

**Penyebab:**
- Route cache tidak di-update
- `.htaccess` tidak ada atau salah

**Solusi:**

1. **Clear route cache:**
```bash
php artisan route:clear
php artisan route:cache
```

2. **Check .htaccess (Apache):**
Pastikan file ada dan dikonfigurasi dengan benar.

3. **Check web server config (Nginx):**
Pastikan rewrite rules sudah benar.

---

### Error: CORS error di frontend

**Penyebab:**
- CORS tidak dikonfigurasi dengan benar
- Frontend URL tidak ada di `allowed_origins`

**Solusi:**

1. **Check `config/cors.php`:**
```php
'allowed_origins' => [
    'https://kasir-pos.sunnflower.site',
    'http://localhost:4173',
],
```

2. **Check `.env`:**
```env
FRONTEND_URL=https://kasir-pos.sunnflower.site
```

3. **Clear config cache:**
```bash
php artisan config:clear
```

---

### Error: Token expired atau 401 Unauthorized

**Penyebab:**
- Token expired
- Token tidak dikirim dengan benar
- Session expired

**Solusi:**

1. **Frontend:**
   - Check apakah token masih ada di `localStorage`
   - Refresh token jika perlu
   - Re-login jika token expired

2. **Backend:**
   - Check Sanctum configuration
   - Check token expiration time

---

### Error: Permission denied (403)

**Penyebab:**
- User tidak memiliki permission
- Role tidak memiliki permission

**Solusi:**

1. **Check user permissions:**
```bash
php artisan tinker
>>> $user = User::find(1);
>>> $user->getAllPermissions();
```

2. **Assign permissions:**
```bash
php artisan tinker
>>> $user->givePermissionTo('products.view');
>>> $role->givePermissionTo('products.view');
```

---

### Error: ChunkLoadError di frontend production

**Penyebab:**
- Static files tidak di-serve dengan benar
- `.htaccess` tidak ada atau salah
- Base path salah

**Solusi:**

1. **Check `.htaccess`:**
Pastikan file ada di `build/` folder dan dikonfigurasi dengan benar.

2. **Check `package.json`:**
```json
"homepage": "/"
```

3. **Check `public/index.html`:**
```html
<base href="/" />
```

4. **Rebuild:**
```bash
npm run build:prod
```

---

### Error: Electron app tidak bisa connect ke API

**Penyebab:**
- CORS tidak allow Electron origin
- API URL salah
- Middleware `AllowElectronOrigin` tidak aktif

**Solusi:**

1. **Check middleware registered:**
File: `bootstrap/app.php`
```php
$middleware->append(\App\Http\Middleware\AllowElectronOrigin::class);
```

2. **Check custom header:**
Frontend harus mengirim `X-Client-Type: electron` header.

3. **Check API URL:**
```env
REACT_APP_API_URL=https://kasir-pos-api.sunnflower.site/api/v1
```

---

### Performance Issues

#### Slow Queries

**Check:**
```bash
# Enable query log
DB::enableQueryLog();
// ... your code ...
dd(DB::getQueryLog());
```

**Solutions:**
- Add database indexes
- Optimize queries dengan eager loading
- Use select specific columns

#### High Memory Usage

**Solutions:**
- Increase PHP memory limit: `memory_limit = 256M`
- Optimize queries
- Use pagination
- Clear cache regularly

---

## 🔍 Debug Mode

### Enable Debug Mode

**Development:**
```env
APP_DEBUG=true
```

**Production:**
```env
APP_DEBUG=false
```

### View Logs

```bash
tail -f storage/logs/laravel.log
```

### Check Environment

```bash
php artisan env
```

---

## 📞 Getting Help

Jika masalah masih terjadi:

1. Check logs: `storage/logs/laravel.log`
2. Enable debug mode (development only)
3. Check browser console (frontend)
4. Check network tab (API calls)

---

**Last Updated**: January 2025

