# Dampak Perubahan Harga Barang terhadap Laporan Keuangan

## 📋 Ringkasan

Sistem saat ini memiliki **dua perilaku berbeda** dalam menangani perubahan harga:

1. ✅ **Harga Jual (Selling Price)** - **AMAN**: Disimpan sebagai snapshot saat transaksi
2. ⚠️ **Harga Beli (Purchase Price)** - **BERMASALAH**: Menggunakan harga saat ini untuk menghitung laporan lama

---

## 🔍 Bagaimana Sistem Bekerja

### 1. Saat Transaksi Dibuat

**File**: `app/Http/Controllers/Api/TransactionController.php` (line 206-217)

```php
// Harga jual disimpan sebagai snapshot
$unitPrice = $item['unit_price'] ?? $product->selling_price;
$totalPrice = ($unitPrice * $item['quantity']) - $itemDiscount;

TransactionItem::create([
    'transaction_id' => $transaction->id,
    'product_id' => $product->id,
    'quantity' => $item['quantity'],
    'unit_price' => $unitPrice,  // ✅ DISIMPAN PERMANEN
    'discount_amount' => $itemDiscount,
    'total_price' => $totalPrice,
]);
```

**Kesimpulan**: 
- ✅ Harga jual **disimpan permanen** di `transaction_items.unit_price`
- ✅ Jika harga jual produk berubah, **transaksi lama tidak terpengaruh**

### 2. Saat Menghitung COGS (Cost of Goods Sold)

**File**: `app/Http/Controllers/Api/FinancialReportController.php` (line 334)

```php
$totalCOGS = (clone $baseQuery)->sum(
    DB::raw('transaction_items.quantity * products.purchase_price')
);
```

**File**: `app/Http/Controllers/Api/ReportController.php` (line 762)

```php
DB::raw('SUM(transaction_items.quantity * products.purchase_price) as total_cogs')
```

**Kesimpulan**:
- ⚠️ COGS menggunakan `products.purchase_price` **saat ini**
- ⚠️ Jika harga beli produk berubah, **laporan keuangan lama akan ikut berubah**

---

## 📊 Contoh Dampak

### Skenario:

1. **Tanggal 1 Januari 2025**:
   - Barang A: Harga Beli = Rp 10.000, Harga Jual = Rp 15.000
   - Transaksi: Jual 10 pcs → Pendapatan = Rp 150.000
   - COGS = 10 × Rp 10.000 = Rp 100.000
   - **Laba Kotor = Rp 150.000 - Rp 100.000 = Rp 50.000** ✅

2. **Tanggal 5 Januari 2025**:
   - Harga beli Barang A naik menjadi Rp 12.000 (tidak ada transaksi)

3. **Laporan Keuangan Periode 1-10 Januari (dibuka tanggal 11 Januari)**:
   - Pendapatan = Rp 150.000 ✅ (tetap, karena dari snapshot)
   - COGS = 10 × Rp 12.000 = **Rp 120.000** ❌ (berubah!)
   - **Laba Kotor = Rp 150.000 - Rp 120.000 = Rp 30.000** ❌ (SALAH!)

**Masalah**: Laba kotor berkurang dari Rp 50.000 menjadi Rp 30.000 padahal transaksi sudah terjadi!

---

## ✅ Yang Sudah Benar

1. **Harga Jual**: Disimpan snapshot, perubahan harga tidak mempengaruhi transaksi lama
2. **Revenue**: Menggunakan `transaction_items.unit_price` (snapshot), akurat
3. **Data Transaksi**: Lengkap dan tidak berubah

---

## ⚠️ Yang Perlu Diperbaiki

1. **COGS Calculation**: Menggunakan `products.purchase_price` saat ini, bukan harga saat transaksi
2. **Historical Accuracy**: Laporan keuangan lama bisa berubah jika harga beli produk diubah
3. **Audit Trail**: Tidak ada record harga beli saat transaksi dibuat

---

## 🔧 Rekomendasi Solusi

### Opsi 1: Simpan Harga Beli saat Transaksi (RECOMMENDED)

**Tambahkan kolom `purchase_price_at_transaction` di tabel `transaction_items`:**

