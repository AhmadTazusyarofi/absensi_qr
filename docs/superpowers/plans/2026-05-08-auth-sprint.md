# Auth Sprint Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement complete role-based authentication with single dashboard, Bootstrap Icons, and all DB tables seeded.

**Architecture:** Session-based PHP auth. `process_login.php` validates credentials and sets session. `dashboard.php` is the single entry point for all roles — `sidebar.php` renders different menus based on `$_SESSION['role']`. `includes/auth_check.php` provides `require_auth()` guard used on every protected page.

**Tech Stack:** Native PHP 8+, MySQL/PDO, TailwindCSS v4, Bootstrap Icons 1.11 (CDN)

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Create | `database/absensi.sql` | Full schema + seed admin |
| Modify | `includes/header.php` | Add Bootstrap Icons CDN link |
| Create | `includes/auth_check.php` | `require_auth()` guard function |
| Create | `includes/sidebar.php` | Role-based nav menu (all roles, one file) |
| Create | `includes/navbar.php` | Topbar: user name, role badge, logout button |
| Modify | `modules/auth/login.php` | Replace SVGs with BI icons, remove register link |
| Create | `modules/auth/process_login.php` | POST handler: validate, set session, redirect |
| Create | `modules/auth/logout.php` | Destroy session, redirect to login |
| Create | `dashboard.php` | Single dashboard entry point, welcome card |

---

## Task 1: Database Schema & Seed

**Files:**
- Create: `database/absensi.sql`

- [ ] **Step 1: Create database directory**

```bash
mkdir -p C:/xampp_kmp/htdocs/absensi/database
```

- [ ] **Step 2: Generate password hash for admin**

Run in terminal:
```bash
php -r "echo password_hash('admin123', PASSWORD_DEFAULT);"
```

Copy the output (looks like `$2y$10$...`). You will paste it in Step 3.

- [ ] **Step 3: Create database/absensi.sql**

Create `database/absensi.sql` — replace `<HASH>` with the output from Step 2:

```sql
-- ============================================================
-- Sistem Absensi SMAN 10 Tangerang Selatan
-- ============================================================

CREATE DATABASE IF NOT EXISTS absensi
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE absensi;

-- 1. users
CREATE TABLE IF NOT EXISTS users (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  nama        VARCHAR(100) NOT NULL,
  email       VARCHAR(100) UNIQUE NOT NULL,
  password    VARCHAR(255) NOT NULL,
  role        ENUM('admin','guru','siswa','orang_tua') NOT NULL,
  foto_profil VARCHAR(255) DEFAULT NULL
);

-- 2. guru
CREATE TABLE IF NOT EXISTS guru (
  id            INT PRIMARY KEY AUTO_INCREMENT,
  user_id       INT NOT NULL,
  nip           VARCHAR(30),
  nama_guru     VARCHAR(100) NOT NULL,
  jenis_kelamin ENUM('L','P'),
  no_hp         VARCHAR(20),
  alamat        TEXT,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. kelas
CREATE TABLE IF NOT EXISTS kelas (
  id            INT PRIMARY KEY AUTO_INCREMENT,
  nama_kelas    VARCHAR(50) NOT NULL,
  wali_kelas_id INT DEFAULT NULL,
  tingkat       VARCHAR(10),
  tahun_ajaran  VARCHAR(20),
  FOREIGN KEY (wali_kelas_id) REFERENCES guru(id) ON DELETE SET NULL
);

-- 4. siswa
CREATE TABLE IF NOT EXISTS siswa (
  id            INT PRIMARY KEY AUTO_INCREMENT,
  user_id       INT DEFAULT NULL,
  nis           VARCHAR(30) UNIQUE NOT NULL,
  nama_siswa    VARCHAR(100) NOT NULL,
  jenis_kelamin ENUM('L','P'),
  kelas_id      INT DEFAULT NULL,
  barcode       VARCHAR(255) UNIQUE,
  foto          VARCHAR(255),
  alamat        TEXT,
  no_hp         VARCHAR(20),
  status        ENUM('aktif','nonaktif') DEFAULT 'aktif',
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE SET NULL
);

-- 5. orang_tua
CREATE TABLE IF NOT EXISTS orang_tua (
  id             INT PRIMARY KEY AUTO_INCREMENT,
  user_id        INT DEFAULT NULL,
  siswa_id       INT NOT NULL,
  nama_orang_tua VARCHAR(100) NOT NULL,
  no_hp          VARCHAR(20),
  alamat         TEXT,
  hubungan       ENUM('ayah','ibu','wali'),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
);

-- 6. absensi
CREATE TABLE IF NOT EXISTS absensi (
  id               INT PRIMARY KEY AUTO_INCREMENT,
  siswa_id         INT NOT NULL,
  guru_id          INT NOT NULL,
  tanggal_absensi  DATE NOT NULL,
  waktu_scan       TIME NOT NULL,
  status_kehadiran ENUM('hadir','izin','sakit','alfa') NOT NULL DEFAULT 'hadir',
  metode_absensi   ENUM('barcode','manual') NOT NULL,
  keterangan       TEXT,
  UNIQUE KEY unique_absensi (siswa_id, tanggal_absensi),
  FOREIGN KEY (siswa_id) REFERENCES siswa(id),
  FOREIGN KEY (guru_id) REFERENCES guru(id)
);

-- 7. jadwal_absensi
CREATE TABLE IF NOT EXISTS jadwal_absensi (
  id                  INT PRIMARY KEY AUTO_INCREMENT,
  kelas_id            INT NOT NULL,
  hari                ENUM('senin','selasa','rabu','kamis','jumat','sabtu') NOT NULL,
  jam_masuk           TIME NOT NULL,
  batas_telat         TIME NOT NULL,
  jam_selesai_absensi TIME NOT NULL,
  status              ENUM('aktif','nonaktif') DEFAULT 'aktif',
  FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE
);

-- 8. log_scan
CREATE TABLE IF NOT EXISTS log_scan (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  siswa_id   INT DEFAULT NULL,
  barcode    VARCHAR(255) NOT NULL,
  hasil_scan ENUM('berhasil','gagal') NOT NULL,
  pesan      VARCHAR(255),
  FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE SET NULL
);

-- ============================================================
-- SEED: Admin awal (password: admin123)
-- ============================================================
INSERT INTO users (nama, email, password, role) VALUES
('Administrator', 'admin@sman10.sch.id', '<HASH>', 'admin');
```

