# CLAUDE.md

## Project Overview

This project is a web-based Student Attendance Information System using QR/Barcode scanning technology for SMAN 10 Tangerang Selatan.

The system is designed to replace manual attendance processes with a faster, more accurate, and real-time digital attendance solution.

The primary attendance workflow uses barcode scanning where teachers scan student QR/barcodes using the website camera interface.

---

# Tech Stack

## Frontend

* HTML5
* TailwindCSS

## Backend

* Native PHP (without framework)

## Database

* MySQL

---

# 👥 System Actors

## 1. Admin

Responsibilities:

* Manage users
* Manage students
* Manage classes
* Generate student barcode
* Print/download barcode cards
* View global attendance reports

---

## 2. Teacher

Primary scanner operator.

Responsibilities:

* Login to system
* Open scan attendance page
* Scan student barcode
* Validate attendance
* Input manual attendance status:
  * Izin (I)
  * Sakit (S)
  * Alfa (A)
* View attendance history

---

## 3. Student

Responsibilities:

* Show barcode identity card
* View personal attendance history

---

## 4. Parent

Responsibilities:

* Monitor child attendance
* View attendance reports in real-time

---

# 🎯 Core Features

## Authentication

* Login system
* Role-based access control
* Session management using PHP session

---

## Student Management

* Add/edit/delete student data
* Assign student to class
* Generate unique barcode
* Print barcode card

---

## Attendance System

* QR/Barcode scanning
* Real-time attendance recording
* Duplicate attendance prevention
* Attendance timestamp logging

---

## Attendance Validation

Rules:

* Barcode must exist in database
* Student can only attend once per day

Constraint:
UNIQUE(student_id, attendance_date)

---

## Attendance Status

Available statuses:

* H = Hadir
* I = Izin
* S = Sakit
* A = Alfa

Default scan result:

* Hadir

---

## Reporting

* Daily attendance report
* Weekly attendance report
* Monthly attendance report
* Export printable reports

---

# 🔄 Attendance Workflow

## 1. Barcode Generation

* Admin creates student data
* System generates unique barcode token
* Barcode stored in database
* QR code generated for printing

---

## 2. Attendance Scan Process

1. Teacher opens scan page
2. Camera activated
3. Teacher scans student barcode
4. System decodes barcode
5. System validates student data
6. System checks duplicate attendance
7. Attendance saved automatically

---

## 3. Scan Result

Success:

* Display student name
* Display attendance success message

Failure:

* Barcode not recognized
* Student already attended today

---

# 🗄️ DESAIN DATABASE (UPDATED)

---

# 📌 1. Tabel `users`

Digunakan untuk autentikasi dan hak akses sistem.

| Field       | Tipe         | Keterangan                    |
| ----------- | ------------ | ----------------------------- |
| id          | INT PK AI    | Primary Key                   |
| nama        | VARCHAR(100) | Nama pengguna                 |
| email       | VARCHAR(100) | Email login                   |
| password    | VARCHAR(100) | Password hash                 |
| role        | ENUM         | admin, guru, siswa, orang_tua |
| foto_profil | VARCHAR(255) | Foto profil                   |

---

# 📌 2. Tabel `guru`

Data guru sekolah.

| Field         | Tipe         | Keterangan               |
| ------------- | ------------ | ------------------------ |
| id            | INT PK AI    | Primary Key              |
| user_id       | INT FK       | Relasi ke tabel users    |
| nip           | VARCHAR(30)  | Nomor induk pegawai      |
| nama_guru     | VARCHAR(100) | Nama guru                |
| jenis_kelamin | ENUM         | L/P                      |
| no_hp         | VARCHAR(20)  | Nomor HP                 |
| alamat        | TEXT         | Alamat guru              |

---

# 📌 3. Tabel `kelas`

Data kelas siswa.

| Field         | Tipe        | Keterangan        |
| ------------- | ----------- | ----------------- |
| id            | INT PK AI   | Primary Key       |
| nama_kelas    | VARCHAR(50) | Contoh: XII RPL 1 |
| wali_kelas_id | INT FK      | Guru wali kelas   |
| tingkat       | VARCHAR(10) | X / XI / XII      |
| tahun_ajaran  | VARCHAR(20) | 2025/2026         |

---

# 📌 4. Tabel `siswa`

Data siswa utama.

| Field         | Tipe                | Keterangan         |
| ------------- | ------------------- | ------------------ |
| id            | INT PK AI           | Primary Key        |
| nis           | VARCHAR(30)         | Nomor induk siswa  |
| nama_siswa    | VARCHAR(100)        | Nama siswa         |
| jenis_kelamin | ENUM                | L/P                |
| kelas_id      | INT FK              | Relasi ke kelas    |
| barcode       | VARCHAR(255) UNIQUE | Barcode unik siswa |
| foto          | VARCHAR(255)        | Foto siswa         |
| alamat        | TEXT                | Alamat siswa       |
| no_hp         | VARCHAR(20)         | Nomor HP siswa     |
| status        | ENUM                | aktif/nonaktif     |

---

# 📌 5. Tabel `orang_tua`

Data orang tua siswa.

| Field          | Tipe         | Keterangan         |
| -------------- | ------------ | ------------------ |
| id             | INT PK AI    | Primary Key        |
| pengguna_id    | INT FK       | Relasi ke pengguna |
| siswa_id       | INT FK       | Relasi ke siswa    |
| nama_orang_tua | VARCHAR(100) | Nama orang tua     |
| no_hp          | VARCHAR(20)  | Nomor HP           |
| alamat         | TEXT         | Alamat             |
| hubungan       | ENUM         | ayah/ibu/wali      |

