# Analisis Kapasitas Outlet - Backend

## 📊 Executive Summary

**Backend dapat menangani:**
- **Secara teoritis**: Unlimited (menggunakan `bigint` ID, max ~9.2 quintillion outlets)
- **Secara praktis**: **100-500 outlets** tanpa masalah signifikan
- **Dengan optimasi**: **1000+ outlets** masih feasible
- **Bottleneck utama**: Saat create outlet baru (harus create ProductStock untuk semua products)

---

## 🗄️ Database Schema Analysis

### Outlets Table Structure

```php
Schema::create('outlets', function (Blueprint $table) {
    $table->id();                      // bigint - bisa sampai 9,223,372,036,854,775,807
    $table->string('name');            // No limit
    $table->string('code')->unique();  // Max 10 chars, unique constraint
    $table->text('address')->nullable();
    $table->string('phone')->nullable();
    $table->string('email')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

**Constraints:**
- ✅ **No hard limit** pada jumlah outlet
- ✅ Primary key menggunakan `bigint` (sangat besar)
- ⚠️ `code` field max 10 karakter (perlu unique)

---

## 🔍 Performance Indexes

### Existing Indexes untuk Outlet Queries

Backend sudah memiliki indexes yang optimal:

1. **Transactions Table:**
   - `index('outlet_id')`
   - `index(['outlet_id', 'transaction_date'])` ← Composite index untuk filtering

2. **Product Stocks Table:**
   - `index('outlet_id')`
   - `unique(['product_id', 'outlet_id'])` ← Composite unique index

3. **Purchases Table:**
   - `index(['outlet_id', 'purchase_date'])`

4. **Expenses Table:**
   - `index(['outlet_id', 'expense_date'])`

5. **Shift Closings Table:**
   - `index('outlet_id')`
   - `index(['outlet_id', 'closing_date'])`

**Kesimpulan:** ✅ Indexes sudah optimal untuk query dengan `outlet_id`

---

## 📈 Query Performance Analysis

### Optimized Queries

Backend sudah menggunakan optimized queries:

1. **OutletController@show()** - Menggunakan aggregated queries:
   ```php
   // Reduces 8 queries to 3 queries (66% reduction)
   $userStats = DB::table('users')->selectRaw('COUNT(*)...')->where('outlet_id', $outlet->id)->first();
   $transactionStats = DB::table('transactions')->selectRaw('...')->where('outlet_id', $outlet->id)->first();
   $stockStats = DB::table('product_stocks')->join('products')->selectRaw('...')->where('outlet_id', $outlet->id)->first();
   ```

2. **Pagination** digunakan untuk listing:
   ```php
   $perPage = $request->get('per_page', 15);  // Default 15 per page
   $outlets = $query->orderBy('name')->paginate($perPage);
   ```

3. **Eager loading** untuk relationships:
   ```php
   Transaction::with(['customer', 'outlet', 'user', 'transactionItems.product'])
   ```

---

## ⚠️ Potential Bottlenecks

### 1. Create Outlet Operation (MAIN BOTTLENECK)

**Lokasi:** `OutletController@store()`

```php
// Create initial stock records for all products
$products = Product::where('is_active', true)->get();
foreach ($products as $product) {
    ProductStock::create([
        'product_id' => $product->id,
        'outlet_id' => $outlet->id,
        'quantity' => 0,
    ]);
}
```

**Masalah:**
- Jika ada **1000 products** → **1000 INSERT queries** (N+1 problem)
- Jika ada **100 outlets** → **100,000 rows** di `product_stocks` table
- Jika ada **1000 outlets** → **1,000,000 rows** di `product_stocks` table

**Estimasi Waktu:**
- 100 products: ~2-5 detik
- 1000 products: ~20-50 detik
- 5000 products: ~100-250 detik

**Solusi:**
```php
// Bulk insert instead of loop
$stockData = $products->map(function ($product) use ($outlet) {
    return [
        'product_id' => $product->id,
        'outlet_id' => $outlet->id,
        'quantity' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ];
})->toArray();

