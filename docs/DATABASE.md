# Database Schema Documentation

## 📊 Overview

Database Kasir POS System menggunakan struktur relasional dengan support untuk multi-outlet. Semua tabel menggunakan timestamps (`created_at`, `updated_at`) dan soft deletes untuk beberapa tabel penting.

---

## 🗄️ Tabel Database

### 1. `users`

Stores user accounts dengan role-based access control.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `name` | string | User full name |
| `email` | string(unique) | Email address |
| `email_verified_at` | timestamp | Email verification |
| `password` | string | Hashed password |
| `outlet_id` | bigint(fk) | Default outlet assignment |
| `is_active` | boolean | Account status |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Update timestamp |

**Relationships:**
- `belongsTo(Outlet)` - User's default outlet
- `hasMany(Transaction)` - User's transactions
- `hasMany(AuditLog)` - User's audit logs

---

### 2. `outlets`

Stores outlet/branch information.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `name` | string | Outlet name |
| `address` | text | Outlet address |
| `phone` | string | Contact phone |
| `email` | string | Contact email |
| `is_active` | boolean | Outlet status |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Update timestamp |

**Relationships:**
- `hasMany(User)` - Outlet users
- `hasMany(ProductStock)` - Outlet stocks
- `hasMany(Transaction)` - Outlet transactions

---

### 3. `products`

Stores product catalog.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `name` | string | Product name |
| `sku` | string(unique) | Stock keeping unit |
| `barcode` | string(unique,nullable) | Barcode |
| `description` | text(nullable) | Product description |
| `category_id` | bigint(fk) | Product category |
| `unit_id` | bigint(fk) | Measurement unit |
| `purchase_price` | decimal(15,2) | Purchase price |
| `selling_price` | decimal(15,2) | Selling price |
| `wholesale_price` | decimal(15,2) | Wholesale price |
| `min_stock` | integer | Minimum stock level |
| `image` | string(nullable) | Product image path |
| `is_active` | boolean | Product status |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Update timestamp |

**Relationships:**
- `belongsTo(Category)` - Product category
- `belongsTo(Unit)` - Measurement unit
- `hasMany(ProductStock)` - Stock per outlet
- `hasMany(TransactionItem)` - Transaction items
- `hasMany(PurchaseItem)` - Purchase items
- `hasMany(StockMovement)` - Stock movements

**Indexes:**
- `idx_products_name` - Search by name
- `idx_products_sku` - Search by SKU
- `idx_products_barcode` - Search by barcode
- `idx_products_active` - Filter by active status
- `idx_products_category_active` - Composite index

---

### 4. `categories`

Stores product categories.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `name` | string(unique) | Category name |
| `description` | text(nullable) | Category description |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Update timestamp |

**Relationships:**
- `hasMany(Product)` - Category products

---

### 5. `units`

Stores measurement units.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `name` | string(unique) | Unit name |
| `symbol` | string | Unit symbol (e.g., "kg", "pcs") |
| `description` | text(nullable) | Unit description |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Update timestamp |

**Relationships:**
- `hasMany(Product)` - Products using this unit

---

### 6. `customers`

Stores customer information dengan loyalty points system.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `name` | string | Customer name |
| `email` | string(nullable) | Email address |
| `phone` | string(nullable) | Phone number |
| `address` | text(nullable) | Address |
| `birth_date` | date(nullable) | Birth date |
| `gender` | enum(nullable) | Gender (male/female) |
| `level` | varchar(50) | Loyalty level (flexible) |
| `loyalty_points` | integer | Current loyalty points |
| `total_purchases` | decimal(15,2) | Total purchase amount |
| `is_active` | boolean | Customer status |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Update timestamp |

**Relationships:**
- `hasMany(Transaction)` - Customer transactions

**Indexes:**
- `idx_customers_name` - Search by name
- `idx_customers_phone` - Search by phone
- `idx_customers_email` - Search by email

---

### 7. `suppliers`

Stores supplier information.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `name` | string | Supplier name |
| `email` | string(nullable) | Email address |
| `phone` | string(nullable) | Phone number |
| `address` | text(nullable) | Address |
| `is_active` | boolean | Supplier status |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Update timestamp |

**Relationships:**
- `hasMany(Purchase)` - Supplier purchases

---

### 8. `transactions`

