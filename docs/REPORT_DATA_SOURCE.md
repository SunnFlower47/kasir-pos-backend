# Sumber Data Laporan Keuangan

## ✅ Kesimpulan

**Semua menu laporan mengambil data dari tabel TRANSAKSI, BUKAN dari tabel PRODUCT.**

---

## 📊 Alur Data Laporan

```
┌─────────────────────────────────────────────────────────────┐
│                    TABEL TRANSAKSI                           │
│  ┌──────────────────┐         ┌──────────────────────┐     │
│  │   transactions   │────────▶│ transaction_items    │     │
│  │                  │         │                      │     │
│  │ - transaction_id │         │ - transaction_id     │     │
│  │ - status         │         │ - product_id (ref)   │     │
│  │ - transaction_   │         │ - quantity           │     │
│  │   date           │         │ - unit_price ✅      │     │
│  │ - total_amount   │         │ - purchase_price ✅  │     │
│  │ - refunded_at    │         │ - discount_amount    │     │
│  └──────────────────┘         │ - total_price ✅     │     │
│                                └──────────────────────┘     │
│                                         │                   │
│                                         │ (SNAPSHOT DATA)   │
│                                         ▼                   │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ Query
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                  MENU LAPORAN                                │
│  • Sales Report       → transaction_items.total_price       │
│  • Profit Report      → transaction_items (revenue + COGS)  │
│  • Financial Report   → transaction_items + purchases       │
│  • Enhanced Report    → transaction_items + transactions    │
│  • Advanced Report    → transaction_items + transactions    │
└─────────────────────────────────────────────────────────────┘
```

---

## 📋 Sumber Data Per Laporan

### 1. **Sales Report** (Laporan Penjualan)

**Data Source:**
```php
// ✅ Mengambil dari transaction_items (SNAPSHOT)
Transaction::where('status', 'completed')
    ->with(['transactionItems'])  // Data dari transaction_items

// Revenue
SUM(transaction_items.total_price)  // ✅ Snapshot
```

**Bukan dari:**
- ❌ `products.selling_price`
- ❌ `products.wholesale_price`

---

### 2. **Profit Report** (Laporan Laba Rugi)

**Data Source:**

```php
// ✅ Revenue dari transaction_items
$revenue = DB::table('transaction_items')
    ->join('transactions', ...)
    ->sum('transaction_items.total_price');  // ✅ Snapshot

// ✅ COGS dari transaction_items (snapshot purchase_price)
$cogs = DB::table('transaction_items')
    ->sum('transaction_items.quantity * transaction_items.purchase_price');  // ✅ Snapshot

// ✅ Refunds dari transactions
$refunds = Transaction::where('status', 'refunded')
    ->sum('total_amount');  // ✅ Snapshot
```

**Bukan dari:**
- ❌ `products.purchase_price` (untuk COGS)
- ❌ `products.selling_price` (untuk revenue)

---

### 3. **Financial Report** (Laporan Keuangan)

**Data Source:**

```php
// ✅ Revenue
TransactionItem::sum('total_price')  // ✅ Snapshot

// ✅ COGS
TransactionItem::sum('quantity * purchase_price')  // ✅ Snapshot

// ✅ Expenses
Purchase::sum('total_amount')  // Dari purchases table
Expense::sum('amount')         // Dari expenses table
```

**Bukan dari:**
- ❌ `products.*` (harga produk saat ini)

---

### 4. **Enhanced Report** (Laporan Enhanced)

**Data Source:**

```php
// ✅ Sales data
TransactionItem::join('transactions', ...)
    ->sum('transaction_items.total_price')  // ✅ Snapshot

// ✅ COGS
TransactionItem::sum('quantity * purchase_price')  // ✅ Snapshot
```

**Bukan dari:**
- ❌ `products.*` (harga produk saat ini)

---

### 5. **Advanced Report** (Laporan Advanced)

**Data Source:**

```php
// ✅ Product performance
TransactionItem::groupBy('product_id')
    ->sum('total_price')  // ✅ Snapshot revenue
    ->sum('quantity * purchase_price')  // ✅ Snapshot COGS

// ✅ Profit calculation
SUM(transaction_items.total_price) - 
SUM(transaction_items.quantity * transaction_items.purchase_price)
```

**Bukan dari:**
- ❌ `products.purchase_price` (untuk COGS)
- ❌ `products.selling_price` (untuk revenue)

---

## 🔍 Mengapa Join ke Tabel Products?

Kadang query melakukan JOIN ke tabel `products`, tapi **HANYA untuk**:

### ✅ Tujuan Join ke Products:

1. **Referensi/Display**
   ```php
   ->join('products', 'transaction_items.product_id', '=', 'products.id')
   // Hanya untuk mengambil: products.name, products.sku, products.category_id
   ```

