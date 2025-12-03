# Implementasi Snapshot Harga Beli untuk Laporan Keuangan

## ✅ Status: Implementasi Selesai

Semua perubahan telah berhasil diimplementasikan untuk menyimpan snapshot harga beli saat transaksi dibuat, sehingga perubahan harga beli produk tidak akan mempengaruhi laporan keuangan yang sudah ada.

---

## 📋 Perubahan yang Dilakukan

### 1. Database Migration
- **File**: `database/migrations/2025_12_02_090026_add_purchase_price_to_transaction_items_table.php`
- **Tindakan**: Menambahkan kolom `purchase_price` di tabel `transaction_items`
- **Backfill**: Data lama akan di-backfill dengan harga beli produk saat ini

### 2. Model Update
- **File**: `app/Models/TransactionItem.php`
- **Perubahan**:
  - Menambahkan `purchase_price` ke `$fillable`
  - Menambahkan casting untuk `purchase_price` sebagai `decimal:2`

### 3. Controller Updates

#### TransactionController
- **File**: `app/Http/Controllers/Api/TransactionController.php`
- **Perubahan**: Menyimpan snapshot `purchase_price` saat transaksi dibuat
```php
'purchase_price' => $product->purchase_price, // Store snapshot
```

#### FinancialReportController
- **File**: `app/Http/Controllers/Api/FinancialReportController.php`
- **Perubahan**: Menggunakan `transaction_items.purchase_price` untuk perhitungan COGS
- **Query Updated**: 
  - Total COGS
  - COGS by category
  - COGS by product
  - Monthly COGS
  - Overall COGS

#### ReportController
- **File**: `app/Http/Controllers/Api/ReportController.php`
- **Perubahan**: Menggunakan snapshot harga beli untuk:
  - Total COGS
  - Top products profit calculation
  - Daily COGS

#### EnhancedReportController
- **File**: `app/Http/Controllers/Api/EnhancedReportController.php`
- **Perubahan**: Total COGS calculation

#### AdvancedReportController
- **File**: `app/Http/Controllers/Api/AdvancedReportController.php`
- **Perubahan**: 
  - Top products profit calculation
  - Total COGS
  - Comprehensive analysis COGS

### 4. Frontend Types
- **File**: `kasir-pos-frontend/src/types/index.ts`
- **Perubahan**: Menambahkan `purchase_price?` (optional) ke interface `TransactionItem`

---

## 🔄 Pola Perubahan Query

**Sebelum:**
```php
SUM(transaction_items.quantity * products.purchase_price)
```

**Sesudah:**
```php
SUM(transaction_items.quantity * COALESCE(transaction_items.purchase_price, products.purchase_price))
```

**Alasan menggunakan COALESCE:**
- Data lama mungkin belum memiliki `purchase_price` (sebelum migration)
- Fallback ke `products.purchase_price` untuk backward compatibility
- Setelah migration dijalankan, semua record akan memiliki `purchase_price`

---

## 🚀 Cara Menjalankan Migration

```bash
cd kasir-pos-system
php artisan migrate
```

Migration ini akan:
1. Menambahkan kolom `purchase_price` ke tabel `transaction_items`
2. Backfill data lama dengan harga beli produk saat ini

---

## ✅ Keuntungan Implementasi Ini

1. **Akurasi Laporan**: Laporan keuangan tidak berubah meski harga beli produk diubah
2. **Audit Trail**: Harga beli saat transaksi tersimpan sebagai snapshot
3. **Konsistensi**: Sama seperti cara menyimpan harga jual (`unit_price`)
4. **Backward Compatible**: Menggunakan COALESCE untuk data lama

---

## 📊 Contoh Dampak

### Sebelum Implementasi:
- Transaksi: Jual 10 pcs @ Rp 15.000 (COGS: Rp 10.000/pcs)
- Laba Kotor = Rp 50.000
- Harga beli naik menjadi Rp 12.000
- **Laporan berubah**: Laba Kotor = Rp 30.000 ❌

### Sesudah Implementasi:
- Transaksi: Jual 10 pcs @ Rp 15.000 (COGS: Rp 10.000/pcs - SNAPSHOT)
- Laba Kotor = Rp 50.000
- Harga beli naik menjadi Rp 12.000
- **Laporan tetap**: Laba Kotor = Rp 50.000 ✅

---

## 🔍 Testing Checklist

- [ ] Jalankan migration
- [ ] Buat transaksi baru, cek apakah `purchase_price` tersimpan
- [ ] Ubah harga beli produk
- [ ] Cek laporan keuangan periode lama (harus tetap sama)
- [ ] Cek laporan keuangan periode baru (menggunakan harga baru)
- [ ] Verifikasi semua jenis laporan (Financial, Enhanced, Advanced)

---

**Dibuat**: 2025-12-02  
**Status**: ✅ Implementasi Selesai