- [ ] **Step 4: Run SQL di phpMyAdmin**

Buka `http://localhost/phpmyadmin` → tab SQL → paste isi `absensi.sql` → klik Go.

Expected: semua tabel terbuat, 1 row di tabel `users`.

- [ ] **Step 5: Verify**

Di phpMyAdmin, klik tabel `users`. Pastikan ada 1 row dengan role `admin`.

- [ ] **Step 6: Commit**

```bash
git add database/absensi.sql
git commit -m "feat: add full database schema and seed admin"
```

---

## Task 2: Bootstrap Icons & auth_check.php

**Files:**
- Modify: `includes/header.php`
- Create: `includes/auth_check.php`

- [ ] **Step 1: Add Bootstrap Icons CDN to header.php**

Buka `includes/header.php`. Tambahkan satu baris setelah tag `<meta viewport>`:

```php
<?php
$pageTitle = $pageTitle ?? 'Absensi SMAN 10';
$basePath = str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 2);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — SMAN 10 Tangerang Selatan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $basePath ?>assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">
```

- [ ] **Step 2: Verify Bootstrap Icons load**

Buka `http://localhost/absensi/modules/auth/login.php` di browser.
Buka DevTools → Network → cari `bootstrap-icons.min.css`.
Expected: status 200.

- [ ] **Step 3: Create includes/auth_check.php**

```php
<?php

function require_auth(array $roles = []): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        header('Location: /absensi/modules/auth/login.php');
        exit;
    }

    if (!empty($roles) && !in_array($_SESSION['role'], $roles, true)) {
        header('Location: /absensi/dashboard.php');
        exit;
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add includes/header.php includes/auth_check.php
git commit -m "feat: add bootstrap icons and auth guard"
```

---

## Task 3: Login Handler & Logout

**Files:**
- Create: `modules/auth/process_login.php`
- Create: `modules/auth/logout.php`

- [ ] **Step 1: Create modules/auth/process_login.php**

