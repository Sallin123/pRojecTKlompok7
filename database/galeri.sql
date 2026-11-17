-- Jalankan satu kali melalui phpMyAdmin pada database db_desa setelah backup.
-- Migrasi ini hanya menambahkan tabel galeri dan tidak mengubah tabel lama.

CREATE TABLE `galeri` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `judul` VARCHAR(150) NOT NULL,
    `keterangan` TEXT NULL,
    `kategori` VARCHAR(100) NOT NULL,
    `nama_file` VARCHAR(64) NOT NULL,
    `tanggal_dokumentasi` DATE NOT NULL,
    `status_publikasi` ENUM('draft', 'publikasi') NOT NULL DEFAULT 'draft',
    `admin_id` INT(11) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_galeri_nama_file` (`nama_file`),
    KEY `idx_galeri_status_tanggal` (`status_publikasi`, `tanggal_dokumentasi`, `id`),
    KEY `idx_galeri_kategori` (`kategori`),
    KEY `idx_galeri_admin` (`admin_id`),
    CONSTRAINT `fk_galeri_admin`
        FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