Stores sales transactions.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `transaction_number` | string(unique) | Transaction number |
| `customer_id` | bigint(fk,nullable) | Customer (walk-in if null) |
| `outlet_id` | bigint(fk) | Outlet |
| `user_id` | bigint(fk) | Cashier/user |
| `transaction_date` | datetime | Transaction datetime |
| `total_amount` | decimal(15,2) | Total amount |
| `discount` | decimal(15,2) | Total discount |
| `status` | enum | Status (pending/completed/refunded) |
| `payment_method` | enum | Payment method (cash/card/transfer) |
| `paid_amount` | decimal(15,2) | Paid amount |
| `change_amount` | decimal(15,2) | Change amount |
| `notes` | text(nullable) | Notes |
| `refunded_at` | timestamp(nullable) | Refund timestamp |
| `refunded_by` | bigint(fk,nullable) | User who refunded |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Update timestamp |

**Relationships:**
- `belongsTo(Customer)` - Transaction customer
- `belongsTo(Outlet)` - Transaction outlet
- `belongsTo(User)` - Cashier
- `hasMany(TransactionItem)` - Transaction items

**Indexes:**
- `idx_transactions_date` - Filter by date
- `idx_transactions_status` - Filter by status
- `idx_transactions_outlet_date` - Composite index
- `idx_transactions_status_date` - Composite index
- `idx_transactions_number` - Search by transaction number

---

### 9. `transaction_items`

Stores transaction line items.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `transaction_id` | bigint(fk) | Parent transaction |
| `product_id` | bigint(fk) | Product |
| `quantity` | integer | Quantity |
| `unit_price` | decimal(15,2) | Unit price at transaction |
| `total_price` | decimal(15,2) | Total price (quantity × unit_price) |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Update timestamp |

**Relationships:**
- `belongsTo(Transaction)` - Parent transaction
- `belongsTo(Product)` - Product

**Indexes:**
- `idx_transaction_items_transaction` - Filter by transaction
- `idx_transaction_items_product_transaction` - Composite index

---

### 10. `purchases`

Stores purchase orders.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `purchase_number` | string(unique) | Purchase order number |
| `supplier_id` | bigint(fk) | Supplier |
| `outlet_id` | bigint(fk) | Outlet |
| `purchase_date` | date | Purchase date |
| `total_amount` | decimal(15,2) | Total amount |
| `status` | enum | Status (pending/completed/cancelled) |
| `notes` | text(nullable) | Notes |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Update timestamp |

**Relationships:**
- `belongsTo(Supplier)` - Purchase supplier
- `belongsTo(Outlet)` - Purchase outlet
- `hasMany(PurchaseItem)` - Purchase items

**Indexes:**
- `idx_purchases_date` - Filter by date
- `idx_purchases_status` - Filter by status
- `idx_purchases_outlet_date` - Composite index

---

### 11. `purchase_items`

Stores purchase order line items.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `purchase_id` | bigint(fk) | Parent purchase |
| `product_id` | bigint(fk) | Product |
| `quantity` | integer | Quantity |
| `unit_price` | decimal(15,2) | Unit price |
| `total_price` | decimal(15,2) | Total price |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Update timestamp |

**Relationships:**
- `belongsTo(Purchase)` - Parent purchase
- `belongsTo(Product)` - Product

---

### 12. `product_stocks`

Stores stock per outlet.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `product_id` | bigint(fk) | Product |
| `outlet_id` | bigint(fk) | Outlet |
| `quantity` | integer | Current stock quantity |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Update timestamp |

**Relationships:**
- `belongsTo(Product)` - Product
- `belongsTo(Outlet)` - Outlet

**Indexes:**
- `idx_stocks_outlet_product` - Composite index (most common query)
- `idx_stocks_quantity` - Low stock check

---

### 13. `stock_movements`

Stores stock movement history.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `product_id` | bigint(fk) | Product |
| `outlet_id` | bigint(fk) | Outlet |
| `type` | enum | Movement type (in/out/adjustment/transfer) |
| `quantity` | integer | Movement quantity |
| `quantity_before` | integer | Stock before |
| `quantity_after` | integer | Stock after |
| `reference_type` | string(nullable) | Related model (Transaction, Purchase, etc.) |
| `reference_id` | bigint(nullable) | Related model ID |
| `notes` | text(nullable) | Notes |
| `user_id` | bigint(fk) | User who made the movement |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Update timestamp |

**Relationships:**
- `belongsTo(Product)` - Product
- `belongsTo(Outlet)` - Outlet
- `belongsTo(User)` - User

**Indexes:**
- `idx_stock_movements_reference` - Composite index (reference_type, reference_id)

---

### 14. `stock_transfers`