```php
<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

require_once '../../config/database.php';

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    header('Location: login.php?error=Email+dan+password+wajib+diisi');
    exit;
}

$pdo  = getDB();
$stmt = $pdo->prepare(
    'SELECT id, nama, password, role, foto_profil FROM users WHERE email = ? LIMIT 1'
);
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    header('Location: login.php?error=Email+atau+password+salah');
    exit;
}

session_regenerate_id(true);

$_SESSION['user_id'] = $user['id'];
$_SESSION['role']    = $user['role'];
$_SESSION['nama']    = $user['nama'];
$_SESSION['foto']    = $user['foto_profil'];

header('Location: /absensi/dashboard.php');
exit;
```

- [ ] **Step 2: Create modules/auth/logout.php**

```php
<?php
session_start();
session_destroy();
header('Location: /absensi/modules/auth/login.php');
exit;
```

- [ ] **Step 3: Commit**

```bash
git add modules/auth/process_login.php modules/auth/logout.php
git commit -m "feat: add login handler and logout"
```

---

## Task 4: Update Login Page

**Files:**
- Modify: `modules/auth/login.php`

- [ ] **Step 1: Replace seluruh isi login.php**

```php
<?php
$pageTitle = 'Login';
$basePath  = '../../';
require_once $basePath . 'includes/header.php';

$error = $_GET['error'] ?? '';
?>

<div class="flex min-h-screen">

    <!-- Left Branding Panel -->
    <div class="hidden lg:flex lg:w-1/2 flex-col items-center justify-center gap-8 p-12"
         style="background: linear-gradient(135deg, #0084d4 0%, #005a94 100%);">

        <img src="<?= $basePath ?>assets/images/logo.png"
             alt="Logo SMAN 10"
             class="w-40 h-40 object-contain drop-shadow-xl">

        <div class="text-center text-white">
            <h1 class="text-4xl font-bold leading-tight">Sistem Absensi</h1>
            <h2 class="text-4xl font-bold leading-tight">SMA 10 Tangerang</h2>
        </div>

    </div>

    <!-- Right Form Panel -->
    <div class="w-full lg:w-1/2 flex flex-col bg-white px-8 py-12">

        <div class="flex-1 flex flex-col items-center justify-center">
        <div class="w-full max-w-sm">

            <h2 class="text-2xl font-bold text-gray-800 mb-1">Selamat Datang Kembali!</h2>
            <p class="text-gray-400 text-sm mb-8 leading-snug">
                Silahkan Masuk Ke Akun Anda Untuk Mengelola Presensi Siswa
            </p>

            <?php if ($error): ?>
            <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg">
                <i class="bi bi-exclamation-circle me-1"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form action="process_login.php" method="POST" novalidate>

                <!-- Email -->
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">
                        Email
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">
                            <i class="bi bi-person"></i>
                        </span>
                        <input
                            type="email"
                            name="email"
                            placeholder="Masukan Email Anda Disini"
                            required
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            class="w-full pl-9 pr-4 py-3 text-sm border border-gray-200 rounded-lg bg-gray-50 placeholder-gray-300 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary focus:bg-white transition"
                        >
                    </div>
                </div>

                <!-- Kata Sandi -->
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">
                        Kata Sandi
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">
                            <i class="bi bi-lock"></i>
                        </span>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            required
                            class="w-full pl-9 pr-10 py-3 text-sm border border-gray-200 rounded-lg bg-gray-50 placeholder-gray-300 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary focus:bg-white transition"
                        >
                        <button
                            type="button"
                            onclick="togglePassword()"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition text-base"
                            tabindex="-1"
                        >
                            <i id="eye-icon" class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Ingat Saya + Lupa Password -->
                <div class="flex items-center justify-between mb-6">
                    <label class="flex items-center gap-2 text-sm text-gray-500 cursor-pointer select-none">
                        <input type="checkbox" name="remember" class="rounded border-gray-300 text-primary focus:ring-primary">
                        Ingat Saya
                    </label>
                    <a href="#" class="text-sm font-medium text-primary hover:underline">Lupa Password?</a>
                </div>

                <!-- Submit -->
                <button
                    type="submit"
                    class="w-full bg-primary hover:bg-primary/90 active:scale-[.98] text-white font-semibold py-3 rounded-lg flex items-center justify-center gap-2 transition"
                >
                    Masuk
                    <i class="bi bi-arrow-right"></i>
                </button>

            </form>
        </div>
        </div>

        <p class="text-xs text-gray-300 text-center tracking-widest uppercase">
            &copy; 2026 Sistem Absensi SMAN 10 Tangerang Selatan. All rights reserved.
        </p>

    </div>

</div>

<script>
function togglePassword() {
    const input = document.getElementById('password');
    const icon  = document.getElementById('eye-icon');
    const show  = input.type === 'password';
    input.type  = show ? 'text' : 'password';
    icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
}
</script>

<?php require_once $basePath . 'includes/footer.php'; ?>
```

