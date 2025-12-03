# Prinsip IMMUTABLE TRANSACTION RECORDS

## 📋 Konsep Dasar

**IMMUTABLE TRANSACTION RECORDS** adalah prinsip fundamental dalam sistem POS yang menyatakan bahwa:

> **Data transaksi historis TIDAK BOLEH BERUBAH, walaupun harga produk (beli, jual, grosir) berubah di kemudian hari.**

Transaksi menyimpan **SNAPSHOT kondisi final** pada saat transaksi dibuat, bukan referensi ke data produk yang bisa berubah.

---

## ✅ Data yang Disimpan sebagai Snapshot

### 1. **Harga Jual (Selling Price)**
- **Kolom**: `transaction_items.unit_price`
- **Status**: ✅ **Sudah Implemented**
- **Penjelasan**: Menyimpan harga final yang digunakan saat transaksi (bisa selling_price atau wholesale_price)

### 2. **Harga Beli (Purchase Price / COGS)**
- **Kolom**: `transaction_items.purchase_price`
- **Status**: ✅ **Sudah Implemented**
- **Penjelasan**: Snapshot harga beli produk saat transaksi dibuat

### 3. **Harga Grosir (Wholesale Price)**
- **Status**: ✅ **Sudah Tercover**
- **Penjelasan**: Harga grosir sudah tercakup dalam `unit_price` jika user memilih harga grosir saat transaksi

### 4. **Data Transaksi Lainnya**
- **Quantity**: ✅ Disimpan snapshot
- **Discount**: ✅ Disimpan snapshot
- **Total Price**: ✅ Disimpan snapshot (calculated)
- **Transaction Date**: ✅ Disimpan snapshot
- **Customer**: ✅ Referensi (ID) - OK karena customer tidak berubah

---

## 🔒 Prinsip Immutability

### ✅ Yang TIDAK Berubah

1. **Harga Jual** (`unit_price`)
   - Jika harga jual produk berubah → transaksi lama tetap menggunakan harga lama

2. **Harga Beli** (`purchase_price`)
   - Jika harga beli produk berubah → transaksi lama tetap menggunakan harga beli lama
   - COGS dihitung dari snapshot, bukan harga saat ini

3. **Harga Grosir**
   - Jika harga grosir produk berubah → transaksi yang sudah menggunakan harga grosir tetap menggunakan harga grosir lama

4. **Quantity, Discount, Total**
   - Semua sudah final dan tidak berubah

### ❌ Yang BOLEH Berubah (Untuk Update Data)

1. **Status Transaksi**
   - Bisa diubah: `pending` → `completed` → `refunded`
   - Bisa ditambahkan: `refunded_at`, `refunded_by`, `refund_reason`

2. **Notes**
   - Bisa diupdate untuk menambahkan catatan

---

## 📊 Struktur Tabel `transaction_items`

```sql
CREATE TABLE transaction_items (
    id BIGINT PRIMARY KEY,
    transaction_id BIGINT,           -- Referensi ke transaksi
    product_id BIGINT,                -- Referensi ke produk (untuk display)
    quantity INTEGER,                 -- ✅ SNAPSHOT: Jumlah yang dibeli
    unit_price DECIMAL(15,2),         -- ✅ SNAPSHOT: Harga jual/grosir final
    purchase_price DECIMAL(15,2),     -- ✅ SNAPSHOT: Harga beli (COGS)
    discount_amount DECIMAL(15,2),    -- ✅ SNAPSHOT: Diskon
    total_price DECIMAL(15,2),        -- ✅ SNAPSHOT: Total harga
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Catatan Penting:**
- `product_id` adalah referensi untuk display, bukan untuk mengambil harga
- Semua harga disimpan sebagai snapshot, bukan diambil dari produk

---

## 🔍 Query Pattern yang BENAR

### ✅ Menggunakan Snapshot Data

```php
// BENAR: Menggunakan snapshot dari transaction_items
$cogs = TransactionItem::where('transaction_id', $id)
    ->sum(DB::raw('quantity * purchase_price'));

$revenue = TransactionItem::where('transaction_id', $id)
    ->sum('total_price');
```

### ❌ Jangan Menggunakan Harga Produk Saat Ini

```php
// SALAH: Menggunakan harga produk saat ini (bisa berubah)
$cogs = TransactionItem::join('products', 'transaction_items.product_id', '=', 'products.id')
    ->where('transaction_id', $id)
    ->sum(DB::raw('transaction_items.quantity * products.purchase_price')); // ❌ SALAH!