2. **Fallback (Backward Compatibility)**
   ```php
   // Untuk data lama yang belum punya purchase_price
   COALESCE(transaction_items.purchase_price, products.purchase_price)
   // Prioritas: transaction_items.purchase_price (snapshot)
   // Fallback: products.purchase_price (hanya untuk data lama)
   ```

3. **Filter/Grouping**
   ```php
   // Untuk grouping by category
   ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
   ```

### ❌ Bukan untuk:

- ❌ Mengambil harga jual (`products.selling_price`)
- ❌ Mengambil harga beli untuk COGS (`products.purchase_price`) - kecuali fallback

---

## 📊 Contoh Query Pattern

### ✅ Pattern BENAR (Menggunakan Snapshot)

```php
// Revenue - Menggunakan snapshot
$revenue = TransactionItem::whereHas('transaction', function($q) {
    $q->where('status', 'completed');
})
->sum('total_price');  // ✅ Dari snapshot

// COGS - Menggunakan snapshot
$cogs = TransactionItem::whereHas('transaction', function($q) {
    $q->where('status', 'completed');
})
->sum(DB::raw('quantity * COALESCE(purchase_price, 0)'));  // ✅ Dari snapshot

// Profit
$profit = $revenue - $cogs;  // ✅ Semua dari snapshot
```

### ❌ Pattern SALAH (Menggunakan Harga Produk Saat Ini)

```php
// ❌ SALAH - Menggunakan harga produk saat ini
$revenue = TransactionItem::join('products', ...)
    ->sum('products.selling_price');  // ❌ Bisa berubah!

// ❌ SALAH - Menggunakan harga beli produk saat ini
$cogs = TransactionItem::join('products', ...)
    ->sum('products.purchase_price');  // ❌ Bisa berubah!
```

---

## 🎯 Key Points

### 1. **Data Snapshot = Immutable**
- Semua harga disimpan di `transaction_items` saat transaksi dibuat
- Harga produk berubah → Transaksi lama TIDAK terpengaruh

### 2. **Laporan = Data Transaksi**
- Semua perhitungan menggunakan data dari `transaction_items`
- Revenue = `transaction_items.total_price`
- COGS = `transaction_items.quantity * transaction_items.purchase_price`

### 3. **Products Table = Reference Only**
- Tabel `products` hanya untuk:
  - Nama produk (display)
  - SKU (display)
  - Category (filter/grouping)
  - **BUKAN untuk harga**

### 4. **Immutable = Accurate**
- Laporan keuangan tetap akurat
- Data historis tidak berubah
- Audit trail lengkap

---

## 📋 Summary Table

| Data | Sumber | Snapshot? | Immutable? |
|------|--------|-----------|------------|
| **Revenue** | `transaction_items.total_price` | ✅ Ya | ✅ Ya |
| **Harga Jual** | `transaction_items.unit_price` | ✅ Ya | ✅ Ya |
| **COGS** | `transaction_items.purchase_price` | ✅ Ya | ✅ Ya |
| **Quantity** | `transaction_items.quantity` | ✅ Ya | ✅ Ya |
| **Discount** | `transaction_items.discount_amount` | ✅ Ya | ✅ Ya |
| **Transaction Date** | `transactions.transaction_date` | ✅ Ya | ✅ Ya |
| **Refunds** | `transactions.total_amount` (status=refunded) | ✅ Ya | ✅ Ya |

---

## ✅ Verifikasi

Semua laporan telah diverifikasi menggunakan snapshot data:

- [x] Sales Report → `transaction_items.total_price`
- [x] Profit Report → `transaction_items` (revenue + COGS)
- [x] Financial Report → `transaction_items` + `purchases` + `expenses`
- [x] Enhanced Report → `transaction_items` + `transactions`
- [x] Advanced Report → `transaction_items` + `transactions`

**Tidak ada laporan yang menggunakan harga produk saat ini untuk perhitungan.**

---

## 🎯 Kesimpulan

**Menu laporan mengambil data dari:**
1. ✅ **Tabel `transactions`** - Info transaksi (tanggal, status, refund)
2. ✅ **Tabel `transaction_items`** - Data snapshot (harga, quantity, COGS)
3. ✅ **Tabel `purchases`** - Data pembelian (expenses)
4. ✅ **Tabel `expenses`** - Data pengeluaran operasional

**Bukan dari:**
- ❌ **Tabel `products`** - Hanya untuk referensi/display, bukan untuk harga

**Prinsip:**
> **Data transaksi historis TIDAK BERUBAH, semua harga adalah SNAPSHOT pada saat transaksi dibuat.**

---

**Dibuat**: 2025-12-02  
**Status**: ✅ Verified - All Reports Use Transaction Data

