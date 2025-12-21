# Project Structure Documentation

## 📁 Directory Structure

```
kasir-pos-system/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/                    # API Controllers
│   │   │   │   ├── AdvancedReportController.php
│   │   │   │   ├── AuditLogController.php
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── CategoryController.php
│   │   │   │   ├── CustomerController.php
│   │   │   │   ├── DashboardController.php
│   │   │   │   ├── EnhancedReportController.php
│   │   │   │   ├── ExpenseController.php
│   │   │   │   ├── ExportImportController.php
│   │   │   │   ├── FinancialReportController.php
│   │   │   │   ├── OutletController.php
│   │   │   │   ├── ProductController.php
│   │   │   │   ├── PurchaseController.php
│   │   │   │   ├── ReceiptController.php
│   │   │   │   ├── ReportController.php
│   │   │   │   ├── SettingController.php
│   │   │   │   ├── ShiftClosingController.php
│   │   │   │   ├── StockController.php
│   │   │   │   ├── StockTransferController.php
│   │   │   │   ├── SupplierController.php
│   │   │   │   ├── TransactionController.php
│   │   │   │   ├── UnitController.php
│   │   │   │   └── UserController.php
│   │   │   └── SystemController.php    # System management
│   │   ├── Middleware/                 # Custom middleware
│   │   └── Requests/                   # Form request validation
│   ├── Models/                         # Eloquent Models
│   │   ├── User.php
│   │   ├── Outlet.php
│   │   ├── Product.php
│   │   ├── Category.php
│   │   ├── Unit.php
│   │   ├── Customer.php
│   │   ├── Supplier.php
│   │   ├── Transaction.php
│   │   ├── TransactionItem.php
│   │   ├── Purchase.php
│   │   ├── PurchaseItem.php
│   │   ├── ProductStock.php
│   │   ├── StockMovement.php
│   │   ├── StockTransfer.php
│   │   ├── StockTransferItem.php
│   │   ├── Expense.php
│   │   ├── ShiftClosing.php
│   │   ├── Setting.php
│   │   └── AuditLog.php
│   ├── Traits/
│   │   └── Auditable.php              # Audit logging trait
│   ├── Services/                       # Business logic services
│   └── Exceptions/                     # Custom exceptions
├── bootstrap/
│   ├── app.php                        # Application bootstrap
│   └── providers.php                  # Service providers
├── config/                            # Configuration files
│   ├── app.php
│   ├── auth.php
│   ├── cors.php
│   ├── database.php
│   ├── permission.php                 # Spatie permission config
│   └── sanctum.php
├── database/
│   ├── migrations/                    # Database migrations
│   ├── seeders/                       # Database seeders
│   │   ├── DatabaseSeeder.php
│   │   ├── RolePermissionSeeder.php
│   │   ├── CategoryUnitSeeder.php
│   │   ├── OutletSeeder.php
│   │   └── SettingSeeder.php
│   └── factories/                     # Model factories
├── public/                            # Public web root
│   ├── index.php
│   └── storage/                       # Storage symlink
├── resources/
│   ├── views/                         # Blade templates (if any)
│   ├── lang/                          # Language files
│   └── js/                            # Frontend assets (if any)
├── routes/
│   ├── api.php                        # API routes
│   ├── web.php                        # Web routes
│   └── console.php                    # Console routes
├── storage/
│   ├── app/                           # Application storage
│   │   ├── public/                    # Public files
│   │   │   ├── logos/                 # Company/Outlet logos
│   │   │   └── products/              # Product images
│   │   └── backups/                   # Database backups
│   ├── framework/                     # Framework files
│   └── logs/                          # Log files
├── tests/                             # Test files
├── docs/                              # Documentation
├── composer.json                      # PHP dependencies
├── package.json                       # Node dependencies (if any)
├── .env.example                       # Environment template
└── artisan                            # Artisan command line
```

---

## 🏗️ Architecture Overview

### MVC Pattern

Aplikasi ini menggunakan **Model-View-Controller (MVC)** pattern yang merupakan standar Laravel:

- **Models** (`app/Models/`): Representasi data dan business logic
- **Controllers** (`app/Http/Controllers/Api/`): Handle HTTP requests dan responses
- **Views**: Digunakan untuk receipt templates dan email (jika ada)

### API-First Architecture

Backend ini dirancang sebagai **RESTful API** yang dapat digunakan oleh berbagai frontend:
- Web Application (React)
- Desktop Application (Electron)
- Mobile Application (React Native)

### Key Components

#### 1. Models (Eloquent ORM)

Semua models menggunakan Eloquent ORM dengan relationships yang jelas:

- **User**: Authentication & authorization
- **Outlet**: Multi-outlet support
- **Product**: Product catalog
- **Transaction**: Sales transactions
- **Purchase**: Purchase orders
- **Stock**: Inventory management
- **Customer**: Customer management dengan loyalty
- **Report**: Various report types

#### 2. Controllers (API Controllers)

Semua API controllers mengikuti RESTful conventions:
- `index()` - List resources
- `show($id)` - Get single resource
- `store()` - Create new resource
- `update($id)` - Update resource
- `destroy($id)` - Delete resource

#### 3. Middleware

- `auth:sanctum` - Authentication
- `throttle` - Rate limiting
- `role:*` - Role-based access
- `permission:*` - Permission-based access

#### 4. Services

Business logic yang kompleks dipisahkan ke service classes untuk:
- Receipt generation
- Report generation
- Backup management
- Export/Import

