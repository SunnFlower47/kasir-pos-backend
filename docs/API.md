# API Documentation

## 📋 Base URL

```
Production: https://kasir-pos-api.sunnflower.site/api/v1
Development: http://localhost:8000/api/v1
```

---

## 🔐 Authentication

### Login

**POST** `/login`

Request:
```json
{
  "email": "user@example.com",
  "password": "password"
}
```

Response:
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "Admin",
      "email": "admin@example.com",
      "outlet_id": 1,
      "roles": ["Super Admin"]
    },
    "token": "1|abcdef123456..."
  }
}
```

### Logout

**POST** `/logout`

Headers:
```
Authorization: Bearer {token}
```

### Profile

**GET** `/profile`

Headers:
```
Authorization: Bearer {token}
```

---

## 📦 Products

### List Products

**GET** `/products`

Query Parameters:
- `search` - Search by name, SKU, or barcode
- `category_id` - Filter by category
- `is_active` - Filter by active status
- `outlet_id` - Include stock for specific outlet
- `with_stock` - Include stock information
- `per_page` - Items per page (default: 15)
- `page` - Page number

### Get Product

**GET** `/products/{id}`

### Get Product by Barcode

**GET** `/products/barcode/scan?barcode=123456789`

### Create Product

**POST** `/products`

Permission: `products.create`

Request:
```json
{
  "name": "Product Name",
  "sku": "SKU001",
  "barcode": "123456789",
  "category_id": 1,
  "unit_id": 1,
  "purchase_price": 10000,
  "selling_price": 15000,
  "wholesale_price": 12000,
  "min_stock": 10,
  "description": "Product description",
  "is_active": true
}
```

### Update Product

**PUT** `/products/{id}`

Permission: `products.edit`

### Delete Product

**DELETE** `/products/{id}`

Permission: `products.delete`

---

## 🏷️ Categories

### List Categories

**GET** `/categories`

### Get Category

**GET** `/categories/{id}`

### Create Category

**POST** `/categories`

Permission: `categories.create`

### Update Category

**PUT** `/categories/{id}`

Permission: `categories.edit`

### Delete Category

**DELETE** `/categories/{id}`

Permission: `categories.delete`

---

## 📏 Units

### List Units

**GET** `/units`

### Get Unit

**GET** `/units/{id}`

### Create Unit

**POST** `/units`

Permission: `units.create`

### Update Unit

**PUT** `/units/{id}`

Permission: `units.edit`

### Delete Unit

**DELETE** `/units/{id}`

Permission: `units.delete`

---

## 📦 Stock Management

### List Stocks

**GET** `/stocks`

Query Parameters:
- `outlet_id` - Filter by outlet
- `low_stock` - Show only low stock items
- `search` - Search products

### Stock Movements

**GET** `/stocks/movements`

Query Parameters:
- `outlet_id` - Filter by outlet
- `product_id` - Filter by product
- `type` - Filter by type (in/out/adjustment/transfer)
- `date_from` - Start date
- `date_to` - End date

### Adjust Stock

**POST** `/stocks/adjust`

Permission: `stocks.adjustment`

Request:
```json
{
  "outlet_id": 1,
  "product_id": 1,
  "quantity": 10,
  "type": "adjustment",
  "notes": "Stock adjustment"
}
```

### Stock Opname

**POST** `/stocks/opname`

Permission: `stocks.adjustment`

### Stock Transfer

**POST** `/stocks/transfer`

Permission: `stocks.transfer`

Request:
```json
{
  "from_outlet_id": 1,
  "to_outlet_id": 2,
  "items": [
    {
      "product_id": 1,
      "quantity": 5
    }
  ],
  "notes": "Transfer notes"
}
```

### Low Stock Alerts

**GET** `/stocks/low-stock-alerts`

Permission: `stocks.view`

---

## 🛒 Transactions

### List Transactions

**GET** `/transactions`

Query Parameters:
- `outlet_id` - Filter by outlet
- `user_id` - Filter by cashier
- `customer_id` - Filter by customer
- `status` - Filter by status (pending/completed/refunded)
- `payment_method` - Filter by payment method
- `date_from` - Start date
- `date_to` - End date
- `search` - Search by transaction number
- `per_page` - Items per page
- `page` - Page number

### Get Transaction

**GET** `/transactions/{id}`

### Create Transaction

**POST** `/transactions`

Request:
```json
{
  "customer_id": 1,
  "outlet_id": 1,
  "transaction_date": "2025-01-15 10:30:00",
  "items": [
    {
      "product_id": 1,
      "quantity": 2,
      "unit_price": 15000
    }
  ],
  "payment_method": "cash",
  "paid_amount": 30000,
  "discount": 0,
  "notes": "Transaction notes"
}
```

### Update Transaction

**PUT** `/transactions/{id}`

### Refund Transaction

**POST** `/transactions/{id}/refund`

Request:
```json
{
  "reason": "Customer request"
}
```

**Note**: Refund memiliki batasan waktu berdasarkan role:
- **Cashier**: Hanya bisa refund transaksi hari ini
- **Admin/Manager**: Bisa refund sesuai batasan waktu di settings

---

## 👥 Customers

### List Customers

**GET** `/customers`

Query Parameters:
- `search` - Search by name, email, or phone
- `level` - Filter by loyalty level
- `is_active` - Filter by active status
- `per_page` - Items per page
- `page` - Page number

### Get Customer

**GET** `/customers/{id}`

### Create Customer

**POST** `/customers`

Permission: `customers.create`

Request:
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "08123456789",
  "address": "Address",
  "level": "bronze",
  "loyalty_points": 0
}
```

