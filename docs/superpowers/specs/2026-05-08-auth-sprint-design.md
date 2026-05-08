# Design: Authentication Sprint

**Date:** 2026-05-08
**Project:** Sistem Informasi Absensi Siswa — SMAN 10 Tangerang Selatan
**Scope:** Sprint 1 — Authentication, role-based dashboard, Bootstrap Icons

---

## 1. Goal

Implement a complete authentication system with role-based single dashboard. All accounts are created by Admin — no self-registration. Bootstrap Icons replaces all inline SVG paths.

---

## 2. Scope (What's Included)

- Bootstrap Icons via CDN in `header.php`
- `login.php` updated: Bootstrap Icons, remove "Daftar Disini" link
- `process_login.php`: validate credentials, set session, redirect to dashboard
- `logout.php`: destroy session, redirect to login
- `includes/auth_check.php`: `require_auth($roles)` guard function
- `includes/sidebar.php`: single file, role-based menu logic inside
- `includes/navbar.php`: topbar with user name, role badge, logout
- `dashboard.php`: single entry point for all roles, welcome card
- `database/absensi.sql`: CREATE TABLE users + all related tables + seed admin

**Out of scope:** Actual module pages (scan, CRUD, reports) — those are future sprints.

---

## 3. No Self-Registration

All accounts created by Admin. Roles:

| Role | Created By | Login |
|------|-----------|-------|
| admin | Pre-seeded SQL | Yes |
| guru | Admin panel (future sprint) | Yes |
| siswa | Admin panel (future sprint) | Yes |
| orang_tua | Admin panel (future sprint) | Yes |

"Daftar Disini" link removed from login page.

---

## 4. Database Schema

### Tabel `users`
```sql
CREATE TABLE users (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  nama        VARCHAR(100) NOT NULL,
  email       VARCHAR(100) UNIQUE NOT NULL,
  password    VARCHAR(255) NOT NULL,
  role        ENUM('admin','guru','siswa','orang_tua') NOT NULL,
  foto_profil VARCHAR(255) DEFAULT NULL
);
```

### Tabel `guru`
```sql
CREATE TABLE guru (
  id            INT PRIMARY KEY AUTO_INCREMENT,
  user_id       INT NOT NULL,
  nip           VARCHAR(30),
  nama_guru     VARCHAR(100) NOT NULL,
  jenis_kelamin ENUM('L','P'),
  no_hp         VARCHAR(20),
  alamat        TEXT,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Tabel `kelas`
```sql
CREATE TABLE kelas (
  id            INT PRIMARY KEY AUTO_INCREMENT,
  nama_kelas    VARCHAR(50) NOT NULL,
  wali_kelas_id INT,
  tingkat       VARCHAR(10),
  tahun_ajaran  VARCHAR(20),
  FOREIGN KEY (wali_kelas_id) REFERENCES guru(id) ON DELETE SET NULL
);
```

### Tabel `siswa`
```sql
CREATE TABLE siswa (
  id            INT PRIMARY KEY AUTO_INCREMENT,
  user_id       INT,
  nis           VARCHAR(30) UNIQUE NOT NULL,
  nama_siswa    VARCHAR(100) NOT NULL,
  jenis_kelamin ENUM('L','P'),
  kelas_id      INT,
  barcode       VARCHAR(255) UNIQUE,
  foto          VARCHAR(255),
  alamat        TEXT,
  no_hp         VARCHAR(20),
  status        ENUM('aktif','nonaktif') DEFAULT 'aktif',
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE SET NULL
);
```

### Tabel `orang_tua`
```sql
CREATE TABLE orang_tua (
  id             INT PRIMARY KEY AUTO_INCREMENT,
  user_id        INT,
  siswa_id       INT NOT NULL,
  nama_orang_tua VARCHAR(100) NOT NULL,
  no_hp          VARCHAR(20),
  alamat         TEXT,
  hubungan       ENUM('ayah','ibu','wali'),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
);
```

### Tabel `absensi`
```sql
CREATE TABLE absensi (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  siswa_id          INT NOT NULL,
  guru_id           INT NOT NULL,
  tanggal_absensi   DATE NOT NULL,
  waktu_scan        TIME NOT NULL,
  status_kehadiran  ENUM('hadir','izin','sakit','alfa') NOT NULL DEFAULT 'hadir',
  metode_absensi    ENUM('barcode','manual') NOT NULL,
  keterangan        TEXT,
  UNIQUE KEY unique_absensi (siswa_id, tanggal_absensi),
  FOREIGN KEY (siswa_id) REFERENCES siswa(id),
  FOREIGN KEY (guru_id) REFERENCES guru(id)
);
```

### Tabel `jadwal_absensi`
```sql
CREATE TABLE jadwal_absensi (
  id                   INT PRIMARY KEY AUTO_INCREMENT,
  kelas_id             INT NOT NULL,
  hari                 ENUM('senin','selasa','rabu','kamis','jumat','sabtu') NOT NULL,
  jam_masuk            TIME NOT NULL,
  batas_telat          TIME NOT NULL,
  jam_selesai_absensi  TIME NOT NULL,
  status               ENUM('aktif','nonaktif') DEFAULT 'aktif',
  FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE
);
```

### Tabel `log_scan`
```sql
CREATE TABLE log_scan (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  siswa_id    INT,
  barcode     VARCHAR(255) NOT NULL,
  hasil_scan  ENUM('berhasil','gagal') NOT NULL,
  pesan       VARCHAR(255),
  FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE SET NULL
);
```

### Seed Admin
Hash di-generate saat implementasi via `php -r "echo password_hash('admin123', PASSWORD_DEFAULT);"` dan dimasukkan ke SQL. Password default admin: `admin123`.

```sql
-- Jalankan php -r "echo password_hash('admin123', PASSWORD_DEFAULT);" untuk dapatkan hash
INSERT INTO users (nama, email, password, role)
VALUES ('Administrator', 'admin@sman10.sch.id', '<generated_hash>', 'admin');
```

---

## 5. Auth Flow

```
POST /modules/auth/process_login.php
  → validate email + password against users table
  → if valid: set session, redirect to /dashboard.php
  → if invalid: redirect to login.php?error=...

