# Nginx CORS Configuration

Jika menggunakan Nginx sebagai reverse proxy, pastikan konfigurasi Nginx **TIDAK menimpa** header CORS yang dikirim oleh Laravel.

## Konfigurasi Nginx yang Benar

```nginx
server {
    listen 443 ssl http2;
    server_name kasir-pos-api.sunnflower.site;

    # SSL configuration...
    
    root /path/to/kasir-pos-system/public;
    index index.php;

    # IMPORTANT: DO NOT set CORS headers in Nginx
    # Let Laravel handle CORS via HandleCors middleware
    # If you set CORS headers in Nginx, they might conflict with Laravel's headers

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        # IMPORTANT: Preserve headers from Laravel
        fastcgi_pass_header Access-Control-Allow-Origin;
        fastcgi_pass_header Access-Control-Allow-Methods;
        fastcgi_pass_header Access-Control-Allow-Headers;
        fastcgi_pass_header Access-Control-Allow-Credentials;
    }

    # Security headers (optional, Laravel already sets these)
    # But if you want to set them in Nginx, make sure they don't interfere with CORS
    # add_header X-Content-Type-Options "nosniff" always;
    # add_header X-Frame-Options "SAMEORIGIN" always;
    # add_header X-XSS-Protection "1; mode=block" always;
}
```

## ❌ JANGAN Lakukan Ini di Nginx:

```nginx
# JANGAN set CORS headers di Nginx jika Laravel sudah handle
# Ini akan menimpa atau conflict dengan Laravel's CORS headers
# add_header 'Access-Control-Allow-Origin' '*' always;  # ❌ SALAH
```

## ✅ Jika Harus Set CORS di Nginx (Tidak Disarankan):

Jika memang harus set CORS di Nginx (misalnya untuk static files), gunakan:

```nginx
location /api/ {
    # Get origin from request
    set $cors_origin "";
    if ($http_origin ~* "^https://kasir-pos\.sunnflower\.site$") {
        set $cors_origin $http_origin;
    }
    
    # Proxy to Laravel
    proxy_pass http://127.0.0.1:8000;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    
    # Let Laravel handle CORS, don't set here
    # Laravel's HandleCors middleware will set CORS headers
}
```

## Troubleshooting

1. **Cek apakah Nginx menimpa headers:**
   ```bash
   curl -I -H "Origin: https://kasir-pos.sunnflower.site" \
        -X OPTIONS \
        https://kasir-pos-api.sunnflower.site/api/v1/profile
   ```

2. **Cek Nginx error logs:**
   ```bash
   tail -f /var/log/nginx/error.log
   ```

3. **Reload Nginx setelah perubahan:**
   ```bash
   sudo nginx -t
   sudo systemctl reload nginx
   ```

---

**Important**: Biarkan Laravel menangani CORS. Jangan set CORS headers di Nginx kecuali ada alasan khusus.

