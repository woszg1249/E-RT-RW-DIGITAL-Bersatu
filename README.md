# Keuangan RT / RT RW Digital Bersatu

Aplikasi manajemen keuangan dan administrasi RT/RW berbasis web (PHP native + MySQLi).

## 📋 Fitur Utama

### Admin
- Kelola warga dan kartu keluarga (KK)
- Kelola tagihan iuran bulanan
- Input dan laporan kas RT
- Kelola kegiatan, posyandu, bank sampah, koperasi
- Kelola UMKM warga
- Notifikasi tagihan
- Laporan keuangan
- Pengajuan dan persetujuan surat

### Warga
- Lihat dan bayar tagihan iuran
- Lihat laporan kas RT
- Input data posyandu, bank sampah, koperasi (sesuai hak akses)
- Ajukan surat keterangan
- Update profil dan foto

## 🔒 Fitur Keamanan

✅ **Password hashing** dengan bcrypt (PASSWORD_DEFAULT)  
✅ **CSRF protection** pada semua form mutasi data  
✅ **SQL injection prevention** dengan prepared statements  
✅ **File upload validation** (MIME type, size, content verification)  
✅ **Authorization** (require_login, require_admin)  
✅ **Session security** (session_regenerate_id setelah login)  
✅ **XSS prevention** (htmlspecialchars pada output)  
✅ **Secure password reset** dengan verifikasi ganda  

## 🚀 Teknologi

- **Backend**: PHP 7.4+ (native, no framework)
- **Database**: MySQL/MariaDB (mysqli extension)
- **Frontend**: HTML5, CSS3, JavaScript vanilla
- **PWA**: Service Worker untuk Progressive Web App

## 📦 Instalasi Lokal (Development)

### Requirements
- PHP 7.4 atau lebih tinggi
- MySQL/MariaDB
- Web server (Apache/Nginx)
- Ekstensi PHP: mysqli, gd, mbstring, session

### Langkah Instalasi

1. **Clone atau extract proyek**
   ```bash
   git clone <repository-url>
   # atau extract ZIP ke folder web server
   ```

2. **Buat database**
   ```sql
   CREATE DATABASE keuangan_rt;
   ```

3. **Konfigurasi database**
   Edit file `Koneksi.php` (baris 5-8):
   ```php
   $host = "localhost";
   $user = "root";
   $pass = "";
   $db   = "keuangan_rt";
   ```

4. **Import struktur database** (jika ada file SQL)
   ```bash
   mysql -u root keuangan_rt < database/keuangan_rt.sql
   ```
   Atau biarkan aplikasi membuat tabel secara otomatis saat pertama kali diakses.

5. **Set permission folder img/**
   ```bash
   chmod 755 img/
   ```

6. **Akses aplikasi**
   ```
   http://localhost/Keuangan%20rt%20apps/
   ```

7. **Buat akun dummy (opsional, hanya development)**
   ```bash
   php create_dummy_accounts.php
   ```
   Password default: `Password123`

## 🌐 Deployment ke Hosting

Lihat file **DEPLOYMENT_GUIDE.txt** untuk panduan lengkap deployment ke hosting online.

### Ringkas:
1. Edit `Koneksi.php` sesuai konfigurasi database hosting
2. Upload semua file **KECUALI**: `.git/`, `create_dummy_accounts.php`, `database/*.sql`
3. Buat `.htaccess` di folder `img/` untuk mencegah eksekusi PHP
4. Set permission: folder 755, file 644
5. Test semua fitur di hosting

## 🔐 Keamanan Production

### Wajib dilakukan sebelum production:
- ✅ Ubah password database di `Koneksi.php`
- ✅ Jangan upload `create_dummy_accounts.php`
- ✅ Jangan upload folder `.git/`
- ✅ Aktifkan SSL/HTTPS
- ✅ Set file permission yang benar
- ✅ Buat `.htaccess` di folder `img/`
- ✅ Ubah password admin default

### File yang JANGAN di-upload ke hosting:
```
.git/
.gitignore
database/*.sql
create_dummy_accounts.php
DEPLOYMENT_GUIDE.txt (opsional)
*.log
.env (jika ada)
```

## 📁 Struktur Folder

```
Keuangan rt apps/
├── folder dashboard/          # Halaman dashboard admin/warga
│   ├── dashboard.php
│   ├── kelola_warga.php
│   ├── kelola_kk.php
│   ├── kelola_tagihan.php
│   ├── input_kas.php
│   ├── laporan.php
│   ├── proses_*.php          # File pemrosesan form
│   └── ...
├── folder login,logout dan register/  # Autentikasi
│   ├── login.php
│   ├── register.php
│   ├── lupa_password.php
│   └── reset_password.php
├── includes/                  # Header, sidebar, footer
│   ├── header.php
│   ├── sidebar.php
│   └── footer.php
├── img/                       # Upload gambar (foto, bukti)
├── js/                        # JavaScript
├── assets/                    # CSS dan assets lainnya
├── Koneksi.php               # Konfigurasi database
├── profil.php                # Halaman profil user
├── proses_update_profil.php  # Update profil
├── index.php                 # Landing page
├── manifest.json             # PWA manifest
└── sw.js                     # Service worker

```

## 👤 Akun Default

Setelah instalasi, buat akun admin via halaman register atau import data SQL.

**PENTING**: Ubah password default segera setelah login pertama!

## 🐛 Troubleshooting

### Error: "Cannot connect to database"
→ Cek konfigurasi di `Koneksi.php`

### Error: "Table doesn't exist"
→ Import struktur database atau biarkan aplikasi membuat tabel otomatis

### Error upload file
→ Cek permission folder `img/` (harus 755)  
→ Cek `php.ini`: `upload_max_filesize` dan `post_max_size` minimal 2M

### Session tidak berfungsi
→ Pastikan `session.save_path` writable  
→ Cek `php.ini`: `session.auto_start = 0`

## 📝 Catatan Pengembangan

Project ini dibuat untuk tugas kuliah dengan fokus pada:
- Keamanan aplikasi web (password hashing, CSRF, SQL injection prevention)
- File upload security
- Authorization dan authentication
- Best practices PHP native

Semua data adalah dummy untuk keperluan demonstrasi.

## 📄 Lisensi

Project untuk keperluan akademik/tugas kuliah.

---

**Developed**: 2024-2026  
**Last Update**: 2026-11-04