- [ ] **Step 2: Rebuild CSS**

```bash
cd C:/xampp_kmp/htdocs/absensi && npm run build
```

Expected: Done in Xms, no errors.

- [ ] **Step 3: Verify login page di browser**

Buka `http://localhost/absensi/modules/auth/login.php`.
Expected:
- Icon `bi-person` muncul di field email (bukan SVG path)
- Icon `bi-lock` muncul di field password
- Tidak ada link "Daftar Disini"
- Toggle password berfungsi (eye ↔ eye-slash)

- [ ] **Step 4: Commit**

```bash
git add modules/auth/login.php assets/css/style.css
git commit -m "feat: update login page with bootstrap icons"
```

---

## Task 5: Sidebar & Navbar

**Design reference:** Sidebar putih dengan menu aktif biru, navbar dengan hamburger + search + user info di kanan.

**Files:**
- Create: `includes/sidebar.php`
- Create: `includes/navbar.php`

- [ ] **Step 1: Create includes/sidebar.php**

```php
<?php
$role        = $_SESSION['role'] ?? '';
$currentPath = $_SERVER['PHP_SELF'];

function sidebarLink(string $href, string $icon, string $label, string $current): string
{
    $isActive = basename($current) === basename($href) ||
                (basename($href) !== 'dashboard.php' && str_contains($current, dirname($href)));
    $active = $isActive
        ? 'bg-primary text-white shadow-sm'
        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900';

    return "<a href=\"{$href}\" class=\"flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition {$active}\">
                <i class=\"bi {$icon} text-base\"></i> {$label}
            </a>";
}
?>

<aside id="sidebar" class="w-64 min-h-screen flex flex-col shrink-0 bg-white border-r border-gray-200 transition-all duration-300">

    <!-- Brand -->
    <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-100">
        <img src="/absensi/assets/images/logo.png" alt="Logo" class="w-10 h-10 object-contain shrink-0">
        <div class="leading-tight overflow-hidden">
            <p class="text-primary font-bold text-sm truncate">SMA 10 Tangerang</p>
            <p class="text-gray-400 text-xs">Sistem Absensi</p>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">

        <?= sidebarLink('/absensi/dashboard.php', 'bi-grid-fill', 'Dashboard', $currentPath) ?>

        <?php if ($role === 'admin'): ?>
        <?= sidebarLink('/absensi/modules/students/index.php', 'bi-people', 'Data Siswa', $currentPath) ?>
        <?= sidebarLink('/absensi/modules/attendance/scan.php', 'bi-qr-code-scan', 'Pemindai QR', $currentPath) ?>
        <?= sidebarLink('/absensi/modules/attendance/history.php', 'bi-journal-text', 'Log Kehadiran', $currentPath) ?>
        <?= sidebarLink('/absensi/modules/reports/index.php', 'bi-bar-chart-line', 'Laporan', $currentPath) ?>
        <?= sidebarLink('/absensi/modules/parents/index.php', 'bi-people-fill', 'Pantauan Orang Tua', $currentPath) ?>
        <?php endif; ?>

        <?php if ($role === 'guru'): ?>
        <?= sidebarLink('/absensi/modules/attendance/scan.php', 'bi-qr-code-scan', 'Pemindai QR', $currentPath) ?>
        <?= sidebarLink('/absensi/modules/attendance/history.php', 'bi-journal-text', 'Log Kehadiran', $currentPath) ?>
        <?= sidebarLink('/absensi/modules/reports/index.php', 'bi-bar-chart-line', 'Laporan', $currentPath) ?>
        <?php endif; ?>

        <?php if ($role === 'siswa'): ?>
        <?= sidebarLink('/absensi/modules/attendance/my-attendance.php', 'bi-journal-text', 'Log Kehadiran', $currentPath) ?>
        <?= sidebarLink('/absensi/modules/profile/index.php', 'bi-person-circle', 'Profil Saya', $currentPath) ?>
        <?php endif; ?>

        <?php if ($role === 'orang_tua'): ?>
        <?= sidebarLink('/absensi/modules/parents/report.php', 'bi-people-fill', 'Pantauan Anak', $currentPath) ?>
        <?php endif; ?>

    </nav>

    <!-- Logout -->
    <div class="px-3 py-4 border-t border-gray-100">
        <a href="/absensi/modules/auth/logout.php"
           class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-red-500 hover:bg-red-50 transition">
            <i class="bi bi-box-arrow-left text-base"></i> Logout
        </a>
    </div>

</aside>
```

