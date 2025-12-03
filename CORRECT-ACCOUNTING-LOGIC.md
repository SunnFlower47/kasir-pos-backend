# Logika Akuntansi yang Benar untuk Perhitungan Laba

## Konsep Dasar Akuntansi

### 1. Gross Profit (Laba Kotor)
```
Gross Profit = Net Revenue - COGS
```
- COGS = Cost of Goods Sold (HPP) = biaya barang yang **BENAR-BENAR TERJUAL**
- Ini sudah benar dan tidak berubah

### 2. Operating Expenses (Biaya Operasional)

**Ini yang perlu dipahami dengan benar:**

Ada 2 pendekatan dalam akuntansi:

---

## Pendekatan 1: Accrual Basis (Standar Akuntansi)

**Konsep:** Barang diakui sebagai expense saat **terjual**, bukan saat **dibeli**.

### Rumus:
```
Unsold Inventory = Purchase Expenses - COGS
Operating Expenses = Operational Expenses + max(0, Unsold Inventory)
```

**Logika:**
- Jika Purchase Expenses > COGS → ada barang yang dibeli tapi belum terjual
  - Unsold Inventory = Purchase - COGS
  - Operating Expenses = Operational + Unsold Inventory
  - Barang yang sudah terjual (COGS) tidak masuk lagi karena sudah dikurangkan dari Gross Profit

- Jika Purchase Expenses ≤ COGS → semua purchase sudah terjual (atau ada inventory lama yang terjual)
  - Unsold Inventory = 0
  - Operating Expenses = Operational saja
  - Purchase Expenses tidak masuk karena sudah menjadi COGS

### Contoh Kasus 1: Purchase > COGS
- Purchase Expenses: Rp 200.000
- COGS: Rp 164.000
- Operational Expenses: Rp 100.000

**Perhitungan:**
- Unsold Inventory = 200.000 - 164.000 = Rp 36.000
- Operating Expenses = 100.000 + 36.000 = Rp 136.000
- Net Profit = 195.500 - 136.000 = Rp 59.500

### Contoh Kasus 2: Purchase < COGS (Data Anda)
- Purchase Expenses: Rp 9.000
- COGS: Rp 164.000
- Operational Expenses: Rp 100.000

**Perhitungan:**
- Unsold Inventory = max(0, 9.000 - 164.000) = 0
- Operating Expenses = 100.000 + 0 = Rp 100.000
- Net Profit = 195.500 - 100.000 = Rp 95.500

**Alasan:** 
- Purchase Expenses (9.000) sudah menjadi COGS, jadi tidak perlu dikurangkan lagi
- Ada inventory lama senilai 155.000 yang terjual (164.000 - 9.000)

---

## Pendekatan 2: Cash Basis (Cash Flow Focus)

**Konsep:** Semua pengeluaran tunai di periode ini adalah expense, tidak peduli apakah sudah terjual atau belum.

### Rumus:
```
Operating Expenses = Operational Expenses + Purchase Expenses
```

### Contoh (Data Anda):
- Purchase Expenses: Rp 9.000
- Operational Expenses: Rp 100.000

**Perhitungan:**
- Operating Expenses = 100.000 + 9.000 = Rp 109.000
- Net Profit = 195.500 - 109.000 = Rp 86.500

**Masalah:**
- Jika COGS > Purchase Expenses, terjadi double counting
- COGS sudah dikurangkan dari Gross Profit
- Tapi Purchase Expenses juga dikurangkan
- Ini bisa menyebabkan profit lebih kecil dari seharusnya

---

## Rekomendasi: Accrual Basis (Pendekatan 1)

**Mengapa Accrual Basis lebih benar:**

1. ✅ Sesuai dengan standar akuntansi (GAAP/IFRS)
2. ✅ Menghindari double counting
3. ✅ Lebih akurat untuk mengukur profitabilitas sebenarnya
4. ✅ Membedakan antara inventory (asset) dan expense
5. ✅ Sesuai dengan matching principle (expense diakui saat revenue diakui)

**Kelemahan:**
- Lebih kompleks
- Perlu tracking inventory

---

## Kesimpulan

**Yang benar secara akuntansi adalah Accrual Basis (Pendekatan 1):**

```
Unsold Inventory = max(0, Purchase Expenses - COGS)
Operating Expenses = Operational Expenses + Unsold Inventory
```

**Untuk bisnis UKM yang fokus pada cash flow, bisa gunakan Cash Basis, tapi perlu diingat:**
- Bisa terjadi double counting jika COGS > Purchase Expenses
- Profit bisa terlihat lebih kecil dari seharusnya
- Tapi lebih sederhana dan fokus pada cash flow

---

## Rekomendasi untuk Sistem Ini

Gunakan **Accrual Basis (Hybrid Approach)** karena:
1. Lebih akurat secara akuntansi
2. Menghindari kesalahan perhitungan
3. Standard practice dalam akuntansi
4. Tetap menampilkan "Total Expenses" (Purchase + Operational) untuk cash flow tracking

