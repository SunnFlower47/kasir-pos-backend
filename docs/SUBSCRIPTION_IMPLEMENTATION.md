# Dokumentasi Implementasi Sistem Subscription & Multi-Tenancy

## 📋 Daftar Isi

1. [Overview](#overview)
2. [Checklist Persiapan](#checklist-persiapan)
3. [Setup Midtrans](#setup-midtrans)
4. [Database Schema](#database-schema)
5. [Global Scope Implementation](#global-scope-implementation)
6. [API Endpoints](#api-endpoints)
7. [Admin Panel API](#admin-panel-api)
8. [Testing Checklist](#testing-checklist)
9. [Troubleshooting](#troubleshooting)

---

## 🎯 Overview

Dokumentasi ini menjelaskan langkah-langkah implementasi sistem subscription dengan multi-tenancy dan integrasi payment gateway Midtrans untuk aplikasi Kasir POS System.

### Konsep Utama

- **Multi-Tenancy**: Setiap organisasi/perusahaan (tenant) memiliki data terpisah
- **Subscription**: Setiap tenant berlangganan dengan plan tertentu (Web, Web+Mobile, Web+Desktop, dll)
- **Payment Gateway**: Menggunakan Midtrans untuk otomatisasi pembayaran
- **Isolasi Data**: Global Scope memastikan setiap tenant hanya bisa akses data mereka sendiri

---

## ✅ Checklist Persiapan

### 1. Akun & Credentials

- [ ] Daftar akun Midtrans (Sandbox)
- [ ] Dapatkan Server Key (untuk backend)
- [ ] Dapatkan Client Key (untuk frontend)
- [ ] Daftar akun Midtrans (Production) - untuk go live
- [ ] Siapkan domain untuk callback URL (production)

### 2. Backend Setup

- [ ] Install package Midtrans: `composer require midtrans/midtrans-php`
- [ ] Setup konfigurasi di `.env`
- [ ] Buat file config `config/midtrans.php`
- [ ] Siapkan database untuk backup (jika perlu migrate data existing)

### 3. Database Migrations

- [ ] Migration: `create_tenants_table`
- [ ] Migration: `add_tenant_id_to_users_table`
- [ ] Migration: `add_tenant_id_to_outlets_table`
- [ ] Migration: `add_tenant_id_to_products_table`
- [ ] Migration: `add_tenant_id_to_customers_table`
- [ ] Migration: `add_tenant_id_to_suppliers_table`
- [ ] Migration: `add_tenant_id_to_transactions_table`
- [ ] Migration: `add_tenant_id_to_purchases_table`
- [ ] Migration: `add_tenant_id_to_categories_table`
- [ ] Migration: `add_tenant_id_to_units_table`
- [ ] Migration: `create_subscriptions_table`
- [ ] Migration: `create_subscription_payments_table`
- [ ] Update unique constraints (SKU, barcode per tenant)

### 4. Models & Relationships

- [ ] Buat Model `Tenant`
- [ ] Buat Model `Subscription`
- [ ] Buat Model `SubscriptionPayment`
- [ ] Buat Trait `TenantScoped`
- [ ] Update Model `User` (tambah `tenant_id`, relationship)
- [ ] Update Model `Outlet` (tambah `tenant_id`, relationship)
- [ ] Update Model `Product` (tambah `tenant_id`, pakai trait TenantScoped)
- [ ] Update semua model lainnya dengan trait TenantScoped

### 5. Controllers & Services

- [ ] Buat `SubscriptionController` (untuk user)
- [ ] Buat `AdminSubscriptionController` (untuk admin panel)
- [ ] Buat `AdminTenantController` (untuk admin panel)
- [ ] Buat Service `SubscriptionService`
- [ ] Buat Service `MidtransService`
- [ ] Update `AuthController` (tambah registration flow)

### 6. Middleware

- [ ] Buat `EnsureTenant` middleware
- [ ] Buat `CheckSubscription` middleware
- [ ] Buat `AdminOnly` middleware
- [ ] Update route groups

### 7. Routes

- [ ] Route untuk subscription (user): `/api/v1/subscriptions/*`
- [ ] Route untuk admin panel: `/admin-api/v1/*`
- [ ] Route untuk Midtrans callback: `/api/subscriptions/midtrans-callback`

### 8. Seeder & Migration Data Existing

- [ ] Buat seeder untuk roles/permissions baru
- [ ] Buat script migration data existing (jika ada data lama)
- [ ] Buat script untuk assign tenant_id ke data existing

---

## 🔧 Setup Midtrans

### Step 1: Daftar Akun Midtrans

1. Kunjungi: https://dashboard.midtrans.com/
2. Daftar akun (gunakan email bisnis)
3. Login ke dashboard

### Step 2: Dapatkan Credentials

**Sandbox Environment (Development):**
1. Login ke Midtrans Dashboard
2. Pilih **Sandbox** mode (untuk testing)
3. Masuk ke **Settings** → **Access Keys**
4. Copy:
   - **Server Key** (untuk backend)
   - **Client Key** (untuk frontend)

**Production Environment (Live):**
1. Setelah lulus verifikasi, pilih **Production** mode
2. Masuk ke **Settings** → **Access Keys**
3. Copy credentials untuk production

### Step 3: Install Package di Backend

```bash
cd kasir-pos-system
composer require midtrans/midtrans-php
```

### Step 4: Setup Konfigurasi

**File `.env`:**

```env
# Midtrans Configuration
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxx  # Sandbox Server Key
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxx  # Sandbox Client Key
MIDTRANS_IS_PRODUCTION=false             # true untuk production
MIDTRANS_IS_SANDBOX=true                 # false untuk production
MIDTRANS_CALLBACK_URL=https://your-backend-domain.com/api/subscriptions/midtrans-callback
```

**File `config/midtrans.php` (buat baru):**

```php
<?php

return [
    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    'is_sanitized' => true,
    'is_3ds' => true,
    'callback_url' => env('MIDTRANS_CALLBACK_URL'),
];
```

### Step 5: Setup Callback URL di Midtrans Dashboard

1. Login ke Midtrans Dashboard
2. Masuk ke **Settings** → **Configuration**
3. Scroll ke **Notification URL**
4. Set URL: `https://your-backend-domain.com/api/subscriptions/midtrans-callback`
5. Save

**Note**: URL harus accessible dari internet (tidak bisa localhost). Untuk testing, bisa pakai ngrok atau deploy ke server.

---

## 🗄️ Database Schema

### 1. Tabel `tenants`

```sql
CREATE TABLE tenants (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    owner_name VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Fields:**
- `name`: Nama perusahaan/organisasi
- `email`: Email tenant (biasanya email owner)
- `phone`: Nomor telepon
- `address`: Alamat
- `owner_name`: Nama pemilik
- `is_active`: Status aktif/nonaktif

### 2. Tabel `subscriptions`

```sql
CREATE TABLE subscriptions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT NOT NULL,
    plan_name VARCHAR(50) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    price DECIMAL(15,2) NOT NULL,
    period VARCHAR(20) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    next_billing_date DATE NULL,
    features JSON NULL,
    max_outlets INT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_status (tenant_id, status)
);
```

**Fields:**
- `tenant_id`: FK ke tenants
- `plan_name`: web, web-desktop, web-mobile, web-mobile-desktop
- `status`: pending, active, expired, cancelled, trial
- `price`: Harga subscription
- `period`: monthly, yearly
- `start_date`: Tanggal mulai
- `end_date`: Tanggal berakhir
- `next_billing_date`: Tanggal billing berikutnya
- `features`: JSON array fitur yang termasuk ['web', 'mobile', 'desktop']
- `max_outlets`: Batas jumlah outlet (optional)

### 3. Tabel `subscription_payments`

```sql
CREATE TABLE subscription_payments (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    subscription_id BIGINT NOT NULL,
    order_id VARCHAR(255) UNIQUE NOT NULL,
    payment_method VARCHAR(50) NULL,
    amount DECIMAL(15,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    transaction_reference VARCHAR(255) NULL,
    midtrans_response JSON NULL,
    payment_date DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
    INDEX idx_order_id (order_id),
    INDEX idx_status (status)
);
```

**Fields:**
- `subscription_id`: FK ke subscriptions
- `order_id`: Order ID dari Midtrans (unique)
- `payment_method`: Metode pembayaran (bank_transfer, e_wallet, dll)
- `amount`: Jumlah pembayaran
- `status`: pending, paid, expired, cancelled, failed
- `transaction_reference`: Reference dari Midtrans
- `midtrans_response`: Full response JSON dari Midtrans
- `payment_date`: Tanggal pembayaran

### 4. Tambah `tenant_id` ke Tabel Existing

Tabel yang perlu ditambah `tenant_id`:
- `users`
- `outlets`
- `products`
- `categories`
- `units`
- `customers`
- `suppliers`
- `transactions`
- `transaction_items`
- `purchases`
- `purchase_items`
- `product_stocks`
- `stock_movements`
- `stock_transfers`
- `stock_transfer_items`
- `expenses`
- `settings` (optional, bisa global atau per tenant)

**Format Migration:**

```sql
ALTER TABLE [table_name] 
ADD COLUMN tenant_id BIGINT NULL AFTER id,
ADD FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
ADD INDEX idx_tenant_id (tenant_id);
```

**Update Unique Constraints:**

Untuk fields yang harus unique per tenant (misal: SKU, barcode):

```sql
-- Hapus constraint lama
ALTER TABLE products DROP INDEX products_sku_unique;
ALTER TABLE products DROP INDEX products_barcode_unique;

-- Buat constraint baru (unique per tenant)
ALTER TABLE products ADD UNIQUE KEY unique_tenant_sku (tenant_id, sku);
ALTER TABLE products ADD UNIQUE KEY unique_tenant_barcode (tenant_id, barcode);
```

---

## 🔒 Global Scope Implementation

### Konsep Global Scope

Global Scope adalah fitur Laravel yang otomatis menambahkan kondisi `WHERE` ke setiap query pada model tertentu. Dalam kasus ini, kita akan auto-filter semua query berdasarkan `tenant_id` dari user yang login.

### Step 1: Buat Trait `TenantScoped`

**File: `app/Traits/TenantScoped.php`**

Trait ini akan:
1. Auto-filter semua SELECT query berdasarkan `tenant_id`
2. Auto-set `tenant_id` saat CREATE data baru
3. Bisa di-disable untuk System Admin

**Fungsi:**
- `bootTenantScoped()`: Method yang otomatis dijalankan saat model boot
- Auto-inject WHERE clause pada query
- Auto-set tenant_id saat creating model

### Step 2: Pakai Trait di Models

Models yang perlu pakai trait ini:
- `Product`
- `Category`
- `Unit`
- `Customer`
- `Supplier`
- `Transaction`
- `Purchase`
- `Expense`
- `User` (untuk filter user per tenant)
- Semua model yang butuh isolasi tenant

**Contoh:**
```php
use App\Traits\TenantScoped;

class Product extends Model
{
    use TenantScoped;
    
    protected $fillable = [
        'tenant_id',
        'name',
        // ... fields lainnya
    ];
}
```

### Step 3: Bypass Global Scope (untuk System Admin)

Untuk System Admin yang perlu lihat semua data:

```php
// Tanpa filter tenant
Product::withoutGlobalScope('tenant')->get();

// Lihat semua produk semua tenant
Product::withoutGlobalScope('tenant')->where('is_active', true)->get();
```

---

## 🌐 API Endpoints

### User API (Ter-filter by Tenant)

Base URL: `/api/v1/`

#### 1. Subscription Endpoints

**GET `/api/v1/subscriptions`**
- Lihat subscription tenant user yang login
- Response: Subscription detail dengan status, plan, expiry

**POST `/api/v1/subscriptions/create-payment`**
- Buat payment request ke Midtrans
- Request body:
  ```json
  {
    "plan_name": "web-desktop",
    "period": "monthly"
  }
  ```
- Response: Payment URL dari Midtrans

**GET `/api/v1/subscriptions/payment-status/{payment_id}`**
- Cek status pembayaran
- Response: Status payment (pending, paid, expired)

**POST `/api/v1/subscriptions/renew`**
- Perpanjang subscription
- Request body:
  ```json
  {
    "period": "monthly"
  }
  ```

#### 2. Registration Endpoint (Update)

**POST `/api/v1/register`**
- Register user baru dengan tenant
- Request body:
  ```json
  {
    "name": "John Doe",
    "email": "john@example.com",
    "password": "Password123",
    "password_confirmation": "Password123",
    "company_name": "PT ABC",
    "phone": "08123456789",
    "address": "Jl. Contoh No. 123"
  }
  ```
- Flow:
  1. Create Tenant
  2. Create User (dengan tenant_id)
  3. Assign Role "Super Admin"
  4. Create Default Outlet
  5. Create Subscription (Trial)

#### 3. Midtrans Callback Endpoint

**POST `/api/subscriptions/midtrans-callback`**
- Endpoint untuk menerima notifikasi dari Midtrans
- Public endpoint (tidak perlu auth, tapi verify signature)
- Flow:
  1. Verify signature dari Midtrans
  2. Extract order_id dari request
  3. Update payment status
  4. Update subscription status (jika paid → active)

---

### Admin Panel API (Tidak Ter-filter)

Base URL: `/admin-api/v1/`

**Note**: Semua endpoint ini hanya bisa diakses oleh System Admin (role: "System Admin")

#### 1. Tenants Management

**GET `/admin-api/v1/tenants`**
- List semua tenant
- Query params: `search`, `status`, `per_page`, `page`
- Response: Paginated list of tenants

**GET `/admin-api/v1/tenants/{id}`**
- Detail tenant
- Response: Tenant detail dengan subscription, outlets, users

**PUT `/admin-api/v1/tenants/{id}`**
- Update tenant
- Request body: `name`, `email`, `phone`, `is_active`

**DELETE `/admin-api/v1/tenants/{id}`**
- Delete tenant (soft delete atau hard delete)

#### 2. Subscriptions Management

**GET `/admin-api/v1/subscriptions`**
- List semua subscription
- Query params: `status`, `plan_name`, `per_page`, `page`

**GET `/admin-api/v1/subscriptions/{id}`**
- Detail subscription dengan payments

**PUT `/admin-api/v1/subscriptions/{id}`**
- Update subscription (ubah plan, extend expiry, dll)

**POST `/admin-api/v1/subscriptions/{id}/activate`**
- Aktifkan subscription manual

**POST `/admin-api/v1/subscriptions/{id}/deactivate`**
- Nonaktifkan subscription

#### 3. Payments Management

**GET `/admin-api/v1/payments`**
- List semua payment
- Query params: `status`, `subscription_id`, `per_page`, `page`

**GET `/admin-api/v1/payments/{id}`**
- Detail payment

**PUT `/admin-api/v1/payments/{id}`**
- Update payment status (manual confirmation)

#### 4. Users Management (Cross-Tenant)

**GET `/admin-api/v1/users`**
- List semua user (semua tenant)
- Query params: `tenant_id`, `search`, `per_page`, `page`

**GET `/admin-api/v1/users/{id}`**
- Detail user (bisa dari tenant manapun)

#### 5. Dashboard

**GET `/admin-api/v1/dashboard`**
- Dashboard stats untuk admin
- Response:
  ```json
  {
    "total_tenants": 100,
    "active_subscriptions": 85,
    "expired_subscriptions": 10,
    "pending_payments": 5,
    "revenue_today": 5000000,
    "revenue_month": 150000000
  }
  ```

---

## 🧪 Testing Checklist

### 1. Registration Flow

- [ ] User bisa register dengan data lengkap
- [ ] Tenant otomatis dibuat
- [ ] User otomatis jadi Super Admin di tenant tersebut
- [ ] Outlet default otomatis dibuat
- [ ] Subscription trial otomatis dibuat
- [ ] User bisa login setelah register

### 2. Login & Authentication

- [ ] User bisa login dengan email & password
- [ ] Token berhasil dibuat
- [ ] User data include tenant_id
- [ ] User tidak bisa akses data tenant lain

### 3. Data Isolation (Global Scope)

- [ ] User Tenant A hanya lihat produk Tenant A
- [ ] User Tenant A hanya lihat customer Tenant A
- [ ] User Tenant A hanya lihat transaksi Tenant A
- [ ] User Tenant A tidak bisa akses data Tenant B
- [ ] Create data baru otomatis dapat tenant_id

### 4. Subscription Payment (Midtrans)

**Sandbox Testing:**
- [ ] Create payment request berhasil
- [ ] Dapat payment URL dari Midtrans
- [ ] User bisa redirect ke Midtrans payment page
- [ ] Simulasi pembayaran berhasil
- [ ] Callback dari Midtrans diterima
- [ ] Payment status update ke "paid"
- [ ] Subscription status update ke "active"

**Test Cards (Sandbox):**
- Success: 4811 1111 1111 1114
- 3DS: 4811 1111 1111 1114 (password: 112233)
- Denied: 4511 1111 1111 1117

### 5. Subscription Features

- [ ] Cek fitur subscription berfungsi
- [ ] User dengan plan "Web Only" tidak bisa akses fitur Mobile
- [ ] User dengan plan "Web+Mobile" bisa akses Web & Mobile
- [ ] Subscription expired → user tidak bisa akses

### 6. Admin Panel API

- [ ] System Admin bisa akses admin API
- [ ] User biasa tidak bisa akses admin API
- [ ] System Admin bisa lihat semua tenant
- [ ] System Admin bisa lihat semua subscription
- [ ] System Admin bisa update subscription
- [ ] System Admin bisa lihat semua payment

### 7. Multi-Outlet dalam Tenant

- [ ] Tenant bisa punya multiple outlets
- [ ] Produk sama untuk semua outlet (tenant level)
- [ ] Stock berbeda per outlet (outlet level)
- [ ] Transaksi terikat ke outlet tertentu
- [ ] User bisa akses beberapa outlet (jika punya akses)

---

## 🔧 Troubleshooting

### Issue 1: Callback dari Midtrans tidak diterima

**Kemungkinan:**
- Callback URL tidak accessible dari internet
- Signature verification gagal
- Route tidak terdaftar dengan benar

**Solusi:**
- Gunakan ngrok untuk testing local
- Pastikan callback URL di Midtrans Dashboard benar
- Check log untuk melihat request dari Midtrans
- Verify signature dengan benar

### Issue 2: Global Scope tidak bekerja

**Kemungkinan:**
- Trait tidak di-import dengan benar
- `Auth::user()` return null
- Tenant_id user null

**Solusi:**
- Pastikan trait di-import di model
- Pastikan user sudah login
- Pastikan user punya tenant_id

### Issue 3: Data tidak ter-filter (lihat data tenant lain)

**Kemungkinan:**
- Model tidak pakai trait TenantScoped
- Query menggunakan `withoutGlobalScope('tenant')`
- System Admin yang akses (normal behavior)

**Solusi:**
- Pastikan semua model pakai trait
- Review query yang bypass global scope
- Check user role

### Issue 4: Payment status tidak update

**Kemungkinan:**
- Callback handler tidak bekerja
- order_id tidak match
- Database transaction gagal

**Solusi:**
- Check log callback handler
- Verify order_id di database vs Midtrans
- Check database connection
- Review error logs

---

## 📝 Notes Penting

1. **Migration Data Existing**: Jika sudah ada data sebelum implementasi multi-tenancy, perlu script untuk assign tenant_id ke data existing

2. **Backup Database**: Selalu backup database sebelum run migration besar

3. **Testing**: Test thoroughly di Sandbox sebelum go to Production

4. **Security**: 
   - Verify signature dari Midtrans callback
   - Jangan expose Server Key di frontend
   - Gunakan HTTPS untuk production

5. **Performance**: 
   - Index pada `tenant_id` untuk performa query
   - Monitor query performance setelah implementasi

6. **Monitoring**: 
   - Monitor callback dari Midtrans
   - Log semua payment activity
   - Alert untuk subscription yang akan expired

---

**Last Updated**: January 2025