---

# 📌 6. Tabel `absensi`

Tabel utama penyimpanan absensi siswa.

| Field            | Tipe      | Keterangan            |
| ---------------- | --------- | --------------------- |
| id               | INT PK AI | Primary Key           |
| siswa_id         | INT FK    | Relasi ke siswa       |
| guru_id          | INT FK    | Guru yang scan        |
| tanggal_absensi  | DATE      | Tanggal absensi       |
| waktu_scan       | TIME      | Jam scan              |
| status_kehadiran | ENUM      | hadir/izin/sakit/alfa |
| metode_absensi   | ENUM      | barcode/manual        |
| keterangan       | TEXT      | Catatan tambahan      |

---

# 📌 7. Tabel `jadwal_absensi`

Digunakan untuk mengatur jadwal kehadiran siswa berdasarkan kelas dan waktu absensi.

| Field               | Tipe      | Keterangan                          |
| ------------------- | --------- | ----------------------------------- |
| id                  | INT PK AI | Primary Key                         |
| kelas_id            | INT FK    | Relasi ke tabel kelas               |
| hari                | ENUM      | senin-selasa-rabu-kamis-jumat-sabtu |
| jam_masuk           | TIME      | Jam mulai absensi                   |
| batas_telat         | TIME      | Batas maksimal hadir                |
| jam_selesai_absensi | TIME      | Batas akhir scan absensi            |
| status              | ENUM      | aktif/nonaktif                      |

---

# 📌 8. Tabel `log_scan`

Menyimpan riwayat proses scan barcode.

| Field      | Tipe         | Keterangan          |
| ---------- | ------------ | ------------------- |
| id         | INT PK AI    | Primary Key         |
| siswa_id   | INT FK       | Relasi siswa        |
| barcode    | VARCHAR(255) | Barcode yang discan |
| hasil_scan | ENUM         | berhasil/gagal      |
| pesan      | VARCHAR(255) | Pesan validasi      |

---

# 🎨 Frontend Guidelines

## UI Style

* Clean dashboard layout
* Responsive design
* TailwindCSS utility-first styling
* Mobile friendly
* Fast loading

---

## Main Pages

* Login Page
* Dashboard
* Student Management
* Scan Attendance Page
* Attendance Report Page
* Parent Monitoring Page

---

## Scan Page Requirements

* Full camera preview
* Animated scan frame
* Real-time feedback
* Success/error notifications
* Student information preview after scan

---

# 🔐 Security Guidelines

* Use password_hash() for password encryption
* Use prepared statements (PDO/MySQLi)
* Prevent SQL injection
* Validate all input
* Secure PHP session handling

---

# ⚡ Performance Guidelines

* Fast attendance validation
* Lightweight frontend
* Efficient database queries
* Avoid unnecessary page reloads

---

# 📂 Suggested Project Structure

/project-root
│
├── /assets
│   ├── /css
│   │   └── style.css
│   │
│   ├── /js
│   │   ├── app.js
│   │   ├── scanner.js
│   │   └── attendance.js
│   │
│   ├── /images
│   │
│   └── /qrcodes
│
├── /config
│   ├── database.php
│   ├── app.php
│   └── session.php
│
├── /core
│   ├── Database.php
│   ├── Auth.php
│   ├── Helper.php
│   └── Validator.php
│
├── /includes
│   ├── header.php
│   ├── footer.php
│   ├── navbar.php
│   ├── sidebar.php
│   └── auth_check.php
│
├── /modules
│   │
│   ├── /auth
│   │   ├── login.php
│   │   ├── logout.php
│   │   └── process_login.php
│   │
│   ├── /dashboard
│   │   └── index.php
│   │
│   ├── /students
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── edit.php
│   │   ├── delete.php
│   │   ├── save.php
│   │   ├── update.php
│   │   └── generate_qr.php
│   │
│   ├── /teachers
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── edit.php
│   │   └── delete.php
│   │
│   ├── /classes
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── edit.php
│   │   └── delete.php
│   │
│   ├── /attendance
│   │   ├── scan.php
│   │   ├── process_scan.php
│   │   ├── manual_attendance.php
│   │   ├── attendance_history.php
│   │   └── attendance_detail.php
│   │
│   ├── /reports
│   │   ├── daily.php
│   │   ├── monthly.php
│   │   ├── export_pdf.php
│   │   └── export_excel.php
│   │
│   ├── /parents
│   │   └── attendance_monitor.php
│   │
│   └── /profile
│       ├── index.php
│       └── update_profile.php
│
├── /uploads
│   ├── /students
│   └── /reports
│
├── /vendor
│
├── /database
│   └── attendance_system.sql
│
├── .htaccess
├── index.php
├── dashboard.php
└── README.md

---

# 🔁 Development Methodology

Agile Development

Sprint Priorities:

1. Authentication
2. Student CRUD
3. Barcode generation
4. Attendance scanning
5. Reporting
6. Parent monitoring

---

# ⚠️ Constraints

* Web-based only
* No mobile native application
* Internet connection required
* Barcode scanning depends on camera access

---

# 🧠 AI/Development Guidelines

* Prioritize simplicity and usability
* Keep code modular
* Avoid overengineering
* Focus on stable attendance flow
* Maintain clean PHP structure
* Ensure responsive Tailwind layout

---

# 📈 Success Indicators

* Faster attendance process
* Reduced manual errors
* Real-time attendance records
* Easy report generation
* Improved attendance transparency

---
