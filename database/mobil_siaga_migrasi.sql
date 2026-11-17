-- =====================================================
-- MIGRASI DATABASE MOBIL SIAGA DESA PEMAYAHAN
-- Database: db_desa_pemulihan
-- Kendaraan: 1 unit
-- Target: MariaDB 10.4.32
--
-- File ini ditujukan untuk migrasi manual setelah ditinjau.
-- Tidak ada data contoh atau data awal yang dimasukkan.
--
-- Prasyarat:
-- - Tabel mobil_siaga_profil yang sudah ada memiliki kolom lama
--   nama_petugas dan kontak_petugas.
-- - Tabel tersebut tetap menjadi profil kendaraan utama.
-- - Tabel profil memiliki PRIMARY KEY (id) dan CHECK (id = 1).
--
-- PERINGATAN:
-- Bagian CHANGE COLUMN di bawah adalah migrasi satu kali.
-- Jangan jalankan ulang setelah kolom lama berhasil diganti nama.
-- CREATE TABLE IF NOT EXISTS tidak memperbarui tabel yang sudah ada.
-- =====================================================

USE `db_desa_pemulihan`;


-- 1. MIGRASI TABEL PROFIL YANG SUDAH ADA
-- Ganti nama kolom lama untuk kontak resmi darurat.

ALTER TABLE `mobil_siaga_profil`
    CHANGE COLUMN `nama_petugas` `nama_kontak_darurat` VARCHAR(150) NULL,
    CHANGE COLUMN `kontak_petugas` `kontak_darurat` VARCHAR(30) NULL,
    ADD COLUMN IF NOT EXISTS `kapasitas_penumpang` SMALLINT UNSIGNED NULL
        AFTER `foto_file`,
    ADD COLUMN IF NOT EXISTS `status_operasional` ENUM(
        'siap_beroperasi',
        'sedang_digunakan',
        'dalam_perawatan',
        'tidak_dapat_beroperasi'
    ) NULL DEFAULT NULL
        AFTER `kontak_darurat`;


-- 2. FASILITAS KENDARAAN

