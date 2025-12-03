# Backup Database Troubleshooting Guide

## Masalah: Tombol Backup Database Tidak Berfungsi di Komputer Lain

### Penyebab Umum:
1. **Path Database Tidak Ditemukan**
2. **Permission Directory Tidak Cukup**
3. **File Database Tidak Ada**
4. **Konfigurasi Environment Berbeda**

### Solusi:

#### 1. Jalankan Script Perbaikan
```bash
php fix-backup-permissions.php
```

#### 2. Cek Database Path
Pastikan file `database.sqlite` ada di salah satu lokasi ini:
- `database/database.sqlite`
- `storage/database.sqlite`
- Path yang dikonfigurasi di `.env`

#### 3. Cek Permission Directory
```bash
# Linux/Mac
chmod 755 storage/app/backups
chown -R www-data:www-data storage/

# Windows
# Pastikan folder storage/app/backups bisa diakses oleh web server
```

#### 4. Cek File .env
Pastikan konfigurasi database benar:
```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

#### 5. Test Manual Backup
Jalankan script test:
```bash
php test-backup.php
```

#### 6. Cek Log Error
Lihat log Laravel di `storage/logs/laravel.log` untuk error detail.

### Troubleshooting Step by Step:

#### Step 1: Cek Database File
```bash
# Cek apakah database.sqlite ada
ls -la database/database.sqlite
# atau di Windows
dir database\database.sqlite
```

#### Step 2: Cek Permission
```bash
# Cek permission storage directory
ls -la storage/app/
# Pastikan folder backups ada dan writable
```

#### Step 3: Test Backup Manual
```bash
# Jalankan script backup manual
php storage/app/backups/manual-backup.php
```

#### Step 4: Cek Web Server Log
- Apache: `/var/log/apache2/error.log`
- Nginx: `/var/log/nginx/error.log`
- Laravel: `storage/logs/laravel.log`

### Solusi untuk Komputer Lain:

#### 1. Copy File Database
Pastikan file `database.sqlite` ikut ter-copy ke komputer baru.

#### 2. Set Permission
```bash
# Linux/Mac
sudo chown -R www-data:www-data storage/
sudo chmod -R 755 storage/

# Windows
# Run as Administrator dan set permission untuk folder storage
```

#### 3. Update .env
Pastikan path database di `.env` sesuai dengan lokasi file di komputer baru.

#### 4. Clear Cache
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

#### 5. Test Koneksi Database
```bash
php artisan tinker
# Di tinker:
DB::connection()->getPdo();
```

### Error Messages dan Solusi:

#### "Database file not found"
- Pastikan file `database.sqlite` ada
- Cek path di `.env`
- Jalankan `php test-backup.php`

#### "Permission denied"
- Set permission folder storage
- Jalankan `php fix-backup-permissions.php`

#### "Directory not writable"
- Cek permission folder `storage/app/backups`
- Pastikan web server bisa menulis ke folder tersebut

#### "Backup creation failed"
- Cek log error di `storage/logs/laravel.log`
- Pastikan database file bisa dibaca
- Test dengan script manual backup

### Script Otomatis untuk Setup:

```bash
#!/bin/bash
# setup-backup.sh

echo "Setting up backup functionality..."

# Create backup directory
mkdir -p storage/app/backups
chmod 755 storage/app/backups

# Set permissions
chmod -R 755 storage/
chown -R www-data:www-data storage/

# Create .htaccess for security
echo "Order Deny,Allow" > storage/app/backups/.htaccess
echo "Deny from all" >> storage/app/backups/.htaccess

# Test backup
php test-backup.php

echo "Backup setup completed!"
```

### Kontak Support:
Jika masalah masih berlanjut, kirimkan:
1. Output dari `php test-backup.php`
2. Log error dari `storage/logs/laravel.log`
3. Konfigurasi `.env` (tanpa password)
4. Informasi sistem operasi dan web server

