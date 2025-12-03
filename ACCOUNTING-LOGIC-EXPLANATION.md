# Penjelasan Logika Akuntansi untuk Perhitungan Laba

## Masalah yang Sering Membingungkan

Ada 2 pendekatan dalam menghitung Operating Expenses:

### Pendekatan 1: Akuntansi Standar (Accrual Basis) - RECOMMENDED

**Konsep:**
- COGS (HPP) = biaya barang yang **benar-benar terjual** di periode ini
- Purchase Expenses = biaya pembelian barang di periode ini
- Operating Expenses = hanya biaya operasional (sewa, listrik, gaji, dll)

**Rumus:**
```
Gross Profit = Net Revenue - COGS
Operating Expenses = Operational Expenses saja
Net Profit = Gross Profit - Operating Expenses
```

**Contoh:**
- Net Revenue: Rp 359.500
- COGS: Rp 164.000 (barang yang terjual)
- Purchase Expenses: Rp 9.000 (pembelian periode ini)
- Operational Expenses: Rp 100.000

**Perhitungan:**
- Gross Profit = 359.500 - 164.000 = **Rp 195.500**
- Operating Expenses = 100.000 (operational saja)
- Net Profit = 195.500 - 100.000 = **Rp 95.500**

**Alasan:**
- COGS (164.000) sudah dikurangkan dari Gross Profit
- Purchase Expenses (9.000) yang belum terjual menjadi **inventory (asset)**, bukan expense
- Jika kita kurangkan lagi Purchase Expenses, berarti kita kurangkan 2x untuk barang yang sama

---

### Pendekatan 2: Cash Basis (Semua Purchase = Expense)

**Konsep:**
- Semua purchase expenses di periode ini dihitung sebagai expense
- Tidak peduli apakah sudah terjual atau belum

**Rumus:**
```
Gross Profit = Net Revenue - COGS
Operating Expenses = Operational Expenses + Purchase Expenses
Net Profit = Gross Profit - Operating Expenses
```

**Contoh (data sama):**
- Net Revenue: Rp 359.500
- COGS: Rp 164.000
- Purchase Expenses: Rp 9.000
- Operational Expenses: Rp 100.000

**Perhitungan:**
- Gross Profit = 359.500 - 164.000 = **Rp 195.500**
- Operating Expenses = 100.000 + 9.000 = **Rp 109.000**
- Net Profit = 195.500 - 109.000 = **Rp 86.500**

**Masalah:**
- Jika COGS > Purchase Expenses (seperti contoh: 164.000 > 9.000), berarti ada inventory lama yang terjual
- Tapi kita tetap kurangkan Purchase Expenses, padahal COGS sudah dikurangkan
- Ini bisa menyebabkan **double counting** atau **understatement** profit

---

## Rekomendasi: Hybrid Approach (Paling Akurat)

**Konsep:**
- COGS sudah dikurangkan dari Gross Profit (untuk barang yang terjual)
- Purchase Expenses yang **belum menjadi COGS** (belum terjual) tetap dihitung sebagai expense
- Ini menghindari double counting

**Rumus:**
```
Gross Profit = Net Revenue - COGS
Unsold Inventory Expense = max(0, Purchase Expenses - COGS)
Operating Expenses = Operational Expenses + Unsold Inventory Expense
Net Profit = Gross Profit - Operating Expenses
```

**Contoh:**

**Kasus 1: Purchase Expenses > COGS**
- Net Revenue: Rp 359.500
- COGS: Rp 164.000
- Purchase Expenses: Rp 200.000 (lebih besar dari COGS)
- Operational Expenses: Rp 100.000

**Perhitungan:**
- Gross Profit = 359.500 - 164.000 = **Rp 195.500**
- Unsold Inventory = max(0, 200.000 - 164.000) = **Rp 36.000** (barang yang dibeli tapi belum terjual)
- Operating Expenses = 100.000 + 36.000 = **Rp 136.000**
- Net Profit = 195.500 - 136.000 = **Rp 59.500**

**Kasus 2: Purchase Expenses < COGS (seperti data Anda)**
- Net Revenue: Rp 359.500
- COGS: Rp 164.000
- Purchase Expenses: Rp 9.000 (lebih kecil dari COGS)
- Operational Expenses: Rp 100.000

**Perhitungan:**
- Gross Profit = 359.500 - 164.000 = **Rp 195.500**
- Unsold Inventory = max(0, 9.000 - 164.000) = **0** (tidak ada, karena semua purchase sudah terjual + ada inventory lama yang terjual)
- Operating Expenses = 100.000 + 0 = **Rp 100.000**
- Net Profit = 195.500 - 100.000 = **Rp 95.500**

---

## Kesimpulan

**Pendekatan yang paling benar secara akuntansi adalah Hybrid Approach:**

1. **Jika Purchase Expenses > COGS:**
   - Ada barang yang dibeli tapi belum terjual
   - Operating Expenses = Operational + (Purchase - COGS)

2. **Jika Purchase Expenses < COGS:**
   - Semua purchase sudah terjual + ada inventory lama yang terjual
   - Operating Expenses = Operational saja (karena COGS sudah dikurangkan)

3. **Jika Purchase Expenses = COGS:**
   - Semua purchase sudah terjual
   - Operating Expenses = Operational saja

**Ini menghindari:**
- Double counting (mengurangi COGS 2x)
- Understatement profit (mengurangi purchase yang sudah menjadi COGS)

---

## Implementasi Saat Ini

Saat ini sistem menggunakan **Pendekatan 2 (Cash Basis)** - semua purchase expenses dikurangkan.

**Apakah ini yang Anda inginkan?** Atau lebih baik menggunakan **Hybrid Approach** yang lebih akurat?

