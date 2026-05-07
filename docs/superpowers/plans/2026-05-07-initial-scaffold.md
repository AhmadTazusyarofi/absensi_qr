# Initial Scaffold + TailwindCSS Setup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Establish a working minimal project skeleton with TailwindCSS build pipeline so subsequent sprints can be built on a clean, consistent foundation.

**Architecture:** Native PHP (no framework) with TailwindCSS compiled via npm CLI. `config/database.php` returns a PDO instance. `includes/header.php` and `footer.php` are included by every page. `index.php` is a temporary proof-of-concept landing page.

**Tech Stack:** PHP 8+, MySQL (XAMPP), TailwindCSS v3 via npm, PDO, HTML5

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Create | `package.json` | npm project config + build scripts |
| Create | `tailwind.config.js` | Tailwind content scan + plugins |
| Create | `assets/css/input.css` | Tailwind directives source file |
| Generate | `assets/css/style.css` | Compiled Tailwind output (via npm build) |
| Create | `config/database.php` | PDO connection, returns `$pdo` |
| Create | `includes/header.php` | HTML head, loads style.css, accepts `$pageTitle` |
| Create | `includes/footer.php` | Closing `</body></html>` + script placeholder |
| Create | `index.php` | Temporary landing page, proof-of-concept |
| Create | `.gitignore` | Excludes node_modules |
| Create dirs | `assets/js/`, `modules/`, `uploads/students/` | Empty folders for future sprints |

---

## Task 0: Initialize Git Repository

**Files:** none (git setup only)

- [ ] **Step 1: Initialize git**

```bash
cd C:/xampp_kmp/htdocs/absensi
git init
```

Expected output: `Initialized empty Git repository in C:/xampp_kmp/htdocs/absensi/.git/`

- [ ] **Step 2: Set default branch name**

```bash
git branch -M main
```

- [ ] **Step 3: Verify git is active**

```bash
git status
```

Expected: `On branch main — No commits yet`

---

## Task 1: Initialize npm Project

**Files:**
- Create: `package.json`

- [ ] **Step 1: Run npm init**

```bash
cd C:/xampp_kmp/htdocs/absensi
npm init -y
```

Expected output: `package.json` created with default values.

- [ ] **Step 2: Install TailwindCSS and plugin**

```bash
npm install tailwindcss @tailwindcss/forms
```

Expected output: `node_modules/` created, `package-lock.json` created.

- [ ] **Step 3: Add build scripts to package.json**

Open `package.json` and replace the `"scripts"` section with:

```json
"scripts": {
  "dev": "tailwindcss -i ./assets/css/input.css -o ./assets/css/style.css --watch",
  "build": "tailwindcss -i ./assets/css/input.css -o ./assets/css/style.css --minify"
}
```

- [ ] **Step 4: Verify package.json**

Open `package.json` and confirm it contains:
- `"tailwindcss"` in dependencies
- `"@tailwindcss/forms"` in dependencies
- `"dev"` and `"build"` in scripts

- [ ] **Step 5: Commit**

```bash
git add package.json package-lock.json
git commit -m "chore: initialize npm project with tailwindcss"
```

---

## Task 2: Configure TailwindCSS

**Files:**
- Create: `tailwind.config.js`
- Create: `assets/css/input.css`

- [ ] **Step 1: Create tailwind.config.js**

Create file `tailwind.config.js` at project root:

```js
/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./**/*.php",
    "!./node_modules/**",
    "!./vendor/**",
  ],
  theme: {
    extend: {},
  },
  plugins: [
    require('@tailwindcss/forms'),
  ],
}
```

- [ ] **Step 2: Create assets/css/input.css**

Create file `assets/css/input.css`:

```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

- [ ] **Step 3: Run build to verify no errors**

```bash
npm run build
```

Expected output:
```
Done in Xms.
```
And file `assets/css/style.css` is created (non-empty, at minimum ~5KB).

- [ ] **Step 4: Commit**

```bash
git add tailwind.config.js assets/css/input.css assets/css/style.css
git commit -m "chore: add tailwindcss config and build pipeline"
```

---

## Task 3: Create Folder Structure and .gitignore

**Files:**
- Create: `.gitignore`
- Create dirs: `assets/js/`, `modules/`, `uploads/students/`

- [ ] **Step 1: Create empty directories with .gitkeep**

Run in PowerShell from project root (`C:/xampp_kmp/htdocs/absensi`):

```powershell
New-Item -ItemType Directory -Force -Path "assets/js", "modules", "uploads/students"
New-Item -ItemType File -Force -Path "assets/js/.gitkeep", "modules/.gitkeep", "uploads/students/.gitkeep"
```

Expected: directories and empty `.gitkeep` files created with no errors.

- [ ] **Step 2: Create .gitignore**

Create file `.gitignore` at project root:

```
# Dependencies
node_modules/
vendor/

