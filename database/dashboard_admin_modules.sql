-- Jalankan satu kali melalui phpMyAdmin pada database db_desa setelah backup.
-- File ini hanya membuat tabel tambahan untuk modul Bansos dan Perangkat Desa.
-- Tidak membuat ulang atau mengubah tabel admin, aduan, surat, maupun penduduk.

CREATE TABLE `bansos_program` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nama_program` VARCHAR(150) NOT NULL,
    `deskripsi` TEXT NULL,
    `status_program` ENUM('aktif', 'arsip') NOT NULL DEFAULT 'aktif',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_bansos_program_status_nama` (`status_program`, `nama_program`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bansos_periode` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `program_id` INT UNSIGNED NOT NULL,
    `nama_periode` VARCHAR(100) NOT NULL,
    `tanggal_mulai` DATE NULL,
    `tanggal_selesai` DATE NULL,
    `status_periode` ENUM('aktif', 'arsip') NOT NULL DEFAULT 'aktif',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_bansos_periode_program_nama` (`program_id`, `nama_periode`),
    KEY `idx_bansos_periode_status_tanggal` (`status_periode`, `tanggal_mulai`),
    CONSTRAINT `fk_bansos_periode_program`
        FOREIGN KEY (`program_id`) REFERENCES `bansos_program` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bansos_penerima` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `periode_id` INT UNSIGNED NOT NULL,
    `penduduk_id` INT UNSIGNED NOT NULL,
    `status_penerima` ENUM('terdaftar', 'disalurkan', 'dibatalkan') NOT NULL DEFAULT 'terdaftar',
    `tanggal_penyaluran` DATE NULL,
    `catatan` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_bansos_penerima_periode_penduduk` (`periode_id`, `penduduk_id`),
    KEY `idx_bansos_penerima_status` (`status_penerima`),
    KEY `idx_bansos_penerima_penduduk` (`penduduk_id`),
    CONSTRAINT `fk_bansos_penerima_periode`
        FOREIGN KEY (`periode_id`) REFERENCES `bansos_periode` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_bansos_penerima_penduduk`
        FOREIGN KEY (`penduduk_id`) REFERENCES `penduduk` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `perangkat_desa` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nama_lengkap` VARCHAR(120) NOT NULL,
    `jabatan` VARCHAR(120) NOT NULL,
    `informasi` TEXT NULL,
    `foto` VARCHAR(255) NULL,
    `urutan` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `status_data` ENUM('aktif', 'arsip') NOT NULL DEFAULT 'aktif',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_perangkat_status_urutan` (`status_data`, `urutan`, `nama_lengkap`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
