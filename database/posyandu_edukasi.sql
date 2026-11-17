-- Jalankan satu kali pada database desa setelah backup dan persetujuan.
-- Migrasi ini hanya menambahkan tabel materi edukasi Posyandu.

CREATE TABLE IF NOT EXISTS `posyandu_edukasi` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `judul` VARCHAR(150) NOT NULL,
    `konten` TEXT NOT NULL,
    `status_publikasi` ENUM('draft', 'publikasi') NOT NULL DEFAULT 'draft',
    `tanggal_publikasi` DATE NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_posyandu_edukasi_publikasi` (`status_publikasi`, `tanggal_publikasi`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
