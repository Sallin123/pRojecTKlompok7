-- Rancangan tabel untuk database db_desa_pemulihan.
-- Jalankan hanya setelah mendapat persetujuan; file ini tidak menambahkan data.

CREATE TABLE IF NOT EXISTS `mobil_siaga_profil` (
    `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `nama_kendaraan` VARCHAR(150) NOT NULL,
    `deskripsi` TEXT NULL,
    `foto_file` VARCHAR(64) NULL,
    `fungsi_layanan` TEXT NULL,
    `cara_penggunaan` TEXT NULL,
    `ketentuan_penggunaan` TEXT NULL,
    `nama_petugas` VARCHAR(150) NULL,
    `kontak_petugas` VARCHAR(30) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `chk_mobil_siaga_profil_satu_baris` CHECK (`id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;