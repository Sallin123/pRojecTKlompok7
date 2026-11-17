-- Jalankan satu kali pada database db_desa_pemulihan setelah memastikan tabel belum ada.
-- Tabel terpisah agar pengelolaan Banner Utama tidak mengubah data Profil Desa.

CREATE TABLE `beranda_banner` (
    `id` TINYINT UNSIGNED NOT NULL,
    `judul` VARCHAR(150) NOT NULL,
    `deskripsi` TEXT NOT NULL,
    `nama_file` VARCHAR(64) DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_beranda_banner_nama_file` (`nama_file`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `beranda_banner` (`id`, `judul`, `deskripsi`, `nama_file`)
VALUES (
    1,
    'DESA PEMAYAHAN',
    'Pusat Informasi, Penegakan Peraturan Desa, Sosialisasi Ketertiban Umum, dan Kanal Pengaduan Terpadu Masyarakat Desa Pemayahan.',
    NULL
);