CREATE TABLE IF NOT EXISTS `mobil_siaga_fasilitas` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `profil_id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `nama_fasilitas` VARCHAR(150) NOT NULL,
    `keterangan` TEXT NULL,
    `urutan` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `aktif` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_msf_profil_aktif` (`profil_id`, `aktif`),
    CONSTRAINT `fk_msf_profil`
        FOREIGN KEY (`profil_id`)
        REFERENCES `mobil_siaga_profil` (`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 3. PETUGAS

CREATE TABLE IF NOT EXISTS `mobil_siaga_petugas` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nama_petugas` VARCHAR(150) NOT NULL,
    `jenis_petugas` ENUM('pengemudi', 'pendamping') NOT NULL,
    `jabatan` VARCHAR(100) NULL,
    `no_hp` VARCHAR(30) NULL,
    `foto_file` VARCHAR(64) NULL,
    `status_tugas` ENUM('siaga', 'bertugas') NULL DEFAULT NULL,
    `aktif` TINYINT(1) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_msp_petugas_status` (`status_tugas`, `aktif`),
    CONSTRAINT `chk_msp_aktif_boolean`
        CHECK (`aktif` IS NULL OR `aktif` IN (0, 1)),
    CONSTRAINT `chk_msp_status_tugas_aktif`
        CHECK (
            `status_tugas` IS NULL
            OR (`aktif` IS NOT NULL AND `aktif` = 1)
        )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 4. JADWAL PETUGAS

CREATE TABLE IF NOT EXISTS `mobil_siaga_jadwal` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `petugas_id` BIGINT UNSIGNED NOT NULL,
    `tanggal` DATE NOT NULL,
    `jam_mulai` TIME NOT NULL,
    `jam_selesai` TIME NOT NULL,
    `catatan` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_msj_tanggal` (`tanggal`),
    INDEX `idx_msj_petugas_tanggal` (`petugas_id`, `tanggal`),
    CONSTRAINT `fk_msj_petugas`
        FOREIGN KEY (`petugas_id`)
        REFERENCES `mobil_siaga_petugas` (`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 5. RIWAYAT STATUS KENDARAAN
-- Catat hanya status kendaraan yang telah dikonfirmasi.

CREATE TABLE IF NOT EXISTS `mobil_siaga_status` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `profil_id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `status` ENUM(
        'siap_beroperasi',
        'sedang_digunakan',
        'dalam_perawatan',
        'tidak_dapat_beroperasi'
    ) NOT NULL,
    `catatan` TEXT NULL,
    `diubah_oleh` VARCHAR(100) NULL,
    `dibuat_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_mss_profil_waktu` (`profil_id`, `dibuat_at`),
    CONSTRAINT `fk_mss_profil`
        FOREIGN KEY (`profil_id`)
        REFERENCES `mobil_siaga_profil` (`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 6. PENGAJUAN MOBIL SIAGA

CREATE TABLE IF NOT EXISTS `mobil_siaga_pengajuan` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kode_pengajuan` VARCHAR(32) NOT NULL,
    `nama_pemohon` VARCHAR(150) NOT NULL,
    `no_hp_pemohon` VARCHAR(30) NOT NULL,
    `alamat_pemohon` TEXT NULL,
    `tujuan_penggunaan` VARCHAR(255) NOT NULL,
    `tanggal_penggunaan` DATE NOT NULL,
    `jam_mulai` TIME NOT NULL,
    `jam_selesai` TIME NULL,
    `jumlah_penumpang` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `catatan` TEXT NULL,
    `status` ENUM(
        'menunggu_verifikasi',
        'diproses',
        'disetujui',
        'ditolak',
        'selesai'
    ) NOT NULL DEFAULT 'menunggu_verifikasi',
    `alasan_penolakan` TEXT NULL,
    `diproses_oleh` VARCHAR(100) NULL,
    `diproses_pada` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_msp_pengajuan_kode` (`kode_pengajuan`),
    INDEX `idx_msp_pengajuan_status_tanggal` (`status`, `tanggal_penggunaan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 7. RIWAYAT PENGAJUAN

CREATE TABLE IF NOT EXISTS `mobil_siaga_pengajuan_riwayat` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pengajuan_id` BIGINT UNSIGNED NOT NULL,
    `status` ENUM(
        'menunggu_verifikasi',
        'diproses',
        'disetujui',
        'ditolak',
        'selesai'
    ) NOT NULL,
    `pesan` TEXT NULL,
    `diubah_oleh` VARCHAR(100) NULL,
    `dibuat_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_mspr_pengajuan_waktu` (`pengajuan_id`, `dibuat_at`),
    CONSTRAINT `fk_mspr_pengajuan`
        FOREIGN KEY (`pengajuan_id`)
        REFERENCES `mobil_siaga_pengajuan` (`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 8. RIWAYAT PENGGUNAAN KENDARAAN

CREATE TABLE IF NOT EXISTS `mobil_siaga_penggunaan` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `profil_id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `pengajuan_id` BIGINT UNSIGNED NULL,
    `petugas_id` BIGINT UNSIGNED NULL,
    `tujuan` VARCHAR(255) NOT NULL,
    `jumlah_penumpang` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `waktu_mulai` DATETIME NOT NULL,
    `waktu_selesai` DATETIME NULL,
    `catatan` TEXT NULL,
    `dicatat_oleh` VARCHAR(100) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_msu_pengajuan` (`pengajuan_id`),
    INDEX `idx_msu_waktu` (`waktu_mulai`),
    CONSTRAINT `fk_msu_profil`
        FOREIGN KEY (`profil_id`)
        REFERENCES `mobil_siaga_profil` (`id`)
        ON DELETE RESTRICT,
    CONSTRAINT `fk_msu_pengajuan`
        FOREIGN KEY (`pengajuan_id`)
        REFERENCES `mobil_siaga_pengajuan` (`id`)
        ON DELETE RESTRICT,
    CONSTRAINT `fk_msu_petugas`
        FOREIGN KEY (`petugas_id`)
        REFERENCES `mobil_siaga_petugas` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