```php
// Migration
Schema::table('transaction_items', function (Blueprint $table) {
    $table->decimal('purchase_price', 15, 2)->after('unit_price');
});

// Saat transaksi dibuat
TransactionItem::create([
    'transaction_id' => $transaction->id,
    'product_id' => $product->id,
    'quantity' => $item['quantity'],
    'unit_price' => $unitPrice,
    'purchase_price' => $product->purchase_price, // ✅ SNAPSHOT
    'discount_amount' => $itemDiscount,
    'total_price' => $totalPrice,
]);

// Saat menghitung COGS
$totalCOGS = (clone $baseQuery)->sum(
    DB::raw('transaction_items.quantity * transaction_items.purchase_price')
);
```

**Keuntungan**:
- ✅ Laporan keuangan tetap akurat meski harga berubah
- ✅ Audit trail lengkap
- ✅ Konsisten dengan cara menyimpan harga jual

**Kekurangan**:
- ⚠️ Perlu migration database
- ⚠️ Perlu update semua laporan yang menghitung COGS

---

### Opsi 2: Gunakan Average Cost (FIFO/LIFO)

Hitung harga beli berdasarkan metode FIFO/LIFO dari purchase history.

**Keuntungan**:
- ✅ Lebih akurat secara akuntansi
- ✅ Menangani multiple purchase dengan harga berbeda

**Kekurangan**:
- ⚠️ Lebih kompleks
- ⚠️ Perlu track purchase history per item

---

### Opsi 3: Lock Historical Data (Quick Fix)

Tambahkan warning saat harga beli berubah: "Perubahan ini akan mempengaruhi laporan keuangan periode sebelumnya. Pastikan Anda yakin."

**Keuntungan**:
- ✅ Tidak perlu perubahan database
- ✅ User aware dengan dampaknya

**Kekurangan**:
- ⚠️ Tidak menyelesaikan masalah akurasi
- ⚠️ Hanya mitigasi, bukan solusi

---

## 🎯 Jawaban Pertanyaan User

**Pertanyaan**: "Jika harga barang naik 1000, apakah mempengaruhi laporan keuangan yang sudah tercatat (misal laba bersih 200000)?"

**Jawaban**:

1. **Harga Jual Naik**: 
   - ❌ **TIDAK MEMPENGARUHI** laporan keuangan yang sudah ada
   - ✅ Harga jual disimpan snapshot saat transaksi

2. **Harga Beli Naik**: 
   - ⚠️ **MEMPENGARUHI** laporan keuangan yang sudah ada
   - ❌ COGS dihitung menggunakan harga beli saat ini
   - ❌ Jika harga beli naik, COGS akan naik → Laba kotor akan turun
   - ❌ Contoh: Laba bersih bisa berubah dari Rp 200.000 menjadi Rp 180.000 (jika COGS naik Rp 20.000)

**Rekomendasi**: Implementasikan Opsi 1 untuk memastikan akurasi laporan keuangan.

---

## 📝 Checklist Perbaikan

- [ ] Tambahkan kolom `purchase_price` di tabel `transaction_items`
- [ ] Update `TransactionController` untuk menyimpan snapshot harga beli
- [ ] Update semua controller laporan untuk menggunakan `transaction_items.purchase_price`
- [ ] Test dengan skenario: ubah harga beli, cek laporan lama tetap sama
- [ ] Update dokumentasi API

---

## 🔗 File-file yang Terpengaruh

1. **Database Migration**:
   - `database/migrations/YYYY_MM_DD_add_purchase_price_to_transaction_items.php`

2. **Models**:
   - `app/Models/TransactionItem.php`

3. **Controllers**:
   - `app/Http/Controllers/Api/TransactionController.php`
   - `app/Http/Controllers/Api/FinancialReportController.php`
   - `app/Http/Controllers/Api/ReportController.php`
   - `app/Http/Controllers/Api/EnhancedReportController.php`
   - `app/Http/Controllers/Api/AdvancedReportController.php`

4. **Frontend Types**:
   - `kasir-pos-frontend/src/types/index.ts`

---

**Dibuat**: {{ date('Y-m-d') }}  
**Status**: ⚠️ Memerlukan Perbaikan