### Update Customer

**PUT** `/customers/{id}`

Permission: `customers.edit`

### Add Loyalty Points

**POST** `/customers/{id}/loyalty/add`

Permission: `customers.edit`

Request:
```json
{
  "points": 100,
  "notes": "Points from purchase"
}
```

### Redeem Loyalty Points

**POST** `/customers/{id}/loyalty/redeem`

Permission: `customers.edit`

Request:
```json
{
  "points": 50,
  "notes": "Redeemed for discount"
}
```

### Delete Customer

**DELETE** `/customers/{id}`

Permission: `customers.delete`

---

## 🏢 Outlets

### List Outlets

**GET** `/outlets`

### Get Outlet

**GET** `/outlets/{id}`

### Create Outlet

**POST** `/outlets`

Permission: `outlets.create`

### Update Outlet

**PUT** `/outlets/{id}`

Permission: `outlets.edit`

### Delete Outlet

**DELETE** `/outlets/{id}`

Permission: `outlets.delete`

---

## 📊 Reports

### Sales Report

**GET** `/reports/sales`

Permission: `reports.sales`

Query Parameters:
- `date_from` - Start date
- `date_to` - End date
- `outlet_id` - Filter by outlet

### Profit Report

**GET** `/reports/profit`

Permission: `reports.sales`

### Enhanced Report

**GET** `/reports/enhanced`

Permission: `reports.sales`

Query Parameters:
- `date_range` - Date range (today/week/month/year/custom)
- `custom_date_from` - Custom start date
- `custom_date_to` - Custom end date
- `outlet_id` - Filter by outlet

### Financial Report

**GET** `/reports/financial/comprehensive`

Permission: `reports.sales`

Query Parameters:
- `date_from` - Start date
- `date_to` - End date
- `outlet_id` - Filter by outlet

### Advanced Report (Business Intelligence)

**GET** `/reports/business-intelligence`

Permission: `reports.sales`

### Purchases Report

**GET** `/reports/purchases`

Permission: `reports.purchases`

### Expenses Report

**GET** `/reports/expenses`

Permission: `reports.purchases`

### Stocks Report

**GET** `/reports/stocks`

Permission: `reports.stocks`

---

## 💰 Expenses

### List Expenses

**GET** `/expenses`

Permission: `expenses.view`

Query Parameters:
- `outlet_id` - Filter by outlet
- `date_from` - Start date
- `date_to` - End date
- `category` - Filter by category
- `per_page` - Items per page
- `page` - Page number

### Get Expense

**GET** `/expenses/{id}`

Permission: `expenses.view`

### Create Expense

**POST** `/expenses`

Permission: `expenses.create`

Request:
```json
{
  "outlet_id": 1,
  "expense_date": "2025-01-15",
  "category": "Operational",
  "description": "Office supplies",
  "amount": 50000
}
```

### Update Expense

**PUT** `/expenses/{id}`

Permission: `expenses.edit`

### Delete Expense

**DELETE** `/expenses/{id}`

