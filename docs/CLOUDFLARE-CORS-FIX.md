# Fix: CORS Error dengan Cloudflare

## 🔧 Masalah dengan Cloudflare

Cloudflare bisa memblokir atau menginterfere dengan CORS preflight requests (OPTIONS), menyebabkan:
- OPTIONS request tidak sampai ke server
- OPTIONS request diubah/diblokir oleh Cloudflare security rules
- Header CORS tidak muncul karena Cloudflare cache

## ✅ Solusi

### 1. Cloudflare Dashboard Settings

#### A. Security Level
1. Login ke Cloudflare Dashboard
2. Pilih domain `kasir-pos-api.sunnflower.site`
3. **Security** → **Settings**
4. Set **Security Level** ke **Medium** atau **Low** (jangan **High**)
   - High bisa memblokir OPTIONS request

#### B. Firewall Rules
1. **Security** → **WAF** → **Custom Rules**
2. Buat rule untuk **ALLOW OPTIONS requests**:
   ```
   (http.request.method eq "OPTIONS")
   ```
   Action: **Allow**

#### C. Page Rules (Optional)
1. **Rules** → **Page Rules**
2. Buat rule untuk `/api/*`:
   - **Cache Level**: Bypass
   - **Security Level**: Medium
   - **Disable Security**: OFF

### 2. SSL/TLS Settings

1. **SSL/TLS** → **Overview**
2. Set **SSL/TLS encryption mode** ke **Full (strict)**
   - JANGAN pakai **Flexible** (akan menyebabkan masalah CORS)
   - **Full (strict)** = Cloudflare → HTTPS → Backend HTTPS

### 3. Caching Settings

1. **Caching** → **Configuration**
2. Untuk `/api/*` routes:
   - **Cache Level**: Bypass
   - Atau set **Browser Cache TTL**: 2 hours (bukan "Respect Existing Headers")

### 4. Transform Rules (Cloudflare Workers - Advanced)

Jika masih bermasalah, bisa pakai Cloudflare Workers untuk handle CORS:

```javascript
addEventListener('fetch', event => {
  event.respondWith(handleRequest(event.request))
})

async function handleRequest(request) {
  // Handle preflight
  if (request.method === 'OPTIONS') {
    return new Response(null, {
      status: 204,
      headers: {
        'Access-Control-Allow-Origin': 'https://kasir-pos.sunnflower.site/',
        'Access-Control-Allow-Methods': 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
        'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-Requested-With, X-Client-Type, X-Client-Version, Accept, Origin',
        'Access-Control-Allow-Credentials': 'true',
        'Access-Control-Max-Age': '86400',
      }
    })
  }

  // Forward request to origin
  const response = await fetch(request)
  
  // Add CORS headers to response
  const newHeaders = new Headers(response.headers)
  newHeaders.set('Access-Control-Allow-Origin', 'https://kasir-pos.sunnflower.site/')
  newHeaders.set('Access-Control-Allow-Credentials', 'true')
  
  return new Response(response.body, {
    status: response.status,
    statusText: response.statusText,
    headers: newHeaders
  })
}
```

## 📋 Checklist

- [ ] Security Level di Cloudflare = Medium/Low (bukan High)
- [ ] Firewall rule untuk ALLOW OPTIONS requests
- [ ] SSL/TLS mode = Full (strict) (bukan Flexible)
- [ ] Cache untuk `/api/*` = Bypass
- [ ] Test OPTIONS request langsung ke origin (bypass Cloudflare)
- [ ] Test dengan Cloudflare disabled sementara

## 🔍 Test Tanpa Cloudflare

Untuk test apakah masalahnya dari Cloudflare:

1. **Temporary disable Cloudflare:**
   - Cloudflare Dashboard → **Overview** → **Pause Cloudflare on Site**
   - Atau ubah DNS A record langsung ke IP server (bypass Cloudflare)

2. **Test OPTIONS request:**
   ```bash
   curl -v -X OPTIONS \
        -H "Origin: https://kasir-pos.sunnflower.site/" \
        -H "Access-Control-Request-Method: POST" \
        https://kasir-pos-api.sunnflower.site/api/v1/login
   ```

3. **Jika berhasil tanpa Cloudflare:**
   - Masalahnya di Cloudflare settings
   - Ikuti solusi di atas

4. **Jika masih error tanpa Cloudflare:**
   - Masalahnya di server/backend
   - Cek middleware dan config Laravel

## ⚠️ Important Notes

1. **Jangan pakai Cloudflare Flexible SSL** - akan menyebabkan masalah CORS
2. **Security Level High** bisa memblokir OPTIONS request
3. **Cache** bisa menyimpan response lama tanpa CORS headers
4. **Firewall rules** harus allow OPTIONS method

---

**Last Updated**: January 2025