---

## 🔄 Data Flow

### Request Flow

```
HTTP Request
    ↓
Routes (routes/api.php)
    ↓
Middleware (Auth, Throttle, Permission)
    ↓
Controller (app/Http/Controllers/Api/)
    ↓
Service/Model (Business Logic)
    ↓
Database (via Eloquent ORM)
    ↓
Response (JSON)
```

### Transaction Flow Example

```
1. User creates transaction via API
   ↓
2. TransactionController@store
   ↓
3. Validate request data
   ↓
4. Create Transaction record
   ↓
5. Create TransactionItem records
   ↓
6. Update ProductStock (decrease stock)
   ↓
7. Create StockMovement (log)
   ↓
8. Update Customer loyalty points (if applicable)
   ↓
9. Create AuditLog entry
   ↓
10. Return JSON response
```

---

## 📦 Models & Relationships

### Core Models

#### User
- `belongsTo` Outlet
- `hasMany` Transactions
- `hasMany` Purchases
- Uses `HasRoles` trait (Spatie Permission)

#### Outlet
- `hasMany` Users
- `hasMany` Transactions
- `hasMany` Purchases
- `hasMany` ProductStocks
- `hasMany` StockMovements

#### Product
- `belongsTo` Category
- `belongsTo` Unit
- `hasMany` ProductStocks (per outlet)
- `hasMany` TransactionItems
- `hasMany` PurchaseItems
- `hasMany` StockMovements

#### Transaction
- `belongsTo` Outlet
- `belongsTo` Customer (nullable)
- `belongsTo` User
- `hasMany` TransactionItems

#### Customer
- `hasMany` Transactions
- Loyalty points management
- Level-based system

#### Stock Management
- **ProductStock**: Stock quantity per outlet
- **StockMovement**: History of all stock changes
- **StockTransfer**: Transfer between outlets

---

## 🔐 Security Architecture

### Authentication
- **Laravel Sanctum**: Token-based authentication
- Token stored in `personal_access_tokens` table
- Token expiration: Configurable (default: no expiration)

### Authorization
- **Spatie Laravel Permission**: Role & Permission system
- **Roles**: Super Admin, Admin, Manager, Cashier
- **Permissions**: Granular permission per resource action

### Rate Limiting
- Login: 5 requests/minute (prevent brute force)
- API: 150 requests/minute (general)
- Barcode scan: 300 requests/minute (high frequency)

### Data Protection
- Password hashing (bcrypt)
- SQL injection protection (Eloquent ORM)
- XSS protection
- CSRF protection (API exempt, but validated)
- CORS configuration

---

## 🗄️ Database Architecture

### Key Tables

1. **users**: User accounts & authentication
2. **outlets**: Multi-outlet support
3. **products**: Product catalog
4. **categories**: Product categories
5. **units**: Measurement units
6. **customers**: Customer database
7. **transactions**: Sales transactions
8. **transaction_items**: Transaction line items
9. **product_stocks**: Stock per outlet
10. **stock_movements**: Stock change history
11. **stock_transfers**: Inter-outlet transfers
12. **purchases**: Purchase orders
13. **expenses**: Operational expenses
14. **settings**: Application settings
15. **audit_logs**: System audit trail

### Indexing Strategy

- Primary keys on all tables
- Foreign keys with indexes
- Composite indexes on frequently queried columns
- Full-text indexes on search fields (name, description)

Lihat [DATABASE.md](./DATABASE.md) untuk schema detail.

---

## 🔄 Business Logic Patterns

### Stock Management
- Stock changes are logged in `stock_movements`
- Stock quantity stored in `product_stocks` (per outlet)
- Atomic operations ensure data consistency

### Transaction Processing
- Immutable transaction records
- Price snapshots at transaction time
- Stock decrement on transaction completion
- Audit logging for all changes

### Loyalty Points
- Points calculated based on transaction amount
- Level updated automatically based on points
- Points can be manually adjusted by admin

### Audit Logging
- All model changes logged via `Auditable` trait
- Tracks: user, action, model, old/new values, IP address
- Immutable log records

---

## 📊 Reporting System

### Report Types

1. **Enhanced Report**: Sales analytics dengan charts
2. **Financial Report**: Laba/rugi comprehensive
3. **Advanced Report**: Business intelligence dashboard
4. **Sales Report**: Basic sales statistics
5. **Purchase Report**: Purchase analytics
6. **Stock Report**: Inventory reports

### Report Generation

- Aggregated queries untuk performance
- Date range filtering
- Outlet filtering
- Export to PDF/Excel

---

## 🚀 Deployment Architecture

### Production Setup

```
Nginx/Apache (Web Server)
    ↓
Laravel Application
    ↓
MySQL Database
    ↓
File Storage (Logos, Products, Backups)
```

### Queue System (Optional)

Untuk background jobs:
- Queue workers untuk heavy tasks
- Supervisor untuk process management

### Caching (Optional)

- Redis/Memcached untuk session & cache
- Query result caching
- Route caching

---

## 📝 Code Standards

### PSR Standards
- PSR-12: Extended Coding Style Guide
- PSR-4: Autoloading Standard

### Laravel Conventions
- Follow Laravel naming conventions
- Use Eloquent relationships
- Use Form Requests for validation
- Use Service classes for complex logic

### Documentation
- PHPDoc comments for methods
- Inline comments for complex logic
- README files in each module

---

**Last Updated**: January 2025