# Uploads (user content)
uploads/students/*
!uploads/students/.gitkeep

# Environment
.env

# OS
.DS_Store
Thumbs.db
```

- [ ] **Step 3: Verify .gitignore excludes node_modules**

```bash
git status
```

Expected: `node_modules/` does NOT appear in untracked files.

- [ ] **Step 4: Commit**

```bash
git add .gitignore assets/js/.gitkeep modules/.gitkeep uploads/students/.gitkeep
git commit -m "chore: add folder structure and gitignore"
```

---

## Task 4: Create Database Config

**Files:**
- Create: `config/database.php`

- [ ] **Step 1: Create config/database.php**

Create file `config/database.php`:

```php
<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'absensi');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    return $pdo;
}
```

> Note: Uses a singleton `getDB()` function so the connection is created once per request. Any page needing the DB calls `$pdo = getDB();` after including this file.

- [ ] **Step 2: Verify MySQL is running in XAMPP**

Open XAMPP Control Panel and confirm Apache and MySQL show status "Running".

- [ ] **Step 3: Create the database in phpMyAdmin**

Open browser → `http://localhost/phpmyadmin`
Run SQL:

```sql
CREATE DATABASE IF NOT EXISTS absensi
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

- [ ] **Step 4: Verify connection manually**

Create a temporary file `test_db.php` at project root:

```php
<?php
require_once 'config/database.php';

try {
    $pdo = getDB();
    echo "Koneksi berhasil!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
```

Open browser → `http://localhost/absensi/test_db.php`
Expected: `Koneksi berhasil!`

Delete `test_db.php` after verification.

- [ ] **Step 5: Commit**

```bash
git add config/database.php
git commit -m "feat: add PDO database connection config"
```

---

## Task 5: Create Header and Footer Templates

**Files:**
- Modify: `includes/header.php` (currently empty)
- Modify: `includes/footer.php` (currently empty)

- [ ] **Step 1: Write includes/header.php**

Replace the contents of `includes/header.php` with:

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
    <link rel="stylesheet" href="<?= $basePath ?>assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">
```

> `$basePath` calculates the relative path back to the project root automatically, so header.php works whether included from root or from a subdirectory like `modules/auth/login.php`.

- [ ] **Step 2: Write includes/footer.php**

Replace the contents of `includes/footer.php` with:

```php
</body>
</html>
```

- [ ] **Step 3: Commit**

```bash
git add includes/header.php includes/footer.php
git commit -m "feat: add header and footer templates"
```

---

## Task 6: Create index.php Landing Page

**Files:**
- Create: `index.php`

- [ ] **Step 1: Create index.php**

Create file `index.php` at project root:

```php
<?php
$pageTitle = 'Selamat Datang';
require_once 'includes/header.php';
?>

<div class="flex flex-col items-center justify-center min-h-screen px-4">
    <div class="bg-white rounded-2xl shadow-lg p-10 max-w-md w-full text-center">

        <img
            src="assets/images/logo.png"
            alt="Logo SMAN 10"
            class="w-24 h-24 mx-auto mb-6 object-contain"
        >

        <h1 class="text-2xl font-bold text-gray-800 mb-2">
            Sistem Absensi Siswa
        </h1>

        <p class="text-gray-500 mb-1 text-sm">
            SMAN 10 Tangerang Selatan
        </p>

        <div class="mt-6 inline-flex items-center gap-2 bg-blue-50 text-blue-700 px-4 py-2 rounded-full text-sm font-medium">
            <span class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
            Sistem sedang disiapkan
        </div>

    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
```

- [ ] **Step 2: Rebuild CSS so new classes are included**

```bash
npm run build
```

Expected: `assets/css/style.css` updated, no errors.

- [ ] **Step 3: Verify in browser**

Open → `http://localhost/absensi/index.php`

Expected:
- Page loads without PHP errors
- Logo visible
- "Sistem Absensi Siswa" heading visible
- Tailwind styles applied (centered layout, white card, shadow)
- Animated blue pulse dot visible

- [ ] **Step 4: Commit**

```bash
git add index.php assets/css/style.css
git commit -m "feat: add proof-of-concept landing page with tailwind"
```

---

## Completion Checklist

After all tasks, verify:

- [ ] `http://localhost/absensi/index.php` loads correctly with Tailwind styles
- [ ] `npm run build` completes without errors
- [ ] `npm run dev` starts watcher without errors (Ctrl+C to stop)
- [ ] `config/database.php` connects successfully to `absensi` database
- [ ] `node_modules/` does not appear in `git status`
- [ ] All 6 commits are present in `git log`