- [ ] **Step 2: Create includes/navbar.php**

```php
<?php
$roleLabels = [
    'admin'     => 'Kepala Admin',
    'guru'      => 'Guru',
    'siswa'     => 'Siswa',
    'orang_tua' => 'Orang Tua',
];

$role    = $_SESSION['role'] ?? 'guest';
$nama    = $_SESSION['nama'] ?? '';
$foto    = $_SESSION['foto'] ?? null;
$initial = mb_strtoupper(mb_substr($nama, 0, 1));
?>

<header class="bg-white border-b border-gray-200 px-4 py-3 flex items-center gap-4 sticky top-0 z-10">

    <!-- Hamburger toggle -->
    <button onclick="toggleSidebar()"
            class="text-gray-500 hover:text-gray-700 p-1.5 rounded-lg hover:bg-gray-100 transition shrink-0">
        <i class="bi bi-list text-xl"></i>
    </button>

    <!-- Search bar -->
    <div class="flex-1 max-w-md">
        <div class="relative">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input type="text"
                   placeholder="Cari siswa atau kelas..."
                   class="w-full pl-9 pr-4 py-2 text-sm bg-gray-100 border-0 rounded-full placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:bg-white transition">
        </div>
    </div>

    <!-- User info -->
    <div class="ml-auto flex items-center gap-3 shrink-0">
        <div class="text-right hidden sm:block">
            <p class="text-sm font-semibold text-gray-800 leading-tight"><?= htmlspecialchars($nama) ?></p>
            <p class="text-xs text-gray-400 uppercase tracking-wide"><?= $roleLabels[$role] ?? $role ?></p>
        </div>
        <?php if ($foto): ?>
            <img src="/absensi/uploads/<?= htmlspecialchars($foto) ?>"
                 alt="Foto" class="w-10 h-10 rounded-full object-cover border-2 border-gray-200">
        <?php else: ?>
            <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-bold text-sm shrink-0">
                <?= htmlspecialchars($initial) ?>
            </div>
        <?php endif; ?>
    </div>

</header>
```

- [ ] **Step 3: Commit**

```bash
git add includes/sidebar.php includes/navbar.php
git commit -m "feat: add white sidebar and topbar navbar"
```

---

## Task 6: Dashboard

**Design reference:** Heading "Hallo, [Nama]!", 3 stat cards (Total Siswa, Hadir, Absen), tabel Log Kehadiran terbaru, Ringkasan Mingguan dengan bar chart (Chart.js).

**Files:**
- Create: `dashboard.php`

- [ ] **Step 1: Create dashboard.php di root project**