ProductStock::insert($stockData);  // Single INSERT query
```

**Impact:** Dapat mengurangi waktu dari 20-50 detik menjadi **< 1 detik**

---

### 2. Product Stocks Table Growth

**Formula:** `Total Rows = Number of Products × Number of Outlets`

| Products | Outlets | Total Rows | Query Performance |
|----------|---------|------------|-------------------|
| 100      | 10      | 1,000      | Excellent ⚡ |
| 1,000    | 50      | 50,000     | Good ✅ |
| 1,000    | 100     | 100,000    | Good ✅ |
| 5,000    | 100     | 500,000    | Acceptable ⚠️ |
| 5,000    | 500     | 2,500,000  | Slow ⚠️ |
| 10,000   | 1000    | 10,000,000 | Very Slow ❌ |

**Kesimpulan:**
- ✅ **< 500,000 rows**: Excellent performance
- ⚠️ **500,000 - 2,000,000 rows**: Still acceptable dengan indexes
- ❌ **> 2,000,000 rows**: Perlu optimasi lebih lanjut (partitioning, caching)

---

### 3. Transaction History Growth

**Per Outlet:**
- Jika setiap outlet melakukan **100 transaksi/hari**
- **1 tahun** = 36,500 transaksi/outlet
- **100 outlets** = 3,650,000 transaksi

**Dengan Index:**
- ✅ Query dengan `outlet_id` + date range: **< 100ms**
- ✅ Composite index `['outlet_id', 'transaction_date']` sangat efektif

**Recommendation:**
- Archive old transactions (> 2 tahun) ke separate table
- Atau gunakan partitioning berdasarkan `transaction_date`

---

## 📊 Capacity Estimates

### Realistic Capacity (Current Implementation)

| Scenario | Outlets | Products | Performance | Notes |
|----------|---------|----------|-------------|-------|
| **Small Business** | 1-10 | 100-500 | ⚡ Excellent | Tidak ada masalah |
| **Medium Business** | 10-50 | 500-1,000 | ✅ Good | Perlu optimasi create outlet |
| **Large Business** | 50-200 | 1,000-5,000 | ⚠️ Acceptable | Perlu bulk insert optimization |
| **Enterprise** | 200-500 | 5,000-10,000 | ⚠️ Acceptable | Perlu caching & archiving |
| **Very Large** | 500-1,000+ | 10,000+ | ❌ Slow | Perlu major optimization |

---

## 🚀 Recommendations

### Immediate Improvements (Quick Wins)

1. **Optimize Outlet Creation (CRITICAL)**
   ```php
   // Replace loop with bulk insert
   ProductStock::insert($stockData);
   ```
   **Impact:** 10-100x faster

2. **Add Caching untuk Outlet List**
   ```php
   Cache::remember('outlets.active', 3600, function() {
       return Outlet::where('is_active', true)->get();
   });
   ```
   **Impact:** Reduce database load

3. **Pagination Default**
   - Already implemented ✅
   - Consider increasing default per_page untuk admin view

### Medium-term Improvements

4. **Database Partitioning**
   - Partition `product_stocks` by `outlet_id` ranges
   - Partition `transactions` by `transaction_date` (monthly/yearly)

5. **Read Replicas**
   - Use read replica untuk reporting queries
   - Reduce load on primary database

6. **Archiving Strategy**
   - Archive transactions > 2 years old
   - Archive product_stocks untuk inactive products/outlets

### Long-term Considerations

7. **Sharding** (jika > 1000 outlets)
   - Shard berdasarkan outlet_id ranges
   - Requires application-level routing

8. **CQRS Pattern**
   - Separate read/write models
   - Optimize reads dengan denormalized views

---

## 🎯 Conclusion

### Current Capacity (Tanpa Optimasi)

- **Recommended**: **50-100 outlets** dengan 1,000-5,000 products
- **Maximum**: **200-500 outlets** dengan optimasi minor

### Dengan Optimasi Create Outlet (Bulk Insert)

- **Recommended**: **100-200 outlets** dengan 1,000-5,000 products
- **Maximum**: **500-1,000 outlets** masih feasible

### Dengan Full Optimization (Caching + Archiving)

- **Recommended**: **200-500 outlets**
- **Maximum**: **1,000+ outlets** possible

---

## 📝 Action Items

### Priority 1 (Critical)
- [ ] **Optimize outlet creation** dengan bulk insert
- [ ] **Test performance** dengan 100+ outlets

### Priority 2 (Important)
- [ ] **Add caching** untuk outlet list
- [ ] **Archive old transactions** (> 2 years)

### Priority 3 (Nice to Have)
- [ ] **Database partitioning** untuk large scale
- [ ] **Read replicas** untuk reporting

---

**Last Updated:** 2025-01-XX
**Estimated Capacity:** 100-500 outlets (dengan optimasi create outlet)

---

# API Calls Capacity Analysis

## 🔄 Rate Limiting Configuration

### Current Rate Limits (Per User/IP)

| Endpoint Type | Rate Limit | Notes |
|--------------|------------|-------|
| **Login** | 5 requests/minute | Brute force protection |
| **General API** | 150 requests/minute | Most endpoints (dashboard, products, transactions, etc.) |
| **Barcode Scan** | 300 requests/minute | High-frequency POS operations |
| **Receipt Generation** | 60 requests/minute | PDF generation is resource-intensive |

### Rate Limiting Analysis

**Important:** Rate limiting adalah **per user/IP**, BUKAN per outlet!

**Calculation:**
- 1 outlet dengan 5 users → 5 × 150 = **750 requests/minute**
- 10 outlets dengan 5 users masing-masing → 50 users × 150 = **7,500 requests/minute**
- 100 outlets dengan 5 users masing-masing → 500 users × 150 = **75,000 requests/minute**

---

## 📊 Concurrent API Calls Capacity

### Per User Capacity

**Per User/IP:**
- General API: **150 requests/minute** = **2.5 requests/second**
- Barcode Scan: **300 requests/minute** = **5 requests/second**

### Per Outlet Capacity (Estimasi)

**Asumsi:** Setiap outlet memiliki:
- 2-5 active users (cashiers/staff)
- Peak hours: 10% dari total time (6 jam per hari)

**Per Outlet (Peak Hour):**
- 5 users × 150 req/min = **750 requests/minute** = **12.5 requests/second**
- 5 users × 2.5 req/sec = **12.5 concurrent requests**

### System-Wide Capacity

**Conservative Estimate (Default Laravel/PHP-FPM):**

| Outlets | Active Users | Peak Requests/Min | Peak Requests/Sec | Status |
|---------|--------------|-------------------|-------------------|--------|
| 1-10 | 5-50 | 750-7,500 | 12.5-125 | ✅ Excellent |
| 10-50 | 50-250 | 7,500-37,500 | 125-625 | ✅ Good |
| 50-100 | 250-500 | 37,500-75,000 | 625-1,250 | ⚠️ Acceptable |
| 100-200 | 500-1,000 | 75,000-150,000 | 1,250-2,500 | ⚠️ Need Optimization |
| 200+ | 1,000+ | 150,000+ | 2,500+ | ❌ Need Scaling |

---

## 🖥️ Server Resources Requirements

### Database Connection Pool

**Laravel Default:**
- MySQL default: **No explicit pool limit** (depends on MySQL `max_connections`)
- PHP-FPM default: **50-100 worker processes** (configurable)
- Each worker can handle 1 request at a time

### PHP-FPM Configuration

**Recommended Settings:**
```ini
pm = dynamic
pm.max_children = 50        # Max concurrent requests
pm.start_servers = 10       # Initial workers
pm.min_spare_servers = 5    # Minimum idle workers
pm.max_spare_servers = 20   # Maximum idle workers
pm.max_requests = 1000      # Restart after N requests
```

**Capacity per PHP-FPM Pool:**
- 50 workers = **50 concurrent requests**
- With average 200ms response time = **250 requests/second**

### MySQL Connection Limits

**Default MySQL:**
```sql
SHOW VARIABLES LIKE 'max_connections';
-- Default: 151 connections
```

**Recommended:**
- Small (1-50 outlets): 100-200 connections
- Medium (50-200 outlets): 200-500 connections
- Large (200+ outlets): 500-1,000 connections

---

## 🔍 API Performance Metrics

### Typical Response Times (With Indexes)

| Endpoint | Avg Response Time | Notes |
|----------|------------------|-------|
| GET /products | 50-100ms | Paginated, indexed |
| GET /transactions | 100-200ms | Filtered by outlet_id, indexed |
| GET /stocks | 80-150ms | Filtered by outlet_id, indexed |
| GET /dashboard | 150-300ms | Multiple aggregated queries |
| POST /transactions (Create) | 200-500ms | Transaction + stock updates |
| GET /reports/sales | 500-2000ms | Complex aggregations |

### Bottleneck Analysis

**Current Bottlenecks:**
1. ✅ **Dashboard queries** - Already optimized with aggregated queries
2. ✅ **Transaction filtering** - Indexes in place (`outlet_id`, `transaction_date`)
3. ✅ **Stock queries** - Composite index `['outlet_id', 'product_id']`
4. ⚠️ **Report queries** - Complex aggregations (500-2000ms)
5. ⚠️ **Create Outlet** - N+1 problem (needs bulk insert)

---

## 📈 Scalability Recommendations

### For 1-50 Outlets (Current Setup OK)

**Requirements:**
- 2 CPU cores
- 4GB RAM
- Default PHP-FPM (50 workers)
- Default MySQL (151 connections)

**Capacity:** ✅ **No issues**

### For 50-200 Outlets (Minor Optimizations)

**Requirements:**
- 4 CPU cores
- 8GB RAM
- PHP-FPM: 100 workers
- MySQL: 300 connections

**Optimizations Needed:**
- ✅ Optimize outlet creation (bulk insert)
- ✅ Add caching for frequently accessed data (products, categories)
- ✅ Consider Redis for session storage

**Capacity:** ✅ **Good with optimizations**

### For 200-500 Outlets (Major Optimizations)

**Requirements:**
- 8 CPU cores
- 16GB RAM
- PHP-FPM: 200 workers
- MySQL: 500 connections

**Optimizations Needed:**
- ✅ All above optimizations
- ✅ Database read replicas for reports
- ✅ Query result caching (Redis)
- ✅ Queue heavy operations (reports, exports)

**Capacity:** ⚠️ **Acceptable with full optimization**

### For 500+ Outlets (Horizontal Scaling)

**Requirements:**
- Load balancer (nginx/haproxy)
- Multiple app servers (2-4 servers)
- Database read replicas
- Redis cluster
- Queue workers (Laravel Horizon)

**Architecture:**
```
Load Balancer
    ├── App Server 1 (PHP-FPM: 100 workers)
    ├── App Server 2 (PHP-FPM: 100 workers)
    └── App Server 3 (PHP-FPM: 100 workers)
    
