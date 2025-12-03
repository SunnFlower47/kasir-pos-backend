# Apache CORS Configuration

Jika menggunakan Apache, pastikan `.htaccess` **TIDAK menimpa** header CORS yang dikirim oleh Laravel.

## Konfigurasi .htaccess yang Benar

File `public/.htaccess` sudah benar dan tidak menimpa CORS headers.

## ❌ JANGAN Tambahkan Ini di .htaccess:

```apache
# JANGAN set CORS headers di .htaccess jika Laravel sudah handle
# Header set Access-Control-Allow-Origin "*"  # ❌ SALAH
```

## ✅ Jika Harus Set CORS di Apache (Tidak Disarankan):

Jika memang harus set CORS di Apache (misalnya untuk static files), gunakan:

```apache
<IfModule mod_headers.c>
    # Only for OPTIONS requests
    <If "%{REQUEST_METHOD} == 'OPTIONS'">
        # Get origin from request and check if allowed
        SetEnvIf Origin "^https://kasir-pos\.sunnflower\.site$" allowed_origin=$0
        
        # Set CORS headers only if origin is allowed
        Header always set Access-Control-Allow-Origin "%{allowed_origin}e" env=allowed_origin
        Header always set Access-Control-Allow-Methods "GET, POST, PUT, PATCH, DELETE, OPTIONS" env=allowed_origin
        Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With, X-Client-Type, X-Client-Version, Accept, Origin" env=allowed_origin
        Header always set Access-Control-Allow-Credentials "true" env=allowed_origin
    </If>
</IfModule>
```

**TAPI** ini tidak disarankan karena Laravel sudah menangani CORS dengan `HandleCors` middleware.

## Troubleshooting

1. **Cek apakah Apache menimpa headers:**
   ```bash
   curl -I -H "Origin: https://kasir-pos.sunnflower.site" \
        -X OPTIONS \
        https://kasir-pos-api.sunnflower.site/api/v1/profile
   ```

2. **Pastikan mod_headers enabled:**
   ```bash
   sudo a2enmod headers
   sudo systemctl restart apache2
   ```

3. **Cek Apache error logs:**
   ```bash
   tail -f /var/log/apache2/error.log
   ```

---

**Important**: Biarkan Laravel menangani CORS. Jangan set CORS headers di Apache kecuali ada alasan khusus.

