# 🧪 Testing Guide - POS System

## 📋 Overview

Panduan testing untuk sistem POS dengan database yang bersih dan data sample untuk testing.

## 🗄️ Database Status

### Current State (After Fresh Migration)
- ✅ **Products: 0** - Kosong untuk testing
- ✅ **Customers: 0** - Kosong untuk testing  
- ✅ **Suppliers: 0** - Kosong untuk testing
- ✅ **Transactions: 0** - Kosong untuk testing
- ✅ **Users: 5** - Admin user + role users
- ✅ **Outlets: 3** - Test outlets available
- ✅ **Categories: 10+** - Product categories
- ✅ **Units: 10+** - Product units

### Test User Credentials
- **Email:** admin@test.com
- **Password:** password
- **Role:** Super Admin
- **Outlet:** Outlet 1

## 🛠️ Testing Helper Commands

### Basic Commands
```bash
# Show database status
php testing-helper.php status

# Clean database (migrate fresh)
php testing-helper.php clean

# Show help
php testing-helper.php help
```

### Sample Data Creation
```bash
# Create sample products (5 items)
php testing-helper.php sample-products

# Create sample customers (5 customers)
php testing-helper.php sample-customers

# Create sample suppliers (3 suppliers)
php testing-helper.php sample-suppliers

# Create sample transactions (30 days of data)
php testing-helper.php sample-transactions

# Create all sample data at once
php testing-helper.php sample-all
```

## 🧪 Testing Scenarios

### 1. Empty State Testing
**Purpose:** Test how system handles empty data

**Steps:**
1. Ensure database is clean: `php testing-helper.php clean`
2. Test all pages with empty data:
   - Dashboard: `http://localhost:3000/dashboard`
   - Reports: `http://localhost:3000/reports`
   - Products: `http://localhost:3000/products`
   - Customers: `http://localhost:3000/customers`
   - POS: `http://localhost:3000/pos`

**Expected Results:**
- ✅ Empty states displayed properly
- ✅ No JavaScript errors
- ✅ Loading states work correctly
- ✅ Error boundaries catch any issues

### 2. Sample Data Testing
**Purpose:** Test system with realistic data

**Steps:**
1. Create sample data: `php testing-helper.php sample-all`
2. Test all functionality:
   - View reports with data
   - Create new transactions
   - Manage products/customers
   - Export reports

**Expected Results:**
- ✅ Charts display data correctly
- ✅ Tables show proper formatting
- ✅ Filters work as expected
- ✅ Export functions work

### 3. Report Dashboard Testing
**Purpose:** Test modular report system

**Test Cases:**
```bash
# Test with empty data
1. Go to Reports page
2. Switch between report types (Sales/Purchases/Stocks/Profit)
3. Change date ranges
4. Try export functions

# Test with sample data
1. Create sample data: php testing-helper.php sample-all
2. Test all report types
3. Test date filters
4. Test outlet filters
5. Test export CSV/HTML/Print
```

### 4. Performance Testing
**Purpose:** Test system performance

**Steps:**
1. Create large dataset:
   ```bash
   # Run sample-all multiple times for more data
   php testing-helper.php sample-all
   php testing-helper.php sample-transactions
   php testing-helper.php sample-transactions
   ```
2. Test page load times
3. Test report generation speed
4. Test export performance

## 🔍 Testing Checklist

### Frontend Testing
- [ ] All pages load without errors
- [ ] Loading states display properly
- [ ] Empty states are user-friendly
- [ ] Error boundaries catch errors
- [ ] Responsive design works on mobile
- [ ] Charts render correctly
- [ ] Tables display data properly
- [ ] Export functions work
- [ ] Filters function correctly
- [ ] Toast notifications appear

### Backend API Testing
- [ ] All endpoints return proper responses
- [ ] Error handling works correctly
- [ ] Data validation functions
- [ ] Authentication works
- [ ] Authorization enforced
- [ ] Database queries optimized
- [ ] Pagination works
- [ ] Filtering works
- [ ] Sorting works

### Report System Testing
- [ ] All report types work (Sales/Purchases/Stocks/Profit)
- [ ] Date range filters work
- [ ] Outlet filters work
- [ ] Advanced filters work
- [ ] Chart data matches table data
- [ ] Export CSV contains correct data
- [ ] Export HTML formats properly
- [ ] Print function works
- [ ] Loading states during data fetch
- [ ] Error handling for API failures

## 🐛 Common Issues & Solutions

### Issue: "No data available"
**Solution:** Create sample data using testing helper

### Issue: Charts not displaying
**Solution:** Check browser console for errors, ensure data format is correct

### Issue: Export not working
**Solution:** Check browser popup blocker, ensure data exists

### Issue: Filters not working
**Solution:** Check API endpoints, verify filter parameters

## 📊 Test Data Specifications

### Sample Products
- 5 products with different categories
- Price range: 5,000 - 25,000 IDR
- Stock quantities: 30-100 units
- All products active

### Sample Customers
- 5 customers with complete data
- Valid phone numbers and emails
- Realistic Indonesian names

### Sample Suppliers
- 3 suppliers with business names
- Complete contact information

### Sample Transactions
- 30 days of transaction history
- 1-5 transactions per day
- Multiple payment methods
- Realistic transaction amounts

## 🚀 Quick Start Testing

```bash
# 1. Clean database
php testing-helper.php clean

# 2. Check empty state
# Visit: http://localhost:3000/reports

# 3. Create sample data
php testing-helper.php sample-all

# 4. Test with data
# Visit: http://localhost:3000/reports

# 5. Check status
php testing-helper.php status
```

## 📝 Test Results Template

```
Date: ___________
Tester: ___________

Empty State Testing:
[ ] Dashboard loads correctly
[ ] Reports show empty state
[ ] No JavaScript errors
[ ] Loading states work

Sample Data Testing:
[ ] Data displays correctly
[ ] Charts render properly
[ ] Filters work
[ ] Export functions work

Performance:
[ ] Page load < 3 seconds
[ ] Report generation < 5 seconds
[ ] Export < 10 seconds

Issues Found:
1. ___________
2. ___________
3. ___________

Overall Status: [ ] PASS [ ] FAIL
```
