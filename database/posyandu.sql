-- Jalankan satu kali pada database db_desa_pemulihan setelah persetujuan.
-- Migrasi ini hanya menambahkan tabel Posyandu; tidak mengubah tabel yang ada.

CREATE TABLE IF NOT EXISTS `posyandu_profil` (
    `id` TINYINT UNSIGNED NOT NULL,
    `nama_posyandu` VARCHAR(150) NOT NULL,
    `deskripsi` TEXT NULL,
    `lokasi` VARCHAR(255) NULL,
    `waktu_layanan` VARCHAR(150) NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `posyandu_agenda` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `jenis` ENUM('jadwal', 'kegiatan', 'imunisasi') NOT NULL,
    `judul` VARCHAR(150) NOT NULL,
    `deskripsi` TEXT NULL,
    `tanggal` DATE NOT NULL,
    `waktu_mulai` TIME NULL,
    `waktu_selesai` TIME NULL,
    `lokasi` VARCHAR(150) NULL,
    `status_publikasi` ENUM('draft', 'publikasi') NOT NULL DEFAULT 'draft',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_posyandu_agenda_publikasi` (`status_publikasi`, `jenis`, `tanggal`, `id`),
    KEY `idx_posyandu_agenda_tanggal` (`tanggal`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `posyandu_kader` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nama` VARCHAR(120) NOT NULL,
    `jabatan` VARCHAR(120) NOT NULL,
    `informasi` TEXT NULL,
    `urutan` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `status_data` ENUM('aktif', 'arsip') NOT NULL DEFAULT 'aktif',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_posyandu_kader_status_urutan` (`status_data`, `urutan`, `nama`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `posyandu_profil` (`id`, `nama_posyandu`)
VALUES (1, 'Posyandu Desa Pemayahan');