```php
<?php
require_once 'includes/auth_check.php';
require_auth(['admin', 'guru', 'siswa', 'orang_tua']);

$pageTitle = 'Dashboard';
require_once 'includes/header.php';

$role = $_SESSION['role'];
$nama = $_SESSION['nama'];

// Stats (query real data, default 0 if tables empty)
$pdo = null;
$totalSiswa  = 0;
$hadirHariIni = 0;
$absenHariIni = 0;
$recentLogs   = [];

try {
    require_once 'config/database.php';
    $pdo = getDB();
    $today = date('Y-m-d');

    if (in_array($role, ['admin', 'guru'])) {
        $totalSiswa   = (int) $pdo->query('SELECT COUNT(*) FROM siswa WHERE status = "aktif"')->fetchColumn();
        $hadirHariIni = (int) $pdo->query("SELECT COUNT(*) FROM absensi WHERE tanggal_absensi = '{$today}' AND status_kehadiran = 'hadir'")->fetchColumn();
        $absenHariIni = (int) $pdo->query("SELECT COUNT(*) FROM absensi WHERE tanggal_absensi = '{$today}' AND status_kehadiran IN ('izin','sakit','alfa')")->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT s.nama_siswa, k.nama_kelas, a.waktu_scan, a.status_kehadiran, s.foto
            FROM absensi a
            JOIN siswa s ON a.siswa_id = s.id
            LEFT JOIN kelas k ON s.kelas_id = k.id
            ORDER BY a.id DESC
            LIMIT 5
        ");
        $stmt->execute();
        $recentLogs = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // Tables not yet seeded — show zeros
}

$statusBadge = [
    'hadir'     => 'bg-blue-100 text-blue-700',
    'terlambat' => 'bg-orange-100 text-orange-700',
    'izin'      => 'bg-yellow-100 text-yellow-700',
    'sakit'     => 'bg-purple-100 text-purple-700',
    'alfa'      => 'bg-red-100 text-red-700',
];
?>

<div class="flex min-h-screen bg-gray-50">

    <?php require_once 'includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0">

        <?php require_once 'includes/navbar.php'; ?>

        <main class="flex-1 p-6 space-y-6">

            <!-- Heading -->
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Hallo, <?= htmlspecialchars($nama) ?>!</h2>
                <p class="text-gray-400 text-sm mt-0.5">
                    Berikut adalah Ringkasan kehadiran hari ini,
                    <?= strftime('%A, %d %B %Y') ?? date('l, d F Y') ?>
                </p>
            </div>

            <?php if (in_array($role, ['admin', 'guru'])): ?>

            <!-- Stat Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Total Siswa Terdaftar</p>
                        <p class="text-4xl font-bold text-gray-800"><?= number_format($totalSiswa, 0, ',', '.') ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-500 text-2xl shrink-0">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Hadir Hari Ini</p>
                        <p class="text-4xl font-bold text-green-500"><?= number_format($hadirHariIni, 0, ',', '.') ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center text-green-500 text-2xl shrink-0">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Absen Hari Ini</p>
                        <p class="text-4xl font-bold text-red-500"><?= number_format($absenHariIni, 0, ',', '.') ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-red-50 flex items-center justify-center text-red-500 text-2xl shrink-0">
                        <i class="bi bi-x-circle-fill"></i>
                    </div>
                </div>

            </div>

            <!-- Log + Weekly Summary -->
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

                <!-- Log Kehadiran Terbaru -->
                <div class="lg:col-span-3 bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-800">Log Kehadiran baru</h3>
                        <a href="/absensi/modules/attendance/history.php"
                           class="text-sm text-primary font-medium hover:underline flex items-center gap-1">
                            Lihat Semua <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>

                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-gray-400 uppercase tracking-wide border-b border-gray-100">
                                <th class="pb-3 text-left font-medium">Nama Siswa</th>
                                <th class="pb-3 text-left font-medium">Kelas</th>
                                <th class="pb-3 text-left font-medium">Waktu</th>
                                <th class="pb-3 text-left font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php if (empty($recentLogs)): ?>
                            <tr>
                                <td colspan="4" class="py-8 text-center text-gray-300 text-sm">
                                    <i class="bi bi-inbox text-3xl block mb-2"></i>
                                    Belum ada data kehadiran hari ini
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td class="py-3 flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 shrink-0 text-xs font-bold">
                                        <?= mb_strtoupper(mb_substr($log['nama_siswa'], 0, 1)) ?>
                                    </div>
                                    <span class="font-medium text-gray-700 truncate max-w-[120px]">
                                        <?= htmlspecialchars($log['nama_siswa']) ?>
                                    </span>
                                </td>
                                <td class="py-3 text-gray-500"><?= htmlspecialchars($log['nama_kelas'] ?? '-') ?></td>
                                <td class="py-3 text-gray-500"><?= $log['waktu_scan'] ? substr($log['waktu_scan'], 0, 5) . ' WIB' : '—' ?></td>
                                <td class="py-3">
                                    <?php $s = $log['status_kehadiran']; ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $statusBadge[$s] ?? 'bg-gray-100 text-gray-600' ?>">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current opacity-70"></span>
                                        <?= strtoupper($s) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Ringkasan Mingguan -->
                <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <h3 class="font-semibold text-gray-800 mb-1">Ringkasan Mingguan</h3>
                    <p class="text-xs text-gray-400 mb-4">Rerata kehadiran 7 hari terakhir</p>
                    <div class="mb-1">
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Rerata Kehadiran</p>
                        <p class="text-2xl font-bold text-gray-800" id="avg-rate">—%</p>
                    </div>
                    <canvas id="weeklyChart" height="160"></canvas>
                    <div class="flex items-center gap-4 mt-3 text-xs text-gray-400">
                        <span class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-sm bg-primary inline-block"></span> Minggu Ini
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-sm bg-blue-100 inline-block"></span> Minggu Lalu
                        </span>
                    </div>
                </div>

            </div>

            <?php else: ?>

            <!-- View untuk Siswa & Orang Tua -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 text-center">
                <i class="bi bi-calendar-check text-5xl text-primary/30 block mb-3"></i>
                <p class="text-gray-500">Pilih menu di sidebar untuk melihat data kehadiran.</p>
            </div>

            <?php endif; ?>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Sidebar toggle
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('w-64');
    sidebar.classList.toggle('w-0');
    sidebar.classList.toggle('overflow-hidden');
}

<?php if (in_array($role, ['admin', 'guru'])): ?>
// Weekly chart (dummy data — replaced by real data in reports sprint)
const days  = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum'];
const thisW = [88, 92, 85, 94, 90];
const lastW = [82, 87, 80, 88, 85];
const avg   = (thisW.reduce((a,b) => a+b, 0) / thisW.length).toFixed(1);

document.getElementById('avg-rate').textContent = avg + '%';

new Chart(document.getElementById('weeklyChart'), {
    type: 'bar',
    data: {
        labels: days,
        datasets: [
            {
                label: 'Minggu Ini',
                data: thisW,
                backgroundColor: '#0070ba',
                borderRadius: 6,
                borderSkipped: false,
            },
            {
                label: 'Minggu Lalu',
                data: lastW,
                backgroundColor: '#bfdbfe',
                borderRadius: 6,
                borderSkipped: false,
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                min: 60,
                max: 100,
                ticks: { callback: v => v + '%', font: { size: 10 } },
                grid: { color: '#f3f4f6' }
            },
            x: { grid: { display: false }, ticks: { font: { size: 11 } } }
        }
    }
});
<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>
```

