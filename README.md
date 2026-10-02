# WP Assessment 1 — Panduan Setup untuk Teman Kelompok

## Prasyarat

Pastikan sudah install salah satu local server berikut:

- **[Laragon](https://laragon.org/)** (Recommended, paling gampang)
- XAMPP
- MAMP
- atau local server lain yang support **PHP** + **MySQL**

---

## Langkah-langkah Setup

### 1. Clone Repository

```bash
cd C:\laragon\www
git clone https://github.com/irpanno1/wp-asessment1.git
```

> **Catatan:** Kalau pakai XAMPP, clone ke folder `C:\xampp\htdocs\` ya.

---

### 2. Buat Database

1. Buka **phpMyAdmin** → biasanya di `http://localhost/phpmyadmin`
   - Kalau pakai Laragon: klik kanan icon Laragon di tray → **MySQL** → **phpMyAdmin**
2. Klik **"New"** (buat database baru)
3. Nama database: **`wp_praltikum1`**
4. Collation: pilih **`utf8mb4_general_ci`**
5. Klik **Create**

---

### 3. Import Database

1. Di phpMyAdmin, klik database **`wp_praltikum1`** yang baru dibuat
2. Klik tab **"Import"**
3. Klik **"Choose File"** → pilih file `database.sql` yang ada di folder project
4. Klik **"Go"** / **"Import"**
5. Tunggu sampai selesai ✅

---

### 4. Buat File `wp-config.php`

File `wp-config.php` **tidak ikut di-push ke GitHub** (demi keamanan). Kamu perlu buat sendiri:

1. Copy file `wp-config-sample.php` dan rename jadi `wp-config.php`
2. Edit bagian database settings:

```php
/** The name of the database for WordPress */
define( 'DB_NAME', 'wp_praltikum1' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );
```

> **Note:** Kalau pakai XAMPP, settingan di atas biasanya sudah sesuai (user `root`, password kosong). Kalau ada password MySQL, sesuaikan ya.

---

### 5. Update URL di Database (PENTING!)

Karena URL WordPress biasanya berbeda di setiap komputer, kamu perlu update URL-nya.

1. Buka **phpMyAdmin** → pilih database `wp_praltikum1`
2. Klik tabel **`wp_options`**
3. Cari dan edit 2 baris ini:

| option_name | Ganti option_value menjadi |
|---|---|
| `siteurl` | `http://localhost/wp-asessment1` |
| `home` | `http://localhost/wp-asessment1` |

> Sesuaikan dengan nama folder tempat kamu clone repo-nya.

---

### 6. Jalankan Website

1. Pastikan **Laragon** / **XAMPP** sudah running (Apache + MySQL)
2. Buka browser → akses: **`http://localhost/wp-asessment1`**
3. Untuk masuk ke admin: **`http://localhost/wp-asessment1/wp-admin`**

---

## Troubleshooting

### Error "Error establishing a database connection"
- Cek apakah MySQL sudah running
- Cek nama database, username, password di `wp-config.php` sudah benar

### Halaman putih / blank
- Cek apakah file `wp-config.php` sudah dibuat
- Cek apakah database sudah di-import

### Gambar / media tidak muncul
- Pastikan folder `wp-content/uploads` sudah ikut ter-clone

### Tampilan berantakan / redirect ke URL lain
- Pastikan sudah update `siteurl` dan `home` di tabel `wp_options` (lihat Langkah 5)

---

## Struktur Project

```
wp-asessment1/
├── database.sql          ← File database (import ini ke phpMyAdmin)
├── wp-config-sample.php  ← Template config (copy jadi wp-config.php)
├── wp-content/
│   ├── themes/           ← Theme yang dipakai
│   │   ├── astra/
│   │   └── kadence/
│   ├── plugins/          ← Plugin yang diinstall
│   │   ├── elementor/
│   │   ├── latepoint/
│   │   └── ...
│   └── uploads/          ← Gambar & media
└── ... (file core WordPress lainnya)
```

---

## Tech Stack

- **WordPress** (CMS)
- **Elementor** (Page Builder)
- **Astra / Kadence** (Themes)
- **Laragon** (Local Development Server)

---

> 💡 **Tips:** Kalau ada masalah, tanya di grup atau hubungi yang push repo ini ya!
