-- Jalankan satu kali pada database db_desa_pemulihan untuk mendukung kategori dan isi detail berita.
-- Berita lama tetap berada pada tabel yang sama; kategori lama memakai label umum.

ALTER TABLE `berita`
    ADD COLUMN `kategori` VARCHAR(100) NOT NULL DEFAULT 'Informasi Desa' AFTER `gambar`,
    ADD COLUMN `isi` TEXT NULL AFTER `kategori`;
