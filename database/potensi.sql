-- Jalankan satu kali melalui phpMyAdmin pada database db_desa_pemulihan setelah backup.
-- Tabel ini menyimpan konten Potensi Desa dan mempertahankan tiga kartu statis lama.

CREATE TABLE `potensi_desa` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kategori` VARCHAR(100) NOT NULL,
    `judul` VARCHAR(150) NOT NULL,
    `harga` DECIMAL(15,0) UNSIGNED NULL,
    `deskripsi` TEXT NULL,
    `nama_file` VARCHAR(64) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_potensi_desa_nama_file` (`nama_file`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `potensi_desa` (`kategori`, `judul`, `deskripsi`)
SELECT 'Sektor Pertanian', 'Pertanian & Hasil Pangan', 'Desa kami memiliki lahan pertanian subur yang menghasilkan komoditas beras, sayuran organik, dan palawija berkualitas tinggi.'
WHERE NOT EXISTS (
    SELECT 1 FROM `potensi_desa` WHERE `kategori` = 'Sektor Pertanian' AND `judul` = 'Pertanian & Hasil Pangan'
);

INSERT INTO `potensi_desa` (`kategori`, `judul`, `deskripsi`)
SELECT 'Sektor Ekonomi Kreatif', 'UMKM & Olahan Kuliner', 'Produk kerajinan tangan lokal dan jajanan tradisional hasil olahan kelompok usaha warga desa yang berdaya saing.'
WHERE NOT EXISTS (
    SELECT 1 FROM `potensi_desa` WHERE `kategori` = 'Sektor Ekonomi Kreatif' AND `judul` = 'UMKM & Olahan Kuliner'
);

INSERT INTO `potensi_desa` (`kategori`, `judul`, `deskripsi`)
SELECT 'Sektor Pariwisata', 'Wisata Alam & Kesenian', 'Kawasan wisata alam pedesaan yang asri beserta sanggar seni budaya lokal yang rutin mengadakan gelaran tradisi.'
WHERE NOT EXISTS (
    SELECT 1 FROM `potensi_desa` WHERE `kategori` = 'Sektor Pariwisata' AND `judul` = 'Wisata Alam & Kesenian'
);