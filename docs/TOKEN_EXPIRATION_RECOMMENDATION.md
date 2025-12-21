# Token Expiration Recommendation

## Analisis Kebutuhan Aplikasi POS

### Client Types:
1. **Mobile App** - Android/iOS (React Native/Expo)
2. **Web App** - Browser-based POS
3. **Electron Desktop App**

### Use Case:
- Cashier bekerja dalam shift (4-8 jam)
- Transaksi finansial (butuh security tinggi)
- Aplikasi digunakan sepanjang hari untuk transaksi

---

## Rekomendasi Token Expiration

### Option 1: Balanced (REKOMENDASI) ⭐

**12 jam (720 menit)**
- ✅ Balance antara security dan UX
- ✅ Cukup untuk shift kerja (8 jam) + buffer
- ✅ Tidak terlalu pendek (tidak ganggu transaksi)
- ✅ Tidak terlalu panjang (aman jika token bocor)

**Implementasi:**
```php
'expiration' => env('SANCTUM_TOKEN_EXPIRATION', 12 * 60), // 12 hours
```

---

### Option 2: Berdasarkan Client Type (ADVANCED)

**Mobile App:** 24 jam (1 hari)
- Device lebih secure (biometric, device lock)
- User tidak perlu login terlalu sering
- Better UX untuk mobile

**Web App:** 8 jam
- Web browser lebih rentan (shared computer)
- Lebih secure untuk web

**Implementasi:**
Perlu custom logic di `AuthController::login()` untuk set expiration berdasarkan client type.

---

### Option 3: Shift-Based

**8 jam (480 menit)**
- Sesuai dengan durasi shift kerja umum
- User harus login setiap shift baru
- Paling secure, tapi kurang nyaman

---

## Perbandingan

| Durasi | Security | UX | Rekomendasi |
|--------|----------|----|----|
| 4 jam | ⭐⭐⭐⭐⭐ | ⭐⭐ | Untuk web app (shared computer) |
| 8 jam | ⭐⭐⭐⭐ | ⭐⭐⭐ | Untuk shift-based POS |
| **12 jam** | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ | **Balanced - REKOMENDASI** |
| 24 jam | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | Untuk mobile app |
| 7 hari | ⭐⭐ | ⭐⭐⭐⭐⭐ | Terlalu panjang untuk production |

---

## Rekomendasi Final

### Untuk Aplikasi POS (Mobile + Web):

**12 jam (720 menit)** - **REKOMENDASI UTAMA**

**Alasan:**
1. ✅ Balance security vs UX yang baik
2. ✅ Cukup untuk shift kerja 8 jam + buffer
3. ✅ Tidak ganggu transaksi yang sedang berlangsung
4. ✅ Aman jika token bocor (masih reasonable expiry)
5. ✅ Cocok untuk mobile dan web app

**Alternatif:**
- Jika lebih prioritize security: **8 jam**
- Jika lebih prioritize UX (mobile-first): **24 jam**

---

## Implementasi Custom Expiration per Client Type (Optional)

Jika ingin implementasi berbeda untuk mobile vs web:

```php
// Di AuthController::login()
$clientType = $request->header('X-Client-Type');

// Set expiration berdasarkan client type
$expirationMinutes = match($clientType) {
    'mobile' => 24 * 60,  // 24 jam untuk mobile
    'electron' => 24 * 60, // 24 jam untuk desktop
    default => 12 * 60,    // 12 jam untuk web
};

$token = $user->createToken('auth_token', [], now()->addMinutes($expirationMinutes))->plainTextToken;
```

**Note:** Perlu modifikasi `AuthController::login()` dan `refresh()`.