Database
    ├── Master (Write)
    └── Replica 1 (Read)
    └── Replica 2 (Read)
```

**Capacity:** ✅ **Scales horizontally**

---

## 🎯 Real-World Capacity Estimate

### Conservative Estimate (Peak Hours)

**Assumptions:**
- Each outlet: 5 active users
- Peak hours: 6 hours/day (25% of time)
- Average: 1 request per 3 seconds per user
- Peak: 2 requests per second per user

**Per Outlet (Peak):**
- 5 users × 2 req/sec = **10 requests/second**
- With 200ms avg response time = **2 concurrent requests per outlet**

**System Capacity (PHP-FPM 50 workers):**
- 50 workers ÷ 2 concurrent/outlet = **~25 outlets at peak**
- With 80% efficiency = **~20 outlets at peak**

**System Capacity (PHP-FPM 200 workers):**
- 200 workers ÷ 2 concurrent/outlet = **~100 outlets at peak**
- With 80% efficiency = **~80 outlets at peak**

### With Optimizations (Caching, Read Replicas)

**Can handle 2-3x more:**
- PHP-FPM 200 workers: **~150-200 outlets at peak**
- PHP-FPM 500 workers: **~300-400 outlets at peak**

---

## 📝 Summary: API Calls Capacity

### Current Setup (No Optimizations)

| Metric | Value |
|--------|-------|
| **Rate Limit (per user)** | 150 requests/minute |
| **PHP-FPM Workers** | 50 (default) |
| **Concurrent Requests** | 50 |
| **Outlets at Peak** | ~20-25 |
| **Total Users Supported** | ~100-125 |

### With Optimizations (Caching, Read Replicas)

| Metric | Value |
|--------|-------|
| **Rate Limit (per user)** | 150 requests/minute |
| **PHP-FPM Workers** | 200 |
| **Concurrent Requests** | 200 |
| **Outlets at Peak** | ~150-200 |
| **Total Users Supported** | ~750-1,000 |

### With Horizontal Scaling

| Metric | Value |
|--------|-------|
| **Rate Limit (per user)** | 150 requests/minute |
| **App Servers** | 3-4 servers |
| **Total Workers** | 300-400 |
| **Concurrent Requests** | 300-400 |
| **Outlets at Peak** | ~300-500 |
| **Total Users Supported** | ~1,500-2,500 |

---

## ⚠️ Important Notes

1. **Rate Limiting is per User/IP**, not per outlet
   - 100 outlets with 5 users each = 500 users = 75,000 requests/minute capacity
   - But actual usage depends on server resources (PHP-FPM, database)

2. **Peak vs Average**
   - Most systems handle 10-25% peak usage
   - Average usage is usually much lower
   - Capacity estimates are for **peak hours**

3. **Database is the Bottleneck**
   - API can handle many requests
   - But database connections and query performance are limiting factors
   - Optimize queries and use caching

4. **Recommendation:**
   - **< 50 outlets**: Current setup OK ✅
   - **50-200 outlets**: Optimize + scale PHP-FPM ✅
   - **200-500 outlets**: Add caching + read replicas ⚠️
   - **500+ outlets**: Horizontal scaling required ❌

---

**Last Updated:** 2025-01-XX
**API Capacity:** ~20-25 outlets (current), ~150-200 outlets (optimized), ~300-500 outlets (scaled)