/dashboard.php
  → require_auth(['admin','guru','siswa','orang_tua'])
  → include sidebar.php (role-based menu)
  → include navbar.php
  → show welcome card with nama + role

/modules/auth/logout.php
  → session_destroy()
  → redirect to /modules/auth/login.php
```

### Session Variables
```php
$_SESSION['user_id']  // INT
$_SESSION['role']     // 'admin'|'guru'|'siswa'|'orang_tua'
$_SESSION['nama']     // string
$_SESSION['foto']     // string|null
```

### `require_auth($roles)` — includes/auth_check.php
```php
function require_auth(array $roles = []): void {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header('Location: /absensi/modules/auth/login.php');
        exit;
    }
    if (!empty($roles) && !in_array($_SESSION['role'], $roles)) {
        header('Location: /absensi/dashboard.php');
        exit;
    }
}
```

---

## 6. Role-Based Menu (sidebar.php)

Single file with PHP conditionals:

| Menu Item | admin | guru | siswa | orang_tua |
|-----------|:-----:|:----:|:-----:|:---------:|
| Dashboard | ✓ | ✓ | ✓ | ✓ |
| Manajemen User | ✓ | | | |
| Manajemen Siswa | ✓ | | | |
| Manajemen Kelas | ✓ | | | |
| Scan Absensi | | ✓ | | |
| Input Manual | | ✓ | | |
| Rekap Kehadiran | ✓ | ✓ | ✓ | |
| Laporan Anak | | | | ✓ |
| Profil Saya | ✓ | ✓ | ✓ | ✓ |

---

## 7. File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Create | `database/absensi.sql` | Full schema + seed |
| Modify | `includes/header.php` | Add Bootstrap Icons CDN |
| Create | `includes/auth_check.php` | `require_auth()` guard |
| Create | `includes/sidebar.php` | Role-based nav menu |
| Create | `includes/navbar.php` | Topbar: user name, role, logout |
| Modify | `modules/auth/login.php` | BI icons, remove register link |
| Create | `modules/auth/process_login.php` | Login handler |
| Create | `modules/auth/logout.php` | Logout handler |
| Create | `dashboard.php` | Single dashboard entry point |

---

## 8. Security

- `password_hash()` / `password_verify()` for all passwords
- PDO prepared statements for all queries
- `session_regenerate_id(true)` after login to prevent session fixation
- `htmlspecialchars()` on all output
- Role check on every protected page via `require_auth()`
