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
  FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE RESTRICT,
  FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE RESTRICT
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
  waktu_scan DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE SET NULL
);

-- ============================================================
-- SEED: Admin awal (password: admin123)
-- ============================================================
INSERT INTO users (nama, email, password, role) VALUES
('Administrator', 'admin@sman10.sch.id', '$2y$10$vvSc/BJ217c399y5F0mVEezj2H9Qjd9XYWS3dYplSmLhbhRmkPWVu', 'admin');