- [ ] **Step 2: Rebuild CSS**

```bash
cd C:/xampp_kmp/htdocs/absensi && npm run build
```

Expected: Done, no errors.

- [ ] **Step 3: Commit**

```bash
git add dashboard.php assets/css/style.css
git commit -m "feat: add role-based dashboard with stats, log table, weekly chart"
```

---

## Task 7: End-to-End Verification

- [ ] **Step 1: Test login sebagai admin**

Buka `http://localhost/absensi/`
Expected: redirect ke `modules/auth/login.php`.

Masukkan:
- Email: `admin@sman10.sch.id`
- Password: `admin123`

Klik Masuk.
Expected: redirect ke `http://localhost/absensi/dashboard.php`.

- [ ] **Step 2: Verify dashboard admin**

Expected di dashboard:
- Sidebar muncul dengan menu: Dashboard, Manajemen User, Manajemen Siswa, Manajemen Kelas, Rekap Kehadiran, Profil Saya
- Navbar menampilkan nama "Administrator" dan badge "Administrator"
- Welcome card dengan icon shield dan pesan yang sesuai
- Jam berjalan live

- [ ] **Step 3: Test akses langsung tanpa login**

Buka tab baru → `http://localhost/absensi/dashboard.php`
Expected: redirect ke halaman login (auth guard bekerja).

- [ ] **Step 4: Test logout**

Klik "Keluar" di sidebar.
Expected: redirect ke login, session terhapus.

Coba akses kembali `http://localhost/absensi/dashboard.php`.
Expected: redirect ke login.

- [ ] **Step 5: Test wrong credentials**

Di login page, masukkan password salah.
Expected: muncul pesan error "Email atau password salah" di atas form.

- [ ] **Step 6: Commit final**

```bash
git add -A
git commit -m "chore: auth sprint complete"
```

---

## Completion Checklist

- [ ] Semua tabel terbuat di database `absensi`
- [ ] Login dengan `admin@sman10.sch.id` / `admin123` berhasil
- [ ] Dashboard menampilkan sidebar dengan menu admin lengkap
- [ ] Auth guard memblokir akses tanpa login
- [ ] Logout menghapus session dan redirect ke login
- [ ] Error message muncul saat kredensial salah
- [ ] Bootstrap Icons tampil (bukan inline SVG)
- [ ] `npm run build` sukses tanpa error