```

---

## 📋 Checklist Verifikasi

### Database Schema
- [x] Kolom `unit_price` ada di `transaction_items`
- [x] Kolom `purchase_price` ada di `transaction_items`
- [x] Kolom `discount_amount` ada di `transaction_items`
- [x] Kolom `total_price` ada di `transaction_items`
- [x] Semua kolom menggunakan tipe `DECIMAL` untuk akurasi

### Backend Implementation
- [x] `TransactionController` menyimpan snapshot `unit_price`
- [x] `TransactionController` menyimpan snapshot `purchase_price`
- [x] `FinancialReportController` menggunakan snapshot untuk COGS
- [x] `ReportController` menggunakan snapshot untuk COGS
- [x] `EnhancedReportController` menggunakan snapshot untuk COGS
- [x] `AdvancedReportController` menggunakan snapshot untuk COGS

### Query Pattern
- [x] Tidak ada query yang menggunakan `products.purchase_price` untuk transaksi lama
- [x] Tidak ada query yang menggunakan `products.selling_price` untuk transaksi lama
- [x] Semua query COGS menggunakan `transaction_items.purchase_price`
- [x] Semua query revenue menggunakan `transaction_items.unit_price` atau `total_price`

---

## 🎯 Contoh Skenario

### Skenario 1: Perubahan Harga Jual

**Tanggal 1 Januari 2025:**
- Produk A: Harga Jual = Rp 15.000
- Transaksi: Jual 10 pcs → Total = Rp 150.000
- `transaction_items.unit_price` = Rp 15.000 ✅

**Tanggal 5 Januari 2025:**
- Produk A: Harga Jual diubah menjadi Rp 20.000

**Hasil:**
- ✅ Transaksi 1 Januari tetap menampilkan Rp 15.000/pcs
- ✅ Total transaksi tetap Rp 150.000
- ✅ Laporan keuangan periode 1 Januari tetap akurat

### Skenario 2: Perubahan Harga Beli

**Tanggal 1 Januari 2025:**
- Produk A: Harga Beli = Rp 10.000
- Transaksi: Jual 10 pcs @ Rp 15.000
- COGS = 10 × Rp 10.000 = Rp 100.000
- Laba Kotor = Rp 150.000 - Rp 100.000 = Rp 50.000
- `transaction_items.purchase_price` = Rp 10.000 ✅

**Tanggal 5 Januari 2025:**
- Produk A: Harga Beli diubah menjadi Rp 12.000

**Hasil:**
- ✅ Transaksi 1 Januari tetap menggunakan COGS Rp 100.000
- ✅ Laba Kotor tetap Rp 50.000
- ✅ Laporan keuangan periode 1 Januari tetap akurat
- ✅ Transaksi baru (setelah 5 Januari) menggunakan COGS baru Rp 12.000

### Skenario 3: Perubahan Harga Grosir

**Tanggal 1 Januari 2025:**
- Produk A: Harga Grosir = Rp 13.000
- Transaksi: Jual 10 pcs dengan harga grosir → Total = Rp 130.000
- `transaction_items.unit_price` = Rp 13.000 ✅

**Tanggal 5 Januari 2025:**
- Produk A: Harga Grosir diubah menjadi Rp 14.000

**Hasil:**
- ✅ Transaksi 1 Januari tetap menampilkan Rp 13.000/pcs
- ✅ Total transaksi tetap Rp 130.000
- ✅ Laporan keuangan tetap akurat

---

## 🚫 Larangan Penting

### ❌ JANGAN LAKUKAN:

1. **Update harga di transaksi lama**
   ```php
   // SALAH - Jangan update harga di transaksi yang sudah completed
   TransactionItem::where('transaction_id', $id)
       ->update(['unit_price' => $newPrice]); // ❌
   ```

2. **Menggunakan harga produk saat ini untuk query transaksi lama**
   ```php
   // SALAH - Harga bisa berubah
   $cogs = TransactionItem::join('products', ...)
       ->sum('products.purchase_price'); // ❌
   ```

3. **Recalculate transaksi yang sudah completed**
   ```php
   // SALAH - Jangan recalculate transaksi yang sudah final
   $transaction->recalculate(); // ❌
   ```

### ✅ BOLEH LAKUKAN:

1. **Update status transaksi**
   ```php
   // BENAR - Status bisa berubah
   $transaction->update(['status' => 'refunded']); // ✅
   ```

2. **Update notes**
   ```php
   // BENAR - Notes bisa ditambahkan
   $transaction->update(['notes' => 'Updated notes']); // ✅
   ```

3. **Tambah data refund**
   ```php
   // BENAR - Data refund bisa ditambahkan
   $transaction->update([
       'refunded_at' => now(),
       'refunded_by' => $user->id
   ]); // ✅
   ```

---

## 🔧 Maintenance & Best Practices

### 1. Audit Query Patterns

Secara berkala, audit semua query yang mengakses transaksi untuk memastikan:
- Menggunakan snapshot data, bukan harga produk saat ini
- Tidak ada perhitungan ulang (recalculation) untuk transaksi lama

### 2. Code Review Checklist

Saat menambah fitur baru, pastikan:
- [ ] Apakah menggunakan snapshot data atau harga produk saat ini?
- [ ] Apakah query akan terpengaruh jika harga produk berubah?
- [ ] Apakah laporan keuangan akan tetap akurat?

### 3. Testing

Test dengan skenario:
1. Buat transaksi dengan harga tertentu
2. Ubah harga produk
3. Verifikasi transaksi lama tidak berubah
4. Verifikasi laporan keuangan tetap akurat

---

## 📚 Referensi

- [Price Change Impact Documentation](./PRICE_CHANGE_IMPACT.md)
- [Price Snapshot Implementation](./PRICE_SNAPSHOT_IMPLEMENTATION.md)

---

## 🎯 Kesimpulan

Prinsip **IMMUTABLE TRANSACTION RECORDS** memastikan:
1. ✅ Data transaksi historis tetap akurat
2. ✅ Laporan keuangan tidak berubah meski harga produk berubah
3. ✅ Audit trail lengkap dan reliable
4. ✅ Compliance dengan prinsip akuntansi yang benar

**Semua data harga disimpan sebagai snapshot pada saat transaksi dibuat, bukan sebagai referensi ke data produk yang bisa berubah.**

---

**Dibuat**: 2025-12-02  
**Status**: ✅ Fully Implemented  
**Prinsip**: 🔒 IMMUTABLE TRANSACTION RECORDS