Stores stock transfers antar outlet.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `transfer_number` | string(unique) | Transfer number |
| `from_outlet_id` | bigint(fk) | Source outlet |
| `to_outlet_id` | bigint(fk) | Destination outlet |
| `transfer_date` | date | Transfer date |
| `status` | enum | Status (pending/completed/cancelled) |
| `notes` | text(nullable) | Notes |
| `user_id` | bigint(fk) | User who created transfer |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Update timestamp |

**Relationships:**
- `belongsTo(Outlet)` - From outlet
- `belongsTo(Outlet)` - To outlet
- `belongsTo(User)` - User
- `hasMany(StockTransferItem)` - Transfer items

---

### 15. `stock_transfer_items`

Stores stock transfer line items.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `stock_transfer_id` | bigint(fk) | Parent transfer |
| `product_id` | bigint(fk) | Product |
| `quantity` | integer | Transfer quantity |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Update timestamp |

**Relationships:**
- `belongsTo(StockTransfer)` - Parent transfer
- `belongsTo(Product)` - Product

---

### 16. `expenses`

Stores operational expenses (non-purchase).

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `outlet_id` | bigint(fk) | Outlet |
| `expense_date` | date | Expense date |
| `category` | string | Expense category |
| `description` | text | Expense description |
| `amount` | decimal(15,2) | Expense amount |
| `user_id` | bigint(fk) | User who created expense |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Update timestamp |

**Relationships:**
- `belongsTo(Outlet)` - Outlet
- `belongsTo(User)` - User

**Indexes:**
- `idx_expenses_date` - Filter by date
- `idx_expenses_outlet_date` - Composite index

---

### 17. `promotions`

Stores promotions & discounts.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `name` | string | Promotion name |
| `description` | text(nullable) | Description |
| `type` | enum | Type (percentage/fixed_amount) |
| `value` | decimal(15,2) | Discount value |
| `start_date` | date | Start date |
| `end_date` | date | End date |
| `is_active` | boolean | Promotion status |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Update timestamp |

**Relationships:**
- `hasMany(PromotionProduct)` - Promotion products

---

### 18. `promotion_products`

Junction table untuk promotion products.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `promotion_id` | bigint(fk) | Promotion |
| `product_id` | bigint(fk) | Product |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Update timestamp |

---

### 19. `settings`

Stores application settings (key-value).

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `key` | string(unique) | Setting key |
| `value` | text | Setting value (JSON) |
| `group` | string | Setting group |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Update timestamp |

**Setting Groups:**
- `loyalty` - Loyalty points settings
- `refund` - Refund settings
- `receipt` - Receipt settings
- `company` - Company information
- `app` - Application settings

---

### 20. `audit_logs`

Stores system audit trail.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `model_type` | string | Model class name |
| `model_id` | bigint | Model ID |
| `event` | string | Event (created/updated/deleted) |
| `old_values` | json(nullable) | Old values |
| `new_values` | json(nullable) | New values |
| `user_id` | bigint(fk,nullable) | User who made the change |
| `ip_address` | string(nullable) | IP address |
| `user_agent` | text(nullable) | User agent |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Update timestamp |

**Relationships:**
- `belongsTo(User)` - User
- `morphTo(Model)` - Related model

---

## 🔗 Relationship Diagram

```
users ──┬──> outlets
        ├──> transactions
        └──> audit_logs

outlets ──┬──> product_stocks
          ├──> transactions
          ├──> purchases
          └──> expenses

products ──┬──> product_stocks
           ├──> transaction_items
           ├──> purchase_items
           ├──> stock_movements
           └──> promotion_products

customers ──> transactions
suppliers ──> purchases

transactions ──> transaction_items
purchases ──> purchase_items
stock_transfers ──> stock_transfer_items
```

---

## 📊 Database Indexes

Semua indexes penting sudah dibuat untuk optimasi query. Lihat migration `2025_01_15_000000_add_performance_indexes.php` untuk detail lengkap.

### Key Indexes

- **Transactions**: `transaction_date`, `status`, `outlet_id`, composite indexes
- **Products**: `name`, `sku`, `barcode`, `is_active`, composite indexes
- **Product Stocks**: Composite index `(outlet_id, product_id)`
- **Customers**: `name`, `phone`, `email`

---

## 🔄 Database Migrations

Semua migrations tersimpan di `database/migrations/`. Jalankan migrations dengan:

```bash
php artisan migrate
```

Rollback migrations:

```bash
php artisan migrate:rollback
```

---

**Last Updated**: January 2025