Permission: `expenses.delete`

### Expense Categories

**GET** `/expenses/categories/list`

Permission: `expenses.view`

---

## 📦 Purchases

### List Purchases

**GET** `/purchases`

Permission: `purchases.view`

### Get Purchase

**GET** `/purchases/{id}`

Permission: `purchases.view`

### Create Purchase

**POST** `/purchases`

Permission: `purchases.create`

### Update Purchase

**PUT** `/purchases/{id}`

Permission: `purchases.edit`

### Update Purchase Status

**PATCH** `/purchases/{id}/status`

Permission: `purchases.edit`

### Delete Purchase

**DELETE** `/purchases/{id}`

Permission: `purchases.delete`

---

## 👨‍💼 Users & Roles

### List Users

**GET** `/users`

**Role**: Super Admin only

### Create User

**POST** `/users`

**Role**: Super Admin only

### Update User

**PUT** `/users/{id}`

**Role**: Super Admin only

### Delete User

**DELETE** `/users/{id}`

**Role**: Super Admin only

### Get Roles

**GET** `/roles`

**Role**: Super Admin only

### Get Permissions

**GET** `/permissions`

**Role**: Super Admin only

### Update Role Permissions

**PUT** `/roles/{role}/permissions`

**Role**: Super Admin only

---

## ⚙️ Settings

### Get Settings

**GET** `/settings`

**Role**: Super Admin, Admin

### Update Settings

**PUT** `/settings`

**Role**: Super Admin, Admin

### Get Settings Group

**GET** `/settings/{group}`

**Role**: Super Admin, Admin

Groups: `loyalty`, `refund`, `receipt`, `company`, `app`

### Update Settings Group

**PUT** `/settings/{group}`

**Role**: Super Admin, Admin

### Upload Logo

**POST** `/settings/logo/upload`

**Role**: Super Admin, Admin

---

## 📋 Audit Logs

### List Audit Logs

**GET** `/audit-logs`

Permission: `audit-logs.view`

Query Parameters:
- `user_id` - Filter by user
- `model_type` - Filter by model type
- `event` - Filter by event (created/updated/deleted)
- `date_from` - Start date
- `date_to` - End date
- `ip_address` - Search by IP
- `per_page` - Items per page
- `page` - Page number

### Get Audit Log

**GET** `/audit-logs/{id}`

Permission: `audit-logs.view`

### Get Statistics

**GET** `/audit-logs/statistics`

Permission: `audit-logs.view`

Query Parameters:
- `date_from` - Start date (default: 30 days ago)
- `date_to` - End date (default: today)

### Cleanup Audit Logs

**DELETE** `/audit-logs/cleanup?days=90`

Permission: `audit-logs.delete`

---

## 📊 Dashboard

### Get Dashboard Data

**GET** `/dashboard`

Query Parameters:
- `outlet_id` - Filter by outlet

### Outlet Comparison

**GET** `/dashboard/outlet-comparison`

**Role**: Super Admin, Admin, Manager

---

## 🧾 Receipts

### Generate PDF Receipt

**GET** `/transactions/{id}/receipt/pdf`

Query Parameters:
- `template` - Template type (default/simple/58mm)

### Generate HTML Receipt

**GET** `/transactions/{id}/receipt/html`

### Public Receipt (No Auth)

**GET** `/public/transactions/{id}/receipt/pdf`
**GET** `/public/transactions/{id}/receipt/html`
**GET** `/public/transactions/{id}/receipt/simple`
**GET** `/public/transactions/{id}/receipt/58mm`

---

## 📤 Response Format

### Success Response

```json
{
  "success": true,
  "data": {...},
  "message": "Optional message"
}
```

### Error Response

```json
{
  "success": false,
  "message": "Error message",
  "errors": {
    "field": ["Error detail"]
  }
}
```

### Paginated Response

```json
{
  "success": true,
  "data": {
    "data": [...],
    "current_page": 1,
    "last_page": 10,
    "per_page": 15,
    "total": 150
  }
}
```

---

## 🔒 Rate Limiting

- **Login**: 5 requests per minute
- **API**: 60 requests per minute

Response headers:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
```

---

## ❌ Error Codes

- `401` - Unauthorized (Invalid/missing token)
- `403` - Forbidden (No permission)
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

---

**Last Updated**: January 2025

