# Design: Initial Project Scaffold + TailwindCSS Setup

**Date:** 2026-05-07
**Project:** Sistem Informasi Absensi Siswa — SMAN 10 Tangerang Selatan
**Scope:** Sprint 0 — Foundation only (no authentication, no modules)

---

## 1. Goal

Establish a working minimal project skeleton so subsequent sprints (auth, student CRUD, barcode, attendance, reporting) can be built on a clean, consistent foundation.

---

## 2. Scope (What's Included)

- Folder structure (minimal — only what's needed now)
- TailwindCSS via npm + CLI (build pipeline)
- `config/database.php` — PDO connection
- `includes/header.php` — reusable HTML head + nav placeholder
- `includes/footer.php` — closing tags + script placeholder
- `index.php` — proof-of-concept landing page confirming Tailwind works
- `.gitignore`

**Out of scope:** Authentication, any modules, any database tables, sidebar, role-based access.

---

## 3. Folder Structure

```
/absensi
├── /assets
│   ├── /css
│   │   ├── input.css        ← Tailwind source (directives only)
│   │   └── style.css        ← Generated output (committed, no CI build step)
│   ├── /js
│   └── /images
│       └── logo.png
├── /config
│   └── database.php
├── /includes
│   ├── header.php
│   └── footer.php
├── /modules                 ← empty, filled in later sprints
├── /uploads
│   └── /students
├── tailwind.config.js
├── package.json
├── index.php
└── .gitignore
```

---

## 4. TailwindCSS Setup

- **Package:** `tailwindcss` + `@tailwindcss/forms` via npm
- **Content scan:** `"./**/*.php"` so Tailwind picks up all PHP templates
- **Input:** `assets/css/input.css` with `@tailwind base/components/utilities`
- **Output:** `assets/css/style.css` (generated, not hand-edited)
- **Scripts:**
  - `npm run dev` → watch mode for development
  - `npm run build` → minified output for production

---

## 5. Database Config (`config/database.php`)

- Driver: PDO
- Host: `localhost`
- User: `root`
- Password: `""` (XAMPP default)
- Database: `absensi`
- Charset: `utf8mb4`
- Error mode: `PDO::ERRMODE_EXCEPTION`
- Returns `$pdo` variable when included

---

## 6. Header/Footer Templates

**`includes/header.php`:**
- Accepts `$pageTitle` variable (defaults to "Absensi SMAN 10")
- Loads `assets/css/style.css`
- Meta viewport for mobile responsiveness
- No sidebar or role-specific nav yet — added in sprint 1

**`includes/footer.php`:**
- Closes `</body></html>`
- Placeholder comment for JS includes

---

## 7. index.php

Temporary proof-of-concept page using Tailwind classes. Shows:
- School logo
- System name
- "Coming Soon" message styled with Tailwind

Will be replaced by login redirect in Sprint 1.

---

## 8. Decisions & Rationale

| Decision | Rationale |
|----------|-----------|
| PDO over MySQLi | Prepared statements native, cleaner API, CLAUDE.md security guideline |
| npm over CDN | User chose option C explicitly; production-grade approach |
| `@tailwindcss/forms` included | Forms will be used heavily (login, student CRUD, attendance) |
| No `core/` or `Auth.php` yet | Out of scope for Approach A; created in Sprint 1 |
| `$pageTitle` variable in header | Avoids hardcoded titles, minimal overhead |
