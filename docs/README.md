# Kasir POS System - Backend Documentation

## 📋 Daftar Isi

1. [Overview](#overview)
2. [Instalasi](#instalasi)
3. [Konfigurasi](#konfigurasi)
4. [Database Schema](#database-schema)
5. [API Documentation](#api-documentation)
6. [Features](#features)
7. [Security](#security)
8. [Performance](#performance)
9. [Deployment](#deployment)

---

## 🎯 Overview

**Kasir POS System** adalah sistem Point of Sale (POS) berbasis web yang dibangun dengan **Laravel 11** dan menggunakan **Laravel Sanctum** untuk authentication. Sistem ini dirancang untuk membantu pengelolaan transaksi, inventori, dan pelaporan bisnis retail.

### Teknologi yang Digunakan

- **Framework**: Laravel 11.x
- **PHP**: 8.2+
- **Database**: MySQL / SQLite / PostgreSQL
- **Authentication**: Laravel Sanctum (Token-based)
- **Authorization**: Spatie Laravel Permission
- **PDF Generation**: DomPDF
- **Excel Export**: Maatwebsite Excel

### Fitur Utama

- ✅ Multi-outlet support
- ✅ Product & Inventory Management
- ✅ Transaction Processing (POS)
- ✅ Customer Management dengan Loyalty Points
- ✅ Purchase Order Management
- ✅ Stock Management & Transfers
- ✅ Financial Reports (Enhanced, Financial, Advanced)
- ✅ Expense Management
- ✅ Audit Logging
- ✅ Role & Permission Management
- ✅ Receipt Printing (PDF & HTML)
- ✅ Refund System
- ✅ Settings Management

---

## 📦 Instalasi

Lihat [INSTALLATION.md](./INSTALLATION.md) untuk panduan instalasi lengkap.

### Quick Start

```bash
# Clone repository
git clone <repository-url>
cd kasir-pos-system

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Setup database di .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kasir_pos
DB_USERNAME=root
DB_PASSWORD=

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Generate Sanctum keys
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate

# Create storage link
php artisan storage:link

# Run server
php artisan serve
```

---

## ⚙️ Konfigurasi

### Environment Variables

File `.env` berisi konfigurasi penting:

```env
# Application
APP_NAME="Kasir POS System"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kasir_pos
DB_USERNAME=root
DB_PASSWORD=

# Frontend URL (untuk CORS)
FRONTEND_URL=http://localhost:4173

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1
SESSION_DRIVER=database
```

### Konfigurasi Tambahan

- **CORS**: `config/cors.php` - Konfigurasi CORS untuk frontend
- **Sanctum**: `config/sanctum.php` - Konfigurasi authentication
- **Permission**: `config/permission.php` - Konfigurasi role & permission

---

## 🗄️ Database Schema

Lihat [DATABASE.md](./DATABASE.md) untuk dokumentasi lengkap schema database.

### Tabel Utama

- `users` - User management
- `outlets` - Multi-outlet support
- `products` - Product catalog
- `categories` - Product categories
- `units` - Measurement units
- `customers` - Customer data dengan loyalty points
- `suppliers` - Supplier management
- `transactions` - Sales transactions
- `transaction_items` - Transaction details
- `purchases` - Purchase orders
- `purchase_items` - Purchase details
- `product_stocks` - Stock per outlet
- `stock_movements` - Stock movement history
- `stock_transfers` - Stock transfers antar outlet
- `expenses` - Operational expenses
- `promotions` - Promotions & discounts
- `settings` - Application settings
- `audit_logs` - System audit trail

---

## 🔌 API Documentation

Lihat [API.md](./API.md) untuk dokumentasi lengkap semua API endpoints.

### Base URL

```
http://localhost:8000/api/v1
```

### Authentication

Semua API endpoints (kecuali login) memerlukan Bearer Token:

```
Authorization: Bearer {token}
```

### Response Format

```json
{
  "success": true,
  "data": {...},
  "message": "Optional message"
}
```

---

## ✨ Features

Lihat [FEATURES.md](./FEATURES.md) untuk dokumentasi lengkap semua fitur.

### Fitur Utama

1. **Authentication & Authorization**
   - Token-based authentication (Sanctum)
   - Role-based access control (RBAC)
   - Permission-based access control (PBAC)

2. **Product Management**
   - CRUD Products
   - Category & Unit management
   - Barcode support
   - Product images
   - Stock tracking per outlet

3. **Transaction Processing**
   - POS interface support
   - Multiple payment methods
   - Discount & promotions
   - Receipt generation
   - Refund system

4. **Inventory Management**
   - Stock tracking per outlet
   - Stock adjustments
   - Stock transfers antar outlet
   - Stock movement history
   - Low stock alerts

5. **Reporting**
   - Enhanced Report (Sales analytics)
   - Financial Report (Laba/rugi)
   - Advanced Report (Business intelligence)

6. **Customer Management**
   - Customer database
   - Loyalty points system
   - Flexible level system
   - Purchase history

---

## 🔒 Security

Lihat [SECURITY-AUDIT.md](../SECURITY-AUDIT.md) untuk audit keamanan lengkap.

### Security Features

- ✅ Token-based authentication (Sanctum)
- ✅ Password hashing (bcrypt)
- ✅ Rate limiting (login: 5/min, API: 60/min)
- ✅ CORS protection
- ✅ SQL injection protection (Eloquent ORM)
- ✅ XSS protection
- ✅ CSRF protection
- ✅ Security headers (X-Content-Type-Options, X-Frame-Options, etc.)
- ✅ HTTPS enforcement (production)
- ✅ Audit logging

---

## ⚡ Performance

Lihat [PERFORMANCE-OPTIMIZATION.md](../PERFORMANCE-OPTIMIZATION.md) untuk optimasi performance.

### Optimizations

- ✅ Eager loading untuk menghindari N+1 queries
- ✅ Database indexes untuk query optimization
- ✅ Query optimization dengan select specific columns
- ✅ Aggregated queries untuk statistics
- ✅ Caching support (Redis/Memcached ready)

---

## 🚀 Deployment

Lihat [DEPLOYMENT.md](./DEPLOYMENT.md) untuk panduan deployment lengkap.

### Production Checklist

- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Generate `APP_KEY`
- [ ] Setup database
- [ ] Run migrations
- [ ] Setup storage link
- [ ] Configure CORS
- [ ] Setup HTTPS
- [ ] Configure queue workers (jika ada)
- [ ] Setup backup schedule

---

## 📚 Dokumentasi Tambahan

- [DATABASE.md](./DATABASE.md) - Database schema documentation
- [API.md](./API.md) - API endpoints documentation
- [FEATURES.md](./FEATURES.md) - Features documentation
- [INSTALLATION.md](./INSTALLATION.md) - Installation guide
- [DEPLOYMENT.md](./DEPLOYMENT.md) - Deployment guide
- [SECURITY-AUDIT.md](../SECURITY-AUDIT.md) - Security audit
- [PERFORMANCE-OPTIMIZATION.md](../PERFORMANCE-OPTIMIZATION.md) - Performance optimization

---

**Last Updated**: January 2025

